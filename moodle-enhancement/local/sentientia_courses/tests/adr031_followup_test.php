<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_courses\external\approve_request;
use local_sentientia_courses\external\list_course_shares;
use local_sentientia_courses\external\reject_request;
use local_sentientia_courses\external\share_course;
use local_sentientia_courses\external\unshare_course;
use local_sentientia_courses\form\edit_course;
use local_sentientia_courses\form\enrol_users_modal;

/**
 * ADR-031 follow-up (2026-09-25): the wave-1 reviewer's should-fix items for
 * the course engine.
 *
 *   S1  the create / edit form listed every category, so a tenant admin read
 *       the names of categories that hold only other tenants' courses;
 *   S2  in their own tenant's courses a tenant admin could enrol people as
 *       manager or coursecreator, and the enrol CSV took any role shortname,
 *       'administrator' included (the modal hid it);
 *   S3  list_course_shares returned any course's share state to any :view
 *       holder;
 *   S4  share / unshare / approve / reject and their pages gated on a
 *       capability alone, so a later grant to a tenant-admin role would have
 *       unscoped it;
 *   S6  the enrol CSV and bulk unenrol looked a user up by email across every
 *       tenant and only then checked the tenant, so with a duplicate address
 *       the caller's own user could read "not found".
 *
 * Each test uses a manager-archetype role assigned at system context - the
 * shape of UAT role 9 - for a tenant admin, alongside the site admin.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_courses\course_manager
 * @covers     \local_sentientia_courses\sharing_manager
 * @covers     \local_sentientia_courses\enrol_csv_processor
 * @covers     \local_sentientia_courses\external\list_course_shares
 * @covers     \local_sentientia_courses\external\share_course
 * @covers     \local_sentientia_courses\external\unshare_course
 * @covers     \local_sentientia_courses\external\approve_request
 * @covers     \local_sentientia_courses\external\reject_request
 * @covers     \local_sentientia_courses\form\edit_course
 * @covers     \local_sentientia_courses\form\enrol_users_modal
 * @group      tenant_isolation
 */
final class adr031_followup_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int manager-archetype role, as UAT's tenant-admin role 9 */
    private $tenantadminrole;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->tenantadminrole = (int) $this->getDataGenerator()->create_role([
            'shortname' => 'adr031fftenantadmin', 'archetype' => 'manager']);
    }

    // ── fixtures ─────────────────────────────────────────────────────────

    /** A user whose open_path is $path ('' = unresolvable tenant). */
    private function user_at(string $path, array $record = []): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user($record);
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin: manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        $u = $this->user_at($path);
        role_assign($this->tenantadminrole, $u->id, \context_system::instance()->id);
        return $u;
    }

    /** A course at an open_path (null = legacy course with no open_path). */
    private function course_at(?string $path, array $record = []): \stdClass {
        global $DB;
        $c = $this->getDataGenerator()->create_course($record + ['visible' => 1]);
        $DB->set_field('course', 'open_path', $path, ['id' => $c->id]);
        return $DB->get_record('course', ['id' => $c->id], '*', MUST_EXIST);
    }

    /** Run $fn and return the moodle_exception error code it threw ('' if none). */
    private function errorcode(callable $fn): string {
        try {
            $fn();
        } catch (\moodle_exception $e) {
            return (string) $e->errorcode;
        }
        return '';
    }

    private function roleid(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /** Role shortnames of an enrol_role_choices() result. */
    private function shortnames(array $choices): array {
        $out = array_values(array_map(fn($r) => (string) $r->shortname, $choices));
        sort($out);
        return $out;
    }

    /** Let the tenant-admin role hold $capability at system context. */
    private function grant(string $capability): void {
        assign_capability($capability, CAP_ALLOW, $this->tenantadminrole,
            \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }

    // ── S1: category options ─────────────────────────────────────────────

    public function test_category_options_hide_categories_holding_only_other_tenants_courses(): void {
        $gen = $this->getDataGenerator();
        $foreign = $gen->create_category(['name' => 'ZEEA only']);
        $mixed = $gen->create_category(['name' => 'Mixed']);
        $own = $gen->create_category(['name' => 'Airpay only']);
        $legacy = $gen->create_category(['name' => 'Legacy']);
        $empty = $gen->create_category(['name' => 'Empty']);
        $this->course_at('/177', ['category' => $foreign->id]);
        $this->course_at('/177', ['category' => $mixed->id]);
        $this->course_at('/1', ['category' => $mixed->id]);
        $this->course_at('/1/2', ['category' => $own->id]);
        $this->course_at(null, ['category' => $legacy->id]);

        $this->setUser($this->tenant_admin('/1'));
        $ids = array_keys(course_manager::edit_category_options());
        $this->assertNotContains((int) $foreign->id, $ids,
            'a category holding only /177 courses is /177 data for a /1 tenant admin');
        foreach ([$mixed, $own, $legacy, $empty] as $cat) {
            $this->assertContains((int) $cat->id, $ids, "category '{$cat->name}' stays listed");
        }

        // The form uses the same list, and refuses a create into a hidden category.
        $form = new edit_course(null, null, 'post', '', null, true, ['courseid' => 0], true);
        $errors = $form->validation(['courseid' => 0, 'category' => (int) $foreign->id,
            'open_costcenterid' => 0, 'fullname' => 'X', 'shortname' => ''], []);
        $this->assertArrayHasKey('category', $errors);
        $errors = $form->validation(['courseid' => 0, 'category' => (int) $own->id,
            'open_costcenterid' => 0, 'fullname' => 'X', 'shortname' => ''], []);
        $this->assertArrayNotHasKey('category', $errors);

        $this->setAdminUser();
        $this->assertContains((int) $foreign->id, array_keys(course_manager::edit_category_options()),
            'a cross-tenant caller keeps every visible category');
    }

    public function test_a_new_tenant_can_still_create_a_course(): void {
        global $DB;
        $default = \core_course_category::get_default();
        $foreign = $this->getDataGenerator()->create_category(['name' => 'ZEEA only']);
        // Every category holds another tenant's courses: nothing is empty or own.
        $this->course_at('/177', ['category' => $default->id]);
        $this->course_at('/177', ['category' => $foreign->id]);

        $this->setUser($this->tenant_admin('/77'));
        $options = course_manager::edit_category_options();
        $this->assertSame([(int) $default->id], array_keys($options),
            'with nothing else left the default category is offered, never an empty list');

        $id = course_manager::create((object) ['fullname' => 'First', 'shortname' => 'adr031fffirst',
            'category' => (int) $default->id, 'open_costcenterid' => 0]);
        $this->assertSame('/77', $DB->get_field('course', 'open_path', ['id' => $id]));

        $this->assertSame('error_outoftenant', $this->errorcode(fn() => course_manager::create((object) [
            'fullname' => 'Planted', 'shortname' => 'adr031ffplanted', 'category' => (int) $foreign->id])));
        $this->assertFalse($DB->record_exists('course', ['shortname' => 'adr031ffplanted']));
    }

    public function test_update_cannot_move_a_course_into_a_hidden_category(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $foreign = $gen->create_category();
        $own = $gen->create_category();
        $empty = $gen->create_category();
        $this->course_at('/177', ['category' => $foreign->id]);
        $mine = $this->course_at('/1', ['category' => $own->id]);

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame('error_outoftenant', $this->errorcode(
            fn() => course_manager::update((int) $mine->id, (object) ['category' => (int) $foreign->id])));
        $this->assertEquals($own->id, $DB->get_field('course', 'category', ['id' => $mine->id]));

        // Staying put, and moving into a category the form offers, still work.
        course_manager::update((int) $mine->id, (object) ['category' => (int) $own->id, 'fullname' => 'Renamed']);
        course_manager::update((int) $mine->id, (object) ['category' => (int) $empty->id]);
        $this->assertEquals($empty->id, $DB->get_field('course', 'category', ['id' => $mine->id]));
    }

    // ── S2: enrolment roles ──────────────────────────────────────────────

    public function test_scoped_enrol_roles_follow_the_allow_assign_matrix_minus_site_level_roles(): void {
        $gen = $this->getDataGenerator();
        // A role holding a site-administration capability, which the tenant
        // admin role is (mis)configured to be able to assign.
        $sitelevel = (int) $gen->create_role(['shortname' => 'adr031ffsitelevel',
            'moodle/user:update' => 'allow']);
        // A harmless course-level role the tenant admin may assign.
        $helper = (int) $gen->create_role(['shortname' => 'adr031ffhelper']);
        // A course-level role the allow-assign matrix does not give them.
        $gen->create_role(['shortname' => 'adr031ffnotallowed']);
        core_role_set_assign_allowed($this->tenantadminrole, $sitelevel);
        core_role_set_assign_allowed($this->tenantadminrole, $helper);

        $own = $this->course_at('/1');
        $this->setUser($this->tenant_admin('/1'));
        $names = $this->shortnames(course_manager::enrol_role_choices($own, 1));
        foreach (['student', 'teacher', 'editingteacher', 'adr031ffhelper'] as $sn) {
            $this->assertContains($sn, $names, "{$sn} is still offered in an own-tenant course");
        }
        foreach (['manager', 'coursecreator', 'adr031fftenantadmin', 'adr031ffsitelevel',
                  'adr031ffnotallowed', 'guest', 'user', 'frontpage'] as $sn) {
            $this->assertNotContains($sn, $names, "{$sn} must not be given by a scoped caller");
        }
        $this->assertContains($sitelevel, course_manager::scoped_forbidden_role_ids());
        $this->assertNotContains($helper, course_manager::scoped_forbidden_role_ids());

        // Cross-tenant callers are unchanged: every role but the hidden ones.
        $this->setAdminUser();
        $names = $this->shortnames(course_manager::enrol_role_choices($own, null));
        $this->assertContains('manager', $names);
        $this->assertContains('adr031ffsitelevel', $names);
        $this->assertNotContains('guest', $names);
    }

    public function test_enrol_modal_refuses_a_manager_role_but_keeps_teacher_roles(): void {
        $own = $this->course_at('/1');
        $a = $this->user_at('/1/2');
        $b = $this->user_at('/1/2');
        $this->setUser($this->tenant_admin('/1'));

        $submit = function(int $roleid, \stdClass $u) use ($own) {
            $data = enrol_users_modal::mock_ajax_submit(['courseid' => (int) $own->id,
                'roleid' => $roleid, 'userids' => [(int) $u->id]]);
            $form = new enrol_users_modal(null, null, 'post', '', null, true, $data, true);
            $form->set_data_for_dynamic_submission();
            return $form->process_dynamic_submission();
        };

        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => $submit($this->roleid('manager'), $a)));
        $this->assertFalse(is_enrolled(\context_course::instance($own->id), $a->id),
            'a tenant admin must not make anyone a course manager');

        $r = $submit($this->roleid('editingteacher'), $b);
        $this->assertSame(1, $r['enrolled']);
        $this->assertTrue(user_has_role_assignment($b->id, $this->roleid('editingteacher'),
            \context_course::instance($own->id)->id));
    }

    public function test_enrol_csv_accepts_only_the_roles_the_modal_offers(): void {
        global $DB;
        // UAT's tenant-admin role is called 'administrator' (manager archetype).
        $this->getDataGenerator()->create_role(['shortname' => 'administrator', 'archetype' => 'manager']);
        $own = $this->course_at('/1', ['shortname' => 'ADR031FFOWN']);
        $u = [];
        for ($i = 0; $i < 6; $i++) {
            $u[$i] = $this->user_at('/1/2');
        }
        $admin = $this->tenant_admin('/1');

        $this->setUser($admin);
        $csv = "email,courseshortname,role\n"
            . "{$u[0]->email},ADR031FFOWN,manager\n"
            . "{$u[1]->email},ADR031FFOWN,administrator\n"
            . "{$u[2]->email},ADR031FFOWN,coursecreator\n"
            . "{$u[3]->email},ADR031FFOWN,editingteacher\n";
        $r = enrol_csv_processor::process($csv, (int) $admin->id);
        $this->assertCount(3, $r['failed'], 'manager, administrator and coursecreator are refused');
        $this->assertSame([$u[3]->email], array_column($r['succeeded'], 'email'));
        $this->assertSame(0, $DB->count_records('role_assignments', ['roleid' => $this->roleid('manager'),
            'contextid' => \context_course::instance($own->id)->id]));

        // The same list for a cross-tenant caller: 'administrator' is hidden
        // from the modal for everyone, so the CSV refuses it too.
        $this->setAdminUser();
        $csv = "email,courseshortname,role\n"
            . "{$u[4]->email},ADR031FFOWN,administrator\n"
            . "{$u[5]->email},ADR031FFOWN,manager\n";
        $r = enrol_csv_processor::process($csv, (int) get_admin()->id);
        $this->assertSame([$u[4]->email], array_column($r['failed'], 'email'));
        $this->assertSame([$u[5]->email], array_column($r['succeeded'], 'email'));
    }

    // ── S3: share state ──────────────────────────────────────────────────

    public function test_list_course_shares_is_confined_to_the_callers_tenant(): void {
        $own = $this->course_at('/1');
        $zeea = $this->course_at('/177');
        $legacy = $this->course_at(null);
        $this->setAdminUser();
        sharing_manager::share_course((int) $own->id, [77]);
        sharing_manager::share_course((int) $zeea->id, [1]);

        $this->setUser($this->tenant_admin('/1'));
        $r = list_course_shares::execute((int) $own->id);
        $this->assertSame([77], array_column($r['shares'], 'tenant_id'), 'own course: readable');
        $this->assertSame([], list_course_shares::execute((int) $legacy->id)['shares']);
        $this->assertSame('error_outoftenant', $this->errorcode(fn() => list_course_shares::execute((int) $zeea->id)),
            'a /177 course\'s share state (shared_by, timestamps) is /177 data');
        $this->assertSame('error_outoftenant', $this->errorcode(fn() => list_course_shares::execute(999999)),
            'a missing course reads exactly like a foreign one');

        $this->setUser($this->tenant_admin(''));
        $this->assertSame('error_outoftenant', $this->errorcode(fn() => list_course_shares::execute((int) $own->id)));
        $this->assertSame('error_outoftenant', $this->errorcode(fn() => list_course_shares::execute((int) $legacy->id)),
            'a caller with no tenant reads nothing');

        $this->setAdminUser();
        $this->assertSame([1], array_column(list_course_shares::execute((int) $zeea->id)['shares'], 'tenant_id'));
    }

    // ── S4: sharing is cross-tenant only ─────────────────────────────────

    public function test_sharing_web_services_need_a_cross_tenant_caller_even_with_the_capability(): void {
        global $DB;
        $own = $this->course_at('/1');
        $zeea = $this->course_at('/177');
        // A later grant of the sharing capabilities to the tenant-admin role.
        $this->grant('local/sentientia_courses:share_to_tenant');
        $this->grant('local/sentientia_courses:approve_request');
        $this->setAdminUser();
        sharing_manager::share_course((int) $zeea->id, [77]);

        $this->setUser($this->tenant_admin('/1'));
        $_POST['sesskey'] = sesskey();
        $this->assertFalse(sharing_manager::can_share(), 'no Share icon for a scoped holder');
        $this->assertSame('error_crosstenantonly',
            $this->errorcode(fn() => share_course::execute((int) $own->id, [77, 177])));
        $this->assertSame('error_crosstenantonly',
            $this->errorcode(fn() => unshare_course::execute((int) $zeea->id, 77)));
        $this->assertFalse($DB->record_exists('local_sentientia_courses_tenant_share', ['courseid' => $own->id]));
        $this->assertTrue(sharing_manager::is_course_shared_to((int) $zeea->id, 77),
            'a /1 tenant admin must not withdraw a /177 course from /77');

        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();
        $this->assertTrue(sharing_manager::can_share());
        $r = share_course::execute((int) $own->id, [77]);
        $this->assertSame([77], $r['shared']);
        $this->assertTrue(unshare_course::execute((int) $zeea->id, 77)['changed']);
    }

    public function test_share_requests_are_decided_by_a_cross_tenant_caller_only(): void {
        global $DB;
        $own = $this->course_at('/1');
        $this->grant('local/sentientia_courses:approve_request');
        $this->setUser($this->user_at('/77'));
        $rid = request_manager::create_request((int) $own->id);
        $this->assertGreaterThan(0, $rid);

        $this->setUser($this->tenant_admin('/1'));
        $_POST['sesskey'] = sesskey();
        $this->assertSame('error_crosstenantonly', $this->errorcode(fn() => approve_request::execute($rid)));
        $this->assertSame('error_crosstenantonly', $this->errorcode(fn() => reject_request::execute($rid, 'no')));
        $this->assertSame('pending', $DB->get_field('local_sentientia_courses_requests', 'status', ['id' => $rid]));
        $this->assertFalse(sharing_manager::is_course_shared_to((int) $own->id, 77));

        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();
        $this->assertTrue(approve_request::execute($rid)['changed']);
        $this->assertTrue(sharing_manager::is_course_shared_to((int) $own->id, 77));
    }

    // ── S6: email lookups ────────────────────────────────────────────────

    public function test_email_lookup_is_bounded_to_the_callers_tenant_before_a_row_is_picked(): void {
        set_config('allowaccountssameemail', 1);
        // The foreign account is created FIRST, so an unbounded get_record()
        // returned it and the caller's own user read "not found".
        $theirs = $this->user_at('/177', ['email' => 'adr031dup@example.com']);
        $mine = $this->user_at('/1/2', ['email' => 'adr031dup@example.com']);

        $this->assertSame([(int) $mine->id],
            array_map('intval', array_column(course_manager::users_by_email_in_scope('adr031dup@example.com', 1), 'id')));
        $this->assertSame([(int) $theirs->id],
            array_map('intval', array_column(course_manager::users_by_email_in_scope('adr031dup@example.com', 177), 'id')));
        $this->assertCount(2, course_manager::users_by_email_in_scope('adr031dup@example.com', null));
        $this->assertSame([], course_manager::users_by_email_in_scope('adr031dup@example.com', 10),
            '/10 is not a prefix match for /1 or /177');

        $own = $this->course_at('/1', ['shortname' => 'ADR031FFDUP']);
        $admin = $this->tenant_admin('/1');
        $this->setUser($admin);
        $r = enrol_csv_processor::process("email,courseshortname\nadr031dup@example.com,ADR031FFDUP\n",
            (int) $admin->id);
        $this->assertCount(1, $r['succeeded'], 'the caller\'s own user is found despite the foreign duplicate');
        $this->assertTrue(is_enrolled(\context_course::instance($own->id), $mine->id));
        $this->assertFalse(is_enrolled(\context_course::instance($own->id), $theirs->id));

        // A cross-tenant caller sees both accounts: ambiguous, so nobody is guessed.
        $other = $this->course_at('/1', ['shortname' => 'ADR031FFDUP2']);
        $this->setAdminUser();
        $r = enrol_csv_processor::process("email,courseshortname\nadr031dup@example.com,ADR031FFDUP2\n",
            (int) get_admin()->id);
        $this->assertCount(1, $r['failed']);
        $this->assertSame([], $r['succeeded']);
        $this->assertFalse(is_enrolled(\context_course::instance($other->id), $mine->id));
    }

    public function test_email_lookup_fails_closed_for_a_bad_scope(): void {
        $this->user_at('/1', ['email' => 'adr031solo@example.com']);
        $this->assertSame([], course_manager::users_by_email_in_scope('adr031solo@example.com', 0));
        $this->assertSame([], course_manager::users_by_email_in_scope('', 1));
        $this->assertCount(1, course_manager::users_by_email_in_scope('adr031solo@example.com', 1));
    }
}
