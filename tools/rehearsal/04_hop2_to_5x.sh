#!/usr/bin/env bash
# 04 hop 2 -- Moodle 4.5.x -> the 5.x Sentientia package (the install path of the production cutover). Timed. Parity after.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md steps 2 and 3 ("Deploy the Sentientia tree ... Upgrade ... Time both hops");
# migration plan 0 (hop 2: the package in a clean directory, never over 4.5; BizLMS plugin tables stay), 4d (deploy: the
# CLI files must be in the package; no leaked config.php; the pre-checks: PHP 8.3, extensions, max_allowed_packet >= 64M,
# I-13), 4e (the in-place upgrade; paygw_airpay and enrol_sentientiasub stay disabled), 5.1 (a fourth diff point).
#
# Ported from hop2_51.sh of the first local rehearsal (38 min, 47 Sentientia plugins installed on the April copy). What that
# run learned, enforced here:
#   * local/airpay_ratings (the retired duplicate of sentientia_ratings) on disk stops the upgrade in 14 s with "Cannot
#     redeclare airpay_display_rating()". The package build leaves it out; this step refuses a tree that has it;
#   * the package build leaves public/config.php out of the zip, so a 5.x tree arrives without Moodle's loader: lib/make_config.sh
#     writes it when it is missing;
#   * a zip that is really a tar (GNU tar writes one when asked for a zip) is refused; a leaked dev config.php is refused.
#
# Steps: unpack (SHA-256 gate) -> tree checks -> BizLMS code off disk -> config with the DB guard -> box and engine checks ->
# restore point -> upgrade.php --non-interactive -> verify (release, Sentientia plugins installed, commerce stays dark)
# -> purge -> parity (migration_parity_check.php --compare). PARITY_HOP2_ENFORCE=0 makes the parity a warning that step 09
# judges again.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
# shellcheck source=lib/make_config.sh
. "$KIT_DIR/lib/make_config.sh"
step_init 04 hop2_to_5x "$@"

CODE_5X_ARCHIVE="${CODE_5X_ARCHIVE:-}"
CODE_5X_SHA256="${CODE_5X_SHA256:-}"
PARITY_HOP2_ENFORCE="${PARITY_HOP2_ENFORCE:-1}"
OUT_UPGRADE="$LOG_DIR/04-hop2-upgrade-output.log"

need_tool "$PHP_BIN"

# 1. The package.
unpack_tree "$CODE_5X_ARCHIVE" "$CODE_5X_SHA256" "$CODE_5X_DIR" public/version.php "Sentientia 5.x package"
if [ -n "$CODE_5X_SHA256" ]; then
    kv_set package.5x.sha256 "$CODE_5X_SHA256"
fi

if [ -f "$CODE_5X_DIR/public/version.php" ]; then
    rel5="$(release_of_file "$CODE_5X_DIR/public/version.php")"
    log "5.x package release: '${rel5}'"
    [[ "$rel5" =~ $HOP2_RELEASE_REGEX ]] || die "the tree at ${CODE_5X_DIR} is '${rel5}', not '${HOP2_RELEASE_REGEX}'"
    kv_set release.hop2_code "$rel5"
    [ -f "$CODE_5X_DIR/admin/cli/upgrade.php" ] || die "${CODE_5X_DIR}/admin/cli/upgrade.php is missing: not a 5.x tree"

    # The CLI files the later steps run (migration plan 4d VERIFY: all present, or the parity gate and repairs cannot run).
    missing_files=""
    for f in local/sentientia_platform/cli/migration_parity_check.php local/sentientia_platform/cli/source_baseline.php \
             local/sentientia_platform/cli/import_bizlms.php local/sentientia_platform/cli/repair_task_registrations.php \
             local/sentientia_platform/cli/repair_bizlms_capabilities.php local/sentientia_catalog/cli/enable_oneclick_enrol.php \
             theme/sentientia/version.php; do
        [ -f "$CODE_5X_DIR/public/$f" ] || missing_files+=" ${f}"
    done
    [ -z "$missing_files" ] || die "the package lacks files the rehearsal needs:${missing_files}"
    log "OK: the package carries the parity, repair, import and catalog CLI files and theme_sentientia"

    # A config.php that is not ours and not the stock loader is a leak (the 2026-08-03 zip carried the dev one).
    for f in "$CODE_5X_DIR/config.php" "$CODE_5X_DIR/public/config.php"; do
        [ -f "$f" ] || continue
        if grep -q 'REHEARSAL-KIT-' "$f" || { grep -q 'Moodle configuration loader' "$f" && grep -q "__DIR__ . '/../config.php'" "$f"; }; then
            continue
        fi
        die "${f} is neither the kit's config nor Moodle's stock loader: a config.php leaked in the package (the 2026-08-03 zip carried the dev one). Remove it by hand and re-run"
    done

    bizlms_off_disk 5x || die "BizLMS plugin code (or local/airpay_ratings) is in the 5.x tree: refused (the upgrade would run their steps, or stop on a duplicate function)"
else
    [ "$EXECUTE" != 1 ] || die "no Moodle 5.x code in ${CODE_5X_DIR}"
    dry "would check: release '${HOP2_RELEASE_REGEX}', the CLI files of the package, no leaked config.php, BizLMS code and local/airpay_ratings off disk"
fi

# 2. Config with the DB guard.
make_config 5x

# 3. The box and the engine.
if [ "$EXECUTE" = 1 ]; then
    pid="$(php_version_id)"
    if [ "$pid" -lt 80300 ] || [ "$pid" -ge 80500 ]; then
        die "PHP version id ${pid}: the 5.x package needs PHP 8.3"
    fi
    for ext in ctype curl dom gd iconv intl json mbstring mysqli openssl pcre simplexml sodium spl xml xmlreader zip zlib; do
        php_has_ext "$ext" || die "PHP extension missing: ${ext}"
    done
    for ext in soap exif fileinfo; do
        php_has_ext "$ext" || warn "PHP extension not loaded (recommended): ${ext}"
    done
    log "OK: PHP ${pid} with the required extensions; memory_limit $(php_run -r 'echo ini_get("memory_limit");'), max_input_vars $(php_run -r 'echo ini_get("max_input_vars");') (the web SAPI needs >= 5000: check its php.ini)"
    packet="$(db_scalar "SELECT @@max_allowed_packet")"
    [ "$packet" -ge 67108864 ] || die "max_allowed_packet is ${packet}: it must be >= 64M (1M drops the connection mid-cron: 2026-06-11 gauntlet; migration plan I-13)"
    log "OK: database $(db_scalar "SELECT VERSION()"), max_allowed_packet ${packet}"
    kv_set engine.version "$(db_scalar "SELECT VERSION()")"
    [ -w "$MOODLEDATA" ] || die "${MOODLEDATA} is not writable by $(id -un)"
else
    dry "would check: PHP 8.3 with the Moodle extensions; max_allowed_packet >= 64M; ${MOODLEDATA} writable"
fi

# 4. Where the database is.
ALREADY=0
if [ "$EXECUTE" = 1 ]; then
    release_now="$(db_config_value release || true)"
    log "the database reports release '${release_now}'"
    if [[ "$release_now" =~ $HOP2_RELEASE_REGEX ]]; then
        rc=0
        m5 ../admin/cli/upgrade.php --is-pending > /dev/null 2>&1 || rc=$?
        if [ "$rc" = 0 ]; then
            log "the database is already on 5.x and nothing is pending: hop 2 is done; only the checks below run"
            ALREADY=1
        else
            note "the database reports 5.x but an upgrade is pending (exit ${rc}): running it"
        fi
    elif [[ "$release_now" =~ $HOP1_RELEASE_REGEX ]]; then
        :
    else
        die "the database reports '${release_now}': hop 2 starts from 4.5 ('${HOP1_RELEASE_REGEX}'), run step 03 first"
    fi
    [ -s "$BASELINE_FILE" ] || die "no baseline at ${BASELINE_FILE}"
else
    dry "would read the database release: 4.5 runs the hop, 5.x with nothing pending skips it"
fi

# 5. The hop.
if [ "$ALREADY" = 0 ]; then
    snapshot_hook "before-hop-2"
    upgrade_args=(--non-interactive)
    if [ "$UPGRADE_ALLOW_UNSTABLE" = 1 ]; then
        upgrade_args+=(--allow-unstable)
    fi
    t0="$(epoch)"
    rc=0
    timed_to "$OUT_UPGRADE" "HOP 2 upgrade 4.5 to 5.x" m5 ../admin/cli/upgrade.php "${upgrade_args[@]}" || rc=$?
    if [ "$EXECUTE" = 1 ]; then
        secs=$(( $(epoch) - t0 ))
        kv_set hop2.seconds "$secs"
        [ "$rc" = 0 ] || die "upgrade.php exited ${rc}: restore the pre-hop snapshot and diagnose (${OUT_UPGRADE})"
        grep -q 'completed successfully' "$OUT_UPGRADE" || die "upgrade.php exited 0 but did not print 'completed successfully' (${OUT_UPGRADE})"
        grep -E 'Command line upgrade from' "$OUT_UPGRADE" | tail -n 1 | sed 's/^/    /'
        log "HOP 2 took ${secs}s ($((secs / 60)) min); the local rehearsal (4.5.10 to 5.1.3 on MariaDB 10.11) took 2,303 s"
        if grep -Eq 'downgrade_exception|Fatal error|Exception -' "$OUT_UPGRADE"; then
            warn "the upgrade output mentions an exception or fatal error: read ${OUT_UPGRADE}"
        fi
        xmldb="$(grep -c 'XMLDB has detected one CHAR NOT NULL' "$OUT_UPGRADE" || true)"
        if [ "${xmldb:-0}" -gt 0 ]; then
            note "${xmldb} XMLDB notice(s) about CHAR NOT NULL columns declared with DEFAULT '' (auto-corrected; source clean-up queued, as in the first rehearsal)"
        fi
    fi
fi

# 6. After the hop.
if [ "$EXECUTE" = 1 ]; then
    rel_after="$(db_config_value release || true)"
    [[ "$rel_after" =~ $HOP2_RELEASE_REGEX ]] || die "after the hop the database reports '${rel_after}', not '${HOP2_RELEASE_REGEX}'"
    log "OK: database release is now '${rel_after}'"
    kv_set release.hop2 "$rel_after"
    rc=0
    m5 ../admin/cli/upgrade.php --is-pending > /dev/null 2>&1 || rc=$?
    [ "$rc" = 0 ] || die "an upgrade is still pending after hop 2 (exit ${rc})"

    installed="$(db_scalar "SELECT COUNT(*) FROM {p}config_plugins WHERE plugin LIKE 'local\\_sentientia\\_%' AND name = 'version'")"
    log "Sentientia local plugins installed: ${installed} (the first rehearsal installed 47)"
    [ "${installed:-0}" -gt 0 ] || die "no local_sentientia plugin is installed after the upgrade"
    kv_set hop2.sentientia_plugins "$installed"

    # Commerce stays dark (migration plan 4e step 3, 6, 8-5).
    enabled="$(cfg5 --name=enrol_plugins_enabled 2> /dev/null || true)"
    case ",${enabled}," in
        *,sentientiasub,*) die "enrol_sentientiasub is ENABLED (${enabled}): the commerce path stays dark at cutover (plan 8-5)" ;;
    esac
    log "OK: enrol_sentientiasub is not enabled (enabled enrol methods: ${enabled:-none})"
    note "verify by hand: paygw_airpay shows DISABLED under Site administration > Plugins > Payment gateways, and Notifications shows no plugin requiring attention (plan 4e step 3)"

    run m5 ../admin/cli/purge_caches.php || die "purge_caches failed after hop 2"
    capture_to "$REPORT_DIR/hop2-missing-after.txt" m5 "$KIT_DIR/lib/missing_plugins.php" "$CODE_5X_DIR/config.php" \
        || warn "could not list the plugins missing from disk after hop 2"
    log "plugins missing from disk after hop 2: $(tail -n 1 "$REPORT_DIR/hop2-missing-after.txt" 2> /dev/null || printf '?') (their tables stay for the importers; never uninstall them)"

    if [ -n "$(kv_get import.applied)" ]; then
        # A re-run after the import: the import changed core tables on purpose, so this compare would fail by design.
        log "the import has run since hop 2 (state/kv/import.applied): the parity after hop 2 was judged then; step 10 judges the data now"
    else
        rc=0
        timed_to "$REPORT_DIR/parity-after-hop2.txt" "parity after hop 2" m5 local/sentientia_platform/cli/migration_parity_check.php --compare="$BASELINE_FILE" || rc=$?
        show_tail "$REPORT_DIR/parity-after-hop2.txt" 16
        kv_set parity.after_hop2 "$rc"
        judge "parity after hop 2" "$rc" "$PARITY_HOP2_ENFORCE"
    fi
else
    dry "would verify: release matches '${HOP2_RELEASE_REGEX}', nothing pending, Sentientia plugins installed, enrol_sentientiasub not enabled, purge caches"
    dry "would run the parity compare: migration_parity_check.php --compare=${BASELINE_FILE} (exit 0 required unless PARITY_HOP2_ENFORCE=0)"
fi
log "hop 2 done"
