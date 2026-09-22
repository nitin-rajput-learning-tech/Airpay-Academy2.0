<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_compliance_report\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the compliance-report privacy provider.
 *
 * @covers \local_sentientia_compliance_report\privacy\provider
 *
 * Until 2026-09-22 this plugin declared null_provider, whose comment read
 * "this plugin does not store personal data in airpay-owned tables". It owns
 * four: local_compliance_snapshot, _exemptions and _email_log are each keyed
 * on a userid, and _email_log also stores the email address the reminder went
 * to. A DPDP subject-access request returned nothing from this plugin and an
 * erasure request deleted nothing, in both cases reporting success.
 *
 * The assertions below are deliberately written so they FAIL against a
 * null_provider: a null_provider cannot even be constructed with these
 * interfaces, and its get_metadata() declares no tables.
 *
 * @package    local_sentientia_compliance_report
 * @category   test
 *
 * @group tenant_isolation
 */
final class privacy_provider_test extends \advanced_testcase {

    /** @var \stdClass The data subject. */
    private $subject;

    /** @var \stdClass Another employee whose records must survive. */
    private $bystander;

    /** @var \stdClass The approver, who appears only as an actor. */
    private $approver;

    /** @var int */
    private $courseid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $this->subject   = $this->getDataGenerator()->create_user();
        $this->bystander = $this->getDataGenerator()->create_user();
        $this->approver  = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->courseid = (int) $course->id;

        $now = time();

        // Mandatory-course configuration, authored by the approver.
        $DB->insert_record('local_compliance_courses', (object) [
            'courseid' => $this->courseid,
            'coursename' => 'POSH Training',
            'deadline_days' => 30,
            'costcenterid' => 1,
            'is_active' => 1,
            'sort_order' => 1,
            'createdby' => $this->approver->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        foreach ([$this->subject, $this->bystander] as $u) {
            $DB->insert_record('local_compliance_snapshot', (object) [
                'userid' => $u->id,
                'courseid' => $this->courseid,
                'costcenterid' => 1,
                'department_path' => '/1/2',
                'status' => 'overdue',
                'completion_date' => 0,
                'progress_percent' => 40,
                'enrol_date' => $now - 86400,
                'deadline_date' => $now + 86400,
                'days_overdue' => 3,
                'matched_by' => 'enrolment',
                'snapshot_date' => $now,
            ]);
            $DB->insert_record('local_compliance_email_log', (object) [
                'userid' => $u->id,
                'courseid' => $this->courseid,
                'email_type' => 'reminder_7d',
                'sent_to' => $u->email,
                'timecreated' => $now,
            ]);
        }

        // The subject has an exemption the approver signed off.
        $DB->insert_record('local_compliance_exemptions', (object) [
            'userid' => $this->subject->id,
            'courseid' => $this->courseid,
            'reason' => 'On parental leave',
            'approved_by' => $this->approver->id,
            'expiry_date' => $now + (86400 * 90),
            'is_active' => 1,
            'timecreated' => $now,
        ]);

        // The bystander has one too, signed off by the same approver. Erasing
        // the approver must not destroy this row.
        $DB->insert_record('local_compliance_exemptions', (object) [
            'userid' => $this->bystander->id,
            'courseid' => $this->courseid,
            'reason' => 'Contractor, out of scope',
            'approved_by' => $this->approver->id,
            'expiry_date' => $now + (86400 * 90),
            'is_active' => 1,
            'timecreated' => $now,
        ]);
    }

    /**
     * Build an approved contextlist at system context for a user.
     *
     * @param \stdClass $user
     * @return approved_contextlist
     */
    private function approved_for(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, 'local_sentientia_compliance_report',
            [\context_system::instance()->id]);
    }

    // ── metadata ─────────────────────────────────────────────────────────

    public function test_metadata_declares_every_table_that_holds_user_data(): void {
        $collection = provider::get_metadata(
            new collection('local_sentientia_compliance_report'));

        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[] = $item->get_name();
        }

        foreach (['local_compliance_snapshot',
                  'local_compliance_exemptions',
                  'local_compliance_email_log',
                  'local_compliance_courses'] as $table) {
            $this->assertContains($table, $declared,
                "{$table} holds a user reference and must be declared; a "
                . 'null_provider declared none of them');
        }
    }

    public function test_the_provider_is_not_a_null_provider(): void {
        $this->assertInstanceOf(\core_privacy\local\metadata\provider::class,
            new provider());
        $this->assertInstanceOf(\core_privacy\local\request\plugin\provider::class,
            new provider());
        $this->assertNotInstanceOf(
            \core_privacy\local\metadata\null_provider::class, new provider(),
            'declaring null_provider asserts to the privacy registry that no '
            . 'personal data is held, which was never true here');
    }

    // ── discovery ────────────────────────────────────────────────────────

    public function test_a_user_with_rows_is_found_and_one_without_is_not(): void {
        $this->assertNotEmpty(
            provider::get_contexts_for_userid((int) $this->subject->id)
                ->get_contextids());

        // An actor-only reference still counts: the approver's identity is
        // held here, so they must be able to see it.
        $this->assertNotEmpty(
            provider::get_contexts_for_userid((int) $this->approver->id)
                ->get_contextids());

        $stranger = $this->getDataGenerator()->create_user();
        $this->assertEmpty(
            provider::get_contexts_for_userid((int) $stranger->id)
                ->get_contextids(),
            'a user with no rows must not be offered an empty export section');
    }

    public function test_get_users_in_context_lists_subjects_and_actors(): void {
        $userlist = new userlist(\context_system::instance(),
            'local_sentientia_compliance_report');
        provider::get_users_in_context($userlist);
        $ids = $userlist->get_userids();

        $this->assertContains((int) $this->subject->id, $ids);
        $this->assertContains((int) $this->bystander->id, $ids);
        $this->assertContains((int) $this->approver->id, $ids);
    }

    // ── export ───────────────────────────────────────────────────────────

    public function test_export_returns_the_users_compliance_data(): void {
        writer::reset();
        provider::export_user_data($this->approved_for($this->subject));

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data(),
            'the export was silently empty before this provider existed');
    }

    // ── erasure: the part that reported success without acting ───────────

    public function test_erasing_a_subject_deletes_their_rows_only(): void {
        global $DB;

        provider::delete_data_for_user($this->approved_for($this->subject));

        $this->assertFalse($DB->record_exists('local_compliance_snapshot',
            ['userid' => $this->subject->id]));
        $this->assertFalse($DB->record_exists('local_compliance_email_log',
            ['userid' => $this->subject->id]),
            'this row held the employee email address the reminder was sent to');
        $this->assertFalse($DB->record_exists('local_compliance_exemptions',
            ['userid' => $this->subject->id]));

        // The bystander is untouched.
        $this->assertTrue($DB->record_exists('local_compliance_snapshot',
            ['userid' => $this->bystander->id]));
        $this->assertTrue($DB->record_exists('local_compliance_email_log',
            ['userid' => $this->bystander->id]));
        $this->assertTrue($DB->record_exists('local_compliance_exemptions',
            ['userid' => $this->bystander->id]));
    }

    public function test_erasing_an_actor_anonymises_and_does_not_destroy_records(): void {
        global $DB;

        $before = $DB->count_records('local_compliance_exemptions');
        $configbefore = $DB->count_records('local_compliance_courses');

        provider::delete_data_for_user($this->approved_for($this->approver));

        // Both exemptions survive - they are other people's records.
        $this->assertSame($before,
            $DB->count_records('local_compliance_exemptions'),
            'erasing the approver must not delete the employees whose '
            . 'exemptions they signed');
        $this->assertSame($configbefore,
            $DB->count_records('local_compliance_courses'),
            'the mandatory-course list is shared configuration, not one '
            . "person's data");

        // But the reference to them is gone.
        $this->assertFalse($DB->record_exists('local_compliance_exemptions',
            ['approved_by' => $this->approver->id]));
        $this->assertFalse($DB->record_exists('local_compliance_courses',
            ['createdby' => $this->approver->id]));
        $this->assertSame(2, $DB->count_records('local_compliance_exemptions',
            ['approved_by' => 0]));
    }

    public function test_bulk_erasure_handles_several_users_at_once(): void {
        global $DB;

        $userlist = new approved_userlist(\context_system::instance(),
            'local_sentientia_compliance_report',
            [$this->subject->id, $this->approver->id]);
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('local_compliance_snapshot',
            ['userid' => $this->subject->id]));
        $this->assertFalse($DB->record_exists('local_compliance_exemptions',
            ['approved_by' => $this->approver->id]));
        // The bystander's own rows survive.
        $this->assertTrue($DB->record_exists('local_compliance_snapshot',
            ['userid' => $this->bystander->id]));
    }

    public function test_context_wide_deletion_clears_subject_rows(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context(
            \context_system::instance());

        $this->assertSame(0, $DB->count_records('local_compliance_snapshot'));
        $this->assertSame(0, $DB->count_records('local_compliance_email_log'));
        $this->assertSame(0, $DB->count_records('local_compliance_exemptions'));
        // Configuration survives, anonymised.
        $this->assertGreaterThan(0,
            $DB->count_records('local_compliance_courses'));
        $this->assertSame(0, $DB->count_records_select('local_compliance_courses',
            'createdby > 0'));
    }

    public function test_a_wrong_context_is_ignored(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        provider::delete_data_for_all_users_in_context(
            \context_course::instance($course->id));

        $this->assertGreaterThan(0,
            $DB->count_records('local_compliance_snapshot'),
            'a course-context request must not wipe site-level tables');
    }
}
