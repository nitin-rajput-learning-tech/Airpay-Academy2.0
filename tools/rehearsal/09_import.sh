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
#   5. --all --apply --confirm=<install fingerprint> --decisions=... --expect-decisions-hash=<recorded> --report=...
#      (IMPORT_APPLY_MODE=resume continues the newest incomplete apply run instead). The apply report's decisions hash must
#      equal the recorded one, and its run id is recorded for step 10;
#   6. --verify --all.
# Exit 2 ("done but unproven": needs-owner reasons the decisions do not accept, or unclaimed legacy tables holding rows) is a
# stop, unless ACCEPT_UNPROVEN=1 with an ACCEPT_UNPROVEN_REF that names Nitin's written acceptance.
# A completed apply is never repeated by this step (state/kv/import.applied): to apply again, restore the snapshot.
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
need_tool "$PHP_BIN"


if [ "$EXECUTE" = 1 ]; then
    release_now="$(db_config_value release || true)"
    [[ "$release_now" =~ $HOP2_RELEASE_REGEX ]] || die "the database reports '${release_now}': the import runs on the 5.x target"
    [ -f "$IMPORT_DECISIONS" ] || die "decisions file not found: ${IMPORT_DECISIONS}"
    [ -s "$BASELINE_FILE" ] || die "no baseline at ${BASELINE_FILE}"
    APPLIED="$(kv_get import.applied)"
    if [ -z "$APPLIED" ]; then
        step_done_ok 08 || die "step 08 (import guard) has not finished ok: arm the guard first"
    fi
    log "decisions file: ${IMPORT_DECISIONS} (file SHA-256 $(sha256_of "$IMPORT_DECISIONS"))"
    kv_set import.decisions_file_sha256 "$(sha256_of "$IMPORT_DECISIONS")"
    fp="$(fingerprint5)"
    [[ "$fp" =~ ^[0-9a-f]{12}$ ]] || die "could not read the install fingerprint (got '${fp}')"
    log "install fingerprint (--confirm): ${fp}"
    imp --status > "$REPORT_DIR/import-status-before.txt" 2>&1 || die "import_bizlms.php --status failed"
    left="$(status_fact armed_seconds_left < "$REPORT_DIR/import-status-before.txt")"
    if [ -z "$APPLIED" ]; then
        [ "${left:-0}" -gt 0 ] || die "the import guard is no longer armed (armed_seconds_left=${left}): run step 08 again"
        log "guard armed for ${left}s more"
    fi
    sed -n '/importer(s) registered/,$p' "$REPORT_DIR/import-status-before.txt" | head -n 30 | sed 's/^/    /'
else
    fp="<install fingerprint>"
    dry "would require step 08 ok, a readable decisions file (${IMPORT_DECISIONS}), the baseline, and the guard still armed"
fi

# A re-run after the apply: the gate before the import, the preflight and the dry run describe a database that no longer exists
# (the import changed core tables on purpose), so only the verify runs.
REVERIFY=0
H=""
if [ "$EXECUTE" = 1 ] && [ -n "$(kv_get import.applied)" ]; then
    REVERIFY=1
    H="$(kv_get import.decisions_hash)"
    log "an apply run is recorded (run $(kv_get import.runid)): the data-intact gate, the preflight and the dry run are history; only the verify runs"
fi
if [ "$REVERIFY" = 0 ]; then

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

fi

# 5. Apply.
if [ "$EXECUTE" = 1 ] && [ -n "$(kv_get import.applied)" ]; then
    log "an apply run is already recorded (run $(kv_get import.runid)): not applying again. To import again, restore the snapshot taken before step 09 and delete state/kv/import.*"
else
    apply_flag="--apply"
    [ "$IMPORT_APPLY_MODE" = resume ] && apply_flag="--resume"
    rc=0
    timed_to "$REPORT_DIR/import-apply.txt" "import ${IMPORT_APPLY_MODE}" imp --all "$apply_flag" --confirm="$fp" \
        --decisions="$IMPORT_DECISIONS" --expect-decisions-hash="$H" --report="$REPORT_DIR/import-apply.json" \
        ${IMPORT_EXTRA[@]+"${IMPORT_EXTRA[@]}"} || rc=$?
    if [ "$EXECUTE" = 1 ]; then
        show_tail "$REPORT_DIR/import-apply.txt" 30
        case "$rc" in
            3) die "the import was REFUSED by a guard (exit 3): $(grep -E '^REFUSED' "$REPORT_DIR/import-apply.txt" | head -n 3 | tr '\n' ' ')" ;;
            1) die "the import failed (exit 1). Read reports/import-apply.txt. The way back is the snapshot (production) or --purge-feature / IMPORT_APPLY_MODE=resume (rehearsal)" ;;
        esac
        show_report_list "$REPORT_DIR/import-apply.json" meta.unproven "UNPROVEN items of the apply"
        judge "import apply" "$rc"
        AH="$(kit_php json_get.php "$REPORT_DIR/import-apply.json" meta.decisions_hash || true)"
        [ "$AH" = "$H" ] || die "the apply report's decisions hash (${AH}) is not the one the apply was asked to use (${H})"
        RUNID="$(kit_php json_get.php "$REPORT_DIR/import-apply.json" meta.runid || true)"
        [[ "$RUNID" =~ ^[0-9]+$ ]] || die "the apply report has no run id"
        kv_set import.applied "1"
        kv_set import.runid "$RUNID"
        kv_set import.apply_exit "$rc"
        log "OK: apply run ${RUNID} complete (exit ${rc}); report reports/import-apply.json (+ .csv of the rows not imported)"
    fi
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
