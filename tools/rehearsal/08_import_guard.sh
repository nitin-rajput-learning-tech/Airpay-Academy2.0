#!/usr/bin/env bash
# 08 import guard -- arm the ADR-032 guard: every condition import_bizlms.php --apply demands, set and verified.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 5a ("snapshot, maintenance on, cron off, noemailever on, arm the guard");
# ADR-032 "Gating" items 1-5b and "Build and run order / Cutover slice" item 2 ("Maintenance on, cron off, noemailever on,
# bizlms_production = 1, arm the guard").
#
# Sets and checks, in this order:
#   * the scheduled-task runner off:  admin/cli/cron.php --disable  (cron_enabled = 0; the guard fails closed on anything else)
#   * CLI maintenance mode on:         admin/cli/maintenance.php --enable  (climaintenance.html; web maintenance does not count)
#   * $CFG->noemailever true in the config.php of the 5.x tree (read as data) and as the running site sees it
#   * the standard log store enabled (tool_log/enabled_stores holds logstore_standard): the event tripwire reads its table.
#     Off = stop, or FIX_LOGSTORE=1 turns it on
#   * no restored task row marked running (a live cron's rows would make the guard refuse): CLEAR_STALE_RUNNING_TASKS=1
#     (the default) resets them and says how many
#   * local_sentientia_platform/bizlms_import_armed_until = now + GUARD_ARM_SECONDS (the runbook says 4 hours; it expires by
#     itself, and a clean --all run clears it)
#   * bizlms_production = 1 only when BIZLMS_PRODUCTION_FLAG=1 (the exact cutover form: --allow-online, --purge-feature and
#     --acknowledge-tripwire are then refused and --expect-decisions-hash is required). Default 0: a rehearsal that fails
#     can be purged and run again; set 1 for the final dress rehearsal.
# Then import_bizlms.php --status must report: maintenance true, noemailever true, standard_log true, cron_enabled false,
# running_tasks 0, armed_seconds_left > 0.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 08 import_guard "$@"

FIX_LOGSTORE="${FIX_LOGSTORE:-0}"
CLEAR_STALE_RUNNING_TASKS="${CLEAR_STALE_RUNNING_TASKS:-1}"
BIZLMS_PRODUCTION_FLAG="${BIZLMS_PRODUCTION_FLAG:-0}"
PLATFORM=local_sentientia_platform
need_tool "$PHP_BIN"
require_kit_marker

if [ "$EXECUTE" = 1 ]; then
    release_now="$(db_config_value release || true)"
    [[ "$release_now" =~ $HOP2_RELEASE_REGEX ]] || die "the database reports '${release_now}': the import runs on the 5.x target"
    check_config_file "$CODE_5X_DIR/config.php" "5.x config" || die "the 5.x config is not safe for the rehearsal"
else
    dry "would check the 5.x config.php (allow-listed database, noemailever = true, no production host)"
fi

# 1. Cron off, maintenance on.
run m5 ../admin/cli/cron.php --disable || die "cron.php --disable failed"
run m5 ../admin/cli/maintenance.php --enable || die "maintenance.php --enable failed"

# 2. noemailever as the running site sees it.
if [ "$EXECUTE" = 1 ]; then
    ne="$(cfg5 --name=noemailever --no-eol 2> /dev/null || true)"
    [ "$ne" = 1 ] || die "the running site reports noemailever='${ne}', not 1"
    log "OK: noemailever is on"
    if [ -n "$DIVERT_EMAILS_TO" ]; then
        log "belt and braces: every e-mail is also diverted to ${DIVERT_EMAILS_TO}"
    fi
fi

# 3. The standard log store.
if [ "$EXECUTE" = 1 ]; then
    stores="$(cfg5 --component=tool_log --name=enabled_stores --no-eol 2> /dev/null || true)"
    log "tool_log/enabled_stores = '${stores}'"
    case ",${stores}," in
        *,logstore_standard,*) log "OK: the standard log store is enabled" ;;
        *)
            if [ "$FIX_LOGSTORE" = 1 ]; then
                new="logstore_standard"
                [ -z "$stores" ] || new="${stores},logstore_standard"
                run cfg5 --component=tool_log --name=enabled_stores --set="$new" || die "could not enable the standard log store"
                warn "the standard log store was OFF and was enabled by FIX_LOGSTORE=1 (production has it as restored: check it there)"
            else
                die "the standard log store is not enabled (tool_log/enabled_stores='${stores}'): the import's event tripwire would be blind. Enable logstore_standard (Site administration > Plugins > Logging) or set FIX_LOGSTORE=1"
            fi
            ;;
    esac
else
    dry "would check tool_log/enabled_stores holds logstore_standard (FIX_LOGSTORE=${FIX_LOGSTORE} enables it)"
fi

# 4. No restored task row marked running.
if [ "$EXECUTE" = 1 ]; then
    ra="$(db_scalar "SELECT COUNT(*) FROM {p}task_adhoc WHERE timestarted IS NOT NULL")"
    rs="$(db_scalar "SELECT COUNT(*) FROM {p}task_scheduled WHERE timestarted IS NOT NULL")"
    log "task rows marked running: adhoc ${ra}, scheduled ${rs}"
    if [ $((ra + rs)) -gt 0 ]; then
        if [ "$CLEAR_STALE_RUNNING_TASKS" = 1 ]; then
            warn "$((ra + rs)) restored task row(s) were marked running by a cron of the live site: nothing runs them here, resetting them"
            db_write "UPDATE {p}task_adhoc SET timestarted = NULL, hostname = NULL, pid = NULL WHERE timestarted IS NOT NULL"
            db_write "UPDATE {p}task_scheduled SET timestarted = NULL, hostname = NULL, pid = NULL WHERE timestarted IS NOT NULL"
            kv_set guard.stale_running_reset "$((ra + rs))"
        else
            die "$((ra + rs)) task row(s) are marked running: the guard would refuse ('a_scheduled_or_adhoc_task_is_running'). Reset them or set CLEAR_STALE_RUNNING_TASKS=1"
        fi
    fi
else
    dry "would require no task_adhoc / task_scheduled row with timestarted set (CLEAR_STALE_RUNNING_TASKS=${CLEAR_STALE_RUNNING_TASKS} resets them)"
fi

# 5. Arm.
if [ "$EXECUTE" = 1 ]; then
    until_ts=$(( $(epoch) + GUARD_ARM_SECONDS ))
else
    until_ts="<now + ${GUARD_ARM_SECONDS}>"
fi
run cfg5 --component="$PLATFORM" --name=bizlms_import_armed_until --set="$until_ts" || die "could not arm the guard"
if [ "$BIZLMS_PRODUCTION_FLAG" = 1 ]; then
    run cfg5 --component="$PLATFORM" --name=bizlms_production --set=1 || die "could not set bizlms_production"
    note "bizlms_production = 1: the exact cutover form (no --allow-online, no --purge-feature; the way back is the snapshot)"
else
    note "bizlms_production is left as it is (BIZLMS_PRODUCTION_FLAG=0): a failed rehearsal import can be purged and repeated. Cutover sets it to 1"
fi

# 6. What the guard sees.
if [ "$EXECUTE" = 1 ]; then
    status_txt="$REPORT_DIR/import-status-armed.txt"
    m5 local/sentientia_platform/cli/import_bizlms.php --status > "$status_txt" 2>&1 || die "import_bizlms.php --status failed: $(tail -n 3 "$status_txt")"
    sed 's/^/    /' "$status_txt" | head -n 22
    fact() { status_fact "$1" < "$status_txt"; }
    [ "$(fact maintenance)" = true ] || die "the guard sees maintenance=$(fact maintenance), not true"
    [ "$(fact noemailever)" = true ] || die "the guard sees noemailever=$(fact noemailever), not true"
    [ "$(fact standard_log)" = true ] || die "the guard sees standard_log=$(fact standard_log), not true"
    [ "$(fact cron_enabled)" = false ] || die "the guard sees cron_enabled=$(fact cron_enabled), not false"
    [ "$(fact running_tasks)" = 0 ] || die "the guard sees running_tasks=$(fact running_tasks), not 0"
    left="$(fact armed_seconds_left)"
    [ "${left:-0}" -gt 0 ] || die "the guard is not armed (armed_seconds_left=${left})"
    log "OK: the guard is armed for ${left}s: maintenance on, noemailever on, standard log on, cron off, no task running"
    kv_set guard.armed_seconds "$left"
else
    dry "would run import_bizlms.php --status and require maintenance true, noemailever true, standard_log true, cron_enabled false, running_tasks 0, armed_seconds_left > 0"
fi
log "import guard armed"
step_end
