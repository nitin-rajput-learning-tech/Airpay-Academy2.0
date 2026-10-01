<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\bizlms\cohort_scope_step;

/**
 * The department list of a BizLMS local_groups row (departmentid, a free CHAR(100)) becomes a clean list of ids.
 *
 * Pure: no database. The import test covers the same cleaning through a real run.
 *
 * @package    local_sentientia_org
 * @category   test
 * @covers     \local_sentientia_org\bizlms\cohort_scope_step::clean_departments
 *
 * @group local_sentientia_org
 * @group bizlms_import
 */
final class cohort_scope_departments_test extends \basic_testcase {

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: bool}> [source, cleaned, reported]
     */
    public static function departments_provider(): array {
        return [
            'null' => [null, null, false],
            'empty' => ['', null, false],
            'blank' => ['   ', null, false],
            'zero means none' => ['0', null, false],
            'one id' => ['5', '5', false],
            'two ids' => ['5,12', '5,12', false],
            'spaces' => [' 5 , 12 ', '5,12', false],
            'a repeat is dropped quietly' => ['12,5,12', '12,5', false],
            'empty items and a zero are dropped quietly' => ['0,,5,', '5', false],
            'what a saved array becomes' => ['Array', null, true],
            'junk between ids' => ['5,x,7', '5,7', true],
            'a negative number' => ['-3,4', '4', true],
            'a decimal' => ['5.5', null, true],
            'a leading zero is still the number' => ['007', '7', false],
        ];
    }

    /**
     * @dataProvider departments_provider
     * @param string|null $raw
     * @param string|null $expected
     * @param bool $reported
     * @return void
     */
    public function test_a_department_list_is_cleaned(?string $raw, ?string $expected, bool $reported): void {
        $changed = false;
        $this->assertSame($expected, cohort_scope_step::clean_departments($raw, $changed));
        $this->assertSame($reported, $changed);
    }
}
