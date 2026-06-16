<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_xapi.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_xapi_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Version 2026061600 — initial schema (created via install.xml).
    // Future upgrade steps go here as new versions are released.
    // Pattern:
    //   if ($oldversion < 2026MMDDNN) {
    //       // ... schema change ...
    //       upgrade_plugin_savepoint(true, 2026MMDDNN, 'local', 'sentientia_xapi');
    //   }

    return true;
}
