<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * G-05 — Filtered analysis + Kirkpatrick aggregation + CSV row tests.
 *
 * Locks in:
 * - build_response_filter generates correct WHERE + params per filter combo
 * - count_responses_filtered returns the right count
 * - get_responses_filtered respects date range + context filters
 * - get_response_stats_filtered narrows stats to the filter set
 * - get_kirkpatrick_summary buckets responses by parent eval level
 * - get_kirkpatrick_summary calculates avg rating + NPS score correctly
 * - response_to_csv_row produces the expected column layout
 * - response_to_csv_row anonymises when eval.anonymous = 1
 * - csv_header_row matches the row layout
 * - the trigger queue's pending shell rows (timesubmitted 0) are never counted, listed or exported
 *
 * @package    local_sentientia_evaluation
 * @category   test
 */
final class analysis_test extends \advanced_testcase {

    private function seed_eval(string $name, int $kirkpatrick = 1, int $anonymous = 0): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_evaluation')) {
            $this->markTestSkipped('local_sentientia_evaluation table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_evaluation', (object) [
            'name'              => $name,
            'description'       => '',
            'kirkpatrick_level' => $kirkpatrick,
            'trigger_event'     => 'manual',
            'days_after'        => 0,
            'costcenterid'      => 0,
            'open_path'         => '/1',
            'status'            => evaluation_manager::STATUS_ACTIVE,
            'anonymous'         => $anonymous,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);
    }

    private function seed_question(int $evalid, string $type = 'rating', int $sortorder = 0): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_evaluation_questions', (object) [
            'evaluationid' => $evalid,
            'questiontype' => $type,
            'questiontext' => 'Q ' . $type,
            'options'      => null,
            'required'     => 1,
            'sortorder'    => $sortorder,
            'timecreated'  => $now,
        ]);
    }

    private function seed_response(int $evalid, int $userid, array $answers,
                                    int $when, ?int $courseid = null, ?int $programid = null,
                                    ?int $classroomid = null): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid'  => $evalid,
            'userid'        => $userid,
            'courseid'      => $courseid,
            'programid'     => $programid,
            'classroomid'   => $classroomid,
            'response_data' => json_encode($answers),
            'timesubmitted' => $when,
        ]);
    }

    // ─── build_response_filter ──────────────────────────────────────────

    public function test_build_filter_evaluationid_only(): void {
        [$where, $params] = evaluation_manager::build_response_filter(['evaluationid' => 5]);
        $this->assertStringContainsString('r.evaluationid = :evid', $where);
        $this->assertSame(5, $params['evid']);
    }

    public function test_build_filter_date_range(): void {
        [$where, $params] = evaluation_manager::build_response_filter([
            'date_from' => 1000,
            'date_to'   => 2000,
        ]);
        $this->assertStringContainsString('r.timesubmitted >= :dfrom', $where);
        $this->assertStringContainsString('r.timesubmitted <= :dto', $where);
        $this->assertSame(1000, $params['dfrom']);
        $this->assertSame(2000, $params['dto']);
    }

    public function test_build_filter_context_ids(): void {
        [$where, $params] = evaluation_manager::build_response_filter([
            'courseid'    => 7,
            'programid'   => 8,
            'classroomid' => 9,
        ]);
        $this->assertStringContainsString('r.courseid = :cid',    $where);
        $this->assertStringContainsString('r.programid = :pid',   $where);
        $this->assertStringContainsString('r.classroomid = :crid',$where);
        $this->assertSame(7, $params['cid']);
        $this->assertSame(8, $params['pid']);
        $this->assertSame(9, $params['crid']);
    }

    public function test_build_filter_empty_only_leaves_out_trigger_shells(): void {
        [$where, $params] = evaluation_manager::build_response_filter([]);
        // The one condition that is always there: a response row with timesubmitted 0 is the trigger queue's
        // pending shell (an invitation), not a response.
        $this->assertSame('r.timesubmitted > 0', $where);
        $this->assertSame([], $params);
    }

    // ─── count + get filtered responses ─────────────────────────────────

    public function test_count_responses_filtered_by_eval(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $e1 = $this->seed_eval('E1');
        $e2 = $this->seed_eval('E2');
        $this->seed_response($e1, 0, [], time());
        $this->seed_response($e1, 0, [], time());
        $this->seed_response($e2, 0, [], time());

        $this->assertSame(2, evaluation_manager::count_responses_filtered(['evaluationid' => $e1]));
        $this->assertSame(1, evaluation_manager::count_responses_filtered(['evaluationid' => $e2]));
    }

    public function test_get_responses_filtered_by_date_range(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid = $this->seed_eval('E');
        $this->seed_response($eid, 0, [], strtotime('2026-01-15'));   // outside
        $this->seed_response($eid, 0, [], strtotime('2026-02-15'));   // inside
        $this->seed_response($eid, 0, [], strtotime('2026-03-15'));   // outside

        $rows = evaluation_manager::get_responses_filtered([
            'evaluationid' => $eid,
            'date_from'    => strtotime('2026-02-01'),
            'date_to'      => strtotime('2026-02-28 23:59:59'),
        ]);
        $this->assertCount(1, $rows);
    }

    public function test_get_responses_filtered_by_courseid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid = $this->seed_eval('E');
        $this->seed_response($eid, 0, [], time(), 100);
        $this->seed_response($eid, 0, [], time(), 200);
        $this->seed_response($eid, 0, [], time(), 100);

        $rows = evaluation_manager::get_responses_filtered([
            'evaluationid' => $eid,
            'courseid'     => 100,
        ]);
        $this->assertCount(2, $rows);
    }

    /**
     * The pending shell evaluation_engine writes when a trigger fires (timesubmitted 0, response_data '{}') is an
     * invitation, not a response. It used to be counted, listed and exported (as an empty row dated 1970).
     */
    public function test_trigger_shells_are_not_counted_listed_or_exported(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid = $this->seed_eval('Shells', 1);
        $qid = $this->seed_question($eid, 'rating');
        $invited = (int) $this->getDataGenerator()->create_user()->id;
        $answered = (int) $this->getDataGenerator()->create_user()->id;
        $this->seed_response($eid, $invited, [], 0);                 // the shell
        $this->seed_response($eid, $answered, [$qid => 4], time());   // a real response

        $this->assertSame(1, evaluation_manager::count_responses($eid));
        $this->assertSame(1, evaluation_manager::count_responses());
        $this->assertSame(1, evaluation_manager::count_responses_scoped());
        $this->assertSame(1, evaluation_manager::count_responses_filtered(['evaluationid' => $eid]));

        $rows = evaluation_manager::get_responses_filtered(['evaluationid' => $eid]);
        $this->assertCount(1, $rows);
        $this->assertSame($answered, (int) reset($rows)->userid);

        // The CSV is built from exactly those rows: no empty row dated 1970.
        $questions = evaluation_manager::get_questions($eid);
        $form = evaluation_manager::get($eid);
        $csv = [];
        foreach ($rows as $row) {
            $csv[] = evaluation_manager::response_to_csv_row($row, $questions, $form);
        }
        $this->assertCount(1, $csv);
        $this->assertStringNotContainsString('1970', $csv[0][0]);
        $this->assertSame('4', $csv[0][6]);

        // The statistics and the Kirkpatrick roll-up agree.
        $this->assertSame(1, evaluation_manager::get_response_stats($eid)[$qid]['count']);
        $filtered = evaluation_manager::get_response_stats_filtered($eid, []);
        $this->assertSame(1, $filtered['response_count']);
        $this->assertSame(1, evaluation_manager::get_kirkpatrick_summary()[1]['response_count']);
    }

    // ─── get_response_stats_filtered ────────────────────────────────────

    public function test_response_stats_filtered_excludes_outsiders(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid = $this->seed_eval('E');
        $qid = $this->seed_question($eid, 'rating');

        // Three responses — two with courseid=100 (rate 5 + 4), one outside (rate 1).
        $this->seed_response($eid, 0, [$qid => 5], time(), 100);
        $this->seed_response($eid, 0, [$qid => 4], time(), 100);
        $this->seed_response($eid, 0, [$qid => 1], time(), 200);

        $stats = evaluation_manager::get_response_stats_filtered($eid, ['courseid' => 100]);
        $this->assertSame(2, $stats['response_count']);
        // avg rating from filtered subset = (5+4)/2 = 4.5
        $this->assertEqualsWithDelta(4.5, $stats['questions'][$qid]['avg'], 0.01);
    }

    // ─── get_kirkpatrick_summary ────────────────────────────────────────

    public function test_kirkpatrick_summary_buckets_by_level(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $e1 = $this->seed_eval('Reaction',  1);   // L1
        $e2 = $this->seed_eval('Learning',  2);   // L2
        $e3 = $this->seed_eval('Behaviour', 3);   // L3

        $this->seed_response($e1, 0, [], time());
        $this->seed_response($e1, 0, [], time());
        $this->seed_response($e2, 0, [], time());

        $sum = evaluation_manager::get_kirkpatrick_summary();

        $this->assertSame(1, $sum[1]['evaluation_count']);
        $this->assertSame(2, $sum[1]['response_count']);
        $this->assertSame(1, $sum[2]['evaluation_count']);
        $this->assertSame(1, $sum[2]['response_count']);
        $this->assertSame(1, $sum[3]['evaluation_count']);
        $this->assertSame(0, $sum[3]['response_count']);
        $this->assertSame(0, $sum[4]['evaluation_count']);
    }

    public function test_kirkpatrick_summary_avg_rating(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid = $this->seed_eval('R', 1);
        $qid = $this->seed_question($eid, 'rating');
        $this->seed_response($eid, 0, [$qid => 5], time());
        $this->seed_response($eid, 0, [$qid => 3], time());
        $this->seed_response($eid, 0, [$qid => 4], time());

        $sum = evaluation_manager::get_kirkpatrick_summary();
        $this->assertSame(3, $sum[1]['rating_count']);
        $this->assertEqualsWithDelta(4.0, $sum[1]['avg_rating'], 0.01);
    }

    public function test_kirkpatrick_summary_nps_score(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid = $this->seed_eval('Reaction', 1);
        $qid = $this->seed_question($eid, 'nps');
        // 4 promoters (9-10), 1 passive (7-8), 5 detractors (0-6).
        // NPS = 40% promoters - 50% detractors = -10.
        foreach ([10, 9, 9, 9, 8, 6, 5, 3, 2, 0] as $score) {
            $this->seed_response($eid, 0, [$qid => $score], time());
        }
        $sum = evaluation_manager::get_kirkpatrick_summary();
        $this->assertSame(10, $sum[1]['nps_count']);
        $this->assertSame(4,  $sum[1]['nps_promoters']);
        $this->assertSame(5,  $sum[1]['nps_detractors']);
        // round() returns float, so use loose equality + delta.
        $this->assertEqualsWithDelta(-10, $sum[1]['nps_score'], 0.01);
    }

    public function test_kirkpatrick_summary_filter_by_date(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid = $this->seed_eval('R', 1);
        $this->seed_response($eid, 0, [], strtotime('2026-01-15'));
        $this->seed_response($eid, 0, [], strtotime('2026-02-15'));
        $this->seed_response($eid, 0, [], strtotime('2026-03-15'));

        $sum = evaluation_manager::get_kirkpatrick_summary([
            'date_from' => strtotime('2026-02-01'),
            'date_to'   => strtotime('2026-02-28 23:59:59'),
        ]);
        $this->assertSame(1, $sum[1]['response_count']);
    }

    // ─── response_to_csv_row + csv_header_row ───────────────────────────

    public function test_csv_header_row_has_context_columns(): void {
        $eid = $this->seed_eval('E', 1);
        $qid1 = $this->seed_question($eid, 'rating', 0);
        $qid2 = $this->seed_question($eid, 'text',   1);
        $this->resetAfterTest();
        $this->setAdminUser();

        $questions = evaluation_manager::get_questions($eid);
        $header = evaluation_manager::csv_header_row($questions);

        $this->assertSame('Submitted',    $header[0]);
        $this->assertSame('Respondent',   $header[1]);
        $this->assertSame('Email',        $header[2]);
        $this->assertSame('Course ID',    $header[3]);
        $this->assertSame('Program ID',   $header[4]);
        $this->assertSame('Classroom ID', $header[5]);
        $this->assertStringContainsString('Q1:', $header[6]);
        $this->assertStringContainsString('Q2:', $header[7]);
    }

    public function test_response_to_csv_row_includes_answers_in_question_order(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid  = $this->seed_eval('E', 1);
        $qid1 = $this->seed_question($eid, 'rating', 0);
        $qid2 = $this->seed_question($eid, 'text',   1);
        $u    = $this->getDataGenerator()->create_user(['firstname' => 'Test', 'lastname' => 'User']);
        $this->seed_response($eid, (int) $u->id, [$qid1 => 5, $qid2 => 'Great session'], time(), 42);

        global $DB;
        $eval = $DB->get_record('local_sentientia_evaluation', ['id' => $eid], '*', MUST_EXIST);
        $resp = $DB->get_record('local_sentientia_evaluation_responses', ['evaluationid' => $eid], '*', MUST_EXIST);
        $questions = evaluation_manager::get_questions($eid);

        $row = evaluation_manager::response_to_csv_row($resp, $questions, $eval);

        $this->assertSame('Test User',     $row[1]);
        $this->assertSame($u->email,       $row[2]);
        $this->assertSame('42',            $row[3]);   // courseid
        $this->assertSame('',              $row[4]);   // programid
        $this->assertSame('',              $row[5]);   // classroomid
        $this->assertSame('5',             $row[6]);   // Q1 rating
        $this->assertSame('Great session', $row[7]);   // Q2 text
    }

    public function test_response_to_csv_row_anonymises_when_anonymous_eval(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $eid  = $this->seed_eval('Anonymous', 1, 1);   // anonymous=1
        $qid1 = $this->seed_question($eid, 'rating', 0);
        $u    = $this->getDataGenerator()->create_user(['firstname' => 'Test', 'lastname' => 'User']);
        // Anonymous evals store userid=0 by convention (per submit_response).
        $this->seed_response($eid, 0, [$qid1 => 4], time());

        global $DB;
        $eval = $DB->get_record('local_sentientia_evaluation', ['id' => $eid], '*', MUST_EXIST);
        $resp = $DB->get_record('local_sentientia_evaluation_responses', ['evaluationid' => $eid], '*', MUST_EXIST);
        $questions = evaluation_manager::get_questions($eid);

        $row = evaluation_manager::response_to_csv_row($resp, $questions, $eval);

        $this->assertSame('(anonymous)', $row[1]);
        $this->assertSame('',            $row[2]);
    }

    // ─── Subject column (supervisor evaluations, ADR-032) ───────────────

    /**
     * Put a subject on a response, the way the BizLMS import does for a supervisor evaluation.
     */
    private function set_subject(int $responseid, ?int $subjectid): void {
        global $DB;
        $DB->set_field('local_sentientia_evaluation_responses', 'subject_userid', $subjectid, ['id' => $responseid]);
    }

    /**
     * The CSV export gets a Subject column only for a form that has a person to name, after Email, and the row
     * width follows the header. A native form keeps its layout; a protected form never names the subject.
     */
    public function test_csv_subject_column_only_for_named_supervisor_responses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $supervisor = $gen->create_user(['firstname' => 'Sue', 'lastname' => 'Supervisor']);
        $subject = $gen->create_user(['firstname' => 'Sam', 'lastname' => 'Subject']);
        // Plain English like every other header of the export: a CSV's headers do not change with the language of
        // whoever exports it (the page's own column heading is the lang string responses_col_subject).
        $label = 'Subject';

        // (a) A native named form: no Subject header, and the row keeps its width.
        $native = $this->seed_eval('Native', 1);
        $nq = $this->seed_question($native, 'rating');
        $this->seed_response($native, (int) $supervisor->id, [$nq => 5], time());
        $nativeform = evaluation_manager::get($native);
        $nativequestions = evaluation_manager::get_questions($native);
        $nativerow = $DB->get_record('local_sentientia_evaluation_responses', ['evaluationid' => $native], '*', MUST_EXIST);
        $this->assertFalse(evaluation_manager::shows_subject($nativeform));
        $plainheader = evaluation_manager::csv_header_row($nativequestions);
        $this->assertNotContains($label, $plainheader);
        $this->assertCount(count($plainheader),
            evaluation_manager::response_to_csv_row($nativerow, $nativequestions, $nativeform));
        $this->assertSame('Email', $plainheader[2]);
        $this->assertSame('Course ID', $plainheader[3]);

        // (b) A supervisor form: Subject right after Email, holding the person the response is about.
        $sp = $this->seed_eval('Supervisor', 1);
        $sq = $this->seed_question($sp, 'rating');
        $responseid = $this->seed_response($sp, (int) $supervisor->id, [$sq => 4], time());
        $this->set_subject($responseid, (int) $subject->id);
        // A second response on the same form with no subject (a native answer): an empty cell, same width.
        $plainid = $this->seed_response($sp, (int) $supervisor->id, [$sq => 3], time() - 10);
        $spform = evaluation_manager::get($sp);
        $spquestions = evaluation_manager::get_questions($sp);
        $this->assertTrue(evaluation_manager::shows_subject($spform));
        $header = evaluation_manager::csv_header_row($spquestions, true);
        $this->assertSame('Email', $header[2]);
        $this->assertSame($label, $header[3]);
        $this->assertSame('Course ID', $header[4]);
        $this->assertCount(count($plainheader) + 1, $header);
        $row = evaluation_manager::response_to_csv_row(
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $responseid], '*', MUST_EXIST),
            $spquestions, $spform, null, true);
        $this->assertCount(count($header), $row);
        $this->assertSame(fullname($supervisor), $row[1]);
        $this->assertSame(fullname($subject), $row[3]);
        $this->assertSame('4', $row[7], 'the answer is still the last column, after Course/Program/Classroom');
        $norow = evaluation_manager::response_to_csv_row(
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $plainid], '*', MUST_EXIST),
            $spquestions, $spform, null, true);
        $this->assertCount(count($header), $norow);
        $this->assertSame('', $norow[3]);

        // (c) The same form made anonymous, or one that holds a userid-0 response, never shows a subject.
        $DB->set_field('local_sentientia_evaluation', 'anonymous', 1, ['id' => $sp]);
        $anonform = evaluation_manager::get($sp);
        $this->assertFalse(evaluation_manager::shows_subject($anonform));
        $anonrow = evaluation_manager::response_to_csv_row(
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $responseid], '*', MUST_EXIST),
            $spquestions, $anonform, null, true);
        $this->assertNotContains(fullname($subject), $anonrow);
        $this->assertSame('', $anonrow[3]);

        $sticky = $this->seed_eval('Once anonymous', 1);
        $stickyq = $this->seed_question($sticky, 'rating');
        $this->seed_response($sticky, 0, [$stickyq => 2], time());
        $namedid = $this->seed_response($sticky, (int) $supervisor->id, [$stickyq => 5], time());
        $this->set_subject($namedid, (int) $subject->id);
        $stickyform = evaluation_manager::get($sticky);
        $this->assertTrue(evaluation_manager::identity_protected($stickyform));
        $this->assertFalse(evaluation_manager::shows_subject($stickyform));
        $stickyrow = evaluation_manager::response_to_csv_row(
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $namedid], '*', MUST_EXIST),
            evaluation_manager::get_questions($sticky), $stickyform, null, true);
        $this->assertNotContains(fullname($subject), $stickyrow);

        // (d) A subject whose account is deleted, or gone altogether, is '(deleted user)', not a name.
        $deleted = $gen->create_user(['firstname' => 'Dee', 'lastname' => 'Deleted']);
        $gone = $this->seed_eval('Subjects who left', 1);
        $gq = $this->seed_question($gone, 'rating');
        $softid = $this->seed_response($gone, (int) $supervisor->id, [$gq => 1], time());
        $this->set_subject($softid, (int) $deleted->id);
        $goneid = $this->seed_response($gone, (int) $supervisor->id, [$gq => 2], time());
        $this->set_subject($goneid, 987654321);
        delete_user($deleted);
        $goneform = evaluation_manager::get($gone);
        $this->assertTrue(evaluation_manager::shows_subject($goneform));
        foreach ([$softid, $goneid] as $id) {
            $leftrow = evaluation_manager::response_to_csv_row(
                $DB->get_record('local_sentientia_evaluation_responses', ['id' => $id], '*', MUST_EXIST),
                evaluation_manager::get_questions($gone), $goneform, null, true);
            $this->assertSame('(deleted user)', $leftrow[3]);
        }
    }

    // ─── number and tick-all-that-apply statistics (responses.php) ──────

    /**
     * A form with a bounded number question (1 to 5), an unbounded one, a tick-all-that-apply question and a
     * rating nobody answers. Returns the question ids by name.
     *
     * @return array{eid: int, bounded: int, free: int, multi: int, rating: int}
     */
    private function seed_number_and_multi_form(): array {
        $eid = $this->seed_eval('Numbers and ticks', 1);
        $bounded = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'numeric', 'questiontext' => 'Score the venue',
            'numeric_min' => 1, 'numeric_max' => 5, 'required' => 0,
        ]);
        $free = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'numeric', 'questiontext' => 'How many people?', 'required' => 0,
        ]);
        $multi = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'multichoice_multi', 'questiontext' => 'Which topics helped?',
            'options' => "A\nB\nC", 'required' => 0,
        ]);
        $rating = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'rating', 'questiontext' => 'Overall', 'required' => 0,
        ]);
        return ['eid' => $eid, 'bounded' => $bounded, 'free' => $free, 'multi' => $multi, 'rating' => $rating];
    }

    /**
     * response_data is a JSON object keyed by question id (int keys), as submit_response() and the BizLMS import
     * both write it.
     */
    private function put_answers(int $eid, array $answers): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid' => $eid, 'userid' => 0, 'response_data' => json_encode((object) $answers),
            'timesubmitted' => time(),
        ]);
    }

    public function test_response_stats_buckets_for_numeric_and_multichoice_multi(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $f = $this->seed_number_and_multi_form();

        foreach ([2, 4, 5] as $score) {
            $this->put_answers($f['eid'], [$f['bounded'] => $score]);
        }
        // An imported BizLMS answer can carry decimals (7.25): they are kept, not truncated.
        $this->put_answers($f['eid'], [$f['free'] => 7.25]);
        $this->put_answers($f['eid'], [$f['multi'] => ['A', 'C']]);
        $this->put_answers($f['eid'], [$f['multi'] => ['C']]);

        $stats = evaluation_manager::get_response_stats($f['eid']);

        $bounded = $stats[$f['bounded']];
        $this->assertSame(3, $bounded['count']);
        $this->assertEqualsWithDelta(3.67, $bounded['avg'], 0.001);
        $this->assertEquals(2, $bounded['min_seen']);
        $this->assertEquals(5, $bounded['max_seen']);
        $this->assertSame([1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 1], $bounded['distribution']);
        $this->assertTrue($bounded['distribution_exact']);

        $free = $stats[$f['free']];
        $this->assertSame(1, $free['count']);
        $this->assertEquals(7.25, $free['sum']);
        $this->assertEquals(7.25, $free['avg']);
        $this->assertArrayNotHasKey('distribution', $free, 'no bounds, no bars');

        $multi = $stats[$f['multi']];
        $this->assertSame(2, $multi['count'], 'two people answered');
        $this->assertSame(3, $multi['total_picks'], 'and ticked three boxes between them');
        $this->assertSame(['A' => 1, 'B' => 0, 'C' => 2], $multi['distribution']);
        $this->assertEquals(1.5, $multi['avg_picks']);

        // One answer that is not a whole number (2.5 is inside 1..5 but is no bar) switches the bars off.
        $this->put_answers($f['eid'], [$f['bounded'] => 2.5]);
        $bounded = evaluation_manager::get_response_stats($f['eid'])[$f['bounded']];
        $this->assertSame(4, $bounded['count']);
        $this->assertFalse($bounded['distribution_exact']);
    }

    public function test_response_question_rows_render_numeric_and_multichoice_multi(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $f = $this->seed_number_and_multi_form();
        foreach ([2, 4, 5] as $score) {
            $this->put_answers($f['eid'], [$f['bounded'] => $score]);
        }
        $this->put_answers($f['eid'], [$f['multi'] => ['A', 'C']]);
        $this->put_answers($f['eid'], [$f['multi'] => ['C']]);

        $rows = evaluation_manager::response_question_rows(
            evaluation_manager::get_questions($f['eid']), evaluation_manager::get_response_stats($f['eid']));
        $this->assertCount(4, $rows);
        $this->assertSame([1, 2, 3, 4], array_column($rows, 'position'), 'numbered 1..n, not by question id');
        [$bounded, $free, $multi, $rating] = $rows;

        // Number question with a 1..5 range: the average, the extremes, the range and a bar per value.
        $this->assertTrue($bounded['is_numeric']);
        $this->assertFalse($bounded['is_multichoice_multi']);
        $this->assertSame(3, $bounded['response_count']);
        $this->assertSame('3.67', $bounded['avg']);
        $this->assertSame('2', $bounded['min_seen']);
        $this->assertSame('5', $bounded['max_seen']);
        $this->assertSame(1, $bounded['bound_min']);
        $this->assertSame(5, $bounded['bound_max']);
        $this->assertSame(get_string('responses_numeric_range', 'local_sentientia_evaluation',
            (object) ['min' => 1, 'max' => 5]), $bounded['range_text']);
        $this->assertTrue($bounded['has_distribution']);
        $this->assertSame(['1', '2', '3', '4', '5'], array_column($bounded['distribution'], 'label'));
        $this->assertSame([0, 1, 0, 1, 1], array_column($bounded['distribution'], 'count'));
        $this->assertSame([0, 33, 0, 33, 33], array_column($bounded['distribution'], 'pct'));

        // Tick-all-that-apply: shares are of respondents (C was ticked by both), so they can exceed 100% together.
        $this->assertTrue($multi['is_multichoice_multi']);
        $this->assertFalse($multi['is_multichoice']);
        $this->assertSame(2, $multi['respondents']);
        $this->assertSame(3, $multi['total_picks']);
        $this->assertSame('1.5', $multi['avg_picks']);
        $this->assertSame(['A', 'B', 'C'], array_column($multi['distribution'], 'option'));
        $this->assertSame([1, 0, 2], array_column($multi['distribution'], 'count'));
        $this->assertSame([50, 0, 100], array_column($multi['distribution'], 'pct'));
        $this->assertSame(get_string('responses_multi_summary', 'local_sentientia_evaluation',
            (object) ['picks' => 3, 'respondents' => 2, 'avg' => '1.5']), $multi['summary']);
        $this->assertNotSame('', $multi['share_note']);

        // A question nobody answered keeps its flag and shows no statistics.
        foreach ([$free, $rating] as $unanswered) {
            $this->assertSame(0, $unanswered['response_count']);
            $this->assertArrayNotHasKey('distribution', $unanswered);
            $this->assertArrayNotHasKey('avg', $unanswered);
        }
        $this->assertTrue($free['is_numeric']);
        $this->assertTrue($rating['is_rating']);
        $this->assertArrayNotHasKey('range_text', $free);

        // The same rows for a tick question nobody answered: no distribution.
        $question = evaluation_manager::get_question($f['multi']);
        $empty = evaluation_manager::response_question_rows([$question], [])[0];
        $this->assertTrue($empty['is_multichoice_multi']);
        $this->assertSame(0, $empty['response_count']);
        $this->assertArrayNotHasKey('distribution', $empty);
        $this->assertSame($question->id, $empty['id']);
    }

    // ─── one respondent's answers (response_detail.php) ─────────────────

    public function test_response_detail_rows_read_question_id_keys_and_list_options(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $eid = $this->seed_eval('Detail', 1);
        $rating = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'rating', 'questiontext' => 'Overall', 'required' => 0,
        ]);
        $choice = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'multichoice', 'questiontext' => 'Colour',
            'options' => "Red\nGreen", 'required' => 0,
        ]);
        $multi = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'multichoice_multi', 'questiontext' => 'Topics',
            'options' => "A\nB\nC", 'required' => 0,
        ]);
        $number = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'numeric', 'questiontext' => 'People', 'required' => 0,
        ]);

        // response_data is keyed by the bare question id; a choice question's options are a plain list.
        $first = $this->put_answers($eid, [$rating => 4, $choice => 'Red', $multi => ['A', 'C'], $number => 7.25]);
        $this->put_answers($eid, [$rating => 2, $choice => 'Red', $multi => ['C'], $number => 3]);
        $third = $this->put_answers($eid, [$choice => 'Green']);
        // The pending shell of an invited user is not a response: it counts for nothing here.
        $this->seed_response($eid, (int) $this->getDataGenerator()->create_user()->id, [], 0);

        $form = evaluation_manager::get($eid);
        $detail = evaluation_manager::response_detail_rows($form,
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $first], '*', MUST_EXIST));
        $this->assertSame(3, $detail['total_responses']);
        $this->assertCount(4, $detail['questions']);
        [$r, $c, $m, $n] = $detail['questions'];

        $this->assertTrue($r['has_my_answer']);
        $this->assertSame('4', $r['my_answer']);
        $this->assertSame(2, $r['response_count']);
        $this->assertEqualsWithDelta(3.0, $r['avg'], 0.001);
        $this->assertSame([1, 2, 3, 4, 5], array_column($r['histogram'], 'level'));
        $this->assertSame([0, 1, 0, 1, 0], array_column($r['histogram'], 'count'));
        $this->assertSame([false, false, false, true, false], array_column($r['histogram'], 'is_my_choice'));

        $this->assertSame('Red', $c['my_answer']);
        $this->assertSame(['Red', 'Green'], array_column($c['histogram'], 'label'), 'the options come from the list');
        $this->assertSame([2, 1], array_column($c['histogram'], 'count'));
        $this->assertEqualsWithDelta(66.7, $c['histogram'][0]['pct'], 0.05);
        $this->assertSame([true, false], array_column($c['histogram'], 'is_my_choice'));

        $this->assertTrue($m['has_my_answer']);
        $this->assertSame('A, C', $m['my_answer']);
        $this->assertSame(2, $m['response_count'], 'two people ticked something');
        $this->assertSame(['A', 'B', 'C'], array_column($m['histogram'], 'label'));
        $this->assertSame([1, 0, 2], array_column($m['histogram'], 'count'));
        $this->assertEqualsWithDelta(100.0, $m['histogram'][2]['pct'], 0.05);
        $this->assertSame([true, false, true], array_column($m['histogram'], 'is_my_choice'));

        $this->assertSame('7.25', $n['my_answer']);
        $this->assertTrue($n['has_avg']);
        $this->assertEqualsWithDelta(5.13, $n['avg'], 0.01);
        $this->assertStringContainsString('5.13', $n['avg_label']);

        // A response that skipped a question: no answer, and none of the options is marked.
        $partial = evaluation_manager::response_detail_rows($form,
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $third], '*', MUST_EXIST));
        $this->assertSame(3, $partial['total_responses']);
        [$r, $c, $m, $n] = $partial['questions'];
        $this->assertFalse($r['has_my_answer']);
        $this->assertSame('', $r['my_answer']);
        $this->assertSame([false, false, false, false, false], array_column($r['histogram'], 'is_my_choice'));
        $this->assertSame([false, true], array_column($c['histogram'], 'is_my_choice'));
        $this->assertFalse($m['has_my_answer']);
        $this->assertSame([false, false, false], array_column($m['histogram'], 'is_my_choice'));
        $this->assertFalse($n['has_my_answer']);
    }

    // ─── follow-ups of the review of the evaluation follow-ups (2026-10-07) ───

    /**
     * response_detail.php opens any response id. A pending shell (timesubmitted 0) is an invitation, not a response,
     * and is refused; a submitted response is not.
     */
    public function test_a_pending_shell_is_not_a_response_to_open(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $eid = $this->seed_eval('Shell guard', 1);
        $q = $this->seed_question($eid, 'rating');
        $real = $this->seed_response($eid, (int) $this->getDataGenerator()->create_user()->id, [$q => 4], time());
        $shell = $this->seed_response($eid, (int) $this->getDataGenerator()->create_user()->id, [], 0);

        evaluation_manager::require_submitted_response(
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $real], '*', MUST_EXIST));
        $this->addToAssertionCount(1);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidresponse', 'local_sentientia_evaluation'));
        evaluation_manager::require_submitted_response(
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $shell], '*', MUST_EXIST));
    }

    /**
     * Mustache reads the text "0" as false. A choice whose text is "0" used to vanish from the response detail page
     * (the row was inside {{#label}}) together with the respondent's pick on it; rows are keyed on is_option and
     * is_level now, and the name and options reach the page escaped exactly once.
     */
    public function test_a_choice_whose_text_is_zero_stays_on_the_response_detail_page(): void {
        global $DB, $OUTPUT, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $eid = $this->seed_eval('Tom & Jerry', 1);
        $choice = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'multichoice', 'questiontext' => 'How many?',
            'options' => "0\n1\nTom & Jerry", 'required' => 0,
        ]);
        $rating = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'rating', 'questiontext' => 'Overall', 'required' => 0,
        ]);
        $rid = $this->put_answers($eid, [$choice => '0', $rating => 3]);

        $form = evaluation_manager::get($eid);
        $detail = evaluation_manager::response_detail_rows($form,
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $rid], '*', MUST_EXIST));
        [$c, $r] = $detail['questions'];
        $this->assertSame(['0', '1', 'Tom & Jerry'], array_column($c['histogram'], 'label'));
        $this->assertSame([true, true, true], array_column($c['histogram'], 'is_option'));
        $this->assertSame([true, false, false], array_column($c['histogram'], 'is_my_choice'), 'the "0" pick is kept');
        $this->assertSame([true, true, true, true, true], array_column($r['histogram'], 'is_level'));

        $PAGE->set_url('/local/sentientia_evaluation/response_detail.php', ['id' => $rid]);
        $html = $OUTPUT->render_from_template('local_sentientia_evaluation/response_detail', [
            'response_id' => $rid, 'eval_name' => evaluation_manager::display_text($form->name), 'eval_id' => $eid,
            'submitted_at' => '1 Jan', 'kirkpatrick' => '1', 'is_anonymous' => true, 'user_name' => '',
            'user_email' => '', 'employee_id' => '', 'questions' => $detail['questions'],
            'question_count' => count($detail['questions']), 'total_responses' => $detail['total_responses'],
            'back_url' => '/back', 'analysis_url' => '/analysis',
        ]);
        $this->assertSame(3, substr_count($html, '<div style="width:160px;" class="small">'), 'one row per option');
        $this->assertStringContainsString('<div style="width:160px;" class="small">0</div>', $html);
        $this->assertStringContainsString('<div style="width:160px;" class="small">Tom &amp; Jerry</div>', $html);
        $this->assertStringContainsString('<h2 class="mb-0">Tom &amp; Jerry</h2>', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html, 'escaped once, not twice');
    }

    /**
     * The Subject of the response list is named through the same fullname() as the CSV (the site's name format applies
     * to both), in one query; a pending shell is not listed; a protected form names nobody.
     */
    public function test_the_response_list_names_the_subject_the_way_the_csv_does(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('fullnamedisplay', 'lastname, firstname');
        $gen = $this->getDataGenerator();
        $supervisor = $gen->create_user(['firstname' => 'Sue', 'lastname' => 'Supervisor']);
        $subject = $gen->create_user(['firstname' => 'Sam', 'lastname' => 'Subject']);
        $deleted = $gen->create_user(['firstname' => 'Dee', 'lastname' => 'Deleted']);

        $eid = $this->seed_eval('Supervisor', 1);
        $q = $this->seed_question($eid, 'rating');
        $now = time();
        $named = $this->seed_response($eid, (int) $supervisor->id, [$q => 4], $now - 10);
        $this->set_subject($named, (int) $subject->id);
        $nobody = $this->seed_response($eid, (int) $supervisor->id, [$q => 3], $now - 20);
        $soft = $this->seed_response($eid, (int) $supervisor->id, [$q => 2], $now - 30);
        $this->set_subject($soft, (int) $deleted->id);
        $gone = $this->seed_response($eid, (int) $supervisor->id, [$q => 1], $now - 40);
        $this->set_subject($gone, 987654321);
        // An invited user's pending shell: not a response, so not listed, whoever it names.
        $shell = $this->seed_response($eid, (int) $supervisor->id, [], 0);
        $this->set_subject($shell, (int) $subject->id);
        delete_user($deleted);

        $form = evaluation_manager::get($eid);
        $this->assertTrue(evaluation_manager::shows_subject($form));
        $rows = evaluation_manager::response_list_rows($form, false, true);
        $this->assertSame([$named, $nobody, $soft, $gone], array_column($rows, 'id'),
            'newest first, and the pending shell is not a response');

        $this->assertSame(fullname($subject), $rows[0]['subject_name']);
        $this->assertNotSame('Sam Subject', $rows[0]['subject_name'], 'the site\'s name format applies');
        $this->assertSame('', $rows[1]['subject_name'], 'a response with no subject');
        $left = get_string('responses_subject_deleted', 'local_sentientia_evaluation');
        $this->assertSame($left, $rows[2]['subject_name'], 'a deleted account');
        $this->assertSame($left, $rows[3]['subject_name'], 'an account that is gone');

        // The CSV names the same person the same way, whether each row looks them up or the export reads them all at
        // once (exportcsv.php).
        $questions = evaluation_manager::get_questions($eid);
        $subjectids = array_column(
            $DB->get_records('local_sentientia_evaluation_responses', ['evaluationid' => $eid], '', 'id, subject_userid'),
            'subject_userid');
        $names = evaluation_manager::subject_names($subjectids);
        $this->assertSame([(int) $subject->id => fullname($subject)], $names, 'only the live account has a name');
        foreach ([$named, $soft, $gone] as $id) {
            $response = $DB->get_record('local_sentientia_evaluation_responses', ['id' => $id], '*', MUST_EXIST);
            $single = evaluation_manager::response_to_csv_row($response, $questions, $form, null, true)[3];
            $batched = evaluation_manager::response_to_csv_row($response, $questions, $form, null, true, $names)[3];
            $this->assertSame($single, $batched);
        }
        $this->assertSame($rows[0]['subject_name'], evaluation_manager::subject_label((int) $subject->id));
        $this->assertSame('(deleted user)', evaluation_manager::subject_label(987654321, $names));
        $this->assertSame('', evaluation_manager::subject_label(null, $names));

        // A protected form names neither the respondent nor the subject, and shows the day, not the minute.
        $protected = evaluation_manager::response_list_rows($form, true, false);
        $this->assertCount(4, $protected);
        $anonymous = get_string('eval_response_responder_anonymous', 'local_sentientia_evaluation');
        foreach ($protected as $row) {
            $this->assertSame($anonymous, $row['user_name']);
            $this->assertSame('', $row['user_email']);
            $this->assertSame('', $row['subject_name']);
        }
        $this->assertSame(evaluation_manager::submitted_label($now - 10, true), $protected[0]['submitted_at']);
    }

    /**
     * Names and descriptions reach a template that prints them with {{ }} filtered but not escaped (the template
     * escapes once), and the aggregate page badges a form as anonymous when it is identity-protected, not only when
     * its flag is set today.
     */
    public function test_names_reach_the_templates_unescaped_and_the_badge_follows_identity_protection(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertSame('Tom & Jerry', evaluation_manager::display_text('Tom & Jerry'));
        $this->assertSame('', evaluation_manager::display_text(null));
        $this->assertSame('', evaluation_manager::display_text(''));
        $this->assertSame('0', evaluation_manager::display_text('0'));

        $eid = $this->seed_eval('Tom & Jerry', 2);
        $DB->set_field('local_sentientia_evaluation', 'description', 'Fish & chips', ['id' => $eid]);
        $header = evaluation_manager::responses_page_header(evaluation_manager::get($eid));
        $this->assertSame('Tom & Jerry', $header['name']);
        $this->assertSame('Fish & chips', $header['description']);
        $this->assertSame(evaluation_manager::KIRKPATRICK_LEVELS[2], $header['kirkpatrick_label']);
        $this->assertFalse($header['is_anonymous'], 'a named form with no anonymous answer');

        // One anonymous answer in the past makes the form protected for good, whatever its flag says.
        $q = $this->seed_question($eid, 'rating');
        $this->seed_response($eid, 0, [$q => 5], time());
        $this->assertFalse((bool) evaluation_manager::get($eid)->anonymous, 'the flag itself is off');
        $this->assertTrue(evaluation_manager::responses_page_header(evaluation_manager::get($eid))['is_anonymous']);
    }

    /**
     * The learner-facing rows: positions count 1..n (the page printed the question id + 1), text and options are not
     * escaped twice, a number question's bounds are not offered as options to tick, and a bound of 0 is kept.
     */
    public function test_the_respond_rows_count_from_one_and_keep_text_options_and_bounds_straight(): void {
        global $OUTPUT, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        // Another form first, so this one's question ids are not 1, 2, 3.
        $other = $this->seed_eval('Other', 1);
        evaluation_manager::create_question((object) [
            'evaluationid' => $other, 'questiontype' => 'text', 'questiontext' => 'Filler', 'required' => 0,
        ]);
        $eid = $this->seed_eval('Respond', 1);
        $choice = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'multichoice', 'questiontext' => 'Tom & Jerry?',
            'options' => "0\nTom & Jerry\nB", 'required' => 1,
        ]);
        $bounded = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'numeric', 'questiontext' => 'Score',
            'numeric_min' => 0, 'numeric_max' => 10, 'required' => 0,
        ]);
        $free = evaluation_manager::create_question((object) [
            'evaluationid' => $eid, 'questiontype' => 'numeric', 'questiontext' => 'How many', 'required' => 0,
        ]);

        $rows = evaluation_manager::respond_question_rows(evaluation_manager::get_questions($eid));
        $this->assertSame([1, 2, 3], array_column($rows, 'position'), 'numbered 1..n, not by question id');
        $this->assertEquals([$choice, $bounded, $free], array_column($rows, 'id'));
        [$c, $b, $f] = $rows;

        $this->assertSame('Tom & Jerry?', $c['questiontext'], 'filtered, not escaped: the template escapes once');
        $this->assertSame(['0', 'Tom & Jerry', 'B'], array_column($c['options'], 'label'));
        $this->assertSame(['0', 'Tom & Jerry', 'B'], array_column($c['options'], 'value'));
        $this->assertTrue($c['is_multichoice']);

        $this->assertSame([], $b['options'], 'the bounds of a number question are not options to tick');
        $this->assertTrue($b['has_numeric_min']);
        $this->assertTrue($b['has_numeric_max']);
        $this->assertSame('0', $b['numeric_min']);
        $this->assertSame('10', $b['numeric_max']);
        $this->assertSame('Range: 0 to 10', $b['numeric_hint']);
        $this->assertFalse($f['has_numeric_min']);
        $this->assertFalse($f['has_numeric_max']);
        $this->assertSame('', $f['numeric_hint']);

        $PAGE->set_url('/local/sentientia_evaluation/respond.php', ['id' => $eid]);
        $html = $OUTPUT->render_from_template('local_sentientia_evaluation/respond', [
            'evaluationid' => $eid, 'name' => evaluation_manager::display_text('Tom & Jerry'), 'description' => '',
            'kirkpatrick_label' => '', 'is_anonymous' => false, 'has_questions' => true, 'questions' => $rows,
            'already_responded' => false, 'context_courseid' => 0, 'context_programid' => 0,
            'context_classroomid' => 0, 'backurl' => '/my/', 'window_locked' => false, 'window_notyetopen' => false,
            'window_closed' => false, 'window_when' => '', 'is_pulse' => false,
        ]);
        $this->assertStringContainsString('<span class="question-position">1</span>', $html);
        $this->assertStringContainsString('<span class="question-position">3</span>', $html);
        $this->assertStringContainsString('min="0"', $html, 'a lower bound of 0 is kept on the input');
        $this->assertStringContainsString('max="10"', $html);
        $this->assertStringContainsString('<h2 class="mb-1" style="font-weight: 700;">Tom &amp; Jerry</h2>', $html);
        $this->assertStringContainsString('<label for="q-' . $choice . '-opt-1">Tom &amp; Jerry</label>', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html, 'escaped once, not twice');
    }
}
