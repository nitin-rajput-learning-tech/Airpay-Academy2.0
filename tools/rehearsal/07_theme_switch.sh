#!/usr/bin/env bash
# 07 theme switch -- production's site theme is epsilon, which is not in the package; switch it to sentientia.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 4g ("Site theme (added 2026-10-01): production's $CFG->theme is epsilon, which
# is not in the package, so pages fall back to stock boost (seen in the 2026-10-01 rehearsal upgrade log). php
# admin/cli/cfg.php --name=theme --set=sentientia; April has no user/course/category/cohort overrides"); migration plan 8 step 7
# (purge, confirm, no user/course/category theme override still names epsilon; verify forcelogin=0, enablemyhome=1 and
# defaulthomepage after the upgrade).
#
# A theme override that names epsilon would still render boost for that user/course/category/cohort: the step stops and
# lists them, unless CLEAR_THEME_OVERRIDES=1 (it then sets those rows' theme to ''). Idempotent.
#
# CLEAR_THEME_OVERRIDES writes the `theme` column of {user}, {course}, {course_categories} and {cohort} before the import's gate
# (step 09) compares those tables with the baseline. That is safe because no column the parity baseline hashes is `theme`:
# source_baseline.php's checksum lists and core::WRITES never name it (selftest.sh asserts it, so a later metric cannot start
# hashing it unnoticed), and the statement leaves timemodified alone.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
step_init 07 theme_switch "$@"

CLEAR_THEME_OVERRIDES="${CLEAR_THEME_OVERRIDES:-0}"
need_tool "$PHP_BIN"
require_kit_marker

if [ "$EXECUTE" = 1 ]; then
    release_now="$(db_config_value release || true)"
    [[ "$release_now" =~ $HOP2_RELEASE_REGEX ]] || die "the database reports '${release_now}': the theme switch runs on the 5.x target"
    [ -n "$(db_first "SELECT value FROM {p}config_plugins WHERE plugin = 'theme_sentientia' AND name = 'version'")" ] \
        || die "theme_sentientia is not installed in this database (hop 2 installs it from the package)"
    log "theme now: '$(cfg5 --name=theme 2> /dev/null || printf 'unset')'"

    # Overrides that would still render another theme.
    total=0
    for t in user course course_categories cohort; do
        n="$(db_scalar "SELECT COUNT(*) FROM {p}${t} WHERE theme IS NOT NULL AND theme <> ''")"
        e="$(db_scalar "SELECT COUNT(*) FROM {p}${t} WHERE theme = 'epsilon'")"
        log "theme overrides in {${t}}: ${n} with a theme set, ${e} naming epsilon"
        total=$((total + e))
        if [ "$n" != "$e" ]; then
            warn "{${t}} has $((n - e)) theme override(s) naming a theme other than epsilon: look at them"
        fi
    done
    for s in allowuserthemes allowcoursethemes allowcategorythemes allowcohortthemes; do
        log "${s} = '$(cfg5 --name="$s" 2> /dev/null || printf 'unset')'"
    done
    if [ "$total" -gt 0 ]; then
        if [ "$CLEAR_THEME_OVERRIDES" = 1 ]; then
            for t in user course course_categories cohort; do
                db_write "UPDATE {p}${t} SET theme = '' WHERE theme = 'epsilon'"
            done
        else
            die "${total} user/course/category/cohort row(s) override the theme with epsilon: set CLEAR_THEME_OVERRIDES=1 to clear them (April had none)"
        fi
    fi
else
    dry "would check that theme_sentientia is installed and that no user/course/category/cohort row overrides the theme with epsilon (CLEAR_THEME_OVERRIDES=${CLEAR_THEME_OVERRIDES})"
fi

run cfg5 --name=theme --set=sentientia || die "cfg.php could not set the theme (exit 4 = hard-set in config.php)"
run m5 ../admin/cli/purge_caches.php || die "purge_caches failed"

if [ "$EXECUTE" = 1 ]; then
    now="$(cfg5 --name=theme --no-eol 2> /dev/null || true)"
    [ "$now" = "sentientia" ] || die "after the switch the theme is '${now}', not sentientia"
    log "OK: the site theme is sentientia"
    kv_set theme.site "$now"
    # The landing posture a restore carries (an upgrade can touch admin defaults; fresh 5.2 installs default the opposite).
    fl="$(cfg5 --name=forcelogin --no-eol 2> /dev/null || printf 'unset')"
    em="$(cfg5 --name=enablemyhome --no-eol 2> /dev/null || printf 'unset')"
    dh="$(cfg5 --name=defaulthomepage --no-eol 2> /dev/null || printf 'unset')"
    log "landing posture: forcelogin=${fl} enablemyhome=${em} defaulthomepage=${dh} (the plan expects forcelogin=0, enablemyhome=1; the walk must land a real user on /my)"
    [ "$fl" = 0 ] || warn "forcelogin is '${fl}', the plan expects 0"
    [ "$em" = 1 ] || warn "enablemyhome is '${em}', the plan expects 1"
    kv_set posture.landing "forcelogin=${fl} enablemyhome=${em} defaulthomepage=${dh}"
else
    dry "would confirm cfg.php --name=theme prints sentientia, and print forcelogin, enablemyhome and defaulthomepage"
fi
log "theme switch done"
step_end
