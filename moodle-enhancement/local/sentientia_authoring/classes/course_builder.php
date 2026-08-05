<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * The course builder — the last ADR-028 gate-#3 stub. Turns an APPROVED
 * authoring draft into a REAL Moodle course:
 *
 *   - a new HIDDEN course (topics format) in the chosen category,
 *   - Section 1: one mod_book "Course content" with a chapter per
 *     approved/edited card (heading → chapter title; body + flip-back
 *     callout + narration transcript → chapter content),
 *   - Section 2: a mastery quiz (only when approved questions exist) —
 *     questions land in the course's default shared question bank via
 *     the GIFT import pipeline (same proven pattern as
 *     local_sentientia_aiquiz\quiz_publisher, Phase G.4), per-answer
 *     feedback preserved, per-question points as slot maxmark, and
 *     gradepass derived from the draft's mastery_score (Airpay default
 *     70%, per-customer configurable).
 *
 * The whole build runs in one delegated transaction and finishes with
 * draft_manager::mark_published($draftid, $courseid) — a failure mid-way
 * never leaves a half-built course claiming to be published.
 *
 * API grounding (local Moodle 5.1.3 source): create_course()
 * course/lib.php:1907; add_moduleinfo() needs the mdl_modules id
 * supplied; book chapters are direct {book_chapters} inserts (the
 * mod_book generator's own pattern) + a book revision bump;
 * quiz_update_sumgrades() is hard-deprecated → grade_calculator.
 *
 * @package local_sentientia_authoring
 */
class course_builder {

    /** Maximum grade of the mastery quiz. */
    public const QUIZ_GRADE = 10.0;

    /**
     * Build a real course from an approved draft.
     *
     * @param int $draftid
     * @param \stdClass $actor Acting user (capability checks run as them).
     * @param bool $manageall Whether the actor holds :manage_all.
     * @param int $categoryid Target course category (0 = default category).
     * @return \stdClass {courseid, shortname, bookcmid, quizid, cardcount, questioncount}
     * @throws \moodle_exception on any validation/permission failure.
     */
    public static function build(int $draftid, \stdClass $actor, bool $manageall,
            int $categoryid = 0): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/gift/format.php');
        require_once($CFG->libdir . '/questionlib.php');

        $bundle = draft_manager::load_for_actor($draftid, $actor, $manageall);
        if ($bundle === null) {
            throw new \moodle_exception('err_draft_not_found', 'local_sentientia_authoring');
        }
        $draft = $bundle->draft;
        if ($draft->status !== draft_manager::STATUS_APPROVED) {
            throw new \moodle_exception('err_publish_not_approved', 'local_sentientia_authoring');
        }

        $cards = array_values(array_filter($bundle->cards, function ($c) {
            return $c->status === draft_manager::ITEM_APPROVED
                || $c->status === draft_manager::ITEM_EDITED;
        }));
        if (count($cards) === 0) {
            throw new \moodle_exception('err_publish_nocards', 'local_sentientia_authoring');
        }
        $questions = array_values(array_filter($bundle->questions, function ($q) {
            return ($q->status === draft_manager::ITEM_APPROVED
                    || $q->status === draft_manager::ITEM_EDITED)
                && in_array($q->qtype, ['multichoice', 'mrq', 'match'], true);
        }));

        if ($categoryid <= 0) {
            $categoryid = (int) $DB->get_field_sql(
                'SELECT MIN(id) FROM {course_categories} WHERE visible = 1');
        }
        $catcontext = \context_coursecat::instance($categoryid);
        require_capability('moodle/course:create', $catcontext, $actor);

        $transaction = $DB->start_delegated_transaction();
        try {
            $course = self::create_hidden_course($draft, $categoryid);
            $bookinfo = self::create_book($course, $draft, $cards);

            $quizid = 0;
            if (count($questions) > 0) {
                $quizid = self::create_mastery_quiz($course, $draft, $questions, $actor);
            }

            draft_manager::mark_published($draftid, (int) $course->id);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return (object) [
            'courseid'      => (int) $course->id,
            'shortname'     => $course->shortname,
            'bookcmid'      => (int) $bookinfo->coursemodule,
            'quizid'        => $quizid,
            'cardcount'     => count($cards),
            'questioncount' => count($questions),
        ];
    }

    /**
     * Create the hidden topics-format course shell.
     *
     * @param \stdClass $draft
     * @param int $categoryid
     * @return \stdClass Course record.
     */
    protected static function create_hidden_course(\stdClass $draft, int $categoryid): \stdClass {
        global $DB;

        // Unique, human-scannable shortname; suffix on collision.
        $base = 'AIC' . $draft->id;
        $shortname = $base;
        $i = 1;
        while ($DB->record_exists('course', ['shortname' => $shortname])) {
            $shortname = $base . '-' . (++$i);
        }

        $summary = get_string('publish_course_summary', 'local_sentientia_authoring', (object) [
            'draftid' => $draft->id,
            'model'   => $draft->model,
        ]);

        return create_course((object) [
            'fullname'      => $draft->title,
            'shortname'     => $shortname,
            'category'      => $categoryid,
            'visible'       => 0,   // Hidden until the author reviews it.
            'format'        => 'topics',
            'numsections'   => 2,
            'summary'       => $summary,
            'summaryformat' => FORMAT_HTML,
            'lang'          => ($draft->targetlang === 'hi') ? 'hi' : '',
        ]);
    }

    /**
     * One mod_book in section 1, a chapter per approved card.
     *
     * @param \stdClass $course
     * @param \stdClass $draft
     * @param \stdClass[] $cards
     * @return \stdClass moduleinfo (->coursemodule, ->instance)
     */
    protected static function create_book(\stdClass $course, \stdClass $draft, array $cards): \stdClass {
        global $DB;

        $moduleinfo = add_moduleinfo((object) [
            'modulename'          => 'book',
            'module'              => (int) $DB->get_field('modules', 'id',
                                        ['name' => 'book'], MUST_EXIST),
            'course'              => $course->id,
            'section'             => 1,
            'visible'             => 1,
            'visibleoncoursepage' => 1,
            'cmidnumber'          => '',
            'name'                => get_string('publish_book_name', 'local_sentientia_authoring'),
            'introeditor'         => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'numbering'           => 1,   // BOOK_NUM_NUMBERS.
            'navstyle'            => 1,
            'customtitles'        => 0,
        ], $course);

        $now = time();
        $pagenum = 0;
        foreach ($cards as $card) {
            $content = self::card_html($card);
            $chapter = (object) [
                'bookid'        => $moduleinfo->instance,
                'pagenum'       => ++$pagenum,
                'subchapter'    => 0,
                'title'         => trim($card->heading) !== ''
                                    ? $card->heading
                                    : get_string('publish_card_untitled',
                                        'local_sentientia_authoring', $pagenum),
                'content'       => $content,
                'contentformat' => FORMAT_HTML,
                'hidden'        => 0,
                'timecreated'   => $now,
                'timemodified'  => $now,
            ];
            $DB->insert_record('book_chapters', $chapter);
        }
        // mod_book caches render state on revision; bump like the core UI does.
        $DB->set_field('book', 'revision', 1, ['id' => $moduleinfo->instance]);

        return $moduleinfo;
    }

    /**
     * Chapter HTML from a card: body paragraphs, flip-back as a callout,
     * narration as a collapsible transcript. Card text is author-reviewed
     * plain text from the studio — escaped and paragraphised here.
     *
     * @param \stdClass $card
     * @return string
     */
    public static function card_html(\stdClass $card): string {
        $html = text_to_html((string) $card->body, false, false, true);
        $flip = trim((string) ($card->flip_back ?? ''));
        if ($flip !== '') {
            $html .= \html_writer::div(
                \html_writer::tag('strong',
                    get_string('publish_card_keypoint', 'local_sentientia_authoring'))
                . ' ' . s($flip),
                'alert alert-info mt-3');
        }
        $narration = trim((string) ($card->narration ?? ''));
        if ($narration !== '') {
            $html .= \html_writer::tag('details',
                \html_writer::tag('summary',
                    get_string('publish_card_transcript', 'local_sentientia_authoring'))
                . text_to_html($narration, false, false, true),
                ['class' => 'mt-3']);
        }
        return $html;
    }

    /**
     * The mastery quiz in section 2 — GIFT-imported bank questions with
     * per-answer feedback, per-question points as slot maxmark, gradepass
     * from the draft's mastery_score.
     *
     * @param \stdClass $course
     * @param \stdClass $draft
     * @param \stdClass[] $questions
     * @param \stdClass $actor
     * @return int quiz id
     */
    protected static function create_mastery_quiz(\stdClass $course, \stdClass $draft,
            array $questions, \stdClass $actor): int {
        global $DB;

        $qbankcm = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type(
            $course, true);
        if ($qbankcm === null) {
            throw new \moodle_exception('err_publish_nobank', 'local_sentientia_authoring');
        }
        require_capability('moodle/question:add', $qbankcm->context, $actor);
        $category = question_get_default_category($qbankcm->context->id, true);

        $questionids = self::import_gift($draft, $questions, $category,
            [$qbankcm->context], $course);
        if (count($questionids) !== count($questions)) {
            throw new \moodle_exception('err_publish_importcount',
                'local_sentientia_authoring', '', (object) [
                    'expected' => count($questions),
                    'actual'   => count($questionids),
                ]);
        }

        $masterypct = max(0, min(100, (int) $draft->mastery_score));
        $moduleinfo = add_moduleinfo((object) [
            'modulename'          => 'quiz',
            'module'              => (int) $DB->get_field('modules', 'id',
                                        ['name' => 'quiz'], MUST_EXIST),
            'course'              => $course->id,
            'section'             => 2,
            'visible'             => 1,
            'visibleoncoursepage' => 1,
            'cmidnumber'          => '',
            'name'                => get_string('publish_quiz_name',
                                        'local_sentientia_authoring', $masterypct),
            'introeditor'         => ['text' => get_string('publish_quiz_intro',
                                        'local_sentientia_authoring', $masterypct),
                                      'format' => FORMAT_HTML, 'itemid' => 0],
            'showdescription'     => 0,
            'timeopen' => 0, 'timeclose' => 0, 'timelimit' => 0,
            'overduehandling' => 'autosubmit', 'graceperiod' => 0,
            'preferredbehaviour' => 'deferredfeedback', 'canredoquestions' => 0,
            'attempts' => 0, 'attemptonlast' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST,
            'decimalpoints' => 2, 'questiondecimalpoints' => -1,
            'shuffleanswers' => 1, 'questionsperpage' => 1, 'navmethod' => 'free',
            'grade' => self::QUIZ_GRADE,
            'gradepass' => round(self::QUIZ_GRADE * $masterypct / 100, 2),
            'quizpassword' => '', 'subnet' => '', 'browsersecurity' => '-',
            'delay1' => 0, 'delay2' => 0, 'showuserpicture' => 0, 'showblocks' => 0,
            'completionattemptsexhausted' => 0, 'completionpass' => 0,
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
            'seb_requiresafeexambrowser' => 0,
        ], $course);

        $quiz = $DB->get_record('quiz', ['id' => $moduleinfo->instance], '*', MUST_EXIST);
        foreach ($questionids as $i => $qid) {
            $points = max(1, (int) ($questions[$i]->points ?? 1));
            quiz_add_quiz_question($qid, $quiz, 0, (float) $points);
        }
        \mod_quiz\quiz_settings::create((int) $quiz->id)
            ->get_grade_calculator()
            ->recompute_quiz_sumgrades();

        return (int) $quiz->id;
    }

    /**
     * GIFT text for the draft questions (per-answer feedback preserved),
     * imported via qformat_gift. Same pattern as aiquiz's quiz_publisher —
     * kept plugin-local because the shapes differ (feedback fields, points)
     * and neither optional plugin may depend on the other.
     *
     * @param \stdClass $draft
     * @param \stdClass[] $questions
     * @param \stdClass $category
     * @param \context[] $contexts
     * @param \stdClass $course
     * @return int[] Created question ids (import order = input order).
     */
    protected static function import_gift(\stdClass $draft, array $questions,
            \stdClass $category, array $contexts, \stdClass $course): array {
        $gift = [];
        $n = 0;
        foreach ($questions as $q) {
            $n++;
            $gift[] = self::question_gift($draft, $q, $n);
        }
        $gifttext = implode("\n\n", $gift) . "\n";

        $tempdir = make_request_directory();
        $tempfile = $tempdir . '/authoring-draft-' . $draft->id . '.gift';
        if (file_put_contents($tempfile, $gifttext) === false) {
            throw new \moodle_exception('err_publish_tempfile', 'local_sentientia_authoring');
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

        ob_start();
        $ok = $qformat->importprocess();
        ob_end_clean();

        if (!$ok) {
            throw new \moodle_exception('err_publish_import', 'local_sentientia_authoring');
        }
        return array_map('intval', $qformat->questionids);
    }

    /**
     * GIFT for ONE question, dispatched by the authoring qtype
     * (question_type.php: multichoice | mrq | match).
     *
     *  - multichoice: '=' correct / '~' wrong, per-answer feedback via '#'.
     *  - mrq:         all-'~' with percentage weights — each correct gets
     *                 %100/ncorrect%, each wrong %-100% (GIFT multi-response
     *                 convention); per-answer feedback via '#'.
     *  - match:       '=left -> right' pairs (GIFT matching has no
     *                 per-answer feedback — only the global '####').
     *
     * @param \stdClass $draft
     * @param \stdClass $q
     * @param int $n 1-based position (error messages).
     * @return string
     */
    public static function question_gift(\stdClass $draft, \stdClass $q, int $n): string {
        $name = self::gift_escape("Authoring D{$draft->id} Q{$n}");
        $lines = ["::{$name}::" . self::gift_escape((string) $q->qtext) . ' {'];

        $fbcorrect = trim((string) ($q->qfeedback_correct ?? ''));
        $fbwrong = trim((string) ($q->qfeedback_incorrect ?? ''));
        $decoded = json_decode((string) $q->qoptions_json, true);

        if ($q->qtype === 'match') {
            if (!is_array($decoded) || count($decoded) < 2) {
                throw new \moodle_exception('err_publish_badoptions',
                    'local_sentientia_authoring', '', $n);
            }
            foreach ($decoded as $pair) {
                if (!is_array($pair) || !isset($pair['left'], $pair['right'])) {
                    throw new \moodle_exception('err_publish_badoptions',
                        'local_sentientia_authoring', '', $n);
                }
                $lines[] = '=' . self::gift_escape((string) $pair['left'])
                    . ' -> ' . self::gift_escape((string) $pair['right']);
            }
        } else {
            $options = is_array($decoded) ? array_values($decoded) : [];
            if (count($options) < 2) {
                throw new \moodle_exception('err_publish_badoptions',
                    'local_sentientia_authoring', '', $n);
            }
            if ($q->qtype === 'mrq') {
                $correct = json_decode((string) $q->qanswer, true);
                if (!is_array($correct) || count($correct) < 1) {
                    throw new \moodle_exception('err_publish_badanswer',
                        'local_sentientia_authoring', '', $n);
                }
                $correct = array_map('intval', $correct);
                foreach ($correct as $idx) {
                    if (!array_key_exists($idx, $options)) {
                        throw new \moodle_exception('err_publish_badanswer',
                            'local_sentientia_authoring', '', $n);
                    }
                }
                $weight = self::mrq_weight(count($correct));
                foreach ($options as $i => $opt) {
                    $iscorrect = in_array($i, $correct, true);
                    $line = '~%' . ($iscorrect ? $weight : '-100') . '%'
                        . self::gift_escape((string) $opt);
                    $fb = $iscorrect ? $fbcorrect : $fbwrong;
                    if ($fb !== '') {
                        $line .= '#' . self::gift_escape($fb);
                    }
                    $lines[] = $line;
                }
            } else { // Single-answer multichoice.
                $correct = (int) $q->qanswer;
                if (!array_key_exists($correct, $options)) {
                    throw new \moodle_exception('err_publish_badanswer',
                        'local_sentientia_authoring', '', $n);
                }
                foreach ($options as $i => $opt) {
                    $line = ($i === $correct ? '=' : '~')
                        . self::gift_escape((string) $opt);
                    $fb = ($i === $correct) ? $fbcorrect : $fbwrong;
                    if ($fb !== '') {
                        $line .= '#' . self::gift_escape($fb);
                    }
                    $lines[] = $line;
                }
            }
        }

        $explanation = trim((string) ($q->qexplanation ?? ''));
        if ($explanation !== '') {
            $lines[] = '####' . self::gift_escape($explanation);
        }
        $lines[] = '}';
        return implode("\n", $lines);
    }

    /**
     * The per-correct-answer GIFT weight for an MRQ with $n correct options.
     *
     * Moodle's question import with setMatchgrades('error') accepts ONLY the
     * canonical fraction list (question/engine/bank levels) — 33.33 fails
     * where 33.33333 passes — so the values are looked up, not computed.
     *
     * @param int $n Number of correct options (1..10).
     * @return string Weight literal for the %...% GIFT syntax.
     * @throws \moodle_exception when $n is outside the canonical table.
     */
    public static function mrq_weight(int $n): string {
        $table = [
            1 => '100', 2 => '50', 3 => '33.33333', 4 => '25', 5 => '20',
            6 => '16.66667', 7 => '14.28571', 8 => '12.5', 9 => '11.11111',
            10 => '10',
        ];
        if (!isset($table[$n])) {
            throw new \moodle_exception('err_publish_badanswer',
                'local_sentientia_authoring', '', $n);
        }
        return $table[$n];
    }

    /**
     * Escape GIFT control characters (backslash first).
     *
     * @param string $text
     * @return string
     */
    public static function gift_escape(string $text): string {
        $text = str_replace('\\', '\\\\', $text);
        foreach (['~', '=', '#', '{', '}', ':'] as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return (string) preg_replace('/\n\s*\n/', "\n", $text);
    }
}
