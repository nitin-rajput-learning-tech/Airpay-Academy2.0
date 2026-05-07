<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge\external;

defined('MOODLE_INTERNAL') || die();

use local_airpay_challenge\challenge_engine;

/**
 * WS tests for join_challenge + leave_challenge.
 *
 * @package    local_airpay_challenge
 * @category   test
 */
final class join_challenge_test extends \advanced_testcase {

    public function test_participate_capability_required(): void {
        global $DB;
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();
        // Block participate explicitly.
        $sysctx = \context_system::instance();
        $userroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'user']);
        if ($userroleid) {
            role_change_permission($userroleid, $sysctx, 'local/airpay_challenge:participate', CAP_PROHIBIT);
        }
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        join_challenge::execute(1);
    }

    public function test_admin_can_join(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $cid = challenge_engine::create_challenge(['name' => 'C', 'shortname' => 'jc',
            'status' => challenge_engine::STATUS_ACTIVE]);

        $r = join_challenge::execute($cid);
        $this->assertGreaterThan(0, $r['attemptid']);

        $this->assertTrue($DB->record_exists('local_airpay_challenge_attempts',
            ['challengeid' => $cid, 'userid' => $USER->id]));
    }

    public function test_double_join_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $cid = challenge_engine::create_challenge(['name' => 'C', 'shortname' => 'jc2',
            'status' => challenge_engine::STATUS_ACTIVE]);
        join_challenge::execute($cid);
        $this->expectException(\moodle_exception::class);
        join_challenge::execute($cid);
    }

    public function test_join_inactive_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $cid = challenge_engine::create_challenge(['name' => 'C', 'shortname' => 'jc3',
            'status' => challenge_engine::STATUS_DRAFT]);
        $this->expectException(\moodle_exception::class);
        join_challenge::execute($cid);
    }

    public function test_leave_via_ws(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $cid = challenge_engine::create_challenge(['name' => 'C', 'shortname' => 'jc4',
            'status' => challenge_engine::STATUS_ACTIVE]);
        join_challenge::execute($cid);
        $this->assertTrue($DB->record_exists('local_airpay_challenge_attempts',
            ['challengeid' => $cid, 'userid' => $USER->id]));

        $r = leave_challenge::execute($cid);
        $this->assertTrue($r['left']);
        $this->assertFalse($DB->record_exists('local_airpay_challenge_attempts',
            ['challengeid' => $cid, 'userid' => $USER->id]));
    }
}
