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
}
