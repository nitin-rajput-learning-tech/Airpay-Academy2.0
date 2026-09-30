<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * The evidence view (ADR-032): what it shows, and who may see it.
 *
 * The view lists, for one reset, the evidence the reset deleted. It is a compliance reader over other
 * people's learning records, so it obeys the history page's tenant scope exactly (ADR-031 decision 8): a reader
 * sees a reset only when they are cross-tenant or the learner is inside their tenant, and a row they may not see
 * is indistinguishable from one that does not exist. It is behind a default-OFF flag.
 *
 * @package    local_sentientia_recompletion
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_recompletion\evidence_report
 *
 * @group local_sentientia_recompletion
 * @group bizlms_import
 * @group tenant_isolation
 */
final class evidence_view_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Course id. */
    private $courseid;
    /** @var \stdClass A learner in tenant /1. */
    private $mine;
    /** @var \stdClass A learner in tenant /77. */
    private $theirs;
    /** @var int History row of the /1 learner. */
    private $minehistory;
    /** @var int History row of the /77 learner. */
    private $theirhistory;
    /** @var int A history row whose learner was erased (userid 0). */
    private $redacted;
    /** @var \stdClass A quiz of 10 marks over 20 raw marks. */
    private $quiz;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        feature_flags::invalidate_caches();

        $g = $this->getDataGenerator();
        $this->mine = $this->user_at('/1/2');
        $this->theirs = $this->user_at('/77/3');
        $course = $g->create_course(['enablecompletion' => 1, 'fullname' => 'Annual AML']);
        $this->courseid = (int) $course->id;
        $this->setAdminUser();
        $this->quiz = $g->create_module('quiz', ['course' => $course->id, 'grade' => 10]);
        $this->quiz = $this->set_quiz_marks((int) $this->quiz->id);

        $this->minehistory = $this->history((int) $this->mine->id, 1000);
        $this->theirhistory = $this->history((int) $this->theirs->id, 2000);
        $this->redacted = $this->history(0, 3000);
        foreach ([[$this->mine, $this->minehistory], [$this->theirs, $this->theirhistory]] as [$user, $history]) {
            $this->archive((int) $user->id, $history, 'quiz_attempt', ['attempt' => '1', 'userid' => (string) $user->id],
                ['instanceid' => $this->quiz->id, 'state' => 'finished', 'grade' => 15]);
            $this->archive((int) $user->id, $history, 'scorm_track', ['value' => 'completed'],
                ['instanceid' => 4, 'itemkey' => 'cmi.core.lesson_status', 'state' => 'completed']);
        }
        $this->archive(0, $this->redacted, 'lti_grade', [], ['instanceid' => 3, 'grade' => 50]);
    }

    /**
     * @param int $quizid
     * @return \stdClass The quiz row with sumgrades 20 and grade 10.
     */
    private function set_quiz_marks(int $quizid): \stdClass {
        global $DB;
        $DB->set_field('quiz', 'sumgrades', 20, ['id' => $quizid]);
        $DB->set_field('quiz', 'grade', 10, ['id' => $quizid]);
        return $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
    }

    private function user_at(?string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A reader who holds :view (the manager archetype) and whose tenant is given by a path.
     *
     * @param string|null $path
     * @return \stdClass
     */
    private function tenant_admin(?string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function history(int $userid, int $time): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_recompletion_history', (object) [
            'ruleid' => 0, 'userid' => $userid, 'courseid' => $this->courseid, 'reason' => 'cron',
            'reset_by_userid' => null, 'previous_timecompleted' => 900, 'reset_grades' => 1, 'reset_attempts' => 1,
            'dryrun' => 0, 'timecreated' => $time, 'source' => 'legacy', 'time_inferred' => 1,
        ]);
    }

    private function archive(int $userid, int $historyid, string $type, array $payload, array $fields): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_recompletion_archive', (object) ([
            'historyid' => $historyid, 'userid' => $userid, 'courseid' => $this->courseid, 'itemtype' => $type,
            'payload' => json_encode($payload), 'timeevent' => 800, 'timecreated' => 1000,
        ] + $fields));
    }

    public function test_the_view_is_off_until_its_flag_is_switched_on(): void {
        $this->setAdminUser();
        $this->assertFalse(evidence_report::enabled());
        $this->assertFalse(feature_flags::load_registry()[evidence_report::FLAG]['default'], 'registered default OFF');
        feature_flags::set(evidence_report::FLAG, 0, true);
        $this->assertTrue(evidence_report::enabled());
    }

    public function test_a_tenant_admin_sees_the_evidence_of_their_own_tenant_and_nobody_elses(): void {
        $this->setUser($this->tenant_admin('/1'));
        $this->assertNotNull(evidence_report::visible_history($this->minehistory));
        $this->assertNull(evidence_report::visible_history($this->theirhistory),
            'another tenant\'s reset is reported exactly like one that does not exist');
        $this->assertNull(evidence_report::visible_history($this->redacted), 'an erased learner has no tenant: site admins only');
        $this->assertNull(evidence_report::visible_history(987654));
        $this->assertNotNull(evidence_report::visible_pair((int) $this->mine->id, $this->courseid));
        $this->assertNull(evidence_report::visible_pair((int) $this->theirs->id, $this->courseid));
        $this->assertNull(evidence_report::visible_pair(0, $this->courseid));

        $this->setUser($this->tenant_admin('/77'));
        $this->assertNull(evidence_report::visible_history($this->minehistory));
        $this->assertNotNull(evidence_report::visible_history($this->theirhistory));
    }

    public function test_a_caller_with_no_tenant_sees_nothing_and_the_site_admin_sees_everything(): void {
        $this->setUser($this->tenant_admin('garbage'));
        foreach ([$this->minehistory, $this->theirhistory, $this->redacted] as $id) {
            $this->assertNull(evidence_report::visible_history($id));
        }
        $this->assertNull(evidence_report::visible_pair((int) $this->mine->id, $this->courseid));

        $this->setAdminUser();
        foreach ([$this->minehistory, $this->theirhistory, $this->redacted] as $id) {
            $this->assertNotNull(evidence_report::visible_history($id));
        }
        $this->assertNotNull(evidence_report::visible_pair((int) $this->theirs->id, $this->courseid));
    }

    public function test_the_header_marks_an_estimated_time_a_legacy_reset_and_who_made_it(): void {
        $this->setAdminUser();
        $header = evidence_report::header(evidence_report::visible_history($this->minehistory));
        $this->assertStringStartsWith('~ ', $header['reset_at'], 'an estimated time is shown with a tilde');
        $this->assertTrue($header['inferred']);
        $this->assertTrue($header['legacy']);
        $this->assertSame(get_string('evidence_scheduled', 'local_sentientia_recompletion'), $header['reset_by']);
        $this->assertSame('Annual AML', $header['course']);
        $this->assertTrue($header['grades_reset']);

        global $DB;
        $DB->set_field('local_sentientia_recompletion_history', 'reset_by_userid', $this->mine->id, ['id' => $this->minehistory]);
        $DB->set_field('local_sentientia_recompletion_history', 'time_inferred', 0, ['id' => $this->minehistory]);
        $header = evidence_report::header(evidence_report::visible_history($this->minehistory));
        $this->assertSame(get_string('evidence_self', 'local_sentientia_recompletion'), $header['reset_by']);
        $this->assertStringStartsNotWith('~', $header['reset_at']);

        // A tenant admin is not told the name of somebody who may belong to another tenant.
        $DB->set_field('local_sentientia_recompletion_history', 'reset_by_userid', $this->theirs->id, ['id' => $this->minehistory]);
        $this->setUser($this->tenant_admin('/1'));
        $header = evidence_report::header(evidence_report::visible_history($this->minehistory));
        $this->assertSame(get_string('evidence_admin', 'local_sentientia_recompletion'), $header['reset_by']);
    }

    public function test_the_sections_list_each_kind_of_evidence_with_the_quiz_marks_scaled(): void {
        $this->setAdminUser();
        $sections = evidence_report::sections_for_history($this->minehistory);
        $titles = array_column($sections, 'title');
        $this->assertSame([get_string('evidence_type_quiz_attempt', 'local_sentientia_recompletion'),
            get_string('evidence_type_scorm_track', 'local_sentientia_recompletion')], $titles);

        $quiz = $sections[0];
        $this->assertSame(1, $quiz['count']);
        $this->assertSame('', $quiz['more']);
        $this->assertCount(count($quiz['headers']), $quiz['rows'][0]['cells'], 'a cell per column');
        $cells = $quiz['rows'][0]['cells'];
        $this->assertSame(format_string($this->quiz->name), $cells[0]);
        $this->assertSame('1', $cells[1], 'the attempt number from the payload');
        $this->assertSame('finished', $cells[2]);
        $this->assertSame('7.50 / 10.00', $cells[3], '15 raw marks of 20, on a quiz of 10');

        $this->assertSame([], evidence_report::sections_for_history(987654));
    }

    public function test_marks_are_scaled_by_the_quiz_and_fall_back_to_the_raw_value(): void {
        $quiz = (object) ['grade' => '10.00000', 'sumgrades' => '20.00000'];
        $this->assertSame('7.50 / 10.00', evidence_report::scaled_marks($quiz, '15'));
        $this->assertSame('15.00', evidence_report::scaled_marks(null, '15'), 'the quiz is gone');
        $this->assertSame('15.00', evidence_report::scaled_marks((object) ['grade' => '10', 'sumgrades' => '0'], '15'),
            'a quiz with no marks cannot scale');
        $this->assertSame('-', evidence_report::scaled_marks($quiz, null));
    }

    public function test_a_long_section_is_capped_and_says_how_many_rows_are_not_shown(): void {
        global $DB;
        $rows = [];
        for ($i = 0; $i < evidence_report::SECTION_LIMIT + 3; $i++) {
            $rows[] = (object) ['historyid' => $this->minehistory, 'userid' => $this->mine->id, 'courseid' => $this->courseid,
                'itemtype' => 'lti_grade', 'instanceid' => 1, 'grade' => $i, 'timeevent' => 800 + $i,
                'payload' => '{}', 'timecreated' => 1000];
        }
        $DB->insert_records('local_sentientia_recompletion_archive', $rows);
        $this->setAdminUser();
        $lti = array_values(array_filter(evidence_report::sections_for_history($this->minehistory),
            static fn(array $s): bool => $s['title'] === get_string('evidence_type_lti_grade', 'local_sentientia_recompletion')));
        $this->assertCount(1, $lti);
        $this->assertSame(evidence_report::SECTION_LIMIT + 3, $lti[0]['count'], 'the count is of the whole section');
        $this->assertCount(evidence_report::SECTION_LIMIT, $lti[0]['rows']);
        $this->assertSame(get_string('evidence_more', 'local_sentientia_recompletion', 3), $lti[0]['more']);
    }

    public function test_evidence_not_tied_to_any_reset_is_reachable_by_learner_and_course(): void {
        $this->archive((int) $this->mine->id, 0, 'quiz_grade', [], ['instanceid' => $this->quiz->id, 'grade' => 4]);
        $this->setAdminUser();
        $sections = evidence_report::sections_for_pair((int) $this->mine->id, $this->courseid);
        $this->assertSame([get_string('evidence_type_quiz_grade', 'local_sentientia_recompletion')], array_column($sections, 'title'));
        $this->assertSame([], evidence_report::sections_for_pair((int) $this->theirs->id, $this->courseid));
    }
}
