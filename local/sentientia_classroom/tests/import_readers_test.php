<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * The reader and engine changes that ship with the BizLMS classroom import (ADR-032, mapping doc section 15,
 * "Code fixes"): the draft and on-hold states, capacity 0, the auto-promote guard, the imported-history readers
 * behind their default-OFF flag, and the dashboards' scoped count.
 *
 * The importer itself is tested in bizlms_import_test.php. Here the rows are inserted directly, so the tests
 * need no legacy table.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_sentientia_classroom\session_manager
 * @covers \local_sentientia_classroom\waitlist_manager
 * @covers \local_sentientia_classroom\external\list_classroom_users
 * @group local_sentientia_classroom
 * @group tenant_isolation
 */
final class import_readers_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const FLAG = 'sentientia.classroom.import_history';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        feature_flags::invalidate_caches();
    }

    private function classroom(array $values = []): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_classroom', (object) ($values + [
            'name' => 'Classroom', 'description' => '', 'costcenterid' => 0, 'open_path' => '/1', 'capacity' => 10,
            'status' => session_manager::STATUS_ACTIVE, 'visible' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]));
    }

    private function roster(int $classroomid, int $userid, array $values = []): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_classroom_users', (object) ($values + [
            'classroomid' => $classroomid, 'userid' => $userid, 'timecreated' => $now, 'timemodified' => $now,
        ]));
    }

    private function wait(int $classroomid, int $userid, int $position): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_classroom_waitlist', (object) [
            'classroomid' => $classroomid, 'userid' => $userid, 'position' => $position, 'status' => 'waiting',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    // ─── Draft and on-hold ────────────────────────────────────────────────────────────────────────────

    public function test_every_status_has_a_name_and_a_badge_and_unknown_ones_are_unknown(): void {
        $this->assertSame('Active', session_manager::status_label(session_manager::STATUS_ACTIVE));
        $this->assertSame('Completed', session_manager::status_label(session_manager::STATUS_COMPLETED));
        $this->assertSame('Cancelled', session_manager::status_label(session_manager::STATUS_CANCELLED));
        $this->assertSame('Draft', session_manager::status_label(session_manager::STATUS_DRAFT));
        $this->assertSame('On hold', session_manager::status_label(session_manager::STATUS_ON_HOLD));
        $this->assertSame('Unknown', session_manager::status_label(3), '3 and 4 are raw BizLMS values, never statuses');
        $this->assertSame('Unknown', session_manager::status_label(4));
        $this->assertSame([5, 6], array_values(array_intersect([5, 6], session_manager::statuses())));
        $this->assertSame('badge-secondary', session_manager::status_badge(99));
        $this->assertSame('badge-warning', session_manager::status_badge(session_manager::STATUS_ON_HOLD));
    }

    public function test_change_status_accepts_draft_and_on_hold_and_refuses_the_raw_bizlms_values(): void {
        global $DB;
        $id = $this->classroom();
        $this->assertSame(5, session_manager::change_status($id, session_manager::STATUS_DRAFT));
        $this->assertSame(6, session_manager::change_status($id, session_manager::STATUS_ON_HOLD));
        $this->assertSame(6, (int) $DB->get_field('local_sentientia_classroom', 'status', ['id' => $id]));
        foreach ([3, 4, 7, -1] as $bad) {
            try {
                session_manager::change_status($id, $bad);
                $this->fail("status {$bad} was accepted");
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidstatus', $e->errorcode);
            }
        }
    }

    public function test_the_status_strings_exist_in_english_and_hindi(): void {
        $strings = \get_string_manager();
        foreach (['status_draft', 'status_onhold', 'status_unknown', 'roster_completion', 'roster_completed',
                'roster_pending', 'roster_completed_on', 'roster_hours', 'history_training_dates', 'history_trainers',
                'history_courses', 'myclassrooms', 'error_protected_history'] as $key) {
            $this->assertTrue($strings->string_exists($key, 'local_sentientia_classroom'), $key);
            $this->assertNotSame('', get_string($key, 'local_sentientia_classroom'));
        }
    }

    // ─── Capacity 0 ───────────────────────────────────────────────────────────────────────────────────

    public function test_capacity_zero_means_unlimited(): void {
        global $DB;
        $id = session_manager::create((object) ['name' => 'No limit', 'capacity' => 0]);
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_classroom', 'capacity', ['id' => $id]));

        // With one person on the roster and a waiting list behind, an unlimited classroom has a free place.
        $gen = $this->getDataGenerator();
        $on = $gen->create_user();
        $waiting = $gen->create_user();
        $this->roster($id, (int) $on->id);
        $this->wait($id, (int) $waiting->id, 1);
        $sink = $this->redirectMessages();
        $this->assertSame((int) $waiting->id, waitlist_manager::auto_promote($id));
        $sink->close();
    }

    // ─── Auto-promote guard ───────────────────────────────────────────────────────────────────────────

    /**
     * @dataProvider closed_or_unstarted_provider
     * @param int $status
     */
    public function test_auto_promote_does_nothing_unless_the_classroom_is_active(int $status): void {
        global $DB;
        $id = $this->classroom(['status' => $status, 'capacity' => 10]);
        $user = $this->getDataGenerator()->create_user();
        $this->wait($id, (int) $user->id, 1);

        $this->assertSame(0, waitlist_manager::auto_promote($id));
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_users', ['classroomid' => $id]));
        $this->assertSame('waiting', $DB->get_field('local_sentientia_classroom_waitlist', 'status', ['classroomid' => $id]));
    }

    /**
     * @return array
     */
    public static function closed_or_unstarted_provider(): array {
        return [
            'draft' => [session_manager::STATUS_DRAFT],
            'on hold' => [session_manager::STATUS_ON_HOLD],
            'cancelled' => [session_manager::STATUS_CANCELLED],
            'completed' => [session_manager::STATUS_COMPLETED],
        ];
    }

    public function test_auto_promote_still_promotes_on_an_active_classroom(): void {
        global $DB;
        $id = $this->classroom(['capacity' => 3]);
        $user = $this->getDataGenerator()->create_user();
        $this->wait($id, (int) $user->id, 1);
        $sink = $this->redirectMessages();
        $this->assertSame((int) $user->id, waitlist_manager::auto_promote($id));
        $sink->close();
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users', ['classroomid' => $id, 'userid' => $user->id]));
    }

    public function test_a_deleted_user_holds_no_place_and_is_never_promoted(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $id = $this->classroom(['capacity' => 1]);
        $gone = $gen->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        $this->roster($id, (int) $gone->id);          // a deleted learner on the roster (imported history)
        $deletedhead = $gen->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $deletedhead->id]);
        $next = $gen->create_user();
        $this->wait($id, (int) $deletedhead->id, 1);
        $this->wait($id, (int) $next->id, 2);

        $sink = $this->redirectMessages();
        // Capacity 1 is held only by a deleted user, so there is a free place, and the head who is deleted is skipped.
        $this->assertSame((int) $next->id, waitlist_manager::auto_promote($id));
        $sink->close();
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_users',
            ['classroomid' => $id, 'userid' => $deletedhead->id]));
    }

    // ─── Roster and waiting-list readers ──────────────────────────────────────────────────────────────

    public function test_roster_and_waiting_list_readers_leave_out_deleted_users(): void {
        global $DB;
        $this->ensure_bizlms_schema();
        $gen = $this->getDataGenerator();
        $id = $this->classroom();
        $live = $gen->create_user();
        $dead = $gen->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $dead->id]);
        $this->roster($id, (int) $live->id);
        $this->roster($id, (int) $dead->id);
        $this->wait($id, (int) $live->id, 1);
        $this->wait($id, (int) $dead->id, 2);

        $this->assertSame(1, session_manager::count_enrolled($id));
        $this->assertSame(1, session_manager::count_enrolled_filtered($id));
        $this->assertCount(1, session_manager::get_enrolled_users($id));
        $this->assertCount(1, waitlist_manager::list_waiting($id));
    }

    // ─── The reader flag ──────────────────────────────────────────────────────────────────────────────

    public function test_the_history_flag_is_registered_and_off_by_default(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(self::FLAG, $registry);
        $this->assertFalse($registry[self::FLAG]['default']);
        $this->assertNotSame('', trim($registry[self::FLAG]['description']));
        $this->setAdminUser();
        $this->assertFalse(session_manager::history_enabled());
        feature_flags::set(self::FLAG, 0, true);
        $this->assertTrue(session_manager::history_enabled());
    }

    public function test_the_roster_service_shows_completion_only_when_the_flag_is_on(): void {
        global $DB;
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $id = $this->classroom();
        $this->roster($id, (int) $user->id, ['completion_status' => 1, 'timecompleted' => 1700000300, 'hours' => 8]);

        $off = external\list_classroom_users::execute($id);
        $this->assertSame('', $off['rows'][0]['completion']);
        $this->assertSame('', $off['rows'][0]['completed_at']);
        $this->assertSame('', $off['rows'][0]['hours']);

        feature_flags::set(self::FLAG, 0, true);
        $on = external\list_classroom_users::execute($id);
        $this->assertSame('Completed', $on['rows'][0]['completion']);
        $this->assertNotSame('', $on['rows'][0]['completed_at']);
        $this->assertSame('8', $on['rows'][0]['hours']);
    }

    public function test_the_overview_template_shows_the_history_block_only_when_asked(): void {
        global $OUTPUT, $PAGE;
        $PAGE->set_url('/local/sentientia_classroom/view.php', ['id' => 1]);
        $base = [
            'classroomid' => 1, 'name' => 'C', 'description' => '', 'has_description' => false, 'location' => '',
            'has_location' => false, 'capacity' => 10, 'trainer_name' => '', 'has_trainer' => false,
            'status_label' => 'Active', 'status_css' => 'badge-success', 'session_count' => 0, 'enrolled_count' => 0,
            'created_human' => '-', 'modified_human' => '-', 'back_url' => '/x', 'tab_overview_active' => true,
            'tab_sessions_active' => false, 'tab_users_active' => false, 'tab_overview_url' => '/o',
            'tab_sessions_url' => '/s', 'tab_users_url' => '/u', 'can_update' => false, 'can_attend' => false,
            'sessions_columns_json' => '[]', 'users_columns_json' => '[]', 'extra_args_json' => '{}',
        ];
        $off = $OUTPUT->render_from_template('local_sentientia_classroom/view', $base);
        $this->assertStringNotContainsString('classroom-history', $off);

        $on = $OUTPUT->render_from_template('local_sentientia_classroom/view', $base + [
            'show_history' => true, 'training_dates' => '03 Feb 2025 – 04 Feb 2025', 'has_training_dates' => true,
            'completed_human' => '04 Feb 2025', 'has_completed' => true,
            'trainers' => [['name' => 'Trainer One'], ['name' => 'Trainer Two']], 'has_trainers' => true,
            'courses' => [['name' => 'Course Z', 'url' => '/course/view.php?id=2']], 'has_courses' => true,
            'logo_url' => '/pluginfile.php/1/local_sentientia_classroom/classroomlogo/1/logo.png', 'has_logo' => true,
        ]);
        $this->assertStringContainsString('classroom-history', $on);
        $this->assertStringContainsString('Trainer Two', $on);
        $this->assertStringContainsString('Course Z', $on);
        $this->assertStringContainsString('classroomlogo/1/logo.png', $on);
        $this->assertStringContainsString('03 Feb 2025', $on);
    }

    public function test_the_my_classrooms_page_needs_the_flag(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        // my.php throws error_history_off unless this is true (it is the only gate; the data is the learner's own).
        $this->assertFalse(session_manager::history_enabled());
        $this->assertTrue(get_string_manager()->string_exists('error_history_off', 'local_sentientia_classroom'));
    }

    public function test_the_my_classrooms_data_lists_the_learners_own_classrooms(): void {
        global $DB, $OUTPUT, $PAGE;
        $learner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->classroom(['name' => 'My workshop', 'status' => session_manager::STATUS_COMPLETED,
            'trainingstart' => 1700000000, 'trainingend' => 1700086400]);
        $draft = $this->classroom(['name' => 'Draft workshop', 'status' => session_manager::STATUS_DRAFT]);
        $hidden = $this->classroom(['name' => 'Hidden workshop', 'visible' => 0]);
        $theirs = $this->classroom(['name' => 'Their workshop']);
        $this->roster($mine, (int) $learner->id, ['completion_status' => 1, 'timecompleted' => 1700000300, 'hours' => 8]);
        $this->roster($draft, (int) $learner->id);
        $this->roster($hidden, (int) $learner->id);
        $this->roster($theirs, (int) $other->id);
        $past = (int) $DB->insert_record('local_sentientia_classroom_sessions', (object) [
            'classroomid' => $mine, 'title' => 'Day 1', 'sessiondate' => 1700000000, 'starttime' => 1700000000,
            'endtime' => 1700003600, 'timecreated' => 1700000000, 'timemodified' => 1700000000]);
        $unmarked = (int) $DB->insert_record('local_sentientia_classroom_sessions', (object) [
            'classroomid' => $mine, 'title' => '', 'sessiondate' => 1700010000, 'starttime' => 1700010000,
            'endtime' => 1700013600, 'timecreated' => 1700000000, 'timemodified' => 1700000000]);
        $DB->insert_record('local_sentientia_classroom_attendance', (object) [
            'sessionid' => $past, 'userid' => $learner->id, 'status' => 1, 'timecreated' => 1700000000,
            'timemodified' => 1700000000]);

        $rows = session_manager::get_user_classrooms((int) $learner->id);
        $this->assertSame(['My workshop'], array_map(fn($r) => $r->name, $rows),
            'a draft and a hidden classroom are not shown to the people on them, and nobody else\'s is');
        $sessions = session_manager::get_user_sessions((int) $learner->id, [$mine]);
        $this->assertSame(1, (int) $sessions[$mine][0]->attendance);
        $this->assertNull($sessions[$mine][1]->attendance);

        $data = my_classrooms::context_for((int) $learner->id, 1800000000);
        $this->assertTrue($data['has_classrooms']);
        $item = $data['classrooms'][0];
        $this->assertSame('My workshop', $item['name']);
        $this->assertSame('Completed', $item['status_label']);
        $this->assertTrue($item['completed']);
        $this->assertSame(8, $item['hours']);
        $this->assertTrue($item['has_training_dates']);
        $this->assertSame('Present', $item['sessions'][0]['attendance']);
        $this->assertSame('Not marked', $item['sessions'][1]['attendance'], 'a past session nobody marked');
        $this->assertNotSame('', $item['sessions'][1]['title'], 'an untitled session has a name');
        $this->assertSame($unmarked, (int) $sessions[$mine][1]->id);

        // A session that has not happened yet shows no attendance at all.
        $future = my_classrooms::context_for((int) $learner->id, 1600000000);
        $this->assertFalse($future['classrooms'][0]['sessions'][1]['has_attendance']);

        // The template renders it, and renders nothing of anybody else's.
        $PAGE->set_url('/local/sentientia_classroom/my.php');
        $html = $OUTPUT->render_from_template('local_sentientia_classroom/my', $data);
        $this->assertStringContainsString('My workshop', $html);
        $this->assertStringContainsString('Day 1', $html);
        $this->assertStringContainsString('Present', $html);
        $this->assertStringNotContainsString('Their workshop', $html);
        $this->assertStringNotContainsString('Draft workshop', $html);

        // A learner on no roster gets the empty message.
        $none = my_classrooms::context_for((int) $this->getDataGenerator()->create_user()->id);
        $this->assertFalse($none['has_classrooms']);
    }

    public function test_the_my_classrooms_page_escapes_a_name_with_an_ampersand_once(): void {
        global $DB, $OUTPUT, $PAGE;
        $learner = $this->getDataGenerator()->create_user();
        $id = $this->classroom(['name' => 'Tom & Jerry', 'location' => 'Hall A & B']);
        $this->roster($id, (int) $learner->id);
        $DB->insert_record('local_sentientia_classroom_sessions', (object) [
            'classroomid' => $id, 'title' => 'Q&A', 'location' => 'Room 1 & 2', 'sessiondate' => 1700000000,
            'starttime' => 1700000000, 'endtime' => 1700003600, 'timecreated' => 1700000000,
            'timemodified' => 1700000000]);

        // The data is plain text: the template, not the page, escapes it.
        $data = my_classrooms::context_for((int) $learner->id, 1800000000);
        $item = $data['classrooms'][0];
        $this->assertSame('Tom & Jerry', $item['name']);
        $this->assertSame('Hall A & B', $item['location']);
        $this->assertSame('Q&A', $item['sessions'][0]['title']);
        $this->assertSame('Room 1 & 2', $item['sessions'][0]['location']);

        $PAGE->set_url('/local/sentientia_classroom/my.php');
        $html = $OUTPUT->render_from_template('local_sentientia_classroom/my', $data);
        $this->assertStringContainsString('Tom &amp; Jerry', $html);
        $this->assertStringContainsString('Sessions of Tom &amp; Jerry', $html, 'the caption argument too');
        $this->assertStringContainsString('Hall A &amp; B', $html);
        $this->assertStringContainsString('Q&amp;A', $html);
        $this->assertStringContainsString('Room 1 &amp; 2', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html, 'nothing is escaped twice');
    }

    public function test_trainers_and_linked_courses_readers(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $id = $this->classroom();
        $t1 = $gen->create_user();
        $t2 = $gen->create_user();
        $gone = $gen->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        $DB->set_field('local_sentientia_classroom', 'trainerid', $t1->id, ['id' => $id]);
        $now = time();
        foreach ([$t1, $t2, $gone] as $trainer) {
            $DB->insert_record('local_sentientia_classroom_trainers', (object) [
                'classroomid' => $id, 'trainerid' => $trainer->id, 'timecreated' => $now, 'timemodified' => $now]);
        }
        $course = $gen->create_course(['fullname' => 'Linked course']);
        $DB->insert_record('local_sentientia_classroom_courses', (object) [
            'classroomid' => $id, 'courseid' => $course->id, 'timecreated' => $now, 'timemodified' => $now]);

        $trainers = session_manager::get_trainers($id);
        $this->assertSame([(int) $t1->id, (int) $t2->id], array_map(fn($u) => (int) $u->id, $trainers),
            'the primary trainer once, the others after, the deleted one not at all');
        $courses = session_manager::get_linked_courses($id);
        $this->assertSame(['Linked course'], array_map(fn($c) => $c->fullname, $courses));
    }

    // ─── Dashboards ───────────────────────────────────────────────────────────────────────────────────

    public function test_the_dashboard_count_is_scoped_to_the_callers_tenant(): void {
        global $DB;
        $this->ensure_bizlms_schema();
        $this->classroom(['open_path' => '/1']);
        $this->classroom(['open_path' => '/1/5']);
        $this->classroom(['open_path' => '/77']);
        $this->classroom(['open_path' => null]);

        $this->setAdminUser();
        $this->assertSame(4, session_manager::count_classrooms_for_caller(), 'a cross-tenant admin sees all');

        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $user->id]);
        $this->setUser($DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST));
        $this->assertSame(2, session_manager::count_classrooms_for_caller(), 'the tenant /1 and below, not /77, not pathless');

        $nopath = $this->getDataGenerator()->create_user();
        $this->setUser($DB->get_record('user', ['id' => $nopath->id], '*', MUST_EXIST));
        $this->assertSame(0, session_manager::count_classrooms_for_caller(), 'no tenant, no count (ADR-031)');
    }

    public function test_the_logo_callback_serves_nothing_to_people_who_may_not_see_the_classroom(): void {
        global $CFG;
        // Moodle does not reliably load a local plugin's lib.php inside PHPUnit.
        require_once($CFG->dirroot . '/local/sentientia_classroom/lib.php');
        $this->setUser($this->getDataGenerator()->create_user());
        $context = \context_system::instance();
        $id = $this->classroom();
        // The flag is off: nothing is served, whatever the file area.
        $this->assertFalse(\local_sentientia_classroom_pluginfile(null, null, $context, 'classroomlogo',
            [$id, 'logo.png'], false));
        $this->assertFalse(\local_sentientia_classroom_pluginfile(null, null, $context, 'somethingelse',
            [$id, 'logo.png'], false));

        // The flag on, a learner who is not on the roster and holds no :view still gets nothing.
        $this->setAdminUser();
        feature_flags::set(self::FLAG, 0, true);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(\local_sentientia_classroom_pluginfile(null, null, $context, 'classroomlogo',
            [$id, 'logo.png'], false));
    }
}
