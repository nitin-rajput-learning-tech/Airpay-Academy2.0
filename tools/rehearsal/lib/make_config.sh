#!/usr/bin/env bash
# Stage B rehearsal kit -- write a rehearsal config.php for one code tree.
#
# Port of make_config.py from the first local rehearsal (2026-09-30), which learned the rule this keeps: a rehearsal
# codebase must refuse to load against any database that is not the rehearsal one. The generated file carries
#   * the settings from the env file (database, wwwroot, dataroot), the DB password read from DB_PASS_FILE,
#   * $CFG->noemailever = true (the dump holds real e-mail addresses; the May 2026 incident),
#   * a REHEARSAL GUARD block that exits when the database is not on the allow-list or when the database host or the
#     wwwroot names a production host. It runs on every load, CLI or web.
#
#   bash lib/make_config.sh --tree 45        CODE_45_DIR/config.php
#   bash lib/make_config.sh --tree 5x        CODE_5X_DIR/config.php (and public/config.php, the stock 5.x loader, when
#                                            the package has none: the build recipe leaves it out of the zip)
#   bash lib/make_config.sh --tree db        CONF_DIR/source-db.config.php: the database settings only, read as DATA by
#                                            cli/source_baseline.php (it is never loaded by Moodle)
#
# A config.php that the kit did not write (no REHEARSAL-KIT-CONFIG marker) is never overwritten, unless FORCE_CONFIG=1.
# Idempotent. DRY by default; --execute writes.

# shellcheck source=common.sh
. "$(dirname "${BASH_SOURCE[0]}")/common.sh"

# cache_config_dir -> the directory of the rehearsal's own cache configuration (Moodle writes cacheconfig.php there).
cache_config_dir() { printf '%s/muc' "${REHEARSAL_WORK%/}"; }

# php_array_literal WORDS... -> 'a', 'b'
php_array_literal() {
    local out="" w
    for w in "$@"; do
        out+="'$(php_squote "$w")', "
    done
    printf '%s' "${out%, }"
}

# render_config TREE -> the config.php text on stdout.
render_config() {
    local tree="$1" collation port
    collation="$DB_COLLATION"
    port="$DB_PORT"
    printf '%s\n' '<?php  // Moodle configuration file - STAGE B REHEARSAL ONLY. Written by tools/rehearsal/lib/make_config.sh.'
    printf '// %s\n' "$KIT_CONFIG_MARKER"
    if [ "$tree" = db ]; then
        printf '%s\n' '// Database settings only: cli/source_baseline.php reads this file as data and never runs it.'
    fi
    printf '%s\n' 'unset($CFG);' 'global $CFG;' '$CFG = new stdClass();' ''
    printf "\$CFG->dbtype    = '%s';\n" "$(php_squote "$DB_TYPE")"
    printf '%s\n' "\$CFG->dblibrary = 'native';"
    printf "\$CFG->dbhost    = '%s';\n" "$(php_squote "$DB_HOST")"
    printf "\$CFG->dbname    = '%s';\n" "$(php_squote "$DB_NAME")"
    printf "\$CFG->dbuser    = '%s';\n" "$(php_squote "$DB_USER")"
    printf "\$CFG->dbpass    = '%s';\n" "$(php_squote "$(db_password)")"
    printf "\$CFG->prefix    = '%s';\n" "$(php_squote "$DB_PREFIX")"
    printf "\$CFG->dboptions = array('dbpersist' => 0, 'dbport' => '%s', 'dbsocket' => '', 'dbcollation' => '%s');\n" \
        "$(php_squote "$port")" "$(php_squote "$collation")"
    printf '\n'
    printf "\$CFG->wwwroot   = '%s';\n" "$(php_squote "$REHEARSAL_WWWROOT")"
    if [ "$tree" = db ]; then
        return 0
    fi
    printf "\$CFG->dataroot  = '%s';\n" "$(php_squote "$MOODLEDATA")"
    printf '%s\n' "\$CFG->admin     = 'admin';"
    printf '%s\n' '$CFG->directorypermissions = 02770;'
    printf '%s\n' '$CFG->noemailever = true;       // the dump holds real addresses: never send'
    if [ -n "$DIVERT_EMAILS_TO" ]; then
        printf "\$CFG->divertallemailsto = '%s';\n" "$(php_squote "$DIVERT_EMAILS_TO")"
    fi
    # The restored moodledata brings live's muc/config.php (the cache stores), which Moodle loads from dataroot: a Redis or
    # memcached store named there would be flushed and refilled by every purge_caches of the rehearsal. The kit's own cache
    # directory (kit-owned, writable, created by make_config) is used instead; step 01 also moves the restored file aside.
    printf "\$CFG->altcacheconfigpath = '%s';\n" "$(php_squote "$(cache_config_dir)")"
    printf '%s\n' '$CFG->debug = 32767;' '$CFG->debugdisplay = 0;' ''
    printf '%s\n' '// REHEARSAL GUARD: this code tree must never run against another database, or name a production host.'
    # shellcheck disable=SC2086
    printf '$rehearsalallowed = array(%s);\n' "$(php_array_literal $REHEARSAL_DB_ALLOWLIST)"
    # shellcheck disable=SC2086
    printf '$rehearsalprodhosts = array(%s);\n' "$(php_array_literal $PRODUCTION_HOSTNAMES)"
    printf "\$rehearsalprefix = '%s';\n" "$(php_squote "$DB_PREFIX")"
    cat <<'PHPGUARD'
if (!in_array($CFG->dbname, $rehearsalallowed, true) || $CFG->prefix !== $rehearsalprefix) {
    fwrite(STDERR, "REHEARSAL GUARD: refusing to run against {$CFG->dbname} (prefix {$CFG->prefix})\n");
    exit(1);
}
foreach ($rehearsalprodhosts as $rehearsalhost) {
    if (stripos($CFG->dbhost . ' ' . $CFG->wwwroot, $rehearsalhost) !== false) {
        fwrite(STDERR, "REHEARSAL GUARD: the database host or wwwroot names a production host ({$rehearsalhost})\n");
        exit(1);
    }
}

require_once(__DIR__ . '/lib/setup.php');
PHPGUARD
}

# write_kit_file TARGET BODY MODE-UMASK LABEL -> idempotent, marker-protected write.
write_kit_file() {
    local target="$1" body="$2" mask="$3" label="$4"
    if [ -f "$target" ]; then
        if grep -q 'REHEARSAL-KIT-' "$target"; then
            if [ "$(cat "$target")" = "$body" ]; then
                log "${label}: ${target} is already what the kit would write"
                return 0
            fi
            log "${label}: ${target} was written by the kit; rewriting it"
        elif [ "${FORCE_CONFIG:-0}" = 1 ]; then
            warn "${label}: ${target} was NOT written by the kit; overwritten because FORCE_CONFIG=1"
        else
            die "${label}: ${target} exists and was not written by the kit (no REHEARSAL-KIT marker). Check it by hand against the env file, or set FORCE_CONFIG=1 to replace it"
        fi
    fi
    if [ "$EXECUTE" != 1 ]; then
        dry "would write ${label}: ${target}"
        return 0
    fi
    ( umask "$mask"; printf '%s\n' "$body" > "$target" )
    log "${label}: wrote ${target} (database ${DB_NAME}; the password is not shown)"
}

# make_config TREE
make_config() {
    local tree="$1" body
    check_pass_file || {
        if [ "$EXECUTE" = 1 ]; then
            die "DB_PASS_FILE ${DB_PASS_FILE} must exist and must not be readable by others"
        fi
        warn "DB_PASS_FILE ${DB_PASS_FILE} is not usable here (needed with --execute)"
    }
    assert_db_allowed
    body="$(render_config "$tree")"
    if [ "$EXECUTE" = 1 ] && [ "$tree" != db ]; then
        # Moodle falls back to dataroot/muc/config.php (live's cache stores) when this directory is missing or not writable.
        (umask 027; mkdir -p "$(cache_config_dir)")
        [ -w "$(cache_config_dir)" ] || die "the cache configuration directory $(cache_config_dir) is not writable by $(id -un)"
    fi
    case "$tree" in
        45)
            [ -d "$CODE_45_DIR" ] || [ "$EXECUTE" != 1 ] || die "CODE_45_DIR ${CODE_45_DIR} does not exist: unpack the 4.5 core first"
            write_kit_file "$CODE_45_DIR/config.php" "$body" 027 "config (4.5 tree)"
            ;;
        5x)
            [ -d "$CODE_5X_DIR/public" ] || [ "$EXECUTE" != 1 ] || die "${CODE_5X_DIR}/public does not exist: unpack the Sentientia package first"
            write_kit_file "$CODE_5X_DIR/config.php" "$body" 027 "config (5.x tree)"
            if [ ! -f "$CODE_5X_DIR/public/config.php" ]; then
                write_kit_file "$CODE_5X_DIR/public/config.php" "$(printf '%s\n' \
                    '<?php' '// REHEARSAL-KIT-LOADER: the stock Moodle 5.x loader, which the package build leaves out of the zip.' \
                    '$configfile = __DIR__ . '"'"'/../config.php'"'"';' \
                    'if (!file_exists($configfile)) {' '    header("Location: install.php");' '    die;' '}' \
                    'require_once($configfile);')" 022 "config loader (5.x public/)"
            fi
            ;;
        db)
            write_kit_file "$SOURCE_DB_CONFIG" "$body" 077 "database settings for the baseline tool"
            ;;
        *)
            die "unknown tree '${tree}' (45, 5x or db)"
            ;;
    esac
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    step_init cfg make_config "$@"
    tree=""
    i=0
    while [ "$i" -lt "${#EXTRA_ARGS[@]}" ]; do
        case "${EXTRA_ARGS[$i]}" in
            --tree)
                i=$((i + 1))
                tree="${EXTRA_ARGS[$i]:-}"
                ;;
            --tree=*) tree="${EXTRA_ARGS[$i]#--tree=}" ;;
            *) die "unknown argument: ${EXTRA_ARGS[$i]}" ;;
        esac
        i=$((i + 1))
    done
    [ -n "$tree" ] || die "give --tree 45, --tree 5x or --tree db"
    make_config "$tree"
    step_end
fi
