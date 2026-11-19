<?php
// This file is part of Sentientia LMS.

/**
 * D4 data fix (cli/pin_compliance_courses.php), post-demo plan session 1.
 *
 * Fixture: the five Airpay compliance courses as imported, each owned by
 * tenant 1 (course.open_path '/1') and tracked for all tenants
 * (costcenterid 0), plus one more tracked course that is not part of D4.
 * Course ids are whatever the generator gives: the script finds courses by
 * name, never by id.
 *
 * @package    local_sentientia_compliance_report
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_compliance_report;

use local_sentientia_compliance_report\local\compliance_pinning;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_compliance_report\local\compliance_pinning
 */
final class compliance_pinning_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** The imported names (fullname, shortname). */
    private const COURSES = [
        'posh'     => ['POSH Training 2025', 'PT2025'],
        'infosec'  => ['IT and Information Security Awareness Training', 'ISAI01'],
        'aml'      => ['Anti Money Laundering', 'AMLI01'],
        'phishing' => ['Phishing Awareness Training', 'PAT01'],
        'posh_ic'  => ['POSH Training for Internal Committee Members', 'CS01'],
    ];

    /** @var \stdClass[] courses by key, plus 'other' */
    private $c = [];

    /** @var int[] compliance row ids by key, plus 'other' */
    private $row = [];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        $this->redirectMessages();
        set_config('auto_enrol', 0, 'local_sentientia_compliance_report');

        $sort = 0;
        foreach (self::COURSES + ['other' => ['Code of Conduct', 'COC01']] as $key => [$fullname, $shortname]) {
            $this->c[$key] = $this->course($fullname, $shortname, '/1');
            $this->row[$key] = $this->track($this->c[$key], 0, ++$sort);
        }
    }

    private function course(string $fullname, string $shortname, string $path): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['fullname' => $fullname, 'shortname' => $shortname]);
        if ($path !== '') {
            $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        }
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    private function track(\stdClass $course, int $costcenterid, int $sort, int $active = 1): int {
        global $DB;
        return (int) $DB->insert_record('local_compliance_courses', (object) [
            'courseid' => $course->id, 'coursename' => $course->fullname, 'deadline_days' => 30,
            'costcenterid' => $costcenterid, 'is_active' => $active, 'sort_order' => $sort,
            'createdby' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function rowfield(int $rowid, string $field) {
        global $DB;
        return $DB->get_field('local_compliance_courses', $field, ['id' => $rowid], MUST_EXIST);
    }

    /**
     * @dataProvider classify_provider
     */
    public function test_classify(string $fullname, string $shortname, array $expected): void {
        $this->assertSame($expected, compliance_pinning::classify($fullname, $shortname));
    }

    public static function classify_provider(): array {
        return [
            'posh 2025'      => ['POSH Training 2025', 'PT2025', ['posh']],
            'ic course'      => ['POSH Training for Internal Committee Members', 'CS01', ['posh_ic']],
            'ic short form'  => ['POSH for IC Members', 'POSHIC', ['posh_ic']],
            'infosec'        => ['IT and Information Security Awareness Training', 'ISAI01', ['infosec']],
            'aml'            => ['Anti Money Laundering', 'AMLI01', ['aml']],
            'aml acronym'    => ['AML refresher', 'X1', ['aml']],
            'phishing'       => ['Phishing Awareness Training', 'PAT01', ['phishing']],
            'posh 2024'      => ['POSH Training 2024', 'PT2024', []],
            'unrelated'      => ['Code of Conduct', 'COC01', []],
            'lower-case ic'  => ['POSH basics for the public', 'pb', []],
            'two groups'     => ['Phishing and AML basics', 'PA1', ['aml', 'phishing']],
        ];
    }

    /** The dry run finds the five courses by name, plans the pins and writes nothing. */
    public function test_dry_run_plans_and_writes_nothing(): void {
        global $DB;
        $before = $DB->get_records('local_compliance_courses', null, 'id');

        $plan = compliance_pinning::plan();

        $this->assertSame([], $plan['problems']);
        $this->assertSame(compliance_pinning::KEYS, array_keys($plan['groups']));
        foreach (compliance_pinning::KEYS as $key) {
            $g = $plan['groups'][$key];
            $this->assertSame((int) $this->c[$key]->id, (int) $g['course']->id, $key);
            $this->assertSame(1, $g['tenant']);
            $this->assertSame([['type' => 'pin', 'rowid' => $this->row[$key], 'tenant' => 1,
                'target' => $this->row[$key]]], $g['actions']);
        }
        $this->assertSame([(int) $this->c['other']->id], array_keys($plan['unmatched']));
        $this->assertFalse($plan['cohort']['exists']);
        $this->assertSame(compliance_pinning::DEFAULT_COHORT_IDNUMBER, $plan['cohort']['idnumber']);

        $text = implode("\n", compliance_pinning::render($plan, false, false));
        $this->assertStringContainsString('DRY RUN', $text);
        $this->assertStringContainsString('PT2025', $text);
        $this->assertStringContainsString('would create', $text);

        $this->assertEquals($before, $DB->get_records('local_compliance_courses', null, 'id'));
        $this->assertFalse($DB->record_exists('cohort', ['idnumber' => compliance_pinning::DEFAULT_COHORT_IDNUMBER]));
    }

    /** --execute pins the four, restricts the IC row to a new empty cohort, and a second run changes nothing. */
    public function test_execute_pins_restricts_and_is_idempotent(): void {
        global $DB;

        $done = compliance_pinning::execute(compliance_pinning::plan());

        foreach (compliance_pinning::KEYS as $key) {
            $this->assertEquals(1, $this->rowfield($this->row[$key], 'costcenterid'), $key);
            $this->assertEquals(1, $this->rowfield($this->row[$key], 'is_active'), $key);
        }
        $cohort = $DB->get_record('cohort', ['idnumber' => compliance_pinning::DEFAULT_COHORT_IDNUMBER], '*', MUST_EXIST);
        $this->assertEquals(\context_system::instance()->id, $cohort->contextid);
        $this->assertSame(0, $DB->count_records('cohort_members', ['cohortid' => $cohort->id]));
        $this->assertEquals($cohort->id, $this->rowfield($this->row['posh_ic'], 'cohortid'));
        foreach (['posh', 'infosec', 'aml', 'phishing', 'other'] as $key) {
            $this->assertEquals(0, $this->rowfield($this->row[$key], 'cohortid'), $key);
        }
        // Not part of D4: untouched.
        $this->assertEquals(0, $this->rowfield($this->row['other'], 'costcenterid'));
        $this->assertNotEmpty($done);

        // Second run: everything is already in place.
        $plan = compliance_pinning::plan();
        $this->assertSame([], $plan['problems']);
        $this->assertTrue($plan['cohort']['exists']);
        foreach ($plan['groups'] as $g) {
            $this->assertSame(['keep'], array_column($g['actions'], 'type'));
        }
        $this->assertSame([], compliance_pinning::execute($plan));
        $this->assertSame(1, $DB->count_records('cohort', ['idnumber' => compliance_pinning::DEFAULT_COHORT_IDNUMBER]));
    }

    /** A tenant row that already exists (unique courseid + costcenterid) is switched on instead. */
    public function test_existing_tenant_row_is_used(): void {
        $pinned = $this->track($this->c['aml'], 1, 99, 0);

        $plan = compliance_pinning::plan();
        $this->assertSame([['type' => 'merge', 'rowid' => $this->row['aml'], 'into' => $pinned,
            'reactivate' => true, 'target' => $pinned]], $plan['groups']['aml']['actions']);

        compliance_pinning::execute($plan);

        $this->assertEquals(0, $this->rowfield($this->row['aml'], 'is_active'));
        $this->assertEquals(0, $this->rowfield($this->row['aml'], 'costcenterid'));
        $this->assertEquals(1, $this->rowfield($pinned, 'is_active'));
    }

    /** Two courses for one group: that group is reported and left alone; the others go ahead. */
    public function test_ambiguous_group_is_left_alone(): void {
        $second = $this->course('Phishing Awareness Refresher', 'PAR01', '/1');
        $secondrow = $this->track($second, 0, 50);

        $plan = compliance_pinning::plan();
        $this->assertArrayNotHasKey('phishing', $plan['groups']);
        $this->assertStringContainsString('Several courses match Phishing awareness', implode("\n", $plan['problems']));

        compliance_pinning::execute($plan);

        $this->assertEquals(0, $this->rowfield($this->row['phishing'], 'costcenterid'));
        $this->assertEquals(0, $this->rowfield($secondrow, 'costcenterid'));
        $this->assertEquals(1, $this->rowfield($this->row['posh'], 'costcenterid'));
    }

    /** A course with no tenant is left alone unless the operator names one; a row pinned elsewhere is left alone. */
    public function test_tenant_resolution(): void {
        global $DB;
        $DB->set_field('course', 'open_path', null, ['id' => $this->c['infosec']->id]);
        $DB->set_field('local_compliance_courses', 'costcenterid', 177, ['id' => $this->row['phishing']]);

        $plan = compliance_pinning::plan();
        $this->assertArrayNotHasKey('infosec', $plan['groups']);
        $this->assertStringContainsString('has no tenant', implode("\n", $plan['problems']));
        $this->assertSame([['type' => 'foreign', 'rowid' => $this->row['phishing'], 'pin' => 177, 'target' => 0]],
            $plan['groups']['phishing']['actions']);

        compliance_pinning::execute(compliance_pinning::plan(['tenant' => 1]));
        $this->assertEquals(1, $this->rowfield($this->row['infosec'], 'costcenterid'));
        $this->assertEquals(177, $this->rowfield($this->row['phishing'], 'costcenterid'));

        $bad = compliance_pinning::plan(['tenant' => 999]);
        $this->assertStringContainsString('not a known tenant', implode("\n", $bad['problems']));
    }

    /** An existing cohort is reused, and its members are counted. */
    public function test_existing_cohort_is_reused(): void {
        $cohort = $this->getDataGenerator()->create_cohort(['idnumber' => 'ic_members_2026']);
        $member = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);
        $this->getDataGenerator()->enrol_user($member->id, $this->c['posh_ic']->id, 'student');
        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->c['posh_ic']->id, 'student');

        $plan = compliance_pinning::plan(['cohortidnumber' => 'ic_members_2026']);
        $this->assertTrue($plan['cohort']['exists']);
        $this->assertSame(1, $plan['cohort']['members']);
        $this->assertSame(1, $plan['cohort']['enrolled_outside']);

        compliance_pinning::execute($plan);
        $this->assertEquals($cohort->id, $this->rowfield($this->row['posh_ic'], 'cohortid'));
    }

    /** A user at a BizLMS org path. */
    private function person(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * Only manual enrolments whose end is start + deadline days are
     * deadline-based; they are cleared with --clear-enddates only, and only
     * for people the course reaches. A Public or ZEEA user the old refresh
     * enrolled into an Airpay course keeps the ended enrolment that keeps
     * them out, unless the course is shared to their tenant.
     */
    public function test_deadline_end_dates(): void {
        global $DB;
        $course = $this->c['aml'];
        $start = time() - 40 * DAYSECS;
        $gen = $this->getDataGenerator();
        $locked = $this->person('/1/2');
        $custom = $this->person('/1/2');
        $selfenrolled = $this->person('/1/2');
        $publicuser = $this->person('/77');
        $zeeauser = $this->person('/177/178');
        $notenant = $gen->create_user();
        $this->assertTrue($gen->enrol_user($locked->id, $course->id, 'student', 'manual', $start, $start + 30 * DAYSECS));
        $this->assertTrue($gen->enrol_user($custom->id, $course->id, 'student', 'manual', $start, $start + 10 * DAYSECS));
        foreach ([$publicuser, $zeeauser, $notenant] as $outsider) {
            $this->assertTrue($gen->enrol_user($outsider->id, $course->id, 'student', 'manual',
                $start, $start + 30 * DAYSECS));
        }
        $selfinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$selfinstance) {
            enrol_get_plugin('self')->add_instance($course);
        }
        $this->assertTrue($gen->enrol_user($selfenrolled->id, $course->id, 'student', 'self', $start, $start + 30 * DAYSECS));

        $plan = compliance_pinning::plan();
        $entry = $plan['enddates'][$course->id];
        $this->assertSame([(int) $locked->id], array_map(fn($ue) => (int) $ue->userid, $entry['matching']));
        $this->assertSame(1, $entry['expired']);
        $this->assertSame(0, $entry['suspended']);
        $this->assertSame(3, $entry['outside']);
        $this->assertSame(2, $entry['other']);
        $text = implode("\n", compliance_pinning::render($plan, false, true));
        $this->assertStringContainsString('3 deadline end dates belong to people outside', $text);
        $this->assertStringContainsString('1 end dates would be cleared', $text);

        $timeend = fn(\stdClass $u) => (int) $DB->get_field_sql(
            "SELECT ue.timeend FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid", ['cid' => $course->id, 'uid' => $u->id]);

        compliance_pinning::execute($plan, false);
        $this->assertSame($start + 30 * DAYSECS, $timeend($locked), 'cleared without --clear-enddates');

        compliance_pinning::execute(compliance_pinning::plan(), true);
        $this->assertSame(0, $timeend($locked));
        $this->assertSame($start + 10 * DAYSECS, $timeend($custom));
        $this->assertSame($start + 30 * DAYSECS, $timeend($selfenrolled));
        foreach ([$publicuser, $zeeauser, $notenant] as $outsider) {
            $this->assertSame($start + 30 * DAYSECS, $timeend($outsider), 'an outsider was let back in');
        }
        $this->assertSame([], compliance_pinning::plan()['enddates'][$course->id]['matching']);

        // Shared to Public: the Public user is reached now, and cleared.
        $DB->insert_record('local_sentientia_courses_tenant_share', (object) [
            'courseid' => $course->id, 'tenant_id' => 77, 'shared_by' => get_admin()->id,
            'status' => 'active', 'timeshared' => time(), 'timemodified' => time(),
        ]);
        compliance_pinning::execute(compliance_pinning::plan(), true);
        $this->assertSame(0, $timeend($publicuser));
        $this->assertSame($start + 30 * DAYSECS, $timeend($zeeauser));
    }

    /** A deadline-based enrolment that the expiry already suspended is counted, and its end date cleared. */
    public function test_suspended_deadline_enrolment_is_counted(): void {
        global $DB;
        $course = $this->c['phishing'];
        $start = time() - 40 * DAYSECS;
        $user = $this->person('/1/2');
        $this->assertTrue($this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual',
            $start, $start + 30 * DAYSECS, ENROL_USER_SUSPENDED));

        $plan = compliance_pinning::plan();
        $this->assertSame(1, $plan['enddates'][$course->id]['suspended']);
        $this->assertStringContainsString('does not reactivate them',
            implode("\n", compliance_pinning::render($plan, true, true)));

        compliance_pinning::execute($plan, true);
        $ue = $DB->get_record_sql(
            "SELECT ue.status, ue.timeend FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid", ['cid' => $course->id, 'uid' => $user->id], MUST_EXIST);
        $this->assertEquals(0, $ue->timeend);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status, 'the script reactivated an enrolment');
    }

    /** The end of a user's enrolment in a course (0 = none). */
    private function timeend(\stdClass $course, \stdClass $user): int {
        global $DB;
        return (int) $DB->get_field_sql(
            "SELECT ue.timeend FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid AND ue.userid = :uid", ['cid' => $course->id, 'uid' => $user->id], MUST_EXIST);
    }

    /**
     * POSH for IC members: the old refresh enrolled every Airpay employee
     * into the IC course with a deadline end date. Only cohort members' end
     * dates are cleared; a non-member keeps the ended enrolment that keeps
     * them out of the IC-only course. The cohort is created empty, so the
     * first run clears none; once L&D adds a member, the next run clears
     * that member's only.
     */
    public function test_ic_course_end_dates_follow_the_cohort(): void {
        global $DB;
        $course = $this->c['posh_ic'];
        $start = time() - 40 * DAYSECS;
        $member = $this->person('/1/2');
        $nonmember = $this->person('/1/3');
        foreach ([$member, $nonmember] as $u) {
            $this->assertTrue($this->getDataGenerator()->enrol_user($u->id, $course->id, 'student', 'manual',
                $start, $start + 30 * DAYSECS));
        }

        // First run: the cohort does not exist yet, so it has no members.
        $plan = compliance_pinning::plan();
        $entry = $plan['enddates'][$course->id];
        $this->assertSame('cohort', $entry['gate']);
        $this->assertSame([], $entry['matching']);
        $this->assertSame(2, $entry['noncohort']);
        $this->assertSame(0, $entry['outside']);
        $text = implode("\n", compliance_pinning::render($plan, true, true));
        $this->assertStringContainsString('2 deadline end dates belong to people who are not in the cohort', $text);
        $this->assertStringContainsString('0 end dates WILL be cleared', $text);

        $done = compliance_pinning::execute($plan, true);
        $this->assertContains('Cleared 0 deadline-based enrolment end dates.', $done);
        $this->assertSame($start + 30 * DAYSECS, $this->timeend($course, $member));
        $this->assertSame($start + 30 * DAYSECS, $this->timeend($course, $nonmember),
            'a non-member was given open access to the IC course');

        // L&D adds the member; the next run clears the member's end date only.
        $cohortid = (int) $DB->get_field('cohort', 'id',
            ['idnumber' => compliance_pinning::DEFAULT_COHORT_IDNUMBER], MUST_EXIST);
        cohort_add_member($cohortid, $member->id);

        $plan = compliance_pinning::plan();
        $entry = $plan['enddates'][$course->id];
        $this->assertSame([(int) $member->id], array_map(fn($ue) => (int) $ue->userid, $entry['matching']));
        $this->assertSame(1, $entry['noncohort']);

        compliance_pinning::execute($plan, true);
        $this->assertSame(0, $this->timeend($course, $member));
        $this->assertSame($start + 30 * DAYSECS, $this->timeend($course, $nonmember),
            'a non-member was given open access to the IC course');
    }

    /**
     * An IC course the plan leaves alone (here: it has no tenant, so it would
     * reach everyone) is not restricted to a cohort: nobody's end date on it
     * is cleared until it is.
     */
    public function test_unrestricted_ic_course_keeps_end_dates(): void {
        global $DB;
        $course = $this->c['posh_ic'];
        $DB->set_field('course', 'open_path', null, ['id' => $course->id]);
        $start = time() - 40 * DAYSECS;
        $airpayuser = $this->person('/1/2');
        $publicuser = $this->person('/77');
        foreach ([$airpayuser, $publicuser] as $u) {
            $this->assertTrue($this->getDataGenerator()->enrol_user($u->id, $course->id, 'student', 'manual',
                $start, $start + 30 * DAYSECS));
        }

        $plan = compliance_pinning::plan();
        $this->assertArrayNotHasKey('posh_ic', $plan['groups']);
        $this->assertNull($plan['cohort']);
        $entry = $plan['enddates'][$course->id];
        $this->assertSame('unrestricted', $entry['gate']);
        $this->assertSame([], $entry['matching']);
        $this->assertSame(2, $entry['noncohort']);
        $this->assertStringContainsString('2 deadline end dates are on an IC-members course that is not restricted',
            implode("\n", compliance_pinning::render($plan, true, true)));

        compliance_pinning::execute($plan, true);
        $this->assertSame($start + 30 * DAYSECS, $this->timeend($course, $airpayuser));
        $this->assertSame($start + 30 * DAYSECS, $this->timeend($course, $publicuser));
    }

    /**
     * A course with a cohort on one row: each row admits its own pin's
     * people. Here the Airpay row is restricted to a cohort and a Public row
     * (the course is shared to Public) is not.
     */
    public function test_cohort_rows_follow_their_pin(): void {
        global $DB;
        $course = $this->c['other'];
        $cohort = $this->getDataGenerator()->create_cohort(['idnumber' => 'coc_airpay']);
        $DB->update_record('local_compliance_courses', (object) [
            'id' => $this->row['other'], 'costcenterid' => 1, 'cohortid' => $cohort->id]);
        $this->track($course, 77, 60);
        $DB->insert_record('local_sentientia_courses_tenant_share', (object) [
            'courseid' => $course->id, 'tenant_id' => 77, 'shared_by' => get_admin()->id,
            'status' => 'active', 'timeshared' => time(), 'timemodified' => time(),
        ]);
        $start = time() - 40 * DAYSECS;
        $member = $this->person('/1/2');
        $nonmember = $this->person('/1/2');
        $publicuser = $this->person('/77');
        cohort_add_member($cohort->id, $member->id);
        foreach ([$member, $nonmember, $publicuser] as $u) {
            $this->assertTrue($this->getDataGenerator()->enrol_user($u->id, $course->id, 'student', 'manual',
                $start, $start + 30 * DAYSECS));
        }

        $entry = compliance_pinning::plan()['enddates'][$course->id];
        $this->assertSame('cohort', $entry['gate']);
        $matched = array_map(fn($ue) => (int) $ue->userid, $entry['matching']);
        sort($matched);
        $expected = [(int) $member->id, (int) $publicuser->id];
        sort($expected);
        $this->assertSame($expected, $matched);
        $this->assertSame(1, $entry['noncohort']);
    }
}
