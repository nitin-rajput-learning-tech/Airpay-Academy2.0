#!/usr/bin/env bash
# 05 repairs -- the post-restore repairs, in the runbook's order, each dry run before its apply.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 4 ("Post-restore repairs (MANDATORY, in order -- all idempotent, dry-run
# first)"): 4a repair_task_registrations (dry run, then --apply; the message preference check is its last line, exit 1 =
# STOP), 4b/4c the tenant registry seed and the tenant/org parity checks (TENANT_CHECKS), then 4e the ADR-032 capability
# repair (ADR-032 "Capabilities" and "Cutover slice" item 0: it runs BEFORE anything reads a role, so it comes before the
# ADR-031 role scripts of step 06, runbook 4f). Migration plan 4f-b, 4f-c, 4f-d.
#
# Not run here, on purpose: runbook step 4d (enable_oneclick_enrol.php, the SW-1 flag flip). Flipping a feature flag is a
# product decision of Nitin's, not a repair; the kit never flips one. Run it by hand after the rehearsal if wanted.
#
# The capability repair (docs/cutover/bizlms-capability-allowlist.json, signed by Nitin 2026-09-30 from the April dump):
#   inventory (writes nothing) -> check against the allow-list (exit 0 required: every grant on a capability of a missing
#   plugin is granted, already held, withheld or declined by name; exit 2 = grants nobody decided: the allow-list must be
#   re-signed against THIS backup) -> apply with --confirm=<install fingerprint> -> check again (nothing left to grant).
# Its --apply needs CLI maintenance mode, so this step turns it on first (the box serves nobody from here to step 11).

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 05 repairs "$@"

TENANT_CHECKS="${TENANT_CHECKS:-warn}"
case "$TENANT_CHECKS" in warn | stop | skip) ;; *) die "TENANT_CHECKS must be warn, stop or skip" ;; esac
CLI=local/sentientia_platform/cli

need_tool "$PHP_BIN"
require_kit_marker

if [ "$EXECUTE" = 1 ]; then
    release_now="$(db_config_value release || true)"
    [[ "$release_now" =~ $HOP2_RELEASE_REGEX ]] || die "the database reports '${release_now}': the repairs run on the 5.x target (step 04 first)"
    [ -f "$CAP_ALLOWLIST" ] || die "capability allow-list not found: ${CAP_ALLOWLIST}"
    log "capability allow-list: ${CAP_ALLOWLIST} (SHA-256 $(sha256_of "$CAP_ALLOWLIST"))"
    kv_set caps.allowlist_sha256 "$(sha256_of "$CAP_ALLOWLIST")"
else
    dry "would require a 5.x database and the signed allow-list ${CAP_ALLOWLIST}"
fi

# 0. CLI maintenance on.
run m5 ../admin/cli/maintenance.php --enable || die "cannot enable CLI maintenance mode"

# ---------------------------------------------------------------------------------------------------------------------
# Runbook 4a: repair_task_registrations.php, dry run then --apply.
# ---------------------------------------------------------------------------------------------------------------------
rc=0
timed_to "$REPORT_DIR/repair-task-registrations-dryrun.txt" "repair_task_registrations dry run" m5 "$CLI/repair_task_registrations.php" || rc=$?
if [ "$EXECUTE" = 1 ]; then
    show_tail "$REPORT_DIR/repair-task-registrations-dryrun.txt" 8
    [ "$rc" = 0 ] || die "repair_task_registrations dry run exited ${rc}"
fi
rc=0
timed_to "$REPORT_DIR/repair-task-registrations-apply.txt" "repair_task_registrations --apply" m5 "$CLI/repair_task_registrations.php" --apply || rc=$?
if [ "$EXECUTE" = 1 ]; then
    show_tail "$REPORT_DIR/repair-task-registrations-apply.txt" 12
    [ "$rc" = 0 ] || die "repair_task_registrations --apply exited ${rc} (1 = a message provider still lacks a default: STOP, review the dry-run output; the repair copies legacy keys and never deletes them)"
    grep -q 'Message preferences check: 0 problems' "$REPORT_DIR/repair-task-registrations-apply.txt" \
        || die "the message preference check did not report 0 problems"
    grep -Eq 'stale-airpay=0' "$REPORT_DIR/repair-task-registrations-apply.txt" \
        || die "stale \\local_airpay_* task rows remain (task_scheduled now: stale-airpay must be 0)"
    log "OK: message preference check 0 problems; $(grep -o 'task_scheduled now: .*' "$REPORT_DIR/repair-task-registrations-apply.txt" | tail -n 1)"
    kv_set repair.task_registrations "ok"
fi

# ---------------------------------------------------------------------------------------------------------------------
# Runbook 4b / 4c: tenant seed (the registry stays DORMANT) and the tenant / org parity checks.
# ---------------------------------------------------------------------------------------------------------------------
tenant_step() {
    # tenant_step LABEL SCRIPT [ARGS...]: warn or stop on failure, by TENANT_CHECKS.
    local label="$1" script="$2" rc=0
    shift 2
    if [ "$EXECUTE" = 1 ] && [ ! -f "$CODE_5X_DIR/public/$script" ]; then
        warn "${label}: ${script} is not in the package"
        return 0
    fi
    timed_to "$REPORT_DIR/tenant-${label// /-}.txt" "$label" m5 "$script" "$@" || rc=$?
    if [ "$EXECUTE" = 1 ]; then
        show_tail "$REPORT_DIR/tenant-${label// /-}.txt" 4
        if [ "$rc" != 0 ]; then
            if [ "$TENANT_CHECKS" = stop ]; then
                die "${label} exited ${rc}"
            fi
            warn "${label} exited ${rc} (TENANT_CHECKS=warn: not a stop; read reports/tenant-${label// /-}.txt)"
        fi
    fi
}
if [ "$TENANT_CHECKS" = skip ]; then
    note "TENANT_CHECKS=skip: runbook 4b and 4c (tenant seed, tenant and org parity) are not run"
else
    tenant_step "seed tenants dry run" local/sentientia_core/cli/seed_tenants.php --dry-run
    tenant_step "seed tenants" local/sentientia_core/cli/seed_tenants.php
    tenant_step "parity check tenants" local/sentientia_core/cli/parity_check_tenants.php
    tenant_step "parity check org" local/sentientia_core/cli/parity_check_org.php
fi

# ---------------------------------------------------------------------------------------------------------------------
# ADR-032 "Capabilities": inventory, allow-list check, apply, check again.
# ---------------------------------------------------------------------------------------------------------------------
rc=0
timed_to "$REPORT_DIR/cap-inventory.txt" "capability inventory" m5 "$CLI/repair_bizlms_capabilities.php" || rc=$?
if [ "$EXECUTE" = 1 ]; then
    head -n 1 "$REPORT_DIR/cap-inventory.txt"
    case "$rc" in
        0 | 2) log "capability inventory: exit ${rc} (the full list is in reports/cap-inventory.txt)" ;;
        *) show_tail "$REPORT_DIR/cap-inventory.txt" 10; die "capability inventory exited ${rc}" ;;
    esac
fi

rc=0
timed_to "$REPORT_DIR/cap-check.txt" "capability allow-list check" m5 "$CLI/repair_bizlms_capabilities.php" --allowlist="$CAP_ALLOWLIST" || rc=$?
if [ "$EXECUTE" = 1 ]; then
    grep -E '^Allow-list|WOULD GRANT|REFUSED|UNAPPROVED|DIVERGENT|NO EQUIVALENT|RESULT' "$REPORT_DIR/cap-check.txt" | sed 's/^/    /' || true
    case "$rc" in
        0) log "OK: every grant on a missing plugin's capability is decided" ;;
        2) die "capability allow-list check: exit 2, grants remain that nobody approved or declined. The signed allow-list is tied to the April dump; re-run the inventory on THIS backup and have Nitin re-sign it (ADR-032 Capabilities, Stage B gate 2)" ;;
        *) die "capability allow-list check exited ${rc} (1 = an allow-list line was refused; 3 = a guard refused)" ;;
    esac
fi

fp=""
if [ "$EXECUTE" = 1 ]; then
    fp="$(fingerprint5)"
    [[ "$fp" =~ ^[0-9a-f]{12}$ ]] || die "could not read the install fingerprint from import_bizlms.php --status (got '${fp}')"
    log "install fingerprint (the --confirm value): ${fp}"
    kv_set import.fingerprint "$fp"
else
    fp="<fingerprint from import_bizlms.php --status>"
fi
rc=0
timed_to "$REPORT_DIR/cap-apply.txt" "capability repair --apply" m5 "$CLI/repair_bizlms_capabilities.php" --allowlist="$CAP_ALLOWLIST" --apply --confirm="$fp" || rc=$?
if [ "$EXECUTE" = 1 ]; then
    grep -E 'Granted|WOULD GRANT|REFUSED|RESULT' "$REPORT_DIR/cap-apply.txt" | sed 's/^/    /' || true
    [ "$rc" = 0 ] || die "capability repair --apply exited ${rc} (3 = a guard refused: CLI maintenance and the fingerprint are the usual cause)"
    kv_set caps.granted "$(sed -n 's/^Granted \([0-9]*\) capability grant(s)\./\1/p' "$REPORT_DIR/cap-apply.txt" | head -n 1)"
fi
rc=0
timed_to "$REPORT_DIR/cap-recheck.txt" "capability repair re-check" m5 "$CLI/repair_bizlms_capabilities.php" --allowlist="$CAP_ALLOWLIST" || rc=$?
if [ "$EXECUTE" = 1 ]; then
    [ "$rc" = 0 ] || die "after the apply the allow-list check exits ${rc}, not 0"
    if grep -q 'WOULD GRANT' "$REPORT_DIR/cap-recheck.txt"; then
        die "after the apply the check still has grants to make: the apply did not carry them"
    fi
    log "OK: after the apply the check exits 0 with nothing left to grant"
fi

run m5 ../admin/cli/purge_caches.php || die "purge_caches failed"
log "repairs done"
