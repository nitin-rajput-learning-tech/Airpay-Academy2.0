<?php
// This file is part of Sentientia LMS.

/**
 * Erasure completeness ("Option A", 2026-11-13, ADR-035): the supplementary provider recovers what stock
 * tool_dataprivacy erasure misses because of two core defects, with no core edit (complete as ADR-035 defines
 * it, not literally: see its "What still remains").
 *
 * The defects (see \local_sentientia_privacy\local\erasure_completeness): core's contextlist iterator stops at
 * the first key hole, so the stock approval drops contexts; and core_course never discovers course_completions
 * rows in courses without (or with untouched) criteria. A4 (2026-11-13) left 17,219 rows behind that way.
 *
 * Every integration test here runs the REAL stock path (privacy manager discovery, the tool_dataprivacy
 * approval, the manager delete), and first ASSERTS that the hole it relies on exists: a fixture that shows no
 * hole proves nothing, so it fails rather than skips. Which context the stock path drops depends on the
 * database's row order, so no test names it in advance: each test computes it from the stock list.
 *
 * @package    local_sentientia_privacy
 * @category   test
 * @group      privacy_erasure
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\contextlist_base;
use core_privacy\local\request\core_user_data_provider;
use core_privacy\local\request\helper;
use local_sentientia_platform\feature_flags;
use local_sentientia_privacy\local\erasure_completeness;
use local_sentientia_privacy\local\forced_contextlist;
use local_sentientia_privacy\privacy\provider;
use tool_dataprivacy\api;

/**
 * @group privacy_erasure
 * @covers \local_sentientia_privacy\local\erasure_completeness
 * @covers \local_sentientia_privacy\privacy\provider
 * @covers \local_sentientia_privacy\event\erasure_supplemented
 */
final class erasure_completeness_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/completionlib.php');
        \core_privacy\local\request\writer::reset();
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Switch the two flags at global scope (the only scope the provider reads).
     */
    private function set_flags(bool $truncation, bool $completion): void {
        feature_flags::set(provider::FLAG_TRUNCATION, 0, $truncation, (int) get_admin()->id, 'erasure_completeness_test');
        feature_flags::set(provider::FLAG_COMPLETION, 0, $completion, (int) get_admin()->id, 'erasure_completeness_test');
    }

    /**
     * Insert one logstore_standard_log row (PHPUnit disables the log stores, so the rows go in directly; tool_log
     * discovery reads every installed store regardless).
     */
    private function log(int $userid, \context $context, int $courseid, ?int $relateduserid = null): int {
        global $DB;
        return (int) $DB->insert_record('logstore_standard_log', (object) [
            'eventname' => '\\core\\event\\course_viewed', 'component' => 'core', 'action' => 'viewed',
            'target' => 'course', 'crud' => 'r', 'edulevel' => 2, 'contextid' => $context->id,
            'contextlevel' => $context->contextlevel, 'contextinstanceid' => $context->instanceid,
            'userid' => $userid, 'courseid' => $courseid, 'relateduserid' => $relateduserid, 'realuserid' => null,
            'anonymous' => 0, 'other' => null, 'timecreated' => time(), 'origin' => 'cli', 'ip' => null,
        ]);
    }

    /**
     * The tool_log hole fixture: the subject's rows grouped by context (2 in x, then 2 in y), somebody else's
     * rows in both, and one row where the subject is only relateduserid (kept by tool_log's delete by design).
     * logstore_standard's discovery has no DISTINCT, so the list is [x,x,..,y,y] in any grouped row order:
     * array_unique keeps {0: x, 2: y} and the iterator stops after x (or the mirror image).
     *
     * @return \context[] The two course contexts.
     */
    private function tool_log_fixture(\stdClass $subject, \stdClass $other): array {
        $gen = $this->getDataGenerator();
        $c1 = $gen->create_course();
        $c2 = $gen->create_course();
        $x = \context_course::instance($c1->id);
        $y = \context_course::instance($c2->id);
        $this->log($subject->id, $x, $c1->id);
        $this->log($subject->id, $x, $c1->id);
        $this->log($subject->id, $y, $c2->id);
        $this->log($subject->id, $y, $c2->id);
        $this->log($other->id, $x, $c1->id);
        $this->log($other->id, $y, $c2->id);
        // In x, the first group: whichever index the database reads first, the subject's list stays grouped.
        $this->log($other->id, $x, $c1->id, $subject->id);
        return [$x, $y];
    }

    /**
     * One component's list exactly as the privacy manager builds it (manager.php:237-248), written out here
     * independently of erasure_completeness::discover().
     */
    private function stock_list(int $userid, string $component): contextlist {
        $list = \core_privacy\manager::component_class_callback($component, core_user_data_provider::class,
            'get_contexts_for_userid', [$userid]);
        if ($list === null) {
            $list = new contextlist();
        }
        $list->set_component($component);
        return helper::add_shared_contexts_to_contextlist_for($userid, $list);
    }

    /**
     * The ids core's foreach yields (the real iterator, as the stock approval walks it).
     *
     * @return int[]
     */
    private function iterated(contextlist_base $list): array {
        $ids = [];
        foreach ($list as $context) {
            $ids[] = (int) $context->id;
        }
        return $ids;
    }

    /**
     * Assert the hole exists in a component's stock list and return [iterated, dropped].
     *
     * @return array{0: int[], 1: int[]}
     */
    private function assert_hole(int $userid, string $component): array {
        $list = $this->stock_list($userid, $component);
        $all = array_values(array_map('intval', $list->get_contextids()));
        $iterated = $this->iterated($list);
        $this->assertLessThan(count($all), count($iterated),
            "Precondition failed: the stock {$component} list has no key hole, so this fixture proves nothing.");
        return [$iterated, array_values(array_diff($all, $iterated))];
    }

    /**
     * The stock erasure path for one subject, as process_data_request_task runs it (discovery, approval,
     * delete with a fresh manager).
     */
    private function run_stock_delete(\stdClass $user, ?\core_privacy\manager_observer $observer = null): void {
        $manager = new \core_privacy\manager();
        if ($observer) {
            $manager->set_observer($observer);
        }
        $collection = $manager->get_contexts_for_userid((int) $user->id);
        $approved = api::get_approved_contextlist_collection_for_collection($collection, $user,
            api::DATAREQUEST_TYPE_DELETE);
        $manager = new \core_privacy\manager();
        if ($observer) {
            $manager->set_observer($observer);
        }
        $manager->delete_data_for_user($approved);
    }

    /**
     * The full tool_dataprivacy lifecycle: request, approval, the real adhoc task.
     *
     * @return int The request id.
     */
    private function lifecycle(\stdClass $user): int {
        $request = api::create_data_request($user->id, api::DATAREQUEST_TYPE_DELETE);
        ob_start();
        api::approve_data_request($request->get('id'));
        $this->runAdhocTasks('\\tool_dataprivacy\\task\\process_data_request_task');
        ob_end_clean();
        return (int) $request->get('id');
    }

    /**
     * Rows of one user in logstore_standard_log, optionally in one context.
     */
    private function logrows(int $userid, ?int $contextid = null, string $field = 'userid'): int {
        global $DB;
        $params = [$field => $userid];
        if ($contextid !== null) {
            $params['contextid'] = $contextid;
        }
        return $DB->count_records('logstore_standard_log', $params);
    }

    /**
     * A purpose and category set as the default for a context level (copied from tool_dataprivacy's
     * expired_contexts_test::create_and_set_purpose_for_contextlevel()).
     */
    private function purpose_for_level(string $retention, int $contextlevel): \tool_dataprivacy\purpose {
        $purpose = new \tool_dataprivacy\purpose(0, (object) [
            'name' => 'Erasure test purpose ' . $contextlevel,
            'retentionperiod' => $retention,
            'lawfulbases' => 'gdpr_art_6_1_a',
        ]);
        $purpose->create();
        $category = new \tool_dataprivacy\category(0, (object) ['name' => 'Erasure test category ' . $contextlevel]);
        $category->create();
        if ($contextlevel <= CONTEXT_USER) {
            api::set_contextlevel((object) [
                'purposeid' => $purpose->get('id'),
                'categoryid' => $category->get('id'),
                'contextlevel' => $contextlevel,
            ]);
        } else {
            [$purposevar, ] = \tool_dataprivacy\data_registry::var_names_from_context(
                \context_helper::get_class_for_level($contextlevel));
            set_config($purposevar, $purpose->get('id'), 'tool_dataprivacy');
        }
        return $purpose;
    }

    // ── (0) Unit layer: forced holes, no database row order involved ────

    public function test_analyse_reads_the_keys_exactly_like_the_iterator(): void {
        // [10, 10, 20] is stored as {0: 10, 2: 20}: the iterator stops after 10.
        $a = erasure_completeness::analyse((new forced_contextlist())->force([10, 10, 20]));
        $this->assertSame([10, 20], $a['full']);
        $this->assertSame([10], $a['iterated']);
        $this->assertSame([20], $a['missed']);

        // A later duplicate only cuts what follows it.
        $a = erasure_completeness::analyse((new forced_contextlist())->force([10, 20, 20, 30, 40]));
        $this->assertSame([10, 20], $a['iterated']);
        $this->assertSame([30, 40], $a['missed']);

        // No duplicate, no hole.
        $a = erasure_completeness::analyse((new forced_contextlist())->force([10, 20, 30]));
        $this->assertSame([10, 20, 30], $a['iterated']);
        $this->assertSame([], $a['missed']);

        // Against core's real iterator, on contexts that exist.
        $front = (int) \context_course::instance(SITEID)->id;
        $list = (new forced_contextlist())->force([SYSCONTEXTID, SYSCONTEXTID, $front]);
        $this->assertSame($this->iterated($list), erasure_completeness::analyse($list)['iterated']);
        $this->assertSame([$front], erasure_completeness::analyse($list)['missed']);

        // dense() is the only shape that survives core's set_contextids() without a hole.
        $keyed = array_intersect([10, 20, 30, 40], [30, 40]);
        $this->assertSame([2, 3], array_keys($keyed));
        $this->assertSame([30, 40], erasure_completeness::dense($keyed));
        $this->assertSame([5, 7], erasure_completeness::dense(['5', 5, 7, '7']));
    }

    public function test_every_list_the_class_builds_iterates_fully(): void {
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $a = \context_course::instance($gen->create_course()->id);
        $b = \context_course::instance($gen->create_course()->id);
        $this->set_flags(true, false);

        // tool_log reports [a, a, SYS, b]: stored {0: a, 2: SYS, 3: b}, so SYS and b are what stock drops.
        $captured = [];
        erasure_completeness::set_test_callback(
            function(string $component, string $method, array $params, callable $real) use ($a, $b, &$captured) {
                if ($component === 'tool_log' && $method === 'get_contexts_for_userid') {
                    return (new forced_contextlist())->force([$a->id, $a->id, SYSCONTEXTID, $b->id]);
                }
                if ($method === 'delete_data_for_user') {
                    $captured[$component] = $params[0];
                }
                return $real();
            });

        $own = provider::get_contexts_for_userid((int) $subject->id);
        $ownids = array_values(array_map('intval', $own->get_contextids()));
        $this->assertCount(count($ownids), $this->iterated($own), 'Our own list must never have a key hole.');
        sort($ownids);
        $expected = [(int) SYSCONTEXTID, (int) $b->id];
        sort($expected);
        $this->assertSame($expected, $ownids);

        $result = erasure_completeness::apply_delete($subject, $own->get_contextids(), erasure_completeness::PATH_CLI,
            true, false);
        $this->assertSame('cached', $result['plan']);
        $this->assertArrayHasKey('tool_log', $captured);
        foreach ($captured as $component => $list) {
            $this->assertInstanceOf(approved_contextlist::class, $list);
            $this->assertCount(count($list), $this->iterated($list), "The {$component} list has a key hole.");
        }
        $toolog = array_values(array_map('intval', $captured['tool_log']->get_contextids()));
        sort($toolog);
        $this->assertSame($expected, $toolog);
        $this->assertSame(2, $result['components']['tool_log']['contexts']);
    }

    // ── (1) Canary, flags OFF ───────────────────────────────────────────

    public function test_flags_off_stock_erasure_leaves_the_dropped_context_behind(): void {
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        $this->tool_log_fixture($subject, $other);
        [$iterated, $dropped] = $this->assert_hole((int) $subject->id, 'tool_log');
        $this->assertCount(1, $iterated);
        $this->assertCount(1, $dropped);

        // Flags OFF (the registered default): our list is exactly the system context, as in 1.0.3.
        $own = provider::get_contexts_for_userid((int) $subject->id);
        $this->assertSame([(int) SYSCONTEXTID], array_values(array_map('intval', $own->get_contextids())));

        $this->run_stock_delete($subject);

        $this->assertSame(0, $this->logrows((int) $subject->id, $iterated[0]));
        $this->assertSame(2, $this->logrows((int) $subject->id, $dropped[0]),
            'Canary: the stock path no longer leaves the dropped context behind. If core is fixed, retire the '
            . 'truncation part of erasure_completeness.');
    }

    // ── (2) Flags ON, the full request lifecycle ────────────────────────

    public function test_flags_on_full_lifecycle_erases_the_dropped_context(): void {
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();
        $events = $this->redirectEvents();

        // Flags OFF run, on an identical fixture, for the message baseline.
        $control = $gen->create_user();
        $controlother = $gen->create_user();
        $this->tool_log_fixture($control, $controlother);
        [, $controldropped] = $this->assert_hole((int) $control->id, 'tool_log');
        $this->lifecycle($control);
        $offmessages = $messages->count();
        $offemails = $emails->count();
        $messages->clear();
        $emails->clear();
        $this->assertSame(2, $this->logrows((int) $control->id, $controldropped[0]));
        $offevents = array_filter($events->get_events(),
            fn($e) => $e instanceof \local_sentientia_privacy\event\erasure_supplemented);
        $this->assertCount(0, $offevents);
        $events->clear();

        // Flags ON.
        $this->set_flags(true, true);
        $subject = $gen->create_user();
        $other = $gen->create_user();
        [$x, $y] = $this->tool_log_fixture($subject, $other);
        [, $dropped] = $this->assert_hole((int) $subject->id, 'tool_log');
        $otherbefore = $this->logrows((int) $other->id);
        $relatedbefore = $this->logrows((int) $subject->id, null, 'relateduserid');
        $this->assertSame(1, $relatedbefore);

        $requestid = $this->lifecycle($subject);

        $this->assertSame(0, $this->logrows((int) $subject->id), 'Every subject userid row must be gone.');
        $this->assertSame($otherbefore, $this->logrows((int) $other->id), 'Somebody else\'s rows must stay.');
        $this->assertSame($relatedbefore, $this->logrows((int) $subject->id, null, 'relateduserid'),
            'relateduserid-only rows are kept by tool_log\'s delete by design.');
        $this->assertEquals(api::DATAREQUEST_STATUS_DELETED, api::get_request($requestid)->get('status'));
        $this->assertSame($offmessages, $messages->count(), 'The supplement must not send any message.');
        $this->assertSame($offemails, $emails->count(), 'The supplement must not send any e-mail.');

        $fired = array_values(array_filter($events->get_events(),
            fn($e) => $e instanceof \local_sentientia_privacy\event\erasure_supplemented));
        $this->assertCount(1, $fired);
        $event = $fired[0];
        $this->assertSame($requestid, (int) $event->objectid);
        $this->assertEmpty($event->relateduserid, 'The record must not become the subject\'s own data.');
        $this->assertSame('request', $event->other['path']);
        $this->assertSame('cached', $event->other['plan']);
        $this->assertSame(1, $event->other['components']['tool_log']['contexts']);
        $this->assertSame(0, $event->other['failures']);
        $this->assertTrue($event->other['flags']['truncation']);
        $this->assertStringContainsString("data request with id '{$requestid}'", $event->get_description());
        $this->assertNotEmpty(\local_sentientia_privacy\event\erasure_supplemented::get_name());
        unset($x, $y, $dropped);
    }

    // ── (3) mod_quiz: the hole the manager's shared completion add makes ─

    public function test_quiz_shared_completion_hole(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $quizgen = $gen->get_plugin_generator('mod_quiz');
        $qa = $quizgen->create_instance(['course' => $course->id]);
        $qb = $quizgen->create_instance(['course' => $course->id]);
        $qc = $quizgen->create_instance(['course' => $course->id]);
        $control = $gen->create_user();
        $subject = $gen->create_user();
        $other = $gen->create_user();

        foreach ([$control, $subject] as $user) {
            // Overrides make mod_quiz itself report Qa and Qc.
            $quizgen->create_override(['quiz' => $qa->id, 'userid' => $user->id, 'timeclose' => time() + DAYSECS]);
            $quizgen->create_override(['quiz' => $qc->id, 'userid' => $user->id, 'timeclose' => time() + DAYSECS]);
            // Activity completion on all three: the manager's shared add appends [Qa, Qb, Qc] after [Qa, Qc].
            foreach ([$qa, $qb, $qc] as $quiz) {
                $DB->insert_record('course_modules_completion', (object) ['coursemoduleid' => $quiz->cmid,
                    'userid' => $user->id, 'completionstate' => 1, 'timemodified' => time()]);
                $DB->insert_record('course_modules_viewed', (object) ['coursemoduleid' => $quiz->cmid,
                    'userid' => $user->id, 'timecreated' => time()]);
            }
        }
        $DB->insert_record('course_modules_completion', (object) ['coursemoduleid' => $qb->cmid,
            'userid' => $other->id, 'completionstate' => 1, 'timemodified' => time()]);
        $qbcontext = (int) \context_module::instance($qb->cmid)->id;

        foreach ([$control, $subject] as $user) {
            [, $dropped] = $this->assert_hole((int) $user->id, 'mod_quiz');
            $this->assertContains($qbcontext, $dropped);
        }

        // Flags OFF: Qb's completion rows survive the stock erasure.
        $this->run_stock_delete($control);
        $this->assertSame(1, $DB->count_records('course_modules_completion',
            ['userid' => $control->id, 'coursemoduleid' => $qb->cmid]));
        $this->assertSame(1, $DB->count_records('course_modules_viewed',
            ['userid' => $control->id, 'coursemoduleid' => $qb->cmid]));

        // Flags ON: gone, and only the subject's. The manager's shared cleanup on our own approved list would
        // remove Qb's rows even without the supplementary mod_quiz dispatch, so the dispatch itself is proved
        // through the list mod_quiz received and the event.
        $this->set_flags(true, false);
        $quizlists = [];
        erasure_completeness::set_test_callback(
            function(string $component, string $method, array $params, callable $real) use (&$quizlists) {
                if ($component === 'mod_quiz' && $method === 'delete_data_for_user') {
                    $quizlists[] = array_values(array_map('intval', $params[0]->get_contextids()));
                }
                return $real();
            });
        $events = $this->redirectEvents();
        $this->run_stock_delete($subject);
        $this->assertSame([[$qbcontext]], $quizlists, 'The supplement must hand Qb, and only Qb, to mod_quiz.');
        $fired = array_values(array_filter($events->get_events(),
            fn($e) => $e instanceof \local_sentientia_privacy\event\erasure_supplemented));
        $this->assertCount(1, $fired);
        $this->assertSame(1, $fired[0]->other['components']['mod_quiz']['contexts']);
        $this->assertSame(0, $fired[0]->other['components']['mod_quiz']['failed']);
        $this->assertSame(0, $fired[0]->other['failures']);
        $events->close();
        $this->assertSame(0, $DB->count_records('course_modules_completion', ['userid' => $subject->id]));
        $this->assertSame(0, $DB->count_records('course_modules_viewed', ['userid' => $subject->id]));
        $this->assertSame(1, $DB->count_records('course_modules_completion',
            ['userid' => $other->id, 'coursemoduleid' => $qb->cmid]));
        $this->assertSame(1, $DB->count_records('course_modules_completion',
            ['userid' => $control->id, 'coursemoduleid' => $qb->cmid]));
    }

    // ── (4) The completion gap ──────────────────────────────────────────

    public function test_completion_gap(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $nocriteria = $gen->create_course(['enablecompletion' => 1]);
        $untouched = $gen->create_course(['enablecompletion' => 1]);
        $DB->insert_record('course_completion_criteria', (object) ['course' => $untouched->id,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_SELF]);
        $control = $gen->create_user();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        foreach ([$control, $subject, $other] as $user) {
            foreach ([$nocriteria, $untouched] as $course) {
                $DB->insert_record('course_completions', (object) ['userid' => $user->id, 'course' => $course->id,
                    'timeenrolled' => time(), 'reaggregate' => 0]);
            }
        }
        // Precondition: core_course never discovers either course.
        $gap = [(int) \context_course::instance($nocriteria->id)->id, (int) \context_course::instance($untouched->id)->id];
        $courselist = array_map('intval', $this->stock_list((int) $subject->id, 'core_course')->get_contextids());
        $this->assertSame([], array_values(array_intersect($gap, $courselist)), 'Precondition: core_course finds them.');

        // Flags OFF: the rows stay.
        $this->run_stock_delete($control);
        $this->assertSame(2, $DB->count_records('course_completions', ['userid' => $control->id]));

        // Completion flag ON (truncation OFF): the subject's rows go, nobody else's.
        $this->set_flags(false, true);
        $this->run_stock_delete($subject);
        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $subject->id]));
        $this->assertSame(2, $DB->count_records('course_completions', ['userid' => $other->id]));
        $this->assertSame(2, $DB->count_records('course_completions', ['userid' => $control->id]));
    }

    // ── (5) The data registry protects what it protects ─────────────────

    public function test_protected_context_keeps_its_rows_and_expired_one_loses_them(): void {
        $this->purpose_for_level('PT1H', CONTEXT_SYSTEM);
        $this->purpose_for_level('PT1H', CONTEXT_USER);
        $coursepurpose = $this->purpose_for_level('P1D', CONTEXT_COURSE);
        $coursepurpose->set('protected', 1)->save();
        $this->assertTrue(\tool_dataprivacy\data_registry::defaults_set());

        $gen = $this->getDataGenerator();
        // Unexpired (ends now, 1 day retention) and protected; expired (ended a week ago).
        $p1 = $gen->create_course(['startdate' => time() - YEARSECS, 'enddate' => time()]);
        $p2 = $gen->create_course(['startdate' => time() - YEARSECS, 'enddate' => time()]);
        $e1 = $gen->create_course(['startdate' => time() - YEARSECS, 'enddate' => time() - WEEKSECS]);
        $e2 = $gen->create_course(['startdate' => time() - YEARSECS, 'enddate' => time() - WEEKSECS]);
        $ctx = [];
        foreach (['p1' => $p1, 'p2' => $p2, 'e1' => $e1, 'e2' => $e2] as $key => $course) {
            $ctx[$key] = \context_course::instance($course->id);
        }
        $subject = $gen->create_user();
        foreach (['p1' => $p1, 'p2' => $p2, 'e1' => $e1, 'e2' => $e2] as $key => $course) {
            $this->log($subject->id, $ctx[$key], $course->id);
            $this->log($subject->id, $ctx[$key], $course->id);
        }
        $this->assert_hole((int) $subject->id, 'tool_log');

        // Request path (the stock approval filters our list too).
        $this->set_flags(true, false);
        $this->run_stock_delete($subject);
        $this->assertSame(2, $this->logrows((int) $subject->id, $ctx['p1']->id));
        $this->assertSame(2, $this->logrows((int) $subject->id, $ctx['p2']->id));
        $this->assertSame(0, $this->logrows((int) $subject->id, $ctx['e1']->id));
        $this->assertSame(0, $this->logrows((int) $subject->id, $ctx['e2']->id));

        // Expiry path: no stock filter there, so ours must hold the protected context back.
        $expiring = $gen->create_user();
        foreach (['p1' => $p1, 'p2' => $p2] as $key => $course) {
            $this->log($expiring->id, $ctx[$key], $course->id);
            $this->log($expiring->id, $ctx[$key], $course->id);
        }
        [, $dropped] = $this->assert_hole((int) $expiring->id, 'tool_log');
        $plan = erasure_completeness::plan((int) $expiring->id, true, false);
        erasure_completeness::remember($plan);
        $events = $this->redirectEvents();
        $result = erasure_completeness::apply_delete($expiring,
            [SYSCONTEXTID, $ctx['p1']->id, $ctx['p2']->id], erasure_completeness::PATH_EXPIRY, true, false);
        $this->assertSame(count($dropped), $result['components']['tool_log']['dropped_by_registry']);
        $this->assertSame(0, $result['components']['tool_log']['contexts']);
        $this->assertSame(4, $this->logrows((int) $expiring->id));
        $fired = array_values(array_filter($events->get_events(),
            fn($e) => $e instanceof \local_sentientia_privacy\event\erasure_supplemented));
        $this->assertCount(1, $fired);
        $this->assertNull($fired[0]->objectid);
        $this->assertSame('expiry', $fired[0]->other['path']);
    }

    // ── (6) Failure reporting ───────────────────────────────────────────

    /**
     * A tool_log hole and a completion gap for one user. Returns [dropped tool_log context, gap course].
     */
    private function failure_fixture(\stdClass $subject, \stdClass $other): array {
        global $DB;
        $this->tool_log_fixture($subject, $other);
        [, $dropped] = $this->assert_hole((int) $subject->id, 'tool_log');
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $DB->insert_record('course_completions', (object) ['userid' => $subject->id, 'course' => $course->id,
            'timeenrolled' => time(), 'reaggregate' => 0]);
        return [$dropped[0], $course];
    }

    /**
     * Make tool_log's supplementary delete throw; everything else runs for real.
     */
    private function break_tool_log_delete(): void {
        erasure_completeness::set_test_callback(
            function(string $component, string $method, array $params, callable $real) {
                if ($component === 'tool_log' && $method === 'delete_data_for_user') {
                    throw new \coding_exception('simulated tool_log failure');
                }
                return $real();
            });
    }

    public function test_a_failing_component_is_reported_once_and_the_rest_still_runs(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        [$dropped, $course] = $this->failure_fixture($subject, $other);
        $this->set_flags(true, true);
        $this->break_tool_log_delete();

        $observer = new class implements \core_privacy\manager_observer {
            /** @var array Every failure the manager reported. */
            public array $failures = [];
            public function handle_component_failure($e, $component, $interface, $methodname, array $params) {
                $this->failures[] = ['component' => $component, 'method' => $methodname, 'message' => $e->getMessage()];
            }
        };
        $events = $this->redirectEvents();
        $this->run_stock_delete($subject, $observer);

        $ours = array_values(array_filter($observer->failures,
            fn($f) => $f['component'] === erasure_completeness::SELF));
        $this->assertCount(1, $ours, 'One aggregate failure, reported once, for local_sentientia_privacy.');
        $this->assertSame('delete_data_for_user', $ours[0]['method']);
        $this->assertStringContainsString('tool_log', $ours[0]['message']);
        $this->assertStringContainsString('residue sweep', $ours[0]['message'], 'The DPO must not be told to re-run it.');
        $this->assertStringNotContainsString((string) $dropped, $ours[0]['message'], 'No ids in the message.');
        // The rest still ran: the completion gap is erased; the failed component's rows are left.
        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $subject->id]));
        $this->assertSame(2, $this->logrows((int) $subject->id, $dropped));

        // The audit record says what failed: tool_log attempted, not erased; the completion course erased.
        $fired = array_values(array_filter($events->get_events(),
            fn($e) => $e instanceof \local_sentientia_privacy\event\erasure_supplemented));
        $this->assertCount(1, $fired);
        $other = $fired[0]->other;
        $this->assertSame(1, $other['components']['tool_log']['contexts']);
        $this->assertSame(1, $other['components']['tool_log']['failed']);
        $this->assertSame(1, $other['completion_courses']);
        $this->assertSame(0, $other['completion_failed']);
        $this->assertStringContainsString('deleted data in 0 context(s) of 0 component(s)', $fired[0]->get_description());
        $this->assertStringContainsString('1 context(s) of 1 component(s)', $fired[0]->get_description());
        $events->close();
        $this->resetDebugging();
    }

    public function test_a_failing_completion_delete_is_not_reported_as_erased(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $DB->insert_record('course_completions', (object) ['userid' => $subject->id, 'course' => $course->id,
            'timeenrolled' => time(), 'reaggregate' => 0]);
        $this->set_flags(false, true);
        $own = provider::get_contexts_for_userid((int) $subject->id);
        erasure_completeness::set_test_callback(
            function(string $component, string $method, array $params, callable $real) {
                if ($component === 'core_course' && $method === 'delete_data_for_user') {
                    throw new \coding_exception('simulated core_course failure');
                }
                return $real();
            });

        $events = $this->redirectEvents();
        $result = erasure_completeness::apply_delete($subject, $own->get_contextids(), erasure_completeness::PATH_EXPIRY,
            false, true);
        $this->assertSame(1, $result['completion_courses']);
        $this->assertSame(1, $result['completion_failed']);
        $fired = array_values(array_filter($events->get_events(),
            fn($e) => $e instanceof \local_sentientia_privacy\event\erasure_supplemented));
        $this->assertCount(1, $fired);
        $this->assertSame(1, $fired[0]->other['completion_failed']);
        $description = $fired[0]->get_description();
        $this->assertStringContainsString('and 0 course completion record set(s) that core never discovers',
            $description);
        $this->assertStringContainsString('0 component(s) and 1 course completion record set(s)', $description);
        $events->close();
        $this->assertSame(1, $DB->count_records('course_completions', ['userid' => $subject->id]));
        $this->resetDebugging();
    }

    public function test_provider_throws_one_aggregate_exception(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        [$dropped, $course] = $this->failure_fixture($subject, $other);
        $this->set_flags(true, true);
        $own = provider::get_contexts_for_userid((int) $subject->id);
        $this->break_tool_log_delete();

        $approved = new approved_contextlist($subject, erasure_completeness::SELF,
            erasure_completeness::dense($own->get_contextids()));
        try {
            provider::delete_data_for_user($approved);
            $this->fail('A failed supplementary delete must be reported.');
        } catch (\moodle_exception $e) {
            $this->assertSame('erasuresupplementfailed_delete', $e->errorcode);
            $this->assertSame('tool_log', $e->a);
        }
        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $subject->id, 'course' => $course->id]));
        $this->assertSame(2, $this->logrows((int) $subject->id, $dropped));
        $this->resetDebugging();
    }

    // ── (7) Our own list, odd subjects ──────────────────────────────────

    public function test_discovery_never_throws_for_missing_or_deleted_subjects(): void {
        $this->set_flags(true, true);

        $list = provider::get_contexts_for_userid(987654321);
        $this->assertContains((int) SYSCONTEXTID, array_map('intval', $list->get_contextids()));

        $gen = $this->getDataGenerator();
        $gone = $gen->create_user();
        $other = $gen->create_user();
        $this->tool_log_fixture($gone, $other);
        delete_user($gone);
        \context_user::instance($gone->id)->delete();
        $list = provider::get_contexts_for_userid((int) $gone->id);
        $this->assertInstanceOf(contextlist::class, $list);
        $this->assertCount(count($list), $this->iterated($list));
        $this->assertContains((int) SYSCONTEXTID, array_map('intval', $list->get_contextids()));
    }

    // ── (8) Canary on core ──────────────────────────────────────────────

    public function test_stock_approval_still_truncates_on_this_core(): void {
        $this->assertTrue(erasure_completeness::stock_approval_truncates(),
            'Core no longer truncates holey lists: retire the truncation part of erasure_completeness '
            . '(and its flag), keep the completion-gap part.');
    }

    // ── Export ──────────────────────────────────────────────────────────

    public function test_export_covers_the_dropped_context_unless_allowfiltering(): void {
        set_config('exportlog', 1, 'tool_log');
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        $this->tool_log_fixture($subject, $other);
        [, $dropped] = $this->assert_hole((int) $subject->id, 'tool_log');
        $droppedcontext = \context::instance_by_id($dropped[0]);
        $this->set_flags(true, false);

        // allowfiltering: the stored lists are full, so the supplement stays out.
        set_config('allowfiltering', 1, 'tool_dataprivacy');
        $own = provider::get_contexts_for_userid((int) $subject->id);
        provider::export_user_data(new approved_contextlist($subject, erasure_completeness::SELF,
            erasure_completeness::dense($own->get_contextids())));
        $this->assertFalse(\core_privacy\local\request\writer::with_context($droppedcontext)->has_any_data());

        set_config('allowfiltering', 0, 'tool_dataprivacy');
        $own = provider::get_contexts_for_userid((int) $subject->id);
        $this->assertContains((int) $dropped[0], array_map('intval', $own->get_contextids()));
        provider::export_user_data(new approved_contextlist($subject, erasure_completeness::SELF,
            erasure_completeness::dense($own->get_contextids())));
        $this->assertTrue(\core_privacy\local\request\writer::with_context($droppedcontext)->has_any_data());
    }

    public function test_export_covers_the_completion_gap_even_with_allowfiltering(): void {
        global $DB;
        set_config('enablecompletion', 1);
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $DB->insert_record('course_completions', (object) ['userid' => $subject->id, 'course' => $course->id,
            'timeenrolled' => time(), 'reaggregate' => 0]);
        $coursecontext = \context_course::instance($course->id);
        $this->set_flags(false, true);

        // allowfiltering stores full lists, so only the truncation part is redundant there: core_course never
        // discovers this course, and only our stored list carries it.
        set_config('allowfiltering', 1, 'tool_dataprivacy');
        $own = provider::get_contexts_for_userid((int) $subject->id);
        $this->assertContains((int) $coursecontext->id, array_map('intval', $own->get_contextids()));
        provider::export_user_data(new approved_contextlist($subject, erasure_completeness::SELF,
            erasure_completeness::dense($own->get_contextids())));
        $this->assertTrue(\core_privacy\local\request\writer::with_context($coursecontext)->has_any_data());
    }

    // ── Cache miss: the delete recomputes the plan ──────────────────────

    public function test_cache_miss_recomputes_with_the_before_and_after_rules(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        $this->tool_log_fixture($subject, $other);
        $this->assert_hole((int) $subject->id, 'tool_log');
        $course = $gen->create_course(['enablecompletion' => 1]);
        foreach ([$subject, $other] as $user) {
            $DB->insert_record('course_completions', (object) ['userid' => $user->id, 'course' => $course->id,
                'timeenrolled' => time(), 'reaggregate' => 0]);
        }
        $this->set_flags(true, true);
        $clock = $this->mock_clock_with_frozen();

        // Discovery and approval as the task runs them; then the plan outlives the store's lifetime.
        $collection = (new \core_privacy\manager())->get_contexts_for_userid((int) $subject->id);
        $approved = api::get_approved_contextlist_collection_for_collection($collection, $subject,
            api::DATAREQUEST_TYPE_DELETE);
        $clock->bump(\local_sentientia_privacy\local\erasure_plan_store::TTL + 1);
        $otherlogs = $this->logrows((int) $other->id);
        $relatedbefore = $this->logrows((int) $subject->id, null, 'relateduserid');

        $events = $this->redirectEvents();
        (new \core_privacy\manager())->delete_data_for_user($approved);

        $fired = array_values(array_filter($events->get_events(),
            fn($e) => $e instanceof \local_sentientia_privacy\event\erasure_supplemented));
        $this->assertCount(1, $fired);
        $payload = $fired[0]->other;
        $this->assertSame('recomputed', $payload['plan']);
        // tool_log runs BEFORE us (full_now ∩ approved), core_course AFTER us (gap ∩ approved).
        $this->assertSame(1, $payload['components']['tool_log']['contexts']);
        $this->assertSame(1, $payload['completion_courses']);
        $this->assertSame(0, $payload['failures']);
        $events->close();
        $this->assertSame(0, $this->logrows((int) $subject->id), 'Every subject userid row must be gone.');
        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $subject->id]));
        $this->assertSame($otherlogs, $this->logrows((int) $other->id), 'Somebody else\'s rows must stay.');
        $this->assertSame($relatedbefore, $this->logrows((int) $subject->id, null, 'relateduserid'));
        $this->assertSame(1, $DB->count_records('course_completions', ['userid' => $other->id]));
    }

    // ── CLI path: no manager, so apply_delete() runs the shared cleanup ─

    public function test_cli_path_runs_the_shared_cleanup_itself(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $quiz = $gen->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id]);
        $modcontext = (int) \context_module::instance($quiz->cmid)->id;
        $expiring = $gen->create_user();
        $swept = $gen->create_user();
        foreach ([$expiring, $swept] as $user) {
            // A view-only row: no component discovers it, as when tool_log is the one that dropped the context.
            $DB->insert_record('course_modules_viewed', (object) ['coursemoduleid' => $quiz->cmid,
                'userid' => $user->id, 'timecreated' => time()]);
        }

        $result = erasure_completeness::apply_delete($expiring, [SYSCONTEXTID, $modcontext],
            erasure_completeness::PATH_EXPIRY, true, false);
        $this->assertSame([], $result['failures']);
        $this->assertSame(1, $DB->count_records('course_modules_viewed', ['userid' => $expiring->id]),
            'On the request and expiry paths the privacy manager runs the shared cleanup, not apply_delete().');

        $result = erasure_completeness::apply_delete($swept, [SYSCONTEXTID, $modcontext],
            erasure_completeness::PATH_CLI, true, false);
        $this->assertSame([], $result['failures']);
        $this->assertSame(0, $DB->count_records('course_modules_viewed', ['userid' => $swept->id]));
        $this->assertSame(1, $DB->count_records('course_modules_viewed', ['userid' => $expiring->id]));
    }
}
