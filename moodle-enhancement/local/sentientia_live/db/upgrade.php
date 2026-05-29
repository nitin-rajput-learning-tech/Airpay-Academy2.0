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

    return true;
}
