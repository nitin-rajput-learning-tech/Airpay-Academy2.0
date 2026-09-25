<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_courses\external\enrol_single;
use local_sentientia_courses\external\list_course_enrolments;
use local_sentientia_courses\external\list_courses;
use local_sentientia_courses\external\remove_featured;
use local_sentientia_courses\external\toggle_visibility;
use local_sentientia_courses\external\unenrol_single;
use local_sentientia_courses\form\edit_course;
use local_sentientia_courses\form\enrol_users_modal;

/**
 * ADR-031 (2026-09-25): the course engine's capabilities say WHAT, never WHERE.
 *
 * Every tenant admin holds a manager-archetype role at system context and so
 * :create, :update, :visibility, :enrol, :manage and :view. Until this date
 * each of those was the ONLY gate, and every one of them reached every tenant:
 * hide or edit any course by id, re-home a course into another tenant, enrol
 * or unenrol anyone anywhere (with any course role), curate every tenant's
 * featured list, and list any tenant's catalogue by passing its org id. A
 * caller whose open_path did not resolve was unscoped in the export and the
 * enrol picker.
 *
 * Each test uses a manager-archetype role assigned at system context - the
 * shape of UAT role 9 - for a tenant admin at /1, a caller with an empty
 * open_path holding the same role, and the site admin.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_courses\course_manager
 * @covers     \local_sentientia_courses\featured_manager
 * @covers     \local_sentientia_courses\enrol_csv_processor
 * @covers     \local_sentientia_courses\external\toggle_visibility
 * @covers     \local_sentientia_courses\external\list_courses
 * @covers     \local_sentientia_courses\external\list_course_enrolments
 * @covers     \local_sentientia_courses\external\enrol_single
 * @covers     \local_sentientia_courses\external\unenrol_single
 * @covers     \local_sentientia_courses\external\remove_featured
 * @covers     \local_sentientia_courses\form\edit_course
 * @covers     \local_sentientia_courses\form\enrol_users_modal
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int manager-archetype role, as UAT's tenant-admin role 9 */
    private $tenantadminrole;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->tenantadminrole = (int) $this->getDataGenerator()->create_role([
            'shortname' => 'adr031tenantadmin', 'archetype' => 'manager']);
    }

    // ── fixtures ─────────────────────────────────────────────────────────

    /** A user whose open_path is $path ('' = unresolvable tenant). */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
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

    /** An org row with a fixed id, filling any NOT NULL column the schema adds. */
    private function org(int $id, string $path, int $depth, int $parentid = 0): void {
        global $DB;
        $rec = ['id' => $id, 'fullname' => 'Org ' . $id, 'shortname' => 'ORG' . $id,
                'parentid' => $parentid, 'path' => $path, 'depth' => $depth, 'visible' => 1];
        foreach ($DB->get_columns('local_sentientia_org') as $col) {
            if (isset($rec[$col->name]) || !$col->not_null || $col->has_default) {
                continue;
            }
            $rec[$col->name] = ($col->meta_type === 'C' || $col->meta_type === 'X') ? '' : 0;
        }
        $DB->insert_record_raw('local_sentientia_org', (object) $rec, true, false, true);
    }

    /** The three orgs most tests need: tenant roots 1 and 177, and /1/2. */
    private function orgs(): void {
        $this->org(1, '/1', 1);
        $this->org(2, '/1/2', 2, 1);
        $this->org(177, '/177', 1);
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

    private function list_ids(array $filters = []): array {
        $r = list_courses::execute('', 'fullname', 'asc', 0, 100, json_encode((object) $filters));
        $ids = array_map('intval', array_column($r['rows'], 'id'));
        sort($ids);
        return $ids;
    }

    private function enrol(\stdClass $user, \stdClass $course, string $role = 'student'): void {
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role, 'manual');
    }

    // ── P0: course writes ────────────────────────────────────────────────

    public function test_tenant_admin_cannot_hide_another_tenants_course(): void {
        global $DB;
        $admin = $this->tenant_admin('/1');
        $zeea = $this->course_at('/177');

        $this->setUser($admin);
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => toggle_visibility::execute((int) $zeea->id, false)));
        $this->assertEquals(1, (int) $DB->get_field('course', 'visible', ['id' => $zeea->id]),
            'a /1 tenant admin must not hide a /177 course');
    }

    public function test_tenant_admin_still_hides_their_own_course(): void {
        global $DB;
        $admin = $this->tenant_admin('/1');
        $own = $this->course_at('/1/2');

        $this->setUser($admin);
        $r = toggle_visibility::execute((int) $own->id, false);
        $this->assertFalse($r['visible']);
        $this->assertEquals(0, (int) $DB->get_field('course', 'visible', ['id' => $own->id]));
    }

    public function test_scoped_callers_cannot_write_to_a_legacy_unscoped_course(): void {
        // Every tenant lists a course with no open_path, so changing it reaches every tenant.
        $legacy = $this->course_at(null);
        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => course_manager::toggle_visibility((int) $legacy->id, false)));
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => course_manager::update((int) $legacy->id, (object) ['fullname' => 'X'])));
    }

    public function test_a_caller_with_no_tenant_cannot_write_any_course(): void {
        $own = $this->course_at('/1');
        foreach (['', 'garbage'] as $path) {
            $this->setUser($this->tenant_admin($path));
            $this->assertSame('error_outoftenant',
                $this->errorcode(fn() => course_manager::toggle_visibility((int) $own->id, false)),
                "open_path '{$path}' must not unlock any course");
            $this->assertSame('invalidtenant', $this->errorcode(fn() => course_manager::create((object) [
                'fullname' => 'N', 'shortname' => 'adr031n' . random_int(1000, 9999),
                'category' => \core_course_category::get_default()->id])));
        }
    }

    public function test_the_site_admin_still_writes_every_course(): void {
        global $DB;
        $zeea = $this->course_at('/177');
        $legacy = $this->course_at(null);
        $this->setAdminUser();
        toggle_visibility::execute((int) $zeea->id, false);
        course_manager::toggle_visibility((int) $legacy->id, false);
        $this->assertEquals(0, (int) $DB->get_field('course', 'visible', ['id' => $zeea->id]));
        $this->assertEquals(0, (int) $DB->get_field('course', 'visible', ['id' => $legacy->id]));
    }

    public function test_edit_form_refuses_another_tenants_course_before_prefill(): void {
        $zeea = $this->course_at('/177');
        $own = $this->course_at('/1');
        $this->orgs();
        $this->setUser($this->tenant_admin('/1'));

        // Core runs check_access_for_dynamic_submission() in the constructor,
        // before set_data_for_dynamic_submission() reads the course record.
        $this->assertSame('error_outoftenant', $this->errorcode(fn() => new edit_course(
            null, null, 'post', '', null, true, ['courseid' => (int) $zeea->id], true)));
        $form = new edit_course(null, null, 'post', '', null, true, ['courseid' => (int) $own->id], true);
        $this->assertInstanceOf(edit_course::class, $form);
    }

    public function test_update_cannot_move_a_course_into_another_tenant(): void {
        global $DB;
        $this->orgs();
        $own = $this->course_at('/1');
        $this->setUser($this->tenant_admin('/1'));

        $this->assertSame('error_outoftenant', $this->errorcode(
            fn() => course_manager::update((int) $own->id, (object) ['open_costcenterid' => 177])));
        $this->assertSame('/1', $DB->get_field('course', 'open_path', ['id' => $own->id]));

        // Inside their own tenant, re-homing still works.
        course_manager::update((int) $own->id, (object) ['open_costcenterid' => 2]);
        $this->assertSame('/1/2', $DB->get_field('course', 'open_path', ['id' => $own->id]));
    }

    public function test_create_lands_in_the_callers_tenant_and_refuses_a_foreign_org(): void {
        global $DB;
        $this->orgs();
        $this->setUser($this->tenant_admin('/1'));
        $cat = \core_course_category::get_default()->id;

        // "No specific organisation" used to create a course every tenant lists.
        $id = course_manager::create((object) ['fullname' => 'Mine', 'shortname' => 'adr031mine',
            'category' => $cat, 'open_costcenterid' => 0]);
        $this->assertSame('/1', $DB->get_field('course', 'open_path', ['id' => $id]));

        $this->assertSame('error_outoftenant', $this->errorcode(fn() => course_manager::create((object) [
            'fullname' => 'Planted', 'shortname' => 'adr031planted', 'category' => $cat,
            'open_costcenterid' => 177])));
        $this->assertFalse($DB->record_exists('course', ['shortname' => 'adr031planted']));
    }

    public function test_org_dropdown_lists_only_the_callers_tenant(): void {
        $this->orgs();
        $this->setUser($this->tenant_admin('/1'));
        $ids = array_keys(course_manager::org_options());
        sort($ids);
        $this->assertSame([0, 1, 2], $ids, 'no /177 org for a /1 tenant admin');

        $this->setUser($this->tenant_admin(''));
        $this->assertSame([0], array_keys(course_manager::org_options()));

        $this->setAdminUser();
        $ids = array_keys(course_manager::org_options());
        sort($ids);
        $this->assertSame([0, 1, 2, 177], $ids);
    }

    // ── P1: reads ────────────────────────────────────────────────────────

    public function test_org_cascade_cannot_list_another_tenants_courses(): void {
        $this->orgs();
        $own = $this->course_at('/1');
        $zeea = $this->course_at('/177');
        $legacy = $this->course_at(null);

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame([], $this->list_ids(['org_l1' => 177]),
            'another tenant\'s org id must not replace the tenant scope');
        $expected = [(int) $own->id, (int) $legacy->id];
        sort($expected);
        $this->assertSame($expected, $this->list_ids(['org_l1' => 999999]),
            'an unknown org id must not drop the tenant scope');

        $this->setAdminUser();
        $this->assertSame([(int) $zeea->id], $this->list_ids(['org_l1' => 177]));
    }

    public function test_export_scope_is_empty_for_a_caller_with_no_tenant(): void {
        global $DB;
        $this->course_at('/1');
        $zeea = $this->course_at('/177');

        $this->setUser($this->tenant_admin(''));
        [$sql, $params] = course_manager::manage_scope_sql('');
        $this->assertSame(0, $DB->count_records_select('course', "id > 1 AND {$sql}", $params),
            'exportcsv.php now uses this scope; it used to export every tenant');

        $this->setUser($this->tenant_admin('/1'));
        [$sql, $params] = course_manager::manage_scope_sql('');
        $this->assertFalse($DB->record_exists_select('course', "id = :z AND {$sql}",
            ['z' => $zeea->id] + $params));

        $this->setAdminUser();
        [$sql, $params] = course_manager::manage_scope_sql('');
        $this->assertTrue($DB->record_exists_select('course', "id = :z AND {$sql}",
            ['z' => $zeea->id] + $params));
    }

    public function test_enrolee_list_is_confined_to_the_viewers_tenant(): void {
        $legacy = $this->course_at(null);
        $zeea = $this->course_at('/177');
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177');
        $this->enrol($mine, $legacy);
        $this->enrol($theirs, $legacy);

        $filters = json_encode(['courseid' => (int) $legacy->id]);
        $this->setUser($this->tenant_admin('/1'));
        $r = list_course_enrolments::execute('', 'lastname', 'asc', 0, 50, $filters);
        $this->assertSame([(int) $mine->id], array_map('intval', array_column($r['rows'], 'userid')));
        $this->assertSame('error_outoftenant', $this->errorcode(fn() => list_course_enrolments::execute(
            '', 'lastname', 'asc', 0, 50, json_encode(['courseid' => (int) $zeea->id]))));

        $this->setAdminUser();
        $r = list_course_enrolments::execute('', 'lastname', 'asc', 0, 50, $filters);
        $this->assertSame(2, $r['total']);
    }

    // ── P0: enrolment ────────────────────────────────────────────────────

    public function test_enrol_single_is_confined_to_the_callers_tenant(): void {
        $own = $this->course_at('/1');
        $zeea = $this->course_at('/177');
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177');

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => enrol_single::execute((int) $zeea->id, $mine->email)));

        $r = enrol_single::execute((int) $own->id, $theirs->email);
        $this->assertFalse($r['success'], 'another tenant\'s email must read as not found');
        $this->assertSame(0, $r['userid']);
        $this->assertFalse(is_enrolled(\context_course::instance($own->id), $theirs->id));

        $r = enrol_single::execute((int) $own->id, $mine->email);
        $this->assertTrue($r['enrolled']);

        $this->setUser($this->tenant_admin(''));
        $this->assertSame('invalidtenant',
            $this->errorcode(fn() => enrol_single::execute((int) $own->id, $mine->email)));

        $this->setAdminUser();
        $this->assertTrue(enrol_single::execute((int) $zeea->id, $theirs->email)['enrolled']);
    }

    public function test_a_course_shared_to_the_tenant_stays_enrollable(): void {
        $zeea = $this->course_at('/177');
        $mine = $this->user_at('/1');
        $this->setAdminUser();
        sharing_manager::share_course((int) $zeea->id, [1]);

        $this->setUser($this->tenant_admin('/1'));
        $this->assertTrue(enrol_single::execute((int) $zeea->id, $mine->email)['enrolled']);

        // ...but only in a learner role: a teacher role there would let the
        // enroller's people edit the owning tenant's course.
        $allowed = course_manager::enrol_allowed_role_ids($zeea, 1);
        $teacher = (int) $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $student = (int) $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'student']);
        $this->assertContains($student, $allowed);
        $this->assertNotContains($teacher, $allowed);
        // Follow-up (decision 6): in an own-tenant course a scoped caller keeps
        // the course roles they may assign - teacher roles included - but never
        // manager, coursecreator or their own tenant-admin role.
        $ownallowed = course_manager::enrol_allowed_role_ids($this->course_at('/1'), 1);
        $this->assertContains($teacher, $ownallowed, 'own-tenant courses keep teacher roles');
        $this->assertContains($student, $ownallowed);
        foreach (['manager', 'coursecreator'] as $sn) {
            $this->assertNotContains((int) $GLOBALS['DB']->get_field('role', 'id', ['shortname' => $sn]),
                $ownallowed, "{$sn} is never given by a scoped caller");
        }
        $this->assertNotContains($this->tenantadminrole, $ownallowed);
        $this->assertNull(course_manager::enrol_allowed_role_ids($zeea, null), 'cross-tenant: unchanged');
    }

    public function test_unenrol_single_is_confined_to_the_callers_tenant(): void {
        $own = $this->course_at('/1');
        $zeea = $this->course_at('/177');
        $mine = $this->user_at('/1');
        $theirs = $this->user_at('/177');
        $this->enrol($theirs, $own);
        $this->enrol($mine, $zeea);

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => unenrol_single::execute((int) $own->id, (int) $theirs->id)));
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => unenrol_single::execute((int) $zeea->id, (int) $mine->id)));
        $this->assertTrue(is_enrolled(\context_course::instance($own->id), $theirs->id));
        $this->assertTrue(is_enrolled(\context_course::instance($zeea->id), $mine->id));

        $this->setAdminUser();
        unenrol_single::execute((int) $own->id, (int) $theirs->id);
        $this->assertFalse(is_enrolled(\context_course::instance($own->id), $theirs->id));
    }

    public function test_enrol_modal_refuses_foreign_courses_and_no_tenant_callers(): void {
        $own = $this->course_at('/1');
        $zeea = $this->course_at('/177');

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame('error_outoftenant', $this->errorcode(fn() => new enrol_users_modal(
            null, null, 'post', '', null, true, ['courseid' => (int) $zeea->id], true)));

        $this->setUser($this->tenant_admin(''));
        $this->assertSame('invalidtenant', $this->errorcode(fn() => new enrol_users_modal(
            null, null, 'post', '', null, true, ['courseid' => (int) $own->id], true)));
    }

    public function test_enrol_modal_submission_cannot_enrol_another_tenants_user(): void {
        global $DB;
        $own = $this->course_at('/1');
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177');
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);

        $this->setUser($this->tenant_admin('/1'));

        // The select below drops the foreign userid in exportValue() before
        // process_dynamic_submission() runs, so it never reaches the server-side
        // user check. Assert that check directly (review S7).
        $this->assertSame('error_outoftenant', $this->errorcode(
            fn() => course_manager::require_enrol_scope((int) $own->id, [(int) $theirs->id])));
        $this->assertSame('error_outoftenant', $this->errorcode(
            fn() => course_manager::require_enrol_scope((int) $own->id, [(int) $mine->id, (int) $theirs->id])),
            'one foreign user refuses the whole batch');
        $this->assertSame(1, course_manager::require_enrol_scope((int) $own->id, [(int) $mine->id]));

        $data = enrol_users_modal::mock_ajax_submit(['courseid' => (int) $own->id,
            'roleid' => $student, 'userids' => [(int) $mine->id, (int) $theirs->id]]);
        $form = new enrol_users_modal(null, null, 'post', '', null, true, $data, true);
        $form->set_data_for_dynamic_submission();
        try {
            $form->process_dynamic_submission();
        } catch (\moodle_exception $e) {
            // Refusing the whole submission is also acceptable.
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $this->assertFalse(is_enrolled(\context_course::instance($own->id), $theirs->id),
            'a /177 user must never be enrolled by a /1 tenant admin');
    }

    public function test_enrol_csv_skips_foreign_courses_and_limits_roles_on_shared_ones(): void {
        $zeea = $this->course_at('/177', ['shortname' => 'ADR031ZEEA']);
        $shared = $this->course_at('/177', ['shortname' => 'ADR031SHARED']);
        $mine = $this->user_at('/1');
        $admin = $this->tenant_admin('/1');
        $this->setAdminUser();
        sharing_manager::share_course((int) $shared->id, [1]);

        $this->setUser($admin);
        $csv = "email,courseshortname,role\n"
            . "{$mine->email},ADR031ZEEA,student\n"
            . "{$mine->email},ADR031SHARED,editingteacher\n"
            . "{$mine->email},ADR031SHARED,student\n";
        $r = enrol_csv_processor::process($csv, (int) $admin->id);

        $this->assertFalse(is_enrolled(\context_course::instance($zeea->id), $mine->id),
            'a course another tenant owns, not shared, reads as not found');
        $this->assertCount(1, $r['succeeded']);
        $this->assertSame('ADR031SHARED', $r['succeeded'][0]['course']);
        $this->assertCount(1, $r['failed'], 'a teacher role in a shared course is refused');
        $this->assertCount(1, $r['skipped']);
    }

    // ── P0: featured courses ─────────────────────────────────────────────

    public function test_featured_curation_is_confined_to_the_callers_tenant(): void {
        $own = $this->course_at('/1');
        $zeea = $this->course_at('/177');
        $this->setAdminUser();
        $global = featured_manager::add((int) $own->id, 0);
        $theirs = featured_manager::add((int) $zeea->id, 177);

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame(1, featured_manager::curation_root());
        foreach ([[(int) $own->id, 0], [(int) $own->id, 177], [(int) $zeea->id, 1]] as [$cid, $list]) {
            $this->assertSame('error_outoftenant',
                $this->errorcode(fn() => featured_manager::assert_can_add($cid, $list)),
                "course {$cid} onto list {$list} must be refused");
        }
        featured_manager::assert_can_add((int) $own->id, 1);
        $mine = featured_manager::add((int) $own->id, 1);

        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => featured_manager::assert_can_edit_rows([$global])));
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => featured_manager::assert_can_edit_rows([$mine, $theirs])));
        featured_manager::assert_can_edit_rows([$mine]);
        $this->assertSame([$mine], array_column(featured_manager::list_all(1), 'id'));

        // The web service is wired to the same check.
        $_POST['sesskey'] = sesskey();
        $this->assertSame('error_outoftenant', $this->errorcode(fn() => remove_featured::execute($theirs)));
        $this->assertTrue($GLOBALS['DB']->record_exists('local_sentientia_featured_courses', ['id' => $theirs]));
    }

    public function test_featured_refuses_a_curator_with_no_tenant_and_keeps_the_site_admin(): void {
        $zeea = $this->course_at('/177');
        $this->setUser($this->tenant_admin(''));
        $this->assertSame('error_outoftenant', $this->errorcode(fn() => featured_manager::curation_root()));

        $this->setAdminUser();
        $this->assertNull(featured_manager::curation_root());
        featured_manager::assert_can_add((int) $zeea->id, 0);
        $row = featured_manager::add((int) $zeea->id, 0);
        featured_manager::assert_can_edit_rows([$row]);
        $this->assertCount(1, featured_manager::list_all());
    }
}
