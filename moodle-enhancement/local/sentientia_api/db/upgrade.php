<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_api.
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_api_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // No upgrade steps yet — install.xml is the source of truth for 1.0.0.
    // Future additive schema changes go here, each behind a version gate +
    // upgrade_plugin_savepoint().

    return true;
}
