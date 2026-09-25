<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_skillsai.
 *
 * P0.1.0 is the initial install — there are no upgrade steps yet. The
 * function exists (and returns true) so the plugin upgrade machinery is
 * wired from day one; subsequent phases add versioned `if ($oldversion <
 * NNNN)` blocks here following the template in .claude/rules/database.md.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_sentientia_skillsai_upgrade(int $oldversion): bool {
    global $DB;

    // 2026061700 — role back-fill (T-01 pattern). The SME caps (extract/review)
    // gained the 'teacher' archetype so the airpay `trainer` role (teacher
    // archetype) can run AI skills extraction + candidate review. Moodle's
    // update_capabilities() only seeds archetype defaults for NEWLY-added caps,
    // so grant explicitly onto the already-existing teacher-archetype roles.
    // Idempotent (assign_capability overwrite=false).
    if ($oldversion < 2026061700) {
        $context = \context_system::instance();
        $caps = [
            'local/sentientia_skillsai:extract',
            'local/sentientia_skillsai:review',
        ];
        foreach ($DB->get_records('role', ['archetype' => 'teacher']) as $role) {
            foreach ($caps as $cap) {
                assign_capability($cap, CAP_ALLOW, $role->id, $context->id, false);
            }
        }
        $context->mark_dirty();
        upgrade_plugin_savepoint(true, 2026061700, 'local', 'sentientia_skillsai');
    }

    // 2026092500 - ADR-031: take :manage_all back from every role.
    // It no longer defaults to the manager archetype (db/access.php), but
    // changing archetypes never revokes what was already granted. Tenant
    // admins hold manager-archetype roles, so every existing grant is a
    // cross-tenant leak of jobs, candidates, taxonomy and gap feeds. Revoke
    // them all; site admins are unaffected (and the code now also requires
    // tenant::is_cross_tenant() before :manage_all unscopes anyone).
    if ($oldversion < 2026092500) {
        $syscontext = \context_system::instance();
        $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
            'capability = :cap', ['cap' => 'local/sentientia_skillsai:manage_all']);
        foreach ($roleids as $roleid) {
            unassign_capability('local/sentientia_skillsai:manage_all', (int) $roleid, $syscontext->id);
        }
        $syscontext->mark_dirty();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_skillsai');
    }

    return true;
}
