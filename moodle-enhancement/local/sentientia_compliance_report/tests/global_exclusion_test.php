<?php
// This file is part of Sentientia LMS.

/**
 * An all-courses exclusion in the compliance snapshot (2026-11-17).
 *
 * exclude_user() (the Configure tab's "Excluded Users") writes one
 * local_compliance_exemptions row with courseid 0, "excluded from ALL
 * compliance tracking". rebuild_snapshot() looked exemptions up by the exact
 * (user, course) pair, so that row matched no course: the learner was
 * auto-enrolled into every applicable compliance course, sent the welcome and
 * reminder mails, escalated to their manager when overdue, and counted as
 * overdue in every report. It now exempts them from every course that applies
 * to their tenant, exactly as a per-course exemption does for one course.
 *
 * Fixture: tenant 1 learners at /1/2 with managers at /1, tenant 177 learners
 * at /177/178. Four tracked courses:
 *   global    costcenterid 0    (every tenant)
 *   airpay    costcenterid 1
 *   induction costcenterid 1
 *   zeea      costcenterid 177
 * The site admin (no open_path, tenant 0) is processed for the global course
 * too, so message assertions always filter by recipient.
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
final class global_exclusion_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** Component of the plugin's own compliance messages. */
    private const COMPONENT = 'local_sentientia_compliance_report';

    /** @var \stdClass[] tracked courses by name: global, airpay, induction, zeea */
    private $c = [];

    /** @var \phpunit_message_sink open for the whole test, so fixture enrolments send nothing either */
    private $sink;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->sink = $this->redirectMessages();
        $this->setAdminUser();
        // auto_enrol is 1 on the imported production copy.
        set_config('auto_enrol', 1, self::COMPONENT);

        $sort = 0;
        foreach (['global' => 0, 'airpay' => 1, 'induction' => 1, 'zeea' => 177] as $name => $tenant) {
            $course = $this->getDataGenerator()->create_course(['fullname' => 'Course ' . $name]);
            $this->track($course, $tenant, ++$sort);
            $this->c[$name] = $course;
        }
    }

    /** Put a course on the compliance list. */
    private function track(\stdClass $course, int $costcenterid, int $sort): void {
        global $DB;
        $DB->insert_record('local_compliance_courses', (object) [
            'courseid' => $course->id, 'coursename' => $course->fullname, 'deadline_days' => 30,
            'costcenterid' => $costcenterid, 'is_active' => 1, 'sort_order' => $sort,
            'createdby' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /** A user at a BizLMS org path, optionally reporting to someone. */
    private function person(string $path, ?\stdClass $manager = null): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        if ($manager) {
            $DB->set_field('user', 'open_supervisorid', $manager->id, ['id' => $u->id]);
        }
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** An exemption row as the report writes it; courseid 0 = all courses. */
    private function exempt(\stdClass $user, int $courseid, ?int $expiry = null): int {
        global $DB;
        return (int) $DB->insert_record('local_compliance_exemptions', (object) [
            'userid' => $user->id, 'courseid' => $courseid, 'reason' => 'test',
            'approved_by' => 0, 'expiry_date' => $expiry, 'is_active' => 1,
            'timecreated' => time(),
        ]);
    }

    /** Enrol a user as if it happened $daysago days ago. */
    private function enrolled_days_ago(\stdClass $user, string $course, int $daysago): void {
        $this->getDataGenerator()->enrol_user($user->id, $this->c[$course]->id, 'student', 'manual',
            time() - $daysago * DAYSECS);
    }

    /** Rebuild the snapshot; returns only the messages the rebuild sent. */
    private function rebuild(): array {
        $this->sink->clear();
        compliance_engine::rebuild_snapshot();
        return $this->sink->get_messages();
    }

    /** Messages to one user, optionally from one component only. */
    private function to(array $messages, \stdClass $user, ?string $component = null): array {
        return array_values(array_filter($messages, fn($m) => (int) $m->useridto === (int) $user->id
            && ($component === null || $m->component === $component)));
    }

    /** Snapshot status by course name for a user; only names that have a row. */
    private function statuses(\stdClass $user): array {
        global $DB;
        $bycourse = $DB->get_records_menu('local_compliance_snapshot', ['userid' => $user->id], '',
            'courseid, status');
        $out = [];
        foreach ($this->c as $name => $course) {
            if (isset($bycourse[$course->id])) {
                $out[$name] = $bycourse[$course->id];
            }
        }
        return $out;
    }

    /** Any enrolment row at all, active or not (the rule rebuild_snapshot() uses). */
    private function is_enrolled(\stdClass $user, string $course): bool {
        global $DB;
        return $DB->record_exists_sql(
            "SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid",
            ['cid' => $this->c[$course]->id, 'uid' => $user->id]);
    }

    /** The plugin's email log for a learner, as sorted "type/recipient" strings. */
    private function logged(\stdClass $user): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('local_compliance_email_log', ['userid' => $user->id]) as $row) {
            $out[] = $row->email_type . '/' . $row->sent_to;
        }
        sort($out);
        return $out;
    }

    /**
     * The defect: an excluded learner beside an identical learner who is not.
     *
     * Each is enrolled in global 10 days ago (not started: reminder1 due),
     * in airpay 37 days ago (7 days overdue: weekly escalation to the manager
     * due) and not in induction (auto-enrol + welcome due).
     */
    public function test_excluded_learner_is_exempted_everywhere_and_never_mailed(): void {
        $mgrexcluded = $this->person('/1');
        $mgrcontrol = $this->person('/1');
        $excluded = $this->person('/1/2', $mgrexcluded);
        $control = $this->person('/1/2', $mgrcontrol);
        foreach ([$excluded, $control] as $u) {
            $this->enrolled_days_ago($u, 'global', 10);
            $this->enrolled_days_ago($u, 'airpay', 37);
        }
        compliance_engine::exclude_user((int) $excluded->id, 'Operations exclusion');

        $messages = $this->rebuild();

        // Excluded: exempted for every course of their tenant, nothing else.
        $this->assertSame(
            ['global' => 'exempted', 'airpay' => 'exempted', 'induction' => 'exempted'],
            $this->statuses($excluded));
        $this->assertFalse($this->is_enrolled($excluded, 'induction'), 'auto-enrolled despite the exclusion');
        $this->assertFalse($this->is_enrolled($excluded, 'zeea'));
        $this->assertSame([], $this->to($messages, $excluded), 'a message reached the excluded learner');
        $this->assertSame([], $this->logged($excluded), 'send_escalation() ran for the excluded learner');
        foreach ($this->to($messages, $mgrexcluded, self::COMPONENT) as $m) {
            // The manager's own welcome mails are fine; an escalation about the learner is not.
            $this->assertStringStartsWith('Enrolled: ', $m->subject);
        }

        // Control: the same learner without the exclusion still gets everything.
        $this->assertSame(
            ['global' => 'not_started', 'airpay' => 'overdue', 'induction' => 'not_started'],
            $this->statuses($control));
        $this->assertTrue($this->is_enrolled($control, 'induction'));
        $this->assertSame(['reminder1/employee', 'weekly_escalation/manager', 'welcome/employee'],
            $this->logged($control));
        $this->assertCount(2, $this->to($messages, $control, self::COMPONENT));
        $escalations = array_filter($this->to($messages, $mgrcontrol, self::COMPONENT),
            fn($m) => str_starts_with($m->subject, 'Weekly overdue: '));
        $this->assertCount(1, $escalations);

        // How the excluded learner shows in the reports.
        $defaulters = array_map('intval', array_column(compliance_engine::get_defaulters('/1'), 'userid'));
        $this->assertNotContains((int) $excluded->id, $defaulters);
        $this->assertContains((int) $control->id, $defaulters);
        $kpis = compliance_engine::get_summary_kpis('/1');
        $this->assertEquals(3, $kpis['exempted']);
        $this->assertEquals(1, $kpis['overdue']);
        $matrix = compliance_engine::get_compliance_matrix('/1');
        $rows = array_column($matrix['rows'], null, 'userid');
        $this->assertSame(['exempted', 'exempted', 'exempted'],
            array_column($rows[$excluded->id]['courses'], 'status'));
        $this->assertTrue($rows[$excluded->id]['all_completed']);
    }

    /** A per-course exemption still covers that course only. */
    public function test_per_course_exemption_still_works(): void {
        $u = $this->person('/1/2');
        $this->exempt($u, (int) $this->c['airpay']->id);

        $messages = $this->rebuild();

        $this->assertSame(
            ['global' => 'not_started', 'airpay' => 'exempted', 'induction' => 'not_started'],
            $this->statuses($u));
        $this->assertFalse($this->is_enrolled($u, 'airpay'));
        $this->assertTrue($this->is_enrolled($u, 'global'));
        $this->assertTrue($this->is_enrolled($u, 'induction'));
        $this->assertSame(['welcome/employee', 'welcome/employee'], $this->logged($u));
        $this->assertCount(2, $this->to($messages, $u, self::COMPONENT));
    }

    /**
     * Same is_active / expiry rule as a per-course exemption: an expired
     * all-courses row lets the learner back in (and is switched off); one that
     * expires later still applies.
     */
    public function test_expired_exclusion_lets_the_learner_back_in(): void {
        global $DB;
        $expired = $this->person('/1/2');
        $current = $this->person('/1/2');
        $expiredrow = $this->exempt($expired, 0, time() - 60);
        $currentrow = $this->exempt($current, 0, time() + DAYSECS);

        $messages = $this->rebuild();

        $this->assertSame(
            ['global' => 'not_started', 'airpay' => 'not_started', 'induction' => 'not_started'],
            $this->statuses($expired));
        $this->assertTrue($this->is_enrolled($expired, 'airpay'));
        $this->assertCount(3, $this->to($messages, $expired, self::COMPONENT));
        $this->assertEquals(0, $DB->get_field('local_compliance_exemptions', 'is_active', ['id' => $expiredrow]));

        $this->assertSame(
            ['global' => 'exempted', 'airpay' => 'exempted', 'induction' => 'exempted'],
            $this->statuses($current));
        $this->assertFalse($this->is_enrolled($current, 'global'));
        $this->assertSame([], $this->to($messages, $current));
        $this->assertEquals(1, $DB->get_field('local_compliance_exemptions', 'is_active', ['id' => $currentrow]));
    }

    /** include_user() ends the exclusion: the next rebuild tracks the learner again. */
    public function test_include_user_lets_the_learner_back_in(): void {
        $u = $this->person('/1/2');
        compliance_engine::exclude_user((int) $u->id, 'temp account');
        $this->assertContains((int) $u->id,
            array_map('intval', array_column(compliance_engine::get_excluded_users(), 'userid')));

        $first = $this->rebuild();
        $this->assertSame(
            ['global' => 'exempted', 'airpay' => 'exempted', 'induction' => 'exempted'],
            $this->statuses($u));
        $this->assertSame([], $this->to($first, $u));

        compliance_engine::include_user((int) $u->id);
        $this->assertSame([], compliance_engine::get_excluded_users());

        $second = $this->rebuild();
        // The exempted rows are overwritten, not left behind.
        $this->assertSame(
            ['global' => 'not_started', 'airpay' => 'not_started', 'induction' => 'not_started'],
            $this->statuses($u));
        $this->assertTrue($this->is_enrolled($u, 'induction'));
        $this->assertSame(['welcome/employee', 'welcome/employee', 'welcome/employee'], $this->logged($u));
        $this->assertCount(3, $this->to($second, $u, self::COMPONENT));
    }

    /**
     * Tenant scope is checked before the exclusion: an excluded learner gets
     * rows only for courses of their own tenant, and another tenant's learner
     * is untouched by it.
     */
    public function test_exclusion_stays_inside_the_learners_tenant(): void {
        $airpayexcluded = $this->person('/1/2');
        $zeeaexcluded = $this->person('/177/178');
        $zeea = $this->person('/177/178');
        compliance_engine::exclude_user((int) $airpayexcluded->id);
        compliance_engine::exclude_user((int) $zeeaexcluded->id);

        $messages = $this->rebuild();

        $this->assertSame(['global' => 'exempted', 'zeea' => 'exempted'], $this->statuses($zeeaexcluded));
        $this->assertSame(
            ['global' => 'exempted', 'airpay' => 'exempted', 'induction' => 'exempted'],
            $this->statuses($airpayexcluded));
        $this->assertSame([], $this->to($messages, $zeeaexcluded));
        $this->assertSame([], $this->to($messages, $airpayexcluded));

        // The other tenant's learner: tracked as before, own tenant's courses only.
        $this->assertSame(['global' => 'not_started', 'zeea' => 'not_started'], $this->statuses($zeea));
        $this->assertTrue($this->is_enrolled($zeea, 'zeea'));
        $this->assertFalse($this->is_enrolled($zeea, 'airpay'));
        $this->assertSame(['welcome/employee', 'welcome/employee'], $this->logged($zeea));
        $this->assertCount(2, $this->to($messages, $zeea, self::COMPONENT));
    }
}
