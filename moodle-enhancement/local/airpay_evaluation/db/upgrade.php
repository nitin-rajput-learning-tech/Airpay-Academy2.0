<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_evaluation_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026041910) {

        // ── local_airpay_evaluation ───────────────────────────────────
        $table = new xmldb_table('local_airpay_evaluation');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',                XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name',              XMLDB_TYPE_CHAR,    '254', null, XMLDB_NOTNULL);
            $table->add_field('description',       XMLDB_TYPE_TEXT,    null, null, null);
            $table->add_field('kirkpatrick_level', XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('trigger_event',     XMLDB_TYPE_CHAR,    '50', null, XMLDB_NOTNULL, null, 'manual');
            $table->add_field('days_after',        XMLDB_TYPE_INTEGER, '4',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('costcenterid',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('open_path',         XMLDB_TYPE_CHAR,    '254', null, null);
            $table->add_field('status',            XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('anonymous',         XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $table->add_index('idx_status',     XMLDB_INDEX_NOTUNIQUE, ['status']);
            $dbman->create_table($table);
        }

        // ── local_airpay_evaluation_questions ─────────────────────────
        $table = new xmldb_table('local_airpay_evaluation_questions');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('evaluationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('questiontype', XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'rating');
            $table->add_field('questiontext', XMLDB_TYPE_TEXT,    null, null, XMLDB_NOTNULL);
            $table->add_field('options',      XMLDB_TYPE_TEXT,    null, null, null);
            $table->add_field('required',     XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('sortorder',    XMLDB_TYPE_INTEGER, '6',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_evaluation', XMLDB_KEY_FOREIGN, ['evaluationid'],
                            'local_airpay_evaluation', ['id']);
            $table->add_index('idx_eval_sort', XMLDB_INDEX_NOTUNIQUE, ['evaluationid', 'sortorder']);
            $dbman->create_table($table);
        }

        // ── local_airpay_evaluation_responses ─────────────────────────
        $table = new xmldb_table('local_airpay_evaluation_responses');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('evaluationid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('userid',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('courseid',      XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('programid',     XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('classroomid',   XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('response_data', XMLDB_TYPE_TEXT,    null, null, XMLDB_NOTNULL);
            $table->add_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_evaluation', XMLDB_KEY_FOREIGN, ['evaluationid'],
                            'local_airpay_evaluation', ['id']);
            $table->add_index('idx_evaluation_user', XMLDB_INDEX_NOTUNIQUE, ['evaluationid', 'userid']);
            $table->add_index('idx_courseid',        XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026041910, 'local', 'airpay_evaluation');
    }

    return true;
}
