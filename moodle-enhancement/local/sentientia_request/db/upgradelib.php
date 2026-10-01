<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_request.
 *
 * @package   local_sentientia_request
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add local_sentientia_request.legacy_source, the column that marks a request imported from BizLMS
 * (ADR-032, request feature), exactly as db/install.xml declares it since 2026093001.
 *
 * NULL on every native row, 'bizlms' on an imported one. The cron jobs select only NULL rows and the request
 * lists show imported rows only with the sentientia.request.imported_history flag. No index: the column is
 * only ever tested alongside status, which is indexed.
 *
 * Safe to run on any site: a site installed from the new install.xml already has the column, and so does one
 * that ran the step before. No row is touched.
 *
 * Used by upgrade step 2026093001; a function so tests/imported_history_test.php can prove it without
 * replaying the upgrade (upgrade_plugin_savepoint() refuses a version the site already has).
 *
 * @param database_manager $dbman
 * @return bool True when the column was added, false when it was there already.
 */
function local_sentientia_request_ensure_legacy_source(database_manager $dbman): bool {
    $table = new xmldb_table('local_sentientia_request');
    $field = new xmldb_field('legacy_source', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'timemodified');
    if ($dbman->field_exists($table, $field)) {
        return false;
    }
    $dbman->add_field($table, $field);
    return true;
}
