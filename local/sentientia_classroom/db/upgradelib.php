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
