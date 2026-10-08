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
if [ "$EXECUTE" = 1 ]; then
    mkdir -p "$REHEARSAL_WORK" "$LOG_DIR"
    LOCK="$REHEARSAL_WORK/.run.lock"
    if ! mkdir "$LOCK" 2> /dev/null; then
        printf 'Another rehearsal run holds %s (pid %s). If none is running, remove that directory.\n' "$LOCK" "$(cat "$LOCK/pid" 2> /dev/null || printf '?')" >&2
        exit 3
    fi
    printf '%s\n' "$$" > "$LOCK/pid"
    trap 'rm -rf "$LOCK"' EXIT
    # The steps it starts take the same lock when run alone (step_init); these two tell them this run already holds it.
    export REHEARSAL_RUN_LOCK="$LOCK" REHEARSAL_RUN_LOCK_PID="$$"
    exec > >(tee -a "$LOG_DIR/run_all.log") 2>&1
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
if [ -z "$FAILED" ]; then
    for entry in "${STEPS[@]}"; do
        id="${entry%%:*}"
        [ "$id" = 12 ] && continue
        want "$id" || continue
        if [ "$id" = 00 ] && [ -z "$ONLY" ] && [ "$FROM" -gt 0 ]; then
            continue
        fi
        run_step "$entry" || break
    done
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
