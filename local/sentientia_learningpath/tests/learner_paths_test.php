<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-032 (code fix 7): the learner "My learning paths" page data.
 *
 * The rules under test are the ones the owner decided: a learner sees their own rows only, inside their own
 * tenant (ADR-031), in active paths only (completed history on an archived path is admin-only), and the page
 * is off until its flag is on.
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @covers     \local_sentientia_learningpath\learner_paths
 * @group local_sentientia_learningpath
 * @group tenant_isolation
 */
final class learner_paths_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(?string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    private function path(?string $openpath, string $name, array $more = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) ($more + [
            'name' => $name, 'description' => '<p>About ' . $name . '</p>', 'descriptionformat' => FORMAT_HTML,
            'costcenterid' => 0, 'open_path' => $openpath, 'status' => 1, 'visible' => 1,
            'timecreated' => 1700000000, 'timemodified' => 1700000000,
        ]));
    }

    private function enrol(int $pathid, int $userid, int $status = 0, int $created = 1700000100, ?int $completed = null): void {
        global $DB;
        $DB->insert_record('local_sentientia_learningpath_users', (object) ['pathid' => $pathid, 'userid' => $userid,
            'status' => $status, 'timecreated' => $created, 'timemodified' => $created, 'timecompleted' => $completed]);
    }

    private function put_course(int $pathid, int $courseid, int $sortorder, int $mandatory = 1): void {
        global $DB;
        $DB->insert_record('local_sentientia_learningpath_courses', (object) ['pathid' => $pathid,
            'courseid' => $courseid, 'sortorder' => $sortorder, 'mandatory' => $mandatory, 'timecreated' => 1700000000,
            'timemodified' => 1700000000]);
    }

    private function complete(int $userid, int $courseid): void {
        global $DB;
        $DB->insert_record('course_completions', (object) ['userid' => $userid, 'course' => $courseid,
            'timeenrolled' => 0, 'timestarted' => 0, 'timecompleted' => 1700000500, 'reaggregate' => 0]);
    }

    /**
     * @param array[] $cards
     * @return string[] Card names.
     */
    private function names(array $cards): array {
        return array_map(static fn(array $c): string => $c['name'], $cards);
    }

    public function test_the_flag_is_off_by_default_and_registered(): void {
        // A site admin resolves the customer-wide value (tenant 0), so the override below is the one read.
        $this->setAdminUser();
        $this->assertFalse(learner_paths::enabled());
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        $this->assertArrayHasKey(learner_paths::FLAG, $registry);
        $this->assertFalse($registry[learner_paths::FLAG]['default']);

        \local_sentientia_platform\feature_flags::set(learner_paths::FLAG, 0, true);
        $this->assertTrue(learner_paths::enabled());
    }

    public function test_a_learner_sees_only_their_own_active_paths_in_their_own_tenant(): void {
        $mine = $this->user_at('/1/5');
        $colleague = $this->user_at('/1/5');

        $visible = $this->path('/1', 'Visible to me');
        $deeper = $this->path('/1/5', 'In my department');
        $othertenant = $this->path('/77', 'Other tenant');
        $pathless = $this->path(null, 'No tenant');
        $archived = $this->path('/1', 'Archived', ['status' => 0, 'visible' => 0]);
        $hidden = $this->path('/1', 'Hidden', ['visible' => 0]);
        $notmine = $this->path('/1', 'Only my colleague');
        $tenbrother = $this->path('/10', 'A tenant whose id starts with mine');

        foreach ([$visible, $deeper, $othertenant, $pathless, $tenbrother] as $id) {
            $this->enrol($id, (int) $mine->id);
        }
        // Completed history on archived or hidden paths exists and is admin-only.
        $this->enrol($archived, (int) $mine->id, path_manager::ENROL_COMPLETED, 1700000100, 1700000900);
        $this->enrol($hidden, (int) $mine->id, path_manager::ENROL_COMPLETED, 1700000100, 1700000900);
        $this->enrol($notmine, (int) $colleague->id);

        $this->setUser($mine);
        $this->assertEqualsCanonicalizing(['Visible to me', 'In my department'], $this->names(learner_paths::cards((int) $mine->id)));
        $this->setUser($colleague);
        $this->assertSame(['Only my colleague'], $this->names(learner_paths::cards((int) $colleague->id)));
    }

    public function test_a_learner_with_no_tenant_sees_nothing(): void {
        $loner = $this->user_at(null);
        $path = $this->path('/1', 'Some path');
        $this->enrol($path, (int) $loner->id);
        $this->setUser($loner);
        $this->assertSame([], learner_paths::cards((int) $loner->id));
    }

    public function test_progress_status_and_dates(): void {
        $user = $this->user_at('/1');
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $c3 = $this->getDataGenerator()->create_course();

        // Two required courses and an optional one; one required course is done.
        $halfway = $this->path('/1', 'Halfway', ['startdate' => 1700000000, 'enddate' => 1800000000]);
        $this->put_course($halfway, (int) $c1->id, 0);
        $this->put_course($halfway, (int) $c2->id, 1);
        $this->put_course($halfway, (int) $c3->id, 2, 0);
        $this->enrol($halfway, (int) $user->id);
        $this->complete((int) $user->id, (int) $c1->id);

        // Marked Completed (an imported learner whose course completions are not in this site): 100 %.
        $done = $this->path('/1', 'Done');
        $this->put_course($done, (int) $c2->id, 0);
        $this->enrol($done, (int) $user->id, path_manager::ENROL_COMPLETED, 1700000100, 1700000900);

        // Enrolled, nothing done, and no enrolment date (BizLMS stored 0): no 1970 on the card.
        $fresh = $this->path('/1', 'Fresh');
        $this->put_course($fresh, (int) $c3->id, 0);
        $this->enrol($fresh, (int) $user->id, path_manager::ENROL_NEW, 0);

        $this->setUser($user);
        $cards = [];
        foreach (learner_paths::cards((int) $user->id) as $card) {
            $cards[$card['name']] = $card;
        }
        $this->assertCount(3, $cards);

        $this->assertTrue($cards['Halfway']['is_inprogress']);
        $this->assertSame(50, $cards['Halfway']['progress'], 'one of two required courses; the optional one does not count');
        $this->assertSame([3, 1], [$cards['Halfway']['course_count'], $cards['Halfway']['courses_done']]);
        $this->assertTrue($cards['Halfway']['has_opens_on']);
        $this->assertTrue($cards['Halfway']['has_closes_on']);

        $this->assertTrue($cards['Done']['is_completed']);
        $this->assertSame(100, $cards['Done']['progress']);
        $this->assertTrue($cards['Done']['has_completed_on']);

        $this->assertTrue($cards['Fresh']['is_notstarted']);
        $this->assertSame(0, $cards['Fresh']['progress']);
        $this->assertFalse($cards['Fresh']['has_enrolled_on']);
        $this->assertFalse($cards['Fresh']['has_completed_on']);
        $this->assertFalse($cards['Fresh']['has_opens_on']);

        // Completed learners sort after the others.
        $this->assertSame('Done', array_keys($cards)[2] ?? null, 'sorted: not completed first, then by name');
    }

    public function test_page_data_counts(): void {
        $user = $this->user_at('/1');
        $c1 = $this->getDataGenerator()->create_course();
        $started = $this->path('/1', 'A started');
        $this->put_course($started, (int) $c1->id, 0);
        $this->enrol($started, (int) $user->id);
        $this->complete((int) $user->id, (int) $c1->id);
        $this->enrol($this->path('/1', 'B new'), (int) $user->id);
        $this->enrol($this->path('/1', 'C done'), (int) $user->id, path_manager::ENROL_COMPLETED, 1700000100, 1700000900);

        $this->setUser($user);
        $data = learner_paths::page_data();
        $this->assertTrue($data['has_paths']);
        $this->assertSame([3, 1, 1, 1], [$data['total'], $data['inprogress'], $data['completed'], $data['notstarted']]);
    }

    public function test_the_page_shows_no_other_learners_data(): void {
        $me = $this->user_at('/1');
        $other = $this->user_at('/1');
        $path = $this->path('/1', 'Shared path');
        $this->enrol($path, (int) $me->id);
        $this->enrol($path, (int) $other->id, path_manager::ENROL_COMPLETED, 1700000100, 1700000900);
        $this->setUser($me);
        $cards = learner_paths::cards((int) $me->id);
        $this->assertCount(1, $cards);
        $this->assertFalse($cards[0]['is_completed'], 'the colleague\'s completion is not mine');
        $this->assertStringNotContainsString($other->lastname, json_encode($cards));
    }
}
