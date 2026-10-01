<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * The exams importer (ADR-032, mapping doc section 9): the quizzes of the BizLMS online exam courses become
 * local_sentientia_exams rows.
 *
 * BizLMS has no exam table of its own. An online exam is a COURSE with open_module = 'online_exams' and
 * open_coursetype = 1, in the singleactivity format, holding a quiz (BZ local/onlineexams/classes/external.php:150,161).
 * Attempts, grades and completions live in core and are read by quiz id, so they carry over untouched; the import
 * only wraps each quiz in the Sentientia exam row that the exams pages list (exam_manager, view.php).
 *
 *   step 1 exams.exam           the exam rows        ('#quiz.course', source id = the course id)
 *   step 2 exams.reminder_seed  the dedupe rows of the overdue escalation (see reminder_seed_step)
 *
 * Nothing is written outside the two target tables. The importer sends nothing, enrols nobody, completes
 * nothing and fires no event. Forum pseudo-courses (open_module = 'forum', also open_coursetype = 1) stay ordinary
 * courses and are not touched; the catalog keeps them out of its lists (decision exams.forum_pseudocourses,
 * implemented in local_sentientia_catalog, so this importer does not declare it).
 *
 * The ids are MAP: nothing outside local_sentientia_exams stores an exam id (BizLMS had none), and the
 * idempotence key is the ADR-032 map ('#quiz.course', course id, subkey), not a column of the target.
 *
 * Depends on the org feature: the exam table has a tenant column (open_path), and tenant resolution reads the
 * organisation table the org importer fills.
 *
 * @package    local_sentientia_exams
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exams_importer implements importer {

    /** Feature key. */
    public const FEATURE = 'exams';

    /** The exam table, the primary target. */
    public const TARGET = 'local_sentientia_exams';

    /** The deadline-reminder dedupe table, the target of the seed step. */
    public const REMIND_TABLE = 'local_sentientia_exams_remind_sent';

    /**
     * Plugin version that carries this importer and the reader fixes shipped with it (version.php; no schema
     * change: both targets are in db/install.xml since 2026052001).
     */
    public const REQUIRES_VERSION = 2026100100;

    /** A quiz that an exam row already wraps: the course's primary row is folded into that exam. */
    public const REASON_ALREADY_REGISTERED = 'already_registered';

    /** The seed step found nothing to seed for a course (no deadline in the past, nobody to escalate). */
    public const REASON_NOTHING_TO_SEED = 'nothing_to_seed';

    /** Used only when the owner decides that a course with no resolvable tenant is not imported. */
    public const REASON_TENANT_UNRESOLVED = 'tenant_unresolved';

    /** A quiz whose course is gone: cannot happen through the source filter, which names existing courses. */
    public const REASON_COURSE_MISSING = 'course_missing';

    /** Decision: courses whose tenant cannot be resolved. pathless (signed) or skip. */
    public const DECISION_TENANT = 'tenant.unresolved.exams';

    /** Decision: a course with several quizzes. per_quiz (signed) is the only value this importer implements. */
    public const DECISION_MULTI_QUIZ = 'exams.multi_quiz';

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return 'local_sentientia_exams';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    public function depends(): array {
        return ['org'];
    }

    public function sources(): array {
        // The quiz table is core, so it always exists and the feature is always applicable; a database with
        // no exam course simply gives zero groups. Exam courses are found through course.open_module, which
        // the step's filter reads when the column is there.
        return [exam_quiz_step::SOURCE_TABLE => new source_spec(exam_quiz_step::SOURCE_TABLE)];
    }

    public function declined_tables(): array {
        return [
            'local_onlinetests' => 'Not in the production code: no BizLMS file declares or writes it. The old exam '
                . 'reader assumed it. Preflight blocks the import when the table exists with rows, so a database '
                . 'that does hold exams there is looked at before anything is imported.',
        ];
    }

    public function target_tables(): array {
        return [self::TARGET, self::REMIND_TABLE];
    }

    public function core_writes(): array {
        return [];
    }

    public function tenant_columns(): array {
        return [self::TARGET => 'open_path'];
    }

    public function reasons(): array {
        return [
            // A quiz that already has an exam: benign, the existing exam is untouched.
            new reason(self::REASON_ALREADY_REGISTERED, false, false),
            // A course with nobody to escalate: benign.
            new reason(self::REASON_NOTHING_TO_SEED, false, false),
            // Rows that were not imported: the owner accepts them in writing. Neither is retryable: both steps are
            // derived groups, which --retry-skipped refuses.
            new reason(self::REASON_TENANT_UNRESOLVED, false, true),
            new reason(self::REASON_COURSE_MISSING, false, true),
        ];
    }

    public function decisions(): array {
        return [
            new decision(self::DECISION_TENANT,
                'Exam courses whose tenant cannot be resolved: import with no tenant path (pathless) or skip them', true,
                null, ['pathless', 'skip']),
            new decision(self::DECISION_MULTI_QUIZ,
                'A course with several quizzes: one exam per quiz (per_quiz), which keeps every quiz\'s history', true,
                null, ['per_quiz']),
        ];
    }

    public function atomic(): bool {
        return true;
    }

    public function steps(): array {
        // Order matters: the seed step finds the exams the first step created through the map.
        return [new exam_step(), new reminder_seed_step()];
    }

    public function preflight(context $ctx): preflight {
        $pf = new preflight();

        // A table the old reader assumed, which no BizLMS code defines. With rows it is somebody's exam data.
        if ($ctx->legacy->exists('local_onlinetests')) {
            $rows = $ctx->legacy->count('local_onlinetests');
            if ($rows > 0) {
                $pf->block('local_onlinetests_has_rows:' . $rows);
            }
        }

        if (!exam_quiz_step::has_markers()) {
            // Not a blocker: a database that never ran BizLMS has no exam courses.
            $pf->warn('course_exam_markers_absent');
            return $pf;
        }

        $filter = exam_quiz_step::quiz_filter();
        $quizzes = $ctx->legacy->count(exam_quiz_step::SOURCE_TABLE, $filter);
        $courses = $ctx->legacy->count_groups(exam_quiz_step::SOURCE_TABLE, ['course'], $filter);
        $pf->count('exam_courses_with_a_quiz', $courses);
        $pf->count('exam_quizzes', $quizzes);
        $pf->count('multi_quiz_extra_quizzes', max(0, $quizzes - $courses));

        // Exam courses that hold no quiz cannot be wrapped: there is nothing to attempt. Reported, not imported.
        [$csql, $cparams] = exam_quiz_step::course_filter();
        $pf->count('exam_courses_without_a_quiz', $ctx->legacy->count('course', [
            $csql . ' AND NOT EXISTS (SELECT 1 FROM {quiz} xq WHERE xq.course = t.id)', $cparams,
        ]));
        // Without a course.open_path column no exam course has a path at all.
        $pf->count('exam_courses_without_a_path', $ctx->legacy->has_column('course', 'open_path')
            ? $ctx->legacy->count('course', [
                $csql . ' AND (t.open_path IS NULL OR t.open_path = :exnopath)', $cparams + ['exnopath' => ''],
            ])
            : $ctx->legacy->count('course', [$csql, $cparams]));
        // Forum pseudo-courses share the open_coursetype marker. They are not imported.
        $pf->count('forum_pseudo_courses', $ctx->legacy->count('course', [
            't.id > 1 AND t.open_module = :exforum AND t.open_coursetype = :extype',
            ['exforum' => 'forum', 'extype' => exam_quiz_step::COURSETYPE_PSEUDO],
        ]));
        // Quizzes that already have an exam: their course's primary row folds into it.
        $pf->count('quizzes_already_registered', $ctx->legacy->count(self::TARGET, [
            't.quizid IN (SELECT xq.id FROM {quiz} xq WHERE xq.course IN (SELECT ec.id FROM {course} ec '
            . 'WHERE ec.id > 1 AND ec.open_module = :exmod AND ec.open_coursetype = :extype))',
            ['exmod' => exam_quiz_step::MODULE_EXAMS, 'extype' => exam_quiz_step::COURSETYPE_PSEUDO],
        ]));
        return $pf;
    }

    public function verify(context $ctx): array {
        global $DB;
        $failures = [];

        // These checks prove the state the import left. The parity check runs verify() again for as long as the
        // site is online, and its source is live core data (the quizzes of the exam courses) and its targets are
        // rows admins may delete once the site is open (an exam, with its dedupe rows left behind). The runbook
        // sets bizlms_production_open at go-live, as for the framework's own missing-target check, and from then
        // on a changed quiz or a deleted exam is not a failed import.
        if ((int) get_config('local_sentientia_platform', 'bizlms_production_open') > 0) {
            return [];
        }

        // The accounting identity of a derived step is not checked by the framework (its unit is a group, not a
        // table row), so the importer checks it: one primary map row per exam course that holds a quiz, in
        // each step.
        $groups = $ctx->legacy->count_groups(exam_quiz_step::SOURCE_TABLE, ['course'], exam_quiz_step::quiz_filter());
        foreach ([exam_step::SOURCE, reminder_seed_step::SOURCE] as $source) {
            $mapped = $DB->count_records(legacymap::TABLE, ['sourcetable' => $source, 'subkey' => '']);
            if ($mapped !== $groups) {
                $failures[] = 'accounting:' . $source . ': courses=' . $groups . ' mapped=' . $mapped;
            }
        }

        // The importer enforces quiz-id uniqueness (idx_quizid is not unique): no quiz may have more than one
        // exam where one of them is a row this import made.
        $duplicates = $DB->get_records_sql(
            "SELECT e.quizid AS k, COUNT(1) AS n
               FROM {" . self::TARGET . "} e
              WHERE e.quizid IN (SELECT e2.quizid
                                   FROM {" . self::TARGET . "} e2
                                   JOIN {" . legacymap::TABLE . "} m ON m.targettable = :extarget AND m.targetid = e2.id
                                    AND m.feature = :exfeature AND m.outcome = :exoutcome)
           GROUP BY e.quizid
             HAVING COUNT(1) > 1",
            ['extarget' => self::TARGET, 'exfeature' => self::FEATURE, 'exoutcome' => 'imported']);
        if ($duplicates) {
            $failures[] = 'quiz_wrapped_twice: quizzes=' . count($duplicates);
        }

        // An imported exam's pass mark is a percentage.
        $badpass = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::TARGET . "} e
               JOIN {" . legacymap::TABLE . "} m ON m.targettable = :extarget AND m.targetid = e.id
                AND m.feature = :exfeature AND m.outcome = :exoutcome
              WHERE e.passinggrade IS NOT NULL AND (e.passinggrade <= 0 OR e.passinggrade > 100)",
            ['extarget' => self::TARGET, 'exfeature' => self::FEATURE, 'exoutcome' => 'imported']);
        if ($badpass > 0) {
            $failures[] = 'passinggrade_out_of_range: rows=' . $badpass;
        }

        // Every seeded dedupe row is there and names an exam that exists.
        $orphans = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . legacymap::TABLE . "} m
              WHERE m.feature = :exfeature AND m.sourcetable = :exsource AND m.outcome = :exoutcome
                AND m.targettable = :exremind
                AND NOT EXISTS (SELECT 1
                                  FROM {" . self::REMIND_TABLE . "} r
                                  JOIN {" . self::TARGET . "} x ON x.id = r.examid
                                 WHERE r.id = m.targetid)",
            ['exfeature' => self::FEATURE, 'exsource' => reminder_seed_step::SOURCE,
                'exoutcome' => 'imported', 'exremind' => self::REMIND_TABLE]);
        if ($orphans > 0) {
            $failures[] = 'seeded_rows_without_an_exam: rows=' . $orphans;
        }

        return $failures;
    }

    public function finalise(context $ctx): void {
        // Nothing to do. The ids are MAP (no sequence to reset), no files are copied, and no cache holds an exam.
        // The mapping doc has finalise() seed the dedupe table; the framework scan bans every DB write here, so
        // reminder_seed_step does it as a load step instead.
    }
}
