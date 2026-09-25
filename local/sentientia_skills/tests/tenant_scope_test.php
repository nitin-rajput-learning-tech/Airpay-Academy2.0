<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: a plugin capability says WHAT, never WHERE.
 *
 * The skills catalogue has no tenant column, so :manage (which edits it)
 * no longer defaults to the manager archetype that every tenant admin holds.
 * What :manage and :view still reach that belongs to a tenant - another
 * user's skill level (backfill), the learners holding a skill, courses and
 * designations - is pinned here to the caller's tenant; a caller with no
 * tenant gets nothing; a site admin is unchanged.
 *
 * @package    local_sentientia_skills
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_skills\skills_manager
 * @covers     \local_sentientia_skills\external\self_rate_skill
 * @covers     \local_sentientia_skills\external\save_course_skill
 * @covers     \local_sentientia_skills\external\delete_skill
 * @covers     \local_sentientia_skills\external\list_course_skills
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function seed_skill(string $name = 'Payments'): int {
        global $DB;
        $catid = $DB->insert_record('local_sentientia_skill_cats', (object) [
            'name' => 'Tenant test', 'icon' => 'fa-cogs', 'color' => '#0066A7',
            'sort_order' => 1, 'timecreated' => time(),
        ]);
        return (int) $DB->insert_record('local_sentientia_skills', (object) [
            'categoryid' => $catid, 'name' => $name, 'description' => '',
            'max_level' => 5, 'sort_order' => 1, 'timecreated' => time(),
        ]);
    }

    private function hold(int $userid, int $skillid, int $level = 3): void {
        global $DB;
        $DB->insert_record('local_sentientia_user_skills', (object) [
            'userid' => $userid, 'skillid' => $skillid, 'current_level' => $level,
            'source' => 'course', 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function user_at(string $path, string $designation = ''): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $DB->set_field('user', 'open_designation', $designation, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin (manager-archetype role at system context), optionally given :manage explicitly. */
    private function tenant_admin(string $path, bool $withmanage = false): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $sysctx = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, $sysctx->id);
        if ($withmanage) {
            $roleid = $this->getDataGenerator()->create_role();
            assign_capability('local/sentientia_skills:manage', CAP_ALLOW, $roleid, $sysctx->id);
            role_assign($roleid, $u->id, $sysctx->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function course_at(string $path): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course ' . $path]);
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return (int) $course->id;
    }

    private function assert_outoftenant(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    public function test_manage_has_no_default_holder(): void {
        global $DB;
        $this->assertFalse($DB->record_exists('role_capabilities',
            ['capability' => 'local/sentientia_skills:manage']),
            'No role may hold :manage by default - tenant admins hold manager-archetype roles.');
        $admin = $this->tenant_admin('/1');
        $this->assertFalse(has_capability('local/sentientia_skills:manage',
            \context_system::instance(), $admin->id));
        $this->assertTrue(has_capability('local/sentientia_skills:manage',
            \context_system::instance(), get_admin()->id));
    }

    public function test_backfill_only_inside_the_callers_tenant(): void {
        global $DB;
        $skillid = $this->seed_skill();
        $colleague = $this->user_at('/1/3');
        $foreign = $this->user_at('/177');
        $nowhere = $this->user_at('');
        $this->setUser($this->tenant_admin('/1', true));
        $_POST['sesskey'] = sesskey();

        foreach ([$foreign, $nowhere] as $target) {
            $this->assert_outoftenant(
                fn() => external\self_rate_skill::execute($skillid, 4, (int) $target->id),
                'A /1 :manage holder must not write the skill level of a user outside /1.');
            $this->assertFalse($DB->record_exists('local_sentientia_user_skills',
                ['userid' => $target->id, 'skillid' => $skillid]));
        }
        $result = external\self_rate_skill::execute($skillid, 4, (int) $colleague->id);
        $this->assertSame(4, $result['new_level']);

        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();
        external\self_rate_skill::execute($skillid, 2, (int) $foreign->id);
        $this->assertSame(2, (int) $DB->get_field('local_sentientia_user_skills', 'current_level',
            ['userid' => $foreign->id, 'skillid' => $skillid]));
    }

    public function test_learners_of_a_skill_are_tenant_scoped(): void {
        $skillid = $this->seed_skill();
        $airpay = $this->user_at('/1/2');
        $zeea = $this->user_at('/177');
        $this->hold((int) $airpay->id, $skillid);
        $this->hold((int) $zeea->id, $skillid);

        $this->setUser($this->tenant_admin('/1'));
        $learners = skills_manager::skill_learners($skillid);
        $this->assertSame([(int) $airpay->id], array_map('intval', array_column($learners, 'userid')),
            'view.php learners tab: a /1 viewer must not see /177 names and emails.');
        $this->assertSame(1, skills_manager::count_skill_learners($skillid));

        $this->setUser($this->user_at(''));
        $this->assertSame([], skills_manager::skill_learners($skillid));
        $this->assertSame(0, skills_manager::count_skill_learners($skillid));

        $this->setAdminUser();
        $this->assertCount(2, skills_manager::skill_learners($skillid));
        $this->assertSame(2, skills_manager::count_skill_learners($skillid));
    }

    public function test_course_search_and_designations_are_tenant_scoped(): void {
        $own = $this->course_at('/1/2');
        $foreign = $this->course_at('/177');
        $this->user_at('/1/2', 'Teller');
        $this->user_at('/177', 'Pilot');

        $this->setUser($this->tenant_admin('/1', true));
        $ids = array_column(skills_manager::search_courses('Course'), 'id');
        $this->assertContains($own, $ids);
        $this->assertNotContains($foreign, $ids, 'The course picker must not list other tenants\' courses.');
        $designations = skills_manager::list_designations();
        $this->assertContains('Teller', $designations);
        $this->assertNotContains('Pilot', $designations);

        $this->setUser($this->tenant_admin('', true));
        $this->assertSame([], skills_manager::search_courses('Course'));
        $this->assertNotContains('Teller', skills_manager::list_designations());

        $this->setAdminUser();
        $ids = array_column(skills_manager::search_courses('Course'), 'id');
        $this->assertContains($foreign, $ids);
        $this->assertContains('Pilot', skills_manager::list_designations());
    }

    public function test_course_mapping_writes_stay_in_tenant(): void {
        global $DB;
        $skillid = $this->seed_skill();
        $own = $this->course_at('/1/2');
        $foreign = $this->course_at('/177');
        $legacy = $this->course_at('');
        $this->setUser($this->tenant_admin('/1', true));
        $_POST['sesskey'] = sesskey();

        foreach ([$foreign, $legacy] as $courseid) {
            $this->assert_outoftenant(fn() => external\save_course_skill::execute($courseid, $skillid, 2),
                'A /1 :manage holder must not map skills onto a course outside /1.');
            $this->assertFalse($DB->record_exists('local_sentientia_course_skills', ['courseid' => $courseid]));
        }
        $this->assertGreaterThan(0, external\save_course_skill::execute($own, $skillid, 2)['id']);

        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();
        $this->assertGreaterThan(0, external\save_course_skill::execute($foreign, $skillid, 2)['id']);
    }

    public function test_deleting_a_skill_is_cross_tenant_only(): void {
        global $DB;
        $skillid = $this->seed_skill();
        $this->hold((int) $this->user_at('/177')->id, $skillid);
        $this->setUser($this->tenant_admin('/1', true));

        $this->assert_outoftenant(fn() => external\delete_skill::execute($skillid),
            'Deleting a skill erases every tenant\'s learners\' levels for it.');
        $this->assertTrue($DB->record_exists('local_sentientia_skills', ['id' => $skillid]));
        $this->assertTrue($DB->record_exists('local_sentientia_user_skills', ['skillid' => $skillid]));

        $this->setAdminUser();
        external\delete_skill::execute($skillid);
        $this->assertFalse($DB->record_exists('local_sentientia_skills', ['id' => $skillid]));
    }

    public function test_course_mapping_reads_are_tenant_scoped(): void {
        $skillid = $this->seed_skill();
        $own = $this->course_at('/1/2');
        $foreign = $this->course_at('/177');
        $this->setAdminUser();
        skills_manager::save_course_skill($own, $skillid, 2);
        skills_manager::save_course_skill($foreign, $skillid, 3);

        // A genuine in-tenant :manage holder (the default grant is revoked).
        $this->setUser($this->tenant_admin('/1', true));
        $top = array_column(skills_manager::top_courses(), 'id');
        $this->assertContains($own, $top);
        $this->assertNotContains($foreign, $top,
            'course_mapping.php\'s initial list must not name other tenants\' courses.');
        $this->assertTrue(skills_manager::can_view_course($own));
        $this->assertFalse(skills_manager::can_view_course($foreign));
        $this->assertNotNull(skills_manager::get_course_summary($own));
        $this->assertNull(skills_manager::get_course_summary($foreign),
            'course_mapping.php?courseid=<a /177 course> must not show its name.');
        $this->assertCount(1, skills_manager::list_course_skills($own));
        $this->assertSame([], skills_manager::list_course_skills($foreign),
            'Another tenant\'s course mappings must not be listed by id.');
        $this->assertSame([], external\list_course_skills::execute($foreign)['rows']);
        $this->assertCount(1, external\list_course_skills::execute($own)['rows']);

        $this->setUser($this->tenant_admin('', true));
        $this->assertSame([], skills_manager::top_courses(), 'No tenant: no courses.');
        $this->assertNull(skills_manager::get_course_summary($own));
        $this->assertSame([], skills_manager::list_course_skills($own));

        $this->setAdminUser();
        $this->assertContains($foreign, array_column(skills_manager::top_courses(), 'id'));
        $this->assertNotNull(skills_manager::get_course_summary($foreign));
        $this->assertCount(1, skills_manager::list_course_skills($foreign));
    }
}
