<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace enrol_sentientiasub;

defined('MOODLE_INTERNAL') || die();

/**
 * Lifecycle state-machine + enrolment side-effect tests for subscription_manager.
 *
 * Covers the pending -> active -> suspended/cancelled transitions and that
 * scope=course activation/suspension/cancellation drive the underlying course
 * enrolment (active / suspended / unenrolled). Payment is NOT exercised here —
 * the manager is deliberately decoupled from it (ADR-023 increment 2).
 *
 * @package enrol_sentientiasub
 * @covers \enrol_sentientiasub\subscription_manager
 */
final class subscription_manager_test extends \advanced_testcase {

    /** @return array{0:\stdClass,1:\stdClass,2:int,3:\context_course} course, user, enrolid, context */
    private function make_instance(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $plugin = enrol_get_plugin('sentientiasub');
        $enrolid = $plugin->add_instance($course, ['roleid' => $roleid, 'status' => ENROL_INSTANCE_ENABLED]);
        return [$course, $user, (int) $enrolid, \context_course::instance($course->id)];
    }

    private function new_sub(int $enrolid, int $userid, int $courseid): int {
        return subscription_manager::create($enrolid, $userid, subscription_manager::SCOPE_COURSE,
            $courseid, 199.00, 'INR', 'monthly', 77);
    }

    public function test_create_makes_pending(): void {
        $this->resetAfterTest();
        [$course, $user, $enrolid] = $this->make_instance();
        $subid = $this->new_sub($enrolid, $user->id, $course->id);

        $sub = subscription_manager::get($subid);
        $this->assertNotNull($sub);
        $this->assertSame(subscription_manager::STATUS_PENDING, $sub->status);
        $this->assertEquals($user->id, $sub->userid);
        $this->assertEquals(77, $sub->costcenterid);
        $this->assertEquals('monthly', $sub->billingperiod);
        $this->assertFalse(subscription_manager::is_active($subid));
    }

    public function test_activate_grants_course_enrolment(): void {
        $this->resetAfterTest();
        [$course, $user, $enrolid, $ctx] = $this->make_instance();
        $subid = $this->new_sub($enrolid, $user->id, $course->id);
        $next = time() + 30 * 86400;

        subscription_manager::activate($subid, $next);

        $sub = subscription_manager::get($subid);
        $this->assertSame(subscription_manager::STATUS_ACTIVE, $sub->status);
        $this->assertGreaterThan(0, $sub->startedts);
        $this->assertEquals($next, $sub->nextchargets);
        $this->assertTrue(subscription_manager::is_active($subid));
        $this->assertTrue(is_enrolled($ctx, $user->id, '', true)); // onlyactive
    }

    public function test_suspend_suspends_enrolment(): void {
        $this->resetAfterTest();
        [$course, $user, $enrolid, $ctx] = $this->make_instance();
        $subid = $this->new_sub($enrolid, $user->id, $course->id);
        subscription_manager::activate($subid, time() + 86400);

        subscription_manager::suspend($subid);

        $this->assertSame(subscription_manager::STATUS_SUSPENDED, subscription_manager::get($subid)->status);
        // Still enrolled, but NOT active.
        $this->assertTrue(is_enrolled($ctx, $user->id));
        $this->assertFalse(is_enrolled($ctx, $user->id, '', true));
    }

    public function test_record_cycle_reactivates_and_advances(): void {
        $this->resetAfterTest();
        [$course, $user, $enrolid, $ctx] = $this->make_instance();
        $subid = $this->new_sub($enrolid, $user->id, $course->id);
        subscription_manager::activate($subid, time() + 86400);
        subscription_manager::suspend($subid);

        $next = time() + 60 * 86400;
        subscription_manager::record_cycle($subid, $next);

        $sub = subscription_manager::get($subid);
        $this->assertSame(subscription_manager::STATUS_ACTIVE, $sub->status);
        $this->assertEquals($next, $sub->nextchargets);
        $this->assertGreaterThan(0, $sub->lastchargets);
        $this->assertTrue(is_enrolled($ctx, $user->id, '', true)); // re-activated
    }

    public function test_cancel_revokes_enrolment(): void {
        $this->resetAfterTest();
        [$course, $user, $enrolid, $ctx] = $this->make_instance();
        $subid = $this->new_sub($enrolid, $user->id, $course->id);
        subscription_manager::activate($subid, time() + 86400);

        subscription_manager::cancel($subid);

        $sub = subscription_manager::get($subid);
        $this->assertSame(subscription_manager::STATUS_CANCELLED, $sub->status);
        $this->assertGreaterThan(0, $sub->cancelledts);
        $this->assertFalse(is_enrolled($ctx, $user->id)); // fully unenrolled
    }

    public function test_get_by_user_enrol_finds_record(): void {
        $this->resetAfterTest();
        [$course, $user, $enrolid] = $this->make_instance();
        $subid = $this->new_sub($enrolid, $user->id, $course->id);

        $found = subscription_manager::get_by_user_enrol($enrolid, $user->id);
        $this->assertNotNull($found);
        $this->assertEquals($subid, $found->id);
    }

    public function test_full_lifecycle(): void {
        $this->resetAfterTest();
        [$course, $user, $enrolid, $ctx] = $this->make_instance();
        $subid = $this->new_sub($enrolid, $user->id, $course->id);

        $this->assertSame(subscription_manager::STATUS_PENDING, subscription_manager::get($subid)->status);
        subscription_manager::activate($subid, time() + 86400);
        $this->assertTrue(is_enrolled($ctx, $user->id, '', true));
        subscription_manager::suspend($subid);
        $this->assertFalse(is_enrolled($ctx, $user->id, '', true));
        subscription_manager::record_cycle($subid, time() + 86400);
        $this->assertTrue(is_enrolled($ctx, $user->id, '', true));
        subscription_manager::cancel($subid);
        $this->assertFalse(is_enrolled($ctx, $user->id));
        $this->assertSame(subscription_manager::STATUS_CANCELLED, subscription_manager::get($subid)->status);
    }

    /**
     * Increment 5 — all-access scope grants via the configured cohort (not per-course enrol).
     * Suspend removes membership (cohorts have no suspended state); reactivate re-adds; cancel removes.
     */
    public function test_allaccess_scope_uses_cohort(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cohortid = (int) $this->getDataGenerator()->create_cohort()->id;
        set_config('allaccess_cohortid', $cohortid, 'enrol_sentientiasub');

        $subid = subscription_manager::create(0, $user->id, subscription_manager::SCOPE_ALLACCESS,
            null, 499.00, 'INR', 'monthly', 77);
        subscription_manager::activate($subid, time() + 86400);
        $this->assertTrue(cohort_is_member($cohortid, $user->id));   // granted
        subscription_manager::suspend($subid);
        $this->assertFalse(cohort_is_member($cohortid, $user->id));  // suspend removes
        subscription_manager::record_cycle($subid, time() + 86400);
        $this->assertTrue(cohort_is_member($cohortid, $user->id));   // reactivate re-adds
        subscription_manager::cancel($subid);
        $this->assertFalse(cohort_is_member($cohortid, $user->id));  // cancel removes
    }

    /**
     * Increment 5 — category scope resolves a cohort by the idnumber convention
     * `sentientiasub_cat_<categoryid>` and manages membership there.
     */
    public function test_category_scope_uses_idnumber_cohort(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cat = $this->getDataGenerator()->create_category();
        $cohortid = (int) $this->getDataGenerator()->create_cohort(
            ['idnumber' => 'sentientiasub_cat_' . $cat->id])->id;

        $subid = subscription_manager::create(0, $user->id, subscription_manager::SCOPE_CATEGORY,
            (int) $cat->id, 199.00, 'INR', 'monthly', 77);
        subscription_manager::activate($subid, time() + 86400);
        $this->assertTrue(cohort_is_member($cohortid, $user->id));
        subscription_manager::cancel($subid);
        $this->assertFalse(cohort_is_member($cohortid, $user->id));
    }
}
