<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * The readers the BizLMS skills import added (ADR-032, mapping doc section 14, code fixes 4, 5 and 7): the words for
 * a level's source, what a learner already holds, the skills a learner said they are interested in, and the
 * courses recommended for them. Interests and the recommendations that use them sit behind the existing
 * skills-first recommendations flag, default OFF; the held-skills list has its own flag.
 *
 * The tenant rules are the ones the gap engine already keeps (ADR-031): the learner's own tenant plus legacy
 * courses with no path, the viewer's scope too when somebody else is looking, nothing without a tenant.
 *
 * @package    local_sentientia_skills
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_skills\skills_manager
 * @group      local_sentientia_skills
 * @group      bizlms_import
 * @group      tenant_isolation
 */
final class bizlms_readers_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    // Helpers.

    private function seed_skill(string $name): int {
        global $DB;
        $catid = (int) $DB->insert_record('local_sentientia_skill_cats', (object) [
            'name' => 'Readers ' . $name, 'description' => '', 'icon' => 'fa-cogs', 'color' => '#0066A7',
            'sort_order' => 1, 'timecreated' => time(),
        ]);
        return (int) $DB->insert_record('local_sentientia_skills', (object) [
            'categoryid' => $catid, 'name' => $name, 'description' => '', 'max_level' => 5,
            'sort_order' => 1, 'timecreated' => time(),
        ]);
    }

    private function user_at(string $path, string $designation = ''): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $DB->set_field('user', 'open_designation', $designation, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function course_at(?string $path, bool $visible = true): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => $visible ? 1 : 0]);
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return (int) $course->id;
    }

    private function teach(int $courseid, int $skillid, int $level): void {
        global $DB;
        $DB->insert_record('local_sentientia_course_skills', (object) [
            'courseid' => $courseid, 'skillid' => $skillid, 'teaches_level' => $level, 'timecreated' => time(),
        ]);
    }

    private function interested(int $userid, int $skillid): void {
        global $DB;
        $DB->insert_record('local_sentientia_skill_interest', (object) [
            'userid' => $userid, 'skillid' => $skillid, 'timecreated' => 10, 'timemodified' => 20,
        ]);
    }

    private function complete(int $userid, int $courseid): void {
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'userid' => $userid, 'course' => $courseid, 'timeenrolled' => 0, 'timestarted' => 0,
            'timecompleted' => time(), 'reaggregate' => 0,
        ]);
    }

    private function flag(string $key, bool $on): void {
        feature_flags::set($key, 0, $on, (int) get_admin()->id, 'phpunit');
    }

    /** @return int[] sorted */
    private function ids(array $courses): array {
        $ids = array_map('intval', array_column($courses, 'courseid'));
        sort($ids);
        return $ids;
    }

    // Tests.

    public function test_the_source_of_a_level_is_words_not_a_code(): void {
        $this->assertSame(get_string('source_import', 'local_sentientia_skills'), skills_manager::source_label('import'));
        $this->assertSame(get_string('source_self', 'local_sentientia_skills'), skills_manager::source_label(' SELF '));
        $this->assertSame(get_string('source_course', 'local_sentientia_skills'), skills_manager::source_label('course'));
        $this->assertSame(get_string('source_manual', 'local_sentientia_skills'), skills_manager::source_label('manual'));
        $this->assertSame(get_string('source_assessment', 'local_sentientia_skills'), skills_manager::source_label('assessment'));
        $this->assertSame('hr-sync', skills_manager::source_label('hr-sync'), 'a code nobody wrote words for is shown as it is');
        $this->assertSame('', skills_manager::source_label(''));
    }

    public function test_held_skills_are_what_the_learner_has_above_zero(): void {
        global $DB;
        $held = $this->seed_skill('Held');
        $none = $this->seed_skill('Not held');
        $learner = $this->user_at('/1');
        foreach ([[$held, 3, 'import'], [$none, 0, 'self']] as [$skillid, $level, $source]) {
            $DB->insert_record('local_sentientia_user_skills', (object) [
                'userid' => $learner->id, 'skillid' => $skillid, 'current_level' => $level, 'source' => $source,
                'timecreated' => 100, 'timemodified' => 200,
            ]);
        }
        $rows = skills_manager::get_held_skills((int) $learner->id);
        $this->assertCount(1, $rows);
        $this->assertSame($held, $rows[0]['skillid']);
        $this->assertSame('Held', $rows[0]['skill_name']);
        $this->assertSame(3, $rows[0]['current_level']);
        $this->assertSame('Intermediate', $rows[0]['current_label']);
        $this->assertSame(get_string('source_import', 'local_sentientia_skills'), $rows[0]['source_label']);
        $this->assertNotSame('', $rows[0]['updated_on']);
        $this->assertSame([], skills_manager::get_held_skills((int) $this->user_at('/1')->id));
    }

    public function test_the_two_flags_are_registered_and_default_off(): void {
        $this->setUser($this->user_at('/1'));
        $this->assertFalse(skills_manager::flag_enabled(skills_manager::FLAG_RECS));
        $this->assertFalse(skills_manager::flag_enabled(skills_manager::FLAG_HELD));
        $this->flag(skills_manager::FLAG_HELD, true);
        $this->assertTrue(skills_manager::flag_enabled(skills_manager::FLAG_HELD));
        $this->assertFalse(skills_manager::flag_enabled(skills_manager::FLAG_RECS), 'the flags are independent');
    }

    public function test_interest_skills_are_listed_by_name(): void {
        $b = $this->seed_skill('Bravo');
        $a = $this->seed_skill('Alpha');
        $learner = $this->user_at('/1');
        $this->interested((int) $learner->id, $b);
        $this->interested((int) $learner->id, $a);
        $rows = skills_manager::get_interest_skills((int) $learner->id);
        $this->assertSame(['Alpha', 'Bravo'], array_column($rows, 'name'));
        $this->assertSame([$a, $b], array_column($rows, 'skillid'));
        $this->assertStringContainsString('/local/sentientia_skills/view.php', $rows[0]['viewurl']);
        $this->assertSame([], skills_manager::get_interest_skills((int) $this->user_at('/1')->id));
    }

    public function test_interest_courses_are_scoped_unfinished_unenrolled_and_visible(): void {
        $skill = $this->seed_skill('Interest');
        $other = $this->seed_skill('Second interest');
        $own = $this->course_at('/1/2');
        $legacy = $this->course_at('');
        $legacynull = $this->course_at(null);
        $foreign = $this->course_at('/177');
        $done = $this->course_at('/1/2');
        $enrolled = $this->course_at('/1/2');
        $hidden = $this->course_at('/1/2', false);
        foreach ([$own, $legacynull, $foreign, $done, $enrolled, $hidden] as $courseid) {
            $this->teach($courseid, $skill, 2);
        }
        $this->teach($legacy, $skill, 1);
        // A course that teaches both interests is one recommendation, not two.
        $this->teach($own, $other, 3);

        $learner = $this->user_at('/1/3');
        $this->interested((int) $learner->id, $skill);
        $this->interested((int) $learner->id, $other);
        $this->complete((int) $learner->id, $done);
        $this->getDataGenerator()->enrol_user($learner->id, $enrolled);

        $this->setUser($learner);
        $rows = skills_manager::get_interest_courses((int) $learner->id, 10);
        $expected = [$own, $legacy, $legacynull];
        sort($expected);
        $this->assertSame($expected, $this->ids($rows),
            'own tenant and legacy courses; not another tenant, a completed, an enrolled or a hidden course');
        $this->assertSame($legacy, $rows[0]['courseid'], 'the easiest level comes first');
        $this->assertSame(1, $rows[0]['teaches_level']);
        $this->assertSame('interest', $rows[0]['reason']);
        $this->assertSame(0, $rows[0]['gap_level']);
        $this->assertCount(count($expected), $rows, 'each course once');

        // The limit, and courses already recommended elsewhere.
        $this->assertCount(1, skills_manager::get_interest_courses((int) $learner->id, 1));
        $left = $this->ids(skills_manager::get_interest_courses((int) $learner->id, 10, [$own]));
        $this->assertNotContains($own, $left);
        $this->assertSame([], skills_manager::get_interest_courses((int) $learner->id, 0));

        // Somebody else looking: the learner's tenant decides, and the viewer's scope limits it further.
        $this->setAdminUser();
        $this->assertSame($expected, $this->ids(skills_manager::get_interest_courses((int) $learner->id, 10)));
        $this->setUser($this->tenant_admin('/177'));
        $legacyonly = [$legacy, $legacynull];
        sort($legacyonly);
        $this->assertSame($legacyonly, $this->ids(skills_manager::get_interest_courses((int) $learner->id, 10)),
            'a /177 viewer must not learn the names of the /1 learner\'s /1 courses');

        // No tenant, no recommendations.
        $nowhere = $this->user_at('');
        $this->interested((int) $nowhere->id, $skill);
        $this->setUser($nowhere);
        $this->assertSame([], skills_manager::get_interest_courses((int) $nowhere->id, 10));
    }

    public function test_recommendations_add_interests_only_behind_the_flag(): void {
        global $DB;
        $gapskill = $this->seed_skill('Gap skill');
        $interest = $this->seed_skill('Interest skill');
        $gapcourse = $this->course_at('/1');
        $interestcourse = $this->course_at('/1');
        $this->teach($gapcourse, $gapskill, 3);
        $this->teach($interestcourse, $interest, 1);

        $learner = $this->user_at('/1', 'Teller');
        $DB->insert_record('local_sentientia_role_skills', (object) [
            'designation' => 'Teller', 'skillid' => $gapskill, 'required_level' => 3, 'timecreated' => time(),
        ]);
        $this->interested((int) $learner->id, $interest);
        $this->setUser($learner);

        // Flag OFF: exactly the gap engine, and the gap rows now say what level they teach.
        $off = skills_manager::get_recommended_courses((int) $learner->id, 5);
        $this->assertSame([$gapcourse], array_map('intval', array_column($off, 'courseid')));
        $this->assertSame('gap', $off[0]['reason']);
        $this->assertSame(3, $off[0]['teaches_level']);
        $this->assertSame($off, skills_manager::get_gap_courses((int) $learner->id, 5));

        // Flag ON: the gap course first, then the interest course.
        $this->flag(skills_manager::FLAG_RECS, true);
        $on = skills_manager::get_recommended_courses((int) $learner->id, 5);
        $this->assertSame([$gapcourse, $interestcourse], array_map('intval', array_column($on, 'courseid')));
        $this->assertSame(['gap', 'interest'], array_column($on, 'reason'));

        // The limit holds, and a full gap list leaves no room for interests.
        $this->assertCount(1, skills_manager::get_recommended_courses((int) $learner->id, 1));
    }

    public function test_the_levels_table_has_the_columns_the_skill_page_reads(): void {
        global $DB;
        // view.php read level_index and name, which this table does not have (code fix 3); the columns are these.
        $columns = array_keys($DB->get_columns('local_sentientia_skill_levels'));
        $this->assertContains('level', $columns);
        $this->assertContains('label', $columns);
        $this->assertNotContains('level_index', $columns);
        $this->assertNotContains('name', $columns);
    }
}
