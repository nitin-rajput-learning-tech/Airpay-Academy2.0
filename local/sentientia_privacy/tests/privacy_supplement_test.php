<?php
// This file is part of Sentientia LMS.

/**
 * ADR-035 erasure supplement in the local_sentientia_privacy privacy provider.
 *
 * Two stock Moodle defects leave personal data behind on a 'Deleted' request:
 *   1. contextlist_base::set_contextids() is array_unique() without a reindex,
 *      and valid() is isset() on the position, so iterating a context list
 *      stops at the first duplicate. tool_dataprivacy builds the approved list
 *      by iterating. Affected: logstore_standard, report_stats.
 *   2. core_course finds course_completions only through completion criteria,
 *      so a completion in a course without criteria is never found.
 * These tests seed the rows the A4 rehearsal found left behind and assert on
 * the rows: the subject's go, the actor's and other users' stay, and a second
 * pass is harmless.
 *
 * @package    local_sentientia_privacy
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_privacy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\contextlist_collection;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_privacy\privacy\provider;
use tool_dataprivacy\api;

/**
 * @covers \local_sentientia_privacy\privacy\provider
 */
final class privacy_supplement_test extends \core_privacy\tests\provider_testcase {

    /** @var string[] */
    private const STATS = ['stats_user_daily', 'stats_user_weekly', 'stats_user_monthly'];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    // ---------------------------------------------------------------- fixtures

    /**
     * One logstore_standard_log row.
     */
    private function log(\context $context, int $userid, ?int $relateduserid = null, ?int $realuserid = null): int {
        global $DB;
        return (int) $DB->insert_record('logstore_standard_log', (object) [
            'eventname'         => '\\core\\event\\course_viewed',
            'component'         => 'core',
            'action'            => 'viewed',
            'target'            => 'course',
            'objecttable'       => null,
            'objectid'          => null,
            'crud'              => 'r',
            'edulevel'          => 2,
            'contextid'         => $context->id,
            'contextlevel'      => $context->contextlevel,
            'contextinstanceid' => $context->instanceid,
            'userid'            => $userid,
            'courseid'          => $context->contextlevel == CONTEXT_COURSE ? $context->instanceid : 0,
            'relateduserid'     => $relateduserid,
            'anonymous'         => 0,
            'other'             => 'N;',
            'timecreated'       => time(),
            'origin'            => 'web',
            'ip'                => '10.0.0.1',
            'realuserid'        => $realuserid,
        ]);
    }

    private function completion(int $courseid, int $userid): void {
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'userid'        => $userid,
            'course'        => $courseid,
            'timeenrolled'  => time() - 3 * DAYSECS,
            'timestarted'   => time() - 2 * DAYSECS,
            'timecompleted' => time() - DAYSECS,
            'reaggregate'   => 0,
        ]);
    }

    private function stats(int $courseid, int $userid): void {
        global $DB;
        foreach (self::STATS as $table) {
            $DB->insert_record($table, (object) [
                'courseid'    => $courseid,
                'userid'      => $userid,
                'roleid'      => 5,
                'timeend'     => time(),
                'statsreads'  => 3,
                'statswrites' => 1,
                'stattype'    => 'activity',
            ]);
        }
    }

    private function actor_logs(int $userid): int {
        global $DB;
        return $DB->count_records('logstore_standard_log', ['userid' => $userid]);
    }

    private function stats_rows(int $userid): int {
        global $DB;
        $n = 0;
        foreach (self::STATS as $table) {
            $n += $DB->count_records($table, ['userid' => $userid]);
        }
        return $n;
    }

    /**
     * Run a deletion the way process_data_request_task does: approve the
     * collection by iterating it, then hand it to the privacy manager.
     */
    private function process_deletion(contextlist_collection $collection, \stdClass $user): void {
        $approved = api::get_approved_contextlist_collection_for_collection(
            $collection, $user, api::DATAREQUEST_TYPE_DELETE);
        (new \core_privacy\manager())->delete_data_for_user($approved);
    }

    /**
     * The supplement's approved list, built from its own discovery.
     */
    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, 'local_sentientia_privacy',
            provider::get_contexts_for_userid($user->id)->get_contextids());
    }

    // --------------------------------------------------- defect 1: duplicates

    /**
     * Documents defect 1 with a deterministic list: [A, A, B] keeps keys 0 and
     * 2, so iteration stops after A and tool_dataprivacy approves A alone.
     * Skips (does not fail) once core is fixed, as a prompt to retire the log
     * and statistics part of the supplement (ADR-035).
     */
    public function test_core_iteration_stops_at_the_first_duplicate(): void {
        $user = $this->getDataGenerator()->create_user();
        $a = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $b = \context_course::instance($this->getDataGenerator()->create_course()->id);

        $list = new contextlist();
        $list->set_component('tool_log');
        $list->add_from_sql((string) $a->id, []);
        $list->add_from_sql((string) $a->id, []);
        $list->add_from_sql((string) $b->id, []);

        $iterated = 0;
        foreach ($list as $unused) {
            $iterated++;
        }
        if ($iterated === count($list)) {
            $this->markTestSkipped('Moodle core no longer cuts a context list short at a duplicate '
                . '(ADR-035 defect 1 is fixed upstream): retire the log/stats part of the supplement.');
        }
        $this->assertCount(2, $list);
        $this->assertSame(1, $iterated);

        $collection = new contextlist_collection($user->id);
        $collection->add_contextlist($list);
        $approved = api::get_approved_contextlist_collection_for_collection(
            $collection, $user, api::DATAREQUEST_TYPE_DELETE);
        $this->assertSame([$a->id], array_values(array_map('intval',
            $approved->get_contextlist_for_component('tool_log')->get_contextids())));
    }

    /**
     * The duplicate-context fixture end to end. Core's tool_log list is cut
     * short to its first context (the defect, made deterministic); the
     * supplement, run by the same privacy manager in the same request, erases
     * the subject's rows in every context and nothing else.
     */
    public function test_duplicate_log_contexts_are_all_erased(): void {
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        $c = [];
        foreach ([1, 2, 3] as $i) {
            $c[$i] = \context_course::instance($gen->create_course()->id);
        }

        // Duplicates: two rows each in C1 and C2, one in C3.
        foreach ([1, 1, 2, 2, 3] as $i) {
            $this->log($c[$i], $subject->id);
            $this->log($c[$i], $other->id);
        }
        // The subject only as related user (the actor's record) and as the
        // real user behind a login-as. Core keeps both; so must we.
        $related = $this->log($c[2], $other->id, $subject->id);
        $realuser = $this->log($c[3], $other->id, null, $subject->id);
        foreach ([1, 2, 3] as $i) {
            $this->stats((int) $c[$i]->instanceid, $subject->id);
            $this->stats((int) $c[$i]->instanceid, $other->id);
        }

        // Core's tool_log list as defect 1 leaves it: [C1, C1, C2, C3].
        $corelog = new contextlist();
        $corelog->set_component('tool_log');
        foreach ([1, 1, 2, 3] as $i) {
            $corelog->add_from_sql((string) $c[$i]->id, []);
        }
        $collection = new contextlist_collection($subject->id);
        $collection->add_contextlist($corelog);

        $ours = provider::get_contexts_for_userid($subject->id);
        $ours->set_component('local_sentientia_privacy');
        $collection->add_contextlist($ours);

        $this->process_deletion($collection, $subject);

        $this->assertSame(0, $this->actor_logs($subject->id), 'every log row the subject performed is gone');
        $this->assertSame(0, $this->stats_rows($subject->id), 'every statistics row of the subject is gone');
        global $DB;
        $this->assertTrue($DB->record_exists('logstore_standard_log', ['id' => $related]),
            'a row naming the subject only as related user is the actor\'s record and stays');
        $this->assertTrue($DB->record_exists('logstore_standard_log', ['id' => $realuser]),
            'a row naming the subject only as real user stays, as in core');
        $this->assertSame(7, $this->actor_logs($other->id), 'the other user\'s rows are untouched');
        $this->assertSame(9, $this->stats_rows($other->id));
    }

    /**
     * The supplement's own list never has a hole: overlapping sources (log,
     * completion and statistics in one course; the system context for the
     * plugin's own tables and for a login) come back once each, and iterating
     * visits exactly count() contexts.
     */
    public function test_own_context_list_has_no_holes(): void {
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $course = $gen->create_course();
        $ctx = \context_course::instance($course->id);
        $sys = \context_system::instance();

        foreach ([1, 2, 3] as $unused) {
            $this->log($ctx, $subject->id);
            $this->log($sys, $subject->id);
        }
        $this->log($ctx, $gen->create_user()->id, $subject->id);
        $this->completion($course->id, $subject->id);
        $this->stats($course->id, $subject->id);

        $list = provider::get_contexts_for_userid($subject->id);
        $ids = array_map('intval', $list->get_contextids());
        sort($ids);
        $this->assertSame([(int) $sys->id, (int) $ctx->id], $ids);
        $this->assertSame(range(0, count($list) - 1), array_keys($list->get_contextids()));

        $iterated = 0;
        foreach ($list as $unused) {
            $iterated++;
        }
        $this->assertSame(count($list), $iterated);
    }

    // ----------------------------------------- defect 2: no completion criteria

    /**
     * A completion in a course without criteria, and one in a course whose
     * criteria the subject never touched: core_course finds neither, the
     * supplement finds and erases both, and other users keep theirs.
     */
    public function test_completion_without_criteria_is_found_and_erased(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();

        $nocriteria = $gen->create_course(['enablecompletion' => 1]);
        $untouched = $gen->create_course(['enablecompletion' => 1]);
        $page = $gen->create_module('page', ['course' => $untouched->id], ['completion' => COMPLETION_TRACKING_MANUAL]);
        $DB->insert_record('course_completion_criteria', (object) [
            'course'         => $untouched->id,
            'criteriatype'   => COMPLETION_CRITERIA_TYPE_ACTIVITY,
            'module'         => 'page',
            'moduleinstance' => $page->cmid,
        ]);
        foreach ([$nocriteria, $untouched] as $course) {
            $this->completion($course->id, $subject->id);
            $this->completion($course->id, $other->id);
        }
        $ctxnone = \context_course::instance($nocriteria->id);
        $ctxuntouched = \context_course::instance($untouched->id);

        $core = array_map('intval', \core_course\privacy\provider::get_contexts_for_userid($subject->id)->get_contextids());
        if (in_array((int) $ctxnone->id, $core, true)) {
            $this->markTestSkipped('core_course now finds completions without criteria '
                . '(ADR-035 defect 2 is fixed upstream): retire the completion part of the supplement.');
        }
        $this->assertNotContains((int) $ctxuntouched->id, $core);

        $ours = array_map('intval', provider::get_contexts_for_userid($subject->id)->get_contextids());
        $this->assertContains((int) $ctxnone->id, $ours);
        $this->assertContains((int) $ctxuntouched->id, $ours);

        provider::delete_data_for_user($this->approved($subject));

        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $subject->id]));
        $this->assertSame(2, $DB->count_records('course_completions', ['userid' => $other->id]));
    }

    // ------------------------------------------------------- double delete

    /**
     * Running after the core providers, and running twice, deletes nothing
     * more and raises nothing.
     */
    public function test_a_second_pass_is_a_noop(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $ctx = \context_course::instance($course->id);
        $this->log($ctx, $subject->id);
        $this->log($ctx, $other->id);
        $this->completion($course->id, $subject->id);
        $this->completion($course->id, $other->id);
        $this->stats($course->id, $subject->id);

        $approved = $this->approved($subject);

        // Core's own providers first, with the complete list.
        \logstore_standard\privacy\provider::delete_data_for_user(
            new approved_contextlist($subject, 'logstore_standard', $approved->get_contextids()));
        \core_completion\privacy\provider::delete_completion($subject, $course->id);

        provider::delete_data_for_user($approved);
        $before = [
            $DB->count_records('logstore_standard_log'),
            $DB->count_records('course_completions'),
            $this->stats_rows($other->id),
        ];
        provider::delete_data_for_user($approved);

        $this->assertSame($before, [
            $DB->count_records('logstore_standard_log'),
            $DB->count_records('course_completions'),
            $this->stats_rows($other->id),
        ]);
        $this->assertSame(0, $this->actor_logs($subject->id));
        $this->assertSame(1, $this->actor_logs($other->id));
        $this->assertSame(1, $DB->count_records('course_completions', ['userid' => $other->id]));
    }

    /**
     * The plugin's own tables are the record of processing and proof of
     * consent: still kept when the subject's other data is erased.
     */
    public function test_own_tables_are_kept(): void {
        global $DB;
        $subject = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_privacy_requests', (object) [
            'userid'       => $subject->id,
            'request_type' => 'account_delete',
            'status'       => 'pending',
            'timecreated'  => time(),
        ]);
        $DB->insert_record('local_privacy_consent_log', (object) [
            'userid'       => $subject->id,
            'consent_type' => 'data_processing',
            'consented'    => 1,
            'timecreated'  => time(),
        ]);
        $this->log(\context_system::instance(), $subject->id);

        provider::delete_data_for_user($this->approved($subject));
        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(1, $DB->count_records('local_privacy_requests', ['userid' => $subject->id]));
        $this->assertSame(1, $DB->count_records('local_privacy_consent_log', ['userid' => $subject->id]));
        $this->assertSame(0, $this->actor_logs($subject->id));
    }

    // ------------------------------------------- userlist and context-wide

    public function test_users_in_context_and_userlist_delete(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $other = $gen->create_user();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $ctx = \context_course::instance($course->id);
        $elsewhere = $gen->create_course();
        foreach ([$subject, $other] as $u) {
            $this->log($ctx, $u->id);
            $this->completion($course->id, $u->id);
            $this->stats($course->id, $u->id);
        }
        $this->log(\context_course::instance($elsewhere->id), $subject->id);

        $userlist = new userlist($ctx, 'local_sentientia_privacy');
        provider::get_users_in_context($userlist);
        $found = array_map('intval', $userlist->get_userids());
        sort($found);
        $this->assertSame([(int) $subject->id, (int) $other->id], $found);

        provider::delete_data_for_users(new approved_userlist($ctx, 'local_sentientia_privacy', [$subject->id]));

        $this->assertSame(0, $DB->count_records('logstore_standard_log', ['userid' => $subject->id, 'contextid' => $ctx->id]));
        $this->assertSame(1, $this->actor_logs($subject->id), 'the row in another course is not this context\'s');
        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $subject->id]));
        $this->assertSame(0, $this->stats_rows($subject->id));
        $this->assertSame(1, $this->actor_logs($other->id));
        $this->assertSame(1, $DB->count_records('course_completions', ['userid' => $other->id]));
        $this->assertSame(3, $this->stats_rows($other->id));
    }

    public function test_delete_for_all_users_in_a_course_context(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $keep = $gen->create_course(['enablecompletion' => 1]);
        foreach ([$course, $keep] as $c) {
            foreach ([$a, $b] as $u) {
                $this->log(\context_course::instance($c->id), $u->id);
                $this->completion($c->id, $u->id);
                $this->stats($c->id, $u->id);
            }
        }

        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));

        $ctx = \context_course::instance($course->id);
        $this->assertSame(0, $DB->count_records('logstore_standard_log', ['contextid' => $ctx->id]));
        $this->assertSame(0, $DB->count_records('course_completions', ['course' => $course->id]));
        $this->assertSame(0, $DB->count_records('stats_user_daily', ['courseid' => $course->id]));
        $this->assertSame(2, $DB->count_records('course_completions', ['course' => $keep->id]));
        $this->assertSame(2, $DB->count_records('stats_user_daily', ['courseid' => $keep->id]));
        $this->assertSame(2, $DB->count_records('logstore_standard_log',
            ['contextid' => \context_course::instance($keep->id)->id]));
    }

    // ------------------------------------------------------------- export

    /**
     * Right of access: logs in every context (duplicates included), the raw
     * completion row for a course without criteria, and statistics.
     */
    public function test_export_covers_every_context(): void {
        $gen = $this->getDataGenerator();
        $subject = $gen->create_user();
        $c = [];
        foreach ([1, 2, 3] as $i) {
            $c[$i] = \context_course::instance($gen->create_course(['enablecompletion' => 1])->id);
        }
        foreach ([1, 1, 2, 2, 3] as $i) {
            $this->log($c[$i], $subject->id);
        }
        $this->completion((int) $c[2]->instanceid, $subject->id);
        $this->stats((int) $c[3]->instanceid, $subject->id);

        provider::export_user_data($this->approved($subject));

        $logpath = [get_string('privacy:path:logs', 'tool_log'), get_string('pluginname', 'logstore_standard')];
        foreach ([1, 2, 3] as $i) {
            $data = writer::with_context($c[$i])->get_data($logpath);
            $this->assertNotEmpty($data, "logs exported for course context {$i}");
        }
        $this->assertCount(2, writer::with_context($c[1])->get_data($logpath)->logs);

        $completion = writer::with_context($c[2])->get_data([
            get_string('pluginname', 'local_sentientia_privacy'),
            get_string('privacy:path:coursecompletion', 'local_sentientia_privacy'),
        ]);
        $this->assertNotEmpty($completion->timecompleted);

        $this->assertNotEmpty(writer::with_context($c[3])->get_data([get_string('privacy:dailypath', 'report_stats')]));
    }

    // -------------------------------------------------------- DPDP panel

    /**
     * The Sentientia DPDP panel keeps anonymised learning records, so it must
     * never call this provider (which would erase completions).
     */
    public function test_the_dpdp_panel_never_calls_the_supplement(): void {
        $method = new \ReflectionMethod(privacy_manager::class, 'erasure_providers');
        $method->setAccessible(true);
        $this->assertArrayNotHasKey('local_sentientia_privacy', $method->invoke(null));
    }
}
