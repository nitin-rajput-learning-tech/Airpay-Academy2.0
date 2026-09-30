<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_sentientia_request.
 *
 * @package   local_sentientia_request
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_sentientia_request_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026051600 — P1 batch: polymorphic requests (course | path | classroom |
    // program). New columns `item_type` + `itemid` extend the previously
    // course-only request workflow without breaking existing rows.
    //
    // Backfill: every existing row had item_type='course' implicitly + itemid
    // equal to courseid. We honour that with an UPDATE.
    //
    // The legacy `courseid` column stays for back-compat with reports, WS
    // returns, and the existing notifier. New code reads (item_type, itemid).
    if ($oldversion < 2026051600) {
        $table = new xmldb_table('local_sentientia_request');

        $field = new xmldb_field('item_type', XMLDB_TYPE_CHAR, '20',
            null, XMLDB_NOTNULL, null, 'course', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, null, '0', 'item_type');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Backfill: legacy rows are all course requests.
        $DB->execute(
            "UPDATE {local_sentientia_request}
                SET itemid = courseid, item_type = 'course'
              WHERE item_type = 'course' AND itemid = 0 AND courseid > 0"
        );

        $idx = new xmldb_index('idx_user_item', XMLDB_INDEX_NOTUNIQUE,
            ['userid', 'item_type', 'itemid']);
        if (!$dbman->index_exists($table, $idx)) {
            $dbman->add_index($table, $idx);
        }

        $idx = new xmldb_index('idx_item_type', XMLDB_INDEX_NOTUNIQUE,
            ['item_type']);
        if (!$dbman->index_exists($table, $idx)) {
            $dbman->add_index($table, $idx);
        }

        upgrade_plugin_savepoint(true, 2026051600, 'local', 'sentientia_request');
    }

    // 2026093001 - ADR-032 (BizLMS import, request feature): mark the rows the import writes.
    //
    // legacy_source is NULL on every native row and 'bizlms' on an imported one. request_manager's two cron
    // jobs (escalate_overdue, auto_expire) select only rows where it IS NULL, so the first cron run after the
    // import does not flip imported pending history to expired; the request lists show imported rows only
    // while the sentientia.request.imported_history flag is on. No index: the column is only ever tested
    // alongside status, which is indexed. Idempotent - a site that installed from the new install.xml
    // already has the column. See db/upgradelib.php.
    if ($oldversion < 2026093001) {
        require_once(__DIR__ . '/upgradelib.php');
        local_sentientia_request_ensure_legacy_source($dbman);

        upgrade_plugin_savepoint(true, 2026093001, 'local', 'sentientia_request');
    }

    return true;
}
