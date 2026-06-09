<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_sentientia_learningpath.
 *
 * @package   local_sentientia_learningpath
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_sentientia_learningpath_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026051600 — P1 batch: time-bounded compliance windows + rich-text
    // description.
    //
    //   startdate         INT  NULLABLE — UNIX ts; path becomes enrollable
    //                                     from this date.
    //   enddate           INT  NULLABLE — UNIX ts; path closes for new
    //                                     enrolments on this date.
    //   descriptionformat INT  NOT NULL DEFAULT 1 — FORMAT_HTML so the
    //                                                editor renders
    //                                                description correctly.
    if ($oldversion < 2026051600) {
        $table = new xmldb_table('local_sentientia_learningpath');

        $field = new xmldb_field('descriptionformat', XMLDB_TYPE_INTEGER, '2',
            null, XMLDB_NOTNULL, null, '1', 'description');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('startdate', XMLDB_TYPE_INTEGER, '10',
            null, null, null, null, 'visible');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('enddate', XMLDB_TYPE_INTEGER, '10',
            null, null, null, null, 'startdate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026051600, 'local', 'sentientia_learningpath');
    }

    return true;
}
