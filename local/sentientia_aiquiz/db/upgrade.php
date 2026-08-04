<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_aiquiz.
 *
 * @package local_sentientia_aiquiz
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_aiquiz_upgrade(int $oldversion): bool {
    global $DB;

    // ── T-01 back-fill (2026-08-04, ADR-028 Phase 1.5): grant generate +
    // review to the teacher archetype and the custom Sentientia Author role ──
    // db/access.php now declares 'teacher' => CAP_ALLOW on both caps (the
    // BizLMS `trainer` role is archetype=teacher, not editingteacher, so real
    // trainers were locked out of AI quiz generation — the first feature
    // scheduled to go live under the signed Addendum-A budget).
    //
    // Moodle's update_capabilities() applies archetype defaults ONLY when a
    // capability is first installed; it never retro-applies a newly-added
    // archetype to an existing capability — so we back-fill here, mirroring
    // what a fresh install would now do. The `sentientiaauthor` role has no
    // archetype at all, so it can never receive archetype defaults; it gets
    // an explicit grant (matching the skillsai/authoring grants it already
    // holds).
    if ($oldversion < 2026080400) {
        $systemcontext = \context_system::instance();
        $caps = ['local/sentientia_aiquiz:generate', 'local/sentientia_aiquiz:review'];

        // Every role whose archetype is `teacher` (BizLMS `trainer`, plus the
        // stock non-editing teacher). Matches the access.php declaration.
        $roles = $DB->get_records('role', ['archetype' => 'teacher'], '', 'id');

        // The scoped Sentientia Author system-context role (authoring
        // 2026061701) — guarded: it does not exist on every deployment.
        if ($author = $DB->get_record('role', ['shortname' => 'sentientiaauthor'], 'id')) {
            $roles[] = $author;
        }

        foreach ($roles as $role) {
            foreach ($caps as $cap) {
                // overwrite=false: fill in the default only where the role has
                // no explicit setting yet. An admin who deliberately set
                // CAP_PREVENT is respected and left untouched.
                assign_capability($cap, CAP_ALLOW, $role->id, $systemcontext->id, false);
            }
        }

        \context_system::instance()->mark_dirty();

        upgrade_plugin_savepoint(true, 2026080400, 'local', 'sentientia_aiquiz');
    }

    return true;
}
