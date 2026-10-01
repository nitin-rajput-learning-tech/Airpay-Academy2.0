<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_exams\exam_manager;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * Step 1 of the exams importer: the quizzes of the BizLMS exam courses become local_sentientia_exams rows
 * (ADR-032, mapping doc section 9).
 *
 *   one exam course  = one group (the quizzes of one course)
 *   its lowest quiz  = the primary row; map key ('#quiz.course', course id, '')
 *   any further quiz = a sub-row; map key ('#quiz.course', course id, 'quiz:<quiz id>'), flagged multi_quiz
 *
 * Column map (the table already exists; no schema change):
 *
 *   quiz.id                                    -> quizid
 *   course.fullname                            -> name        ('fullname - quiz.name' when the course has more than one quiz)
 *   course.open_path (normalised, and walked
 *       up to the nearest organisation)        -> open_path   (NULL when it does not resolve: cross-tenant callers only)
 *   the organisation at that path              -> costcenterid (the same convention as exam_manager::create())
 *   second segment of the path                 -> departmentid
 *   course.category                            -> categoryid  (0 when the category is gone)
 *   quiz.timelimit                             -> duration    (seconds; 0 -> NULL)
 *   grade item gradepass / grademax * 100      -> passinggrade (percent; none -> NULL, which view.php reads as 50)
 *   course.visible                             -> status and visible (1 -> 1 and 1; 0 -> 0 and 0)
 *   course.timecreated, course.timemodified    -> timecreated, timemodified
 *
 * A quiz that an exam row already wraps (an admin made the exam by hand, on UAT for instance) is never
 * wrapped twice: idx_quizid is not unique and the duplicate check lives only in exam_manager::create(), so this
 * step checks the quiz id itself. That course's primary row is a fold into the existing exam; nothing of the
 * existing exam is changed.
 *
 * @package    local_sentientia_exams
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exam_step extends exam_quiz_step {

    /** The accounting unit: exam courses, read through their quizzes. */
    public const SOURCE = '#quiz.course';

    /** The longest local_sentientia_exams.name. */
    private const NAME_MAX = 254;

    /** The longest local_sentientia_exams.open_path. */
    private const PATH_MAX = 254;

    public function key(): string {
        return 'exams.exam';
    }

    public function sourcetable(): string {
        return self::SOURCE;
    }

    public function targettable(): string {
        return exams_importer::TARGET;
    }

    /**
     * One exam course in, its exam rows out.
     *
     * @param \stdClass[] $rows The quizzes of one exam course.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        // The owner's choice, read so that a value this code does not implement can never run: the importer
        // declares per_quiz as the only allowed value, and preflight blocks any other.
        $ctx->decision(exams_importer::DECISION_MULTI_QUIZ);

        $quizzes = self::by_id($rows);
        $courseid = (int) $quizzes[0]->course;

        $course = $ctx->legacy->fetch('course', [$courseid],
            ['id', 'fullname', 'category', 'visible', 'timecreated', 'timemodified', 'open_path'])[$courseid] ?? null;
        if ($course === null) {
            return [outcome::skip($courseid, exams_importer::REASON_COURSE_MISSING, 'course_gone')];
        }

        // Tenant: the course's own path, normalised and checked against the organisation engine.
        $raw = isset($course->open_path) ? (string) $course->open_path : null;
        [$path, , $method] = $ctx->tenant->resolve(['course' => $raw]);
        $coursewarnings = [];
        if ($path !== null && \core_text::strlen($path) > self::PATH_MAX) {
            // The column holds 254 characters. A path is never cut short (that would be another tenant's path):
            // it is treated as a path that does not resolve, and the decision below applies.
            $path = null;
            $method = 'unresolved';
            $coursewarnings[] = 'path_too_long';
        }
        if ($path === null && $ctx->decision(exams_importer::DECISION_TENANT) === 'skip') {
            $skipped = outcome::skip($courseid, exams_importer::REASON_TENANT_UNRESOLVED, 'no_tenant')
                ->tenant_method($method);
            foreach ($coursewarnings as $code) {
                $skipped->warn($code);
            }
            return [$skipped];
        }
        $costcenterid = 0;
        $departmentid = null;
        if ($path !== null) {
            $segments = explode('/', ltrim($path, '/'));
            $org = $ctx->tenant->org_for_path($path, false);
            // An organisation's path ends in its own id (org_manager builds it that way), so when the org table
            // is not there to ask (a dry run before the org importer has run), the last segment is the id.
            $costcenterid = $org !== null ? (int) $org->id : (int) end($segments);
            $departmentid = isset($segments[1]) ? (int) $segments[1] : null;
        }

        $multi = count($quizzes) > 1;
        $pass = exam_manager::quiz_pass_percentages(array_map(static fn(\stdClass $q): int => (int) $q->id, $quizzes));

        $outcomes = [];
        foreach ($quizzes as $position => $quiz) {
            $warnings = $coursewarnings;
            $quizid = (int) $quiz->id;

            // Name: the course's full name; a course with several quizzes names each exam after its quiz too.
            [$fullname, $repaired] = self::clean_text(trim((string) $course->fullname));
            [$quizname, $quizrepaired] = self::clean_text(trim((string) $quiz->name));
            if ($repaired || $quizrepaired) {
                $warnings[] = 'repaired_text:name';
            }
            if ($fullname === '') {
                $fullname = $quizname !== '' ? $quizname : 'Exam ' . $quizid;
                $warnings[] = 'name_derived';
            } else if ($multi && $quizname !== '') {
                $fullname .= ' — ' . $quizname;
            }

            $categoryid = (int) ($course->category ?? 0);
            if ($categoryid > 0 && !$ctx->lookups->exists('course_categories', $categoryid)) {
                $categoryid = 0;
                $warnings[] = 'category_missing';
            }

            $timelimit = (int) $quiz->timelimit;
            $percent = $pass[$quizid] ?? 0.0;
            if ($percent > 100.0) {
                $percent = 100.0;
                $warnings[] = 'passinggrade_clamped';
            }

            $created = (int) ($course->timecreated ?? 0);
            $modified = (int) ($course->timemodified ?? 0);
            if ($created <= 0) {
                $created = (int) $quiz->timecreated;
                $warnings[] = 'derived_timestamp';
            }
            if ($modified <= 0) {
                $modified = $created;
                $warnings[] = 'derived_timestamp';
            }

            $visible = (int) ($course->visible ?? 1) === 1 ? 1 : 0;
            $row = (object) [
                'name' => $ctx->text->fit($fullname, self::NAME_MAX, 'name'),
                'quizid' => $quizid,
                'costcenterid' => $costcenterid,
                'departmentid' => $departmentid,
                'categoryid' => $categoryid,
                'open_path' => $path,
                'duration' => $timelimit > 0 ? $timelimit : null,
                'passinggrade' => $percent > 0 ? round($percent, 2) : null,
                'status' => $visible,
                'visible' => $visible,
                'timecreated' => $created,
                'timemodified' => $modified,
            ];

            $existing = $this->exam_of_quiz($ctx, $quizid);
            if ($position === 0) {
                $primary = $existing > 0
                    ? outcome::fold($courseid, exams_importer::TARGET, $existing, exams_importer::REASON_ALREADY_REGISTERED)
                    : outcome::insert($courseid, exams_importer::TARGET, $row);
                $primary->tenant_method($method);
                if ($multi) {
                    $primary->warn('multi_quiz');
                }
                foreach ($warnings as $code) {
                    $primary->warn($code);
                }
                $outcomes[] = $primary;
            } else if ($existing > 0) {
                // Only an insert can be a sub-row, and the quiz already has its exam: nothing to add.
                $outcomes[0]->warn('extra_quiz_already_registered');
            } else {
                $sub = outcome::insert($courseid, exams_importer::TARGET, $row, 'quiz:' . $quizid);
                foreach ($warnings as $code) {
                    $sub->warn($code);
                }
                $outcomes[] = $sub;
            }
        }
        return $outcomes;
    }

    /**
     * The exam that already wraps a quiz, if any (read through the context: a step has no database).
     *
     * @param context $ctx
     * @param int $quizid
     * @return int Exam id; 0 when the quiz has none.
     */
    private function exam_of_quiz(context $ctx, int $quizid): int {
        $found = $ctx->legacy->page(exams_importer::TARGET, 0, 1, ['id'], ['t.quizid = :exquiz', ['exquiz' => $quizid]]);
        return $found ? (int) array_key_first($found) : 0;
    }
}
