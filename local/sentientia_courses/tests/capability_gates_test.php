<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * The course-manager / enroller gates ask about capabilities that exist.
 *
 * Persona pass 2026-09-30, D9. course_manager::can_manage() and can_enrol()
 * also asked has_capability('local/courses:manage' | 'local/courses:enrol'),
 * BizLMS names that ADR-025 renamed to local/sentientia_courses:manage|enrol and
 * that no db/access.php declares. Core answers an unknown capability with
 * false plus a debugging() notice - so the branch could never help, and the
 * theme (which calls both helpers on every course view) logged the notice each
 * time. PHPUnit fails a test on any unexpected debugging() call, so a plain
 * call here is the regression check.
 *
 * The pages that gated on the same retired name (manager/index.php,
 * skills/index.php, notifications/nudge.php) now ask the new capability and
 * rely on ADR-031 tenant scoping AFTER the gate, which the last test pins.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_courses\course_manager
 *
 * @group tenant_isolation
 */
final class capability_gates_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /**
     * A user, in tenant $path, whose only system-level role allows $caps.
     *
     * @param string[] $caps
     * @param string $path open_path (a tenant root such as '/8001')
     * @return \stdClass
     */
    private function user_with(array $caps, string $path = '/8001'): \stdClass {
        global $DB;
        $syscontext = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $user->open_path = $path;
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
        role_assign($roleid, $user->id, $syscontext->id);
        return $user;
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    public function test_holder_of_the_manage_capability_can_manage_but_not_enrol(): void {
        $this->setUser($this->user_with(['local/sentientia_courses:manage']));

        $this->assertTrue(course_manager::can_manage());
        $this->assertFalse(course_manager::can_enrol());
        $this->assertDebuggingNotCalled();
    }

    public function test_holder_of_the_enrol_capability_can_enrol_but_not_manage(): void {
        $this->setUser($this->user_with(['local/sentientia_courses:enrol']));

        $this->assertTrue(course_manager::can_enrol());
        $this->assertFalse(course_manager::can_manage());
        $this->assertDebuggingNotCalled();
    }

    public function test_plain_user_can_neither_manage_nor_enrol(): void {
        $this->setUser($this->user_with(['local/sentientia_courses:view']));

        $this->assertFalse(course_manager::can_manage());
        $this->assertFalse(course_manager::can_enrol());
        $this->assertDebuggingNotCalled();
    }

    public function test_site_admin_can_manage_and_enrol(): void {
        $this->setAdminUser();

        $this->assertTrue(course_manager::can_manage());
        $this->assertTrue(course_manager::can_enrol());
        $this->assertDebuggingNotCalled();
    }

    /**
     * The retired names stay retired: nothing declares them on a Sentientia
     * install, and neither helper consults them (a consult would be a
     * debugging() call, which fails this test).
     */
    public function test_retired_bizlms_names_are_not_consulted(): void {
        if (get_capability_info('local/courses:manage') || get_capability_info('local/courses:enrol')) {
            $this->markTestSkipped('BizLMS local_courses is installed here; the retired names are declared.');
        }
        $this->setUser($this->user_with([]));

        $this->assertFalse(course_manager::can_manage());
        $this->assertFalse(course_manager::can_enrol());
        $this->assertDebuggingNotCalled();
    }

    /**
     * The capability says WHAT, never WHERE (ADR-031). What the three pages do
     * once a :manage holder is through their gate: a tenant-8001 admin is a
     * course manager, is not cross-tenant, may act on a tenant-8001 user and is
     * refused a tenant-8002 user.
     */
    public function test_manage_holder_is_still_bound_to_their_own_tenant(): void {
        $admin = $this->user_with(['local/sentientia_courses:manage'], '/8001');
        $sametenant = $this->user_with([], '/8001');
        $othertenant = $this->user_with([], '/8002');
        $this->setUser($admin);

        $this->assertTrue(course_manager::can_manage());
        $this->assertFalse(\local_sentientia_platform\tenant::is_cross_tenant((int) $admin->id));

        \local_sentientia_platform\tenant::require_same_tenant_user((int) $sametenant->id);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_outoftenant', 'local_sentientia_platform'));
        \local_sentientia_platform\tenant::require_same_tenant_user((int) $othertenant->id);
    }
}
