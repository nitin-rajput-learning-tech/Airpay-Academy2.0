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

    return true;
}
