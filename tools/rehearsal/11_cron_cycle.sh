#!/usr/bin/env bash
# 11 cron cycle -- one full cron run over the restored data, with noemailever on, timed; the first sight of the restored backlog.
#
# Runbook: ADR-032 "Cutover slice" item 7 ("admin/cli/checks.php clean; purge caches; disarm; cron on; maintenance off");
# migration plan 8 step 2 (cron -- enable and prove ONE clean run with noemailever still 1: zero real-address sends; re-run the
# parity after the first cron cycle) and 4f-a (the restored task_adhoc / task_scheduled backlog must go nowhere), section 9
# gate 5 (a drained-to-zero mail backlog: the restored backlog count and zero real-address sends across a full cycle).
#
# What it does:
#   1. noemailever must be on, in the 5.x config.php (read as data) and as the running site sees it;
#   2. disarm the import guard (bizlms_import_armed_until = 0) and record the backlog before: task_adhoc by class, past-due
#      scheduled tasks, the highest notification, message and task_log ids;
#   3. lift CLI maintenance (cron.php refuses to run under it) and run ONE cycle: cron.php --force --keep-alive=0. The
#      site setting cron_enabled stays 0, so no scheduler can start a second one. Timed;
#   4. record the backlog after, count the e-mails the cycle tried to send and noemailever swallowed ("Not sending email due
#      to $CFG->noemailever"), and TIME the transfer_question_categories task (Moodle 5.0+ queues it on upgrade; its duration
#      at real volume is unknown until now): the seconds come from {task_log};
#   5. admin/cli/checks.php (informational: its exit code and lines are recorded, the cutover wants it clean);
#   6. the parity compare once more with --after-import (informational: scheduled tasks legitimately write; the point is to
#      see what a cron cycle changed, as the plan asks);
#   7. maintenance goes back ON unless MAINTENANCE_AFTER_CRON=off (lift it for the workflow smoke walk).
# It never turns cron_enabled on, never sets noemailever off, and never sends mail.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 11 cron_cycle "$@"

MAINTENANCE_AFTER_CRON="${MAINTENANCE_AFTER_CRON:-on}"
case "$MAINTENANCE_AFTER_CRON" in on | off) ;; *) die "MAINTENANCE_AFTER_CRON must be on or off" ;; esac
PLATFORM=local_sentientia_platform
need_tool "$PHP_BIN"

counts_snapshot() {
    # counts_snapshot FILE: the backlog and the append-only high-water marks.
    local f="$1"
    {
        printf 'taken: %s\n' "$(ts)"
        printf 'task_adhoc rows: %s\n' "$(db_scalar "SELECT COUNT(*) FROM {p}task_adhoc")"
        printf 'task_scheduled past due and enabled: %s\n' \
            "$(db_scalar "SELECT COUNT(*) FROM {p}task_scheduled WHERE disabled = 0 AND nextruntime <> 0 AND nextruntime < UNIX_TIMESTAMP()")"
        printf 'transfer_question_categories adhoc rows queued: %s\n' \
            "$(db_scalar "SELECT COUNT(*) FROM {p}task_adhoc WHERE classname LIKE '%transfer\\_question\\_categories%'")"
        printf 'max notifications.id: %s\n' "$(db_scalar "SELECT COALESCE(MAX(id), 0) FROM {p}notifications")"
        printf 'max messages.id: %s\n' "$(db_scalar "SELECT COALESCE(MAX(id), 0) FROM {p}messages")"
        printf 'max task_log.id: %s\n' "$(db_scalar "SELECT COALESCE(MAX(id), 0) FROM {p}task_log")"
        printf '\ntask_adhoc by class (top 25):\n'
        db_q "SELECT classname, COUNT(*) FROM {p}task_adhoc GROUP BY classname ORDER BY 2 DESC LIMIT 25"
    } > "$f"
}

if [ "$EXECUTE" = 1 ]; then
    [ -n "$(kv_get import.applied)" ] || die "step 09 has not recorded an apply run: the cron cycle follows the import"
    check_config_file "$CODE_5X_DIR/config.php" "5.x config" || die "the 5.x config is not safe for the rehearsal"
    ne="$(cfg5 --name=noemailever --no-eol 2> /dev/null || true)"
    [ "$ne" = 1 ] || die "the running site reports noemailever='${ne}': no cron cycle without it"
    log "OK: noemailever is on${DIVERT_EMAILS_TO:+; e-mails are also diverted to ${DIVERT_EMAILS_TO}}"
else
    dry "would require noemailever on (config.php as data, and the running site)"
fi

# 2. Disarm and record the backlog before.
run cfg5 --component="$PLATFORM" --name=bizlms_import_armed_until --set=0 || die "could not disarm the import guard"
if [ "$EXECUTE" = 1 ]; then
    counts_snapshot "$REPORT_DIR/cron-before.txt"
    sed -n '1,8p' "$REPORT_DIR/cron-before.txt" | sed 's/^/    /'
    kv_set cron.adhoc_before "$(sed -n 's/^task_adhoc rows: //p' "$REPORT_DIR/cron-before.txt")"
else
    dry "would write reports/cron-before.txt: task_adhoc rows by class, past-due scheduled tasks, high-water marks"
fi

# 3. One cycle.
run m5 ../admin/cli/maintenance.php --disable || die "could not lift CLI maintenance (cron.php refuses to run under it)"
rc=0
timed_to "$REPORT_DIR/cron-cycle.txt" "CRON cycle (noemailever)" m5 ../admin/cli/cron.php --force --keep-alive=0 || rc=$?
if [ "$EXECUTE" = 1 ]; then
    show_tail "$REPORT_DIR/cron-cycle.txt" 6
    # Whatever happens next, the site goes back under maintenance unless the operator wants it open.
    if [ "$MAINTENANCE_AFTER_CRON" = on ]; then
        run m5 ../admin/cli/maintenance.php --enable > /dev/null || warn "could not put CLI maintenance back on"
    fi
    [ "$rc" = 0 ] || die "cron.php exited ${rc} (reports/cron-cycle.txt)"
    grep -q 'Cron run completed correctly' "$REPORT_DIR/cron-cycle.txt" || die "the cron output has no 'Cron run completed correctly' line"
    cycle="$(grep 'Cron completed at' "$REPORT_DIR/cron-cycle.txt" | tail -n 1 || true)"
    log "${cycle}"
    kv_set cron.cycle_line "$cycle"
else
    dry "would then put CLI maintenance back on (MAINTENANCE_AFTER_CRON=${MAINTENANCE_AFTER_CRON})"
fi

# 4. After: backlog, blocked sends, the transfer_question_categories task.
if [ "$EXECUTE" = 1 ]; then
    counts_snapshot "$REPORT_DIR/cron-after.txt"
    log "task_adhoc rows: before $(kv_get cron.adhoc_before), after $(sed -n 's/^task_adhoc rows: //p' "$REPORT_DIR/cron-after.txt")"
    blocked="$(grep -c 'Not sending email due to' "$REPORT_DIR/cron-cycle.txt" || true)"
    log "e-mails the cycle tried to send and noemailever swallowed: ${blocked:-0}"
    kv_set cron.blocked_mails "${blocked:-0}"
    [ "$(cfg5 --name=noemailever --no-eol 2> /dev/null || true)" = 1 ] || die "noemailever is no longer on after the cycle"
    log "OK: noemailever was on for the whole cycle: zero real-address sends"
    tq="$(db_q "SELECT classname, ROUND(timeend - timestart, 2), result FROM {p}task_log WHERE classname LIKE '%transfer\\_question\\_categories%' ORDER BY id DESC LIMIT 5")"
    if [ -n "$tq" ]; then
        log "TRANSFER_QUESTION_CATEGORIES (class, seconds, result 0 = ok):"
        printf '%s\n' "$tq" | sed 's/^/    /'
        secs="$(printf '%s\n' "$tq" | head -n 1 | cut -f 2)"
        record_timing "transfer_question_categories task" "${secs%.*}" "0"
        kv_set cron.transfer_question_categories_seconds "$secs"
    else
        queued="$(sed -n 's/^transfer_question_categories adhoc rows queued: //p' "$REPORT_DIR/cron-before.txt")"
        if [ "${queued:-0}" -gt 0 ]; then
            warn "${queued} transfer_question_categories adhoc row(s) were queued but the cycle logged no run of the task (adhoc concurrency?): run another cycle"
        else
            note "no transfer_question_categories task was queued on this database (nothing to transfer, or it already ran)"
        fi
        kv_set cron.transfer_question_categories_seconds "not run"
    fi
    failed="$(db_scalar "SELECT COUNT(*) FROM {p}task_log WHERE id > $(sed -n 's/^max task_log.id: //p' "$REPORT_DIR/cron-before.txt") AND result = 1")"
    log "tasks that failed during the cycle: ${failed}"
    kv_set cron.failed_tasks "$failed"
    [ "${failed:-0}" = 0 ] || warn "${failed} task(s) failed during the cycle: select classname from task_log where result = 1 and id > the 'max task_log.id' of reports/cron-before.txt"
else
    dry "would record the backlog after, count 'Not sending email due to' lines, and time the transfer_question_categories task from {task_log}"
fi

# 5. checks.php (informational).
rc=0
timed_to "$REPORT_DIR/checks.txt" "admin/cli/checks.php" m5 ../admin/cli/checks.php || rc=$?
if [ "$EXECUTE" = 1 ]; then
    show_tail "$REPORT_DIR/checks.txt" 12
    kv_set checks.exit "$rc"
    case "$rc" in
        0) log "checks.php: clean" ;;
        1) warn "checks.php exited 1 (warnings): reports/checks.txt" ;;
        *) warn "checks.php exited ${rc} (critical or unknown): reports/checks.txt; the cutover wants it clean" ;;
    esac
fi

# 6. Parity once more (informational).
if [ "$EXECUTE" = 1 ]; then
    H="$(kv_get import.decisions_hash)"
    RUNID="$(kv_get import.runid)"
    rc=0
    timed_to "$REPORT_DIR/parity-after-cron.txt" "parity after the cron cycle" m5 local/sentientia_platform/cli/migration_parity_check.php \
        --compare="$BASELINE_FILE" --after-import --decisions="$IMPORT_DECISIONS" --expect-decisions-hash="$H" \
        --run="$RUNID" --report="$REPORT_DIR/import-apply.json" || rc=$?
    grep -E 'DRIFT|FAIL|UNPROVEN|RESULT' "$REPORT_DIR/parity-after-cron.txt" | sed 's/^/    /' | head -n 25 || true
    kv_set parity.after_cron "$rc"
    if [ "$rc" = 0 ]; then
        log "parity after the cron cycle: exit 0 (the cycle changed nothing the baseline holds)"
    else
        warn "parity after the cron cycle: exit ${rc}. Informational here: a scheduled task may legitimately write (reports/parity-after-cron.txt lists what changed)"
    fi
else
    dry "would run migration_parity_check.php --compare ... --after-import once more, informational"
fi
log "cron cycle done; CLI maintenance is $([ "$MAINTENANCE_AFTER_CRON" = on ] && printf 'ON' || printf 'OFF (lift/keep it for the smoke walk)')"
