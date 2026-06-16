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

    unset($dbman); // Silence "unused" until the first real upgrade step lands.

    return true;
}
