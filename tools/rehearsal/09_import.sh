#!/usr/bin/env bash
# 09 import -- the ADR-032 BizLMS import: data-intact gate, preflight, dry run, apply with a report, verify.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 5 (the data-intact gate BEFORE the import) and 5a; ADR-032 "Build and run
# order / Cutover slice" items 3 to 5, "CLI" and "Gating". All 19 importers run through the one framework
# (import_bizlms.php --all); the BizLMS legacy tables are the archive and are never written.
#
# Order:
#   1. status, and the guard must still be armed (step 08; it expires after GUARD_ARM_SECONDS);
#   2. THE GATE BEFORE THE IMPORT: migration_parity_check.php --compare=<source baseline>. Exit 0 or no import: nothing
#      but the upgrades and the repairs may have touched the data, every number and every BizLMS legacy table equals the
#      source baseline;
#   3. import_bizlms.php --preflight --all --decisions=...  (exit 0 = no blockers);
#   4. a DRY RUN with --report: its report holds meta.decisions_hash, the SHA-256 the apply must be made with. The hash is
#      recorded (state/kv/import.decisions_hash, and the summary) because cutover day passes it as --expect-decisions-hash:
#      the rehearsed decisions are the ones that run. IMPORT_EXPECT_DECISIONS_HASH, when set (cutover), must equal it;
#   5. the restore point (SNAPSHOT_HOOK "before-import": with bizlms_production = 1 it is the only way back), then
#      --all --apply --confirm=<install fingerprint> --decisions=... --expect-decisions-hash=<recorded> --report=...
#      (IMPORT_APPLY_MODE=resume continues the newest incomplete apply run instead). The apply report's decisions hash must
#      equal the recorded one, and its run id is recorded for step 10;
#   6. --verify --all.
# Exit 2 ("done but unproven": needs-owner reasons the decisions do not accept, or unclaimed legacy tables holding rows) is a
# stop, unless ACCEPT_UNPROVEN=1 with an ACCEPT_UNPROVEN_REF that names Nitin's written acceptance.
#
# RE-RUNNING (this is where the earlier version had no way forward): where the step stands is decided from the kit's record AND
# from the database's own run table (local_sentientia_legacyrun, the newest apply run's status), see import_phase in lib/common.sh:
#   * no apply run in the database                 -> fresh: items 2 to 5 in order (a guard refusal that wrote nothing leaves no run);
#   * an apply run that is not complete (the apply failed, was killed, or the guard expired) -> the data now holds imported rows, so the
#     gate, preflight and dry run (items 2 to 4) are history and are NOT run again: set IMPORT_APPLY_MODE=resume and the same
#     decisions hash (read from state/kv/import.decisions_hash, else from the run) continues the run;
#   * a complete apply run                          -> the kit records it (state/kv/import.applied, .runid, .apply_exit) BEFORE it judges
#     the exit code, so a re-run with ACCEPT_UNPROVEN=1 and ACCEPT_UNPROVEN_REF set only judges the recorded exit 2 again, then verifies.
# A completed apply is never repeated by this step: to apply again, restore the snapshot and delete state/kv/import.*.
# Timings of every part go to logs/timings.tsv: they set the maintenance window.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 09 import "$@"

IMPORT_APPLY_MODE="${IMPORT_APPLY_MODE:-apply}"
IMPORT_EXTRA_ARGS="${IMPORT_EXTRA_ARGS:-}"
IMPORT_EXPECT_DECISIONS_HASH="${IMPORT_EXPECT_DECISIONS_HASH:-}"
case "$IMPORT_APPLY_MODE" in apply | resume) ;; *) die "IMPORT_APPLY_MODE must be apply or resume" ;; esac
IMPORT_EXTRA=()
if [ -n "$IMPORT_EXTRA_ARGS" ]; then
    read -r -a IMPORT_EXTRA <<<"$IMPORT_EXTRA_ARGS"
fi
CLI=local/sentientia_platform/cli
RUN_TABLE=local_sentientia_legacyrun
need_tool "$PHP_BIN"
require_kit_marker

PHASE=fresh
RUN_STATUS=""
if [ "$EXECUTE" = 1 ]; then
    release_now="$(db_config_value release || true)"
    [[ "$release_now" =~ $HOP2_RELEASE_REGEX ]] || die "the database reports '${release_now}': the import runs on the 5.x target"
    [ -f "$IMPORT_DECISIONS" ] || die "decisions file not found: ${IMPORT_DECISIONS}"
    [ -s "$BASELINE_FILE" ] || die "no baseline at ${BASELINE_FILE}"

    # Where this step stands: the kit's record, and what the database itself says about the newest apply run.
    APPLIED="$(kv_get import.applied)"
    if [ "$(db_scalar "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}' AND TABLE_NAME = '${DB_PREFIX}${RUN_TABLE}'")" = 1 ]; then
        RUN_STATUS="$(db_first "SELECT status FROM {p}${RUN_TABLE} WHERE runmode = 'apply' ORDER BY id DESC LIMIT 1")"
    fi
    PHASE="$(import_phase "$APPLIED" "$RUN_STATUS" "$IMPORT_APPLY_MODE")"
    case "$PHASE" in
        refuse:*) die "${PHASE#refuse:}" ;;
    esac
    log "where this step stands: ${PHASE} (kit record: $([ -n "$APPLIED" ] && printf applied || printf "nothing applied"); newest apply run in the database: ${RUN_STATUS:-none}; IMPORT_APPLY_MODE=${IMPORT_APPLY_MODE})"
    if [ -n "$(kv_get import.apply_started)" ] && [ -z "$RUN_STATUS" ] && [ -z "$APPLIED" ]; then
        note "an earlier apply was started ($(kv_get import.apply_started)) but left no run in the database: it was refused before it wrote anything. Starting from the gate again"
        kv_unset import.apply_started
    fi

    log "decisions file: ${IMPORT_DECISIONS} (file SHA-256 $(sha256_of "$IMPORT_DECISIONS"))"
    kv_set import.decisions_file_sha256 "$(sha256_of "$IMPORT_DECISIONS")"
    fp="$(fingerprint5)"
    [[ "$fp" =~ ^[0-9a-f]{12}$ ]] || die "could not read the install fingerprint (got '${fp}')"
    log "install fingerprint (--confirm): ${fp}"
    imp --status > "$REPORT_DIR/import-status-before.txt" 2>&1 || die "import_bizlms.php --status failed"
    left="$(status_fact armed_seconds_left < "$REPORT_DIR/import-status-before.txt")"
    if [ "$PHASE" = fresh ] || [ "$PHASE" = resume ]; then
        # Only a run that is about to write needs the guard; step 08 sets it, and it expires.
        step_done_ok 08 || die "step 08 (import guard) has not finished ok: arm the guard first"
        [ "${left:-0}" -gt 0 ] || die "the import guard is no longer armed (armed_seconds_left=${left}): run step 08 again"
        log "guard armed for ${left}s more"
    fi
    sed -n '/importer(s) registered/,$p' "$REPORT_DIR/import-status-before.txt" | head -n 30 | sed 's/^/    /'
else
    fp="<install fingerprint>"
    dry "would require step 08 ok, a readable decisions file (${IMPORT_DECISIONS}), the baseline, and the guard still armed"
    dry "would decide from the kit's record and from the newest apply run in ${DB_PREFIX}${RUN_TABLE}: fresh (gate, preflight, dry run, apply), resume (IMPORT_APPLY_MODE=resume continues an unfinished run), or recorded (only the judgement of the recorded exit and the verify)"
fi

H=""
if [ "$PHASE" = fresh ]; then

# 2. The data-intact gate before the import.
rc=0
timed_to "$REPORT_DIR/parity-before-import.txt" "parity before import" m5 "$CLI/migration_parity_check.php" --compare="$BASELINE_FILE" || rc=$?
if [ "$EXECUTE" = 1 ]; then
    show_tail "$REPORT_DIR/parity-before-import.txt" 16
    kv_set parity.pre_import "$rc"
    judge "parity before import" "$rc"
fi

# 3. Preflight.
rc=0
timed_to "$REPORT_DIR/import-preflight.txt" "import preflight" imp --preflight --all --decisions="$IMPORT_DECISIONS" || rc=$?
if [ "$EXECUTE" = 1 ]; then
    grep -E 'BLOCKER|WARNING|^RESULT' "$REPORT_DIR/import-preflight.txt" | head -n 40 | sed 's/^/    /' || true
    [ "$rc" = 0 ] || die "import preflight exited ${rc}: blockers (reports/import-preflight.txt). Nothing was written"
    log "OK: import preflight, no blockers"
fi

# 4. Dry run, and the decisions hash.
rc=0
timed_to "$REPORT_DIR/import-dryrun.txt" "import dry run" imp --all --decisions="$IMPORT_DECISIONS" --report="$REPORT_DIR/import-dryrun.json" ${IMPORT_EXTRA[@]+"${IMPORT_EXTRA[@]}"} || rc=$?
if [ "$EXECUTE" = 1 ]; then
    show_tail "$REPORT_DIR/import-dryrun.txt" 14
    show_report_list "$REPORT_DIR/import-dryrun.json" meta.unproven "UNPROVEN items of the dry run"
    judge "import dry run" "$rc"
    H="$(kit_php json_get.php "$REPORT_DIR/import-dryrun.json" meta.decisions_hash || true)"
    [[ "$H" =~ ^[0-9a-f]{64}$ ]] || die "the dry-run report holds no decisions hash (reports/import-dryrun.json meta.decisions_hash='${H}')"
    log "DECISIONS HASH (recorded for cutover --expect-decisions-hash): ${H}"
    kv_set import.decisions_hash "$H"
    if [ -n "$IMPORT_EXPECT_DECISIONS_HASH" ] && [ "$IMPORT_EXPECT_DECISIONS_HASH" != "$H" ]; then
        die "the decisions file hashes to ${H}, not the rehearsed ${IMPORT_EXPECT_DECISIONS_HASH}: cutover must use the rehearsed decisions (a change after the rehearsal is a re-approval event)"
    fi
else
    H="<decisions hash from import-dryrun.json meta.decisions_hash>"
    dry "would record meta.decisions_hash of the dry-run report and, when IMPORT_EXPECT_DECISIONS_HASH is set, refuse a different one"
fi

elif [ "$EXECUTE" = 1 ]; then
    # resume, recorded or recover: the data is no longer the source's, so the gate, the preflight and the dry run are history. The
    # decisions the run was started with are the ones that go on.
    H="$(kv_get import.decisions_hash)"
    if [ -z "$H" ] && [ -n "$RUN_STATUS" ]; then
        H="$(db_first "SELECT decisionshash FROM {p}${RUN_TABLE} WHERE runmode = 'apply' ORDER BY id DESC LIMIT 1")"
        [ -z "$H" ] || log "the decisions hash was not recorded by the kit: taken from the newest apply run (${H})"
    fi
    [[ "$H" =~ ^[0-9a-f]{64}$ ]] || die "no decisions hash is recorded (state/kv/import.decisions_hash) and the apply run holds none: the rehearsed decisions cannot be named"
    if [ -n "$IMPORT_EXPECT_DECISIONS_HASH" ] && [ "$IMPORT_EXPECT_DECISIONS_HASH" != "$H" ]; then
        die "the run's decisions hash is ${H}, not the rehearsed ${IMPORT_EXPECT_DECISIONS_HASH}"
    fi
    log "the data-intact gate, the preflight and the dry run are history (${PHASE}); decisions hash ${H}"
fi

# 5. Apply (fresh or resume), or record a complete run the kit never saw.
if [ "$EXECUTE" != 1 ]; then
    snapshot_hook "before-import"
    timed_to "$REPORT_DIR/import-apply.txt" "import apply" imp --all --apply --confirm="$fp" \
        --decisions="$IMPORT_DECISIONS" --expect-decisions-hash="$H" --report="$REPORT_DIR/import-apply.json" \
        ${IMPORT_EXTRA[@]+"${IMPORT_EXTRA[@]}"}
elif [ "$PHASE" = fresh ] || [ "$PHASE" = resume ]; then
    apply_flag="--apply"
    if [ "$PHASE" = fresh ]; then
        # The way back, taken now (the dry run before it changed nothing, so this is the state the gate judged). Written down before
        # the apply starts, so a failed apply leaves the record that it started.
        snapshot_hook "before-import"
        kv_set import.apply_started "$(ts) decisions_hash=${H}"
    else
        apply_flag="--resume"
        log "resuming the newest apply run (${RUN_STATUS}); the restore point of the original start is the one to go back to"
    fi
    rc=0
    timed_to "$REPORT_DIR/import-apply.txt" "import ${IMPORT_APPLY_MODE}" imp --all "$apply_flag" --confirm="$fp" \
        --decisions="$IMPORT_DECISIONS" --expect-decisions-hash="$H" --report="$REPORT_DIR/import-apply.json" \
        ${IMPORT_EXTRA[@]+"${IMPORT_EXTRA[@]}"} || rc=$?
    show_tail "$REPORT_DIR/import-apply.txt" 30
    kv_set import.apply_exit "$rc"
    case "$rc" in
        0 | 2) ;;
        3) die "the import was REFUSED by a guard (exit 3): $(grep -E '^REFUSED' "$REPORT_DIR/import-apply.txt" | head -n 3 | tr '\n' ' '). If it wrote nothing the next run starts again from the gate; if the run had started, re-run step 08 and then this step with IMPORT_APPLY_MODE=resume" ;;
        1) die "the import failed (exit 1). Read reports/import-apply.txt. Data may be partly imported: continue with IMPORT_APPLY_MODE=resume (rehearsal and cutover), or go back to the snapshot taken before this step (production), or --purge-feature (rehearsal, bizlms_production not 1)" ;;
        *) die "import_bizlms.php exited ${rc} (a crash, not one of its result codes): read reports/import-apply.txt, then IMPORT_APPLY_MODE=resume or the snapshot" ;;
    esac
    AH="$(kit_php json_get.php "$REPORT_DIR/import-apply.json" meta.decisions_hash || true)"
    [ "$AH" = "$H" ] || die "the apply report's decisions hash (${AH}) is not the one the apply was asked to use (${H})"
    RUNID="$(kit_php json_get.php "$REPORT_DIR/import-apply.json" meta.runid || true)"
    [[ "$RUNID" =~ ^[0-9]+$ ]] || die "the apply report has no run id"
    # Record the finished run BEFORE judging its exit code: an exit 2 that is not accepted yet stops this step, and the re-run (with the
    # acceptance) must find the apply recorded, not run into a database the import already changed.
    kv_set import.applied "1"
    kv_set import.runid "$RUNID"
    log "OK: apply run ${RUNID} complete (exit ${rc}); report reports/import-apply.json (+ .csv of the rows not imported)"
    show_report_list "$REPORT_DIR/import-apply.json" meta.unproven "UNPROVEN items of the apply"
    judge "import apply" "$rc"
elif [ "$PHASE" = recover ]; then
    # A complete apply run is in the database, and the kit has no record of it (it was killed between the apply and its record).
    rj="$REPORT_DIR/import-apply.json"
    [ -s "$rj" ] || die "an apply run is complete in the database, but the kit never recorded it and reports/import-apply.json does not exist: read the run's rows (${DB_PREFIX}${RUN_TABLE}), then record it by hand: state/kv/import.applied=1, import.runid=<id>, import.apply_exit=<exit>"
    RUNID="$(kit_php json_get.php "$rj" meta.runid || true)"
    DBRUN="$(db_first "SELECT id FROM {p}${RUN_TABLE} WHERE runmode = 'apply' ORDER BY id DESC LIMIT 1")"
    [[ "$RUNID" =~ ^[0-9]+$ ]] && [ "$RUNID" = "$DBRUN" ] || die "reports/import-apply.json is of run '${RUNID}', the newest apply run in the database is '${DBRUN}': they are not the same run"
    AH="$(kit_php json_get.php "$rj" meta.decisions_hash || true)"
    [ "$AH" = "$H" ] || die "the apply report's decisions hash (${AH}) is not the recorded one (${H})"
    rep_exit="$(kit_php json_get.php "$rj" meta.exit || true)"
    [[ "$rep_exit" =~ ^[0-9]+$ ]] || die "the apply report holds no exit code (meta.exit)"
    kv_set import.apply_exit "$rep_exit"
    kv_set import.applied "1"
    kv_set import.runid "$RUNID"
    log "OK: recorded the complete apply run ${RUNID} from its report (exit ${rep_exit})"
    show_report_list "$rj" meta.unproven "UNPROVEN items of the apply"
    judge "import apply" "$rep_exit"
else
    # recorded: only the judgement of the recorded exit (an acceptance given since counts) and the verify remain.
    log "an apply run is recorded (run $(kv_get import.runid), exit $(kv_get import.apply_exit)): not applying again. To import again, restore the snapshot taken before step 09 and delete state/kv/import.*"
    show_report_list "$REPORT_DIR/import-apply.json" meta.unproven "UNPROVEN items of the apply"
    recorded_exit="$(kv_get import.apply_exit)"
    judge "import apply" "${recorded_exit:-0}"
fi

# 6. Verify.
rc=0
timed_to "$REPORT_DIR/import-verify.txt" "import verify" imp --verify --all --decisions="$IMPORT_DECISIONS" --expect-decisions-hash="$H" || rc=$?
if [ "$EXECUTE" = 1 ]; then
    show_tail "$REPORT_DIR/import-verify.txt" 12
    judge "import verify" "$rc"
    kv_set import.verify_exit "$rc"
fi
log "import done"
