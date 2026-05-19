<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_exams_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026051901 — P1 #23: exam categories.
    //
    // Adds `categoryid` to `local_airpay_exams` so admins can tag exams
    // by topic (compliance, sales, leadership) without rebuilding the
    // wrapping course. FK references the core `course_categories` table
    // (same taxonomy as courses — BizLMS reused it for exams too).
    //
    // 0 = uncategorised; preserves the existing-data invariant of no
    // category required for previously-imported exams.
    //
    // Closes audit item #12 from
    // parity-audit-2026-05-15/airpay_exams.md.
    if ($oldversion < 2026051901) {
        $table = new xmldb_table('local_airpay_exams');
        $field = new xmldb_field('categoryid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, null, '0', 'departmentid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026051901, 'local', 'airpay_exams');
    }

    return true;
}
