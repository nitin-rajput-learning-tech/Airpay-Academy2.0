<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_programs\bizlms\rules;

/**
 * The BizLMS program rules the importer translates (ADR-032, mapping doc section 16), as values in and values
 * out: no database, no Moodle.
 *
 * @package    local_sentientia_programs
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_programs\bizlms\rules
 *
 * @group local_sentientia_programs
 * @group bizlms_import
 */
final class bizlms_rules_test extends \basic_testcase {

    /**
     * @param string $tracking
     * @param string $ids
     * @return \stdClass
     */
    private function program_criteria(string $tracking, string $ids = ''): \stdClass {
        return (object) ['leveltracking' => $tracking, 'levelids' => $ids];
    }

    /**
     * @param string $tracking
     * @param string $ids
     * @return \stdClass
     */
    private function level_criteria(string $tracking, string $ids = ''): \stdClass {
        return (object) ['coursetracking' => $tracking, 'courseids' => $ids];
    }

    public function test_csv_ids_trims_drops_junk_and_keeps_order(): void {
        $this->assertSame([12, 13], rules::csv_ids('12, 13'));
        $this->assertSame([4, 5], rules::csv_ids('4,5'));
        $this->assertSame([7, 3], rules::csv_ids(' 7 ,, x, 0, -2, 3 ,7'), 'junk, zero, negatives and repeats go');
        $this->assertSame([], rules::csv_ids(''));
        $this->assertSame([], rules::csv_ids(null));
    }

    public function test_program_completion_required_is_zero_only_for_or(): void {
        $this->assertSame(1, rules::program_completion_required(null), 'no criteria row: every level');
        $this->assertSame(1, rules::program_completion_required($this->program_criteria('ALL')));
        $this->assertSame(1, rules::program_completion_required($this->program_criteria('AND', '1,2')));
        $this->assertSame(1, rules::program_completion_required($this->program_criteria('')));
        $this->assertSame(0, rules::program_completion_required($this->program_criteria('OR', '1,2')));
        $this->assertSame(0, rules::program_completion_required($this->program_criteria(' or ')), 'case and spaces');
    }

    public function test_level_required_follows_the_program_criteria_list(): void {
        $this->assertSame(1, rules::level_required(null, 101));
        $this->assertSame(1, rules::level_required($this->program_criteria('ALL', '101'), 999), 'ALL ignores the list');
        $this->assertSame(1, rules::level_required($this->program_criteria('AND', ''), 999), 'empty list: no filter');
        $this->assertSame(1, rules::level_required($this->program_criteria('AND', '101,103'), 103));
        $this->assertSame(0, rules::level_required($this->program_criteria('AND', '101,103'), 108));
        $this->assertSame(1, rules::level_required($this->program_criteria('OR', '101, 103'), 101));
        $this->assertSame(0, rules::level_required($this->program_criteria('OR', '101, 103'), 108));
    }

    public function test_level_rule_is_any_only_for_or(): void {
        $this->assertSame('all', rules::level_rule(null));
        $this->assertSame('all', rules::level_rule($this->level_criteria('ALL')));
        $this->assertSame('all', rules::level_rule($this->level_criteria('AND', '1,2')));
        $this->assertSame('all', rules::level_rule($this->level_criteria('')));
        $this->assertSame('any', rules::level_rule($this->level_criteria('OR', '1,2')));
    }

    public function test_course_mandatory_follows_the_level_criteria_list(): void {
        $this->assertSame(1, rules::course_mandatory(null, 5));
        $this->assertSame(1, rules::course_mandatory($this->level_criteria('ALL', '5'), 9));
        $this->assertSame(1, rules::course_mandatory($this->level_criteria('OR', ''), 9), 'OR with no list: every course');
        $this->assertSame(1, rules::course_mandatory($this->level_criteria('OR', '12, 13'), 13));
        $this->assertSame(0, rules::course_mandatory($this->level_criteria('OR', '12, 13'), 14));
        $this->assertSame(0, rules::course_mandatory($this->level_criteria('AND', '12'), 13));
        $this->assertSame([12, 13], rules::qualifying_courses($this->level_criteria('OR', '12, 13'), [12, 13, 14]));
        $this->assertSame([12, 13, 14], rules::qualifying_courses(null, [12, 13, 14]));
    }

    public function test_program_status_never_draft_unless_the_owner_chose_it(): void {
        $this->assertSame([rules::PROGRAM_ACTIVE, false], rules::program_status(0, 1, 'archived'));
        $this->assertSame([rules::PROGRAM_ACTIVE, false], rules::program_status(0, null, 'archived'), 'NULL visible is on');
        $this->assertSame([rules::PROGRAM_ARCHIVED, false], rules::program_status(0, 0, 'archived'), 'switched off');
        $this->assertSame([rules::PROGRAM_DRAFT, false], rules::program_status(0, 0, 'draft'), 'owner chose Draft');
        $this->assertSame([rules::PROGRAM_ARCHIVED, false], rules::program_status(2, 1, 'draft'), 'status 2 is always archived');
        $this->assertSame([rules::PROGRAM_ACTIVE, true], rules::program_status(7, 1, 'archived'), 'unknown status: by visible, reported');
        $this->assertSame([rules::PROGRAM_ARCHIVED, true], rules::program_status(7, 0, 'archived'));
    }

    public function test_level_completion_date_takes_first_or_last_course_and_never_passes_the_stored_date(): void {
        // All courses: the last one completes the level; any course: the first one.
        $this->assertSame(300, rules::level_completion_date([100, 300, 200], rules::RULE_ALL, 0));
        $this->assertSame(100, rules::level_completion_date([100, 300, 200], rules::RULE_ANY, 0));
        // A course re-completed after a reset is later than the real level completion: the stored date caps it.
        $this->assertSame(350, rules::level_completion_date([700], rules::RULE_ALL, 350));
        // No course completion on record: the stored date, else nothing.
        $this->assertSame(360, rules::level_completion_date([], rules::RULE_ALL, 360));
        $this->assertNull(rules::level_completion_date([], rules::RULE_ALL, 0));
        // Zero and negative times are not completions.
        $this->assertSame(360, rules::level_completion_date([0, -5], rules::RULE_ANY, 360));
    }

    public function test_current_level_is_the_first_without_a_stored_completion(): void {
        $levels = [11, 12, 13];
        $this->assertNull(rules::current_level(rules::USER_ENROLLED, $levels, []), 'not started: none');
        $this->assertNull(rules::current_level(rules::USER_INPROGRESS, [], []), 'no levels: none');
        $this->assertSame(11, rules::current_level(rules::USER_INPROGRESS, $levels, []));
        $this->assertSame(12, rules::current_level(rules::USER_INPROGRESS, $levels, [11 => true]));
        $this->assertSame(11, rules::current_level(rules::USER_INPROGRESS, $levels, [12 => true]), 'a gap counts');
        $this->assertSame(13, rules::current_level(rules::USER_INPROGRESS, $levels, [11 => true, 12 => true, 13 => true]));
        $this->assertSame(13, rules::current_level(rules::USER_COMPLETED, $levels, []), 'completed: the last level');
    }

    public function test_flag_and_time_or(): void {
        $this->assertSame(0, rules::flag(null));
        $this->assertSame(0, rules::flag(0));
        $this->assertSame(1, rules::flag(1));
        $this->assertSame(1, rules::flag('1'));
        $this->assertSame(500, rules::time_or(500, 9));
        $this->assertSame(9, rules::time_or(0, 9));
        $this->assertSame(9, rules::time_or(null, 9));
    }
}
