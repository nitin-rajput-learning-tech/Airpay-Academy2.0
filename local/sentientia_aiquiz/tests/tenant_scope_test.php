<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: AI quiz drafts, and the courses they are pushed into, stay inside
 * the caller's tenant.
 *
 * :manage_all defaulted to the manager archetype, which every tenant admin
 * holds at system context, and holding it made draft_manager::list_for_actor()
 * return every tenant's drafts and load_for_actor() skip the tenant check - so
 * any tenant admin could read, approve, edit and finalise another tenant's
 * generated questions. The push target was any course id the reviewer posted.
 * A caller with no tenant shared bucket 0 with every other tenantless caller,
 * site-admin drafts included.
 *
 * @package    local_sentientia_aiquiz
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_aiquiz\draft_manager
 * @covers     \local_sentientia_aiquiz\quiz_publisher
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const CAP = 'local/sentientia_aiquiz:manage_all';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A tenant admin as UAT has them: a manager-archetype role at SYSTEM
     * context, optionally also granted :manage_all deliberately.
     */
    private function tenant_admin_at(string $path, bool $manageall = true): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $sys = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, $sys->id);
        if ($manageall) {
            $roleid = $this->getDataGenerator()->create_role();
            assign_capability(self::CAP, CAP_ALLOW, $roleid, $sys->id);
            role_assign($roleid, $u->id, $sys->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function draft_by(\stdClass $owner, string $title = 'Draft', int $courseid = 0): int {
        return draft_manager::create_pending((int) $owner->id, $courseid, $title,
            'Source text.', 'claude-sonnet-4-6', 1);
    }

    private function course_at(?string $path): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /** @return int[] */
    private function listed_ids(\stdClass $actor, bool $manageall): array {
        return array_map(fn($d) => (int) $d->id, draft_manager::list_for_actor($actor, $manageall));
    }

    public function test_manage_all_has_no_default_grant(): void {
        global $DB;
        foreach ($DB->get_records('role', ['archetype' => 'manager']) as $role) {
            $this->assertFalse($DB->record_exists('role_capabilities',
                ['roleid' => $role->id, 'capability' => self::CAP]),
                "Role {$role->shortname} (manager archetype) must not hold :manage_all by default.");
        }
    }

    public function test_a_tenant_admin_sees_nothing_of_another_tenant(): void {
        $admin = $this->tenant_admin_at('/1');
        $manageall = has_capability(self::CAP, \context_system::instance(), $admin->id);
        $this->assertTrue($manageall, 'Fixture: the tenant admin holds :manage_all deliberately.');

        $colleague = $this->draft_by($this->user_at('/1/2'), 'Airpay draft');
        $foreign = $this->draft_by($this->user_at('/177/4'), 'ZEEA draft');

        $ids = $this->listed_ids($admin, $manageall);
        $this->assertContains($colleague, $ids, 'The tenant admin still reviews their own tenant.');
        $this->assertNotContains($foreign, $ids, ':manage_all says WHAT, not WHERE.');

        $this->assertNotNull(draft_manager::load_for_actor($colleague, $admin, $manageall));
        $this->assertNull(draft_manager::load_for_actor($foreign, $admin, $manageall),
            'Tenant 177\'s draft (questions, answers, owner) stays out of reach by id.');
    }

    public function test_a_caller_with_no_tenant_sees_only_their_own_drafts(): void {
        $nobody = $this->tenant_admin_at('');
        $mine = $this->draft_by($nobody, 'Mine');
        $othertenantless = $this->draft_by($this->user_at(''), 'Another tenantless author');
        $siteadmins = $this->draft_by(get_admin(), 'Site admin draft');
        $tenanted = $this->draft_by($this->user_at('/1'), 'Airpay draft');

        $ids = $this->listed_ids($nobody, true);
        $this->assertSame([$mine], $ids,
            'Bucket 0 is nobody\'s tenant: no shared drafts, never every tenant.');
        foreach ([$othertenantless, $siteadmins, $tenanted] as $did) {
            $this->assertNull(draft_manager::load_for_actor($did, $nobody, true));
        }
        $this->assertNotNull(draft_manager::load_for_actor($mine, $nobody, true));
    }

    public function test_the_site_admin_still_sees_every_tenant(): void {
        $airpay = $this->draft_by($this->user_at('/1'), 'Airpay');
        $zeea = $this->draft_by($this->user_at('/177'), 'ZEEA');
        $tenantless = $this->draft_by($this->user_at(''), 'Tenantless');

        $admin = get_admin();
        $ids = $this->listed_ids($admin, true);
        foreach ([$airpay, $zeea, $tenantless] as $did) {
            $this->assertContains($did, $ids);
            $this->assertNotNull(draft_manager::load_for_actor($did, $admin, true));
        }
    }

    public function test_a_crosstenant_holder_with_manage_all_is_unscoped(): void {
        $zeea = $this->draft_by($this->user_at('/177'), 'ZEEA');
        $platform = $this->tenant_admin_at('/1');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(\local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW,
            $roleid, \context_system::instance()->id);
        role_assign($roleid, $platform->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertNotNull(draft_manager::load_for_actor($zeea, $platform, true));
        $this->assertNull(draft_manager::load_for_actor($zeea, $platform, false),
            'Cross-tenant alone does not grant :manage_all - both are needed.');
    }

    public function test_publish_refuses_a_course_in_another_tenant(): void {
        global $DB;
        $admin = $this->tenant_admin_at('/1');
        $this->setUser($admin);
        $did = $this->draft_by($admin, 'Own draft');
        $mock = anthropic_client::call_mock('Source text.', 1);
        draft_manager::persist_questions($did, response_parser::parse($mock['body']), 0, 0, 'mock');
        $DB->set_field(draft_manager::QUESTION_TABLE, 'status',
            draft_manager::Q_STATUS_APPROVED, ['draftid' => $did]);
        $this->assertSame(draft_manager::STATUS_APPROVED, draft_manager::finalise_review($did, (int) $admin->id));

        $foreign = $this->course_at('/177');
        try {
            quiz_publisher::publish($did, (int) $foreign->id, $admin, true);
            $this->fail('A tenant admin must not push into another tenant\'s course.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('quiz', ['course' => $foreign->id]));
        $this->assertSame(draft_manager::STATUS_APPROVED,
            $DB->get_field(draft_manager::DRAFT_TABLE, 'status', ['id' => $did]));
    }

    public function test_course_pickers_are_bounded_to_the_callers_tenant(): void {
        $admin = $this->tenant_admin_at('/1');
        $own = $this->course_at('/1/2');
        $foreign = $this->course_at('/177');
        $sibling = $this->course_at('/10');

        $targets = quiz_publisher::target_courses($admin);
        $this->assertArrayHasKey((int) $own->id, $targets);
        $this->assertArrayNotHasKey((int) $foreign->id, $targets);
        $this->assertArrayNotHasKey((int) $sibling->id, $targets, '/10 is not inside /1.');

        $this->assertSame(['1=0', []], quiz_publisher::course_scope_sql($this->user_at('')),
            'A caller with no tenant is offered no course at all.');
        $this->assertSame(['1=1', []], quiz_publisher::course_scope_sql(get_admin()),
            'The site admin is not tenant-bounded.');
    }

    /** An APPROVED draft owned by $owner, ready to push. */
    private function approved_draft_by(\stdClass $owner): int {
        global $DB;
        $did = $this->draft_by($owner, 'Own draft');
        $mock = anthropic_client::call_mock('Source text.', 1);
        draft_manager::persist_questions($did, response_parser::parse($mock['body']), 0, 0, 'mock');
        $DB->set_field(draft_manager::QUESTION_TABLE, 'status',
            draft_manager::Q_STATUS_APPROVED, ['draftid' => $did]);
        $this->assertSame(draft_manager::STATUS_APPROVED,
            draft_manager::finalise_review($did, (int) $owner->id));
        return $did;
    }

    /**
     * Wave-1 review S1: require_path_access() waves a NULL-path course through
     * (a readable legacy row), so a tenantless actor holding
     * course:manageactivities could push a quiz into a legacy shared course.
     */
    public function test_a_tenantless_actor_cannot_push_into_a_legacy_course(): void {
        global $DB;
        $nobody = $this->tenant_admin_at('');
        $this->setUser($nobody);
        $did = $this->approved_draft_by($nobody);

        foreach ([null, ''] as $path) {
            $legacy = $this->course_at($path);
            $this->assertTrue(has_capability('moodle/course:manageactivities',
                \context_course::instance($legacy->id), $nobody),
                'Fixture: the capability alone would allow the push.');
            try {
                quiz_publisher::publish($did, (int) $legacy->id, $nobody, true);
                $this->fail('A caller with no tenant must not push into a legacy course.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
            $this->assertSame(0, $DB->count_records('quiz', ['course' => $legacy->id]));
        }
        $this->assertSame(draft_manager::STATUS_APPROVED,
            $DB->get_field(draft_manager::DRAFT_TABLE, 'status', ['id' => $did]),
            'The refused push leaves the draft untouched.');
    }

    /**
     * A legacy NULL-path course belongs to no tenant and every tenant's
     * catalogue lists it, so a tenant admin writing into it writes into all
     * of them: refused for any scoped actor, and not offered by the pickers.
     */
    public function test_a_scoped_admin_cannot_push_into_a_legacy_shared_course(): void {
        global $DB;
        $admin = $this->tenant_admin_at('/1');
        $this->setUser($admin);
        $did = $this->approved_draft_by($admin);
        $legacy = $this->course_at(null);

        try {
            quiz_publisher::publish($did, (int) $legacy->id, $admin, true);
            $this->fail('A scoped actor must not push into a course of no tenant.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('quiz', ['course' => $legacy->id]));

        $this->assertArrayNotHasKey((int) $legacy->id, quiz_publisher::target_courses($admin),
            'The push picker does not offer a course the push would refuse.');
        [$sql, $args] = quiz_publisher::course_scope_sql($admin);
        $offered = $DB->get_fieldset_select('course', 'id', "id > 1 AND {$sql}", $args);
        $this->assertNotContains((string) $legacy->id, array_map('strval', $offered),
            'Nor does the generate picker.');
    }

    public function test_require_course_in_scope_decides_each_case(): void {
        $own = $this->course_at('/1/2');
        $root = $this->course_at('/1');
        $foreign = $this->course_at('/177');
        $sibling = $this->course_at('/10');
        $legacy = $this->course_at(null);
        $scoped = $this->user_at('/1/5');
        $nobody = $this->user_at('');

        quiz_publisher::require_course_in_scope($own, $scoped);
        quiz_publisher::require_course_in_scope($root, $scoped);
        foreach ([[$foreign, $scoped], [$sibling, $scoped], [$legacy, $scoped],
                  [$own, $nobody], [$legacy, $nobody]] as [$course, $actor]) {
            try {
                quiz_publisher::require_course_in_scope($course, $actor);
                $this->fail("Course {$course->id} must be refused for user {$actor->id}.");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        }

        // Cross-tenant actors still reach every course, legacy ones included.
        foreach ([$own, $foreign, $legacy] as $course) {
            quiz_publisher::require_course_in_scope($course, get_admin());
        }
    }

    public function test_the_site_admin_still_pushes_into_a_legacy_course(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $did = $this->approved_draft_by($USER);
        $legacy = $this->course_at(null);

        $result = quiz_publisher::publish($did, (int) $legacy->id, $USER, true);
        $this->assertGreaterThan(0, $result->quizid);
        $this->assertSame(1, $DB->count_records('quiz', ['course' => $legacy->id]));
    }

    public function test_the_upgrade_revoke_removes_existing_grants(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/sentientia_aiquiz/db/upgradelib.php');
        // What an install before 2026-09-25 (or reset_role_capabilities()) left behind.
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        assign_capability(self::CAP, CAP_ALLOW, $managerid, \context_system::instance()->id);
        $holder = $this->user_at('/1');
        role_assign($managerid, $holder->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::CAP, \context_system::instance(), $holder->id));

        $this->assertGreaterThanOrEqual(1, local_sentientia_aiquiz_revoke_manage_all());

        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => self::CAP]));
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability(self::CAP, \context_system::instance(), $holder->id));
    }
}
