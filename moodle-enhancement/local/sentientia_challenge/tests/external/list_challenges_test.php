<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge\external;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_challenge\challenge_engine;

/**
 * WS tests for list_challenges. Locks in capability gate, action HTML
 * emission, and tenant-aware row visibility.
 */
final class list_challenges_test extends \advanced_testcase {

    public function test_view_capability_required(): void {
        $this->resetAfterTest();

        // Make a logged-in user with NO archetype role at all.
        $u = $this->getDataGenerator()->create_user();
        // Strip the default authenticated role's :view to truly remove it.
        $sysctx = \context_system::instance();
        // Default role for authenticated users is 'user' which has :view by archetype.
        // Override to deny.
        $userroleid = (int) $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'user']);
        if ($userroleid) {
            role_change_permission($userroleid, $sysctx, 'local/sentientia_challenge:view', CAP_PROHIBIT);
        }
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_challenges::execute();
    }

    public function test_admin_sees_all_active(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        challenge_engine::create_challenge(['name' => 'A', 'shortname' => 'la',
            'status' => challenge_engine::STATUS_ACTIVE]);
        challenge_engine::create_challenge(['name' => 'B', 'shortname' => 'lb',
            'status' => challenge_engine::STATUS_ACTIVE]);
        challenge_engine::create_challenge(['name' => 'D', 'shortname' => 'ld',
            'status' => challenge_engine::STATUS_DRAFT]);

        $r = list_challenges::execute('', 'active', 'timecreated', 'desc', 0, 25);
        $this->assertSame(2, $r['total']);
        foreach ($r['rows'] as $row) {
            $this->assertSame(challenge_engine::STATUS_ACTIVE, $row['status']);
        }
    }

    public function test_admin_gets_all_action_buttons(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        challenge_engine::create_challenge(['name' => 'A', 'shortname' => 'aaa',
            'status' => challenge_engine::STATUS_ACTIVE]);

        $r = list_challenges::execute('', 'active', 'timecreated', 'desc', 0, 25);
        $row = $r['rows'][0];
        // Admin has both manage + participate caps.
        $this->assertStringContainsString('edit-challenge',   $row['actions']);
        $this->assertStringContainsString('delete-challenge', $row['actions']);
        $this->assertStringContainsString('join-challenge',   $row['actions']);
        $this->assertStringContainsString('fa-trophy',        $row['actions'],
            'leaderboard link icon must always be present');
    }

    public function test_search_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        challenge_engine::create_challenge(['name' => 'Q1 Compliance', 'shortname' => 'q1c',
            'status' => challenge_engine::STATUS_ACTIVE]);
        challenge_engine::create_challenge(['name' => 'Onboarding', 'shortname' => 'onb',
            'status' => challenge_engine::STATUS_ACTIVE]);

        $r = list_challenges::execute('compliance', 'active', 'timecreated', 'desc', 0, 25);
        $this->assertSame(1, $r['total']);
    }

    public function test_filterstoolong_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        list_challenges::execute('', 'all', 'timecreated', 'desc', 0, 25, str_repeat('x', 5000));
    }

    public function test_caller_already_joined_shows_leave_button(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cid = challenge_engine::create_challenge(['name' => 'J', 'shortname' => 'jj',
            'status' => challenge_engine::STATUS_ACTIVE]);
        global $USER;
        challenge_engine::join($cid, (int) $USER->id);

        $r = list_challenges::execute('', 'active', 'timecreated', 'desc', 0, 25);
        $row = $r['rows'][0];
        $this->assertTrue($row['joined']);
        $this->assertStringContainsString('leave-challenge', $row['actions']);
        $this->assertStringNotContainsString('join-challenge', $row['actions']);
    }
}
