<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_classroom.
 *
 * @package   local_sentientia_classroom
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The locations schema (ENTERPRISE-GRADE-PLAN.md A.5), exactly as
 * db/install.xml declares it since 2026092501.
 *
 * Until then local_sentientia_locations and the two locationid columns came
 * only from upgrade step 2026051160, so a site installed fresh after that step
 * (PHPUnit init included) had none of them while an upgraded site had all of
 * them, and check_database_schema.php reported the difference. That step also
 * passed the decimals as a 10th argument to xmldb_table::add_field(), which
 * takes 8, so latitude and longitude were created NUMBER(10,0) and would have
 * stored every coordinate rounded to a whole degree.
 *
 * This brings any site to the install.xml schema and is safe to run on any of
 * them: it creates the table where it is missing, adds locationid where it is
 * missing, and widens latitude / longitude to NUMBER(10,6) where they have
 * fewer decimals. Nothing is dropped and no row is touched. No code writes
 * these yet, so there is no data to convert.
 *
 * Used by upgrade step 2026092501; a function so
 * tests/location_schema_test.php can prove it without replaying the upgrade.
 *
 * @param database_manager $dbman
 * @return string[] what was changed, one line each (empty when nothing was)
 */
function local_sentientia_classroom_ensure_location_schema(database_manager $dbman): array {
    global $DB;
    $changed = [];

    $table = new xmldb_table('local_sentientia_locations');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name',         XMLDB_TYPE_CHAR,    '200',   null, XMLDB_NOTNULL);
        $table->add_field('city',         XMLDB_TYPE_CHAR,    '100',   null, null, null, '');
        $table->add_field('address',      XMLDB_TYPE_TEXT,    null,    null, null, null, null);
        $table->add_field('capacity',     XMLDB_TYPE_INTEGER, '10',    null, null, null, '0');
        $table->add_field('equipment',    XMLDB_TYPE_TEXT,    null,    null, null, null, null);
        $table->add_field('latitude',     XMLDB_TYPE_NUMBER,  '10, 6', null, null, null, null);
        $table->add_field('longitude',    XMLDB_TYPE_NUMBER,  '10, 6', null, null, null, null);
        $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, '0');
        $table->add_field('active',       XMLDB_TYPE_INTEGER, '1',     null, XMLDB_NOTNULL, null, '1');
        // ADR-032 (2026093002): the venue hierarchy the BizLMS import fills. Same three columns as
        // local_sentientia_classroom_location_import_fields(), which adds them to a table that exists.
        foreach (local_sentientia_classroom_location_import_fields() as $field) {
            $table->addField($field);
        }
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_active',     XMLDB_INDEX_NOTUNIQUE, ['active']);
        $table->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
        $table->add_index('idx_city',       XMLDB_INDEX_NOTUNIQUE, ['city']);

        $dbman->create_table($table);
        $changed[] = 'created local_sentientia_locations';
    } else {
        $columns = $DB->get_columns('local_sentientia_locations', false);
        foreach (['latitude' => 'equipment', 'longitude' => 'latitude'] as $name => $previous) {
            if (!isset($columns[$name]) || (int) $columns[$name]->scale >= 6) {
                continue;
            }
            local_sentientia_classroom_widen_decimals($dbman, $table,
                new xmldb_field($name, XMLDB_TYPE_NUMBER, '10, 6', null, null, null, null, $previous));
            $changed[] = "widened local_sentientia_locations.{$name} to NUMBER(10,6)";
        }
        local_sentientia_classroom_add_missing_fields($dbman, $table,
            local_sentientia_classroom_location_import_fields(), $changed);
    }

    foreach (['local_sentientia_classroom', 'local_sentientia_classroom_sessions'] as $tablename) {
        $t = new xmldb_table($tablename);
        $f = new xmldb_field('locationid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'location');
        if ($dbman->table_exists($t) && !$dbman->field_exists($t, $f)) {
            $dbman->add_field($t, $f);
            $changed[] = "added {$tablename}.locationid";
        }
    }

    return $changed;
}

/**
 * Widen a NUMBER column's decimals on any supported database.
 *
 * database_manager::change_field_precision() emits no SQL on PostgreSQL when
 * the column currently has 0 decimals: postgres_sql_generator::getAlterFieldSQL()
 * treats an empty old scale as "decimals unchanged". So on the postgres family
 * the column type is altered directly; elsewhere the DDL API is used. Existing
 * values are cast, which is safe for a widening that keeps enough integer digits.
 *
 * @param database_manager $dbman
 * @param xmldb_table $table
 * @param xmldb_field $field the target definition (TYPE_NUMBER with decimals)
 */
function local_sentientia_classroom_widen_decimals(database_manager $dbman, xmldb_table $table,
        xmldb_field $field): void {
    global $DB;
    if ($DB->get_dbfamily() === 'postgres') {
        $DB->change_database_structure('ALTER TABLE ' . $DB->get_prefix() . $table->getName()
            . ' ALTER COLUMN ' . $field->getName()
            . ' TYPE NUMERIC(' . (int) $field->getLength() . ',' . (int) $field->getDecimals() . ')');
        return;
    }
    $dbman->change_field_precision($table, $field);
}

/**
 * T-01 back-fill: let the teacher and editingteacher archetype roles view classrooms and take
 * attendance.
 *
 * db/access.php now lists the `teacher` archetype (the Sentientia `trainer` role) for
 * local/sentientia_classroom:view and :attendance. Moodle applies archetype defaults only when a
 * capability is first registered, so a site that already has these capabilities never gives them
 * to existing roles; this grants them explicitly, as a fresh install now would.
 *
 * Idempotent, and it only fills a gap: a role that already has ANY setting for the capability at
 * system context (ALLOW already, or an administrator's PREVENT or PROHIBIT) is left as it is, so
 * nothing is ever downgraded or overridden. A capability that is not registered is skipped.
 * Roles of other archetypes (manager, student, none) are not touched. No other capability is
 * granted: :manage, :create, :update and :delete stay with the manager archetype.
 *
 * Used by upgrade step 2026093001; a function so tests/trainer_caps_backfill_test.php can prove it
 * without replaying the upgrade.
 *
 * @return int number of grants made (0 when every role already had a setting)
 */
function local_sentientia_classroom_backfill_teacher_caps(): int {
    global $DB;

    $syscontext = \context_system::instance();
    $caps = ['local/sentientia_classroom:view', 'local/sentientia_classroom:attendance'];
    $granted = 0;

    $roles = $DB->get_records_list('role', 'archetype', ['teacher', 'editingteacher'], 'id ASC', 'id');
    foreach ($roles as $role) {
        foreach ($caps as $cap) {
            if (!$DB->record_exists('capabilities', ['name' => $cap])) {
                continue;
            }
            if ($DB->record_exists('role_capabilities',
                    ['roleid' => $role->id, 'capability' => $cap, 'contextid' => $syscontext->id])) {
                continue;   // An explicit setting stands, whatever it is.
            }
            assign_capability($cap, CAP_ALLOW, $role->id, $syscontext->id, false);
            $granted++;
        }
    }
    $syscontext->mark_dirty();

    return $granted;
}

/**
 * ADR-032: the venue-hierarchy columns of local_sentientia_locations, exactly as db/install.xml declares them.
 *
 * @return xmldb_field[] parentid, venue_type, building
 */
function local_sentientia_classroom_location_import_fields(): array {
    return [
        new xmldb_field('parentid',   XMLDB_TYPE_INTEGER, '10',  null, null, null, null),
        new xmldb_field('venue_type', XMLDB_TYPE_INTEGER, '2',   null, null, null, null),
        new xmldb_field('building',   XMLDB_TYPE_CHAR,    '225', null, null, null, null),
    ];
}

/**
 * Add the columns that are missing from a table that exists; touch nothing else.
 *
 * @param database_manager $dbman
 * @param xmldb_table $table
 * @param xmldb_field[] $fields
 * @param string[] $changed appended with one line per column added
 * @return void
 */
function local_sentientia_classroom_add_missing_fields(database_manager $dbman, xmldb_table $table,
        array $fields, array &$changed): void {
    if (!$dbman->table_exists($table)) {
        return;
    }
    foreach ($fields as $field) {
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $changed[] = 'added ' . $table->getName() . '.' . $field->getName();
        }
    }
}

/**
 * ADR-032: bring any site to the schema the BizLMS classroom importer writes to, as db/install.xml declares it.
 *
 * Adds, where missing and without touching a row:
 *  - local_sentientia_classroom: shortname, trainingstart, trainingend, timecompleted, createdby;
 *  - local_sentientia_classroom_users: completion_status, timecompleted, hours;
 *  - local_sentientia_locations: parentid, venue_type, building (through ensure_location_schema());
 *  - the tables local_sentientia_classroom_trainers and local_sentientia_classroom_courses.
 *
 * Idempotent: a fresh install (install.xml already has all of it) changes nothing and returns an empty list.
 * Used by upgrade step 2026093002; a function so tests/import_schema_test.php can prove it without replaying
 * the upgrade.
 *
 * @param database_manager $dbman
 * @return string[] what was changed, one line each (empty when nothing was)
 */
function local_sentientia_classroom_ensure_import_schema(database_manager $dbman): array {
    $changed = local_sentientia_classroom_ensure_location_schema($dbman);

    local_sentientia_classroom_add_missing_fields($dbman, new xmldb_table('local_sentientia_classroom'), [
        new xmldb_field('shortname',     XMLDB_TYPE_CHAR,    '225', null, null, null, null),
        new xmldb_field('trainingstart', XMLDB_TYPE_INTEGER, '10',  null, null, null, null),
        new xmldb_field('trainingend',   XMLDB_TYPE_INTEGER, '10',  null, null, null, null),
        new xmldb_field('timecompleted', XMLDB_TYPE_INTEGER, '10',  null, null, null, null),
        new xmldb_field('createdby',     XMLDB_TYPE_INTEGER, '10',  null, null, null, null),
    ], $changed);

    local_sentientia_classroom_add_missing_fields($dbman, new xmldb_table('local_sentientia_classroom_users'), [
        new xmldb_field('completion_status', XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '0'),
        new xmldb_field('timecompleted',     XMLDB_TYPE_INTEGER, '10', null, null, null, null),
        new xmldb_field('hours',             XMLDB_TYPE_INTEGER, '10', null, null, null, null),
    ], $changed);

    $trainers = new xmldb_table('local_sentientia_classroom_trainers');
    if (!$dbman->table_exists($trainers)) {
        $trainers->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $trainers->add_field('classroomid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $trainers->add_field('trainerid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $trainers->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $trainers->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $trainers->add_key('primary',      XMLDB_KEY_PRIMARY, ['id']);
        $trainers->add_key('fk_classroom', XMLDB_KEY_FOREIGN, ['classroomid'], 'local_sentientia_classroom', ['id']);
        $trainers->add_key('fk_trainer',   XMLDB_KEY_FOREIGN, ['trainerid'],   'user',                       ['id']);
        $trainers->add_index('idx_classroom_trainer', XMLDB_INDEX_UNIQUE, ['classroomid', 'trainerid']);
        $dbman->create_table($trainers);
        $changed[] = 'created local_sentientia_classroom_trainers';
    }

    $courses = new xmldb_table('local_sentientia_classroom_courses');
    if (!$dbman->table_exists($courses)) {
        $courses->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $courses->add_field('classroomid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $courses->add_field('courseid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $courses->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $courses->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $courses->add_key('primary',      XMLDB_KEY_PRIMARY, ['id']);
        $courses->add_key('fk_classroom', XMLDB_KEY_FOREIGN, ['classroomid'], 'local_sentientia_classroom', ['id']);
        $courses->add_key('fk_course',    XMLDB_KEY_FOREIGN, ['courseid'],    'course',                     ['id']);
        $courses->add_index('idx_classroom_course', XMLDB_INDEX_UNIQUE, ['classroomid', 'courseid']);
        $dbman->create_table($courses);
        $changed[] = 'created local_sentientia_classroom_courses';
    }

    return $changed;
}
