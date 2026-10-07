<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\test\bizlms_fixture;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\feature_flags;

/**
 * The three e-mails BizLMS sends that Sentientia had no sender for (decision COMMS-N7, 2026-10-07): course enrolment,
 * learning-path enrolment and the manager's copy of a course completion. Each is behind its own default-OFF flag.
 *
 * Nothing is ever delivered here: $CFG->noemailever is set, so every send ends as a delivery-log row marked suppressed,
 * which is also what every non-production copy does. The tests read those rows.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @covers     \local_sentientia_emails\parity_senders
 * @covers     \local_sentientia_emails\observer
 * @covers     \local_sentientia_emails\task\send_path_enrolments
 *
 * @group local_sentientia_emails
 * @group tenant_isolation
 */
final class parity_senders_test extends \advanced_testcase {
    use bizlms_fixture;

    private const LOG = 'local_sentientia_email_log';

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        // The observers run when the enrolment commits; an outer test transaction would hold them back.
        $this->preventResetByRollback();
        $this->setAdminUser();
        $CFG->noemailever = true;
    }

    protected function tearDown(): void {
        try {
            foreach ([parity_senders::FLAG_COURSE_ENROLMENT, parity_senders::FLAG_PATH_ENROLMENT,
                      parity_senders::FLAG_MANAGER_COPY] as $flag) {
                feature_flags::set($flag, 0, null);
                feature_flags::set($flag, 77, null);
            }
        } catch (\Throwable $e) {
            debugging('could not unset the test flags: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        parent::tearDown();
    }

    /**
     * @param string $path open_path, '' for none.
     * @param array $more Generator fields.
     * @return \stdClass A user at that tenant path.
     */
    private function user_at(string $path, array $more = []): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user($more);
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * @param string ...$flags Flags to turn ON for every customer and tenant.
     * @return void
     */
    private function on(string ...$flags): void {
        foreach ($flags as $flag) {
            feature_flags::set($flag, 0, true);
        }
    }

    /**
     * @param array $conditions
     * @return \stdClass[] Rows of the delivery log, oldest first.
     */
    private function logs(array $conditions = []): array {
        global $DB;
        return array_values($DB->get_records(self::LOG, $conditions, 'id ASC'));
    }

    // The flags.

    public function test_the_three_senders_ship_off(): void {
        $registry = feature_flags::load_registry();
        foreach ([parity_senders::FLAG_COURSE_ENROLMENT, parity_senders::FLAG_PATH_ENROLMENT,
                  parity_senders::FLAG_MANAGER_COPY] as $flag) {
            $this->assertArrayHasKey($flag, $registry, "{$flag} is registered in db/feature_flags.php");
            $this->assertFalse($registry[$flag]['default'], "{$flag} defaults to OFF");
            $this->assertFalse(feature_flags::is_enabled($flag));
            $this->assertFalse(parity_senders::enabled_anywhere($flag));
        }
    }

    // COMMS-N7: a learner enrolled in a course.

    public function test_with_the_flag_off_an_enrolment_sends_nothing(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user_at('/1/5');
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->assertSame([], $this->logs());
        $this->assertFalse(parity_senders::course_enrolled((int) $user->id, (int) $course->id));
    }

    public function test_an_enrolment_sends_the_course_enrolment_email_when_the_flag_is_on(): void {
        $this->on(parity_senders::FLAG_COURSE_ENROLMENT);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user_at('/1/5');
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $rows = $this->logs();
        $this->assertCount(1, $rows, 'the user_enrolment_created observer sent one e-mail');
        $row = $rows[0];
        $this->assertSame((int) $user->id, (int) $row->userid);
        $this->assertSame((int) $course->id, (int) $row->courseid);
        $this->assertSame('enrollment/course_enrolled', $row->template_key, 'every send writes its template key');
        $this->assertSame('email', $row->channel);
        $this->assertSame('suppressed', $row->status, 'noemailever: logged, never delivered');
        $this->assertNull($row->rule_id, 'no rule row exists, so the built-in default is used');
        $this->assertNull($row->legacy_source, 'a native row, not an imported one');
        $this->assertSame(1, (int) $row->tenant_id);
        $this->assertSame('You have been enrolled in: ' . $course->fullname, $row->subject);
    }

    public function test_the_same_enrolment_email_is_sent_once_within_a_few_minutes(): void {
        $this->on(parity_senders::FLAG_COURSE_ENROLMENT);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user_at('/1/5');
        $this->assertTrue(parity_senders::course_enrolled((int) $user->id, (int) $course->id));
        $this->assertFalse(parity_senders::course_enrolled((int) $user->id, (int) $course->id),
            'a bulk operation that fires the event twice sends one e-mail');
        $this->assertCount(1, $this->logs());
    }

    public function test_a_hidden_course_a_suspended_user_the_site_course_and_a_suspended_enrolment_send_nothing(): void {
        global $DB;
        $this->on(parity_senders::FLAG_COURSE_ENROLMENT);
        $user = $this->user_at('/1/5');

        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);
        $this->assertFalse(parity_senders::course_enrolled((int) $user->id, (int) $hidden->id), 'the link would not open');
        $this->assertFalse(parity_senders::course_enrolled((int) $user->id, SITEID));

        $course = $this->getDataGenerator()->create_course();
        $suspended = $this->user_at('/1/5', ['suspended' => 1]);
        $this->assertFalse(parity_senders::course_enrolled((int) $suspended->id, (int) $course->id));
        $deleted = $this->user_at('/1/5');
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);
        $this->assertFalse(parity_senders::course_enrolled((int) $deleted->id, (int) $course->id));

        $this->getDataGenerator()->enrol_user($user->id, $course->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->assertSame([], $this->logs(), 'a suspended enrolment is not a welcome');
    }

    public function test_the_flag_is_read_for_the_learners_own_tenant(): void {
        feature_flags::set(parity_senders::FLAG_COURSE_ENROLMENT, 77, true);
        $course = $this->getDataGenerator()->create_course();
        $airpay = $this->user_at('/1/5');
        $public = $this->user_at('/77');

        $this->assertTrue(parity_senders::enabled_anywhere(parity_senders::FLAG_COURSE_ENROLMENT));
        $this->assertFalse(parity_senders::course_enrolled((int) $airpay->id, (int) $course->id),
            'the flag is ON for tenant 77 only');
        $this->assertTrue(parity_senders::course_enrolled((int) $public->id, (int) $course->id));
        $rows = $this->logs();
        $this->assertCount(1, $rows);
        $this->assertSame((int) $public->id, (int) $rows[0]->userid);
        $this->assertSame(77, (int) $rows[0]->tenant_id);
    }

    public function test_an_administrator_can_switch_the_email_off_or_choose_the_channel_with_a_rule(): void {
        global $DB;
        $this->on(parity_senders::FLAG_COURSE_ENROLMENT);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user_at('/1/5');
        $ruleid = (int) $DB->insert_record('local_sentientia_email_rules', (object) [
            'rule_name' => 'Course enrolled', 'rule_type' => parity_senders::RULE_COURSE_ENROLLED, 'tenant_id' => 0,
            'channel' => 'email', 'template_key' => 'enrollment/course_enrolled', 'enabled' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertFalse(parity_senders::course_enrolled((int) $user->id, (int) $course->id),
            'a rule row that exists and is disabled means the e-mail was switched off');
        $this->assertSame([], $this->logs());

        $DB->set_field('local_sentientia_email_rules', 'enabled', 1, ['id' => $ruleid]);
        $this->assertTrue(parity_senders::course_enrolled((int) $user->id, (int) $course->id));
        $rows = $this->logs();
        $this->assertCount(1, $rows);
        $this->assertSame($ruleid, (int) $rows[0]->rule_id, 'the rule that decided is on the row');
    }

    // COMMS-N7: the manager's copy of a course completion.

    /**
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass} The learner (supervised by the manager), the manager and the course.
     */
    private function learner_with_manager(string $learnerpath = '/1/5', string $managerpath = '/1/5'): array {
        global $DB;
        $manager = $this->user_at($managerpath, ['firstname' => 'Binay', 'lastname' => 'Upadhyay']);
        $learner = $this->user_at($learnerpath, ['firstname' => 'Priya', 'lastname' => 'Singh']);
        $DB->set_field('user', 'open_supervisorid', $manager->id, ['id' => $learner->id]);
        $learner = $DB->get_record('user', ['id' => $learner->id], '*', MUST_EXIST);
        $course = $this->getDataGenerator()->create_course();
        return [$learner, $manager, $course];
    }

    public function test_with_the_flag_off_a_completion_sends_the_manager_nothing(): void {
        [$learner, , $course] = $this->learner_with_manager();
        $this->assertFalse(parity_senders::manager_completion_copy($learner, $course));
        $this->assertSame([], $this->logs());
    }

    public function test_a_completion_sends_the_supervisor_a_copy_when_the_flag_is_on(): void {
        $this->on(parity_senders::FLAG_MANAGER_COPY);
        [$learner, $manager, $course] = $this->learner_with_manager();

        $this->assertTrue(parity_senders::manager_completion_copy($learner, $course));
        $rows = $this->logs();
        $this->assertCount(1, $rows);
        $this->assertSame((int) $manager->id, (int) $rows[0]->userid, 'the e-mail is the manager\'s');
        $this->assertSame((int) $course->id, (int) $rows[0]->courseid);
        $this->assertSame('enrollment/manager_course_completed', $rows[0]->template_key);
        $this->assertSame('suppressed', $rows[0]->status);
        // The row is the manager's. It must not name the learner: the privacy provider reaches a row by the person it
        // belongs to, so the learner's own erasure could never remove a name written into the manager's row.
        $this->assertSame('A team member has completed ' . $course->fullname, $rows[0]->subject);
    }

    public function test_the_manager_copy_log_row_holds_nothing_of_the_learner(): void {
        global $DB;
        $this->on(parity_senders::FLAG_MANAGER_COPY);
        [$learner, $manager, $course] = $this->learner_with_manager();
        $this->assertTrue(parity_senders::manager_completion_copy($learner, $course));

        $rows = $this->logs();
        $this->assertCount(1, $rows);
        $this->assertSame((int) $manager->id, (int) $rows[0]->userid);
        // Every text column of the row, and the learner's name, e-mail and username, in either case.
        $text = strtolower(implode("\n", array_map('strval', (array) $rows[0])));
        foreach ([$learner->firstname, $learner->lastname, $learner->email, $learner->username] as $needle) {
            $this->assertStringNotContainsString(strtolower((string) $needle), $text, 'the log row names the learner');
        }
        $this->assertNull($rows[0]->sender_userid, 'no sender column points at the learner either');
        // Nothing of the learner is anywhere in the log table (it has no column that holds the learner's id).
        $this->assertSame(0, $DB->count_records(self::LOG, ['userid' => (int) $learner->id]));
    }

    public function test_the_log_subject_option_changes_what_is_logged_and_never_what_is_sent(): void {
        $this->on(parity_senders::FLAG_MANAGER_COPY);
        [$learner, $manager, $course] = $this->learner_with_manager();
        $rule = parity_senders::rule_for(parity_senders::RULE_MANAGER_COPY, $manager);
        $context = [
            'firstname' => $manager->firstname, 'member_name' => 'Priya Singh', 'course_name' => $course->fullname,
            'course_url' => 'https://example.test/c', 'completion_date' => '1 January 2026', 'team_url' => 'https://example.test/t',
            'subject' => 'Priya Singh has completed X',
        ];
        $withlog = notification_sender::send($rule, $manager, $context, (int) $course->id, ['log_subject' => 'Neutral']);
        $plain = notification_sender::send($rule, $manager, $context, (int) $course->id);
        $this->assertSame('suppressed', $withlog[0]['status']);
        $rows = $this->logs();
        $this->assertCount(2, $rows);
        $this->assertSame('Neutral', $rows[0]->subject, 'the option replaces the logged subject');
        $this->assertSame('Priya Singh has completed X', $rows[1]->subject, 'without the option the sent subject is logged, as before');
        $this->assertSame('suppressed', $plain[0]['status']);
    }

    public function test_the_course_completed_observer_sends_the_manager_copy_without_a_learner_rule(): void {
        global $DB;
        $this->on(parity_senders::FLAG_MANAGER_COPY);
        [$learner, $manager, $course] = $this->learner_with_manager();
        $this->assertSame(0, $DB->count_records('local_sentientia_email_rules', ['rule_type' => 'course_completed']),
            'no learner rule: the manager copy does not depend on one');

        $completion = (int) $DB->insert_record('course_completions', (object) [
            'userid' => $learner->id, 'course' => $course->id, 'timeenrolled' => time() - 100, 'timestarted' => time() - 50,
            'timecompleted' => time(), 'reaggregate' => 0,
        ]);
        \core\event\course_completed::create([
            'objectid' => $completion,
            'relateduserid' => $learner->id,
            'context' => \context_course::instance($course->id),
            'courseid' => $course->id,
            'other' => ['relateduserid' => $learner->id],
        ])->trigger();

        $rows = $this->logs(['template_key' => 'enrollment/manager_course_completed']);
        $this->assertCount(1, $rows);
        $this->assertSame((int) $manager->id, (int) $rows[0]->userid);
    }

    public function test_no_copy_goes_to_a_missing_suspended_or_other_tenant_supervisor(): void {
        global $DB;
        $this->on(parity_senders::FLAG_MANAGER_COPY);

        [$learner, $manager, $course] = $this->learner_with_manager();
        $DB->set_field('user', 'suspended', 1, ['id' => $manager->id]);
        $this->assertFalse(parity_senders::manager_completion_copy($learner, $course), 'a suspended supervisor');

        [$learner2, , $course2] = $this->learner_with_manager();
        $DB->set_field('user', 'open_supervisorid', 0, ['id' => $learner2->id]);
        $learner2 = $DB->get_record('user', ['id' => $learner2->id], '*', MUST_EXIST);
        $this->assertFalse(parity_senders::manager_completion_copy($learner2, $course2), 'no supervisor');

        // ADR-031: a person's name and progress do not cross a tenant boundary, and a tenant that does not resolve gets nothing.
        [$learner3, , $course3] = $this->learner_with_manager('/1/5', '/77');
        $this->assertFalse(parity_senders::manager_completion_copy($learner3, $course3), 'a supervisor of another tenant');
        [$learner4, , $course4] = $this->learner_with_manager('', '');
        $this->assertFalse(parity_senders::manager_completion_copy($learner4, $course4), 'neither has a tenant');
        $this->assertSame([], $this->logs());
    }

    public function test_the_manager_copy_has_its_own_rule_type_and_can_be_switched_off(): void {
        global $DB;
        $this->on(parity_senders::FLAG_MANAGER_COPY);
        [$learner, , $course] = $this->learner_with_manager();
        $DB->insert_record('local_sentientia_email_rules', (object) [
            'rule_name' => 'Manager copy', 'rule_type' => parity_senders::RULE_MANAGER_COPY, 'tenant_id' => 0,
            'channel' => 'email', 'audience' => 'manager', 'template_key' => 'enrollment/manager_course_completed',
            'enabled' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertFalse(parity_senders::manager_completion_copy($learner, $course));
        $this->assertSame([], $this->logs());
    }

    // COMMS-N7: a learner enrolled in a learning path. The path plugin fires no event, so a task polls its table.

    /**
     * A path with one course, and the id of the course.
     *
     * @param array $more Columns of the path.
     * @return array{0: int, 1: \stdClass} The path id and the course.
     */
    private function path(array $more = []): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_learningpath_users')) {
            $this->markTestSkipped('the learning-path plugin is not installed on this test site');
        }
        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $pathid = (int) $DB->insert_record('local_sentientia_learningpath', (object) ($more + [
            'name' => 'HR Onboarding', 'status' => 1, 'visible' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]));
        $DB->insert_record('local_sentientia_learningpath_courses', (object) [
            'pathid' => $pathid, 'courseid' => $course->id, 'sortorder' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        return [$pathid, $course];
    }

    /**
     * @param int $pathid
     * @param int $userid
     * @param array $more
     * @return int The enrolment id.
     */
    private function enrol_in_path(int $pathid, int $userid, array $more = []): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_learningpath_users', (object) ($more + [
            'pathid' => $pathid, 'userid' => $userid, 'status' => 0, 'enrolledby' => 2, 'timecreated' => $now,
            'timemodified' => $now,
        ]));
    }

    public function test_the_first_run_of_the_poller_only_sets_its_marker(): void {
        [$pathid] = $this->path();
        $user = $this->user_at('/1/5');
        $existing = $this->enrol_in_path($pathid, (int) $user->id);
        $this->on(parity_senders::FLAG_PATH_ENROLMENT);

        $this->assertFalse(get_config('local_sentientia_emails', parity_senders::CONFIG_PATH_WATERMARK));
        $this->assertSame(0, parity_senders::send_pending_path_enrolments(), 'the back catalogue is never e-mailed');
        $this->assertSame($existing, (int) get_config('local_sentientia_emails', parity_senders::CONFIG_PATH_WATERMARK));
        $this->assertSame([], $this->logs());
    }

    public function test_a_new_path_enrolment_is_emailed_once_when_the_flag_is_on(): void {
        [$pathid, $course] = $this->path(['enddate' => time() + 10 * DAYSECS]);
        $user = $this->user_at('/1/5');
        $this->on(parity_senders::FLAG_PATH_ENROLMENT);
        parity_senders::send_pending_path_enrolments();   // First run: the marker.
        $id = $this->enrol_in_path($pathid, (int) $user->id);

        $this->assertSame(1, parity_senders::send_pending_path_enrolments());
        $rows = $this->logs();
        $this->assertCount(1, $rows);
        $this->assertSame((int) $user->id, (int) $rows[0]->userid);
        $this->assertSame('enrollment/learning_path_enrolled', $rows[0]->template_key);
        $this->assertSame('New learning path: HR Onboarding', $rows[0]->subject);
        $this->assertSame('suppressed', $rows[0]->status);
        $this->assertSame($id, (int) get_config('local_sentientia_emails', parity_senders::CONFIG_PATH_WATERMARK));

        $this->assertSame(0, parity_senders::send_pending_path_enrolments(), 'the marker has moved past it');
        $this->assertCount(1, $this->logs());
    }

    public function test_with_the_flag_off_the_marker_still_moves_so_a_later_flip_never_sends_old_enrolments(): void {
        [$pathid] = $this->path();
        $user = $this->user_at('/1/5');
        parity_senders::send_pending_path_enrolments();
        $id = $this->enrol_in_path($pathid, (int) $user->id);

        $this->assertSame(0, parity_senders::send_pending_path_enrolments());
        $this->assertSame($id, (int) get_config('local_sentientia_emails', parity_senders::CONFIG_PATH_WATERMARK));
        $this->on(parity_senders::FLAG_PATH_ENROLMENT);
        $this->assertSame(0, parity_senders::send_pending_path_enrolments(), 'the enrolment was made while the flag was OFF');
        $this->assertSame([], $this->logs());
    }

    public function test_with_the_flag_off_the_poller_jumps_to_the_highest_id_without_reading_the_rows(): void {
        [$pathid] = $this->path();
        parity_senders::send_pending_path_enrolments();   // First run: the marker.
        $last = 0;
        foreach (range(1, 5) as $unused) {
            $last = $this->enrol_in_path($pathid, (int) $this->user_at('/1/5')->id);
        }
        $this->assertSame(0, parity_senders::send_pending_path_enrolments());
        $this->assertSame($last, (int) get_config('local_sentientia_emails', parity_senders::CONFIG_PATH_WATERMARK),
            'one jump to the highest id');
        $this->assertSame(0, parity_senders::send_pending_path_enrolments(), 'a run with nothing new changes nothing');
        $this->assertSame($last, (int) get_config('local_sentientia_emails', parity_senders::CONFIG_PATH_WATERMARK));
        $this->assertSame([], $this->logs());
    }

    public function test_a_mixed_batch_moves_the_marker_to_its_last_row_and_mails_only_the_new_enrolments(): void {
        [$pathid] = $this->path();
        $this->on(parity_senders::FLAG_PATH_ENROLMENT);
        parity_senders::send_pending_path_enrolments();   // First run: the marker.
        $old = $this->enrol_in_path($pathid, (int) $this->user_at('/1/5')->id,
            ['timecreated' => time() - parity_senders::PATH_ROW_MAX_AGE - 60]);
        $new = $this->enrol_in_path($pathid, (int) $this->user_at('/1/5')->id);
        $skipped = $this->enrol_in_path($pathid, (int) $this->user_at('/1/5', ['suspended' => 1])->id);

        $this->assertSame(1, parity_senders::send_pending_path_enrolments());
        $this->assertCount(1, $this->logs());
        $this->assertGreaterThan($new, $skipped);
        $this->assertGreaterThan($old, $new);
        $this->assertSame($skipped, (int) get_config('local_sentientia_emails', parity_senders::CONFIG_PATH_WATERMARK),
            'the marker ends on the last row looked at, a skipped one included');
        $this->assertSame(0, parity_senders::send_pending_path_enrolments(), 'nothing is looked at twice');
        $this->assertCount(1, $this->logs());
    }

    public function test_an_old_an_imported_or_an_archived_path_enrolment_is_not_emailed(): void {
        global $DB;
        [$pathid] = $this->path();
        [$archived] = $this->path(['status' => 0]);
        $user = $this->user_at('/1/5');
        $this->on(parity_senders::FLAG_PATH_ENROLMENT);
        parity_senders::send_pending_path_enrolments();

        $old = $this->enrol_in_path($pathid, (int) $this->user_at('/1/5')->id,
            ['timecreated' => time() - parity_senders::PATH_ROW_MAX_AGE - 60]);
        $imported = $this->enrol_in_path($pathid, (int) $this->user_at('/1/5')->id);
        $DB->insert_record(legacymap::TABLE, (object) [
            'feature' => 'learningplan', 'sourcetable' => 'local_learningplan_users', 'sourceid' => 1, 'subkey' => '',
            'targettable' => 'local_sentientia_learningpath_users', 'targetid' => $imported, 'outcome' => 'imported',
            'runid' => 0, 'timecreated' => time(),
        ]);
        $this->enrol_in_path($archived, (int) $user->id);

        $this->assertSame(0, parity_senders::send_pending_path_enrolments());
        $this->assertSame([], $this->logs());
        $this->assertGreaterThanOrEqual($old, (int) get_config('local_sentientia_emails', parity_senders::CONFIG_PATH_WATERMARK));
    }

    public function test_the_scheduled_task_runs_the_poller(): void {
        [$pathid] = $this->path();
        $user = $this->user_at('/1/5');
        $this->on(parity_senders::FLAG_PATH_ENROLMENT);
        $task = new \local_sentientia_emails\task\send_path_enrolments();
        $this->assertNotEmpty($task->get_name());
        ob_start();
        $task->execute();   // First run: the marker.
        $this->enrol_in_path($pathid, (int) $user->id);
        $task->execute();
        ob_end_clean();
        $this->assertCount(1, $this->logs());
    }

    // The templates the senders use.

    public function test_every_sender_template_exists_and_renders(): void {
        foreach (['enrollment/course_enrolled', 'enrollment/learning_path_enrolled', 'enrollment/manager_course_completed'] as $key) {
            $context = email_context::get_sample($key);
            $html = email_renderer::render('local_sentientia_emails/' . $key, $context);
            $this->assertNotSame('', trim($html), $key);
        }
        $keys = [];
        foreach (email_renderer::get_template_list() as $category) {
            foreach ($category['templates'] as $template) {
                $keys[] = $template['key'];
            }
        }
        $this->assertContains('enrollment/manager_course_completed', $keys, 'the editor and the preview list it');
    }

    public function test_the_learning_path_email_shows_no_deadline_for_a_path_without_one(): void {
        $context = email_context::get_sample('enrollment/learning_path_enrolled');
        unset($context['deadline_date'], $context['deadline_days']);
        $html = email_renderer::render('local_sentientia_emails/enrollment/learning_path_enrolled', $context);
        $this->assertStringNotContainsString('Deadline', $html);
        $this->assertStringNotContainsString('avoid escalation', $html);
        $with = email_renderer::render('local_sentientia_emails/enrollment/learning_path_enrolled',
            email_context::get_sample('enrollment/learning_path_enrolled'));
        $this->assertStringContainsString('Deadline', $with);
    }
}
