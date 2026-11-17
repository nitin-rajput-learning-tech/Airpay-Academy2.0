<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_skillsai.
 *
 * P0.1.0 is the initial install. Later phases add versioned `if ($oldversion <
 * NNNN)` blocks here following the template in .claude/rules/database.md.
 * These steps run only when an existing install is upgraded, never on a
 * fresh install (that is db/install.php).
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

    // 2026111600 - fresh-install gap. db/install.php ran author_role::ensure()
    // before Moodle registered this plugin's capabilities (it does that after
    // install.php returns), so a site that installed this plugin at 2026090700
    // or later in the same run as local_sentientia_authoring, or after it (CI,
    // PHPUnit sites, a new customer, the Stage B cutover), has a Sentientia
    // Author role without :extract / :review. The gap always leaves BOTH
    // without a system-context row at once, so grant ONLY those two caps,
    // ONLY to that role, and ONLY when the role has no system-context setting
    // for EITHER of them. One setting (any permission) is an administrator's
    // configuration, never the gap: then nothing is granted. No-op where the
    // role does not exist.
    if ($oldversion < 2026111600) {
        require_once(__DIR__ . '/upgradelib.php');
        local_sentientia_skillsai_backfill_author_caps();
        upgrade_plugin_savepoint(true, 2026111600, 'local', 'sentientia_skillsai');
    }

    return true;
}
