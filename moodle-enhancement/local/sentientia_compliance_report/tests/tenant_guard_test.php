<?php
// This file is part of Sentientia LMS.

/**
 * A1 (post-demo plan, session 1, 2026-11-20): who a tracked course reaches.
 *
 * The five Airpay compliance courses were tracked for "all tenants"
 * (costcenterid 0). rebuild_snapshot() checked only that pin, so the hourly
 * task auto-enrolled, mailed and counted every Public (/77) and ZEEA (/177)
 * user in Airpay's (/1) courses, and everyone in the IC-members-only course.
 * It now also requires the course to reach the user's tenant (its own course,
 * or shared to it through local_sentientia_courses) and the row's cohort to
 * hold the user, removes snapshot rows that no longer apply, and enrols with
 * no end date.
 *
 * @package    local_sentientia_compliance_report
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_compliance_report;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_compliance_report\compliance_engine
 * @group tenant_isolation
 */
final class tenant_guard_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** Component of the plugin's own compliance messages. */
    private const COMPONENT = 'local_sentientia_compliance_report';

    /** @var \phpunit_message_sink */
    private $sink;

    /** @var \stdClass[] people by name: airpay, public, zeea */
    private $u = [];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->sink = $this->redirectMessages();
        $this->setAdminUser();
        set_config('auto_enrol', 1, self::COMPONENT);

        $this->u['airpay'] = $this->person('/1/2');
        $this->u['public'] = $this->person('/77');
        $this->u['zeea'] = $this->person('/177/178');
    }

    /** A course owned by a tenant path ('' = legacy course, no open_path). */
    private function course(string $path, string $name = 'Course'): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['fullname' => $name]);
        if ($path !== '') {
            $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        }
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /** Put a course on the compliance list; returns the row id. */
    private function track(\stdClass $course, int $costcenterid = 0, int $cohortid = 0): int {
        global $DB;
        static $sort = 0;
        return (int) $DB->insert_record('local_compliance_courses', (object) [
            'courseid' => $course->id, 'coursename' => $course->fullname, 'deadline_days' => 30,
            'costcenterid' => $costcenterid, 'cohortid' => $cohortid, 'is_active' => 1,
            'sort_order' => ++$sort, 'createdby' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /** Share a course to a tenant the way sharing_manager stores it. */
    private function share(\stdClass $course, int $tenant, string $status = 'active'): void {
        global $DB;
        $DB->insert_record('local_sentientia_courses_tenant_share', (object) [
            'courseid' => $course->id, 'tenant_id' => $tenant, 'shared_by' => get_admin()->id,
            'status' => $status, 'timeshared' => time(), 'timemodified' => time(),
        ]);
    }

    /** A user at a BizLMS org path. */
    private function person(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** Rebuild; returns [stats, messages sent by the rebuild]. */
    private function rebuild(): array {
        $this->sink->clear();
        $stats = compliance_engine::rebuild_snapshot();
        return [$stats, $this->sink->get_messages()];
    }

    private function is_enrolled(\stdClass $user, \stdClass $course): bool {
        global $DB;
        return $DB->record_exists_sql(
            "SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid",
            ['cid' => $course->id, 'uid' => $user->id]);
    }

    private function snapshot_status(\stdClass $user, \stdClass $course): ?string {
        global $DB;
        $status = $DB->get_field('local_compliance_snapshot', 'status',
            ['userid' => $user->id, 'courseid' => $course->id]);
        return $status === false ? null : (string) $status;
    }

    private function messages_to(array $messages, \stdClass $user): array {
        return array_values(array_filter($messages, fn($m) => (int) $m->useridto === (int) $user->id
            && $m->component === self::COMPONENT));
    }

    /**
     * The defect: an Airpay course tracked for all tenants (costcenterid 0)
     * reaches Airpay people only.
     */
    public function test_public_and_zeea_users_are_not_auto_enrolled_into_an_airpay_course(): void {
        global $DB;
        $course = $this->course('/1', 'POSH Training 2025');
        $this->track($course, 0);

        [$stats, $messages] = $this->rebuild();

        $this->assertTrue($this->is_enrolled($this->u['airpay'], $course));
        $this->assertSame('not_started', $this->snapshot_status($this->u['airpay'], $course));
        $this->assertCount(1, $this->messages_to($messages, $this->u['airpay']));

        foreach (['public', 'zeea'] as $name) {
            $user = $this->u[$name];
            $this->assertFalse($this->is_enrolled($user, $course), "{$name} user was auto-enrolled");
            $this->assertNull($this->snapshot_status($user, $course), "{$name} user has a snapshot row");
            $this->assertSame([], $this->messages_to($messages, $user), "{$name} user was mailed");
            $this->assertFalse($DB->record_exists('local_compliance_email_log', ['userid' => $user->id]));
        }
        // Only the Airpay user is tracked; the site admin (no tenant) is not either.
        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['auto_enrolled']);
        $this->assertGreaterThanOrEqual(2, $stats['out_of_scope']);

        // The Public and ZEEA reports have nothing of it.
        $this->assertSame(0, (int) compliance_engine::get_summary_kpis('/77')['total']);
        $this->assertSame(0, (int) compliance_engine::get_summary_kpis('/177')['total']);
        $this->assertSame(1, (int) compliance_engine::get_summary_kpis('/1')['total']);
    }

    /** A share to Public lets the course reach Public; ZEEA still not; a withdrawn share reaches nobody. */
    public function test_a_shared_course_reaches_the_tenant_it_is_shared_to(): void {
        $shared = $this->course('/1', 'Phishing Awareness Training');
        $withdrawn = $this->course('/1', 'Anti Money Laundering');
        $this->track($shared, 0);
        $this->track($withdrawn, 0);
        $this->share($shared, 77);
        $this->share($withdrawn, 177, 'withdrawn');

        $this->rebuild();

        $this->assertTrue($this->is_enrolled($this->u['airpay'], $shared));
        $this->assertTrue($this->is_enrolled($this->u['public'], $shared));
        $this->assertSame('not_started', $this->snapshot_status($this->u['public'], $shared));
        $this->assertFalse($this->is_enrolled($this->u['zeea'], $shared));

        $this->assertTrue($this->is_enrolled($this->u['airpay'], $withdrawn));
        $this->assertFalse($this->is_enrolled($this->u['zeea'], $withdrawn), 'a withdrawn share still reached ZEEA');
        $this->assertFalse($this->is_enrolled($this->u['public'], $withdrawn));
    }

    /**
     * Rows written before the fix are removed from the snapshot, so the
     * reports stop counting them; the enrolment itself stays (D3).
     */
    public function test_stale_out_of_tenant_snapshot_rows_are_removed_and_enrolments_kept(): void {
        global $DB;
        $course = $this->course('/1');
        $this->track($course, 0);
        // As the old code left it: the Public user enrolled and tracked.
        $this->getDataGenerator()->enrol_user($this->u['public']->id, $course->id, 'student');
        $DB->insert_record('local_compliance_snapshot', (object) [
            'userid' => $this->u['public']->id, 'courseid' => $course->id, 'costcenterid' => 77,
            'department_path' => '/77', 'status' => 'overdue', 'progress_percent' => 0,
            'days_overdue' => 12, 'matched_by' => 'userid', 'snapshot_date' => time() - HOURSECS,
        ]);
        $this->assertSame(1, (int) compliance_engine::get_summary_kpis('/77')['overdue']);

        [$stats] = $this->rebuild();

        $this->assertNull($this->snapshot_status($this->u['public'], $course));
        $this->assertTrue($this->is_enrolled($this->u['public'], $course), 'the rebuild unenrolled someone');
        $this->assertSame(1, $stats['pruned']);
        $this->assertSame(0, (int) compliance_engine::get_summary_kpis('/77')['total']);
        $this->assertSame(0, count(compliance_engine::get_defaulters('/77')));
        // The Airpay user's row is untouched by the prune.
        $this->assertSame('not_started', $this->snapshot_status($this->u['airpay'], $course));
    }

    /** A legacy course with no open_path keeps reaching every tenant, as before. */
    public function test_a_course_without_an_owner_still_reaches_every_tenant(): void {
        $course = $this->course('');
        $this->track($course, 0);

        $this->rebuild();

        foreach (['airpay', 'public', 'zeea'] as $name) {
            $this->assertTrue($this->is_enrolled($this->u[$name], $course), "{$name} user not enrolled");
        }
    }

    /**
     * A row pinned to a tenant the course is not shared to applies to
     * nobody; sharing the course opens it to that tenant.
     */
    public function test_a_pin_to_another_tenant_needs_a_share(): void {
        $course = $this->course('/1');
        $this->track($course, 77);

        $this->rebuild();
        foreach (['airpay', 'public', 'zeea'] as $name) {
            $this->assertFalse($this->is_enrolled($this->u[$name], $course), "{$name} user enrolled");
        }

        $this->share($course, 77);
        $this->rebuild();
        $this->assertTrue($this->is_enrolled($this->u['public'], $course));
        $this->assertFalse($this->is_enrolled($this->u['airpay'], $course), 'the pin no longer holds');
        $this->assertFalse($this->is_enrolled($this->u['zeea'], $course));
    }

    /**
     * D4: an auto-enrolment has no end date, so an overdue learner can still
     * finish; the deadline lives in the snapshot.
     */
    public function test_auto_enrolment_has_no_end_date(): void {
        global $DB;
        $course = $this->course('/1');
        $this->track($course, 0);

        $this->rebuild();

        $ue = $DB->get_record_sql(
            "SELECT ue.timestart, ue.timeend FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid",
            ['cid' => $course->id, 'uid' => $this->u['airpay']->id], MUST_EXIST);
        $this->assertEquals(0, $ue->timeend, 'the enrolment ends at the deadline');
        $snap = $DB->get_record('local_compliance_snapshot',
            ['userid' => $this->u['airpay']->id, 'courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals((int) $snap->enrol_date + 30 * DAYSECS, (int) $snap->deadline_date);
    }

    /**
     * A row restricted to a cohort reaches its members only. Non-members'
     * rows are removed and the matrix reads "Not applicable" for them, which
     * does not count against "all completed". A deleted cohort reaches nobody.
     */
    public function test_a_cohort_row_tracks_members_only(): void {
        global $DB;
        $member = $this->u['airpay'];
        $other = $this->person('/1/2');
        $publicmember = $this->u['public'];
        $cohort = $this->getDataGenerator()->create_cohort(['idnumber' => 'ic_test']);
        cohort_add_member($cohort->id, $member->id);
        cohort_add_member($cohort->id, $publicmember->id);

        $everyone = $this->course('/1', 'Information Security');
        $ic = $this->course('/1', 'POSH for IC Members');
        $this->track($everyone, 1);
        $icrow = $this->track($ic, 1, (int) $cohort->id);
        // The old code tracked the non-member for the IC course too.
        $DB->insert_record('local_compliance_snapshot', (object) [
            'userid' => $other->id, 'courseid' => $ic->id, 'costcenterid' => 1, 'department_path' => '/1/2',
            'status' => 'not_enrolled', 'progress_percent' => 0, 'days_overdue' => 0,
            'matched_by' => 'userid', 'snapshot_date' => time() - HOURSECS,
        ]);

        $this->rebuild();

        $this->assertTrue($this->is_enrolled($member, $ic));
        $this->assertFalse($this->is_enrolled($other, $ic));
        $this->assertNull($this->snapshot_status($other, $ic));
        $this->assertFalse($this->is_enrolled($publicmember, $ic), 'the cohort let another tenant in');
        $this->assertTrue($this->is_enrolled($other, $everyone));

        $matrix = compliance_engine::get_compliance_matrix('/1');
        $rows = array_column($matrix['rows'], null, 'userid');
        $bycourse = fn(int $uid) => array_column($rows[$uid]['courses'], 'status', 'courseid');
        $this->assertSame('not_applicable', $bycourse((int) $other->id)[$ic->id]);
        $this->assertSame('not_started', $bycourse((int) $member->id)[$ic->id]);

        // Complete the other person's one applicable course: all completed.
        $DB->set_field('local_compliance_snapshot', 'status', 'completed',
            ['userid' => $other->id, 'courseid' => $everyone->id]);
        $rows = array_column(compliance_engine::get_compliance_matrix('/1')['rows'], null, 'userid');
        $this->assertTrue($rows[$other->id]['all_completed']);

        // The Configure tab names the cohort.
        $managed = array_column(compliance_engine::get_managed_courses(), null, 'id');
        $this->assertStringContainsString((string) $cohort->name, $managed[$icrow]['entity_name']);

        // A deleted cohort: nobody, not everybody.
        cohort_delete_cohort($cohort);
        [$stats] = $this->rebuild();
        $this->assertNull($this->snapshot_status($member, $ic));
        $this->assertGreaterThanOrEqual(1, $stats['pruned']);
        $managed = array_column(compliance_engine::get_managed_courses(), null, 'id');
        $this->assertStringContainsString(get_string('cohort_deleted', self::COMPONENT, $cohort->id),
            $managed[$icrow]['entity_name']);
    }

    /** Report columns: another tenant's course is not a column of "Not Enrolled" in the Public report. */
    public function test_report_columns_follow_the_course_audience(): void {
        $airpay = $this->course('/1');
        $legacy = $this->course('');
        $this->track($airpay, 0);
        $this->track($legacy, 0);
        $ids = fn(string $path) => array_values(array_map(fn($r) => (int) $r->courseid,
            compliance_engine::get_active_courses_for_scope($path)));

        $this->assertSame([(int) $airpay->id, (int) $legacy->id], $ids('/1'));
        $this->assertSame([(int) $legacy->id], $ids('/77'));
        $this->assertSame([(int) $airpay->id, (int) $legacy->id], $ids(''));

        $this->share($airpay, 77);
        $this->assertSame([(int) $airpay->id, (int) $legacy->id], $ids('/77'));
        $this->assertSame([(int) $legacy->id], $ids('/177'));
    }

    /** A course listed for all tenants and for Airpay is tracked once per person. */
    public function test_a_course_listed_twice_is_counted_once(): void {
        $course = $this->course('/1');
        $this->track($course, 0);
        $this->track($course, 1);

        [$stats] = $this->rebuild();

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, (int) compliance_engine::get_summary_kpis('/1')['total']);
    }

    /** row_applies() truth table, without the database. */
    public function test_row_applies_rules(): void {
        $audiences = [
            10 => ['tenant' => 1, 'sharedto' => [77 => true]],
            11 => ['tenant' => 0, 'sharedto' => []],
        ];
        $cohorts = [5 => [42 => true]];
        $row = fn(int $courseid, int $pin, int $cohortid = 0) => (object) [
            'courseid' => $courseid, 'costcenterid' => $pin, 'cohortid' => $cohortid];

        $this->assertTrue(compliance_engine::row_applies($row(10, 0), 1, 1, $audiences, $cohorts));
        $this->assertTrue(compliance_engine::row_applies($row(10, 0), 77, 1, $audiences, $cohorts));
        $this->assertFalse(compliance_engine::row_applies($row(10, 0), 177, 1, $audiences, $cohorts));
        $this->assertFalse(compliance_engine::row_applies($row(10, 0), 0, 1, $audiences, $cohorts));
        $this->assertFalse(compliance_engine::row_applies($row(10, 1), 77, 1, $audiences, $cohorts));
        $this->assertTrue(compliance_engine::row_applies($row(11, 0), 177, 1, $audiences, $cohorts));
        $this->assertTrue(compliance_engine::row_applies($row(11, 0), 0, 1, $audiences, $cohorts));
        $this->assertTrue(compliance_engine::row_applies($row(10, 1, 5), 1, 42, $audiences, $cohorts));
        $this->assertFalse(compliance_engine::row_applies($row(10, 1, 5), 1, 43, $audiences, $cohorts));
        $this->assertFalse(compliance_engine::row_applies($row(10, 1, 6), 1, 42, $audiences, $cohorts));
        // A row without the column (older caller) is not cohort-restricted.
        $this->assertTrue(compliance_engine::row_applies(
            (object) ['courseid' => 10, 'costcenterid' => 0], 1, 1, $audiences, $cohorts));
    }
}
