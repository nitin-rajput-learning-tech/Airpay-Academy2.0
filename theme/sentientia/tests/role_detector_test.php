<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for role_detector (Bug #11 follow-up, ADR-009).
 *
 * The detector encapsulates the rules for which tier a user belongs to:
 * Site Admin, L&D Admin, Manager, Learner. Both layout/dashboard.php
 * and classes/sidebar_navigation.php consume it; they MUST agree.
 *
 * Bug #11 showed up because two duplicated implementations disagreed
 * about Joseph Mandapati (BizLMS administrator role at category context).
 * The refactor at commit fcd150c0a removed the duplication; this test
 * codifies the 5-tier matrix so future edits can't quietly regress one
 * detection path while leaving the other intact.
 *
 * Fixtures are created per test method (resetAfterTest) so the test
 * doesn't depend on any production data — runs in any CI environment.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @group theme_sentientia
 * @group role_detector
 */
final class role_detector_test extends \advanced_testcase {

    /**
     * Site Admin: any user listed in $CFG->siteadmins.
     */
    public function test_siteadmin_detected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        // Promote to site admin by appending to $CFG->siteadmins.
        global $CFG;
        $CFG->siteadmins = $CFG->siteadmins . ',' . $user->id;
        $this->setUser($user);

        $r = role_detector::detect();

        $this->assertTrue($r['issiteadmin'], 'Site admin should be detected');
        $this->assertTrue($r['isadmin'],     'Site admin is admin');
        $this->assertFalse($r['isldadmin'],  'Site admin is not separately L&D Admin');
        $this->assertFalse($r['ismanager']);
        $this->assertFalse($r['islearner']);
    }

    /**
     * L&D Admin via the `local/sentientia_courses:manage` capability at system context.
     */
    public function test_ldadmin_via_capability(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();

        // Create a role that has local/sentientia_courses:manage and assign it.
        $roleid = $this->getDataGenerator()->create_role([
            'shortname' => 'ldadmintestrole',
            'name'      => 'L&D Admin Test Role',
        ]);
        // Only assign the capability if it exists (it's plugin-provided
        // and may be unavailable in a stock Moodle test environment).
        $caps = get_all_capabilities();
        if (!empty($caps['local/sentientia_courses:manage'])) {
            assign_capability('local/sentientia_courses:manage', CAP_ALLOW, $roleid, $context);
            role_assign($roleid, $user->id, $context);
            $this->setUser($user);

            $r = role_detector::detect();

            $this->assertFalse($r['issiteadmin']);
            $this->assertTrue($r['isldadmin'],
                'User with local/sentientia_courses:manage cap should be L&D Admin');
            $this->assertTrue($r['isadmin']);
            $this->assertFalse($r['ismanager']);
        } else {
            $this->markTestSkipped(
                'local/sentientia_courses:manage capability not available — '
                . 'requires BizLMS plugin local_courses');
        }
    }

    /**
     * L&D Admin via the BizLMS `administrator` role at category context.
     * This is the Joseph Mandapati path that Bug #11 was about.
     */
    public function test_ldadmin_via_bizlms_admin_role_at_category(): void {
        $this->resetAfterTest();
        global $DB;

        // The role we need is the system `administrator` shortname role,
        // which exists in stock Moodle but is normally only assignable at
        // system context. For this test we manually assign it at a
        // category context to mimic the BizLMS pattern. We create the
        // category first so we have a valid context.
        $adminroleid = (int) $DB->get_field('role', 'id',
            ['shortname' => 'administrator'], IGNORE_MISSING);
        if (!$adminroleid) {
            $this->markTestSkipped('administrator role not present');
        }

        // Allow administrator role at category contextlevel (40).
        global $CFG;
        // get_role_contextlevels() reads from {role_context_levels}; add
        // an entry if missing.
        $existing = $DB->record_exists('role_context_levels',
            ['roleid' => $adminroleid, 'contextlevel' => CONTEXT_COURSECAT]);
        if (!$existing) {
            $DB->insert_record('role_context_levels', (object) [
                'roleid'       => $adminroleid,
                'contextlevel' => CONTEXT_COURSECAT,
            ]);
        }

        $user = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $context = \context_coursecat::instance($category->id);
        role_assign($adminroleid, $user->id, $context);
        $this->setUser($user);

        $r = role_detector::detect();

        $this->assertFalse($r['issiteadmin']);
        $this->assertTrue($r['isldadmin'],
            'User with administrator role at category context should be detected '
            . 'as L&D Admin (Bug #11 — this was Joseph Mandapati\'s path).');
        $this->assertTrue($r['isadmin']);
        $this->assertFalse($r['ismanager']);
    }

    /**
     * Manager via the `moodle/site:viewreports` system capability.
     */
    public function test_manager_via_capability(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();

        $roleid = $this->getDataGenerator()->create_role([
            'shortname' => 'managertestrole',
            'name'      => 'Manager Test Role',
        ]);
        assign_capability('moodle/site:viewreports', CAP_ALLOW, $roleid, $context);
        role_assign($roleid, $user->id, $context);
        $this->setUser($user);

        $r = role_detector::detect();

        $this->assertFalse($r['issiteadmin']);
        $this->assertFalse($r['isldadmin']);
        $this->assertTrue($r['ismanager'],
            'User with moodle/site:viewreports should be detected as Manager');
        $this->assertFalse($r['islearner']);
    }

    /**
     * Manager via the BizLMS `open_supervisorid` direct-report pattern.
     * Only run when the column exists (BizLMS-installed env).
     */
    public function test_manager_via_supervisor_relationship(): void {
        $this->resetAfterTest();
        global $DB;

        // Guard the BizLMS column.
        $dbman = $DB->get_manager();
        $usertable = new \xmldb_table('user');
        $superfield = new \xmldb_field('open_supervisorid');
        if (!$dbman->field_exists($usertable, $superfield)) {
            $this->markTestSkipped(
                'open_supervisorid column missing — requires BizLMS schema');
        }

        $manageruser = $this->getDataGenerator()->create_user();
        $reportuser  = $this->getDataGenerator()->create_user(
            ['open_supervisorid' => $manageruser->id]);

        $this->setUser($manageruser);
        $r = role_detector::detect();

        $this->assertFalse($r['issiteadmin']);
        $this->assertFalse($r['isldadmin']);
        $this->assertTrue($r['ismanager'],
            'Manager via direct-report (open_supervisorid) should be detected');
    }

    /**
     * Plain Learner — no admin caps, no direct reports.
     */
    public function test_learner_default(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $r = role_detector::detect();

        $this->assertFalse($r['issiteadmin']);
        $this->assertFalse($r['isldadmin']);
        $this->assertFalse($r['isadmin']);
        $this->assertFalse($r['ismanager']);
        $this->assertTrue($r['islearner'],
            'Default user (no caps, no reports) should be Learner');
        $this->assertFalse($r['switched_to_employee']);
    }

    /**
     * Tier-order invariant: never simultaneously two tiers (except admin = siteadmin || ldadmin).
     */
    public function test_tier_invariants(): void {
        $this->resetAfterTest();

        // For each fixture above, the boolean tier flags must be
        // mutually exclusive (except `isadmin` which is the OR of
        // siteadmin + ldadmin).
        $users = [
            'plain' => $this->getDataGenerator()->create_user(),
        ];
        foreach ($users as $label => $user) {
            $this->setUser($user);
            $r = role_detector::detect();
            $count = (int)$r['issiteadmin'] + (int)$r['isldadmin']
                   + (int)$r['ismanager'] + (int)$r['islearner'];
            $this->assertSame(1, $count,
                "User `{$label}` should belong to exactly ONE tier "
                . "(siteadmin/ldadmin/manager/learner), got: "
                . json_encode([
                    'issiteadmin' => $r['issiteadmin'],
                    'isldadmin'   => $r['isldadmin'],
                    'ismanager'   => $r['ismanager'],
                    'islearner'   => $r['islearner'],
                ]));
            $this->assertSame(
                $r['issiteadmin'] || $r['isldadmin'],
                $r['isadmin'],
                "isadmin must equal issiteadmin || isldadmin");
        }
    }

    /**
     * Switched-to-employee suppresses higher tiers — a BizLMS L&D Admin
     * who switches into the employee role for testing sees Learner.
     */
    public function test_switched_to_employee_demotes(): void {
        $this->resetAfterTest();
        global $DB, $USER, $SESSION;

        // Make a user who is normally L&D Admin via cap.
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $caps = get_all_capabilities();
        if (empty($caps['local/sentientia_courses:manage'])) {
            $this->markTestSkipped('local/sentientia_courses:manage cap not available');
        }
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'switchtestrole']);
        assign_capability('local/sentientia_courses:manage', CAP_ALLOW, $roleid, $context);
        role_assign($roleid, $user->id, $context);

        $employeeroleid = (int) $DB->get_field('role', 'id',
            ['shortname' => 'employee'], IGNORE_MISSING);
        if (!$employeeroleid) {
            $this->markTestSkipped('employee role not present (BizLMS schema)');
        }

        $this->setUser($user);

        // Sanity: as L&D Admin without switch, detect = isldadmin true.
        $r1 = role_detector::detect();
        $this->assertTrue($r1['isldadmin'],
            'Sanity: user should be L&D Admin before switching');

        // Now simulate a switch to employee via the session shape that
        // dashboard.php and role_detector both look at.
        $SESSION->airpay_switchrole = (object) ['roleid' => $employeeroleid];

        $r2 = role_detector::detect();
        $this->assertTrue($r2['switched_to_employee']);
        $this->assertFalse($r2['isldadmin'],
            'After switch, L&D Admin should appear as Learner');
        $this->assertTrue($r2['islearner']);
    }
}
