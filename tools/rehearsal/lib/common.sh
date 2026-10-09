#!/usr/bin/env bash
# shellcheck shell=bash
# Stage B rehearsal kit -- shared helpers. Source this file; never run it.
#
# What every step gets from here:
#   * one env file (rehearsal.env), validated before anything else happens: the database must be on the explicit
#     rehearsal allow-list, no production hostname may appear in any host or path setting;
#   * DRY by default: run / timed / check print what they would do and change nothing; --execute runs;
#   * a log per step with a UTC timestamp on every line, a timings table (logs/timings.tsv), a status file per step
#     (state/NN.status) and a small key/value store (state/kv/*) the later steps and the summary read;
#   * database access through the mysql client with a private option file (the password is never on a command
#     line and never printed), refused for any database that is not on the allow-list.
#
# The kit is meant to be run AS the web user of the target box (sudo -u www-data bash tools/rehearsal/run_all.sh ...),
# the way Moodle's CLI tools are meant to be run; every file it writes is then owned by the one user that needs it.

if [ -n "${REHEARSAL_COMMON_LOADED:-}" ]; then
    return 0
fi
REHEARSAL_COMMON_LOADED=1

set -Eeuo pipefail

KIT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(cd "$KIT_DIR/../.." && pwd)"
export KIT_DIR REPO_ROOT

# ---------------------------------------------------------------------------------------------------------------------
# Globals
# ---------------------------------------------------------------------------------------------------------------------
EXECUTE=0
ENV_FILE="${REHEARSAL_ENV:-}"
STEP_ID="kit"
STEP_NAME=""
STEP_STATUS=""
STEP_T0=0
WARNINGS=0
EXTRA_ARGS=()
LOG_FILE=""
TMP_DIR=""
DB_CNF=""
DB_STATE=""
DB_TABLES=0
SCRIPT_PATH=""
KIT_CONFIG_MARKER="REHEARSAL-KIT-CONFIG"

# ---------------------------------------------------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------------------------------------------------
ts() { date -u +%Y-%m-%dT%H:%M:%SZ; }
epoch() { date -u +%s; }
log() { printf '%s [%s] %s\n' "$(ts)" "$STEP_ID" "$*"; }
note() { log "NOTE: $*"; }
warn() { WARNINGS=$((WARNINGS + 1)); log "WARN: $*"; }
dry() { log "[DRY] $*"; }
die() { log "FAIL: $*"; STEP_STATUS="fail"; exit 1; }
die_unproven() { log "NOT PROVEN: $*"; STEP_STATUS="unproven"; exit 2; }

# fmt_cmd ARGS... -> the command quoted so that it can be pasted into a shell.
fmt_cmd() {
    local out="" a
    for a in "$@"; do
        out+="$(printf '%q' "$a") "
    done
    printf '%s' "${out% }"
}

# cmd_display ARGS... -> the command as it will really run: the kit's wrappers (m5, m45, imp, cfg5, cfg45, php_run,
# baseline_tool) are expanded to the PHP command line, so a DRY run prints something an operator can read and paste.
cmd_display() {
    local php="${PHP_BIN:-php}${PHP_OPTS:+ $PHP_OPTS}"
    case "${1:-}" in
        m5) shift; printf '(cd %q && %s %s)' "${CODE_5X_DIR:-?}/public" "$php" "$(fmt_cmd "$@")" ;;
        m45) shift; printf '(cd %q && %s %s)' "${CODE_45_DIR:-?}" "$php" "$(fmt_cmd "$@")" ;;
        imp) shift; cmd_display m5 local/sentientia_platform/cli/import_bizlms.php "$@" ;;
        cfg5) shift; cmd_display m5 ../admin/cli/cfg.php "$@" ;;
        cfg45) shift; cmd_display m45 admin/cli/cfg.php "$@" ;;
        php_run) shift; printf '%s %s' "$php" "$(fmt_cmd "$@")" ;;
        baseline_tool) shift; printf '%s %s' "$php" "$(fmt_cmd "${SOURCE_BASELINE_PHP:-?}" --config="${SOURCE_DB_CONFIG:-?}" "$@")" ;;
        *) fmt_cmd "$@" ;;
    esac
}

mode_name() { if [ "$EXECUTE" = 1 ]; then printf 'EXECUTE'; else printf 'DRY'; fi; }

kit_rev() { git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || printf 'unknown'; }

usage() {
    # The leading comment block of the script that called us, minus the shebang.
    local self="${SCRIPT_PATH:-${BASH_SOURCE[${#BASH_SOURCE[@]} - 1]}}"
    awk 'NR == 1 { next } /^#/ { sub(/^# ?/, ""); print; next } { exit }' "$self"
    printf '\nOptions:\n  --execute     run it (the default is DRY: print what would run)\n'
    printf '  --env FILE    the env file (default: $REHEARSAL_ENV, else tools/rehearsal/rehearsal.env)\n'
    printf '  -h, --help    this text\n'
}

# ---------------------------------------------------------------------------------------------------------------------
# Arguments and environment
# ---------------------------------------------------------------------------------------------------------------------
parse_args() {
    while [ $# -gt 0 ]; do
        case "$1" in
            --execute) EXECUTE=1 ;;
            --dry-run) EXECUTE=0 ;;
            --env)
                shift
                [ $# -gt 0 ] || { printf 'Option --env needs a file.\n' >&2; exit 3; }
                ENV_FILE="$1"
                ;;
            --env=*) ENV_FILE="${1#--env=}" ;;
            -h | --help) usage; exit 0 ;;
            *) EXTRA_ARGS+=("$1") ;;
        esac
        shift
    done
}

# is_abs_posix PATH -> an absolute POSIX path (the kit is for the Linux target box; no drive letters, no backslashes).
is_abs_posix() {
    case "$1" in
        /*) ;;
        *) return 1 ;;
    esac
    case "$1" in
        *\\*) return 1 ;;
    esac
    return 0
}

# prod_host_hit LABEL VALUE -> returns 1 (and says so) when VALUE contains a production hostname.
prod_host_hit() {
    local label="$1" value="${2,,}" h
    for h in ${PRODUCTION_HOSTNAMES:-}; do
        case "$value" in
            *"${h,,}"*)
                log "FAIL: ${label} names a production host (${h}): refused"
                return 1
                ;;
        esac
    done
    return 0
}

# prod_host_in_text TEXT -> prints the first production hostname that TEXT contains (nothing when there is none); logs nothing.
prod_host_in_text() {
    local value="${1,,}" h
    for h in ${PRODUCTION_HOSTNAMES:-}; do
        case "$value" in
            *"${h,,}"*)
                printf '%s' "$h"
                return 0
                ;;
        esac
    done
    return 0
}

# db_name_denied NAME -> returns 0 when NAME can never be a rehearsal database (compared in lower case).
# Anything with 'prod' or 'uat' inside is refused, with no underscore boundary: production's own database is called
# airpayprod and UAT's is sentientia_uat, and the earlier boundary patterns (prod_*, *_prod) let both through.
db_name_denied() {
    local n="${1,,}"
    case "$n" in
        moodle | mysql | sys | test | information_schema | performance_schema | mariadb) return 0 ;;
    esac
    case "$n" in
        *prod* | *uat*) return 0 ;;
    esac
    case "$n" in
        live | live_* | *_live | *_live_*) return 0 ;;
    esac
    return 1
}

# The live database's own name: the one schema no rehearsal server may hold (probe_db), whatever the env file says.
KIT_LIVE_SCHEMAS="airpayprod"

# db_allowed NAME -> 0 when NAME is on the explicit allow-list.
db_allowed() {
    local want="$1" n
    for n in ${REHEARSAL_DB_ALLOWLIST:-}; do
        if [ "$n" = "$want" ]; then
            return 0
        fi
    done
    return 1
}

assert_db_allowed() {
    db_allowed "$DB_NAME" || die "database '${DB_NAME}' is not on the rehearsal allow-list (REHEARSAL_DB_ALLOWLIST='${REHEARSAL_DB_ALLOWLIST:-}')"
    if db_name_denied "$DB_NAME"; then
        die "database '${DB_NAME}' looks like a production or system database: refused"
    fi
}

# validate_policy -> the checks that never wait for --execute: they cost nothing and they are the reason the kit is safe.
validate_policy() {
    local n bad=0
    [ -n "${REHEARSAL_DB_ALLOWLIST:-}" ] || die "REHEARSAL_DB_ALLOWLIST is empty: name the database(s) this rehearsal may touch"
    for n in $REHEARSAL_DB_ALLOWLIST; do
        case "$n" in
            *[!A-Za-z0-9_]* | '')
                log "FAIL: allow-list entry '${n}' is not a plain database name"
                bad=1
                ;;
        esac
        if db_name_denied "$n"; then
            log "FAIL: allow-list entry '${n}' looks like a production or system database"
            bad=1
        fi
    done
    [ "$bad" = 0 ] || die "REHEARSAL_DB_ALLOWLIST holds a name that can never be a rehearsal database"
    case "$DB_NAME" in
        *[!A-Za-z0-9_]* | '') die "DB_NAME '${DB_NAME}' is not a plain database name" ;;
    esac
    case "$DB_PREFIX" in
        *[!a-z0-9_]* | '') die "DB_PREFIX '${DB_PREFIX}' is not a plain table prefix" ;;
    esac
    assert_db_allowed
    [ -n "${PRODUCTION_HOSTNAMES:-}" ] || die "PRODUCTION_HOSTNAMES is empty: list the production hosts the rehearsal must never name"
    # The live database endpoint (the RDS host name of live's config.php) is a required setting: without it nothing stops a
    # DB_HOST copied from that file. DRY only warns, so the plan can be printed from the example.
    case "${PRODUCTION_DB_ENDPOINT:-}" in
        '')
            if [ "$EXECUTE" = 1 ]; then
                die "PRODUCTION_DB_ENDPOINT is empty: set it to the live database host name (dbhost of live's config.php). The kit refuses any setting that names it, and cannot do that while it is unknown"
            fi
            warn "PRODUCTION_DB_ENDPOINT is not set: --execute refuses to start without it"
            ;;
        *CHANGE_ME* | *REPLACE* | '<'*)
            die "PRODUCTION_DB_ENDPOINT still holds a placeholder (${PRODUCTION_DB_ENDPOINT})"
            ;;
    esac
    prod_host_hit DB_HOST "$DB_HOST" || bad=1
    prod_host_hit REHEARSAL_WWWROOT "$REHEARSAL_WWWROOT" || bad=1
    prod_host_hit DIVERT_EMAILS_TO "${DIVERT_EMAILS_TO:-}" || bad=1
    prod_host_hit REHEARSAL_WORK "$REHEARSAL_WORK" || bad=1
    prod_host_hit MOODLEDATA "$MOODLEDATA" || bad=1
    [ "$bad" = 0 ] || die "a production hostname appears in the rehearsal settings"
    case "$REHEARSAL_WWWROOT" in
        http://* | https://*) ;;
        *) die "REHEARSAL_WWWROOT must start with http:// or https:// (got '${REHEARSAL_WWWROOT}')" ;;
    esac
    case "$REHEARSAL_WWWROOT" in
        */) die "REHEARSAL_WWWROOT must not end with a slash" ;;
    esac
    case "$DB_TYPE" in
        mysqli | mariadb | auroramysql) ;;
        *) die "DB_TYPE must be mysqli, mariadb or auroramysql (got '${DB_TYPE}')" ;;
    esac
}

REQUIRED_VARS="REHEARSAL_WORK DB_HOST DB_NAME DB_USER DB_PASS_FILE REHEARSAL_DB_ALLOWLIST REHEARSAL_WWWROOT MOODLEDATA CODE_45_DIR CODE_5X_DIR PRODUCTION_HOSTNAMES"

load_env() {
    local f="${ENV_FILE:-$KIT_DIR/rehearsal.env}"
    if [ ! -f "$f" ]; then
        if [ "$EXECUTE" = 1 ]; then
            die "env file not found: ${f} (copy tools/rehearsal/rehearsal.env.example to rehearsal.env and edit it)"
        fi
        f="$KIT_DIR/rehearsal.env.example"
        [ -f "$f" ] || die "no env file and no rehearsal.env.example next to the kit"
        note "no env file given: DRY mode reads the example (${f}); its values are placeholders"
    fi
    ENV_FILE="$f"
    # The file is sourced as shell, so nobody else may be able to write it.
    local mode g o
    mode="$(stat -c %a "$f" 2>/dev/null || printf '000')"
    mode="000${mode}"
    g="${mode: -2:1}"
    o="${mode: -1}"
    if [ $(((10#$g | 10#$o) & 2)) -ne 0 ]; then
        die "env file ${f} is writable by group or others (mode ${mode: -3}): chmod 600 it"
    fi
    set -a
    # shellcheck disable=SC1090
    . "$f"
    set +a

    : "${PHP_BIN:=php}"
    : "${PHP_OPTS:=}"
    : "${MYSQL_BIN:=mysql}"
    : "${WEB_USER:=}"
    : "${DB_PORT:=}"
    : "${DB_PREFIX:=mdl_}"
    : "${DB_TYPE:=mysqli}"
    : "${DB_COLLATION:=utf8mb4_unicode_ci}"
    : "${DIVERT_EMAILS_TO:=}"
    : "${UPGRADE_ALLOW_UNSTABLE:=1}"
    : "${ACCEPT_UNPROVEN:=0}"
    : "${ACCEPT_UNPROVEN_REF:=}"
    : "${SOURCE_RELEASE_REGEX:=^4\\.1\\.}"
    : "${HOP1_RELEASE_REGEX:=^4\\.5\\.}"
    : "${HOP2_RELEASE_REGEX:=^5\\.}"
    : "${TENANT_ADMIN_ROLE:=administrator}"
    : "${CAP_ALLOWLIST:=$REPO_ROOT/moodle-enhancement/docs/cutover/bizlms-capability-allowlist.json}"
    : "${IMPORT_DECISIONS:=$REPO_ROOT/moodle-enhancement/docs/cutover/bizlms-import-decisions.json}"
    : "${SOURCE_BASELINE_PHP:=$REPO_ROOT/moodle-enhancement/local/sentientia_platform/cli/source_baseline.php}"
    : "${ADR031_SCRIPTS_DIR:=$REPO_ROOT/tools/uat}"
    : "${TENANT_CHECKS:=warn}"
    : "${GUARD_ARM_SECONDS:=14400}"
    : "${PRODUCTION_DB_ENDPOINT:=}"
    : "${FORBIDDEN_SERVER_SCHEMAS:=}"
    : "${RESTORE_DONE_BY_HAND:=}"
    : "${RESTORE_MOODLEDATA_BY_HAND:=}"
    : "${RESTORE_MOODLEDATA_SHA256:=}"
    : "${FILEDIR_MAX_WRONGSIZE:=0}"
    : "${RESTORE_ALLOW_NO_TRAILER:=0}"
    : "${SNAPSHOT_TAKEN:=}"
    : "${BIZLMS_PRODUCTION_FLAG:=0}"
    : "${ALLOW_BASELINE_TOOL_SKEW:=0}"
    export SOURCE_BASELINE_PHP

    local v missing=""
    for v in $REQUIRED_VARS; do
        if [ -z "${!v:-}" ]; then
            missing+=" ${v}"
        fi
    done
    [ -z "$missing" ] || die "the env file leaves these settings empty:${missing}"

    # The live database endpoint is a production hostname too: every check that scans for one (database host, wwwroot,
    # paths, the strings of a config.php, the REHEARSAL GUARD of the generated config) then covers it.
    if [ -n "$PRODUCTION_DB_ENDPOINT" ]; then
        case " $PRODUCTION_HOSTNAMES " in
            *" $PRODUCTION_DB_ENDPOINT "*) ;;
            *) PRODUCTION_HOSTNAMES="$PRODUCTION_HOSTNAMES $PRODUCTION_DB_ENDPOINT" ;;
        esac
    fi

    REHEARSAL_WORK="${REHEARSAL_WORK%/}"
    LOG_DIR="$REHEARSAL_WORK/logs"
    STATE_DIR="$REHEARSAL_WORK/state"
    REPORT_DIR="$REHEARSAL_WORK/reports"
    BASELINE_DIR="$REHEARSAL_WORK/baseline"
    CONF_DIR="$REHEARSAL_WORK/conf"
    BASELINE_FILE="${BASELINE_FILE:-$BASELINE_DIR/source-baseline.json}"
    SOURCE_DB_CONFIG="$CONF_DIR/source-db.config.php"
    TIMINGS_FILE="$LOG_DIR/timings.tsv"

    # Read the PHP options into an array once.
    PHP_OPT_ARR=()
    if [ -n "$PHP_OPTS" ]; then
        read -r -a PHP_OPT_ARR <<<"$PHP_OPTS"
    fi

    validate_policy
}

# ---------------------------------------------------------------------------------------------------------------------
# Step lifecycle
# ---------------------------------------------------------------------------------------------------------------------
# take_run_lock: one --execute run per REHEARSAL_WORK at a time. run_all.sh takes REHEARSAL_WORK/.run.lock and says so to the steps it
# starts (REHEARSAL_RUN_LOCK = the lock directory, REHEARSAL_RUN_LOCK_PID = its pid, which the lock's pid file must still hold); a step run
# alone takes the lock itself and releases it when it exits (on_exit). A second run finds the lock held and stops with exit 3, before it
# opens a log, a state file or the database. A step that run_all.sh started also writes its own pid to <lock>/step.pid: a step can outlive
# the run_all.sh that started it (SIGKILL), and the refusal then names both pids and says whether each is alive.
STEP_LOCK=""
STEP_PIDFILE=""

# lock_held_message LOCK -> the refusal text for a lock that is held: its pid and the pid of the step run_all.sh started, each with
# whether that process is alive. A step still running under a run_all.sh that was killed is the reason both are printed.
lock_held_message() {
    local lock="$1" p s text
    p="$(cat "$lock/pid" 2> /dev/null || true)"
    s="$(cat "$lock/step.pid" 2> /dev/null || true)"
    text="pid ${p:-?}"
    if [ -n "$p" ]; then
        if kill -0 "$p" 2> /dev/null; then text+=" (alive)"; else text+=" (not running)"; fi
    fi
    if [ -n "$s" ]; then
        text+=", step pid ${s}"
        if kill -0 "$s" 2> /dev/null; then text+=" (alive)"; else text+=" (not running)"; fi
    fi
    printf 'Another rehearsal run holds %s (%s). Remove that directory only when NONE of the pids it names is alive: a step outlives a run_all.sh that was killed, and two runs in one work directory write the same database and state.' "$lock" "$text"
}

take_run_lock() {
    local lock="$REHEARSAL_WORK/.run.lock"
    if [ -n "${REHEARSAL_RUN_LOCK:-}" ] && [ "$REHEARSAL_RUN_LOCK" = "$lock" ] && [ -n "${REHEARSAL_RUN_LOCK_PID:-}" ] \
            && [ "$(cat "$lock/pid" 2> /dev/null || true)" = "$REHEARSAL_RUN_LOCK_PID" ]; then
        printf '%s\n' "$$" > "$lock/step.pid" 2> /dev/null || true
        STEP_PIDFILE="$lock/step.pid"
        return 0
    fi
    mkdir -p "$REHEARSAL_WORK"
    if ! mkdir "$lock" 2> /dev/null; then
        printf '%s\n' "$(lock_held_message "$lock")" >&2
        exit 3
    fi
    printf '%s\n' "$$" > "$lock/pid"
    STEP_LOCK="$lock"
    trap 'rm -rf "$STEP_LOCK"' EXIT
}

# step_init NN name [args...]: parse the arguments, load and validate the env, open the log, arm the exit trap and the signal traps.
#
# SIGNALS. TERM, INT and HUP are trapped, because a step that is killed while a foreground child runs (php upgrade.php, the restore, the
# mysql client) must not record success and must not release .run.lock under that child. Without the traps bash leaves its wait without
# reaping the child and runs the EXIT trap with $? = 0 (the status of the last command that completed): the step wrote status=ok, rc=0,
# logged 'ok (rc 0)' and removed the lock while the child ran on (reproduced, round 6 review). With the traps bash defers the handler until
# the foreground command has returned, and the handler exits 128+n, so the EXIT trap sees a non-zero status: the status file says fail (with
# signal=NAME), and the lock is held until the child has ended. The child is not interrupted by a signal sent to the step alone (the process
# group is how to stop it too). A step also writes status=running at its start, so that a death the traps cannot see (SIGKILL, power) leaves a
# status that is not 'ok'; the status the file held before is kept in a previous= line (step_done_ok reads it for the step that asks about itself).
# EVERY signal whose default action ends bash with its EXIT trap run is trapped the same way (STEP_SIGNALS: bash runs the EXIT trap with
# $? = 0 for USR1, USR2, ALRM, VTALRM, XCPU, XFSZ and PIPE too (PIPE: see on_step_pipe), and a step that got one of them while its command ran wrote status=ok and released
# the lock under the command: round 6b review, USR1 and ALRM reproduced). The exit status 128+n takes n from 'kill -l NAME'.
# THE PROCESS GROUP. A signal sent to the whole group (Ctrl-C, an ssh hangup, kill -TERM -- -PGID) also kills the tee that step_init started
# (exec > >(tee ...)), which dies first; the handler's first log line was then a SIGPIPE that killed the handler itself (round 6b review:
# status=running, .run.lock left behind, exit 141, the log silent). The handlers log through log_survivor, which ignores SIGPIPE and falls back
# to the log file, so a group signal ends the way a signal to the step alone does: status=fail, rc=128+n, signal=NAME, lock released. SIGPIPE
# itself is only noted (on_step_pipe, below), so that the signal that caused the dead pipe is the one that is recorded.
STEP_SIGNAL=""
STEP_SIGNALS="TERM INT HUP ALRM USR1 USR2 VTALRM XCPU XFSZ"
# SIGPIPE is the one signal that is only NOTED (on_step_pipe) and never ends the step by itself: the death of the tee a step writes its log through
# raises it, and a group signal (TERM to the whole process group) kills that tee at the same moment as it signals the step, so the first write to the
# dead pipe (bash's own "Terminated" message about the killed command is one) raises PIPE too. Bash runs pending traps in the order of the signal
# numbers, PIPE (13) before TERM (15): a PIPE handler that exited would record the step as killed by PIPE (rc 141) and never log the TERM. The PIPE
# handler therefore only notes it; the signal that is the cause (TERM) then runs its own handler and decides rc and signal=NAME, and a step that
# saw PIPE and no other signal is recorded failed, never ok, when it ends (on_exit).
STEP_PIPE=0
on_step_pipe() { STEP_PIPE=1; }
# THE LAST LINE (round 8). STEP_SIGNALS names the signals the kit knows how to act on, and it cannot be complete: bash runs the EXIT trap with
# $? = 0 for EVERY signal whose default action ends it (ABRT, TRAP, SYS, ILL, FPE, BUS, SEGV, PROF, LOST and the rest of its list), and a
# trap list that must name each of them is one signal short of recording success on a step that never finished (round 7 review: ABRT, TRAP and
# SYS reproduced, ok and the lock released under a running command). So a step is ok only when it REACHED ITS END: every step script ends with
# step_end, and on_exit takes a status of 0 for a success only when step_end has run, never from $? alone. A step that ends with status 0
# without it (a signal not in STEP_SIGNALS, or an 'exit 0' that skipped the last line) is recorded fail (ended=unreached in its status file) and
# keeps its lock, as a step that died without a trap does: the signal may have left its command running. The same holds for run_all.sh, which
# keeps .run.lock then (RUN_REACHED_END). A signal that cannot be trapped (KILL) leaves status=running, which nothing reads as ok.
STEP_REACHED_END=0
STEP_UNREACHED=0
step_end() { STEP_REACHED_END=1; }

# log_survivor TEXT...: log() for a handler that runs while the step is ending (a signal, the exit trap): it never fails, and the line gets
# through when the step's own tee is gone. SIGPIPE is ignored from here on (the process is ending; nothing after this needs it) and a line the
# pipe does not take is appended straight to the log file (SURVIVOR_LOG, else the step's LOG_FILE). Running under set -e, a handler that
# let a failed write end it would skip the status file.
log_survivor() {
    trap '' PIPE
    local line
    line="$(log "$@")"
    if ! printf '%s\n' "$line" 2> /dev/null; then
        if [ -n "${SURVIVOR_LOG:-${LOG_FILE:-}}" ]; then
            printf '%s\n' "$line" >> "${SURVIVOR_LOG:-$LOG_FILE}" 2> /dev/null || true
        fi
    fi
    return 0
}

on_step_signal() {
    local n="${2:-}"
    STEP_SIGNAL="$1"
    if [ -z "$n" ]; then
        n="$(kill -l "$1" 2> /dev/null || true)"
    fi
    case "$n" in '' | *[!0-9]* | 0) n=1 ;; esac
    log_survivor "SIG${1} received by step ${STEP_ID}: the foreground command has finished, so the step stops here, records itself as failed (exit $((128 + n))) and releases its lock; it does not go on"
    exit $((128 + n))
}

# write_step_status STATUS RC SECONDS: state/NN.status (EXECUTE only). running carries the status the file held before.
write_step_status() {
    if [ "$EXECUTE" != 1 ] || [ -z "${STATE_DIR:-}" ] || [ ! -d "$STATE_DIR" ]; then
        return 0
    fi
    local f="$STATE_DIR/${STEP_ID}.status" prev="" finished=""
    if [ "$1" = running ]; then
        if [ -f "$f" ]; then
            prev="$(sed -n 's/^status=//p' "$f" | head -n 1)"
            if [ "$prev" = running ]; then
                prev="$(sed -n 's/^previous=//p' "$f" | head -n 1)"
            fi
        fi
    else
        finished="$(ts)"
    fi
    {
        printf 'status=%s\nrc=%s\nwarnings=%s\nseconds=%s\nfinished=%s\nname=%s\nkit=%s\n' \
            "$1" "$2" "$WARNINGS" "$3" "$finished" "$STEP_NAME" "$(kit_rev)"
        if [ "$1" = running ]; then
            printf 'previous=%s\nstarted=%s\npid=%s\n' "$prev" "$(ts)" "$$"
        fi
        if [ -n "$STEP_SIGNAL" ]; then
            printf 'signal=%s\n' "$STEP_SIGNAL"
        fi
        if [ "$STEP_UNREACHED" = 1 ]; then
            printf 'ended=unreached\n'
        fi
    } > "$f"
}

step_init() {
    STEP_ID="$1"
    STEP_NAME="$2"
    shift 2
    SCRIPT_PATH="${BASH_SOURCE[1]}"
    EXTRA_ARGS=()
    parse_args "$@"
    load_env
    if [ "$EXECUTE" = 1 ]; then
        take_run_lock
        mkdir -p "$LOG_DIR" "$STATE_DIR" "$REPORT_DIR" "$BASELINE_DIR"
        (umask 077; mkdir -p "$CONF_DIR")
        LOG_FILE="$LOG_DIR/${STEP_ID}-${STEP_NAME}.log"
        exec > >(tee -a "$LOG_FILE") 2>&1
    fi
    STEP_T0="$(epoch)"
    trap 'on_exit $?' EXIT
    local sig
    for sig in $STEP_SIGNALS; do
        trap "on_step_signal $sig" "$sig" 2> /dev/null || true
    done
    trap on_step_pipe PIPE 2> /dev/null || true
    trap 'log "ERROR: a command failed (rc=$?) at ${BASH_SOURCE[0]##*/}:${LINENO}: ${BASH_COMMAND}"' ERR
    write_step_status running "" 0
    log "step ${STEP_ID} ${STEP_NAME} start; mode $(mode_name); kit $(kit_rev); env ${ENV_FILE}"
}

on_exit() {
    local rc="$1" t1 seconds status bg pipefail_rc=0
    # Cleanup is not interrupted: a second signal while the status is written must not leave the file at 'running' half way, or the lock
    # released before the status is there.
    # shellcheck disable=SC2086
    trap '' $STEP_SIGNALS PIPE 2> /dev/null || true
    # A step that saw SIGPIPE (its log pipe died) and no signal that ended it is not ok, whatever its last command returned.
    if [ "$rc" = 0 ] && [ "$STEP_PIPE" = 1 ]; then
        rc=$((128 + $(kill -l PIPE 2> /dev/null || printf 13)))
        pipefail_rc=1
        [ -n "$STEP_SIGNAL" ] || STEP_SIGNAL=PIPE
    fi
    # A status of 0 is a success only when the step reached its last line (step_end): bash runs this trap with $? = 0 for every signal whose
    # default action ends it, including those STEP_SIGNALS does not name (see THE LAST LINE above). Never ok from $? alone.
    if [ "$rc" = 0 ] && [ "$STEP_REACHED_END" != 1 ]; then
        rc=1
        STEP_UNREACHED=1
        log_survivor "ERROR: step ${STEP_ID} ${STEP_NAME} ended without reaching its last line (step_end): a signal that ends bash and that no trap here names, or an 'exit 0' that skipped the end. It is NOT recorded ok, and its lock is kept: a command it started may still be running (remove the lock directory only when no pid it names is alive)"
    fi
    t1="$(epoch)"
    seconds=$((t1 - STEP_T0))
    case "$rc" in
        0) status="ok" ;;
        2) status="unproven" ;;
        *) status="fail" ;;
    esac
    # A background job of the step (the heartbeat of timed_to) must not outlive it: when the signal trap runs right after the foreground
    # command, the line that kills the heartbeat has not run yet.
    bg="$(jobs -p 2> /dev/null || true)"
    if [ -n "$bg" ]; then
        # shellcheck disable=SC2086
        kill $bg 2> /dev/null || true
    fi
    if [ -n "$TMP_DIR" ] && [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
    fi
    log_survivor "step ${STEP_ID} ${STEP_NAME} ${status} (rc ${rc}) in ${seconds}s, ${WARNINGS} warning(s), mode $(mode_name)"
    write_step_status "$status" "$rc" "$seconds"
    # A step that did not reach its end keeps its lock and its pid file (they name the pids that tell whether a command is still running).
    if [ "$STEP_UNREACHED" != 1 ]; then
        if [ -n "$STEP_PIDFILE" ]; then
            rm -f "$STEP_PIDFILE"
        fi
        if [ -n "$STEP_LOCK" ]; then
            rm -rf "$STEP_LOCK"
        fi
    fi
    # The process ends with the status it recorded (run_all.sh stops on a step that exits non-zero): a step that saw SIGPIPE and finished
    # "normally" is recorded failed above, and exits so as well; so does one that did not reach its end (bash 5.2 ends a signalled process with
    # the signal's own status whatever this trap says, so there this only matters for an 'exit 0' that skipped the last line; another bash may
    # end it with this exit status: never with 0).
    if [ "$pipefail_rc" = 1 ] || [ "$STEP_UNREACHED" = 1 ]; then
        exit "$rc"
    fi
}

# step_done_ok NN -> 0 when step NN finished ok in EXECUTE mode. A step whose status file says 'running' did not finish (it is running, or
# it died without a trap running), so it is not ok; the one exception is the step asking about ITSELF (step 02 asks whether an earlier run of
# 02 finished ok): its own file says 'running' because of this very run, and what it asks about is how the earlier run ended (previous=).
step_done_ok() {
    local f="$STATE_DIR/$1.status" st
    [ -f "$f" ] || return 1
    st="$(sed -n 's/^status=//p' "$f" | head -n 1)"
    if [ "$st" = running ] && [ "$1" = "$STEP_ID" ]; then
        st="$(sed -n 's/^previous=//p' "$f" | head -n 1)"
    fi
    [ "$st" = ok ]
}

# ---------------------------------------------------------------------------------------------------------------------
# Running things
# ---------------------------------------------------------------------------------------------------------------------
# run CMD...: execute in EXECUTE mode, print in DRY mode. Returns the command's status; the caller decides.
run() {
    if [ "$EXECUTE" != 1 ]; then
        dry "would run: $(cmd_display "$@")"
        return 0
    fi
    log "RUN: $(cmd_display "$@")"
    local rc=0
    "$@" || rc=$?
    return "$rc"
}

record_timing() {
    [ "$EXECUTE" = 1 ] || return 0
    mkdir -p "$LOG_DIR"
    printf '%s\t%s\t%s\t%s\t%s\n' "$(ts)" "$STEP_ID" "$1" "$2" "$3" >> "$TIMINGS_FILE"
}

# timed LABEL CMD...: like run, and the seconds go to the log and to logs/timings.tsv.
timed() {
    local label="$1"
    shift
    if [ "$EXECUTE" != 1 ]; then
        dry "would run (timed '${label}'): $(cmd_display "$@")"
        return 0
    fi
    local t0 t1 rc=0
    log "RUN: ${label}: $(cmd_display "$@")"
    t0="$(epoch)"
    "$@" || rc=$?
    t1="$(epoch)"
    log "TIME ${label}: $((t1 - t0))s rc=${rc}"
    record_timing "$label" "$((t1 - t0))" "$rc"
    return "$rc"
}

# timed_to OUTFILE LABEL CMD...: timed, with the command's output going to OUTFILE and a heartbeat every 60 s (an upgrade
# runs for tens of minutes). On a non-zero status the last lines of OUTFILE are shown.
timed_to() {
    local out="$1" label="$2"
    shift 2
    if [ "$EXECUTE" != 1 ]; then
        dry "would run (timed '${label}', output to ${out}): $(cmd_display "$@")"
        return 0
    fi
    local t0 t1 rc=0 hb
    log "RUN: ${label} -> ${out}: $(cmd_display "$@")"
    t0="$(epoch)"
    : > "$out"
    (
        while sleep 60; do
            log "  ... ${label} still running ($(( $(epoch) - t0 ))s, $(wc -l < "$out" | tr -d ' ') lines of output)"
        done
    ) &
    hb=$!
    "$@" > "$out" 2>&1 || rc=$?
    kill "$hb" 2>/dev/null || true
    wait "$hb" 2>/dev/null || true
    t1="$(epoch)"
    log "TIME ${label}: $((t1 - t0))s rc=${rc} (output ${out}, $(wc -l < "$out" | tr -d ' ') lines)"
    record_timing "$label" "$((t1 - t0))" "$rc"
    if [ "$rc" -ne 0 ]; then
        tail -n 25 "$out" | sed 's/^/    | /'
    fi
    return "$rc"
}

# show_tail FILE [N]: the last N lines of FILE, indented (EXECUTE only; DRY has no file).
show_tail() {
    [ "$EXECUTE" = 1 ] && [ -f "$1" ] || return 0
    tail -n "${2:-15}" "$1" | sed 's/^/    | /'
}

# show_report_list JSON KEY LABEL: log a list an import report holds (meta.unproven, meta.blockers) when it is not empty.
show_report_list() {
    local v
    v="$(kit_php json_get.php "$1" "$2" 2> /dev/null || true)"
    if [ -n "$v" ] && [ "$v" != "[]" ]; then
        log "${3}: ${v}"
    fi
    return 0
}

# capture_to OUTFILE CMD...: run, keep the output in OUTFILE, show nothing; returns the status (read-only commands).
capture_to() {
    local out="$1"
    shift
    local rc=0
    "$@" > "$out" 2>&1 || rc=$?
    return "$rc"
}

# check DESCRIPTION CMD...: EXECUTE runs CMD and stops the step when it fails; DRY says what would be checked.
check() {
    local d="$1"
    shift
    if [ "$EXECUTE" != 1 ]; then
        dry "would check: ${d}"
        return 0
    fi
    if "$@" > /dev/null 2>&1; then
        log "OK: ${d}"
    else
        die "${d}"
    fi
}

# ---------------------------------------------------------------------------------------------------------------------
# key/value results (state/kv/KEY): what the summary and the later steps read
# ---------------------------------------------------------------------------------------------------------------------
kv_set() {
    [ "$EXECUTE" = 1 ] || return 0
    mkdir -p "$STATE_DIR/kv"
    printf '%s\n' "$2" > "$STATE_DIR/kv/$1"
}

kv_get() {
    local f="$STATE_DIR/kv/$1"
    if [ -f "$f" ]; then
        cat "$f"
    fi
    return 0
}

kv_unset() {
    [ "$EXECUTE" = 1 ] || return 0
    rm -f "$STATE_DIR/kv/$1"
}

# ---------------------------------------------------------------------------------------------------------------------
# PHP and Moodle CLI
# ---------------------------------------------------------------------------------------------------------------------
php_run() { "$PHP_BIN" ${PHP_OPT_ARR[@]+"${PHP_OPT_ARR[@]}"} "$@"; }

# m45 ARGS...: PHP in the 4.5 tree (cwd = its root), e.g. m45 admin/cli/upgrade.php --non-interactive
m45() { ( cd "$CODE_45_DIR" && php_run "$@" ); }

# m5 ARGS...: PHP in the 5.x package (cwd = public/, as the runbook says), e.g. m5 ../admin/cli/cfg.php --name=theme
m5() { ( cd "$CODE_5X_DIR/public" && php_run "$@" ); }

# kit_php FILE ARGS...: one of the kit's own PHP helpers (no Moodle).
kit_php() { php_run "$KIT_DIR/lib/$1" "${@:2}"; }

# baseline_tool ARGS...: cli/source_baseline.php against the rehearsal database (config read as data, read-only session).
baseline_tool() { php_run "$SOURCE_BASELINE_PHP" --config="$SOURCE_DB_CONFIG" "$@"; }

php_version_id() { php_run -r 'echo PHP_VERSION_ID;'; }

php_has_ext() {
    php_run -m | tr 'A-Z' 'a-z' | grep -qx "$1"
}

# imp ARGS...: import_bizlms.php of the 5.x tree.
imp() { m5 local/sentientia_platform/cli/import_bizlms.php "$@"; }

# cfg5 ARGS... / cfg45 ARGS...: admin/cli/cfg.php of the tree. Exit 3 = not set; callers handle it.
cfg5() { m5 ../admin/cli/cfg.php "$@"; }
cfg45() { m45 admin/cli/cfg.php "$@"; }

# ---------------------------------------------------------------------------------------------------------------------
# Database
# ---------------------------------------------------------------------------------------------------------------------
sql_escape_option() {
    # Escape a value for a double-quoted option-file string.
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g'
}

db_password() {
    # The password: the first line of DB_PASS_FILE (empty file = empty password, which only a scratch box should have).
    if [ -f "$DB_PASS_FILE" ]; then
        head -n 1 "$DB_PASS_FILE" | tr -d '\r'
    fi
}

check_pass_file() {
    [ -f "$DB_PASS_FILE" ] || return 1
    local mode o
    mode="000$(stat -c %a "$DB_PASS_FILE" 2>/dev/null || printf '000')"
    o="${mode: -1}"
    [ "$o" = 0 ]
}

# db_init_cnf: write the private option file for the mysql client (once per process).
# Every kit client call uses --defaults-file (the kit's file ONLY), not --defaults-extra-file: on Unix the client reads the user's ~/.my.cnf
# (and MySQL's ~/.mylogin.cnf) AFTER an extra file, so an operator's [client] host, port, user or password would override the kit's and the
# marker, in-flight and schema checks could run on another server than the one Moodle's hops write to. --defaults-file must be the first
# option of the command line (it is, in every call). What it cannot settle: (a) with DB_HOST=localhost the CLI client and PHP's mysqli each
# choose a Unix socket of their own, and the two can name different servers: use DB_HOST=127.0.0.1 with the DB_PORT of the rehearsal server
# (TCP), which both sides read the same way; (b) a MySQL 8 client still reads ~/.mylogin.cnf after --defaults-file (a [client] group in it
# would apply): keep no login-path file for the user that runs the kit (the MariaDB client has none).
db_init_cnf() {
    if [ -n "$DB_CNF" ] && [ -f "$DB_CNF" ]; then
        return 0
    fi
    assert_db_allowed
    if [ -z "$TMP_DIR" ]; then
        TMP_DIR="$(umask 077; mktemp -d "${TMPDIR:-/tmp}/rehearsal.XXXXXX")"
    fi
    DB_CNF="$TMP_DIR/client.cnf"
    (
        umask 077
        {
            printf '[client]\n'
            printf 'host="%s"\n' "$(sql_escape_option "$DB_HOST")"
            if [ -n "$DB_PORT" ]; then
                printf 'port=%s\n' "$DB_PORT"
            fi
            printf 'user="%s"\n' "$(sql_escape_option "$DB_USER")"
            printf 'password="%s"\n' "$(sql_escape_option "$(db_password)")"
            printf 'default-character-set=utf8mb4\n'
        } > "$DB_CNF"
    )
}

# mysql_nodb ARGS...: the client without a default database (probing, CREATE DATABASE).
mysql_nodb() {
    db_init_cnf
    "$MYSQL_BIN" --defaults-file="$DB_CNF" --batch --skip-column-names "$@"
}

# db_q SQL: run SQL on the rehearsal database; rows come back tab-separated without a header. {p} is the table prefix.
db_q() {
    [ "$EXECUTE" = 1 ] || die "internal error: db_q called in DRY mode"
    db_init_cnf
    local sql="${1//\{p\}/$DB_PREFIX}"
    "$MYSQL_BIN" --defaults-file="$DB_CNF" --batch --skip-column-names "$DB_NAME" -e "$sql"
}

# db_first SQL -> the first value of the first row ('' when there is no row). A failed query is an error (rc 1, said on
# stderr): the answer is read from a variable, not through a pipe to head, because an empty answer must never be
# mistaken for "nothing there" (a run of this kit on a loaded workstation lost 2 of 60 answers through the pipe).
# Call it as x="$(db_first ...)": errors go to stderr, which the command substitution does not swallow.
db_first() {
    local out rc=0
    out="$(db_q "$1")" || rc=$?
    if [ "$rc" != 0 ]; then
        log "FAIL: the query failed (rc ${rc}): ${1:0:160}" >&2
        return 1
    fi
    out="${out%%$'\n'*}"
    printf '%s' "${out%$'\r'}"
}

# db_scalar SQL -> like db_first, and the answer must not be empty (counts, versions, sizes). A COUNT or a version can
# never be empty, so an empty answer is asked again twice (the mysql client was seen to print nothing, with status 0,
# on a loaded workstation) and is an error after that.
db_scalar() {
    local out tries=0
    while :; do
        out="$(db_first "$1")" || return 1
        if [ -n "$out" ]; then
            break
        fi
        tries=$((tries + 1))
        if [ "$tries" -ge 3 ]; then
            log "FAIL: the query returned no value, three times: ${1:0:160}" >&2
            return 1
        fi
        sleep 1
    done
    printf '%s' "$out"
}

# count_retry COMMAND...: run COMMAND (it prints one COUNT) until it prints something, at most three times, one second apart. A COUNT can
# never be empty, and the mysql client was seen to print nothing, with status 0, on a loaded workstation, so an empty answer is asked
# again and is an error after the third (rc 1). A command that fails is an error at once. Prints the first line, without a carriage return.
count_retry() {
    local out tries=0 rc=0
    while :; do
        rc=0
        out="$("$@")" || rc=$?
        if [ "$rc" != 0 ]; then
            return 1
        fi
        out="${out%%$'\n'*}"
        out="${out%$'\r'}"
        if [ -n "$out" ]; then
            break
        fi
        tries=$((tries + 1))
        if [ "$tries" -ge 3 ]; then
            return 1
        fi
        sleep 1
    done
    printf '%s' "$out"
}

# db_write SQL: a write on the rehearsal database (EXECUTE only; DRY prints it).
db_write() {
    if [ "$EXECUTE" != 1 ]; then
        dry "would write SQL: $1"
        return 0
    fi
    log "SQL: $1"
    db_q "$1" > /dev/null
}

# probe_db -> DB_STATE = unreachable | absent | empty | present (and DB_TABLES).
probe_db() {
    DB_STATE="unreachable"
    DB_TABLES=0
    db_init_cnf
    if ! mysql_nodb -e 'SELECT 1' > /dev/null 2>&1; then
        return 0
    fi
    # The server must not be production or UAT: refuse one that holds the live schema (airpayprod) or any schema the env
    # file lists in FORBIDDEN_SERVER_SCHEMAS (add UAT's schema there unless this rehearsal shares UAT's server).
    # The list always names information_schema, so an answer without it (the client printed nothing, with status 0, as it was seen to on
    # this box, or the query failed) means the scan did not see the server's schemas: asked again twice, then "unreachable", which every
    # caller refuses. An empty list must never read as "no forbidden schema here".
    # WHAT THE SCAN CAN SEE: information_schema.SCHEMATA lists only the schemas DB_USER holds some privilege on, unless DB_USER has the global
    # SHOW DATABASES privilege. A rehearsal login without it, on a server that also holds airpayprod or UAT's schema, does not see them, and
    # the scan passes. So on any server that is not the rehearsal's own, grant the rehearsal login SHOW DATABASES (and name UAT's schema in
    # FORBIDDEN_SERVER_SCHEMAS); the other guards (the name guard, the allow-list, the kit marker) do not depend on it.
    local schema schema_l forbidden hit="" schemata="" tries=0
    while :; do
        schemata="$(mysql_nodb -e 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA' 2> /dev/null)" || schemata=""
        schemata="${schemata//$'\r'/}"
        case $'\n'"${schemata,,}"$'\n' in
            *$'\n'information_schema$'\n'*) break ;;
        esac
        tries=$((tries + 1))
        if [ "$tries" -ge 3 ]; then
            log "the schema list of ${DB_HOST} could not be read (it came back without information_schema): the server is treated as unreachable, because production and UAT schemas could not be looked for"
            return 0
        fi
        sleep 1
    done
    while IFS= read -r schema; do
        schema_l="${schema,,}"
        for forbidden in $KIT_LIVE_SCHEMAS ${FORBIDDEN_SERVER_SCHEMAS,,}; do
            if [ "$schema_l" = "$forbidden" ]; then
                hit="$schema"
            fi
        done
    done <<< "$schemata"
    if [ -n "$hit" ]; then
        die "the database server at ${DB_HOST} holds the schema '${hit}': this is production or UAT, not a rehearsal server. Refused"
    fi
    # Only a literal 0 reads as "absent" and only a literal 0 as "empty": a COUNT the client printed nothing for (it was seen to, with
    # status 0, on this box) is asked again, and an answer that stays empty, is not a number, or comes with an error leaves the state
    # "unreachable" (which every caller refuses), never absent or empty. These two states say nothing about a failed restore: a database
    # that holds the in-flight table is a partial copy whatever its state reads, and step 01 looks for the table by itself (inflight_count).
    local exists tables
    exists="$(count_retry mysql_nodb -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${DB_NAME}'")" || exists=""
    case "$exists" in
        0) DB_STATE="absent"; return 0 ;;
        1) ;;
        *) log "the count of schemata named ${DB_NAME} could not be read (got '${exists}'): the database is treated as unreachable, not as absent"; return 0 ;;
    esac
    tables="$(count_retry db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}'")" || tables=""
    if ! [[ "$tables" =~ ^[0-9]+$ ]]; then
        log "the count of tables of ${DB_NAME} could not be read (got '${tables}'): the database is treated as unreachable, not as empty"
        return 0
    fi
    DB_TABLES="$tables"
    if [ "$DB_TABLES" = 0 ]; then
        DB_STATE="empty"
    else
        DB_STATE="present"
    fi
}

# db_config_value NAME -> the value of a {config} row (empty when absent). Needs a Moodle database.
db_config_value() {
    # A setting that holds a value must never read as empty: the mysql client was seen to print nothing, with status 0, on a loaded
    # workstation (found again by the 2026-10-08 harness: "cron_enabled is not 0 after the update" on a row that held 0). An empty
    # answer is therefore checked against a COUNT (which db_scalar asks again until it gets one) and asked again when the row does
    # hold a value.
    local v="" tries=0
    while :; do
        v="$(db_first "SELECT value FROM {p}config WHERE name = '$1'")" || return 1
        if [ -n "$v" ]; then
            break
        fi
        if [ "$(db_scalar "SELECT COUNT(*) FROM {p}config WHERE name = '$1' AND value <> ''")" = 0 ]; then
            break
        fi
        tries=$((tries + 1))
        if [ "$tries" -ge 3 ]; then
            log "FAIL: the setting '$1' holds a value but three reads of it came back empty" >&2
            return 1
        fi
        sleep 1
    done
    printf '%s' "$v"
}

# ---------------------------------------------------------------------------------------------------------------------
# config.php as data, and the kit-owned config files
# ---------------------------------------------------------------------------------------------------------------------
# config_get FILE NAME -> the literal value of $CFG->NAME (empty and rc 3 when absent).
config_get() { kit_php config_probe.php "$1" "--get=$2"; }

# php_squote TEXT -> TEXT escaped for a single-quoted PHP string.
php_squote() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/\\\\'/g"
}

# check_config_file FILE LABEL -> 0 when the config points only at the rehearsal; prints each problem.
check_config_file() {
    local f="$1" label="$2" bad=0 v s
    [ -f "$f" ] || { log "FAIL: ${label}: ${f} is not a file"; return 1; }
    v="$(config_get "$f" dbname || true)"
    if [ -z "$v" ] || ! db_allowed "$v" || db_name_denied "$v"; then
        log "FAIL: ${label}: dbname '${v}' is not on the rehearsal allow-list"
        bad=1
    fi
    v="$(config_get "$f" prefix || true)"
    if [ "$v" != "$DB_PREFIX" ]; then
        log "FAIL: ${label}: prefix '${v}' is not '${DB_PREFIX}'"
        bad=1
    fi
    v="$(config_get "$f" dbhost || true)"
    if [ "$v" != "$DB_HOST" ]; then
        log "FAIL: ${label}: dbhost '${v}' is not the rehearsal host '${DB_HOST}'"
        bad=1
    fi
    v="$(config_get "$f" noemailever || true)"
    if [ "$v" != "true" ]; then
        log "FAIL: ${label}: \$CFG->noemailever is '${v}', not true (the dump holds real e-mail addresses)"
        bad=1
    fi
    v="$(config_get "$f" wwwroot || true)"
    if [ "$v" != "$REHEARSAL_WWWROOT" ]; then
        log "FAIL: ${label}: wwwroot '${v}' is not the rehearsal wwwroot '${REHEARSAL_WWWROOT}'"
        bad=1
    fi
    v="$(config_get "$f" dataroot || true)"
    if [ "$v" != "$MOODLEDATA" ]; then
        log "FAIL: ${label}: dataroot '${v}' is not '${MOODLEDATA}'"
        bad=1
    fi
    while IFS= read -r s; do
        prod_host_hit "${label} setting" "$s" || bad=1
    done < <(kit_php config_probe.php "$f" --strings 2> /dev/null || true)
    if ! grep -q 'REHEARSAL GUARD' "$f"; then
        warn "${label}: ${f} has no REHEARSAL GUARD block (it was not written by lib/make_config.sh)"
    fi
    [ "$bad" = 0 ]
}

# ---------------------------------------------------------------------------------------------------------------------
# Results
# ---------------------------------------------------------------------------------------------------------------------
accept_unproven() {
    [ "$ACCEPT_UNPROVEN" = 1 ] && [ -n "$ACCEPT_UNPROVEN_REF" ]
}

# judge LABEL RC [ENFORCE]: turn a parity/import exit code into the step's decision.
#   0 -> fine. 2 -> not proven: a stop unless ACCEPT_UNPROVEN=1 with an ACCEPT_UNPROVEN_REF naming the written
#   acceptance. 1 and 3 -> a stop. ENFORCE=0 turns the stops into warnings (used where a later step re-judges).
judge() {
    local label="$1" rc="$2" enforce="${3:-1}"
    case "$rc" in
        0)
            log "RESULT ${label}: exit 0"
            ;;
        2)
            if accept_unproven; then
                warn "${label}: exit 2 (not proven) ACCEPTED by ${ACCEPT_UNPROVEN_REF}"
                kv_set "accepted.${label}" "$ACCEPT_UNPROVEN_REF"
            elif [ "$enforce" = 0 ]; then
                warn "${label}: exit 2 (not proven); not enforced at this step"
            else
                die_unproven "${label}: exit 2. Read the UNPROVEN lines. To continue, Nitin must accept them in writing: set ACCEPT_UNPROVEN=1 and ACCEPT_UNPROVEN_REF=<where the acceptance is written> and re-run this step"
            fi
            ;;
        *)
            if [ "$enforce" = 0 ]; then
                warn "${label}: exit ${rc}; not enforced at this step"
            else
                die "${label}: exit ${rc} (1 = drift or a failed invariant, 3 = refused or could not run). Stop and investigate"
            fi
            ;;
    esac
}

# sha256_of FILE
sha256_of() { sha256sum "$1" | cut -d ' ' -f 1; }

# sha256_lf_of FILE -> the SHA-256 of the file with its carriage returns removed: the same source file read through a Windows checkout
# (CRLF) and through a Linux one (LF) is the same file for the comparison of the baseline tool.
sha256_lf_of() { tr -d '\015' < "$1" | sha256sum | cut -d ' ' -f 1; }

# need_tool NAME: EXECUTE stops without it; DRY warns.
need_tool() {
    if command -v "$1" > /dev/null 2>&1; then
        return 0
    fi
    if [ "$EXECUTE" = 1 ]; then
        die "required tool not found: $1"
    fi
    warn "tool not found here (needed when run with --execute): $1"
}

# fingerprint5 -> the install fingerprint import_bizlms.php --status prints (the --confirm value).
fingerprint5() {
    m5 local/sentientia_platform/cli/import_bizlms.php --status 2> /dev/null \
        | sed -n 's/^Install fingerprint (pass as --confirm): *//p' | head -n 1
}

# status_fact NAME < status text -> the value of one fact line of import_bizlms.php --status.
status_fact() {
    awk -v want="$1" '$1 == want { print $2; exit }'
}

# ---------------------------------------------------------------------------------------------------------------------
# The two code trees
# ---------------------------------------------------------------------------------------------------------------------
# plugin_dir TREE COMPONENT -> the directory of a plugin in tree 45 or 5x.
plugin_dir() {
    local tree="$1" comp="$2" base type name rel
    if [ "$tree" = 45 ]; then
        base="$CODE_45_DIR"
    else
        base="$CODE_5X_DIR/public"
    fi
    type="${comp%%_*}"
    name="${comp#*_}"
    case "$type" in
        local) rel="local/${name}" ;;
        block) rel="blocks/${name}" ;;
        enrol) rel="enrol/${name}" ;;
        tool) rel="admin/tool/${name}" ;;
        theme) rel="theme/${name}" ;;
        paygw) rel="payment/gateway/${name}" ;;
        *) return 1 ;;
    esac
    printf '%s/%s' "$base" "$rel"
}

# bizlms_off_disk TREE -> checks lib/bizlms_plugins.txt against the tree; returns 1 when a rule R is broken.
bizlms_off_disk() {
    local tree="$1" rule comp h1 h2 dir present=0 broken=0 total=0
    while read -r comp h1 h2; do
        case "$comp" in '' | '#'*) continue ;; esac
        if [ "$tree" = 45 ]; then rule="$h1"; else rule="$h2"; fi
        [ "$rule" = "-" ] && continue
        total=$((total + 1))
        dir="$(plugin_dir "$tree" "$comp")" || continue
        if [ -e "$dir" ]; then
            present=$((present + 1))
            if [ "$rule" = R ]; then
                log "FAIL: BizLMS plugin ${comp} is on disk (${dir}) and must not be"
                broken=1
            else
                warn "BizLMS plugin ${comp} is on disk (${dir}); the BizLMS code is meant to be off disk"
            fi
        fi
    done < "$KIT_DIR/lib/bizlms_plugins.txt"
    log "BizLMS code off disk (tree ${tree}): ${total} plugin directories checked, ${present} present"
    [ "$broken" = 0 ]
}

# file_magic_pk FILE -> 0 when FILE starts with the zip signature.
file_magic_pk() { [ "$(head -c 2 "$1" 2> /dev/null)" = "PK" ]; }

# unpack_tree ARCHIVE SHA256 DEST MARKER LABEL: put the code of ARCHIVE in DEST unless DEST already holds it.
# MARKER is a file below the tree's root (version.php for 4.5, public/version.php for 5.x) that locates the root inside
# the archive, whatever its top directory is called. A zip that is really a tar is refused (GNU tar writes one when asked
# for a zip: the 2026-09-10 package came out at 519 MB).
unpack_tree() {
    local archive="$1" want="$2" dest="$3" marker="$4" label="$5"
    if [ -f "$dest/$marker" ]; then
        log "${label}: ${dest} already holds the code (${marker} found); not unpacking"
        return 0
    fi
    if [ "$EXECUTE" != 1 ]; then
        # DRY prints the plan even when the archive is not on this machine; a wrong hash is still refused.
        if [ -z "$archive" ] || [ ! -f "$archive" ]; then
            warn "${label}: ${dest} holds no code and the archive (${archive:-not configured}) is not here; --execute needs both"
            dry "would unpack ${archive:-the archive} into ${dest} after checking its SHA-256"
            return 0
        fi
        if [ -z "$want" ]; then
            warn "${label}: the archive's SHA-256 is not set in the env file; --execute refuses without it"
        fi
    fi
    [ -n "$archive" ] || die "${label}: ${dest} holds no code and no archive is configured"
    [ -f "$archive" ] || die "${label}: archive not found: ${archive}"
    local got
    got="$(sha256_of "$archive")"
    if [ -z "$want" ]; then
        [ "$EXECUTE" != 1 ] || die "${label}: the archive's SHA-256 is required (set it in the env file; it is ${got})"
    else
        [ "$got" = "$want" ] || die "${label}: archive SHA-256 is ${got}, expected ${want}: refused"
        log "${label}: archive SHA-256 ${got} matches"
    fi
    if [ -d "$dest" ] && [ -n "$(ls -A "$dest" 2> /dev/null)" ]; then
        die "${label}: ${dest} is not empty and holds no ${marker}: refusing to unpack over it"
    fi
    if [ "$EXECUTE" != 1 ]; then
        dry "would unpack ${archive} into ${dest}"
        return 0
    fi
    local tmp root
    tmp="$(mktemp -d "$(dirname "$dest")/.unpack.XXXXXX")"
    case "$archive" in
        *.zip)
            file_magic_pk "$archive" || die "${label}: ${archive} is named .zip but is not a zip (GNU tar trap): rebuild it with bsdtar"
            need_tool unzip
            timed "unpack ${label}" unzip -q "$archive" -d "$tmp" || die "${label}: unzip failed"
            ;;
        *)
            timed "unpack ${label}" tar -C "$tmp" -xf "$archive" || die "${label}: tar failed"
            ;;
    esac
    root="$(find "$tmp" -maxdepth 3 -type f -path "*/${marker}" -print -quit)"
    [ -n "$root" ] || die "${label}: ${marker} not found in the archive"
    root="${root%/"$marker"}"
    if [ -d "$dest" ]; then
        rmdir "$dest"
    fi
    mv "$root" "$dest"
    rm -rf "$tmp"
    log "${label}: unpacked into ${dest}"
}

# release_of_file VERSION_PHP -> the $release string of a Moodle version.php.
release_of_file() {
    sed -n "s/^\\\$release *= *'\\([^']*\\)'.*/\\1/p" "$1" | head -n 1
}

# snapshot_acknowledged LABEL -> 0 when SNAPSHOT_TAKEN names the label (a comma list: before-hop-1,before-hop-2,before-import) or is
# 'all': the operator took that restore point by hand and says so.
snapshot_acknowledged() {
    local want="$1" item
    local IFS=','
    for item in ${SNAPSHOT_TAKEN:-}; do
        item="${item// /}"
        if [ "$item" = "$want" ] || [ "$item" = all ]; then
            return 0
        fi
    done
    return 1
}

# snapshot_hook LABEL: the operator's snapshot command (RDS snapshot, LVM, dump), if any.
# Without a hook the kit can only remind, and the irreversible step starts in the same second, so a reminder alone is not a restore
# point. With BIZLMS_PRODUCTION_FLAG=1 (the exact cutover form, where the snapshot is the only way back) the kit therefore refuses to
# go on unless there is a hook or SNAPSHOT_TAKEN names this label; in the rehearsal form (the dump is the way back) it still only
# says so.
snapshot_hook() {
    local label="$1"
    if [ -n "${SNAPSHOT_HOOK:-}" ]; then
        timed "snapshot ${label}" "$SNAPSHOT_HOOK" "$label" || die "the snapshot hook failed for ${label}"
    elif snapshot_acknowledged "$label"; then
        note "no SNAPSHOT_HOOK: the restore point '${label}' was taken by hand, as SNAPSHOT_TAKEN says (${SNAPSHOT_TAKEN})"
    elif [ "$EXECUTE" = 1 ] && [ "${BIZLMS_PRODUCTION_FLAG:-0}" = 1 ]; then
        die "no restore point for '${label}': BIZLMS_PRODUCTION_FLAG=1 is the cutover form, where the snapshot is the only way back, and neither SNAPSHOT_HOOK nor SNAPSHOT_TAKEN names it. Take the snapshot, set SNAPSHOT_TAKEN=${label} (a comma list of labels, or all) and run this step again"
    else
        note "no SNAPSHOT_HOOK configured: take the restore point for '${label}' by hand now (the rollback of a failed hop is a restore), or set SNAPSHOT_TAKEN=${label} once you have"
    fi
}

# ---------------------------------------------------------------------------------------------------------------------
# The file store gate (migration plan 4c step 2; rehearsal runbook step 1)
# ---------------------------------------------------------------------------------------------------------------------
# expected_paths < hashes -> the filedir-relative path Moodle uses for each content hash: ab/cd/abcdef...
expected_paths() {
    awk 'NF { print substr($1, 1, 2) "/" substr($1, 3, 2) "/" $1 }'
}

# disk_paths DIR -> every regular file below DIR as ab/cd/hash, only names that are a content hash.
disk_paths() {
    find "$1" -type f -printf '%P\n' | grep -E '^[0-9a-f]{2}/[0-9a-f]{2}/[0-9a-f]{40}$' || true
}

# expected_paths_sizes < "hash<TAB>size" -> "ab/cd/hash<TAB>size": what {files} says each content file is, and how big.
expected_paths_sizes() {
    awk -F '[ \t]+' 'NF >= 2 { sub(/\r$/, "", $2); print substr($1, 1, 2) "/" substr($1, 3, 2) "/" $1 "\t" $2 }'
}

# disk_paths_sizes DIR -> "ab/cd/hash<TAB>bytes" for every regular file below DIR whose name is a content hash.
disk_paths_sizes() {
    local tab=$'\t'
    find "$1" -type f -printf '%P\t%s\n' | grep -E "^[0-9a-f]{2}/[0-9a-f]{2}/[0-9a-f]{40}${tab}[0-9]+\$" || true
}

# filedir_wrong_sizes DBSIZES DISKSIZES -> "path<TAB>size(s) in {files}<TAB>size on disk" for each content file that is on disk with a size other
# than {files}.filesize. Both inputs are "path<TAB>size", sorted with LC_ALL=C. A file that is missing is not listed (the missing list has it).
# This is what shows a content file that was cut inside (a cut archive or a hand unpack that stopped in the middle of a file), which the
# existence check cannot see. A content hash is the SHA-1 of the content, so it has one size; but {files} is legacy data, and a hash that two
# rows record with two different filesize values (one of them wrong) appears here twice. The file is cut only when its size on disk is NONE
# of the sizes {files} records for that hash: a complete copy is never refused for the one wrong row (the listing then shows every recorded
# size, comma separated), while a file cut to any other length still is.
filedir_wrong_sizes() {
    LC_ALL=C join -t $'\t' -o 0,1.2,2.2 "$1" "$2" | awk -F '\t' '
        {
            if (!($1 in disk)) {
                order[++n] = $1
                disk[$1] = $3
                sizes[$1] = $2
            } else if (index("," sizes[$1] ",", "," $2 ",") == 0) {
                sizes[$1] = sizes[$1] "," $2
            }
            if ($2 == $3) {
                good[$1] = 1
            }
        }
        END {
            for (i = 1; i <= n; i++) {
                p = order[i]
                if (!(p in good)) {
                    print p "\t" sizes[p] "\t" disk[p]
                }
            }
        }'
}

# filedir_hash_check DIR PREFIX -> reads EVERY content file below DIR and compares the SHA-1 of what it reads with the file's own name (round 8, the
# content proof of the moodledata copy). Moodle names each file of its file store by the SHA-1 of its content (filedir/ab/cd/abcd...: 40 hexadecimal
# digits, in two directories named by the first two pairs), so the file store proves ITSELF, file by file, without the archive, its checksum or
# the database: a file that was zero-filled (a segmented or pre-allocated copy that stopped part way keeps every name and every size, so the
# existence and size gates of step 01 pass it), cut or damaged does not hash to its name. A checksum of an archive taken on the box that holds the
# copy (the round 7 review's repro) matches a cut copy and proves nothing; this does not depend on where any checksum came from.
# It reads the whole file store once (a moodledata of hundreds of gigabytes: as long as a full read of the disk takes, in parallel; progress is logged
# every minute). Names that are not a content hash (not 40 lowercase hexadecimal digits in two two-digit directories; Moodle's own warning.txt in the
# root is not counted) are counted and listed, not failed.
#   PREFIX-mismatch.txt  one line per file whose content does not hash to its name: path<TAB>sha1 of its content
#   PREFIX-odd.txt       the names that are not a content hash
# The working lists (one line per file of the file store: hundreds of megabytes for a large one) are written under TMP_DIR, which the step's EXIT
# trap removes, never beside the reports: a signal that stops the check leaves nothing behind in reports/ (round 9, S6).
# Sets FILEDIR_HASH_FILES (hashed), FILEDIR_HASH_BYTES (their size), FILEDIR_HASH_BAD (mismatches), FILEDIR_HASH_UNREAD (listed and not read) and
# FILEDIR_HASH_ODD. rc 0 = every one hashes to its name and every one was read; rc 1 = a mismatch, or a file that could not be read.
FILEDIR_HASH_FILES=0
FILEDIR_HASH_BYTES=0
FILEDIR_HASH_BAD=0
FILEDIR_HASH_UNREAD=0
FILEDIR_HASH_ODD=0
filedir_hash_check() {
    local dir="$1" prefix="$2" tab=$'\t' hit_re all good names err cnt jobs counted t0 hb rc=0 got="" bad=""
    if [ -z "$TMP_DIR" ]; then
        TMP_DIR="$(umask 077; mktemp -d "${TMPDIR:-/tmp}/rehearsal.XXXXXX")"
    fi
    all="$TMP_DIR/filedir-hash.all.tmp"
    good="$TMP_DIR/filedir-hash.good.tmp"
    names="$TMP_DIR/filedir-hash.names.tmp"
    err="$TMP_DIR/filedir-hash.err.tmp"
    cnt="$TMP_DIR/filedir-hash.count.tmp"
    hit_re="^[0-9a-f]{2}/[0-9a-f]{2}/[0-9a-f]{40}${tab}[0-9]+\$"
    FILEDIR_HASH_FILES=0 FILEDIR_HASH_BYTES=0 FILEDIR_HASH_BAD=0 FILEDIR_HASH_UNREAD=0 FILEDIR_HASH_ODD=0
    : > "${prefix}-mismatch.txt"
    : > "$cnt"
    find "$dir" -type f -printf '%P\t%s\n' > "$all"
    grep -E "$hit_re" "$all" > "$good" || true
    grep -Ev "$hit_re" "$all" | grep -Ev "^warning\.txt${tab}" | cut -f 1 > "${prefix}-odd.txt" || true
    FILEDIR_HASH_ODD="$(wc -l < "${prefix}-odd.txt" | tr -d ' ')"
    counted="$(wc -l < "$good" | tr -d ' ')"
    FILEDIR_HASH_BYTES="$(awk -F "$tab" '{ s += $2 } END { printf "%.0f", s }' "$good")"
    cut -f 1 "$good" | tr '\n' '\0' > "$names"
    jobs="$(nproc 2> /dev/null || printf 2)"
    [[ "$jobs" =~ ^[0-9]+$ ]] || jobs=2
    [ "$jobs" -le 8 ] || jobs=8
    [ "$jobs" -ge 1 ] || jobs=1
    log "FILEDIR HASH: reading ${counted} content files (${FILEDIR_HASH_BYTES} bytes) with ${jobs} parallel sha1sum processes; ${FILEDIR_HASH_ODD} name(s) that are not a content hash"
    t0="$(epoch)"
    (
        while sleep 60; do
            log "  ... filedir hash check still running ($(( $(epoch) - t0 ))s, $(cut -d ' ' -f 1 "$cnt" 2> /dev/null | head -n 1) of ${counted} files read)"
        done
    ) &
    hb=$!
    # -n 32: every sha1sum process prints its (at most ~3 KB of) lines in one write, so the lines of two processes never interleave.
    ( cd "$dir" && xargs -0 -r -n 32 -P "$jobs" sha1sum -- < "$names" 2> "$err" \
        | awk -v out="${prefix}-mismatch.txt" -v cnt="$cnt" '
            { files++; h = $1; p = substr($0, 43); n = split(p, a, "/")
              if (length(h) != 40 || h != a[n]) { print p "\t" h >> out; bad++ }
              if (files % 20000 == 0) { printf "%d\n", files > cnt; close(cnt) } }
            END { printf "%d %d\n", files + 0, bad + 0 > cnt; close(cnt) }' ) || rc=$?
    kill "$hb" 2> /dev/null || true
    wait "$hb" 2> /dev/null || true
    read -r got bad < "$cnt" || true
    [[ "$got" =~ ^[0-9]+$ ]] || got=0
    [[ "$bad" =~ ^[0-9]+$ ]] || bad=0
    FILEDIR_HASH_FILES="$got"
    FILEDIR_HASH_BAD="$bad"
    FILEDIR_HASH_UNREAD=$((counted - got))
    [ "$FILEDIR_HASH_UNREAD" -ge 0 ] || FILEDIR_HASH_UNREAD=0
    if [ -s "$err" ]; then
        head -n 5 "$err" | sed 's/^/    sha1sum: /'
    fi
    log "FILEDIR HASH: read ${FILEDIR_HASH_FILES} of ${counted} files in $(( $(epoch) - t0 ))s: ${FILEDIR_HASH_BAD} whose content does not hash to their name, ${FILEDIR_HASH_UNREAD} that could not be read (xargs exit ${rc})"
    rm -f "$all" "$good" "$names" "$err" "$cnt"
    [ "$FILEDIR_HASH_BAD" = 0 ] && [ "$FILEDIR_HASH_UNREAD" = 0 ] && [ "$rc" = 0 ]
}

# comm_only_first A B -> lines of sorted A that are not in sorted B; comm_only_second the other way.
comm_only_first() { LC_ALL=C comm -23 "$1" "$2"; }
comm_only_second() { LC_ALL=C comm -13 "$1" "$2"; }

# ---------------------------------------------------------------------------------------------------------------------
# The dump that is restored (step 01): never one that can reach another database, never a truncated one
# ---------------------------------------------------------------------------------------------------------------------
# dump_stream FILE -> the dump as text on stdout (gzip -dc for *.gz).
dump_stream() {
    case "$1" in
        *.gz) gzip -dc "$1" ;;
        *) cat "$1" ;;
    esac
}

# dump_unsafe_statement FILE -> prints the first statement of the dump that must not be restored here (nothing when there is none):
#   * USE / CREATE DATABASE / DROP DATABASE (mysqldump --databases / --all-databases) sends everything after it to the schema it
#     names, on whatever server DB_HOST is, whatever REHEARSAL_DB_ALLOWLIST says;
#   * SET @@GLOBAL.GTID_PURGED (what mysqldump writes for a GTID-enabled source such as RDS unless --set-gtid-purged=OFF) is a
#     SERVER-wide setting: on MariaDB it aborts the restore, and on a privileged MySQL login it changes the replication state of a
#     server that may be UAT's;
#   * any line that names the in-flight table (KIT_INFLIGHT_TABLE). A mysqldump of a database that held it (a partial copy the kit was
#     still restoring into) carries DROP TABLE IF EXISTS / CREATE TABLE / INSERT for it: restored, it would drop and re-create the kit's
#     claim in the middle of the load, and a load that died between that DROP and CREATE would leave a partial copy with no in-flight
#     table, which RESTORE_DONE_BY_HAND adopts;
#   * a command of the mysql CLIENT itself (mysqldump never writes one): a backslash command at the start of a line (\u db = USE, \. file and
#     source file = read another file, \! and system = run a shell command, \r and connect = another server, \q and quit and exit = stop
#     reading). With --one-database a \u makes the client skip the rest of the dump and exit 0, which would stamp a partial copy.
# It reads the whole file once (the trailer check reads only its tail), before the restore reads it again.
dump_unsafe_statement() {
    { dump_stream "$1" | grep -a -m1 -Ei -e '^[[:space:]]*(/\*![0-9]*[[:space:]]+)?(use[[:space:]]|create[[:space:]]+(database|schema)[[:space:]]|drop[[:space:]]+(database|schema)[[:space:]]|set[[:space:]]+(@@global\.|global[[:space:]]+)gtid_purged)' -e "$KIT_INFLIGHT_TABLE" -e '^[[:space:]]*\\[a-zA-Z.!]' -e '^[[:space:]]*(source|system|connect|quit|exit)([[:space:]]|;|$)' | cut -c1-160; } 2> /dev/null || true
}

# dump_mysql8_collation FILE -> prints the first utf8mb4_0900_* collation named in the first 20 MB of the dump (nothing when there is
# none). MySQL 8 dumps name it in every CREATE TABLE; MariaDB does not know it, so the restore would fail at the first table, after the
# whole file was scanned once already. The head is enough: the first tables are at the top.
dump_mysql8_collation() {
    { dump_stream "$1" | head -c 20000000 | grep -a -m1 -Eo 'utf8mb4_0900_[a-z0-9_]+'; } 2> /dev/null || true
}

# dump_has_trailer FILE -> 0 when the dump ends with mysqldump's "-- Dump completed" line (an aborted mysqldump has none,
# and restores as a silent partial copy).
dump_has_trailer() {
    case "$1" in
        *.gz) gzip -dc "$1" 2> /dev/null | tail -c 4096 | grep -aq -- '-- Dump completed' ;;
        *) tail -c 4096 "$1" | grep -aq -- '-- Dump completed' ;;
    esac
}

# ---------------------------------------------------------------------------------------------------------------------
# The kit marker: this database (and this moodledata) is a copy THIS kit restored, or one the operator named as restored by hand
# ---------------------------------------------------------------------------------------------------------------------
# Every writing step (01 after the restore, 02 to 11) refuses a database that does not carry the marker of the restore
# recorded in state/kv/restore.id. An allow-listed database name is not enough: a mistyped DB_HOST or a UAT database named like
# a rehearsal one has no marker. The marker is a {config} row; the moodledata has a file with the same id.
KIT_MARKER_KEY="rehearsal_kit_restore_id"
KIT_MARKER_FILE=".rehearsal-kit-restore-id"

new_restore_id() { od -An -N16 -tx1 /dev/urandom | tr -d ' \n'; }

# marker_get -> the marker of the database, with a return code that says whether the read can be believed:
#   rc 0, a value printed   the marker row holds that value;
#   rc 0, nothing printed   there is NO marker row (a COUNT that ran without error said 0), or there is no {config} table at all (the server's
#                           own error 1146, and nothing else);
#   rc 1, nothing printed   CANNOT TELL: the read failed with any other error (a lost connection, a restarting server, a wrong DB_PREFIX
#                           is 1146 and the case above), came back blank while the COUNT says the row exists, or the COUNT itself failed.
# A caller must treat rc 1 as "do not know", never as "no marker". It used to read every failure as "no marker" (rc 0, empty), and on the
# RESTORE_DONE_BY_HAND path (which the kit says stays in rehearsal.env) one lost connection made step 01 archive a finished rehearsal's
# state/ and overwrite the database's marker with a new id (round 6 review, reproduced). Each read is asked up to three times, a second
# apart. The value is read with its stderr kept apart, so a warning of the client is never taken for the value.
marker_get() { config_row_get "$KIT_MARKER_KEY"; }

# config_row_get NAME: the value of the kit's own {config} row NAME, read the way marker_get says (rc 0 with a value, rc 0 with nothing = no
# such row or no {config} table, rc 1 = cannot tell). Used for the restore marker and for the 'step 01 finished' row.
config_row_get() {
    local key="$1" v="" n="" tries=0 rc errf
    db_init_cnf
    errf="$TMP_DIR/row.err"
    while :; do
        rc=0
        v="$(db_q "SELECT value FROM {p}config WHERE name = '${key}'" 2> "$errf")" || rc=$?
        v="${v//$'\r'/}"
        if [ "$rc" != 0 ]; then
            if grep -q 'ERROR 1146' "$errf" 2> /dev/null; then
                return 0
            fi
        else
            v="${v%%$'\n'*}"
            if [ -n "$v" ]; then
                printf '%s' "$v"
                return 0
            fi
            n="$(count_retry db_q "SELECT COUNT(*) FROM {p}config WHERE name = '${key}'" 2> /dev/null)" || n=""
            if [ "$n" = 0 ]; then
                return 0
            fi
        fi
        tries=$((tries + 1))
        if [ "$tries" -ge 3 ]; then
            return 1
        fi
        sleep 1
    done
}

# marker_definitely_absent -> 0 only when the database DEFINITELY carries no marker: its {config} table exists, and TWO reads a second apart,
# each answering without an error, say COUNT(*) = 0 for the marker row. Anything else (a failed or blank read, a count that is not 0, the
# two reads disagreeing, no {config} table) is rc 1. The hand-restore path (RESTORE_DONE_BY_HAND) asks this before it moves a rehearsal's
# state/ to archive/ and stamps a new id, so that one misread cannot do either.
marker_definitely_absent() {
    local i n cfg
    cfg="$(count_retry db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}' AND TABLE_NAME = '${DB_PREFIX}config'")" || return 1
    [ "$cfg" = 1 ] || return 1
    for i in 1 2; do
        n="$(db_q "SELECT COUNT(*) FROM {p}config WHERE name = '${KIT_MARKER_KEY}'" 2> /dev/null)" || return 1
        n="${n%%$'\n'*}"
        n="${n%$'\r'}"
        [ "$n" = 0 ] || return 1
        if [ "$i" = 1 ]; then
            sleep 1
        fi
    done
    return 0
}

# marker_set ID: the restore the kit made itself is stamped (the dump may hold the marker row of a stamped database: it is replaced).
marker_set() {
    db_write "INSERT INTO {p}config (name, value) VALUES ('${KIT_MARKER_KEY}', '$1') ON DUPLICATE KEY UPDATE value = '$1'"
}

# marker_insert_new ID: stamp a hand-restored database with a PLAIN INSERT. A marker row that exists (one the earlier read missed) is the
# server's error 1062 here, and nothing is overwritten: the caller stops and moves nothing. rc 0 = stamped, 1 = the row exists (1062),
# 2 = any other failure.
marker_insert_new() {
    local out rc=0
    log "SQL: INSERT INTO ${DB_PREFIX}config (name, value) VALUES ('${KIT_MARKER_KEY}', '${1:0:8}...')  (a plain INSERT: a marker row that exists is an error, never overwritten)"
    out="$(db_q "INSERT INTO {p}config (name, value) VALUES ('${KIT_MARKER_KEY}', '$1')" 2>&1)" || rc=$?
    if [ "$rc" = 0 ]; then
        return 0
    fi
    case "$out" in
        *'ERROR 1062'*) return 1 ;;
    esac
    log "the marker INSERT failed: ${out:0:200}"
    return 2
}

# ---------------------------------------------------------------------------------------------------------------------
# The in-flight table: a kit restore that did not finish says so IN THE DATABASE it was writing to
# ---------------------------------------------------------------------------------------------------------------------
# Step 01 creates this table (outside the Moodle prefix) in the target database after the dump has been checked and immediately before it
# loads it, and drops it only when the restore is verified complete, just before it stamps the database. A database that holds it is a
# partial copy: step 01 refuses it whatever RESTORE_DONE_BY_HAND says and whatever REHEARSAL_WORK the run uses (the fact is in the database,
# not in a work directory), and only DROP DATABASE clears it. The CREATE TABLE is also the claim on an empty database: a second restore
# that meets the table (or a database that is no longer empty) stops instead of writing over the first.
KIT_INFLIGHT_TABLE="zz_rehearsal_restore_inflight"

# sql_squote TEXT -> TEXT escaped for a single-quoted SQL string.
sql_squote() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/''/g"
}

# inflight_count -> 1 when the database holds the in-flight table, 0 when it does not; rc 1 (nothing printed) when that could not be told,
# which a caller must treat as "cannot tell", never as 0. Needs the database to exist.
# It is read from the table itself, by the server's error, and not from a COUNT of information_schema.TABLES: a single count that comes
# back wrong (the client was seen to print nothing, with status 0; the selftest's fault model also has a count that answers 0) would read
# a present table as absent, and that one read was all that stood between RESTORE_DONE_BY_HAND and a partial copy. Here
#   absent   = the server's own error 1146 (the table does not exist), and nothing else;
#   present  = SELECT 1 FROM the table ran without an error AND the 'answered' line printed by the statement before it is there (a client
#              that printed nothing proves nothing, and must not read as "present" either: that would send an operator to DROP DATABASE on a
#              complete copy);
#   anything else (another error, a lost connection, no output) is asked again, twice, one second apart, and is "cannot tell" after that.
inflight_count() {
    local out rc tries=0
    while :; do
        rc=0
        out="$(db_q "SELECT 'answered'; SELECT 1 FROM \`${KIT_INFLIGHT_TABLE}\` LIMIT 0" 2>&1)" || rc=$?
        out="${out//$'\r'/}"
        if [ "$rc" = 0 ]; then
            case $'\n'"$out"$'\n' in
                *$'\n'answered$'\n'*) printf '1'; return 0 ;;
            esac
        else
            case "$out" in
                *'ERROR 1146'*) printf '0'; return 0 ;;
            esac
        fi
        tries=$((tries + 1))
        if [ "$tries" -ge 3 ]; then
            return 1
        fi
        sleep 1
    done
}

# inflight_describe -> what the in-flight table says about the restore (id, dump, start), for a message; never fails.
inflight_describe() {
    local row
    row="$(db_first "SELECT CONCAT('restore ', SUBSTRING(restore_id, 1, 8), '..., dump ', dump_path, ', started ', started) FROM \`${KIT_INFLIGHT_TABLE}\`" 2> /dev/null || true)"
    printf '%s' "${row:-its row could not be read}"
}

# inflight_begin ID DUMP: claim the database for this restore. The CREATE TABLE fails when the table is already there (another restore is,
# or was, writing to this database), and the table count right after it must be 1 (the table itself): a database that gained tables
# since step 01 looked at it (a restore that finished meanwhile, a hand restore) is released again and refused. The table is dropped here
# only when this call created it.
inflight_begin() {
    local id="$1" dump="$2" n
    case "$KIT_INFLIGHT_TABLE" in
        "$DB_PREFIX"*) die "DB_PREFIX '${DB_PREFIX}' is a prefix of the in-flight table name ${KIT_INFLIGHT_TABLE}: it would be taken for a Moodle table. Use another prefix" ;;
    esac
    log "SQL: CREATE TABLE ${KIT_INFLIGHT_TABLE} (restore ${id:0:8}..., dump ${dump}): the database is claimed for this restore"
    db_q "CREATE TABLE \`${KIT_INFLIGHT_TABLE}\` (restore_id CHAR(32) NOT NULL, dump_path TEXT NOT NULL, started VARCHAR(40) NOT NULL, PRIMARY KEY (restore_id)) ENGINE=InnoDB" \
        || die "cannot claim database ${DB_NAME}: the in-flight table ${KIT_INFLIGHT_TABLE} could not be created. If it exists, another restore is writing to this database (or did, and did not finish): the kit never writes over it. Otherwise the database user needs the CREATE privilege on it"
    if ! db_q "INSERT INTO \`${KIT_INFLIGHT_TABLE}\` (restore_id, dump_path, started) VALUES ('${id}', '$(sql_squote "$dump")', '$(ts)')" > /dev/null; then
        db_q "DROP TABLE \`${KIT_INFLIGHT_TABLE}\`" > /dev/null || true
        die "cannot claim database ${DB_NAME}: the in-flight table was created but its row could not be written (it is dropped again; nothing was restored)"
    fi
    n="$(count_retry db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}'")" || n=""
    if [ "$n" != 1 ]; then
        db_q "DROP TABLE \`${KIT_INFLIGHT_TABLE}\`" > /dev/null || true
        die "database ${DB_NAME} holds '${n}' tables right after it was claimed, not 1 (the in-flight table itself): it is not the empty database step 01 saw (another restore, or a hand restore, wrote to it meanwhile; or the table count could not be read). The claim is released and nothing was restored. Look at what is there, then run step 01 again"
    fi
}

# inflight_end: the restore is verified complete; the database no longer counts as a partial copy.
inflight_end() {
    log "SQL: DROP TABLE ${KIT_INFLIGHT_TABLE}: the restore is verified complete"
    db_q "DROP TABLE \`${KIT_INFLIGHT_TABLE}\`" > /dev/null \
        || die "the restore is complete, but the in-flight table ${KIT_INFLIGHT_TABLE} could not be dropped, so database ${DB_NAME} still counts as a partial copy and every later run refuses it. Fix the cause (the database user needs the DROP privilege), then DROP DATABASE \`${DB_NAME}\` and restore again: only a dropped database is cleared"
}

moodledata_marker_get() {
    if [ -f "$MOODLEDATA/$KIT_MARKER_FILE" ]; then
        head -n 1 "$MOODLEDATA/$KIT_MARKER_FILE" | tr -d '\r\n'
    fi
    return 0
}

# The marker file of the moodledata has up to three lines:
#   1  the restore id (what moodledata_marker_get reads)
#   2  archive=<identity of the archive the kit unpacked here>   written BEFORE the unpack starts
#   3  unpacked=<time>                                           written after the unpack succeeded
# They let a re-run tell "the same unpack, finished" from "a partial unpack", "another archive" and "not unpacked by the kit", and
# let a NEW restore refuse a dataroot that an earlier rehearsal has already used.

# archive_identity FILE -> absolute path | size | mtime. A 30 GB archive is not hashed: if the path, the size and the mtime are the
# same, it is the same archive.
archive_identity() {
    local f="$1" p
    # The resolved path is also what is measured: stat of a symlink reports the link, not the archive it points at.
    p="$(readlink -f "$f" 2> /dev/null || true)"
    [ -n "$p" ] || p="$f"
    printf '%s|%s|%s' "$p" "$(stat -c %s "$p")" "$(stat -c %Y "$p")"
}

# archive_kind FILE -> zip | gzip | bzip2 | xz | zstd | tar, by the first bytes (tar is everything else: GNU tar reads it uncompressed).
archive_kind() {
    local magic
    magic="$(head -c 6 "$1" 2> /dev/null | od -An -tx1 | tr -d ' \n')"
    case "$magic" in
        504b*) printf 'zip' ;;
        1f8b*) printf 'gzip' ;;
        425a68*) printf 'bzip2' ;;
        fd377a585a00*) printf 'xz' ;;
        28b52ffd*) printf 'zstd' ;;
        *) printf 'tar' ;;
    esac
}

# archive_stream KIND FILE -> the uncompressed bytes of a tar on stdout (KIND from archive_kind: gzip, bzip2, xz, zstd; tar = the file itself).
archive_stream() {
    case "$1" in
        gzip) gzip -dc -- "$2" ;;
        bzip2) bzip2 -dc -- "$2" ;;
        xz) xz -dc -- "$2" ;;
        zstd) zstd -dc -q -- "$2" ;;
        *) cat -- "$2" ;;
    esac
}

# tar_ends_complete KIND FILE -> 0 when the last 1024 bytes of the tar (as it is once uncompressed) are all NUL: the two zero blocks that end
# every tar (a writer that pads to a record, as GNU tar, bsdtar and python's tarfile do, only adds more NULs). GNU tar 1.35 extracts a tar
# that is cut exactly at a member header with exit status 0 and no message, so tar's own status cannot tell a cut archive from a whole one.
# A COMPRESSED tar is not safe either: the tar writer that dies leaves its pipe at end of input, and the compressor then closes its stream
# normally, so 'gzip -t' is happy with a perfectly valid .tar.gz that holds a cut tar. The compressed stream is therefore read to its end here
# (one decompression pass, which is also the integrity test of the compression: a failing decompressor is rc 1 through pipefail) and only
# its last 1024 bytes are kept. A file that is not a tar at all fails the same test (its last bytes are not NUL). This is a check of the
# FORMAT: only a checksum computed on the live server, where the archive was made (RESTORE_MOODLEDATA_SHA256) proves the archive is the one that was made.
# For an UNCOMPRESSED tar the format is not even that much: a zero-filled region (a pre-allocated or segmented copy that stopped, a file
# system that kept the size and lost the data) ends in zero blocks at any cut point, and GNU tar takes two zero blocks where a header is due for
# the end of the archive and exits 0. archive_proof therefore does not accept a plain tar on this test; it needs the checksum (round 7 review).
# A compressed tar is different: the decompressor fails on a zero-filled region (its CRC or length check), which is what makes this test enough.
tar_ends_complete() {
    local kind="$1" f="$2" hex
    # One pass: the last 1024 bytes of the (uncompressed) stream as hexadecimal. A failing decompressor is rc != 0 through pipefail.
    if [ "$kind" = tar ]; then
        # An uncompressed tar is a file that can be sought in: only its end is read (a tar of the moodledata is many gigabytes).
        hex="$(set -o pipefail; tail -c 1024 -- "$f" | od -An -v -tx1 | tr -d ' \n')" || return 1
    else
        hex="$(set -o pipefail; archive_stream "$kind" "$f" | tail -c 1024 | od -An -v -tx1 | tr -d ' \n')" || return 1
    fi
    # Fewer than 1024 bytes (an empty or tiny stream) is no end-of-archive marker; so is any byte other than NUL.
    [ "${#hex}" -ge 2048 ] || return 1
    [[ "$hex" =~ ^0+$ ]]
}

# tar_end_near_file_end FILE -> 0 when the end of the archive that GNU tar finds is where a whole tar leaves it: at least the two zero blocks (1024
# bytes) before the end of the file, and at most one record plus one block (10752 bytes). Sets TAR_END_BLOCK (the 512-byte block of the first zero
# block, or of the end of the file) and TAR_END_GAP (the bytes of the file after it).
# The end of a tar is two zero blocks, padded to a record. GNU tar (write_eot), bsdtar and Python's tarfile write ONE zero block and zero-fill the
# rest of the record, and a second whole record follows when that first zero block was the last block of its record: a correct archive then ends
# 512 + 10240 = 10752 bytes after the first zero block (the first zero block at offset 9728 mod 10240, one archive in twenty; round 8 refused those,
# round 9 fix). GNU tar takes the zero block for the end wherever a header is due, and exits 0. A copy that
# stopped part way (a pre-allocated or segmented download that kept the size) holds a region of zeros where members were: its last 1024 bytes
# are NUL (tar_ends_complete is satisfied) and tar unpacks everything before the region and says nothing about the rest. The region shows in the
# listing: with -R GNU tar prints the block where it found '** Block of NULs **' (or '** End of File **' when the file ends where a header is due,
# which a whole tar never does: it always keeps the 1024 bytes), and in a whole tar that block is at most 10752 bytes from the end of the file (a tar
# written with another blocking factor than the default 20 pads further: write it with the default). The listing reads the headers of the whole
# archive (the content of a member is skipped, not read) and runs with LC_ALL=C, because GNU tar translates the '** Block of NULs **' line; a tar
# that cannot be listed to its end (a cut inside a member: 'Unexpected EOF') is rc 1 as well. Round 8 (S3), round 9 (M, S4).
TAR_END_BLOCK=""
TAR_END_GAP=""
tar_end_near_file_end() {
    local f="$1" last size
    last="$(set -o pipefail; LC_ALL=C tar -tRf "$f" 2> /dev/null | LC_ALL=C awk '/^block [0-9]+: \*\* (Block of NULs|End of File) \*\*$/ { last = $2 } END { sub(/:$/, "", last); print last }')" || return 1
    [[ "$last" =~ ^[0-9]+$ ]] || return 1
    size="$(stat -c %s -- "$f")" || return 1
    TAR_END_BLOCK="$last"
    TAR_END_GAP=$((size - last * 512))
    [ "$TAR_END_GAP" -ge 1024 ] && [ "$TAR_END_GAP" -le 10752 ]
}

moodledata_marker_line() {
    if [ -f "$MOODLEDATA/$KIT_MARKER_FILE" ]; then
        sed -n "${1}p" "$MOODLEDATA/$KIT_MARKER_FILE" | tr -d '\r\n'
    fi
    return 0
}

# moodledata_unpack_state IDENTITY -> none (no archive recorded) | other (a different archive) | incomplete (this archive, never
# finished) | match (this archive, finished).
moodledata_unpack_state() {
    local want="$1" l2 l3
    l2="$(moodledata_marker_line 2)"
    l3="$(moodledata_marker_line 3)"
    case "$l2" in
        '') printf 'none' ;;
        "archive=${want}")
            if [ -n "$l3" ]; then
                printf 'match'
            else
                printf 'incomplete'
            fi
            ;;
        *) printf 'other' ;;
    esac
}

# moodledata_write_marker ID [IDENTITY [done]]: (re)write the marker file. IDENTITY adds the archive line, 'done' the unpacked line.
moodledata_write_marker() {
    mkdir -p "$MOODLEDATA"
    {
        printf '%s\n' "$1"
        if [ -n "${2:-}" ]; then
            printf 'archive=%s\n' "$2"
        fi
        if [ -n "${3:-}" ]; then
            printf 'unpacked=%s\n' "$(ts)"
        fi
    } > "$MOODLEDATA/$KIT_MARKER_FILE"
}

# moodledata_restamp ID: a new restore reuses an unpack that is already recorded (lines 2 and 3 stay as they are): only the id changes.
moodledata_restamp() {
    local rest=""
    if [ -f "$MOODLEDATA/$KIT_MARKER_FILE" ]; then
        rest="$(sed -n '2,$p' "$MOODLEDATA/$KIT_MARKER_FILE")"
    fi
    {
        printf '%s\n' "$1"
        if [ -n "$rest" ]; then
            printf '%s\n' "$rest"
        fi
    } > "$MOODLEDATA/$KIT_MARKER_FILE"
}

# moodledata_recent_writes -> the first file below sessions/ or localcache/ written in the last 30 minutes (nothing when there is
# none). A dataroot that a site is using shows it there within minutes; a copy nobody runs does not.
moodledata_recent_writes() {
    local d
    for d in "$MOODLEDATA/sessions" "$MOODLEDATA/localcache"; do
        [ -d "$d" ] || continue
        find "$d" -type f -mmin -30 2> /dev/null | head -n 1
    done | head -n 1
}

# ---------------------------------------------------------------------------------------------------------------------
# The in-flight unpack: a kit unpack of the moodledata that did not finish says so IN THE MOODLEDATA it was writing to
# ---------------------------------------------------------------------------------------------------------------------
# The moodledata half of the in-flight table above, for the same reason: an unpack that stopped half way leaves a filedir that looks
# restored, and a re-run with RESTORE_MOODLEDATA_ARCHIVE unset (or in another REHEARSAL_WORK) found a populated filedir and went on, so
# steps 02 to 11 ran on a partial copy. Step 01 creates this file in MOODLEDATA (restore id, archive identity, start time) BEFORE the unpack
# writes anything, and removes it only when the unpack has returned success. A MOODLEDATA that holds it is a PARTIAL copy: step 00, step 01
# and require_kit_marker (steps 02 to 11) refuse it whatever RESTORE_MOODLEDATA_ARCHIVE, RESTORE_MOODLEDATA_BY_HAND, RESTORE_DONE_BY_HAND or
# REHEARSAL_WORK say, because the fact is in the directory itself; only emptying the directory clears it. The file is created with noclobber,
# so it is also the claim on the directory: a second unpack into it (another run, another work directory) stops instead of writing over the first.
KIT_UNPACK_INFLIGHT_FILE=".rehearsal_unpack_inflight"
UNPACK_INFLIGHT_BODY=""

unpack_inflight_present() {
    [ -e "$MOODLEDATA/$KIT_UNPACK_INFLIGHT_FILE" ] || [ -L "$MOODLEDATA/$KIT_UNPACK_INFLIGHT_FILE" ]
}

# unpack_inflight_describe -> what the file says (restore id, archive, start), for a message; never fails.
unpack_inflight_describe() {
    local f="$MOODLEDATA/$KIT_UNPACK_INFLIGHT_FILE" id arch started
    id="$(sed -n '1s/^restore_id=//p' "$f" 2> /dev/null | tr -d '\r\n' || true)"
    arch="$(sed -n '2s/^archive=//p' "$f" 2> /dev/null | tr -d '\r\n' || true)"
    started="$(sed -n '3s/^started=//p' "$f" 2> /dev/null | tr -d '\r\n' || true)"
    printf 'restore %s..., archive %s, started %s' "${id:0:8}" "${arch:-?}" "${started:-?}"
}

# unpack_inflight_begin ID ARCHIVE_IDENTITY: claim the moodledata for this unpack, before the unpack writes anything.
unpack_inflight_begin() {
    local f="$MOODLEDATA/$KIT_UNPACK_INFLIGHT_FILE"
    mkdir -p "$MOODLEDATA"
    UNPACK_INFLIGHT_BODY="$(printf 'restore_id=%s\narchive=%s\nstarted=%s' "$1" "$2" "$(ts)")"
    log "claiming ${MOODLEDATA} for this unpack: creating ${KIT_UNPACK_INFLIGHT_FILE} (restore ${1:0:8}..., archive ${2}); it is removed only when the unpack has finished"
    if ! ( set -o noclobber; printf '%s\n' "$UNPACK_INFLIGHT_BODY" > "$f" ) 2> /dev/null; then
        die "cannot claim ${MOODLEDATA} for this unpack: ${f} could not be created. If it exists, another unpack is writing here (or did, and did not finish): the kit never writes over it. $(unpack_unfinished_advice). Otherwise the directory is not writable"
    fi
}

# unpack_inflight_end: the unpack returned success, so the moodledata no longer counts as a partial copy. The file must still be the one
# unpack_inflight_begin wrote: an archive taken from a moodledata whose own unpack had not finished carries the file itself and overwrites ours,
# and what was unpacked is then partial however cleanly tar ended.
unpack_inflight_end() {
    local f="$MOODLEDATA/$KIT_UNPACK_INFLIGHT_FILE"
    if [ "$(cat "$f" 2> /dev/null || true)" != "$UNPACK_INFLIGHT_BODY" ]; then
        die "the unpack of the moodledata returned success, but ${f} is not the file this run created (it now says: $(unpack_inflight_describe)): the archive carries its own ${KIT_UNPACK_INFLIGHT_FILE}, so it was made from a moodledata whose unpack had not finished, and what was unpacked is a PARTIAL copy. The file stays, so every kit step refuses this directory. $(unpack_unfinished_advice), and use a complete archive"
    fi
    rm -f "$f" || die "the unpack of the moodledata is complete, but ${f} could not be removed, so ${MOODLEDATA} still counts as a partial copy and every later run refuses it. $(unpack_unfinished_advice) (the directory must be writable)"
    log "OK: the unpack is verified complete: ${KIT_UNPACK_INFLIGHT_FILE} removed from ${MOODLEDATA}"
}

# moodledata_unfinished_unpack -> prints why MOODLEDATA holds an unpack that did not finish (nothing when it does not): the in-flight file
# above, or a marker file that records an 'archive=' line and no 'unpacked=' line (written before the unpack starts, completed after it:
# the record of a kit that had no in-flight file yet, or of one whose file was removed by hand).
moodledata_unfinished_unpack() {
    local l2 l3
    if unpack_inflight_present; then
        printf '%s is there (%s)' "$KIT_UNPACK_INFLIGHT_FILE" "$(unpack_inflight_describe)"
        return 0
    fi
    l2="$(moodledata_marker_line 2)"
    l3="$(moodledata_marker_line 3)"
    if [[ "$l2" == archive=* ]] && [ -z "$l3" ]; then
        printf '%s records an unpack of an archive (%s) and no "unpacked" line' "$KIT_MARKER_FILE" "${l2#archive=}"
    fi
    return 0
}

# unpack_unfinished_advice -> what to do about a moodledata that holds an unfinished unpack (one sentence, no full stop).
unpack_unfinished_advice() {
    printf 'The kit never adopts it, whatever RESTORE_MOODLEDATA_ARCHIVE, RESTORE_MOODLEDATA_BY_HAND or REHEARSAL_WORK say. Empty %s (or point MOODLEDATA at a new, empty directory) and run step 01 again, so that the kit unpacks the archive itself; or unpack the archive by hand into an EMPTY directory (first check its SHA-256 against the one taken where it was made, and that tar ran to the end of a COMPLETE archive: a tar cut at a member header also exits 0), set MOODLEDATA to it and RESTORE_MOODLEDATA_BY_HAND to its path' "$MOODLEDATA"
}

# STEP 01 FINISHED. Step 01 stamps the database and the moodledata early (right after the restore and the unpack), long before its file store
# gate and its neutralisation (SMTP credentials wiped, cron_enabled = 0, the OAuth2 tokens blanked) have run. A copy that is stamped is therefore
# NOT a copy that step 01 has cleared: a failed filedir gate, or a step 01 killed before the gate or the neutralisation (the round 6b TERM
# repro), left a stamped copy that steps 02 to 11 accepted, and live's OAuth2 refresh tokens stayed usable by the step 11 cron cycle (round 7
# review). So the very last thing a successful step 01 does is record the restore id it verified, in two places that must agree with
# state/kv/restore.id: the {config} row KIT_STEP01_KEY of the database (it travels with the neutralised data, so a snapshot or a dump taken
# before step 01 finished does not carry it) and state/kv/restore.verified; step 01 removes both when it starts on a copy it already
# stamped. require_kit_marker (steps 02 to 11) also asks that state/01.status is ok.
KIT_STEP01_KEY="rehearsal_kit_step01_ok"

# step01_unverify: a step 01 that is running is not finished (EXECUTE only; the database must be this rehearsal's, so after its marker check).
step01_unverify() {
    kv_unset restore.verified
    db_write "DELETE FROM {p}config WHERE name = '${KIT_STEP01_KEY}'"
}

# step01_verify ID: step 01 reached its end for restore ID: the very last act of the step.
step01_verify() {
    db_write "INSERT INTO {p}config (name, value) VALUES ('${KIT_STEP01_KEY}', '$1') ON DUPLICATE KEY UPDATE value = '$1'"
    kv_set restore.verified "$1"
    log "OK: step 01 finished for restore ${1:0:8}...: recorded in the database ({config} ${KIT_STEP01_KEY}) and in state/kv/restore.verified; steps 02 to 11 accept this copy"
}

# step01_unfinished ID -> prints why step 01 has NOT finished ok for restore ID (nothing when it has); rc 1 (nothing printed) when the
# database's record could not be read, which a caller must treat as "cannot tell", never as finished.
step01_unfinished() {
    local want="$1" have st kvv
    if ! step_done_ok 01; then
        st="$(sed -n 's/^status=//p' "$STATE_DIR/01.status" 2> /dev/null | head -n 1 || true)"
        printf 'state/01.status says %s, not ok' "${st:-nothing (there is no state/01.status)}"
        return 0
    fi
    kvv="$(kv_get restore.verified)"
    if [ "$kvv" != "$want" ]; then
        if [ -n "$kvv" ]; then
            printf 'state/kv/restore.verified holds %s..., not this restore id %s...' "${kvv:0:8}" "${want:0:8}"
        else
            printf 'state/kv/restore.verified is not set'
        fi
        return 0
    fi
    have="$(config_row_get "$KIT_STEP01_KEY")" || return 1
    if [ "$have" != "$want" ]; then
        printf 'the database holds no {config} row %s for this restore (it holds %s)' "$KIT_STEP01_KEY" "${have:-none}"
    fi
    return 0
}

# require_kit_marker [during-step-01]: EXECUTE stops unless the database AND the moodledata carry the id that state/kv/restore.id holds,
# neither is a partial copy (the database holds no in-flight table, the moodledata no in-flight file and no unpack record that did not
# finish), and step 01 has finished ok for this restore (see STEP 01 FINISHED above). Step 01's own call passes during-step-01: it is the
# step that makes the copy finished.
require_kit_marker() {
    [ "$EXECUTE" = 1 ] || return 0
    local want have dataid inflight unfinished why
    # The client's option file (it holds the database password) is made HERE, in the step's own shell, before the first command substitution: a
    # $(...) below that ran first made its own temp directory with its own copy of the file, and nothing removes what a subshell made (the step's
    # EXIT trap removes TMP_DIR of the step's shell only): one directory per read was left in TMPDIR (round 7 review).
    db_init_cnf
    want="$(kv_get restore.id)"
    [ -n "$want" ] || die "no restore id is recorded (state/kv/restore.id): step 01 has not stamped this rehearsal. Run step 01 first"
    # A marker row can be in a partial copy (a dump or a snapshot of a stamped database that died after {config} was loaded): the marker
    # does not make a database whole. One query.
    inflight="$(inflight_count)" \
        || die "database ${DB_NAME}: whether it holds the in-flight table ${KIT_INFLIGHT_TABLE} could not be read (the client answered nothing, or with an error other than 'table does not exist'): it is treated as a partial restore until it can be read. Run this step again once the server answers"
    [ "$inflight" = 0 ] || die "database ${DB_NAME} holds the table ${KIT_INFLIGHT_TABLE}: a restore the kit started into it did not finish ($(inflight_describe)), so it is a PARTIAL copy whatever marker it carries. Refused. Only DROP DATABASE clears it: restore again into an empty database (step 01)"
    unfinished="$(moodledata_unfinished_unpack)"
    [ -z "$unfinished" ] || die "${MOODLEDATA} holds an unpack that did not finish (${unfinished}), so it is a PARTIAL copy whatever marker it carries. Refused. $(unpack_unfinished_advice)"
    have="$(marker_get)" \
        || die "database ${DB_NAME}: its rehearsal-kit marker could not be read (a failed read, or a blank answer for a row that exists): cannot tell whether it is this rehearsal's copy, so the kit will not write to it. Run this step again once the server answers"
    [ -n "$have" ] || die "database ${DB_NAME} carries no rehearsal-kit marker: it is not a copy this kit restored (step 01), so the kit will not write to it"
    [ "$have" = "$want" ] || die "database ${DB_NAME} carries restore id ${have:0:8}..., but this work directory belongs to restore ${want:0:8}...: another restore, or a database that is not this rehearsal's. Refused"
    dataid="$(moodledata_marker_get)"
    [ -n "$dataid" ] || die "${MOODLEDATA} carries no ${KIT_MARKER_FILE}: it is not a moodledata this kit stamped (step 01), so the kit will not write to it"
    [ "$dataid" = "$want" ] || die "${MOODLEDATA} carries restore id ${dataid:0:8}..., not ${want:0:8}...: it belongs to another rehearsal or site. Refused"
    if [ "${1:-}" != during-step-01 ]; then
        why="$(step01_unfinished "$want")" \
            || die "database ${DB_NAME}: the record that step 01 finished for restore ${want:0:8}... ({config} ${KIT_STEP01_KEY}) could not be read (a failed read, or a blank answer for a row that exists): cannot tell whether step 01 finished, so the kit will not write to this copy. Run this step again once the server answers"
        [ -z "$why" ] || die "step 01 (restore check) has not finished ok for this restore (${why}): the copy is stamped, but its filedir gate and its neutralisation (SMTP credentials wiped, cron_enabled = 0, the restored OAuth2 tokens blanked) have not all been done, so step ${STEP_ID} and every step after it refuse it. Fix what step 01 reported and run it again: bash tools/rehearsal/run_all.sh --execute --from 01"
    fi
}

# restore_dataroot_used ID -> 0 when restore-ids.log records that a step after 01 ran against the moodledata of restore ID (written by
# rotate_work_state, because the status files that say so are archived by the same rotation).
restore_dataroot_used() {
    [ -n "${1:-}" ] && [ -f "$REHEARSAL_WORK/restore-ids.log" ] \
        && awk -v id="$1" '$1 == id && $2 == "used" { found = 1 } END { exit !found }' "$REHEARSAL_WORK/restore-ids.log"
}

# work_state_has_history -> 0 when state/ holds results of a rehearsal (kv values, or a status file of step 01 or later). A status file that
# says 'running' is not a result: it is the record a step writes at its start (step 01's own, in the run that asks), or what a step that
# died without a trap leaves.
work_state_has_history() {
    local f
    if [ -d "$STATE_DIR/kv" ] && [ -n "$(ls -A "$STATE_DIR/kv" 2> /dev/null)" ]; then
        return 0
    fi
    for f in "$STATE_DIR"/0[1-9].status "$STATE_DIR"/1[0-2].status; do
        if [ -f "$f" ] && ! grep -qx 'status=running' "$f"; then
            return 0
        fi
    done
    return 1
}

# rotate_work_state: a new restore starts a new rehearsal. state/, reports/, baseline/, muc/ (the rehearsal's own cache
# configuration: the 5.x run of the earlier rehearsal wrote a cacheconfig.php there that hop 1's 4.5 code must not start on) and
# logs/timings.tsv of the earlier one move to archive/<stamp>-<id>/ (never deleted) so nothing of it can be mistaken for this
# one's result. 00.status (the preflight of this very run) stays.
rotate_work_state() {
    [ "$EXECUTE" = 1 ] || return 0
    local stamp old dest d keep00=""
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    old="$(kv_get restore.id)"
    dest="$REHEARSAL_WORK/archive/${stamp}-${old:0:8}"
    [ -n "$old" ] || dest="$REHEARSAL_WORK/archive/${stamp}-unstamped"
    mkdir -p "$dest"
    # The one fact of state/ that must outlive the rotation: a step after 01 ran against this restore's moodledata (hop 1 and 2, the
    # repairs, the role-9 state file, caches, sessions and cron files). Step 01 refuses to reuse such a dataroot for a new restore, and
    # a restore that dies after this rotation is retried with the status files already in archive/. The line goes into restore-ids.log,
    # which no rotation moves, keyed by the restore id the dataroot carries.
    if [ -n "$old" ]; then
        for d in "$STATE_DIR"/0[2-9].status "$STATE_DIR"/1[0-2].status; do
            if [ -f "$d" ]; then
                printf '%s used %s\n' "$old" "$(ts)" >> "$REHEARSAL_WORK/restore-ids.log"
                break
            fi
        done
    fi
    if [ -f "$STATE_DIR/00.status" ]; then
        keep00="$(cat "$STATE_DIR/00.status")"
    fi
    for d in "$STATE_DIR" "$REPORT_DIR" "$BASELINE_DIR" "$REHEARSAL_WORK/muc"; do
        if [ -d "$d" ]; then
            mv "$d" "$dest/"
        fi
    done
    if [ -f "$TIMINGS_FILE" ]; then
        mv "$TIMINGS_FILE" "$dest/"
    fi
    mkdir -p "$STATE_DIR" "$REPORT_DIR" "$BASELINE_DIR"
    if [ -n "$keep00" ]; then
        printf '%s\n' "$keep00" > "$STATE_DIR/00.status"
    fi
    log "an earlier rehearsal's state, reports, baseline, cache configuration and timings moved to ${dest} (a new restore starts a new rehearsal)"
}

# ---------------------------------------------------------------------------------------------------------------------
# What the kit records about the code it ran
# ---------------------------------------------------------------------------------------------------------------------
# The Moodle 5.0 upgrade (lib/db/upgrade.php, 2025040100.01) calls uninstall_plugin() for these two module types when their code is
# not on disk, and uninstalling a module type deletes every activity of it with its completion rows and instance tables. The 5.x
# plugin directories are public/mod/survey and public/mod/chat.
HOP2_UNINSTALLS_MISSING_MODULES="survey chat"

# modules_lost_in_hop2 DIR -> "name count" for each module type the 5.0 upgrade uninstalls when absent (above) that has activities in
# this database and whose code is NOT in the 5.x tree DIR/public/mod/<name>/version.php. Nothing printed = nothing would be lost.
# Returns 1 when the database cannot be asked.
modules_lost_in_hop2() {
    local dir="$1" name n
    for name in $HOP2_UNINSTALLS_MISSING_MODULES; do
        if [ -f "$dir/public/mod/$name/version.php" ]; then
            continue
        fi
        n="$(db_scalar "SELECT COUNT(*) FROM {p}course_modules cm JOIN {p}modules m ON m.id = cm.module WHERE m.name = '${name}'")" || return 1
        if [ "${n:-0}" -gt 0 ]; then
            printf '%s %s\n' "$name" "$n"
        fi
    done
    return 0
}

# tree_manifest_sha DIR -> SHA-256 over every version.php below DIR (core and every plugin: path and content hash), so the
# summary names the code that really ran, not only the archive the operator said it unpacked.
tree_manifest_sha() {
    ( cd "$1" && find . -name version.php -type f -not -path './node_modules/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -d ' ' -f 1 )
}

# ---------------------------------------------------------------------------------------------------------------------
# Step 06 and step 09 recovery: pure helpers (selftest.sh tests them without a database)
# ---------------------------------------------------------------------------------------------------------------------
# adr031_role9_dry_counts < dry-run text -> "<capabilities to prohibit> <allow rows to remove>" from the line
#   DRY RUN: N capabilities would be prohibited (M already are) and K allow row(s) removed for role X. Nothing changed.
# Prints nothing and returns 1 when the line is not there.
adr031_role9_dry_counts() {
    sed -n 's/^DRY RUN: \([0-9][0-9]*\) capabilities would be prohibited ([0-9][0-9]* already are) and \([0-9][0-9]*\) allow row(s) removed.*/\1 \2/p' | tail -n 1 | grep .
}

# import_phase APPLIED_KV RUN_STATUS MODE -> where a re-run of step 09 stands, decided from the kit's record AND the
# database's own run table (the newest apply run's status: running, failed, aborted, complete; empty = no apply run):
#   recorded  the kit recorded a finished apply (only the verify, and the judgement of the recorded exit, remain);
#   fresh     nothing was applied: the data-intact gate, the preflight and the dry run come first;
#   resume    an apply run is not complete and IMPORT_APPLY_MODE=resume: continue it, the gate and dry run are history;
#   recover   a complete apply run exists that the kit never recorded (read it from the report);
#   refuse:.. an unfinished run exists and the operator did not choose resume.
import_phase() {
    local applied="$1" run="$2" mode="$3"
    if [ -n "$applied" ]; then
        printf 'recorded'
        return 0
    fi
    case "$run" in
        '') printf 'fresh' ;;
        complete) printf 'recover' ;;
        *)
            if [ "$mode" = resume ]; then
                printf 'resume'
            else
                printf 'refuse:an apply run of this import is %s in the database. Set IMPORT_APPLY_MODE=resume to continue it, or restore the snapshot taken before step 09 and delete state/kv/import.*' "$run"
            fi
            ;;
    esac
}

# parity_only_pre_repair_invariant FILE -> 0 when the output of migration_parity_check.php --compare has exactly one RESULT
# line and it says the only failed invariant is message_provider_defaults (what repair_task_registrations.php --apply, step 05,
# repairs). Any drift, legacy change or other invariant adds a RESULT line, and then this is not the case.
parity_only_pre_repair_invariant() {
    [ -f "$1" ] || return 1
    [ "$(grep -c '^RESULT:' "$1" || true)" = 1 ] || return 1
    grep -qx 'RESULT: 1 invariant(s) FAILED (message_provider_defaults).' "$1"
}

# names_our_tree TEXT -> 0 when TEXT names one of the two code trees of this rehearsal (a scheduler line that runs THIS rehearsal's cron).
# The directory counts as a whole path word: the characters before and after it must not be able to continue a path name. A real
# scheduler line is usually 'cd /srv/rehearsal/moodle5 && php admin/cli/cron.php', which names the tree with no slash after it;
# '/srv/rehearsal/moodle5-uat/admin/cli/cron.php' and '/mnt/srv/rehearsal/moodle5/...' are other trees and do not count.
names_our_tree() {
    local text="$1" dir rest before after pc nc
    for dir in "${CODE_45_DIR%/}" "${CODE_5X_DIR%/}"; do
        [ -n "$dir" ] || continue
        rest="$text"
        while [[ "$rest" == *"$dir"* ]]; do
            before="${rest%%"$dir"*}"
            after="${rest#*"$dir"}"
            pc="${before: -1}"
            nc="${after:0:1}"
            if { [ -z "$pc" ] || [[ ! "$pc" =~ [A-Za-z0-9_.+-] ]]; } && { [ -z "$nc" ] || [[ ! "$nc" =~ [A-Za-z0-9_.+-] ]]; }; then
                return 0
            fi
            rest="$after"
        done
    done
    return 1
}
