#!/usr/bin/env bash
# 10 parity compare -- everything is held to the SOURCE baseline, except what the import itself wrote, which is explained from
# the import's own records.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 5a (the post-import compare) and "The parity tool, in one place"; ADR-032
# "Parity hooks" (hook 2: the bizlms_import invariant; hook 4 made exact: --after-import); migration plan 4g.
#
#   migration_parity_check.php --compare=<source baseline> --after-import --decisions=<the rehearsed decisions>
#       --expect-decisions-hash=<the hash recorded by step 09> --run=<the apply run> --report=<the apply report>
#
# Exit 0 = 100% PARITY: every number matches the source baseline, the BizLMS legacy tables are untouched, and every change to
# a core table (user_enrolments, enrol, role_assignments, course open_* columns, tag instances) is in the import's own records.
# Exit 1 = drift or a failed invariant (stop). Exit 2 = not proven (stop, unless Nitin's written acceptance is referenced in
# ACCEPT_UNPROVEN_REF with ACCEPT_UNPROVEN=1). Exit 3 = refused (no complete apply run of this install, a decisions hash that is
# not the expected one, a run that is not a complete apply run).

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 10 parity_compare "$@"

need_tool "$PHP_BIN"
require_kit_marker

if [ "$EXECUTE" = 1 ]; then
    [ -n "$(kv_get import.applied)" ] || die "step 09 has not recorded a complete apply run (state/kv/import.applied): run the import first"
    H="$(kv_get import.decisions_hash)"
    RUNID="$(kv_get import.runid)"
    [[ "$H" =~ ^[0-9a-f]{64}$ ]] || die "no decisions hash recorded by step 09"
    [[ "$RUNID" =~ ^[0-9]+$ ]] || die "no apply run id recorded by step 09"
    [ -s "$REPORT_DIR/import-apply.json" ] || die "reports/import-apply.json is missing"
    [ -s "$BASELINE_FILE" ] || die "no baseline at ${BASELINE_FILE}"
    log "comparing with the source baseline (SHA-256 $(sha256_of "$BASELINE_FILE")), apply run ${RUNID}, decisions hash ${H}"
else
    H="<hash recorded by step 09>"
    RUNID="<apply run id recorded by step 09>"
fi

rc=0
timed_to "$REPORT_DIR/parity-after-import.txt" "parity after import" m5 local/sentientia_platform/cli/migration_parity_check.php \
    --compare="$BASELINE_FILE" --after-import --decisions="$IMPORT_DECISIONS" --expect-decisions-hash="$H" \
    --run="$RUNID" --report="$REPORT_DIR/import-apply.json" || rc=$?

if [ "$EXECUTE" = 1 ]; then
    # The sections that matter, then the verdict.
    grep -E '^Invariants|^  (OK|FAIL|SKIPPED) |^BizLMS legacy|^Import \(|UNPROVEN|REFUSED|DRIFT|RESULT' "$REPORT_DIR/parity-after-import.txt" | sed 's/^/    /' | head -n 60 || true
    kv_set parity.after_import "$rc"
    case "$rc" in
        0) log "RESULT parity after import: exit 0, 100% PARITY" ;;
    esac
    judge "parity after import" "$rc"
else
    dry "would require exit 0 (or exit 2 with ACCEPT_UNPROVEN=1 and ACCEPT_UNPROVEN_REF naming Nitin's written acceptance)"
fi
log "parity compare done"
step_end
