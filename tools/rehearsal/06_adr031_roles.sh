#!/usr/bin/env bash
# 06 ADR-031 role configuration in TARGET mode -- the same four scripts UAT runs, aimed at the rehearsal box.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 4f ("ADR-031 role configuration"; the capability repair of 4e runs first); migration plan 4f-f (added 2026-09-29;
# target mode 2026-09-30): predeploy probe, role-9 core capabilities (dry run, then apply), cross-tenant platform role
# (dry run, then apply), read web-service smoke. docs/operations/ROLE9-CORE-CAPS-2026-09-26.md section 10.
#
# On a restored live database the BizLMS tenant-admin role (id 9, 'administrator') arrives holding core role:manage,
# user:loginas|update|delete|create at system context; step 06 prohibits them (tenant admins lose core "Log in as": Nitin,
# 2026-09-29) and creates the platform role that carries local/sentientia_platform:crosstenant, assigned to NOBODY.
#
# Target mode: every script gets --target=<REHEARSAL_WWWROOT> --config=<the 5.x config.php>. It refuses unless the loaded
# config's wwwroot equals --target exactly and local_sentientia_platform is on disk for that config, and prints
# "TARGET MODE: wwwroot ... database ..." first; read that line. The four scripts are copied out of tools/uat/ because
# tools/uat/ is not in the deployed package.
#
# Decisions that are NOT the operator's: --accept-nonsystem-holders (the role is assigned below system context; the
# dry run prints a WARNING and the apply refuses without it) is passed only when ADR031_ACCEPT_NONSYSTEM_HOLDERS=1, which
# is Nitin's written decision. The cross-tenant role is created empty; who gets it is his call, by hand.
# Exit codes of the scripts: 0 clean, 2 done with WARNINGs (logged, not a stop), 1 refused or error (a stop).
#
# Re-running: adr031_role9_core_caps.php --apply refuses ("Already applied ... revert it first") while its state file exists in
# the dataroot, so this step does not apply role 9 twice. When the state file is there the dry run must find nothing left to
# prohibit or remove (0 capabilities, 0 allow rows), and then the apply is skipped; a dry run that still wants changes is a stop.
# The cross-tenant role script is idempotent by itself.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 06 adr031_roles "$@"

ADR031_ACCEPT_NONSYSTEM_HOLDERS="${ADR031_ACCEPT_NONSYSTEM_HOLDERS:-0}"
WORKDIR="$REHEARSAL_WORK/adr031"
CFGFILE="$CODE_5X_DIR/config.php"
need_tool "$PHP_BIN"
require_kit_marker

if [ "$EXECUTE" = 1 ]; then
    [ -f "$CFGFILE" ] || die "${CFGFILE} is missing: run step 04 first"
    check_config_file "$CFGFILE" "5.x config" || die "the 5.x config is not safe for the rehearsal"
    release_now="$(db_config_value release || true)"
    [[ "$release_now" =~ $HOP2_RELEASE_REGEX ]] || die "the database reports '${release_now}': the role scripts run on the 5.x target"
    mkdir -p "$WORKDIR"
    for s in adr031_predeploy_probe adr031_role9_core_caps adr031_crosstenant_role adr031_ws_smoke; do
        [ -f "$ADR031_SCRIPTS_DIR/$s.php" ] || die "missing ${ADR031_SCRIPTS_DIR}/${s}.php"
        cp -p "$ADR031_SCRIPTS_DIR/$s.php" "$WORKDIR/$s.php"
    done
    log "copied the four ADR-031 scripts to ${WORKDIR} (from ${ADR031_SCRIPTS_DIR}, kit $(kit_rev))"
else
    dry "would copy adr031_predeploy_probe, adr031_role9_core_caps, adr031_crosstenant_role and adr031_ws_smoke from ${ADR031_SCRIPTS_DIR} to ${WORKDIR}"
fi

TARGET_ARGS=(--target="$REHEARSAL_WWWROOT" --config="$CFGFILE")

# adr031 SCRIPT OUTNAME LABEL ARGS...: run one script in target mode; prints its exit code in $ADR_RC.
ADR_RC=0
adr031() {
    local script="$1" out="$2" label="$3"
    shift 3
    ADR_RC=0
    timed_to "$REPORT_DIR/${out}.txt" "$label" php_run "$WORKDIR/${script}.php" "${TARGET_ARGS[@]}" "$@" || ADR_RC=$?
    if [ "$EXECUTE" = 1 ]; then
        grep -E 'TARGET MODE|^  database ' "$REPORT_DIR/${out}.txt" | head -n 2 | sed 's/^/    /' || true
    fi
}

# 1. Read-only probe: the role-9 assignments by context level and the deploy gates.
adr031 adr031_predeploy_probe adr031-predeploy-probe "ADR-031 predeploy probe"
if [ "$EXECUTE" = 1 ]; then
    [ "$ADR_RC" = 0 ] || { show_tail "$REPORT_DIR/adr031-predeploy-probe.txt" 20; die "adr031_predeploy_probe exited ${ADR_RC}"; }
    grep -iE 'role 9|assign|below system|context level' "$REPORT_DIR/adr031-predeploy-probe.txt" | head -n 12 | sed 's/^/    /' || true
fi

# 2. Role 9: dry run, then apply.
adr031 adr031_role9_core_caps adr031-role9-dryrun "ADR-031 role 9 core caps dry run" --role="$TENANT_ADMIN_ROLE" --dry-run
if [ "$EXECUTE" = 1 ]; then
    case "$ADR_RC" in
        0) log "role 9 dry run: clean" ;;
        2) warn "role 9 dry run printed WARNING lines (the role is assigned below system context, or the Sentientia courses plugin is missing): read reports/adr031-role9-dryrun.txt before the apply"
           grep -E 'WARNING' "$REPORT_DIR/adr031-role9-dryrun.txt" | head -n 10 | sed 's/^/    /' || true ;;
        *) show_tail "$REPORT_DIR/adr031-role9-dryrun.txt" 20; die "adr031_role9_core_caps dry run exited ${ADR_RC}" ;;
    esac
fi
apply_args=(--role="$TENANT_ADMIN_ROLE" --apply)
if [ "$ADR031_ACCEPT_NONSYSTEM_HOLDERS" = 1 ]; then
    apply_args+=(--accept-nonsystem-holders)
    warn "ADR031_ACCEPT_NONSYSTEM_HOLDERS=1: holders of the role below system context are accepted (Nitin's decision)"
fi
# The script refuses a second --apply for as long as its state file exists ("Already applied ... revert it first": the file holds
# the prior values --revert needs). So a re-run of this step (after a later failure in it, or a whole run repeated) must not
# apply again: the dry run above has to say that nothing is left to do, and then the apply is skipped. A dry run that still
# wants changes while the state file exists means somebody re-roled it since: that is a stop, not a re-apply. With no state file
# and a dry run that has nothing to do, the apply was a no-op the first time (the script writes no state then): also skipped.
ROLE9_STATE="$MOODLEDATA/adr031_role9_core_caps_${TENANT_ADMIN_ROLE}.json"
DO_ROLE9_APPLY=1
if [ "$EXECUTE" = 1 ]; then
    counts=""
    counts="$(adr031_role9_dry_counts < "$REPORT_DIR/adr031-role9-dryrun.txt" || true)"
    if [ -f "$ROLE9_STATE" ]; then
        [ -n "$counts" ] || die "role 9 was applied before (${ROLE9_STATE}), and the dry run printed no 'DRY RUN: N capabilities ... K allow row(s)' line to prove that nothing is left to do (reports/adr031-role9-dryrun.txt)"
        [ "$counts" = "0 0" ] || die "role 9 was applied before (${ROLE9_STATE}), but the dry run now wants to prohibit/remove '${counts}' (capabilities, allow rows): the role was changed since. Read reports/adr031-role9-dryrun.txt; to start over, revert it (adr031_role9_core_caps.php --revert) and run this step again"
        log "OK: role 9 core capabilities were applied before (state ${ROLE9_STATE}) and the dry run finds nothing left to prohibit or remove: not applying again"
        DO_ROLE9_APPLY=0
    elif [ -n "$(kv_get adr031.role9_applied)" ] && [ "$counts" = "0 0" ]; then
        log "OK: role 9 core capabilities need no change (applied before; nothing left to prohibit or remove): not applying again"
        DO_ROLE9_APPLY=0
    elif [ -n "$(kv_get adr031.role9_applied)" ]; then
        note "role 9 was applied before and has been reverted since (no state file, and the dry run wants '${counts:-?}'): applying again"
        kv_unset adr031.role9_applied
    fi
fi
if [ "$DO_ROLE9_APPLY" = 1 ]; then
    adr031 adr031_role9_core_caps adr031-role9-apply "ADR-031 role 9 core caps apply" "${apply_args[@]}"
    if [ "$EXECUTE" = 1 ]; then
        case "$ADR_RC" in
            0) log "OK: role 9 core capabilities prohibited (prior values are saved under the dataroot for --revert)"
               kv_set adr031.role9_applied "$(ts)" ;;
            *) show_tail "$REPORT_DIR/adr031-role9-apply.txt" 25
               die "adr031_role9_core_caps --apply exited ${ADR_RC}. Exit 1 with 'below system context' means the role has holders at category or course level: that needs Nitin's decision (ADR031_ACCEPT_NONSYSTEM_HOLDERS=1), not the operator's" ;;
        esac
    fi
fi

# 3. The platform role that carries cross-tenant authority: dry run, then apply.
adr031 adr031_crosstenant_role adr031-crosstenant-dryrun "ADR-031 cross-tenant role dry run" --tenant-admin-role="$TENANT_ADMIN_ROLE" --dry-run
if [ "$EXECUTE" = 1 ]; then
    case "$ADR_RC" in
        0 | 2) log "cross-tenant role dry run: exit ${ADR_RC}" ;;
        *) show_tail "$REPORT_DIR/adr031-crosstenant-dryrun.txt" 20; die "adr031_crosstenant_role dry run exited ${ADR_RC}" ;;
    esac
fi
adr031 adr031_crosstenant_role adr031-crosstenant-apply "ADR-031 cross-tenant role apply" --tenant-admin-role="$TENANT_ADMIN_ROLE" --apply
if [ "$EXECUTE" = 1 ]; then
    case "$ADR_RC" in
        0) log "OK: platform role 'sentientiaplatform' in place and assigned to nobody (who gets it is Nitin's call, by hand)" ;;
        2) warn "cross-tenant role apply finished with WARNING lines: read reports/adr031-crosstenant-apply.txt"
           grep -E 'WARNING' "$REPORT_DIR/adr031-crosstenant-apply.txt" | head -n 10 | sed 's/^/    /' || true ;;
        *) show_tail "$REPORT_DIR/adr031-crosstenant-apply.txt" 25; die "adr031_crosstenant_role --apply exited ${ADR_RC}" ;;
    esac
fi

# 4. Read web services as real personas: ERROR must be 0.
adr031 adr031_ws_smoke adr031-ws-smoke "ADR-031 web-service smoke"
if [ "$EXECUTE" = 1 ]; then
    [ "$ADR_RC" = 0 ] || { show_tail "$REPORT_DIR/adr031-ws-smoke.txt" 20; die "adr031_ws_smoke exited ${ADR_RC}"; }
    summary="$(grep '^SUMMARY:' "$REPORT_DIR/adr031-ws-smoke.txt" | tail -n 1 || true)"
    [ -n "$summary" ] || die "the web-service smoke printed no SUMMARY line"
    log "${summary}"
    errors="$(printf '%s' "$summary" | sed -n 's/.*ERROR=\([0-9]*\).*/\1/p')"
    kv_set adr031.ws_smoke "$summary"
    if [ "${errors:-x}" != 0 ]; then
        grep -E '^  ERROR' "$REPORT_DIR/adr031-ws-smoke.txt" | head -n 15 | sed 's/^/    /' || true
        die "web-service smoke: ERROR=${errors:-?}, not 0 (SQL MySQL 8.4 rejects, fatal errors on ADR-031 paths, or scoping that refuses an in-tenant persona)"
    fi
    log "OK: web-service smoke ERROR=0"
else
    dry "would require the smoke's SUMMARY line to end with ERROR=0"
fi
run m5 ../admin/cli/purge_caches.php || die "purge_caches failed"
log "ADR-031 role configuration done"
