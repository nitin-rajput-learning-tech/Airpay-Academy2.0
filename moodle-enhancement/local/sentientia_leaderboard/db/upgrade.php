<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_sentientia_leaderboard.
 *
 * Phase L.0 (2026052400) shipped the initial schema via db/install.xml.
 * Phase L.1 (2026052500) adds the `local_sentientia_lb_notify_log`
 * throttle table that powers rank-change notification rate limiting.
 *
 * @package local_sentientia_leaderboard
 */
function xmldb_local_sentientia_leaderboard_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026052500) {
        // Phase L.1: notification throttle log.
        $table = new xmldb_table('local_sentientia_lb_notify_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('boardid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL);
        $table->add_field('customerid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '1');
        $table->add_field('last_sent', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');
        $table->add_field('last_old_rank', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');
        $table->add_field('last_new_rank', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');
        $table->add_field('last_reason', XMLDB_TYPE_CHAR, '40', null,
            XMLDB_NOTNULL, null, '');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_board', XMLDB_KEY_FOREIGN, ['boardid'],
            'local_sentientia_lb_boards', ['id']);
        $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'],
            'user', ['id']);
        $table->add_key('uk_board_user_customer', XMLDB_KEY_UNIQUE,
            ['boardid', 'userid', 'customerid']);

        $table->add_index('idx_last_sent', XMLDB_INDEX_NOTUNIQUE,
            ['last_sent']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026052500, 'local',
            'sentientia_leaderboard');
    }

    return true;
}
