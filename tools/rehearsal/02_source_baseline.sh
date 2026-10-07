#!/usr/bin/env bash
# 02 source baseline -- the numbers every later step is held to, taken on the restored 4.1.2 copy BEFORE any upgrade.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 0 and "The parity tool, in one place" (source_baseline.php: one file, PHP
# 7.4 to 8.4, no Sentientia plugin, config read as data, read-only session on one consistent snapshot, exit 0/1/2/3);
# migration plan 4a (the authoritative baseline comes from LIVE at the freeze; a baseline from the restored copy is the
# rehearsal's weaker form) and 5.1; ADR-032 "Parity hooks" (the A1/A2 baseline: counts, value checksums, every BizLMS
# legacy table CRC'd over every row, the five core tables the import may write).
#
# Rules it enforces:
#   * the database must carry this rehearsal's kit marker (step 01 stamps it): nothing is read as "the source" from a database the kit did not restore;
#   * the database must still be the source release (SOURCE_RELEASE_REGEX): a baseline from an upgraded copy is worthless;
#   * with LIVE_BASELINE_FILE, that file IS the baseline (copied, its SHA-256 recorded); otherwise the baseline is taken
#     here, and a baseline that already exists is never overwritten: it is re-verified with --compare (exit 0 = the
#     database is still the source it describes), which makes the step idempotent.
#
# Output: baseline/source-baseline.json (BASELINE_FILE), its SHA-256 and the summary line in the log and in state/kv.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
# shellcheck source=lib/make_config.sh
. "$KIT_DIR/lib/make_config.sh"
step_init 02 source_baseline "$@"

LIVE_BASELINE_FILE="${LIVE_BASELINE_FILE:-}"
need_tool "$PHP_BIN"

[ -f "$SOURCE_BASELINE_PHP" ] || [ "$EXECUTE" != 1 ] || die "source_baseline.php not found: ${SOURCE_BASELINE_PHP}"
if [ -f "$SOURCE_BASELINE_PHP" ]; then
    log "baseline tool: ${SOURCE_BASELINE_PHP} (SHA-256 $(sha256_of "$SOURCE_BASELINE_PHP"))"
    kv_set baseline.tool_sha256 "$(sha256_of "$SOURCE_BASELINE_PHP")"
    kv_set baseline.tool_sha256_lf "$(sha256_lf_of "$SOURCE_BASELINE_PHP")"
fi

if [ "$EXECUTE" = 1 ]; then
    check_pass_file || die "DB_PASS_FILE ${DB_PASS_FILE} is missing or readable by others"
    require_kit_marker
    make_config db
    release="$(db_config_value release || true)"
    log "database release: '${release}'"
    if [[ ! "$release" =~ $SOURCE_RELEASE_REGEX ]] || step_done_ok 03; then
        # A re-run after the hops: the baseline of record must be the one that was recorded, and nothing else is done.
        if step_done_ok 02 && [ -s "$BASELINE_FILE" ] && [ "$(sha256_of "$BASELINE_FILE")" = "$(kv_get baseline.sha256)" ]; then
            log "OK: the database has moved on (release '${release}') and the baseline of record is intact (SHA-256 $(sha256_of "$BASELINE_FILE")): nothing to take or verify"
            exit 0
        fi
        die "the database reports release '${release}', not the source '${SOURCE_RELEASE_REGEX}' (or hop 1 already ran), and there is no recorded baseline: a baseline cannot be taken from a copy that has been upgraded. Restore the source again"
    fi
else
    dry "would write the baseline tool settings (${SOURCE_DB_CONFIG}) and check that the database still reports release '${SOURCE_RELEASE_REGEX}'"
fi

if [ -n "$LIVE_BASELINE_FILE" ]; then
    # The baseline of record is the one taken on live at the freeze.
    if [ "$EXECUTE" = 1 ]; then
        [ -f "$LIVE_BASELINE_FILE" ] || die "LIVE_BASELINE_FILE not found: ${LIVE_BASELINE_FILE}"
        if [ -f "$BASELINE_FILE" ]; then
            [ "$(sha256_of "$BASELINE_FILE")" = "$(sha256_of "$LIVE_BASELINE_FILE")" ] \
                || die "${BASELINE_FILE} exists and differs from LIVE_BASELINE_FILE: move one of them away"
            log "the baseline is already in place and equals LIVE_BASELINE_FILE"
        else
            cp -p "$LIVE_BASELINE_FILE" "$BASELINE_FILE"
            chmod 640 "$BASELINE_FILE"
            log "baseline of record: copied ${LIVE_BASELINE_FILE} to ${BASELINE_FILE}"
        fi
    else
        dry "would use LIVE_BASELINE_FILE (${LIVE_BASELINE_FILE}) as the baseline"
    fi
elif [ "$EXECUTE" = 1 ] && [ -f "$BASELINE_FILE" ]; then
    log "a baseline already exists (${BASELINE_FILE}); keeping it. Re-verifying that this database is still the source it describes"
else
    rc=0
    timed_to "$REPORT_DIR/baseline-take.txt" "take baseline" baseline_tool --baseline="$BASELINE_FILE" || rc=$?
    if [ "$EXECUTE" = 1 ]; then
        show_tail "$REPORT_DIR/baseline-take.txt" 8
        [ "$rc" = 0 ] || die "the baseline could not be taken (exit ${rc}); nothing was written"
        chmod 640 "$BASELINE_FILE"
    fi
fi

if [ "$EXECUTE" = 1 ]; then
    [ -s "$BASELINE_FILE" ] || die "${BASELINE_FILE} is missing or empty"
    format="$(kit_php json_get.php "$BASELINE_FILE" format || true)"
    [ "${format:-0}" -ge 2 ] || die "the baseline is JSON format '${format}'; the Stage B gates need format 2 (take it again with this version of source_baseline.php)"
    base_release="$(kit_php json_get.php "$BASELINE_FILE" release || true)"
    base_metrics="$(kit_php json_get.php "$BASELINE_FILE" tool.metrics || printf none)"
    log "baseline: format ${format}, metrics version ${base_metrics}, taken on release '${base_release}', SHA-256 $(sha256_of "$BASELINE_FILE"), $(wc -c < "$BASELINE_FILE" | tr -d ' ') bytes"
    log "baseline holds: $(kit_php json_get.php "$BASELINE_FILE" counts --count || printf '?') counts, $(kit_php json_get.php "$BASELINE_FILE" checksums --count || printf '?') value checksums, $(kit_php json_get.php "$BASELINE_FILE" legacy --count || printf '0') BizLMS legacy tables, users_total_active $(kit_php json_get.php "$BASELINE_FILE" counts.users_total_active || printf '?')"
    kv_set baseline.sha256 "$(sha256_of "$BASELINE_FILE")"
    kv_set baseline.release "$base_release"
    kv_set baseline.file "$BASELINE_FILE"
    if [ "$(kit_php json_get.php "$BASELINE_FILE" legacy --count || printf 0)" = 0 ]; then
        warn "the baseline holds no BizLMS legacy table: if this is the live copy something is wrong (live has 93)"
    fi
    # Verify: the database is still exactly what the baseline describes (also what makes a re-run idempotent).
    rc=0
    timed_to "$REPORT_DIR/baseline-verify.txt" "verify baseline against this database" baseline_tool --compare="$BASELINE_FILE" || rc=$?
    show_tail "$REPORT_DIR/baseline-verify.txt" 6
    judge "baseline vs the source it was taken from" "$rc"
    kv_set parity.source_selfcheck "$rc"
else
    dry "would verify the baseline against this database with source_baseline.php --compare (exit 0)"
fi
log "keep ${BASELINE_FILE} with the change ticket: every later step is measured against it"
