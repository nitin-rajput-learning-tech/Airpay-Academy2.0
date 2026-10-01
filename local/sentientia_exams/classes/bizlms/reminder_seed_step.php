<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * Step 2 of the exams importer: mark every overdue escalation of an imported exam as already sent
 * (ADR-032, mapping doc section 9, "Side effects").
 *
 * The exam_overdue task (db/tasks.php, off by default, two-step opt-in) messages a learner's supervisor for
 * every enrolled learner who has not finished an exam whose quiz.timeclose passed in the last few days. It dedupes
 * on local_sentientia_exams_remind_sent (user, exam, bucket, deadline), where an overdue bucket is stored as a
 * NEGATIVE days_before_deadline. An imported exam has a past deadline and a roster of learners who never sat it,
 * so the first run after somebody turns the task on would escalate every one of them to a supervisor, for a
 * deadline that passed before Sentientia went live.
 *
 * So for every imported exam whose deadline passed before the import, this step writes the dedupe row of each
 * learner the task would pick (active enrolment in an enabled enrol instance, user not deleted or suspended, no
 * finished and graded attempt) for each configured overdue bucket. The mapping doc puts this in finalise(); the
 * framework's static scan bans every DB write there, so it is a load step: its target rows are written through
 * the same writer, and its map rows are the proof of what was seeded.
 *
 * A reminder bucket (positive days) of a past deadline is never reached by exam_reminder (it skips a deadline in the
 * past), so only the overdue buckets are seeded. A deadline still ahead at import time is left alone: the
 * reminders it earns once somebody enables the tasks are the feature working.
 *
 * Each group (one exam course) gives exactly one primary outcome. The first row seeded is the primary insert;
 * every further row is a sub-row 'seed:<n>' (a position in the course's list, never a learner: the map holds no
 * personal data). A course with nothing to seed is archived with a reason.
 *
 * It depends on step 1: the exam a row points at is the one that step created, found through the map.
 *
 * @package    local_sentientia_exams
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reminder_seed_step extends exam_quiz_step {

    /** The accounting unit of this step. */
    public const SOURCE = '#quiz.reminder_seed';

    /** Config key of exam_overdue's buckets, and its default (the task's own). */
    private const BUCKETS_CONFIG = 'overdue_days_after';
    private const BUCKETS_DEFAULT = '1,7,14';

    /** Rows per page when listing a roster. */
    private const PAGE = 5000;

    public function key(): string {
        return 'exams.reminder_seed';
    }

    public function sourcetable(): string {
        return self::SOURCE;
    }

    public function targettable(): string {
        return exams_importer::REMIND_TABLE;
    }

    /**
     * One exam course in, the dedupe rows of its overdue learners out.
     *
     * @param \stdClass[] $rows The quizzes of one exam course.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $quizzes = self::by_id($rows);
        $courseid = (int) $quizzes[0]->course;
        $buckets = self::overdue_buckets();
        $now = time();

        $outcomes = [];
        $position = 0;
        if ($buckets) {
            foreach ($quizzes as $index => $quiz) {
                $deadline = (int) $quiz->timeclose;
                if ($deadline <= 0 || $deadline >= $now) {
                    continue;
                }
                // The exam step keyed its primary row with no subkey (the course's lowest quiz) and the others
                // 'quiz:<id>'. Only an exam that step CREATED is seeded: a fold points at somebody else's exam.
                $entry = $ctx->map->entry(exam_step::SOURCE, $courseid, $index === 0 ? '' : 'quiz:' . (int) $quiz->id);
                if ($entry === null || $entry['outcome'] !== 'imported' || $entry['targetid'] === null) {
                    continue;
                }
                $examid = (int) $entry['targetid'];
                foreach (self::overdue_learners($ctx, $courseid, (int) $quiz->id) as $userid) {
                    foreach ($buckets as $bucket) {
                        $row = (object) [
                            'userid' => $userid,
                            'examid' => $examid,
                            'days_before_deadline' => -$bucket,
                            'deadline_ts' => $deadline,
                            // The table has no source timestamp: this is when the dedupe row was made.
                            'timesent' => $now,
                        ];
                        $position++;
                        $outcomes[] = $position === 1
                            ? outcome::insert($courseid, exams_importer::REMIND_TABLE, $row)
                            : outcome::insert($courseid, exams_importer::REMIND_TABLE, $row, 'seed:' . $position);
                    }
                }
            }
        }
        if (!$outcomes) {
            return [outcome::archive($courseid, exams_importer::REASON_NOTHING_TO_SEED)];
        }
        return $outcomes;
    }

    /**
     * The overdue buckets exam_overdue is configured with, sorted, as the task reads them: a comma or space
     * separated list of whole days from 1 to 365.
     *
     * @return int[]
     */
    public static function overdue_buckets(): array {
        $raw = (string) get_config('local_sentientia_exams', self::BUCKETS_CONFIG);
        $raw = $raw !== '' ? $raw : self::BUCKETS_DEFAULT;
        $buckets = [];
        foreach (preg_split('/[,\s]+/', trim($raw)) ?: [] as $part) {
            if ($part !== '' && ctype_digit($part) && (int) $part > 0 && (int) $part <= 365) {
                $buckets[(int) $part] = true;
            }
        }
        $buckets = array_keys($buckets);
        sort($buckets);
        return $buckets;
    }

    /**
     * The learners exam_overdue could escalate for this quiz: an active enrolment in an enabled enrol instance
     * of the quiz's course, a user who is neither deleted nor suspended, and no finished, graded attempt. (The
     * task also wants a supervisor; that is left out so a supervisor assigned later does not start the flood.)
     *
     * Read through the context's bounded reader, by keyset pages, never one unbounded query.
     *
     * @param context $ctx
     * @param int $courseid
     * @param int $quizid
     * @return int[] User ids, ascending.
     */
    private static function overdue_learners(context $ctx, int $courseid, int $quizid): array {
        $filter = [
            't.status = :exsue
             AND t.enrolid IN (SELECT en.id FROM {enrol} en WHERE en.courseid = :exscourse AND en.status = :exsen)
             AND t.userid IN (SELECT u.id FROM {user} u WHERE u.deleted = 0 AND u.suspended = 0)
             AND NOT EXISTS (SELECT 1 FROM {quiz_attempts} qa
                              WHERE qa.quiz = :exsquiz AND qa.userid = t.userid
                                AND qa.state = :exsstate AND qa.sumgrades IS NOT NULL)',
            ['exsue' => 0, 'exscourse' => $courseid, 'exsen' => 0, 'exsquiz' => $quizid, 'exsstate' => 'finished'],
        ];
        $users = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page('user_enrolments', $after, self::PAGE, ['userid'], $filter);
            foreach ($page as $id => $row) {
                $users[(int) $row->userid] = true;
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        $ids = array_keys($users);
        sort($ids);
        return $ids;
    }
}
