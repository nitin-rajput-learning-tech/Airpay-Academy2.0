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
    # The server must not be production or UAT: refuse one that holds the live schema (airpayprod) or any schema the env
    # file lists in FORBIDDEN_SERVER_SCHEMAS (add UAT's schema there unless this rehearsal shares UAT's server).
    local schema schema_l forbidden hit=""
    while IFS= read -r schema; do
        schema="${schema%$'\r'}"
        schema_l="${schema,,}"
        for forbidden in $KIT_LIVE_SCHEMAS ${FORBIDDEN_SERVER_SCHEMAS,,}; do
            if [ "$schema_l" = "$forbidden" ]; then
                hit="$schema"
            fi
        done
    done < <(mysql_nodb -e 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA')
    if [ -n "$hit" ]; then
        die "the database server at ${DB_HOST} holds the schema '${hit}': this is production or UAT, not a rehearsal server. Refused"
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
#     server that may be UAT's.
# It reads the whole file once (the trailer check reads only its tail), before the restore reads it again.
dump_unsafe_statement() {
    { dump_stream "$1" | grep -a -m1 -Ei -e '^[[:space:]]*(/\*![0-9]*[[:space:]]+)?(use[[:space:]]|create[[:space:]]+(database|schema)[[:space:]]|drop[[:space:]]+(database|schema)[[:space:]]|set[[:space:]]+(@@global\.|global[[:space:]]+)gtid_purged)' | cut -c1-160; } 2> /dev/null || true
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

marker_get() {
    # The marker of the database, or nothing (also nothing when there is no {config} table). An empty read is checked against a
    # COUNT first (see db_config_value): a client that printed nothing must not look like a database without the marker.
    local v="" tries=0
    while :; do
        v="$(db_first "SELECT value FROM {p}config WHERE name = '${KIT_MARKER_KEY}'" 2> /dev/null)" || return 0
        if [ -n "$v" ]; then
            break
        fi
        if [ "$(db_scalar "SELECT COUNT(*) FROM {p}config WHERE name = '${KIT_MARKER_KEY}'" 2> /dev/null || printf 0)" = 0 ]; then
            break
        fi
        tries=$((tries + 1))
        if [ "$tries" -ge 3 ]; then
            return 0
        fi
        sleep 1
    done
    printf '%s' "$v"
}

marker_set() {
    db_write "INSERT INTO {p}config (name, value) VALUES ('${KIT_MARKER_KEY}', '$1') ON DUPLICATE KEY UPDATE value = '$1'"
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

# require_kit_marker: EXECUTE stops unless the database AND the moodledata carry the id that state/kv/restore.id holds.
require_kit_marker() {
    [ "$EXECUTE" = 1 ] || return 0
    local want have dataid
    want="$(kv_get restore.id)"
    [ -n "$want" ] || die "no restore id is recorded (state/kv/restore.id): step 01 has not stamped this rehearsal. Run step 01 first"
    have="$(marker_get)"
    [ -n "$have" ] || die "database ${DB_NAME} carries no rehearsal-kit marker: it is not a copy this kit restored (step 01), so the kit will not write to it"
    [ "$have" = "$want" ] || die "database ${DB_NAME} carries restore id ${have:0:8}..., but this work directory belongs to restore ${want:0:8}...: another restore, or a database that is not this rehearsal's. Refused"
    dataid="$(moodledata_marker_get)"
    [ -n "$dataid" ] || die "${MOODLEDATA} carries no ${KIT_MARKER_FILE}: it is not a moodledata this kit stamped (step 01), so the kit will not write to it"
    [ "$dataid" = "$want" ] || die "${MOODLEDATA} carries restore id ${dataid:0:8}..., not ${want:0:8}...: it belongs to another rehearsal or site. Refused"
}

# work_state_has_history -> 0 when state/ holds results of a rehearsal (kv values, or a status file of step 01 or later).
work_state_has_history() {
    local f
    if [ -d "$STATE_DIR/kv" ] && [ -n "$(ls -A "$STATE_DIR/kv" 2> /dev/null)" ]; then
        return 0
    fi
    for f in "$STATE_DIR"/0[1-9].status "$STATE_DIR"/1[0-2].status; do
        if [ -f "$f" ]; then
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
