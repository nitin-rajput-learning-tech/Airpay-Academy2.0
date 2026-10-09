#!/usr/bin/env bash
# 03 hop 1 -- Moodle 4.1.2 -> 4.5.x on a clean 4.5 core, BizLMS plugin code OFF disk. Timed. Parity after the hop.
#
# Runbook: MIGRATION-REHEARSAL-RUNBOOK.md step 3 ("Upgrade ... Time both hops ... Checkpoint after hop 1: php
# source_baseline.php --config=<the 4.5 config.php> --compare=...: exit 0 and the BizLMS tables must match (95 of 95 on the
# April copy). Any DRIFT line here is a core-upgrade effect, isolated from the rest."); migration plan 0 (two hops: 5.2
# requires 4.4 or later; the decision of 2026-09-30: BizLMS code OFF disk; never uninstall a missing-from-disk plugin;
# I-4 = restore + both hops + repairs; a clean directory for each hop).
#
# Ported from hop1_45.sh of the first local rehearsal (20.6 min on the April copy, MariaDB 10.11; the target engine MySQL 8.4
# is rehearsed here for the first time): unpack the 4.5 core, config with the DB guard, list the plugins missing from disk,
# upgrade.php --non-interactive, release check, purge, missing list again.
#
# Steps: the 4.5 tree is in place (archive checked by SHA-256, zip signature verified); none of the production BizLMS
# plugin directories is on disk (lib/bizlms_plugins.txt); config.php with the DB guard; PHP 8.1-8.3 and the extensions;
# the database is the source release (or already 4.5: then nothing runs); restore point; upgrade; verify; parity.

# shellcheck source=lib/common.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
# shellcheck source=lib/make_config.sh
. "$KIT_DIR/lib/make_config.sh"
step_init 03 hop1_to_45 "$@"

CODE_45_ARCHIVE="${CODE_45_ARCHIVE:-}"
CODE_45_SHA256="${CODE_45_SHA256:-}"
OUT_UPGRADE="$LOG_DIR/03-hop1-upgrade-output.log"

need_tool "$PHP_BIN"
require_kit_marker

# 1. The code.
unpack_tree "$CODE_45_ARCHIVE" "$CODE_45_SHA256" "$CODE_45_DIR" version.php "4.5 core"
if [ -f "$CODE_45_DIR/version.php" ]; then
    rel45="$(release_of_file "$CODE_45_DIR/version.php")"
    log "4.5 tree release: '${rel45}'"
    [[ "$rel45" =~ $HOP1_RELEASE_REGEX ]] || die "the tree at ${CODE_45_DIR} is '${rel45}', not '${HOP1_RELEASE_REGEX}'"
    kv_set release.hop1_code "$rel45"
    # The tree that really runs (a directory that already held code is trusted by its version.php, not by an archive hash).
    if [ "$EXECUTE" = 1 ]; then
        manifest45="$(tree_manifest_sha "$CODE_45_DIR")"
        log "4.5 tree manifest SHA-256 (path and hash of every version.php): ${manifest45}"
        kv_set tree.45.manifest_sha "$manifest45"
    fi
    [ -f "$CODE_45_DIR/admin/cli/upgrade.php" ] || die "${CODE_45_DIR}/admin/cli/upgrade.php is missing: not a Moodle core tree"
    if [ -d "$CODE_45_DIR/public" ]; then
        die "${CODE_45_DIR}/public exists: this is a 5.x tree. Hop 1 needs the 4.5 core"
    fi
else
    [ "$EXECUTE" != 1 ] || die "no Moodle code in ${CODE_45_DIR}"
    dry "would check the tree release against '${HOP1_RELEASE_REGEX}'"
fi

# 2. BizLMS code off disk (decision of 2026-09-30).
if [ -d "$CODE_45_DIR" ]; then
    bizlms_off_disk 45 || die "BizLMS plugin code is on disk in the 4.5 tree: hop 1 runs with it OFF disk (their upgrade steps must not run)"
else
    dry "would check that none of the BizLMS plugin directories of lib/bizlms_plugins.txt exists in ${CODE_45_DIR}"
fi

# 3. Config with the DB guard.
make_config 45

# 4. The box.
if [ "$EXECUTE" = 1 ]; then
    pid="$(php_version_id)"
    if [ "$pid" -lt 80100 ] || [ "$pid" -ge 80400 ]; then
        die "PHP version id ${pid}: Moodle 4.5 supports 8.1 to 8.3 (8.4 is refused)"
    fi
    for ext in ctype curl dom gd iconv intl json mbstring mysqli openssl pcre simplexml sodium spl xml xmlreader zip zlib; do
        php_has_ext "$ext" || die "PHP extension missing: ${ext}"
    done
    log "OK: PHP ${pid} with the required extensions; memory_limit $(php_run -r 'echo ini_get("memory_limit");')"
    mkdir -p "$MOODLEDATA"
    [ -w "$MOODLEDATA" ] || die "${MOODLEDATA} is not writable by $(id -un)"
    log "database server: $(db_scalar "SELECT VERSION()"); max_allowed_packet $(db_scalar "SELECT @@max_allowed_packet")"
else
    dry "would check: PHP 8.1 to 8.3 with the Moodle extensions; ${MOODLEDATA} writable; the database server version"
fi

# 5. Where the database is.
if [ "$EXECUTE" = 1 ]; then
    release_now="$(db_config_value release || true)"
    log "the database reports release '${release_now}'"
    if [[ "$release_now" =~ $HOP1_RELEASE_REGEX ]]; then
        rc=0
        m45 admin/cli/upgrade.php --is-pending > /dev/null 2>&1 || rc=$?
        if [ "$rc" = 0 ]; then
            log "the database is already at 4.5 and nothing is pending: hop 1 is done; only the checks below run"
            ALREADY=1
        else
            note "the database reports 4.5 but an upgrade is pending (exit ${rc}): running it"
            ALREADY=0
        fi
    elif [[ "$release_now" =~ $SOURCE_RELEASE_REGEX ]]; then
        ALREADY=0
    elif [[ "$release_now" =~ $HOP2_RELEASE_REGEX ]]; then
        ALREADY=2
        log "the database reports '${release_now}': it is past hop 1 (a re-run after hop 2): nothing to do here"
    else
        die "the database reports '${release_now}': neither the source ('${SOURCE_RELEASE_REGEX}') nor 4.5 ('${HOP1_RELEASE_REGEX}')"
    fi
    [ -s "$BASELINE_FILE" ] || die "no baseline at ${BASELINE_FILE}: run step 02 before the first hop (it cannot be taken afterwards)"
    log "baseline in place: SHA-256 $(sha256_of "$BASELINE_FILE")"
else
    ALREADY=0
    dry "would read the database release: the source runs the hop, 4.5 with nothing pending skips it"
    dry "would require the baseline of step 02 (it cannot be taken after a hop)"
fi

# 6. The hop.
if [ "$ALREADY" = 0 ]; then
    snapshot_hook "before-hop-1"
    # The output is kept (not sent to /dev/null): a DRY run must print the purge, and a failed one says why in the log.
    run m45 admin/cli/purge_caches.php || warn "purge_caches before the hop failed (a 4.1.2 database with 4.5 code often cannot purge: ignored)"
    if [ "$EXECUTE" = 1 ]; then
        capture_to "$REPORT_DIR/hop1-missing-before.txt" m45 "$KIT_DIR/lib/missing_plugins.php" "$CODE_45_DIR/config.php" \
            && log "plugins missing from disk before the hop: $(tail -n 1 "$REPORT_DIR/hop1-missing-before.txt")" \
            || warn "could not list the plugins missing from disk before the hop (reports/hop1-missing-before.txt)"
    fi
    upgrade_args=(--non-interactive)
    if [ "$UPGRADE_ALLOW_UNSTABLE" = 1 ]; then
        upgrade_args+=(--allow-unstable)
    fi
    t0="$(epoch)"
    rc=0
    timed_to "$OUT_UPGRADE" "HOP 1 upgrade 4.1.x to 4.5" m45 admin/cli/upgrade.php "${upgrade_args[@]}" || rc=$?
    if [ "$EXECUTE" = 1 ]; then
        secs=$(( $(epoch) - t0 ))
        kv_set hop1.seconds "$secs"
        [ "$rc" = 0 ] || die "upgrade.php exited ${rc}: restore the pre-hop snapshot and diagnose (${OUT_UPGRADE})"
        grep -q 'completed successfully' "$OUT_UPGRADE" || die "upgrade.php exited 0 but did not print 'completed successfully' (${OUT_UPGRADE})"
        grep -E 'Command line upgrade from' "$OUT_UPGRADE" | tail -n 1 | sed 's/^/    /'
        log "HOP 1 took ${secs}s ($((secs / 60)) min); local rehearsal on MariaDB 10.11 took 1,235 s"
        if grep -Eq 'downgrade_exception|Fatal error|Exception -' "$OUT_UPGRADE"; then
            warn "the upgrade output mentions an exception or fatal error: read ${OUT_UPGRADE}"
        fi
    fi
fi

# 7. After the hop.
if [ "$ALREADY" = 2 ]; then
    log "hop 1 was done and checked before hop 2 (see reports/parity-after-hop1.txt)"
elif [ "$EXECUTE" = 1 ]; then
    rel_after="$(db_config_value release || true)"
    [[ "$rel_after" =~ $HOP1_RELEASE_REGEX ]] || die "after the hop the database reports '${rel_after}', not '${HOP1_RELEASE_REGEX}'"
    log "OK: database release is now '${rel_after}'"
    kv_set release.hop1 "$rel_after"
    rc=0
    m45 admin/cli/upgrade.php --is-pending > /dev/null 2>&1 || rc=$?
    [ "$rc" = 0 ] || die "an upgrade is still pending after hop 1 (exit ${rc})"
    run m45 admin/cli/purge_caches.php || die "purge_caches failed after hop 1"
    capture_to "$REPORT_DIR/hop1-missing-after.txt" m45 "$KIT_DIR/lib/missing_plugins.php" "$CODE_45_DIR/config.php" \
        || die "cannot list the plugins missing from disk after hop 1"
    total="$(tail -n 1 "$REPORT_DIR/hop1-missing-after.txt")"
    log "plugins missing from disk after hop 1: ${total} (BizLMS code is off disk by design; the local rehearsal showed 50)"
    kv_set hop1.missing_plugins "${total#total }"
    # A missing plugin that is not a known BizLMS one is unexpected: say so.
    awk '$1 !~ /^#/ && NF { print $1 }' "$KIT_DIR/lib/bizlms_plugins.txt" | LC_ALL=C sort -u > "$REPORT_DIR/.known-bizlms.txt"
    grep -v '^total ' "$REPORT_DIR/hop1-missing-after.txt" | LC_ALL=C sort -u > "$REPORT_DIR/.missing-now.txt"
    unexpected="$(comm_only_first "$REPORT_DIR/.missing-now.txt" "$REPORT_DIR/.known-bizlms.txt" | tr '\n' ' ')"
    if [ -n "$unexpected" ]; then
        warn "plugins missing from disk that are not on the known BizLMS list: ${unexpected}"
    fi
    rm -f "$REPORT_DIR/.known-bizlms.txt" "$REPORT_DIR/.missing-now.txt"
    log "reminder: never uninstall a missing-from-disk plugin (plugins page, uninstall_plugins.php --purge-missing): it drops its tables, the archive the import reads"

    # Parity after hop 1: no Sentientia plugin needed (source_baseline.php --compare on the 4.5 copy).
    rc=0
    timed_to "$REPORT_DIR/parity-after-hop1.txt" "parity after hop 1" baseline_tool --compare="$BASELINE_FILE" || rc=$?
    show_tail "$REPORT_DIR/parity-after-hop1.txt" 14
    kv_set parity.after_hop1 "$rc"
    judge "parity after hop 1" "$rc"
else
    dry "would verify: release matches '${HOP1_RELEASE_REGEX}', no upgrade pending, purge caches, list the plugins missing from disk (expect the BizLMS set)"
    dry "would run the parity checkpoint: source_baseline.php --compare=${BASELINE_FILE} (exit 0 required)"
fi
log "hop 1 done"
