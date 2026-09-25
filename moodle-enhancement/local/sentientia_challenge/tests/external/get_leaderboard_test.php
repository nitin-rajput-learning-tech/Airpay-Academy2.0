<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge\external;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_challenge\challenge_engine;
use local_sentientia_challenge\leaderboard_manager;

/**
 * WS tests for get_leaderboard. Locks in tenant scoping (mine vs all).
 *
 * execute() is called with NAMED arguments. On 2026-05-22 (Goal A Bug #10)
 * `search` was prepended to the signature to match the shared datatable
 * client contract; these tests still passed arguments positionally in the
 * old order, so the challenge id landed in $search and 'mine' in
 * $challengeid (TypeError). Named arguments bind the way the test means.
 * The positional order that the WS dispatcher relies on is locked in
 * separately by test_execute_signature_matches_parameter_order().
 *
 * @package    local_sentientia_challenge
 * @category   test
 */
final class get_leaderboard_test extends \advanced_testcase {

    /**
     * core_external\external_api::call_external_function() validates the
     * request against execute_parameters() and then calls execute() with
     * array_values() of the result, i.e. by POSITION. If the key order of
     * execute_parameters() and the parameter order of execute() ever drift
     * apart, every web-service call binds the wrong value to the wrong
     * argument.
     */
    public function test_execute_signature_matches_parameter_order(): void {
        $declared = array_keys(get_leaderboard::execute_parameters()->keys);
        $signature = array_map(
            static fn(\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionMethod(get_leaderboard::class, 'execute'))->getParameters()
        );
        $this->assertSame($declared, $signature);
    }

    private function complete_course_for(int $userid): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($userid, $course->id, 'student');
        $now = time();
        $DB->insert_record('course_completions', (object) [
            'userid' => $userid, 'course' => $course->id,
            'timeenrolled' => $now, 'timestarted' => $now, 'timecompleted' => $now,
            'reaggregate' => 0,
        ]);
        return (int) $course->id;
    }

    public function test_view_capability_required(): void {
        global $DB;
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $userroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'user']);
        if ($userroleid) {
            role_change_permission($userroleid, $sysctx, 'local/sentientia_challenge:view', CAP_PROHIBIT);
        }
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        get_leaderboard::execute(challengeid: 0);
    }

    public function test_returns_top_for_challenge(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $cid = challenge_engine::create_challenge(['name' => 'L', 'shortname' => 'lll',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 1, 'pointsreward' => 50]);
        $this->complete_course_for((int) $u1->id);
        $this->complete_course_for((int) $u2->id);
        challenge_engine::join($cid, (int) $u1->id);
        challenge_engine::join($cid, (int) $u2->id);

        leaderboard_manager::recompute_challenge($cid);

        $r = get_leaderboard::execute(challengeid: $cid, tenantmode: 'mine',
            sort: 'points', sortdir: 'desc', page: 0, perpage: 25);
        $this->assertSame(2, $r['total']);
        // Each row has rank, points, fullname.
        foreach ($r['rows'] as $row) {
            $this->assertGreaterThanOrEqual(1, (int) $row['rank']);
            $this->assertGreaterThanOrEqual(0, (int) $row['points']);
            $this->assertNotEmpty($row['fullname']);
        }
    }

    public function test_aggregate_returns_zero_when_no_completions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $r = get_leaderboard::execute(challengeid: 0, tenantmode: 'mine',
            sort: 'points', sortdir: 'desc', page: 0, perpage: 25);
        $this->assertSame(0, $r['total']);
        $this->assertCount(0, $r['rows']);
    }

    public function test_filterstoolong_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // required_capability_exception is also a moodle_exception, so pin the
        // message too: this test must prove the filters guard fired.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_filterstoolong', 'local_sentientia_challenge'));
        get_leaderboard::execute(challengeid: 0, tenantmode: 'mine',
            sort: 'points', sortdir: 'desc', page: 0, perpage: 25,
            filters: str_repeat('x', 5000));
    }

    public function test_ismine_flag_set_for_caller_row(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = challenge_engine::create_challenge(['name' => 'M', 'shortname' => 'mmm',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 1, 'pointsreward' => 50]);
        $this->complete_course_for((int) $USER->id);
        challenge_engine::join($cid, (int) $USER->id);
        leaderboard_manager::recompute_challenge($cid);

        $r = get_leaderboard::execute(challengeid: $cid, tenantmode: 'mine',
            sort: 'points', sortdir: 'desc', page: 0, perpage: 25);
        $found = false;
        foreach ($r['rows'] as $row) {
            if ($row['userid'] === (int) $USER->id) {
                $this->assertTrue($row['ismine']);
                $found = true;
            } else {
                $this->assertFalse($row['ismine']);
            }
        }
        $this->assertTrue($found, 'caller must be in their own leaderboard');
    }
}
