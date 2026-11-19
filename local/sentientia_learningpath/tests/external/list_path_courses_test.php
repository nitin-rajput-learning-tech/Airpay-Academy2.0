<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacymap;

/**
 * Post-demo quick wins (2026-11-19, plan 2.4) on the Courses tab of a learning path.
 *
 * Locks in:
 *  - "#" (position) is the course's place on the path counted from 1, dense over sortorder gaps, and the same
 *    whatever the table is sorted or searched by. The stored sortorder starts at 0 and is still returned.
 *  - A row the BizLMS import created is flagged imported and gets a lock action that explains why it stays,
 *    never the remove action; a row made on the site keeps the remove action.
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @covers     \local_sentientia_learningpath\external\list_path_courses
 * @group      local_sentientia_learningpath
 */
final class list_path_courses_test extends \advanced_testcase {

    private function seed_path(): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => 'Path', 'description' => '', 'costcenterid' => 0, 'open_path' => '/1', 'status' => 1,
            'visible' => 1, 'timecreated' => 100, 'timemodified' => 100,
        ]);
    }

    /**
     * Put a course on the path with a given stored sortorder.
     *
     * @param int $pathid
     * @param \stdClass $course
     * @param int $sortorder
     * @return int The row id.
     */
    private function place(int $pathid, \stdClass $course, int $sortorder): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath_courses', (object) [
            'pathid' => $pathid, 'courseid' => $course->id, 'sortorder' => $sortorder, 'mandatory' => 1,
            'timecreated' => 100, 'timemodified' => 100,
        ]);
    }

    /**
     * Record that the import created a course row.
     *
     * @param int $rowid
     * @return void
     */
    private function imported(int $rowid): void {
        global $DB;
        $DB->insert_record(legacymap::TABLE, (object) ['feature' => 'learningplan',
            'sourcetable' => 'test_local_learningplan_courses', 'sourceid' => $rowid, 'subkey' => '',
            'targettable' => 'local_sentientia_learningpath_courses', 'targetid' => $rowid, 'outcome' => 'imported',
            'reason' => null, 'detail' => null, 'runid' => 0, 'timecreated' => time()]);
    }

    /**
     * @param array $result list_path_courses::execute() result.
     * @return array<string, array> Rows keyed by course full name.
     */
    private function by_name(array $result): array {
        $rows = [];
        foreach ($result['rows'] as $row) {
            $rows[$row['name']] = $row;
        }
        return $rows;
    }

    public function test_position_counts_from_one_whatever_the_sort_or_search(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_path();
        $gen = $this->getDataGenerator();
        // Stored order 0, 5, 9: the first course used to show as "#0", and a removal left gaps.
        $this->place($pid, $gen->create_course(['fullname' => 'Charlie']), 0);
        $this->place($pid, $gen->create_course(['fullname' => 'Alpha']), 5);
        $this->place($pid, $gen->create_course(['fullname' => 'Bravo']), 9);

        $result = list_path_courses::execute($pid);
        $this->assertSame([1, 2, 3], array_column($result['rows'], 'position'), 'path order, from 1, no gaps');
        $this->assertSame([0, 5, 9], array_column($result['rows'], 'sortorder'), 'the stored order is still returned');

        $byname = list_path_courses::execute($pid, '', 'fullname', 'asc');
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], array_column($byname['rows'], 'name'));
        $this->assertSame([2, 3, 1], array_column($byname['rows'], 'position'), 'the place does not follow the sort');

        $search = list_path_courses::execute($pid, 'Bravo');
        $this->assertCount(1, $search['rows']);
        $this->assertSame(3, $search['rows'][0]['position'], 'nor the search');

        $paged = list_path_courses::execute($pid, '', 'sortorder', 'asc', 1, 2);
        $this->assertSame([3], array_column($paged['rows'], 'position'), 'nor the page');
    }

    public function test_positions_skip_rows_whose_course_is_gone(): void {
        global $DB;
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $gen = $this->getDataGenerator();
        $first = $this->place($pid, $gen->create_course(), 0);
        // A row pointing at a course that no longer exists is not listed, so it takes no place.
        $DB->insert_record('local_sentientia_learningpath_courses', (object) ['pathid' => $pid, 'courseid' => 999999,
            'sortorder' => 1, 'mandatory' => 1, 'timecreated' => 100, 'timemodified' => 100]);
        $third = $this->place($pid, $gen->create_course(), 2);

        $this->assertSame([$first => 1, $third => 2], list_path_courses::positions($pid));
        $this->assertSame([], list_path_courses::positions($pid + 1), 'another path has none');
    }

    public function test_an_imported_row_shows_a_lock_and_never_the_remove_action(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_path();
        $gen = $this->getDataGenerator();
        $this->imported($this->place($pid, $gen->create_course(['fullname' => 'From BizLMS']), 0));
        $this->place($pid, $gen->create_course(['fullname' => 'Made here']), 1);

        $rows = $this->by_name(list_path_courses::execute($pid));

        $imported = $rows['From BizLMS'];
        $this->assertTrue($imported['imported']);
        $this->assertStringContainsString('data-action="imported-course-info"', $imported['actions']);
        $this->assertStringNotContainsString('unassign-course', $imported['actions']);
        $locked = s(get_string('imported_course_locked', 'local_sentientia_learningpath'));
        $this->assertStringContainsString('aria-label="' . $locked . '"', $imported['actions']);

        $native = $rows['Made here'];
        $this->assertFalse($native['imported']);
        $this->assertStringContainsString('data-action="unassign-course"', $native['actions']);
        $this->assertStringNotContainsString('imported-course-info', $native['actions']);
        $remove = s(get_string('remove_from_path', 'local_sentientia_learningpath'));
        $this->assertStringContainsString('title="' . $remove . '"', $native['actions'], 'the label is a string');
    }

    public function test_an_adopted_row_counts_as_imported_and_a_skipped_one_does_not(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_path();
        $gen = $this->getDataGenerator();
        $adopted = $this->place($pid, $gen->create_course(['fullname' => 'Adopted']), 0);
        $skipped = $this->place($pid, $gen->create_course(['fullname' => 'Skipped']), 1);
        foreach ([[$adopted, 'adopted'], [$skipped, 'skipped']] as [$rowid, $outcome]) {
            $DB->insert_record(legacymap::TABLE, (object) ['feature' => 'learningplan',
                'sourcetable' => 'test_' . $outcome, 'sourceid' => $rowid, 'subkey' => '',
                'targettable' => 'local_sentientia_learningpath_courses', 'targetid' => $rowid, 'outcome' => $outcome,
                'reason' => null, 'detail' => null, 'runid' => 0, 'timecreated' => time()]);
        }

        $rows = $this->by_name(list_path_courses::execute($pid));
        $this->assertTrue($rows['Adopted']['imported'], 'provenance::is_imported() treats adopted rows as imported');
        $this->assertFalse($rows['Skipped']['imported']);
    }

    public function test_the_result_matches_the_declared_return_structure(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_path();
        $this->imported($this->place($pid, $this->getDataGenerator()->create_course(), 0));

        $result = list_path_courses::execute($pid);
        $clean = \core_external\external_api::clean_returnvalue(list_path_courses::execute_returns(), $result);
        $this->assertSame(1, $clean['rows'][0]['position']);
        $this->assertTrue($clean['rows'][0]['imported']);
    }
}
