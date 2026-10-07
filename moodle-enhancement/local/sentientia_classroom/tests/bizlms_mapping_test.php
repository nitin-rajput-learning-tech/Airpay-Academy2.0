<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_classroom\bizlms\mapping;

/**
 * The value rules of the BizLMS classroom import, which need no legacy table (ADR-032, mapping doc section 15).
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_sentientia_classroom\bizlms\mapping
 * @covers \local_sentientia_classroom\url_rule
 * @group local_sentientia_classroom
 * @group bizlms_import
 */
final class bizlms_mapping_test extends \basic_testcase {

    public function test_classroom_status_mapping_with_the_new_states(): void {
        $this->assertSame(1, mapping::classroom_status(1, true));
        $this->assertSame(0, mapping::classroom_status(3, true), 'cancelled');
        $this->assertSame(2, mapping::classroom_status(4, true), 'completed');
        $this->assertSame(5, mapping::classroom_status(0, true), 'BizLMS new becomes draft');
        $this->assertSame(6, mapping::classroom_status(2, true), 'BizLMS hold becomes on hold');
        $this->assertNull(mapping::classroom_status(9, true), 'a value BizLMS never used');
    }

    public function test_the_new_states_never_reuse_three_and_four(): void {
        // 3 and 4 are raw BizLMS values that an earlier copy script may have left in the table.
        foreach ([0, 1, 2, 3, 4] as $legacy) {
            $this->assertNotContains(mapping::classroom_status($legacy, true), [3, 4]);
        }
    }

    public function test_collapse_active_turns_new_and_hold_into_active(): void {
        $this->assertSame(1, mapping::classroom_status(0, false));
        $this->assertSame(1, mapping::classroom_status(2, false));
        $this->assertSame(0, mapping::classroom_status(3, false));
        $this->assertSame(2, mapping::classroom_status(4, false));
    }

    public function test_attendance_status_mapping(): void {
        $this->assertSame(1, mapping::attendance_status(1), 'present');
        $this->assertSame(0, mapping::attendance_status(2), 'absent');
        $this->assertNull(mapping::attendance_status(0), 'the unmarked placeholder');
        $this->assertNull(mapping::attendance_status(null));
    }

    public function test_session_end_rebuilds_a_finish_that_is_not_after_the_start(): void {
        $this->assertSame([200, ''], mapping::session_end(100, 200, 0));
        $this->assertSame([100 + 5400, 'derived_timestamp'], mapping::session_end(100, 50, 90));
        $this->assertSame([50, 'end_not_after_start'], mapping::session_end(100, 50, 0));
        // A session with no start (BizLMS datetimeknown = 0) has nothing to add a duration to.
        $this->assertSame([0, 'end_not_after_start'], mapping::session_end(0, 0, 90));
    }

    public function test_a_room_name_keeps_the_room_whole_and_cuts_the_institute(): void {
        $this->assertSame(['Inst - R1', false], mapping::room_location_name('Inst', 'R1'));
        [$name, $cut] = mapping::room_location_name(str_repeat('i', 225), str_repeat('r', 45));
        $this->assertTrue($cut);
        $this->assertSame(200, \core_text::strlen($name));
        $this->assertStringEndsWith(' - ' . str_repeat('r', 45), $name);
        $this->assertSame(['Room only', false], mapping::room_location_name('', 'Room only'));
        $this->assertSame(['Institute only', false], mapping::room_location_name('Institute only', ''));
        // The 254-character column of a session's location.
        [$name, $cut] = mapping::room_location_name(str_repeat('i', 225), 'R', 254);
        $this->assertFalse($cut);
        $this->assertSame(225 + 3 + 1, \core_text::strlen($name));
    }

    public function test_dense_positions_order_by_sort_order_then_id(): void {
        $this->assertSame([7 => 1, 9 => 2, 5 => 3], mapping::dense_positions([5 => 9, 7 => 5, 9 => 5]));
        $this->assertSame([], mapping::dense_positions([]));
    }

    /**
     * @dataProvider waitlist_provider
     * @param array $args enrolstatus, onroster, classroom status, closedstaywaiting, openstaywaiting
     * @param array $expected status and note
     */
    public function test_waitlist_status(array $args, array $expected): void {
        $this->assertSame($expected, mapping::waitlist_status(...$args));
    }

    /**
     * @return array
     */
    public static function waitlist_provider(): array {
        return [
            'moved to the roster' => [[1, false, 1, false, true], ['promoted', '']],
            'moved, classroom closed' => [[1, false, 4, false, true], ['promoted', '']],
            'waiting but on the roster' => [[0, true, 1, false, true], ['promoted', 'already_enrolled']],
            'waiting on a completed classroom' => [[0, false, 4, false, true], ['removed', 'classroom_closed']],
            'waiting on a cancelled classroom' => [[0, false, 3, false, true], ['removed', 'classroom_closed']],
            'closed classroom, owner keeps waiting' => [[0, false, 3, true, true], ['waiting', '']],
            'waiting on an active classroom' => [[0, false, 1, false, true], ['waiting', '']],
            'waiting on a draft classroom' => [[0, false, 0, false, true], ['waiting', '']],
            'open classroom, owner removes' => [[0, false, 0, false, false], ['removed', 'kept_out_of_the_queue']],
            'deleted learner, place would stay waiting' => [[0, false, 1, false, true, true], ['removed', 'user_deleted']],
            'deleted learner, closed classroom the owner keeps waiting' => [[0, false, 3, true, true, true],
                ['removed', 'user_deleted']],
            'deleted learner, already moved to the roster' => [[1, false, 1, false, true, true], ['promoted', '']],
            'deleted learner, classroom closed' => [[0, false, 4, false, true, true], ['removed', 'classroom_closed']],
            'active learner, flag spelled out' => [[0, false, 1, false, true, false], ['waiting', '']],
        ];
    }

    public function test_waitlist_reason_text(): void {
        $this->assertNull(mapping::waitlist_reason(''));
        $this->assertSame('Imported from BizLMS: already enrolled', mapping::waitlist_reason('already_enrolled'));
        $this->assertSame('Imported from BizLMS: classroom closed before promotion', mapping::waitlist_reason('classroom_closed'));
        $this->assertSame('Imported from BizLMS: the learner no longer exists', mapping::waitlist_reason('user_deleted'));
    }

    public function test_path_helpers(): void {
        $this->assertSame([1, 5], mapping::path_segments('/1/5'));
        $this->assertSame(5, mapping::costcenter_of_path('/1/5'));
        $this->assertSame(5, mapping::department_of_path('/1/5'));
        $this->assertSame(0, mapping::department_of_path('/1'));
        $this->assertSame(1, mapping::costcenter_of_path('/1'));
        $this->assertSame(0, mapping::costcenter_of_path(null));
        $this->assertSame([], mapping::path_segments(''));
    }

    public function test_time_and_value_helpers(): void {
        $row = (object) ['timecreated' => 5, 'timemodified' => 1, 'x' => '', 'n' => 'NULL', 'y' => ' text '];
        $this->assertSame(5, mapping::time_modified($row, 5), 'the BizLMS default of 1 means never modified');
        $row->timemodified = 9;
        $this->assertSame(9, mapping::time_modified($row, 5));
        $this->assertSame(0, mapping::int($row, 'x'));
        $this->assertSame(7, mapping::int($row, 'missing', 7));
        $this->assertTrue(mapping::missing($row, 'x'));
        $this->assertTrue(mapping::missing($row, 'nothere'));
        $this->assertSame('', mapping::real_text($row, 'n'), "BizLMS's literal NULL default is no value");
        $this->assertSame('text', mapping::text($row, 'y'));
        $this->assertNull(mapping::date_or_null((object) ['d' => 0], 'd'));
        $this->assertSame(42, mapping::date_or_null((object) ['d' => '42'], 'd'));
        $this->assertNull(mapping::actor((object) ['u' => 0], 'u'));
        $this->assertSame(3, mapping::actor((object) ['u' => '3'], 'u'));
        $this->assertSame(mapping::CAPACITY_MAX, mapping::clamp(99999999, mapping::CAPACITY_MAX));
        $this->assertSame(0, mapping::clamp(-4, 10));
    }

    public function test_the_link_rule_is_the_rule_a_typed_link_goes_through(): void {
        $this->assertSame('https://www.zoom.us/j/1', url_rule::sanitize_legacy('www.zoom.us/j/1'));
        $this->assertSame('https://teams.example/x', url_rule::sanitize_legacy(' https://teams.example/x '));
        $this->assertNull(url_rule::sanitize_legacy('ftp://x'));
        $this->assertNull(url_rule::sanitize_legacy('javascript:alert(1)'));
        $this->assertNull(url_rule::sanitize_legacy('/relative/path'));
        $this->assertNull(url_rule::sanitize_legacy(''));
        $this->assertNull(url_rule::sanitize_legacy(null));
        $long = 'https://example.org/' . str_repeat('a', 2000);
        $this->assertSame(1024, \core_text::strlen((string) url_rule::sanitize_legacy($long)));
        // session_manager applies the very same rule to a typed link.
        $this->assertSame(url_rule::sanitize('https://a.b/c'), session_manager::sanitize_url('https://a.b/c'));
        $this->assertNull(session_manager::sanitize_url('javascript:alert(1)'));
    }
}
