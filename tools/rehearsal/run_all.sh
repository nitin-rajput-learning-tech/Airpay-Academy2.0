#!/usr/bin/env bash
# run_all.sh -- the Stage B rehearsal, steps 00 to 12 in order. DRY by default: it prints what would run and changes nothing.
#
#   bash tools/rehearsal/run_all.sh [--env FILE] [--execute] [--from NN] [--to NN] [--only NN[,NN...]] [--list]
#
#   (no flags)    DRY: every step prints the commands it would run and the gates it would check. Safe anywhere.
#   --execute     run for real. Run it AS the web user: sudo -u www-data bash tools/rehearsal/run_all.sh --execute
#   --from NN     start at step NN (the preflight 00 still runs first: it is the gate)
#   --to NN       stop after step NN
#   --only NN,..  run just these steps (no preflight, no summary unless listed)
#   --list        print the steps and what each cites in the runbook
#   --env FILE    the env file (default: $REHEARSAL_ENV, else tools/rehearsal/rehearsal.env)
#
# The first step that fails stops the run, and step 12 (the summary) still runs, so there is always a report. Every step is
# idempotent: after fixing the cause, run again with --from NN. Exit code: that of the failed step (1 failed, 2 not proven,
# 3 usage), 0 when every step finished ok.
# A TERM, INT or HUP sent to run_all.sh alone does not interrupt the step that is running: run_all.sh waits for it (the lock
# REHEARSAL_WORK/.run.lock stays held until it has finished), starts no further step and no summary, and exits 143 (TERM) / 130 (INT) /
# 129 (HUP). USR1, USR2, ALRM, VTALRM, XCPU and XFSZ are handled the same way (exit 128+n): none of them may end run_all.sh with
# its lock released under a running step. The same holds for a step: a signal sent to a step alone is acted on when the command it is
# running (php upgrade.php, the restore, the import) has returned, never before; the step then records ITSELF as failed (state/NN.status:
# status=fail, rc=128+n, signal=NAME), releases its lock and exits 128+n, and run_all.sh starts nothing more. A step is never recorded ok
# on a signal. A step is also marked status=running while it runs, so one that dies without a trap (SIGKILL) is never read as ok.
# A signal sent to the whole PROCESS GROUP (Ctrl-C, an ssh hangup, kill -TERM -- -PGID) is how to stop a step AND what it runs: it reaches
# run_all.sh, the step, the command the step runs and their tee processes at once. The command ends at once; the step then records itself
# failed as above (its own log line goes straight to its log file, because its tee is gone too), and run_all.sh logs STOPPED to
# logs/run_all.log and exits 128+n (143 for kill -TERM, 130 for Ctrl-C), releasing its lock. A signal sent to step 01 while it loads the
# database acts when the load has returned: a load that returned 0 is then NOT verified (the in-flight table stays), so the database must
# be dropped and restored again; do not signal step 01 during the load unless that is what is wanted. Run the rehearsal under tmux or
# screen: a dropped ssh session sends HUP.
#
# Steps (each is its own script and can be run alone with the same options):
#   00 preflight        refuse unless the config is safe (allow-listed database, noemailever, no cron, no production host)
#   01 restore check    restore into an EMPTY database if asked; filedir content-hash gate; neutralise the restored mail/cron
#   02 source baseline  the baseline on the 4.1.x copy, before any upgrade
#   03 hop 1            4.1.x -> 4.5.x, BizLMS code off disk, timed, parity after
#   04 hop 2            4.5.x -> the 5.x Sentientia package, timed, parity after
#   05 repairs          repair_task_registrations, tenant seed/parity, capability repair against the signed allow-list
#   06 adr031 roles     the four ADR-031 scripts in target mode
#   07 theme switch     site theme epsilon -> sentientia
#   08 import guard     arm the ADR-032 guard
#   09 import           data-intact gate, preflight, dry run (records the decisions hash), apply, verify
#   10 parity compare   --after-import against the source baseline
#   11 cron cycle       one cron cycle under noemailever, timed; checks.php
#   12 summary          the report for Nitin

STEPS=(
    "00:00_preflight.sh:preflight"
    "01:01_restore_check.sh:restore check"
    "02:02_source_baseline.sh:source baseline"
    "03:03_hop1_to_45.sh:hop 1 to 4.5"
    "04:04_hop2_to_5x.sh:hop 2 to 5.x"
    "05:05_repairs.sh:repairs"
    "06:06_adr031_roles.sh:ADR-031 roles"
    "07:07_theme_switch.sh:theme switch"
    "08:08_import_guard.sh:import guard"
    "09:09_import.sh:import"
    "10:10_parity_compare.sh:parity compare"
    "11:11_cron_cycle.sh:cron cycle"
    "12:12_summary.sh:summary"
)
CITES=(
    "00|runbook Inputs, step 1 (noemailever MANDATORY); plan 1.3, 4d.3, 8-2"
    "01|runbook step 1 (restore, filedir gate, restore loss); plan 4c, 4f-a (SMTP wipe, I-11)"
    "02|runbook step 0 and the parity-tool table; plan 4a, 5.1; ADR-032 Parity hooks 1-2"
    "03|runbook step 3 (hop 1, checkpoint after hop 1); plan 0 (BizLMS code OFF disk), I-4"
    "04|runbook steps 2-3 (hop 2); plan 0, 4d, 4e (commerce stays dark)"
    "05|runbook step 4a-4c and 4e; ADR-032 Capabilities and Cutover slice 0; plan 4f-a..d"
    "06|runbook step 4f; plan 4f-f; ROLE9-CORE-CAPS-2026-09-26 section 10"
    "07|runbook step 4g (theme); plan 8-7"
    "08|runbook step 5a; ADR-032 Gating, Cutover slice 2"
    "09|runbook steps 5 and 5a; ADR-032 CLI, Cutover slice 3-5"
    "10|runbook step 5a; ADR-032 Parity hooks 2 and 4"
    "11|ADR-032 Cutover slice 7; plan 8-2, 4f-a, 9 gate 5"
    "12|runbook step 7; plan 9, 10 (I-4)"
)

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"

STEP_ID="run"
FROM=0
TO=12
ONLY=""
LIST=0
PASS=()
while [ $# -gt 0 ]; do
    case "$1" in
        --execute) EXECUTE=1; PASS+=(--execute) ;;
        --env) shift; [ $# -gt 0 ] || { printf 'Option --env needs a file.\n' >&2; exit 3; }; ENV_FILE="$1"; PASS+=(--env "$1") ;;
        --env=*) ENV_FILE="${1#--env=}"; PASS+=("$1") ;;
        --from) shift; FROM="$((10#${1:-x}))" ;;
        --from=*) FROM="$((10#${1#--from=}))" ;;
        --to) shift; TO="$((10#${1:-x}))" ;;
        --to=*) TO="$((10#${1#--to=}))" ;;
        --only) shift; ONLY="${1:-}" ;;
        --only=*) ONLY="${1#--only=}" ;;
        --list) LIST=1 ;;
        -h | --help) awk 'NR == 1 { next } /^#/ { sub(/^# ?/, ""); print; next } { exit }' "${BASH_SOURCE[0]}"; exit 0 ;;
        *) printf 'Unknown option: %s (see --help)\n' "$1" >&2; exit 3 ;;
    esac
    shift
done

if [ "$LIST" = 1 ]; then
    for entry in "${STEPS[@]}"; do
        id="${entry%%:*}"
        rest="${entry#*:}"
        for c in "${CITES[@]}"; do
            [ "${c%%|*}" = "$id" ] && cite="${c#*|}"
        done
        printf '%s  %-22s %s\n' "$id" "${rest#*:}" "${cite:-}"
    done
    exit 0
fi

# Only the order and the lock need the env; each step loads and validates it itself.
parse_args "${PASS[@]+"${PASS[@]}"}"
load_env

want() {
    # want NN -> 0 when step NN is in the selection
    local n=$((10#$1)) o
    if [ -n "$ONLY" ]; then
        for o in ${ONLY//,/ }; do
            [ $((10#$o)) -eq "$n" ] && return 0
        done
        return 1
    fi
    [ "$n" -ge "$FROM" ] && [ "$n" -le "$TO" ]
}

LOCK=""
SIGNAL=""
if [ "$EXECUTE" = 1 ]; then
    mkdir -p "$REHEARSAL_WORK" "$LOG_DIR"
    LOCK="$REHEARSAL_WORK/.run.lock"
    if ! mkdir "$LOCK" 2> /dev/null; then
        printf '%s\n' "$(lock_held_message "$LOCK")" >&2
        exit 3
    fi
    printf '%s\n' "$$" > "$LOCK/pid"
    trap 'rm -rf "$LOCK"' EXIT
    # TERM / INT / HUP sent to run_all.sh ALONE (kill <pid>; Ctrl-C reaches the whole process group, so the step gets it too; sudo relays HUP
    # to its command when the ssh session drops, which left run_all.sh dead and the lock released under a running step). The step runs in the
    # FOREGROUND, and bash defers a trapped signal until the foreground command has finished: the handler only notes it, so the lock is
    # never released while a step still runs (the EXIT trap used to remove .run.lock and leave the step running, and a step run alone could
    # then take the lock in the same work directory). The running step is not interrupted: run_all.sh waits for it, starts no further step
    # (the summary included) and exits 143 / 130 / 129. To stop the step as well, signal its process group.
    # The first signal is the one that is reported (a group signal also kills this run's own tee, and the write that finds the pipe dead raises
    # PIPE after the TERM). The handler logs through log_survivor: when the tee is gone (a signal to the whole process group), the line goes
    # straight to logs/run_all.log, and the SIGPIPE that the dead pipe raises no longer ends run_all.sh with its lock released and no STOPPED line
    # (round 6b review: exit 141). Every signal that would end bash with its EXIT trap run is handled (STEP_SIGNALS, see step_init).
    on_signal() {
        [ -n "$SIGNAL" ] || SIGNAL="$1"
        SURVIVOR_LOG="$LOG_DIR/run_all.log"
        log_survivor "SIG${1} received by run_all.sh: the running step is not interrupted and the lock ${LOCK} stays held until it has finished; no further step will start"
    }
    for sig in $STEP_SIGNALS; do
        trap "on_signal $sig" "$sig" 2> /dev/null || true
    done
    # SIGPIPE only needs to stop ending run_all.sh with its EXIT trap (bash runs it with status 0 on the default action): it is noted, like in a step
    # (on_step_pipe), so that the signal that caused the dead pipe (TERM to the group) is the one that is reported. It is ignored while the pipe is dead.
    trap : PIPE 2> /dev/null || true
    # The steps it starts take the same lock when run alone (step_init); these two tell them this run already holds it.
    export REHEARSAL_RUN_LOCK="$LOCK" REHEARSAL_RUN_LOCK_PID="$$"
    exec > >(tee -a "$LOG_DIR/run_all.log") 2>&1
fi
# Whether this run goes through step 01 (0 or 1): the preflight refuses a stamped copy whose step 01 has not finished ok only when nothing in this run
# will finish it (a run that starts after step 01).
if want 01; then
    export REHEARSAL_RUN_STEP01=1
else
    export REHEARSAL_RUN_STEP01=0
fi

log "Stage B rehearsal: mode $(mode_name), kit $(kit_rev), env ${ENV_FILE}"
RC=0
FAILED=""
run_step() {
    # run_step ENTRY -> runs the script; sets RC and FAILED on a non-zero exit
    local entry="$1" id file name
    id="${entry%%:*}"
    file="${entry#*:}"
    file="${file%%:*}"
    name="${entry##*:}"
    log "==== step ${id}: ${name} ===="
    # The signal is read HERE, after the line above (its date is a forked command, and a trapped signal that arrives while bash waits for it
    # is handled once it returns) and immediately before the step is started: a TERM that arrived in that window used to start the step all
    # the same, and run_all.sh then waited hours for it. The callers stop the run on SIGNAL.
    if [ -n "$SIGNAL" ]; then
        log "step ${id} (${name}) NOT started: SIG${SIGNAL} was received"
        return 1
    fi
    local rc=0
    bash "$KIT_DIR/$file" "${PASS[@]+"${PASS[@]}"}" || rc=$?
    if [ "$rc" -ne 0 ]; then
        log "==== step ${id} (${name}) exited ${rc} ===="
        RC="$rc"
        FAILED="${id} ${name}"
        return 1
    fi
    return 0
}

# The preflight is the gate: it runs first unless the selection is explicit (--only).
if [ -z "$ONLY" ] && [ "$FROM" -gt 0 ]; then
    run_step "${STEPS[0]}" || true
fi
if [ -z "$FAILED" ] && [ -z "$SIGNAL" ]; then
    for entry in "${STEPS[@]}"; do
        id="${entry%%:*}"
        [ "$id" = 12 ] && continue
        want "$id" || continue
        if [ "$id" = 00 ] && [ -z "$ONLY" ] && [ "$FROM" -gt 0 ]; then
            continue
        fi
        run_step "$entry" || break
        # A TERM / INT / HUP that arrived while that step ran was only noted (see on_signal): stop here, the step has finished.
        [ -z "$SIGNAL" ] || break
    done
fi

# A signal stops the run: no further step, and no summary either (it is read-only: run step 12 by hand when the run is to be reported).
if [ -n "$SIGNAL" ]; then
    log_survivor "STOPPED by SIG${SIGNAL}: the step that was running has finished${FAILED:+ (it failed: ${FAILED})}, nothing further was started and the summary was not run. Continue with: bash tools/rehearsal/run_all.sh --execute --from NN (NN = the step that was running; or 12_summary.sh --execute for the report)"
    signum="$(kill -l "$SIGNAL" 2> /dev/null || true)"
    case "$signum" in '' | *[!0-9]* | 0) signum=15 ;; esac
    exit $((128 + signum))
fi

# The summary runs last, failure or not (unless --only leaves it out), so there is always a report.
if [ -n "$ONLY" ]; then
    want 12 && { run_step "${STEPS[12]}" || true; }
else
    run_step "${STEPS[12]}" || true
fi

if [ -n "$FAILED" ]; then
    log "STOPPED at step ${FAILED} (exit ${RC}). Fix the cause, then: bash tools/rehearsal/run_all.sh --execute --from ${FAILED%% *}"
    exit "$RC"
fi
log "rehearsal run finished: every selected step ok ($(mode_name) mode)"
