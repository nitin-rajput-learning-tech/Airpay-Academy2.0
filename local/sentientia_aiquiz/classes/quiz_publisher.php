<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Phase G.4 — the real mod_quiz publisher (ADR-028 engineering gate #3).
 *
 * Closes the "quiz id 0" stub: approved/edited questions from a reviewed
 * draft become REAL question-bank questions inside the target course's
 * default shared question bank (Moodle 5.x mod_qbank instance), and a
 * REAL quiz activity is created in the course with those questions
 * attached. The draft is then marked pushed with the actual quiz id.
 *
 * Design choices (grounded in the local Moodle 5.1.3 source):
 *  - Question creation goes through the battle-tested GIFT import
 *    pipeline (qformat_gift) rather than hand-shaping qtype form data —
 *    the importer owns answer/feedback/hint persistence across core
 *    versions, we own only the (escaped) GIFT text.
 *  - The bank is question_bank_helper::get_default_open_instance_system_type()
 *    (the 5.x canonical "course question bank"), category =
 *    question_get_default_category($bankcontext, true).
 *  - The activity is created with course/modlib.php add_moduleinfo(),
 *    quiz defaults mirroring mod/quiz/tests/generator/lib.php, then
 *    quiz_add_quiz_question() + quiz_update_sumgrades().
 *
 * Everything runs in one delegated transaction: a failure mid-way never
 * leaves a half-published quiz or orphaned draft state.
 *
 * @package local_sentientia_aiquiz
 */
class quiz_publisher {

    /** Default maximum grade for the created quiz. */
    public const DEFAULT_GRADE = 10.0;

    /**
     * Publish a reviewed draft into a course as a real quiz.
     *
     * @param int $draftid
     * @param int $courseid Target course (draft->courseid, or reviewer-chosen).
     * @param \stdClass $actor The acting user (capability checks run as them).
     * @param bool $manageall Whether the actor holds :manage_all.
     * @return \stdClass {quizid, cmid, count, quizname}
     * @throws \moodle_exception on any validation/permission failure.
     */
    public static function publish(int $draftid, int $courseid, \stdClass $actor,
            bool $manageall): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/gift/format.php');
        require_once($CFG->libdir . '/questionlib.php');

        $bundle = draft_manager::load_for_actor($draftid, $actor, $manageall);
        if ($bundle === null) {
            throw new \moodle_exception('review_notfound', 'local_sentientia_aiquiz');
        }
        $draft = $bundle->draft;

        if ($draft->status !== draft_manager::STATUS_APPROVED) {
            throw new \moodle_exception('push_err_notapproved', 'local_sentientia_aiquiz');
        }

        $pushable = array_values(array_filter($bundle->questions, function ($q) {
            return ($q->status === draft_manager::Q_STATUS_APPROVED
                    || $q->status === draft_manager::Q_STATUS_EDITED)
                && $q->qtype === 'multichoice';
        }));
        if (count($pushable) === 0) {
            throw new \moodle_exception('push_err_noquestions', 'local_sentientia_aiquiz');
        }

        $course = get_course($courseid);
        // ADR-031: the target course must sit in the actor's tenant. A
        // manager-archetype role at system context (every tenant admin)
        // satisfies course:manageactivities in EVERY course, and the target
        // can be any course id the reviewer posts, so the capability check
        // below cannot answer WHERE. Cross-tenant actors pass.
        self::require_course_in_scope($course, $actor);
        $coursecontext = \context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $coursecontext, $actor);

        // The 5.x course question bank: a shared mod_qbank instance.
        $qbankcm = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type(
            $course, true);
        if ($qbankcm === null) {
            throw new \moodle_exception('push_err_nobank', 'local_sentientia_aiquiz');
        }
        $bankcontext = $qbankcm->context;
        require_capability('moodle/question:add', $bankcontext, $actor);

        $transaction = $DB->start_delegated_transaction();
        try {
            $category = question_get_default_category($bankcontext->id, true);

            $questionids = self::import_gift_questions($draft, $pushable, $category,
                [$bankcontext], $course);
            if (count($questionids) !== count($pushable)) {
                throw new \moodle_exception('push_err_importcount',
                    'local_sentientia_aiquiz', '', (object) [
                        'expected' => count($pushable),
                        'actual'   => count($questionids),
                    ]);
            }

            $quizname = trim(format_string($draft->title)) !== ''
                ? $draft->title
                : get_string('push_default_quizname', 'local_sentientia_aiquiz', $draft->id);
            $moduleinfo = self::create_quiz_module($course, $quizname, $draft);

            $quiz = $DB->get_record('quiz', ['id' => $moduleinfo->instance], '*', MUST_EXIST);
            foreach ($questionids as $qid) {
                quiz_add_quiz_question($qid, $quiz);
            }
            // quiz_update_sumgrades() is hard-deprecated since 4.2 (MDL-76897).
            \mod_quiz\quiz_settings::create((int) $quiz->id)
                ->get_grade_calculator()
                ->recompute_quiz_sumgrades();

            draft_manager::mark_pushed($draftid, (int) $quiz->id);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return (object) [
            'quizid'   => (int) $quiz->id,
            'cmid'     => (int) $moduleinfo->coursemodule,
            'count'    => count($questionids),
            'quizname' => $quizname,
        ];
    }

    /**
     * ADR-031: refuse unless $course is in $actor's tenant.
     *
     * This guards a WRITE (a quiz pushed into the course, or a draft bound to
     * it as its push target), so it is stricter than
     * tenant::require_path_access(), which waves a course with no open_path
     * through as a readable legacy row:
     *
     *  - a cross-tenant actor passes;
     *  - an actor whose tenant does not resolve (scope_path() null) is
     *    refused outright, whatever the course;
     *  - a course with a NULL / empty open_path is refused for every scoped
     *    actor. It belongs to no tenant, so it cannot be shown to be in the
     *    actor's, and every tenant's catalogue lists it: a quiz pushed into it
     *    reaches all of them (wave-1 review S1, 2026-09-25);
     *  - otherwise the course must be the actor's tenant root or `/`-bounded
     *    below it (tenant::require_path_access()).
     *
     * Schema-portable: a vanilla course row has no open_path at all, and on
     * such a site only cross-tenant actors (site admins) can push.
     *
     * @param \stdClass $course A course record (get_course() / '*')
     * @param \stdClass $actor  A user record carrying id and open_path
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_course_in_scope(\stdClass $course, \stdClass $actor): void {
        $scope = \local_sentientia_platform\tenant::scope_path($actor);
        if ($scope === '') {
            return;
        }
        $path = (string) ($course->open_path ?? '');
        if ($scope === null || $path === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        \local_sentientia_platform\tenant::require_path_access($path, (int) $actor->id);
    }

    /**
     * ADR-031: WHERE fragment bounding a {course} query to the actor's tenant.
     *
     * '1=1' for a cross-tenant actor, the tenant's `/`-bounded subtree for a
     * scoped one, and '1=0' - nothing - for an actor whose tenant does not
     * resolve. Used by the generate and push course pickers, which listed
     * every course on the site. Both pick a PUSH TARGET, so legacy NULL-path
     * courses are left out for a scoped actor: require_course_in_scope()
     * refuses them, and a picker must not offer what the write will refuse.
     *
     * @param \stdClass $actor
     * @param string    $alias {course} alias, '' for none
     * @return array{0: string, 1: array}
     */
    public static function course_scope_sql(\stdClass $actor, string $alias = ''): array {
        $scope = \local_sentientia_platform\tenant::scope_path($actor);
        if ($scope === null) {
            return ['1=0', []];
        }
        if ($scope === '') {
            return ['1=1', []];
        }
        return \local_sentientia_platform\tenant::path_descendant_filter(
            $scope, $alias, 'open_path', 'aqcourse', false);
    }

    /**
     * Courses the actor may push a draft into: those where they hold
     * course:manageactivities, bounded to their tenant (ADR-031).
     *
     * @param \stdClass $actor
     * @param int       $limit
     * @return array<int, string> course id => fullname (unformatted)
     */
    public static function target_courses(\stdClass $actor, int $limit = 100): array {
        global $DB;
        $capable = get_user_capability_course('moodle/course:manageactivities',
            (int) $actor->id, false, 'fullname', 'fullname ASC', $limit);
        $ids = [];
        foreach ($capable ?: [] as $c) {
            if ((int) $c->id !== SITEID) {
                $ids[] = (int) $c->id;
            }
        }
        if (empty($ids)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'aqtc');
        [$tsql, $targs] = self::course_scope_sql($actor);
        $rows = $DB->get_records_select('course', "id {$insql} AND {$tsql}",
            $inparams + $targs, 'fullname ASC', 'id, fullname');
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = (string) $r->fullname;
        }
        return $out;
    }

    /**
     * Build GIFT text for the pushable questions and run it through the
     * qformat_gift importer into $category.
     *
     * @param \stdClass $draft
     * @param \stdClass[] $questions Pushable draft questions.
     * @param \stdClass $category Target question category.
     * @param \context[] $contexts
     * @param \stdClass $course
     * @return int[] Created question ids.
     * @throws \moodle_exception when the importer reports failure.
     */
    protected static function import_gift_questions(\stdClass $draft, array $questions,
            \stdClass $category, array $contexts, \stdClass $course): array {
        $gift = [];
        $n = 0;
        foreach ($questions as $q) {
            $n++;
            $options = json_decode((string) $q->qoptions_json, true);
            if (!is_array($options) || count($options) < 2) {
                throw new \moodle_exception('push_err_badoptions',
                    'local_sentientia_aiquiz', '', $n);
            }
            $correct = (int) $q->qanswer;   // Stringified 0-based index.
            if (!array_key_exists($correct, array_values($options))) {
                throw new \moodle_exception('push_err_badanswer',
                    'local_sentientia_aiquiz', '', $n);
            }

            $name = self::gift_escape("AIQuiz D{$draft->id} Q{$n}");
            $lines = ["::{$name}::" . self::gift_escape((string) $q->qtext) . ' {'];
            foreach (array_values($options) as $i => $opt) {
                $prefix = ($i === $correct) ? '=' : '~';
                $lines[] = $prefix . self::gift_escape((string) $opt);
            }
            $explanation = trim((string) ($q->qexplanation ?? ''));
            if ($explanation !== '') {
                $lines[] = '####' . self::gift_escape($explanation);
            }
            $lines[] = '}';
            $gift[] = implode("\n", $lines);
        }
        $gifttext = implode("\n\n", $gift) . "\n";

        // qformat reads from a file; use a per-request temp dir.
        $tempdir = make_request_directory();
        $tempfile = $tempdir . '/aiquiz-draft-' . $draft->id . '.gift';
        if (file_put_contents($tempfile, $gifttext) === false) {
            throw new \moodle_exception('push_err_tempfile', 'local_sentientia_aiquiz');
        }

        $qformat = new \qformat_gift();
        $qformat->setCategory($category);
        $qformat->setContexts($contexts);
        $qformat->setCourse($course);
        $qformat->setFilename($tempfile);
        $qformat->setRealfilename(basename($tempfile));
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);

        // The importer echoes progress HTML; a redirect-driven page (and the
        // unit tests) want it silent.
        ob_start();
        $ok = $qformat->importprocess();
        ob_end_clean();

        if (!$ok) {
            throw new \moodle_exception('push_err_import', 'local_sentientia_aiquiz');
        }
        return array_map('intval', $qformat->questionids);
    }

    /**
     * Create the quiz course module via add_moduleinfo() with defaults
     * mirroring mod/quiz/tests/generator/lib.php (the granular review
     * fields are folded into the review bitmasks by quiz_process_options()).
     *
     * @param \stdClass $course
     * @param string $quizname
     * @param \stdClass $draft
     * @return \stdClass The populated moduleinfo (->instance, ->coursemodule).
     */
    protected static function create_quiz_module(\stdClass $course, string $quizname,
            \stdClass $draft): \stdClass {
        global $DB;

        $intro = get_string('push_quiz_intro', 'local_sentientia_aiquiz', (object) [
            'draftid' => $draft->id,
            'model'   => $draft->model,
        ]);

        $moduleinfo = (object) [
            'modulename'           => 'quiz',
            // add_moduleinfo() consumes the mdl_modules id directly (the
            // mod_form normally injects it) — without it course_create_module
            // inserts course_modules.module = NULL and the DML layer throws.
            'module'               => (int) $DB->get_field('modules', 'id',
                                        ['name' => 'quiz'], MUST_EXIST),
            'course'               => $course->id,
            'section'              => 0,
            'visible'              => 0,   // Hidden until the trainer reviews placement.
            'visibleoncoursepage'  => 1,
            'cmidnumber'           => '',
            'name'                 => $quizname,
            'introeditor'          => ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => 0],
            'showdescription'      => 0,
            // Quiz timing/behaviour defaults (mod/quiz/tests/generator/lib.php).
            'timeopen'             => 0,
            'timeclose'            => 0,
            'timelimit'            => 0,
            'overduehandling'      => 'autosubmit',
            'graceperiod'          => 0,
            'preferredbehaviour'   => 'deferredfeedback',
            'canredoquestions'     => 0,
            'attempts'             => 0,
            'attemptonlast'        => 0,
            'grademethod'          => QUIZ_GRADEHIGHEST,
            'decimalpoints'        => 2,
            'questiondecimalpoints' => -1,
            'shuffleanswers'       => 1,
            'questionsperpage'     => 1,
            'navmethod'            => 'free',
            'grade'                => self::DEFAULT_GRADE,
            'quizpassword'         => '',
            'subnet'               => '',
            'browsersecurity'      => '-',
            'delay1'               => 0,
            'delay2'               => 0,
            'showuserpicture'      => 0,
            'showblocks'           => 0,
            'completionattemptsexhausted' => 0,
            'completionpass'       => 0,
            // Granular review flags (folded by quiz_process_options()).
            'attemptduring' => 1, 'correctnessduring' => 1, 'maxmarksduring' => 1,
            'marksduring' => 1, 'specificfeedbackduring' => 1,
            'generalfeedbackduring' => 1, 'rightanswerduring' => 1,
            'overallfeedbackduring' => 0,
            'attemptimmediately' => 1, 'correctnessimmediately' => 1,
            'maxmarksimmediately' => 1, 'marksimmediately' => 1,
            'specificfeedbackimmediately' => 1, 'generalfeedbackimmediately' => 1,
            'rightanswerimmediately' => 1, 'overallfeedbackimmediately' => 1,
            'attemptopen' => 1, 'correctnessopen' => 1, 'maxmarksopen' => 1,
            'marksopen' => 1, 'specificfeedbackopen' => 1,
            'generalfeedbackopen' => 1, 'rightansweropen' => 1,
            'overallfeedbackopen' => 1,
            'attemptclosed' => 1, 'correctnessclosed' => 1, 'maxmarksclosed' => 1,
            'marksclosed' => 1, 'specificfeedbackclosed' => 1,
            'generalfeedbackclosed' => 1, 'rightanswerclosed' => 1,
            'overallfeedbackclosed' => 1,
            // Safe Exam Browser access rule expects its selector present.
            'seb_requiresafeexambrowser' => 0,
        ];

        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Escape GIFT-special characters in question text.
     *
     * Order matters: backslash first, then the control characters
     * (~ = # { } :) that would otherwise change the parse.
     *
     * @param string $text
     * @return string
     */
    public static function gift_escape(string $text): string {
        $text = str_replace('\\', '\\\\', $text);
        foreach (['~', '=', '#', '{', '}', ':'] as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        // GIFT treats a blank line as end-of-question; collapse them.
        return (string) preg_replace('/\n\s*\n/', "\n", $text);
    }
}
