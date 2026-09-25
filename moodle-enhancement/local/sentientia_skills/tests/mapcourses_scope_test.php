<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_skills\external\copy_designation;
use local_sentientia_skills\external\delete_category;
use local_sentientia_skills\external\delete_course_skill;
use local_sentientia_skills\external\delete_designation_skill;
use local_sentientia_skills\external\list_course_skills;
use local_sentientia_skills\external\save_course_skill;
use local_sentientia_skills\external\save_designation_skill;
use local_sentientia_skills\external\save_skill_level;
use local_sentientia_skills\external\search_courses;
use local_sentientia_skills\form\edit_category;
use local_sentientia_skills\form\edit_designation_skill_dynamic_form;
use local_sentientia_skills\form\edit_skill;
use local_sentientia_skills\form\edit_skill_level_dynamic_form;

/**
 * ADR-031 follow-up (2026-09-25): mapping skills onto courses is a tenant
 * function, and writing the shared catalogue is a cross-tenant one.
 *
 * Wave 1 revoked local/sentientia_skills:manage from every role, because
 * the catalogue it edits has no tenant column. But course mapping also
 * required :manage, so tenant admins could no longer map skills onto their
 * own courses. The documented remedy was to grant :manage back. That would
 * have reopened global writes: only delete_skill checked is_cross_tenant().
 *
 * Now:
 *   - :mapcourses (manager archetype default) covers the course-mapping page
 *     and web services, which stay held to the caller's tenant;
 *   - every catalogue write (categories, skills, levels, designation matrix)
 *     needs :manage AND tenant::is_cross_tenant(), whoever holds :manage.
 *
 * Tenant admins here have the core manager role at system context, which is
 * the shape of UAT role 9.
 *
 * @package    local_sentientia_skills
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_skills\skills_manager
 * @covers     \local_sentientia_skills\external\save_course_skill
 * @covers     \local_sentientia_skills\external\delete_course_skill
 * @covers     \local_sentientia_skills\external\list_course_skills
 * @covers     \local_sentientia_skills\external\search_courses
 * @covers     \local_sentientia_skills\external\copy_designation
 * @covers     \local_sentientia_skills\external\delete_category
 * @covers     \local_sentientia_skills\external\delete_designation_skill
 * @covers     \local_sentientia_skills\external\save_designation_skill
 * @covers     \local_sentientia_skills\external\save_skill_level
 * @covers     \local_sentientia_skills\form\edit_skill
 * @covers     \local_sentientia_skills\form\edit_category
 * @covers     \local_sentientia_skills\form\edit_skill_level_dynamic_form
 * @covers     \local_sentientia_skills\form\edit_designation_skill_dynamic_form
 * @group      tenant_isolation
 */
final class mapcourses_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    // ── fixtures ─────────────────────────────────────────────────────────

    private function category(string $name = 'Mapcourses test'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_skill_cats', (object) [
            'name' => $name, 'icon' => 'fa-cogs', 'color' => '#0066A7',
            'sort_order' => 1, 'timecreated' => time(),
        ]);
    }

    private function skill(string $name = 'Settlements'): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_skills', (object) [
            'categoryid' => $this->category(), 'name' => $name, 'description' => '',
            'max_level' => 5, 'sort_order' => 1, 'timecreated' => time(),
        ]);
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A user at $path holding a role with exactly these system capabilities. */
    private function holder(string $path, array $capabilities): \stdClass {
        $u = $this->user_at($path);
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($capabilities as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $sysctx->id, true);
        }
        role_assign($roleid, $u->id, $sysctx->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    /** A tenant admin: the core manager role at system context, as UAT role 9. */
    private function tenant_admin(string $path, bool $withmanage = false): \stdClass {
        global $DB;
        $u = $withmanage ? $this->holder($path, [skills_manager::CAP_MANAGE]) : $this->user_at($path);
        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST),
            $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function course_at(?string $path): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course ' . ($path ?? 'legacy')]);
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return (int) $course->id;
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

    // ── :mapcourses ──────────────────────────────────────────────────────

    public function test_mapcourses_defaults_to_the_manager_archetype_and_manage_to_nobody(): void {
        global $DB;
        $sysctx = \context_system::instance()->id;
        foreach (get_archetype_roles('manager') as $role) {
            $this->assertTrue($DB->record_exists('role_capabilities', ['roleid' => $role->id,
                'contextid' => $sysctx, 'capability' => skills_manager::CAP_MAP_COURSES,
                'permission' => CAP_ALLOW]), 'tenant admins keep in-tenant course mapping by default');
        }
        foreach (['student', 'editingteacher', 'user'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                $this->assertFalse($DB->record_exists('role_capabilities', ['roleid' => $role->id,
                    'capability' => skills_manager::CAP_MAP_COURSES]));
            }
        }
        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => skills_manager::CAP_MANAGE]),
            ':manage stays revoked from every role');

        $admin = $this->tenant_admin('/1');
        $this->setUser($admin);
        $this->assertTrue(skills_manager::can_map_courses());
        $this->assertFalse(has_capability(skills_manager::CAP_MANAGE, \context_system::instance()));
        $this->assertFalse(skills_manager::can_write_catalogue());
    }

    public function test_a_tenant_admin_maps_their_own_courses_without_manage(): void {
        global $DB;
        $skillid = $this->skill();
        $own = $this->course_at('/1/2');
        $foreign = $this->course_at('/177');
        $legacy = $this->course_at(null);
        $this->setUser($this->tenant_admin('/1'));
        $_POST['sesskey'] = sesskey();

        skills_manager::require_map_courses();  // course_mapping.php's gate
        $id = (int) save_course_skill::execute($own, $skillid, 2)['id'];
        $this->assertTrue($DB->record_exists('local_sentientia_course_skills', ['id' => $id, 'courseid' => $own]));
        $this->assertCount(1, list_course_skills::execute($own)['rows']);
        $found = array_column(search_courses::execute('Course')['rows'], 'id');
        $this->assertContains($own, $found);
        $this->assertNotContains($foreign, $found);

        // WHERE is unchanged: another tenant's course and a legacy course stay refused.
        foreach ([$foreign, $legacy] as $courseid) {
            $this->assertSame('error_outoftenant',
                $this->errorcode(fn() => save_course_skill::execute($courseid, $skillid, 2)));
            $this->assertFalse($DB->record_exists('local_sentientia_course_skills', ['courseid' => $courseid]));
        }
        $this->assertSame([], list_course_skills::execute($foreign)['rows']);

        delete_course_skill::execute($id);
        $this->assertFalse($DB->record_exists('local_sentientia_course_skills', ['id' => $id]));
    }

    public function test_mapping_needs_mapcourses_or_manage(): void {
        $skillid = $this->skill();
        $own = $this->course_at('/1');

        $this->setUser($this->user_at('/1'));
        $_POST['sesskey'] = sesskey();
        $this->assertFalse(skills_manager::can_map_courses());
        $this->assertSame('nopermissions', $this->errorcode(fn() => skills_manager::require_map_courses()));
        $this->assertSame('nopermissions', $this->errorcode(fn() => save_course_skill::execute($own, $skillid, 2)));
        $this->assertSame('nopermissions', $this->errorcode(fn() => search_courses::execute('Course')));

        // A catalogue curator (:manage) has always been able to map courses; still scoped.
        $this->setUser($this->holder('/1', [skills_manager::CAP_MANAGE]));
        $_POST['sesskey'] = sesskey();
        $this->assertTrue(skills_manager::can_map_courses());
        $this->assertGreaterThan(0, save_course_skill::execute($own, $skillid, 2)['id']);
        $this->assertSame('error_outoftenant',
            $this->errorcode(fn() => save_course_skill::execute($this->course_at('/177'), $skillid, 2)));
    }

    // ── catalogue writes ─────────────────────────────────────────────────

    public function test_catalogue_writes_need_a_cross_tenant_caller_even_with_manage(): void {
        global $DB;
        $skillid = $this->skill();
        $emptycat = $this->category('Empty');
        $rowid = (int) $DB->insert_record('local_sentientia_role_skills', (object) [
            'designation' => 'Teller', 'skillid' => $skillid, 'required_level' => 3, 'timecreated' => time()]);

        // The old deploy note's remedy: a tenant admin granted :manage explicitly.
        $this->setUser($this->tenant_admin('/1', true));
        $_POST['sesskey'] = sesskey();
        $this->assertTrue(has_capability(skills_manager::CAP_MANAGE, \context_system::instance()));
        $this->assertFalse(skills_manager::can_write_catalogue());

        $refused = [
            'copy_designation' => fn() => copy_designation::execute('Teller', 'Cashier'),
            'save_designation_skill' => fn() => save_designation_skill::execute('Cashier', $skillid, 4),
            'delete_designation_skill' => fn() => delete_designation_skill::execute($rowid),
            'save_skill_level' => fn() => save_skill_level::execute($skillid, 1, 'Awareness'),
            'delete_category' => fn() => delete_category::execute($emptycat),
            'edit_skill form' => fn() => new edit_skill(null, null, 'post', '', null, true, [], true),
            'edit_category form' => fn() => new edit_category(null, null, 'post', '', null, true, [], true),
            'edit_skill_level form' => fn() => new edit_skill_level_dynamic_form(
                null, null, 'post', '', null, true, ['skillid' => $skillid], true),
            'edit_designation_skill form' => fn() => new edit_designation_skill_dynamic_form(
                null, null, 'post', '', null, true, ['designation' => 'Teller'], true),
        ];
        foreach ($refused as $what => $fn) {
            $this->assertSame('error_catalogueplatformonly', $this->errorcode($fn),
                "{$what}: the catalogue is shared by every tenant, so :manage alone must not write it");
        }
        $this->assertFalse($DB->record_exists('local_sentientia_role_skills', ['designation' => 'Cashier']));
        $this->assertTrue($DB->record_exists('local_sentientia_role_skills', ['id' => $rowid]));
        $this->assertFalse($DB->record_exists('local_sentientia_skill_levels', ['skillid' => $skillid]));
        $this->assertTrue($DB->record_exists('local_sentientia_skill_cats', ['id' => $emptycat]));

        // Without :manage the capability check still comes first.
        $this->setUser($this->tenant_admin('/1'));
        $_POST['sesskey'] = sesskey();
        $this->assertSame('nopermissions', $this->errorcode(fn() => save_skill_level::execute($skillid, 1, 'X')));
    }

    public function test_cross_tenant_curators_still_write_the_catalogue(): void {
        global $DB;
        $skillid = $this->skill();

        // A named platform role: :manage + :crosstenant, not a site admin.
        $curator = $this->holder('/1', [skills_manager::CAP_MANAGE,
            \local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY]);
        $this->setUser($curator);
        $_POST['sesskey'] = sesskey();
        $this->assertTrue(skills_manager::can_write_catalogue());
        $this->assertGreaterThan(0, save_designation_skill::execute('Teller', $skillid, 3)['id']);
        $this->assertSame(1, copy_designation::execute('Teller', 'Cashier')['copied']);
        $this->assertGreaterThan(0, save_skill_level::execute($skillid, 1, 'Awareness')['id']);
        $this->assertInstanceOf(edit_skill::class, new edit_skill(null, null, 'post', '', null, true, [], true));

        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();
        $emptycat = $this->category('Empty');
        $this->assertTrue(delete_category::execute($emptycat)['success']);
        $this->assertFalse($DB->record_exists('local_sentientia_skill_cats', ['id' => $emptycat]));
    }
}
