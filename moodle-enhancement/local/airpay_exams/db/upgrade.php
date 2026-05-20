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

    // 2026052001 — P1 #33: exam deadline-reminder cron tracking table.
    //
    // Mirrors P1 #28's local_airpay_courses_remind_sent shape but
    // keyed on the EXAM id (which wraps a quiz) rather than a course.
    // The deadline source is quiz.timeclose, not enrolment + days.
    // Closes audit item #16 from parity-audit-2026-05-15/airpay_exams.md.
    if ($oldversion < 2026052001) {
        $table = new xmldb_table('local_airpay_exams_remind_sent');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('examid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('days_before_deadline', XMLDB_TYPE_INTEGER, '4', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('deadline_ts', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timesent', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'],
                'user', ['id']);
            $table->add_key('fk_exam', XMLDB_KEY_FOREIGN, ['examid'],
                'local_airpay_exams', ['id']);

            $table->add_index('idx_user_exam_bucket', XMLDB_INDEX_UNIQUE,
                ['userid', 'examid', 'days_before_deadline', 'deadline_ts']);
            $table->add_index('idx_timesent', XMLDB_INDEX_NOTUNIQUE,
                ['timesent']);

            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026052001, 'local', 'airpay_exams');
    }

    return true;
}
