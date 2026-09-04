<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_sentientia_live.
 *
 * Phase E.0 ships the initial schema via db/install.xml — this file
 * starts as a no-op savepoint and grows as subsequent phases ship
 * schema changes.
 *
 * @package local_sentientia_live
 */
function xmldb_local_sentientia_live_upgrade(int $oldversion): bool {
    global $DB;

    // ── T-01 (QA Walk 2026-05-29): grant create + run to teacher archetype ──
    // db/access.php now declares 'teacher' => CAP_ALLOW on the create + run
    // capabilities (the BizLMS `trainer` role is archetype=teacher, not
    // editingteacher, so trainers were locked out of trainer/index.php).
    //
    // Moodle's update_capabilities() applies archetype defaults ONLY when a
    // capability is first installed (see lib/accesslib.php — the
    // assign_legacy_capabilities() call lives inside the `$newcaps` loop). It
    // never retro-applies a newly-added archetype to a capability that already
    // exists in {capabilities}. Because create + run already shipped in an
    // earlier version, the access.php change alone does NOT reach the existing
    // teacher-archetype roles — so we back-fill them here, mirroring exactly
    // what a fresh install would have done.
    if ($oldversion < 2026052900) {
        $systemcontext = \context_system::instance();

        // Every role whose archetype is `teacher` (BizLMS `trainer`, plus the
        // stock non-editing teacher). Matches the access.php declaration.
        $teacherroles = $DB->get_records('role', ['archetype' => 'teacher'], '', 'id');
        foreach ($teacherroles as $role) {
            // overwrite=false: fill in the default only where the role has no
            // explicit setting yet. An admin who deliberately set CAP_PREVENT
            // is respected and left untouched.
            assign_capability('local/sentientia_live:create', CAP_ALLOW,
                $role->id, $systemcontext->id, false);
            assign_capability('local/sentientia_live:run', CAP_ALLOW,
                $role->id, $systemcontext->id, false);
        }

        // Force a rebuild of the dirty access caches so the new grants apply
        // on the next request (the upgrade finaliser also purges caches).
        \context_system::instance()->mark_dirty();

        upgrade_plugin_savepoint(true, 2026052900, 'local', 'sentientia_live');
    }

    // ── H4 remediation (UAT-SECURITY-POSTURE-2026-09-03, 2026-09-04) ──
    // New local_sentientia_live_sse table backing the SSE concurrency-cap
    // registry (classes/sse_connection_registry.php). See db/install.xml
    // for the full column/index documentation.
    if ($oldversion < 2026090302) {
        $dbman = $DB->get_manager(); // The earlier step scopes its own $dbman; define ours.
        $table = new xmldb_table('local_sentientia_live_sse');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);
            $table->add_field('sid', XMLDB_TYPE_CHAR, '100', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timeheartbeat', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_sessionid', XMLDB_KEY_FOREIGN, ['sessionid'],
                'local_sentientia_live_sessions', ['id']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'],
                'user', ['id']);

            $table->add_index('idx_sid', XMLDB_INDEX_NOTUNIQUE, ['sid']);
            $table->add_index('idx_heartbeat', XMLDB_INDEX_NOTUNIQUE, ['timeheartbeat']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026090302, 'local', 'sentientia_live');
    }

    return true;
}
