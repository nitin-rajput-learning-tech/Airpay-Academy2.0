<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

/**
 * Upgrade script for local_airpay_programs.
 *
 * @package    local_airpay_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_programs_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 1.0.0 — install all 4 tables (programs, levels, courses, users).
    if ($oldversion < 2026041906) {

        // ── local_airpay_programs ─────────────────────────────────────
        $table = new xmldb_table('local_airpay_programs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',                  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name',                XMLDB_TYPE_CHAR,    '254', null, XMLDB_NOTNULL);
            $table->add_field('description',         XMLDB_TYPE_TEXT,    null, null, null);
            $table->add_field('costcenterid',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('open_path',           XMLDB_TYPE_CHAR,    '254', null, null);
            $table->add_field('status',              XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('visible',             XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('completion_required', XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated',         XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $table->add_index('idx_status',     XMLDB_INDEX_NOTUNIQUE, ['status']);
            $dbman->create_table($table);
        }

        // ── local_airpay_programs_levels ──────────────────────────────
        $table = new xmldb_table('local_airpay_programs_levels');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',                  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('programid',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('name',                XMLDB_TYPE_CHAR,    '254', null, XMLDB_NOTNULL);
            $table->add_field('description',         XMLDB_TYPE_TEXT,    null, null, null);
            $table->add_field('sortorder',           XMLDB_TYPE_INTEGER, '6',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completion_required', XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated',         XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary',     XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_program',  XMLDB_KEY_FOREIGN, ['programid'],
                            'local_airpay_programs', ['id']);
            $table->add_index('idx_program_sort', XMLDB_INDEX_NOTUNIQUE, ['programid', 'sortorder']);
            $dbman->create_table($table);
        }

        // ── local_airpay_programs_courses ─────────────────────────────
        $table = new xmldb_table('local_airpay_programs_courses');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('levelid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('courseid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('sortorder',   XMLDB_TYPE_INTEGER, '6',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('mandatory',   XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary',   XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_level',  XMLDB_KEY_FOREIGN, ['levelid'], 'local_airpay_programs_levels', ['id']);
            $table->add_key('fk_course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $table->add_index('idx_level_course', XMLDB_INDEX_UNIQUE, ['levelid', 'courseid']);
            $dbman->create_table($table);
        }

        // ── local_airpay_programs_users ───────────────────────────────
        $table = new xmldb_table('local_airpay_programs_users');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',              XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('programid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('userid',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('currentlevelid',  XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('status',          XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecompleted',   XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_key('primary',    XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_program', XMLDB_KEY_FOREIGN, ['programid'], 'local_airpay_programs', ['id']);
            $table->add_key('fk_user',    XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('idx_program_user', XMLDB_INDEX_UNIQUE,    ['programid', 'userid']);
            $table->add_index('idx_status',       XMLDB_INDEX_NOTUNIQUE, ['status']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026041906, 'local', 'airpay_programs');
    }

    // 2026051600 — P1 #9: enrolment-window dates + rich-text description.
    // Mirrors the airpay_learningpath W2 #2 commit (8df39b36f).
    if ($oldversion < 2026051600) {
        $table = new xmldb_table('local_airpay_programs');

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

        upgrade_plugin_savepoint(true, 2026051600, 'local', 'airpay_programs');
    }

    return true;
}
