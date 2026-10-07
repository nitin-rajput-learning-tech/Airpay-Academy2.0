<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Joins the two checked-in BizLMS install files the classroom import reads into one install file.
 *
 * The classroom feature reads two BizLMS plugins, local_classroom and local_location, and keeps a verbatim
 * copy of each install file (tests/fixtures/bizlms/<plugin>.install.xml, ADR-032 "Test approach" 2). The
 * legacy_schema_fixture trait creates the tables of ONE file, so the two are joined here into a temporary
 * file. Nothing is edited on the way: the TABLE elements are copied as they are.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fixture_xml {

    /** The checked-in copies, in creation order (the location tables have no foreign keys to the classroom ones). */
    private const FILES = ['local_classroom.install.xml', 'local_location.install.xml'];

    /**
     * Write the joined install file and return its path.
     *
     * @return string Absolute path of a file the legacy_schema_fixture trait can load.
     */
    public static function combined(): string {
        $dir = dirname(__DIR__, 2) . '/fixtures/bizlms';
        $tables = '';
        foreach (self::FILES as $file) {
            $raw = file_get_contents($dir . '/' . $file);
            if ($raw === false || !preg_match('/<TABLES>(.*)<\/TABLES>/s', $raw, $matches)) {
                throw new \coding_exception('classroom import fixture is not readable: ' . $file);
            }
            $tables .= $matches[1];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n"
            . '<XMLDB PATH="local/sentientia_classroom/tests/fixtures/bizlms" VERSION="20260930"'
            . ' COMMENT="BizLMS local_classroom and local_location, joined for the classroom import tests">' . "\n"
            . '  <TABLES>' . $tables . '</TABLES>' . "\n"
            . '</XMLDB>' . "\n";
        $path = make_temp_directory('bizlms_classroom_fixture') . '/joined.install.xml';
        file_put_contents($path, $xml);
        return $path;
    }
}
