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

    // 2026050901 — Phase G.2: per-question anonymous toggle.
    if ($oldversion < 2026050901) {
        $table = new xmldb_table('local_airpay_evaluation_questions');
        $field = new xmldb_field('anonymous', XMLDB_TYPE_INTEGER, '1',
            null, XMLDB_NOTNULL, null, '0', 'required');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026050901,
            'local', 'airpay_evaluation');
    }

    // 2026051500 — W1-5: trigger queue + observer wiring.
    //
    // Adds the local_airpay_evaluation_triggers table so the event observer
    // can enqueue future fires with a `days_after` delay, and a scheduled
    // task can drain the queue. Before W1-5 the trigger_event column on the
    // evaluation form was decorative; now an enabled course_completion
    // evaluation actually queues + delivers.
    if ($oldversion < 2026051500) {
        $table = new xmldb_table('local_airpay_evaluation_triggers');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',            XMLDB_TYPE_INTEGER, '10', null,
                              XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('evaluationid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('userid',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('itemid',        XMLDB_TYPE_INTEGER, '10', null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('trigger_event', XMLDB_TYPE_CHAR,    '50', null, XMLDB_NOTNULL);
            $table->add_field('fire_after',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('status',        XMLDB_TYPE_INTEGER, '2',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',   XMLDB_TYPE_INTEGER, '10', null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('timefired',     XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_evaluation', XMLDB_KEY_FOREIGN, ['evaluationid'],
                            'local_airpay_evaluation', ['id']);
            $table->add_key('uk_eval_user_item', XMLDB_KEY_UNIQUE,
                            ['evaluationid', 'userid', 'itemid']);
            $table->add_index('idx_fire_status', XMLDB_INDEX_NOTUNIQUE,
                              ['status', 'fire_after']);
            $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026051500, 'local', 'airpay_evaluation');
    }

    // 2026051901 — P1 #17: time-bounded availability + multiple-submit.
    //
    // Three new columns on local_airpay_evaluation:
    //   timeopen        — Unix timestamp the evaluation becomes available
    //                      (0 = immediately, no constraint).
    //   timeclose       — Unix timestamp the evaluation stops accepting
    //                      responses (0 = never closes, no constraint).
    //   multiple_submit — 1 = the same user can submit more than once,
    //                      used for pulse surveys (weekly engagement check,
    //                      monthly compliance tick, etc.). 0 = one-and-done.
    //
    // Closes audit items #14 + #15 from
    // parity-audit-2026-05-15/airpay_evaluation.md.
    if ($oldversion < 2026051901) {
        $table = new xmldb_table('local_airpay_evaluation');

        $field = new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'anonymous');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'timeopen');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('multiple_submit', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'timeclose');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026051901, 'local', 'airpay_evaluation');
    }

    // 2026051903 — P1 #19: email-on-response admin notification flag.
    //
    // Adds one column to local_airpay_evaluation:
    //   notify_admin_on_response — 1 = fire `evaluation_response` message
    //                              provider to siteadmins on every
    //                              successful submission. 0 = silent.
    //
    // Closes audit item #17 from
    // parity-audit-2026-05-15/airpay_evaluation.md.
    if ($oldversion < 2026051903) {
        $table = new xmldb_table('local_airpay_evaluation');
        $field = new xmldb_field('notify_admin_on_response',
            XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'multiple_submit');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026051903, 'local', 'airpay_evaluation');
    }

    // 2026052010 — P1 #30: conditional question display.
    //
    // Adds two columns to `local_airpay_evaluation_questions`:
    //   depends_on_qid   — parent question id (nullable). NULL = always shown.
    //   depends_on_value — required parent answer (nullable). NULL with a
    //                       non-null parent = "show when parent has ANY
    //                       non-empty answer".
    //
    // Closes audit item #10 from
    // parity-audit-2026-05-15/airpay_evaluation.md. Enables branching
    // surveys without a separate dependencies table — the parent ref
    // lives on the child row.
    if ($oldversion < 2026052010) {
        $table = new xmldb_table('local_airpay_evaluation_questions');

        $field = new xmldb_field('depends_on_qid', XMLDB_TYPE_INTEGER, '10',
            null, null, null, null, 'anonymous');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('depends_on_value', XMLDB_TYPE_CHAR, '255',
            null, null, null, null, 'depends_on_qid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026052010,
            'local', 'airpay_evaluation');
    }

    return true;
}
