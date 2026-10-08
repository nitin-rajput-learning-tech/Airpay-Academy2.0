#!/usr/bin/env bash
# 00 preflight -- refuse to go on unless this box and this configuration are safe for a rehearsal.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md "Inputs" and step 1 (noemailever MANDATORY: the dump holds real addresses; the
# May 2026 incident); migration plan 1.3 (noemailever from restore through the verifying cron run), 4d step 3 (cron is NOT
# firing on the new box), 8 step 2, 9 (the rollout gate).
#
# It changes nothing, even with --execute. The checks that need no database run in DRY mode too.
# A failed check does not stop the others: the step lists every problem, then stops.
#
# Refuses unless:
#   * the database is on the explicit rehearsal allow-list (REHEARSAL_DB_ALLOWLIST) and is no production or system name;
#   * no production hostname appears in any host or path setting, nor in any string of a config.php already present;
#   * a config.php already present points only at the rehearsal: allow-listed database, DB_HOST, the rehearsal wwwroot and
#     dataroot, $CFG->noemailever = true;
#   * no scheduler on the box runs THIS rehearsal's Moodle cron (a crontab, /etc/cron.d or systemd timer line that names
#     CODE_45_DIR or CODE_5X_DIR; another site's cron.php is noted, not refused) and, once step 01 has run, the
#     database has cron_enabled = 0 (the guard that holds whatever a scheduler does);
#   * PRODUCTION_DB_ENDPOINT (the live database host) is set with --execute and is refused in every host and path setting;
#     the database name carries neither 'prod' nor 'uat' (production's is airpayprod, UAT's sentientia_uat);
#   * the paths are absolute POSIX paths, the two code trees are two separate directories, the kit runs as WEB_USER,
#     the tools, PHP version and files the steps need are there.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 00 preflight "$@"

FAILS=0
fail() {
    log "FAIL: $*"
    FAILS=$((FAILS + 1))
}
pass() { log "OK: $*"; }

# ---------------------------------------------------------------------------------------------------------------------
log "database policy (checked when the env file was loaded): database '${DB_NAME}' on host '${DB_HOST}', allow-list '${REHEARSAL_DB_ALLOWLIST}'"
pass "the database is on the allow-list and no production hostname is in the host, wwwroot or path settings"

# ---------------------------------------------------------------------------------------------------------------------
# Paths: absolute POSIX, no Windows leftovers, trees apart.
for v in REHEARSAL_WORK MOODLEDATA CODE_45_DIR CODE_5X_DIR DB_PASS_FILE; do
    if is_abs_posix "${!v}"; then
        :
    else
        fail "${v}='${!v}' is not an absolute POSIX path"
    fi
done
for v in CODE_45_ARCHIVE CODE_5X_ARCHIVE RESTORE_DB_DUMP RESTORE_MOODLEDATA_ARCHIVE RESTORE_MOODLEDATA_BY_HAND LIVE_BASELINE_FILE SNAPSHOT_HOOK \
         CAP_ALLOWLIST IMPORT_DECISIONS SOURCE_BASELINE_PHP ADR031_SCRIPTS_DIR; do
    if [ -n "${!v:-}" ] && ! is_abs_posix "${!v}"; then
        fail "${v}='${!v}' is not an absolute POSIX path"
    fi
done
prod_host_hit CODE_45_DIR "$CODE_45_DIR" || fail "CODE_45_DIR names a production host"
prod_host_hit CODE_5X_DIR "$CODE_5X_DIR" || fail "CODE_5X_DIR names a production host"
case "${CODE_45_DIR%/}/" in
    "${CODE_5X_DIR%/}/"*) fail "CODE_45_DIR is inside CODE_5X_DIR: each hop needs a clean directory of its own" ;;
esac
case "${CODE_5X_DIR%/}/" in
    "${CODE_45_DIR%/}/"*) fail "CODE_5X_DIR is inside CODE_45_DIR: each hop needs a clean directory of its own" ;;
esac
if [ "${CODE_45_DIR%/}" = "${CODE_5X_DIR%/}" ]; then
    fail "CODE_45_DIR and CODE_5X_DIR are the same directory (never unpack 5.x over 4.5)"
fi
case "${MOODLEDATA%/}/" in
    "${CODE_45_DIR%/}/"* | "${CODE_5X_DIR%/}/"*) fail "MOODLEDATA is inside a code tree" ;;
esac
[ "$FAILS" = 0 ] && pass "paths are absolute POSIX paths; the two code trees are separate"

# ---------------------------------------------------------------------------------------------------------------------
# Who runs this.
if [ -n "$WEB_USER" ]; then
    if [ "$(id -un)" = "$WEB_USER" ]; then
        pass "running as the web user ${WEB_USER}"
    elif [ "$EXECUTE" = 1 ]; then
        fail "running as $(id -un), not as ${WEB_USER}: run  sudo -u ${WEB_USER} bash tools/rehearsal/run_all.sh --execute"
    else
        warn "running as $(id -un); --execute needs ${WEB_USER}"
    fi
else
    note "WEB_USER is empty: the user is not checked"
fi

# ---------------------------------------------------------------------------------------------------------------------
# Tools.
for t in awk sed grep sort comm find du sha256sum head tr tee stat; do
    command -v "$t" > /dev/null 2>&1 || fail "required tool missing: ${t}"
done
need_tool "$PHP_BIN"
need_tool "$MYSQL_BIN"
if [ -n "${CODE_45_ARCHIVE:-}${CODE_5X_ARCHIVE:-}${RESTORE_MOODLEDATA_ARCHIVE:-}" ]; then
    need_tool unzip
    need_tool tar
fi
if [ -n "${RESTORE_DB_DUMP:-}" ]; then
    need_tool gzip
fi
if command -v "$PHP_BIN" > /dev/null 2>&1; then
    pid="$(php_version_id 2> /dev/null || printf 0)"
    if [ "$pid" -ge 80100 ] && [ "$pid" -lt 80400 ]; then
        pass "PHP ${pid} (8.1 to 8.3: hop 1 runs on 4.5 and refuses 8.4; hop 2 needs 8.3, checked again in step 04)"
    elif [ "$EXECUTE" = 1 ]; then
        fail "PHP version id ${pid} is outside 8.1 to 8.3"
    else
        warn "PHP version id ${pid} is outside 8.1 to 8.3"
    fi
    if php_run -r 'exit(extension_loaded("tokenizer") ? 0 : 1);'; then
        pass "PHP tokenizer is loaded (the kit reads config.php as data with it)"
    else
        fail "PHP tokenizer extension is missing"
    fi
fi

# ---------------------------------------------------------------------------------------------------------------------
# Files the steps need.
kitfiles="lib/common.sh lib/make_config.sh lib/config_probe.php lib/json_get.php lib/missing_plugins.php lib/bizlms_plugins.txt"
for f in $kitfiles; do
    [ -f "$KIT_DIR/$f" ] || fail "kit file missing: ${f}"
done
for f in "$SOURCE_BASELINE_PHP" "$CAP_ALLOWLIST" "$IMPORT_DECISIONS"; do
    if [ -f "$f" ]; then
        pass "found ${f}"
    elif [ "$EXECUTE" = 1 ]; then
        fail "file missing: ${f}"
    else
        warn "file not found here (needed with --execute): ${f}"
    fi
done
for f in adr031_predeploy_probe adr031_role9_core_caps adr031_crosstenant_role adr031_ws_smoke; do
    if [ -f "$ADR031_SCRIPTS_DIR/$f.php" ]; then
        :
    elif [ "$EXECUTE" = 1 ]; then
        fail "ADR-031 script missing: ${ADR031_SCRIPTS_DIR}/${f}.php"
    else
        warn "ADR-031 script not found here: ${ADR031_SCRIPTS_DIR}/${f}.php"
    fi
done
if [ "$EXECUTE" = 1 ]; then
    if check_pass_file; then
        pass "DB_PASS_FILE exists and is not readable by others"
    else
        fail "DB_PASS_FILE ${DB_PASS_FILE} is missing or readable by others (chmod 600)"
    fi
    if [ -f "$DB_PASS_FILE" ] && [ ! -s "$DB_PASS_FILE" ]; then
        warn "DB_PASS_FILE is empty: the database password is empty (only a scratch box should have that)"
    fi
fi

# ---------------------------------------------------------------------------------------------------------------------
# config.php files that are already there: refused unless they point only at the rehearsal.
for pair in "4.5 tree:$CODE_45_DIR/config.php" "5.x tree:$CODE_5X_DIR/config.php" \
            "5.x public loader or config:$CODE_5X_DIR/public/config.php" "baseline tool settings:$SOURCE_DB_CONFIG"; do
    label="${pair%%:*}"
    cfgfile="${pair#*:}"
    [ -f "$cfgfile" ] || continue
    if grep -q 'REHEARSAL-KIT-LOADER' "$cfgfile"; then
        pass "${label}: ${cfgfile} is the kit's loader (it only requires ../config.php)"
        continue
    fi
    if grep -q 'Moodle configuration loader' "$cfgfile" && grep -q "__DIR__ . '/../config.php'" "$cfgfile"; then
        pass "${label}: ${cfgfile} is the stock Moodle 5.x loader"
        continue
    fi
    if [ "$label" = "baseline tool settings" ]; then
        v="$(config_get "$cfgfile" dbname || true)"
        if [ -n "$v" ] && db_allowed "$v" && ! db_name_denied "$v"; then
            pass "${label}: ${cfgfile} names the allow-listed database ${v}"
        else
            fail "${label}: ${cfgfile} names database '${v}', not an allow-listed one"
        fi
        continue
    fi
    if check_config_file "$cfgfile" "$label"; then
        pass "${label}: ${cfgfile} points only at the rehearsal (database, host, wwwroot, dataroot, noemailever = true)"
    else
        fail "${label}: ${cfgfile} is not safe for the rehearsal (see the lines above)"
    fi
done

# ---------------------------------------------------------------------------------------------------------------------
# Cron: no scheduler on this box may run THIS rehearsal's Moodle cron. A line that runs another site's cron.php (UAT's, on a
# shared box) is not ours to refuse: it is noted, and the rehearsal database has cron_enabled = 0 from step 01 on, which is
# the guard that holds whatever a scheduler does. Only a line that names CODE_45_DIR or CODE_5X_DIR is a failure.
cron_hits=0
scan_cron_text() {
    # scan_cron_text SOURCE < text : count active (uncommented) lines that run this rehearsal's Moodle cron.
    local source="$1" line
    while IFS= read -r line; do
        case "$line" in '' | '#'*) continue ;; esac
        case "$line" in
            *cron.php*)
                if names_our_tree "$line"; then
                    fail "an active scheduler line runs this rehearsal's Moodle cron (${source}): ${line}"
                    cron_hits=$((cron_hits + 1))
                else
                    note "an active scheduler line runs a cron.php that names neither CODE_45_DIR nor CODE_5X_DIR (${source}): ${line} -- not this rehearsal's; confirm it belongs to another site"
                fi
                ;;
        esac
    done
}
if command -v crontab > /dev/null 2>&1; then
    scan_cron_text "crontab of $(id -un)" < <(crontab -l 2> /dev/null || true)
fi
for f in /etc/crontab /etc/cron.d/* /var/spool/cron/crontabs/* /var/spool/cron/*; do
    [ -f "$f" ] && [ -r "$f" ] || continue
    scan_cron_text "$f" < "$f" || true
done
if command -v systemctl > /dev/null 2>&1; then
    while IFS= read -r timer; do
        [ -n "$timer" ] || continue
        service="$(systemctl show -p Unit --value "$timer" 2> /dev/null || true)"
        unit_text="$(systemctl cat "$timer" ${service:+"$service"} 2> /dev/null || true)"
        if names_our_tree "$unit_text"; then
            fail "the systemd timer ${timer} runs this rehearsal's Moodle: disable it (systemctl list-timers --all)"
            cron_hits=$((cron_hits + 1))
        else
            note "the systemd timer ${timer} mentions moodle or sentientia but names neither CODE_45_DIR nor CODE_5X_DIR: not this rehearsal's; confirm it belongs to another site"
        fi
    done < <(systemctl list-timers --all --no-legend 2> /dev/null | awk '{ for (i = 1; i <= NF; i++) if ($i ~ /\.timer$/) { print $i; break } }' | grep -Ei 'moodle|sentientia' || true)
fi
if [ "$cron_hits" = 0 ]; then
    pass "no scheduler line of this user or of the system crontabs runs this rehearsal's Moodle cron"
fi
note "other users' crontabs cannot be read from here: confirm by hand that none runs cron.php (sudo crontab -l -u ${WEB_USER:-www-data})"

# ---------------------------------------------------------------------------------------------------------------------
# The database, when it can be asked (EXECUTE only; DRY never opens a connection).
if [ "$EXECUTE" = 1 ] && [ "$FAILS" = 0 ]; then
    probe_db
    case "$DB_STATE" in
        unreachable)
            fail "the database server at ${DB_HOST} cannot be reached with DB_USER / DB_PASS_FILE"
            ;;
        absent)
            pass "database ${DB_NAME} does not exist yet (step 01 restores into it)"
            ;;
        empty)
            pass "database ${DB_NAME} exists and is empty (step 01 restores into it)"
            ;;
        present)
            log "database ${DB_NAME} holds ${DB_TABLES} tables"
            inflight="$(inflight_count || printf '?')"
            if [ "$inflight" = 1 ]; then
                fail "database ${DB_NAME} holds the table ${KIT_INFLIGHT_TABLE}: a restore this kit started into it did not finish ($(inflight_describe)), so it is a PARTIAL copy that step 01 refuses whatever RESTORE_DONE_BY_HAND says. Only DROP DATABASE clears it: drop it and create it empty"
            elif [ "$inflight" != 0 ]; then
                warn "the count of the in-flight table ${KIT_INFLIGHT_TABLE} in database ${DB_NAME} could not be read: step 01 treats that as a partial restore until it can be read"
            fi
            if [ -n "$(marker_get)" ]; then
                pass "database ${DB_NAME} carries a rehearsal-kit marker (restore $(marker_get | cut -c1-8)...)"
            else
                warn "database ${DB_NAME} carries no rehearsal-kit marker: step 01 refuses it unless the kit restored it, or RESTORE_DONE_BY_HAND=${DB_NAME} states that you restored the live backup into it by hand"
            fi
            if [ "$(db_scalar "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}' AND TABLE_NAME = '${DB_PREFIX}config'")" = 1 ]; then
                cron="$(db_config_value cron_enabled || true)"
                if [ "$cron" = "0" ]; then
                    pass "cron_enabled = 0 in the database"
                elif step_done_ok 01; then
                    fail "cron_enabled is '${cron}' in the database after step 01 set it to 0: someone re-enabled it (php admin/cli/cron.php --disable)"
                else
                    warn "cron_enabled is '${cron:-unset}' in the restored database; step 01 sets it to 0 before anything else touches it"
                fi
                nm="$(db_config_value noemailever || true)"
                note "config table holds noemailever='${nm}' (irrelevant: the config.php setting rules)"
            fi
            ;;
    esac
fi

# ---------------------------------------------------------------------------------------------------------------------
# The moodledata: a populated directory must be one this kit stamped (it may be another site's dataroot, UAT's for one).
if [ "$EXECUTE" = 1 ] && [ -d "$MOODLEDATA" ] && [ -n "$(ls -A "$MOODLEDATA" 2> /dev/null)" ]; then
    if [ -n "$(moodledata_marker_get)" ]; then
        pass "${MOODLEDATA} carries a rehearsal-kit marker (restore $(moodledata_marker_get | cut -c1-8)...)"
    else
        warn "${MOODLEDATA} is not empty and carries no rehearsal-kit marker: step 01 refuses it (it may be another site's dataroot) unless RESTORE_MOODLEDATA_BY_HAND=${MOODLEDATA} (its own statement, naming the path) says it holds the live moodledata restored for this rehearsal; even then it must show no write in sessions/ or localcache/ in the last 30 minutes"
    fi
fi

# ---------------------------------------------------------------------------------------------------------------------
if [ "$FAILS" -gt 0 ]; then
    die "${FAILS} preflight check(s) failed: the rehearsal does not start"
fi
log "preflight passed with ${WARNINGS} warning(s): nothing in this configuration can reach production"
