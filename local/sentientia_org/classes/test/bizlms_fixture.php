<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\test;

defined('MOODLE_INTERNAL') || die();

/**
 * Test fixture trait — ensures the BizLMS-injected columns exist on
 * the user / course tables before a test runs.
 *
 * Production has open_path, open_employeeid, open_designation,
 * open_supervisorid, open_location (added by the legacy BizLMS
 * costcenter plugin, which we kept for compatibility). The PHPUnit
 * test DB created by `init.php` does NOT have these columns because
 * they are not in any local plugin's install.xml — they are part of
 * a vendor data schema that lives outside our repo.
 *
 * This trait adds them back at the start of each test so our queries
 * referencing u.open_path actually compile.
 *
 * Usage:
 *   final class my_test extends \advanced_testcase {
 *       use \local_sentientia_org\test\bizlms_fixture;
 *       public function test_something(): void {
 *           $this->resetAfterTest();
 *           $this->ensure_bizlms_schema();
 *           // ... rest of test
 *       }
 *   }
 */
trait bizlms_fixture {

    /**
     * Add BizLMS columns to mdl_user and mdl_course if missing.
     * Idempotent — safe to call from every test method.
     */
    protected function ensure_bizlms_schema(): void {
        global $DB;
        $dbman = $DB->get_manager();

        // mdl_user — five BizLMS columns referenced by sentientia_users / org / etc.
        $usertable = new \xmldb_table('user');
        $userfields = [
            ['open_path',           XMLDB_TYPE_CHAR,    '255', null, null,        null, null],
            ['open_employeeid',     XMLDB_TYPE_CHAR,    '255', null, null,        null, null],
            ['open_designation',    XMLDB_TYPE_CHAR,    '255', null, null,        null, null],
            ['open_supervisorid',   XMLDB_TYPE_INTEGER, '20',  null, null,        null, null],
            ['open_location',       XMLDB_TYPE_CHAR,    '200', null, null,        null, null],
            // Audience-filter columns queried by the *_audience_enroller /
            // _assigner classes (learningpath, programs, classroom, evaluation).
            // Defs mirror local_sentientia_core\substrate::user_fields().
            ['open_region',         XMLDB_TYPE_CHAR,    '200', null, null,        null, null],
            ['open_employmenttype', XMLDB_TYPE_CHAR,    '512', null, null,        null, null],
            ['open_grade',          XMLDB_TYPE_CHAR,    '200', null, null,        null, null],
            ['open_hrmsrole',       XMLDB_TYPE_CHAR,    '200', null, null,        null, null],
        ];
        foreach ($userfields as [$name, $type, $length, $unsigned, $notnull, $sequence, $default]) {
            $field = new \xmldb_field($name, $type, $length, $unsigned, $notnull, $sequence, $default);
            if (!$dbman->field_exists($usertable, $field)) {
                $dbman->add_field($usertable, $field);
            }
        }

        // mdl_course — open_path used by list_courses scope filter.
        $coursetable = new \xmldb_table('course');
        $coursefield = new \xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($coursetable, $coursefield)) {
            $dbman->add_field($coursetable, $coursefield);
        }
    }
}
