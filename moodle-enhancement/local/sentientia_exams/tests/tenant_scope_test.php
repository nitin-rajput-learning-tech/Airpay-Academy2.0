<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: :view / :manage say WHAT, never WHERE.
 *
 * Tenant admins hold a manager-archetype role at system context, and with it
 * :view and :manage. Until 2026-09-25 that let any of them open (view.php),
 * edit, deactivate or delete any tenant's exam by id, wrap another tenant's
 * quiz, and list another tenant's exams by sending that tenant's org id as a
 * cascade filter. These tests pin the fix: the exam (and its quiz's course)
 * must be in the caller's tenant unless tenant::is_cross_tenant().
 *
 * @package    local_sentientia_exams
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_exams\exam_manager
 * @covers     \local_sentientia_exams\external\list_exams
 * @covers     \local_sentientia_exams\external\delete_exam
 * @covers     \local_sentientia_exams\external\toggle_status
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** Insert an exam wrapper directly (quizid 0: no quiz needed for these paths). */
    private function seed_exam(string $name, ?string $path, int $status = 1): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_exams', (object) [
            'name'         => $name,
            'quizid'       => 0,
            'costcenterid' => 0,
            'open_path'    => $path,
            'duration'     => 1800,
            'passinggrade' => 70,
            'status'       => $status,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    private function seed_org(string $path): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname'     => 'Org ' . $path,
            'shortname'    => 'org' . str_replace('/', '_', $path),
            'parentid'     => 0,
            'path'         => $path,
            'depth'        => substr_count($path, '/'),
            'visible'      => 1,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /** A tenant admin: the stock manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A quiz whose course sits at $coursepath. */
    private function quiz_in(string $coursepath): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $coursepath, ['id' => $course->id]);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        return (int) $quiz->id;
    }

    private function assert_outoftenant(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    public function test_tenant_admin_cannot_write_another_tenants_exam(): void {
        global $DB;
        $foreign = $this->seed_exam('ZEEA exam', '/177/178');
        $this->setUser($this->tenant_admin('/1'));

        $this->assert_outoftenant(fn() => external\delete_exam::execute($foreign),
            'A /1 tenant admin must not delete a /177 exam.');
        $this->assert_outoftenant(fn() => external\toggle_status::execute($foreign, false),
            'A /1 tenant admin must not deactivate a /177 exam.');
        $this->assert_outoftenant(fn() => exam_manager::update($foreign, (object) ['name' => 'Hijacked']),
            'A /1 tenant admin must not edit a /177 exam.');
        $this->assert_outoftenant(
            fn() => exam_manager::require_exam_access($DB->get_record('local_sentientia_exams', ['id' => $foreign])),
            'view.php gate: a /1 tenant admin must not open a /177 exam.');

        $row = $DB->get_record('local_sentientia_exams', ['id' => $foreign], '*', MUST_EXIST);
        $this->assertSame('ZEEA exam', $row->name);
        $this->assertSame(1, (int) $row->status);
    }

    public function test_tenant_admin_keeps_their_own_tenant(): void {
        global $DB;
        $own = $this->seed_exam('Airpay exam', '/1/5');
        $this->setUser($this->tenant_admin('/1'));

        $result = external\toggle_status::execute($own, false);
        $this->assertFalse($result['active']);
        exam_manager::update($own, (object) ['name' => 'Renamed']);
        $this->assertSame('Renamed', $DB->get_field('local_sentientia_exams', 'name', ['id' => $own]));
        external\delete_exam::execute($own);
        $this->assertFalse($DB->record_exists('local_sentientia_exams', ['id' => $own]));
    }

    public function test_exam_with_no_path_is_cross_tenant_only(): void {
        global $DB;
        $orphan = $this->seed_exam('No org', null);
        $this->setUser($this->tenant_admin('/1'));
        $this->assert_outoftenant(fn() => external\delete_exam::execute($orphan),
            'An exam with no open_path cannot be shown to be in the caller\'s tenant.');

        $this->setAdminUser();
        external\delete_exam::execute($orphan);
        $this->assertFalse($DB->record_exists('local_sentientia_exams', ['id' => $orphan]));
    }

    public function test_cascade_filter_narrows_but_never_widens(): void {
        $this->seed_exam('Airpay exam', '/1');
        $this->seed_exam('ZEEA exam', '/177');
        $zeeaorg = $this->seed_org('/177');
        $filters = json_encode(['org_l1' => $zeeaorg]);

        $this->setUser($this->tenant_admin('/1'));
        $result = external\list_exams::execute('', 'name', 'asc', 0, 25, $filters);
        $this->assertSame(0, (int) $result['total'],
            'Another tenant\'s org id in the cascade must not list that tenant\'s exams.');
        $this->assertSame(1, (int) external\list_exams::execute()['total']);
        $this->assertSame(1, exam_manager::count_scoped());

        $this->setAdminUser();
        $result = external\list_exams::execute('', 'name', 'asc', 0, 25, $filters);
        $this->assertSame(1, (int) $result['total'], 'A site admin may still drill into any tenant.');
        $this->assertSame(2, exam_manager::count_scoped());
    }

    public function test_caller_with_no_tenant_gets_nothing(): void {
        global $DB;
        $exam = $this->seed_exam('Airpay exam', '/1');
        $airpayorg = $this->seed_org('/1');
        $this->setUser($this->tenant_admin(''));

        $this->assertSame(0, (int) external\list_exams::execute()['total']);
        $this->assertSame(0, (int) external\list_exams::execute('', 'name', 'asc', 0, 25,
            json_encode(['org_l1' => $airpayorg]))['total'],
            'The cascade must not hand a no-tenant caller an org subtree.');
        $this->assertSame(0, exam_manager::count_scoped());
        $this->assert_outoftenant(fn() => external\delete_exam::execute($exam),
            'A caller with no tenant must not delete anything.');
        $this->assertTrue($DB->record_exists('local_sentientia_exams', ['id' => $exam]));
        $this->assertSame([0], array_keys(exam_manager::get_quiz_options()));
    }

    public function test_create_stays_inside_the_callers_tenant(): void {
        global $DB;
        $zeeaorg = $this->seed_org('/177');
        $ownquiz = $this->quiz_in('/1/2');
        $foreignquiz = $this->quiz_in('/177');
        $this->setUser($this->tenant_admin('/1'));

        $this->assert_outoftenant(fn() => exam_manager::create((object) [
            'name' => 'Planted', 'quizid' => $ownquiz, 'costcenterid' => $zeeaorg]),
            'A /1 tenant admin must not create an exam inside /177.');
        $this->assert_outoftenant(fn() => exam_manager::create((object) [
            'name' => 'Wrapped', 'quizid' => $foreignquiz, 'costcenterid' => 0]),
            'A /1 tenant admin must not wrap a quiz from a /177 course.');

        // "No specific organisation" lands in the caller's own tenant.
        $id = exam_manager::create((object) ['name' => 'Mine', 'quizid' => $ownquiz, 'costcenterid' => 0]);
        $this->assertSame('/1', $DB->get_field('local_sentientia_exams', 'open_path', ['id' => $id]));

        $this->assert_outoftenant(fn() => exam_manager::update($id, (object) ['costcenterid' => $zeeaorg]),
            'A /1 tenant admin must not re-home their exam into /177.');

        $options = exam_manager::get_quiz_options();
        $this->assertArrayHasKey($ownquiz, $options);
        $this->assertArrayNotHasKey($foreignquiz, $options, 'The quiz picker must not list other tenants\' quizzes.');
    }

    public function test_site_admin_is_unchanged(): void {
        global $DB;
        $zeeaorg = $this->seed_org('/177');
        $foreignquiz = $this->quiz_in('/177');
        $this->setAdminUser();

        $id = exam_manager::create((object) ['name' => 'Platform', 'quizid' => $foreignquiz,
            'costcenterid' => $zeeaorg]);
        $this->assertSame('/177', $DB->get_field('local_sentientia_exams', 'open_path', ['id' => $id]));
        $this->assertArrayHasKey($foreignquiz, exam_manager::get_quiz_options());
        external\toggle_status::execute($id, false);
        external\delete_exam::execute($id);
        $this->assertFalse($DB->record_exists('local_sentientia_exams', ['id' => $id]));
    }

    public function test_view_page_checks_only_declared_capabilities(): void {
        // view.php checked the never-declared :update, so its edit flag was
        // always false. Every capability it asks about must exist; the page
        // is a script, so read its source.
        $source = file_get_contents(__DIR__ . '/../view.php');
        preg_match_all("~has_capability\\(\\s*'(local/sentientia_exams:[a-z_]+)'~", $source, $m);
        $this->assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $capability) {
            $this->assertNotEmpty(get_capability_info($capability),
                "view.php asks about {$capability}, which db/access.php does not declare.");
        }
        $this->assertContains('local/sentientia_exams:manage', $m[1],
            'The edit flag follows :manage, as edit_exam, delete and toggle_status do.');
    }
}
