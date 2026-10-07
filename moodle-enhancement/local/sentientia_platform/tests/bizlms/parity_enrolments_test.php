<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\parity;

/**
 * The rehearsal parity gate explains the enrolments import (ADR-032, "Parity hooks" 4; owner decision of 2026-10-07, courses
 * cluster doc item "the rehearsal parity gate will fail by design").
 *
 * migration_parity_check.php counts and checksums core user_enrolments, and the enrolments importer (gap G6) adds one manual
 * enrolment per converted BizLMS enrolment (7 733 on the April 2026 copy). Without an explanation a clean import drifted, and
 * the runbook's "100% PARITY" step could not pass. The explanation comes from the legacy map and covers exactly the rows the
 * map says were imported and that still exist: the count must grow by exactly that many, and the checksum, a SUM of per-row
 * CRC32 values, must grow by exactly the sum of those rows' CRCs. Anything else stays drift.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\parity
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class parity_enrolments_test extends \advanced_testcase {

    /** The columns migration_parity_check.php hashes for user_enrolments, in its order. */
    private const COLUMNS = ['id', 'enrolid', 'userid', 'status', 'timestart', 'timeend'];

    /**
     * count and checksum of user_enrolments built the way the CLI builds them (MySQL family only).
     *
     * @return array{rows: int, crc: string}
     */
    private function checksum(): array {
        global $DB;
        $parts = array_map(static fn(string $c): string => "IFNULL(`{$c}`, '~NULL~')", self::COLUMNS);
        $expr = 'CONCAT_WS(0x1f, ' . implode(', ', $parts) . ')';
        return [
            'rows' => (int) $DB->count_records('user_enrolments'),
            'crc' => (string) $DB->get_field_sql("SELECT COALESCE(SUM(CRC32({$expr})), 0) FROM {user_enrolments}"),
        ];
    }

    /**
     * Say in the legacy map that the enrolments feature imported a user_enrolments row.
     *
     * @param int $ueid The new manual enrolment.
     * @param int $legacyid The legacy enrolment it came from.
     * @param string $feature
     * @return void
     */
    private function map_import(int $ueid, int $legacyid, string $feature = 'enrolments'): void {
        global $DB;
        $DB->insert_record(legacymap::TABLE, (object) [
            'feature' => $feature, 'sourcetable' => '#user_enrolments.id', 'sourceid' => $legacyid, 'subkey' => '',
            'targettable' => 'user_enrolments', 'targetid' => $ueid, 'outcome' => 'imported', 'reason' => null,
            'detail' => null, 'runid' => 0, 'timecreated' => 1700000000,
        ]);
    }

    private function ueid(int $userid, int $courseid): int {
        global $DB;
        return (int) $DB->get_field_sql(
            'SELECT ue.id FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :u AND e.courseid = :c', ['u' => $userid, 'c' => $courseid], MUST_EXIST);
    }

    public function test_the_count_delta_is_explained_only_when_it_is_exactly_the_imported_rows(): void {
        $this->assertTrue(parity::enrolment_count_explained(100, 107, 7));
        $this->assertFalse(parity::enrolment_count_explained(100, 106, 7), 'one row short: something else changed');
        $this->assertFalse(parity::enrolment_count_explained(100, 108, 7), 'one row too many');
        $this->assertFalse(parity::enrolment_count_explained(100, 100, 0), 'no import, no explanation');
        $this->assertFalse(parity::enrolment_count_explained(100, 107, 0), 'a delta nothing in the map accounts for is drift');
        $this->assertFalse(parity::enrolment_count_explained(107, 100, 7), 'a count that fell is never explained');
    }

    public function test_the_checksum_delta_is_explained_only_when_the_crcs_add_up(): void {
        $base = ['rows' => 10, 'crc' => '1000'];
        $added = ['rows' => 2, 'crc' => '300'];
        $this->assertTrue(parity::enrolment_checksum_explained($base, ['rows' => 12, 'crc' => '1300'], $added));
        $this->assertFalse(parity::enrolment_checksum_explained($base, ['rows' => 12, 'crc' => '1301'], $added),
            'a changed legacy row moves the sum');
        $this->assertFalse(parity::enrolment_checksum_explained($base, ['rows' => 13, 'crc' => '1300'], $added));
        $this->assertFalse(parity::enrolment_checksum_explained($base, ['rows' => 12, 'crc' => '1300'],
            ['rows' => 0, 'crc' => null]), 'nothing imported');
        $this->assertFalse(parity::enrolment_checksum_explained(['rows' => 10, 'crc' => null],
            ['rows' => 12, 'crc' => '1300'], $added), 'an engine without a CRC proves nothing');
        $this->assertFalse(parity::enrolment_checksum_explained($base, ['rows' => 12, 'crc' => null], $added));
    }

    public function test_only_rows_the_enrolments_feature_imported_and_that_still_exist_are_counted(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $users = [];
        foreach (['a', 'b', 'c', 'd'] as $key) {
            $users[$key] = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($users[$key]->id, $course->id);
        }
        $this->map_import($this->ueid($users['a']->id, $course->id), 9001);
        $this->map_import($this->ueid($users['b']->id, $course->id), 9002);
        // Another feature's import is not this one's, a row that is merely mapped as folded is not a new row, and a
        // target that no longer exists adds nothing.
        $this->map_import($this->ueid($users['c']->id, $course->id), 9003, 'learningplan');
        $DB->insert_record(legacymap::TABLE, (object) [
            'feature' => 'enrolments', 'sourcetable' => '#user_enrolments.id', 'sourceid' => 9004, 'subkey' => '',
            'targettable' => 'user_enrolments', 'targetid' => $this->ueid($users['d']->id, $course->id),
            'outcome' => 'folded', 'reason' => 'already_manual', 'detail' => null, 'runid' => 0, 'timecreated' => 1700000000,
        ]);
        $this->map_import(99999999, 9005);

        $added = parity::imported_enrolments(self::COLUMNS);

        $this->assertSame(2, $added['rows']);
        $this->assertSame(0, $added['switched_off'], 'no trail table rows yet');
        if ($DB->get_dbfamily() === 'mysql') {
            $expected = $this->checksum_of([$this->ueid($users['a']->id, $course->id), $this->ueid($users['b']->id, $course->id)]);
            $this->assertSame($expected, $added['crc'], 'the CRC of exactly those two rows');
        } else {
            $this->assertNull($added['crc'], 'no CRC32 on this engine');
        }
    }

    /**
     * @param int[] $ids
     * @return string SUM(CRC32(row)) of those user_enrolments rows.
     */
    private function checksum_of(array $ids): string {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $parts = array_map(static fn(string $c): string => "IFNULL(`{$c}`, '~NULL~')", self::COLUMNS);
        $expr = 'CONCAT_WS(0x1f, ' . implode(', ', $parts) . ')';
        return (string) $DB->get_field_sql("SELECT COALESCE(SUM(CRC32({$expr})), 0) FROM {user_enrolments} WHERE id {$insql}",
            $params);
    }

    public function test_a_real_import_delta_adds_up_and_a_tampered_row_does_not(): void {
        global $DB;
        $this->resetAfterTest();
        if ($DB->get_dbfamily() !== 'mysql') {
            $this->markTestSkipped('the checksum is SUM(CRC32()), MySQL and MariaDB only');
        }
        $course = $this->getDataGenerator()->create_course();
        $legacy = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($legacy->id, $course->id);

        // The source baseline: one enrolment that exists before the import.
        $baseline = $this->checksum();

        // The import converts two enrolments.
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($one->id, $course->id);
        $this->getDataGenerator()->enrol_user($two->id, $course->id);
        $this->map_import($this->ueid($one->id, $course->id), 9101);
        $this->map_import($this->ueid($two->id, $course->id), 9102);

        $now = $this->checksum();
        $added = parity::imported_enrolments(self::COLUMNS);
        $this->assertSame(2, $added['rows']);
        $this->assertTrue(parity::enrolment_count_explained($baseline['rows'], $now['rows'], $added['rows']));
        $this->assertTrue(parity::enrolment_checksum_explained($baseline, $now, $added),
            'the baseline sum plus the two imported rows is the sum now');

        // A legacy row changed after the baseline (not the import's doing): the sum no longer adds up.
        $DB->set_field('user_enrolments', 'timeend', time() + 100 * DAYSECS, ['id' => $this->ueid($legacy->id, $course->id)]);
        $this->assertFalse(parity::enrolment_checksum_explained($baseline, $this->checksum(), $added));

        // Put it back: explained again.
        $DB->set_field('user_enrolments', 'timeend', 0, ['id' => $this->ueid($legacy->id, $course->id)]);
        $this->assertTrue(parity::enrolment_checksum_explained($baseline, $this->checksum(), $added));

        // A legacy row DELETED is a count that no longer fits.
        $legacyrow = $DB->get_record('user_enrolments', ['id' => $this->ueid($legacy->id, $course->id)], '*', MUST_EXIST);
        $DB->delete_records('user_enrolments', ['id' => $legacyrow->id]);
        $this->assertFalse(parity::enrolment_count_explained($baseline['rows'], $this->checksum()['rows'], $added['rows']));
        $this->assertFalse(parity::enrolment_checksum_explained($baseline, $this->checksum(), $added));
        $DB->import_record('user_enrolments', $legacyrow);

        // An imported row an administrator changed since: the map keeps no CRC per row, so the helper reads the live row and
        // the delta is still explained; a STALE figure (taken before the edit) is not. What the gate proves is that every row
        // the baseline had is exactly as it was.
        $DB->set_field('user_enrolments', 'status', 1, ['id' => $this->ueid($one->id, $course->id)]);
        $this->assertTrue(parity::enrolment_checksum_explained($baseline, $this->checksum(),
            parity::imported_enrolments(self::COLUMNS)));
        $this->assertFalse(parity::enrolment_checksum_explained($baseline, $this->checksum(), $added));
    }
}
