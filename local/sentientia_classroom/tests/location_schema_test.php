<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * The locations schema is the same on a fresh install and an upgraded site
 * (2026-09-25 review).
 *
 * local_sentientia_locations and the locationid columns on
 * local_sentientia_classroom / _sessions came only from upgrade step
 * 2026051160, never from install.xml. A site installed fresh after that step
 * (UAT's 5.2 install, PHPUnit init, any new customer) had none of them; an
 * upgraded site had all of them, with latitude / longitude at NUMBER(10,0)
 * because the decimals were passed as an argument add_field() does not take.
 * install.xml now declares them and step 2026092501
 * (db/upgradelib.php local_sentientia_classroom_ensure_location_schema)
 * brings both kinds of site to that schema.
 *
 * The table carries costcenterid, the tenant column every tenant-scoped
 * locations query will filter on, so it is asserted too.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers ::local_sentientia_classroom_ensure_location_schema
 * @covers ::xmldb_local_sentientia_classroom_upgrade
 * @group tenant_isolation
 */
final class location_schema_test extends \advanced_testcase {

    private const LOCATIONS = 'local_sentientia_locations';

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/sentientia_classroom/db/upgradelib.php');
    }

    protected function tearDown(): void {
        global $CFG, $DB;
        // A failed assertion must not leave the shared test DB without the
        // schema the other tests expect: restore it from install.xml's
        // definition, independently of the helper under test.
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::LOCATIONS)) {
            $dbman->install_one_table_from_xmldb_file(
                $CFG->dirroot . '/local/sentientia_classroom/db/install.xml', self::LOCATIONS);
        }
        foreach (['local_sentientia_classroom', 'local_sentientia_classroom_sessions'] as $tablename) {
            $table = new \xmldb_table($tablename);
            $field = new \xmldb_field('locationid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        parent::tearDown();
    }

    /** Assert the schema install.xml declares. */
    private function assert_install_xml_schema(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $this->assertTrue($dbman->table_exists(self::LOCATIONS));
        $cols = $DB->get_columns(self::LOCATIONS, false);
        foreach (['latitude', 'longitude'] as $name) {
            $this->assertArrayHasKey($name, $cols);
            $this->assertEquals(6, (int) $cols[$name]->scale, "{$name} must keep 6 decimals.");
        }
        $this->assertArrayHasKey('costcenterid', $cols, 'The tenant column must be there.');
        $this->assertTrue((bool) $cols['costcenterid']->not_null);
        foreach (['local_sentientia_classroom', 'local_sentientia_classroom_sessions'] as $table) {
            $this->assertArrayHasKey('locationid', $DB->get_columns($table, false),
                "{$table}.locationid must exist.");
        }
    }

    public function test_a_fresh_install_already_has_the_locations_schema(): void {
        global $DB;
        $this->assert_install_xml_schema();
        $this->assertSame([], local_sentientia_classroom_ensure_location_schema($DB->get_manager()),
            'A fresh install must need nothing: it is already what install.xml declares.');
    }

    public function test_a_site_installed_before_the_fix_gets_the_table_and_columns(): void {
        global $DB;
        $dbman = $DB->get_manager();
        // What a fresh install between 2026051160 and 2026092501 looks like.
        $dbman->drop_table(new \xmldb_table(self::LOCATIONS));
        foreach (['local_sentientia_classroom', 'local_sentientia_classroom_sessions'] as $table) {
            $dbman->drop_field(new \xmldb_table($table), new \xmldb_field('locationid'));
        }

        $changed = local_sentientia_classroom_ensure_location_schema($dbman);

        $this->assertContains('created local_sentientia_locations', $changed);
        $this->assertContains('added local_sentientia_classroom.locationid', $changed);
        $this->assertContains('added local_sentientia_classroom_sessions.locationid', $changed);
        $this->assert_install_xml_schema();
    }

    public function test_upgrade_step_2026092501_widens_the_coordinates_of_an_upgraded_site(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/sentientia_classroom/db/upgrade.php');
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(self::LOCATIONS);
        // What step 2026051160 actually created: NUMBER(10,0).
        $dbman->change_field_precision($table,
            new \xmldb_field('latitude', XMLDB_TYPE_NUMBER, '10', null, null, null, null, 'equipment'));
        $dbman->change_field_precision($table,
            new \xmldb_field('longitude', XMLDB_TYPE_NUMBER, '10', null, null, null, null, 'latitude'));
        $this->assertEquals(0, (int) $DB->get_columns(self::LOCATIONS, false)['latitude']->scale,
            'Precondition: the column has lost its decimals.');

        set_config('version', 2026092500, 'local_sentientia_classroom');
        ob_start();
        $ok = xmldb_local_sentientia_classroom_upgrade(2026092500);
        $out = (string) ob_get_clean();

        $this->assertTrue($ok);
        $this->assertStringContainsString('widened local_sentientia_locations.latitude', $out);
        $this->assertStringContainsString('widened local_sentientia_locations.longitude', $out);
        $this->assert_install_xml_schema();
        $this->assertEquals(2026092501, get_config('local_sentientia_classroom', 'version'));

        // A coordinate now survives the round trip instead of becoming 19 / 73.
        $id = $DB->insert_record(self::LOCATIONS, (object) [
            'name' => 'Mumbai HQ', 'city' => 'Mumbai', 'latitude' => 19.076090,
            'longitude' => 72.877426, 'costcenterid' => 1, 'active' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $row = $DB->get_record(self::LOCATIONS, ['id' => $id], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(19.076090, (float) $row->latitude, 0.0000005);
        $this->assertEqualsWithDelta(72.877426, (float) $row->longitude, 0.0000005);
    }
}
