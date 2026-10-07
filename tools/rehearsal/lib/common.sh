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

# db_name_denied NAME -> returns 0 when NAME can never be a rehearsal database.
db_name_denied() {
    case "$1" in
        moodle | mysql | sys | test | information_schema | performance_schema | mariadb) return 0 ;;
    esac
    case "$1" in
        prod | prod_* | *_prod | *_prod_* | production | production_* | *_production | *_production_* | live | live_* | *_live | *_live_*) return 0 ;;
    esac
    return 1
}

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
    export SOURCE_BASELINE_PHP

    local v missing=""
    for v in $REQUIRED_VARS; do
        if [ -z "${!v:-}" ]; then
            missing+=" ${v}"
        fi
    done
    [ -z "$missing" ] || die "the env file leaves these settings empty:${missing}"

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
# step_init NN name [args...]: parse the arguments, load and validate the env, open the log, arm the exit trap.
step_init() {
    STEP_ID="$1"
    STEP_NAME="$2"
    shift 2
    SCRIPT_PATH="${BASH_SOURCE[1]}"
    EXTRA_ARGS=()
    parse_args "$@"
    load_env
    if [ "$EXECUTE" = 1 ]; then
        mkdir -p "$LOG_DIR" "$STATE_DIR" "$REPORT_DIR" "$BASELINE_DIR"
        (umask 077; mkdir -p "$CONF_DIR")
        LOG_FILE="$LOG_DIR/${STEP_ID}-${STEP_NAME}.log"
        exec > >(tee -a "$LOG_FILE") 2>&1
    fi
    STEP_T0="$(epoch)"
    trap 'on_exit $?' EXIT
    trap 'log "ERROR: a command failed (rc=$?) at ${BASH_SOURCE[0]##*/}:${LINENO}: ${BASH_COMMAND}"' ERR
    log "step ${STEP_ID} ${STEP_NAME} start; mode $(mode_name); kit $(kit_rev); env ${ENV_FILE}"
}

on_exit() {
    local rc="$1" t1 seconds status
    t1="$(epoch)"
    seconds=$((t1 - STEP_T0))
    case "$rc" in
        0) status="ok" ;;
        2) status="unproven" ;;
        *) status="fail" ;;
    esac
    if [ -n "$TMP_DIR" ] && [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
    fi
    log "step ${STEP_ID} ${STEP_NAME} ${status} (rc ${rc}) in ${seconds}s, ${WARNINGS} warning(s), mode $(mode_name)"
    if [ "$EXECUTE" = 1 ] && [ -n "${STATE_DIR:-}" ] && [ -d "${STATE_DIR:-/nonexistent}" ]; then
        {
            printf 'status=%s\nrc=%s\nwarnings=%s\nseconds=%s\nfinished=%s\nname=%s\nkit=%s\n' \
                "$status" "$rc" "$WARNINGS" "$seconds" "$(ts)" "$STEP_NAME" "$(kit_rev)"
        } > "$STATE_DIR/${STEP_ID}.status"
    fi
}

# step_done_ok NN -> 0 when step NN finished ok in EXECUTE mode.
step_done_ok() {
    local f="$STATE_DIR/$1.status"
    [ -f "$f" ] && grep -qx 'status=ok' "$f"
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
    "$MYSQL_BIN" --defaults-extra-file="$DB_CNF" --batch --skip-column-names "$@"
}

# db_q SQL: run SQL on the rehearsal database; rows come back tab-separated without a header. {p} is the table prefix.
db_q() {
    [ "$EXECUTE" = 1 ] || die "internal error: db_q called in DRY mode"
    db_init_cnf
    local sql="${1//\{p\}/$DB_PREFIX}"
    "$MYSQL_BIN" --defaults-extra-file="$DB_CNF" --batch --skip-column-names "$DB_NAME" -e "$sql"
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
    local exists
    exists="$(mysql_nodb -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${DB_NAME}'")"
    if [ "$exists" != 1 ]; then
        DB_STATE="absent"
        return 0
    fi
    DB_TABLES="$(db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}'")"
    if [ "$DB_TABLES" = 0 ]; then
        DB_STATE="empty"
    else
        DB_STATE="present"
    fi
}

# db_config_value NAME -> the value of a {config} row (empty when absent). Needs a Moodle database.
db_config_value() {
    db_first "SELECT value FROM {p}config WHERE name = '$1'"
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

# snapshot_hook LABEL: the operator's snapshot command (RDS snapshot, LVM, dump), if any.
snapshot_hook() {
    if [ -n "${SNAPSHOT_HOOK:-}" ]; then
        timed "snapshot $1" "$SNAPSHOT_HOOK" "$1" || die "the snapshot hook failed for $1"
    else
        note "no SNAPSHOT_HOOK configured: take the restore point for '$1' by hand now (the rollback of a failed hop is a restore)"
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

# comm_only_first A B -> lines of sorted A that are not in sorted B; comm_only_second the other way.
comm_only_first() { LC_ALL=C comm -23 "$1" "$2"; }
comm_only_second() { LC_ALL=C comm -13 "$1" "$2"; }
