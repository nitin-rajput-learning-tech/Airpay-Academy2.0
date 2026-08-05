<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_authoring.
 *
 * P0.3.0 (2026-06-16) is the install baseline — the five tables are created
 * by db/install.xml, so there are no upgrade steps yet. This file exists to
 * satisfy the local-plugin checklist (CLAUDE.md §6) and to give future schema
 * revisions a home that follows the version-checked savepoint pattern from
 * .claude/rules/database.md.
 *
 * @package local_sentientia_authoring
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run the local_sentientia_authoring upgrade steps.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_sentientia_authoring_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // No upgrade steps yet — P0.3.0 is the install baseline.
    // Template for future revisions (kept as documentation, never runs at
    // the current version):
    //
    // if ($oldversion < 2026XXXXXX) {
    //     $table = new xmldb_table('local_sentientia_auth_question');
    //     $field = new xmldb_field('newcol', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'points');
    //     if (!$dbman->field_exists($table, $field)) {
    //         $dbman->add_field($table, $field);
    //     }
    //     upgrade_plugin_savepoint(true, 2026XXXXXX, 'local', 'sentientia_authoring');
    // }

    // 2026061700 — role back-fill (T-01 pattern). The author caps
    // (generate/review/managetemplates) gained the 'teacher' archetype so the
    // airpay `trainer` role (teacher archetype = the SME/author role) can use
    // the GenAI Authoring Studio. Moodle's update_capabilities() only seeds
    // archetype defaults for NEWLY-added caps, so grant explicitly onto the
    // already-existing teacher-archetype roles. Idempotent —
    // assign_capability(overwrite=false) leaves any role that already has an
    // explicit setting untouched.
    if ($oldversion < 2026061700) {
        $context = \context_system::instance();
        $caps = [
            'local/sentientia_authoring:generate',
            'local/sentientia_authoring:review',
            'local/sentientia_authoring:managetemplates',
        ];
        foreach ($DB->get_records('role', ['archetype' => 'teacher']) as $role) {
            foreach ($caps as $cap) {
                assign_capability($cap, CAP_ALLOW, $role->id, $context->id, false);
            }
        }
        $context->mark_dirty();
        upgrade_plugin_savepoint(true, 2026061700, 'local', 'sentientia_authoring');
    }

    // 2026061701 — ship a dedicated, scoped "Sentientia Author" role. SME content
    // authors get ONLY the GenAI author/SME caps (authoring + skillsai) at system
    // context — NOT the broad teacher/manager archetype caps. The new tools gate
    // at CONTEXT_SYSTEM, so this role is assignable at the System level only.
    // Idempotent: created only when the shortname is free; caps re-synced every
    // run; caps whose owning plugin isn't installed are skipped (no orphan rows).
    if ($oldversion < 2026061701) {
        $shortname = 'sentientiaauthor';
        $existing = $DB->get_field('role', 'id', ['shortname' => $shortname]);
        if ($existing) {
            $roleid = (int) $existing;
        } else {
            $roleid = create_role(
                'Sentientia Author',
                $shortname,
                'Content author / SME. Grants the GenAI Authoring Studio and Skills '
                    . 'Intelligence capabilities at system context, without broader '
                    . 'teacher/manager permissions. Assign at the System level to staff '
                    . 'who create learning content.',
                '' // Custom role — no archetype, so it inherits no broad caps.
            );
        }
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $syscontext = \context_system::instance();
        $authorcaps = [
            'local/sentientia_authoring:generate',
            'local/sentientia_authoring:review',
            'local/sentientia_authoring:managetemplates',
            'local/sentientia_skillsai:extract',
            'local/sentientia_skillsai:review',
        ];
        foreach ($authorcaps as $cap) {
            if ($DB->record_exists('capabilities', ['name' => $cap])) {
                assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
            }
        }
        $syscontext->mark_dirty();
        upgrade_plugin_savepoint(true, 2026061701, 'local', 'sentientia_authoring');
    }

    unset($dbman);

    return true;
}
