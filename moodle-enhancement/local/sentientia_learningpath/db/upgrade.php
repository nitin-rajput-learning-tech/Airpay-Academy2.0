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

    // 2026061600 — P0.2: Adaptive Learning Journeys.
    //
    // New table: local_sentientia_lp_adaptive_log
    //   Stores every pivot decision (branch / accelerate / remediate) made
    //   by journey_engine for audit, analytics, and replay debugging.
    //
    //   pathid          FK → local_sentientia_learningpath.id
    //   userid          FK → mdl_user.id
    //   costcenterid    BizLMS tenant (mandatory for multi-tenant scoping)
    //   pivot_type      CHAR 20: 'branch'|'accelerate'|'remediate'|'no_action'
    //   trigger_type    CHAR 20: 'quiz_score'|'velocity'|'skills_gap'|'combined'
    //   source_courseid The course whose quiz/completion triggered the decision
    //   target_courseid The course the engine added or skipped (0 = none)
    //   quiz_score      NUMBER nullable — raw percentage score
    //   velocity_score  NUMBER nullable — completion velocity index (0.0–2.0)
    //   skills_gap_json TEXT nullable — serialised gap payload from skillsai
    //   decision_notes  TEXT nullable — human-readable rationale
    //   timecreated     UNIX timestamp
    //   timemodified    UNIX timestamp
    //
    // New columns on local_sentientia_learningpath:
    //   adaptive_mode        TINYINT 1 NOT NULL DEFAULT 0
    //   score_threshold_low  NUMBER nullable — below this → remediate
    //   score_threshold_high NUMBER nullable — above this → accelerate/branch
    //
    // New columns on local_sentientia_learningpath_courses:
    //   is_remedial          TINYINT 1 NOT NULL DEFAULT 0
    //   is_accelerator       TINYINT 1 NOT NULL DEFAULT 0
    //   remedial_for_courseid INT nullable — main course this node remediates
    if ($oldversion < 2026061600) {

        // ── 1. Adaptive columns on main path table ─────────────────────
        $table_path = new xmldb_table('local_sentientia_learningpath');

        $f = new xmldb_field('adaptive_mode', XMLDB_TYPE_INTEGER, '1',
            null, XMLDB_NOTNULL, null, '0', 'enddate');
        if (!$dbman->field_exists($table_path, $f)) {
            $dbman->add_field($table_path, $f);
        }

        $f = new xmldb_field('score_threshold_low', XMLDB_TYPE_NUMBER, '5',
            null, null, null, null, 'adaptive_mode');
        $f->setDecimals(2);
        if (!$dbman->field_exists($table_path, $f)) {
            $dbman->add_field($table_path, $f);
        }

        $f = new xmldb_field('score_threshold_high', XMLDB_TYPE_NUMBER, '5',
            null, null, null, null, 'score_threshold_low');
        $f->setDecimals(2);
        if (!$dbman->field_exists($table_path, $f)) {
            $dbman->add_field($table_path, $f);
        }

        // ── 2. Node-type columns on path-courses junction table ─────────
        $table_courses = new xmldb_table('local_sentientia_learningpath_courses');

        $f = new xmldb_field('is_remedial', XMLDB_TYPE_INTEGER, '1',
            null, XMLDB_NOTNULL, null, '0', 'mandatory');
        if (!$dbman->field_exists($table_courses, $f)) {
            $dbman->add_field($table_courses, $f);
        }

        $f = new xmldb_field('is_accelerator', XMLDB_TYPE_INTEGER, '1',
            null, XMLDB_NOTNULL, null, '0', 'is_remedial');
        if (!$dbman->field_exists($table_courses, $f)) {
            $dbman->add_field($table_courses, $f);
        }

        $f = new xmldb_field('remedial_for_courseid', XMLDB_TYPE_INTEGER, '10',
            null, null, null, null, 'is_accelerator');
        if (!$dbman->field_exists($table_courses, $f)) {
            $dbman->add_field($table_courses, $f);
        }

        // ── 3. Adaptive decision log table ─────────────────────────────
        $table_log = new xmldb_table('local_sentientia_lp_adaptive_log');
        if (!$dbman->table_exists($table_log)) {
            $table_log->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table_log->add_field('pathid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table_log->add_field('userid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table_log->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table_log->add_field('pivot_type', XMLDB_TYPE_CHAR, '20', null,
                XMLDB_NOTNULL, null, 'no_action');
            $table_log->add_field('trigger_type', XMLDB_TYPE_CHAR, '20', null,
                XMLDB_NOTNULL, null, 'quiz_score');
            $table_log->add_field('source_courseid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table_log->add_field('target_courseid', XMLDB_TYPE_INTEGER, '10', null,
                null, null, '0');
            $table_log->add_field('quiz_score', XMLDB_TYPE_NUMBER, '6', null,
                null, null, null);
            $table_log->add_field('velocity_score', XMLDB_TYPE_NUMBER, '6', null,
                null, null, null);
            $table_log->add_field('skills_gap_json', XMLDB_TYPE_TEXT, null, null,
                null, null, null);
            $table_log->add_field('decision_notes', XMLDB_TYPE_TEXT, null, null,
                null, null, null);
            $table_log->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table_log->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table_log->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table_log->add_index('idx_path_user', XMLDB_INDEX_NOTUNIQUE,
                ['pathid', 'userid']);
            $table_log->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE,
                ['costcenterid']);
            $table_log->add_index('idx_pivot_type', XMLDB_INDEX_NOTUNIQUE,
                ['pivot_type']);
            $table_log->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE,
                ['timecreated']);

            $dbman->create_table($table_log);
        }

        upgrade_plugin_savepoint(true, 2026061600, 'local', 'sentientia_learningpath');
    }

    return true;
}
