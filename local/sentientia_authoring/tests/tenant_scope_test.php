<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: Authoring Studio drafts and templates stay inside the caller's
 * tenant.
 *
 * :manage_all defaulted to the manager archetype, which every tenant admin
 * holds at system context, and holding it unscoped draft_manager and
 * template_manager - so any tenant admin could review, edit, finalise and
 * publish another tenant's course drafts and rewrite or archive their
 * templates. "Shared" templates were every costcenterid-0 row, which also
 * caught a tenantless author's private template, and anyone who could see a
 * built-in could rewrite it for every tenant.
 *
 * @package    local_sentientia_authoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_authoring\draft_manager
 * @covers     \local_sentientia_authoring\template_manager
 * @covers     \local_sentientia_authoring\course_builder
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const CAP = 'local/sentientia_authoring:manage_all';

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
     * context, here also granted :manage_all deliberately.
     */
    private function tenant_admin_at(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $sys = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, $sys->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(self::CAP, CAP_ALLOW, $roleid, $sys->id);
        role_assign($roleid, $u->id, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::CAP, $sys, $u->id));
        return $u;
    }

    private function draft_by(\stdClass $owner, string $title = 'Draft'): int {
        return draft_manager::create_pending((int) $owner->id, $title, 'Source.', 'prompt', 'en',
            'claude-sonnet-4-6', 70);
    }

    private function approved_draft_by(\stdClass $owner): int {
        global $DB;
        $did = $this->draft_by($owner, 'POSH essentials');
        $mock = course_generator::call_mock('POSH escalation source.', 3, 2);
        $parsed = response_parser::parse($mock['body']);
        draft_manager::persist_generation($did, $parsed->cards, $parsed->questions, 0, 0, 'mock');
        $DB->set_field(draft_manager::CARD_TABLE, 'status', draft_manager::ITEM_APPROVED, ['draftid' => $did]);
        $DB->set_field(draft_manager::QUESTION_TABLE, 'status', draft_manager::ITEM_APPROVED, ['draftid' => $did]);
        $this->assertSame(draft_manager::STATUS_APPROVED, draft_manager::finalise_review($did, (int) $owner->id));
        return $did;
    }

    /** @return int[] */
    private function ids(array $rows): array {
        return array_map(fn($r) => (int) $r->id, $rows);
    }

    public function test_manage_all_has_no_default_grant(): void {
        global $DB;
        foreach ($DB->get_records('role', ['archetype' => 'manager']) as $role) {
            $this->assertFalse($DB->record_exists('role_capabilities',
                ['roleid' => $role->id, 'capability' => self::CAP]),
                "Role {$role->shortname} (manager archetype) must not hold :manage_all by default.");
        }
    }

    public function test_a_tenant_admin_sees_no_other_tenants_drafts(): void {
        $admin = $this->tenant_admin_at('/1');
        $own = $this->draft_by($this->user_at('/1/2'), 'Airpay');
        $foreign = $this->draft_by($this->user_at('/177/4'), 'ZEEA');

        $ids = $this->ids(draft_manager::list_for_actor($admin, true));
        $this->assertContains($own, $ids, 'The tenant admin still reviews their own tenant.');
        $this->assertNotContains($foreign, $ids, ':manage_all says WHAT, not WHERE.');
        $this->assertNotNull(draft_manager::load_for_actor($own, $admin, true));
        $this->assertNull(draft_manager::load_for_actor($foreign, $admin, true),
            'Review, voice-over and publish all load through this - none reach tenant 177.');
    }

    public function test_a_tenant_admin_cannot_publish_another_tenants_draft(): void {
        global $DB;
        $admin = $this->tenant_admin_at('/1');
        $this->setUser($admin);
        $foreign = $this->approved_draft_by($this->user_at('/177'));
        $before = $DB->count_records('course');
        try {
            course_builder::build($foreign, $admin, true);
            $this->fail('A tenant admin must not publish tenant 177\'s draft as a course.');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_draft_not_found', $e->errorcode);
        }
        $this->assertSame($before, $DB->count_records('course'));
    }

    public function test_a_published_course_is_filed_under_the_drafts_tenant(): void {
        $this->setAdminUser();
        global $USER;
        $did = $this->approved_draft_by($this->user_at('/77/3'));
        $result = course_builder::build($did, $USER, true);
        $this->assertSame('/77', get_course($result->courseid)->open_path,
            'A NULL open_path would make the course legacy-visible to every tenant.');
    }

    public function test_a_tenant_admin_sees_no_other_tenants_templates(): void {
        $admin = $this->tenant_admin_at('/1');
        $own = template_manager::create((int) $this->user_at('/1/2')->id, 'Airpay tpl', 'Body');
        $foreign = template_manager::create((int) $this->user_at('/177')->id, 'ZEEA tpl', 'Body');

        $ids = $this->ids(template_manager::list_for_actor($admin, true));
        $this->assertContains($own, $ids);
        $this->assertNotContains($foreign, $ids);
        $this->assertNull(template_manager::load_for_actor($foreign, $admin, true),
            'templates.php edit/archive load through this, so they cannot reach it either.');
    }

    public function test_a_tenantless_authors_template_is_not_published_to_every_tenant(): void {
        $nobody = $this->user_at('');
        $private = template_manager::create((int) $nobody->id, 'Private', 'Body');
        template_manager::seed_builtins();

        foreach (['/1', '/77/2', '/177'] as $path) {
            $author = $this->user_at($path);
            $this->assertNull(template_manager::load_for_actor($private, $author, false),
                "costcenterid 0 is not 'shared': {$path} must not see it.");
            $this->assertNotContains($private, $this->ids(template_manager::list_for_actor($author, false)));
        }
        // The owner keeps it, and everyone keeps the built-ins.
        $this->assertNotNull(template_manager::load_for_actor($private, $nobody, false));
        $builtins = array_filter(template_manager::list_for_actor($this->user_at('/177'), false),
            fn($t) => (int) $t->is_builtin === 1);
        $this->assertNotEmpty($builtins);
    }

    public function test_a_caller_with_no_tenant_sees_only_their_own_drafts(): void {
        $nobody = $this->tenant_admin_at('');
        $mine = $this->draft_by($nobody, 'Mine');
        $other = $this->draft_by($this->user_at(''), 'Another tenantless author');
        $siteadmin = $this->draft_by(get_admin(), 'Site admin');
        $tenanted = $this->draft_by($this->user_at('/1'), 'Airpay');

        $this->assertSame([$mine], $this->ids(draft_manager::list_for_actor($nobody, true)),
            'Bucket 0 is nobody\'s tenant: no shared drafts, never every tenant.');
        foreach ([$other, $siteadmin, $tenanted] as $did) {
            $this->assertNull(draft_manager::load_for_actor($did, $nobody, true));
        }
    }

    public function test_only_a_cross_tenant_caller_may_edit_a_builtin(): void {
        global $DB;
        template_manager::seed_builtins();
        $builtin = $DB->get_record(template_manager::TABLE, ['is_builtin' => 1], '*', IGNORE_MULTIPLE);

        $trainer = $this->user_at('/77');
        $this->assertNotNull(template_manager::load_for_actor((int) $builtin->id, $trainer, false),
            'Every tenant may still USE a built-in.');
        $this->assertFalse(template_manager::can_edit($builtin, $trainer, false),
            'A rewrite would change the template every other tenant generates from.');
        $this->assertFalse(template_manager::can_edit($builtin, $this->tenant_admin_at('/1'), true));
        $this->assertTrue(template_manager::can_edit($builtin, get_admin(), true));
    }

    public function test_tenant_templates_are_editable_inside_the_tenant_only(): void {
        global $DB;
        $owner = $this->user_at('/1/2');
        $tid = template_manager::create((int) $owner->id, 'Airpay tpl', 'Body');
        $tpl = $DB->get_record(template_manager::TABLE, ['id' => $tid], '*', MUST_EXIST);

        $this->assertTrue(template_manager::can_edit($tpl, $owner, false));
        $this->assertTrue(template_manager::can_edit($tpl, $this->user_at('/1/5'), false),
            'A colleague in the same tenant keeps edit rights.');
        $this->assertFalse(template_manager::can_edit($tpl, $this->tenant_admin_at('/177'), true));
        $this->assertFalse(template_manager::can_edit($tpl, $this->user_at(''), false));
        $this->assertTrue(template_manager::can_edit($tpl, get_admin(), true));
    }

    public function test_the_site_admin_still_sees_every_tenant(): void {
        $admin = get_admin();
        $drafts = [$this->draft_by($this->user_at('/1')), $this->draft_by($this->user_at('/177')),
            $this->draft_by($this->user_at(''))];
        $templates = [template_manager::create((int) $this->user_at('/77')->id, 'P', 'B'),
            template_manager::create((int) $this->user_at('')->id, 'T', 'B')];

        $draftids = $this->ids(draft_manager::list_for_actor($admin, true));
        foreach ($drafts as $did) {
            $this->assertContains($did, $draftids);
            $this->assertNotNull(draft_manager::load_for_actor($did, $admin, true));
        }
        $tplids = $this->ids(template_manager::list_for_actor($admin, true));
        foreach ($templates as $tid) {
            $this->assertContains($tid, $tplids);
        }
    }

    /**
     * Wave-1 review S2: a costcenterid-0 draft publishes with a NULL
     * open_path, which every tenant catalogue lists as legacy once unhidden.
     */
    public function test_a_tenantless_author_cannot_publish_a_tenantless_draft(): void {
        global $DB;
        $nobody = $this->tenant_admin_at('');
        $this->setUser($nobody);
        $did = $this->approved_draft_by($nobody);
        $this->assertSame(0, (int) $DB->get_field(draft_manager::DRAFT_TABLE, 'costcenterid', ['id' => $did]));
        $this->assertTrue(has_capability('moodle/course:create', \context_system::instance(), $nobody),
            'Fixture: the capability alone would allow the publish.');
        $before = $DB->count_records('course');

        try {
            course_builder::build($did, $nobody, true);
            $this->fail('A tenantless draft must not become a course every tenant lists.');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_publish_notenant', $e->errorcode);
        }
        $this->assertSame($before, $DB->count_records('course'));
        $this->assertSame(draft_manager::STATUS_APPROVED,
            $DB->get_field(draft_manager::DRAFT_TABLE, 'status', ['id' => $did]));
    }

    /** An owner who has moved tenant cannot publish into the tenant they left. */
    public function test_an_author_cannot_publish_a_draft_filed_under_another_tenant(): void {
        global $DB;
        $author = $this->tenant_admin_at('/1');
        $did = $this->approved_draft_by($author);
        $DB->set_field('user', 'open_path', '/77', ['id' => $author->id]);
        $author = $DB->get_record('user', ['id' => $author->id], '*', MUST_EXIST);
        $this->setUser($author);
        $this->assertNotNull(draft_manager::load_for_actor($did, $author, true),
            'Fixture: the owner can still load their own draft.');
        $before = $DB->count_records('course');

        try {
            course_builder::build($did, $author, true);
            $this->fail('A /77 author must not create a course filed under tenant 1.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $this->assertSame($before, $DB->count_records('course'));
    }

    public function test_a_scoped_author_still_publishes_their_tenants_draft(): void {
        global $DB;
        $author = $this->tenant_admin_at('/1/2');
        $this->setUser($author);
        $did = $this->approved_draft_by($author);

        $result = course_builder::build($did, $author, true);
        $this->assertSame('/1', get_course($result->courseid)->open_path);
        $this->assertSame(draft_manager::STATUS_PUBLISHED,
            $DB->get_field(draft_manager::DRAFT_TABLE, 'status', ['id' => $did]));
    }

    public function test_cross_tenant_callers_still_publish_a_tenantless_draft(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $did = $this->approved_draft_by($USER);
        $result = course_builder::build($did, $USER, true);
        $this->assertEmpty(get_course($result->courseid)->open_path,
            'A cross-tenant actor\'s tenantless draft keeps the NULL path, as before.');

        $platform = $this->user_at('');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(\local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW,
            $roleid, \context_system::instance()->id);
        role_assign($roleid, $platform->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $draft = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $this->draft_by($platform)], '*', MUST_EXIST);
        course_builder::require_publishable_tenant($draft, $platform);
        $this->assertSame(0, (int) $draft->costcenterid, 'A :crosstenant holder passes the tenant check.');
    }

    public function test_the_upgrade_revoke_removes_existing_grants(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/sentientia_authoring/db/upgradelib.php');
        // What an install before 2026-09-25 (or reset_role_capabilities()) left behind.
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        assign_capability(self::CAP, CAP_ALLOW, $managerid, \context_system::instance()->id);
        $holder = $this->user_at('/1');
        role_assign($managerid, $holder->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::CAP, \context_system::instance(), $holder->id));

        $this->assertGreaterThanOrEqual(1, local_sentientia_authoring_revoke_manage_all());

        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => self::CAP]));
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability(self::CAP, \context_system::instance(), $holder->id));
    }
}
