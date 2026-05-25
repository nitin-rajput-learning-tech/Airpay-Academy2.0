<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

use local_airpay_core\feature_flags;

/**
 * Tests for the Phase L.1 rank-change notification pipeline.
 *
 * Locks in:
 *   - the `rankings_updated` event triggers a Moodle message when the
 *     master notification flag is ON (`test_rank_change_triggers_message_when_flag_on`)
 *   - the same event is a silent no-op when the flag is OFF — default
 *     behaviour for every tenant until the flag is flipped
 *     (`test_no_message_when_flag_off`)
 *   - a second rank change within 24h does NOT re-fire a message
 *     (`test_throttle_blocks_duplicate_within_24h`)
 *   - a fresh top-10 entry — even with a movement smaller than the
 *     5-position threshold — fires (`test_top_10_entry_triggers_message`)
 *   - the message subject/body templates are reachable (sanity check on
 *     the lang strings)
 *
 * Strategy: tests dispatch the event directly with a constructed
 * `changes` payload. The recompute → event integration is already covered
 * by `ranking_engine_test::test_recompute_emits_event`; here we keep the
 * focus on the observer + message_helper behaviour. One integration-
 * style test (`test_recompute_triggers_message_end_to_end`) covers the
 * complete recompute → observer → message_send pipeline.
 *
 * @package    local_sentientia_leaderboard
 * @category   test
 * @covers     \local_sentientia_leaderboard\message_helper
 * @covers     \local_sentientia_leaderboard\observer
 * @covers     \local_sentientia_leaderboard\event\rankings_updated
 */
final class message_helper_test extends \advanced_testcase {

    /** Component name we filter messages by. */
    private const COMPONENT = 'local_sentientia_leaderboard';
    private const PROVIDER  = 'rank_change';

    protected function setUp(): void {
        parent::setUp();
        // Clear feature_flags caches so a per-test override is honoured.
        if (class_exists('\\local_airpay_core\\feature_flags')) {
            feature_flags::invalidate_caches();
        }
    }

    // ─── helpers ────────────────────────────────────────────────────

    /**
     * Set the Phase L.1 notification flag globally for the test scope.
     */
    private function set_notifications_flag(bool $on): void {
        global $USER;
        feature_flags::set(message_helper::FLAG_KEY, 0, $on,
            (int) $USER->id, 'phpunit');
        feature_flags::invalidate_caches();
    }

    /**
     * Create a user pinned to tenant 1 (Airpay).
     */
    private function create_user(): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $u->id]);
        return $u;
    }

    /**
     * Insert a minimal board row and return its id. Bypasses
     * board_manager::create() so we don't need a real quiz/course just
     * to wire up the throttle table.
     */
    private function create_board(int $ownerid): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_lb_boards',
            (object) [
                'name'              => 'L1 Test Board',
                'type'              => board_manager::TYPE_COMPLETION,
                'scope'             => board_manager::SCOPE_TENANT,
                'courseid'          => 1,
                'quizid'            => 0,
                'skill_ids_json'    => null,
                'window_start'      => null,
                'window_end'        => null,
                'recompute_seconds' => 120,
                'ownerid'           => $ownerid,
                'customerid'        => 1,
                'tenantid'          => 1,
                'status'            => board_manager::STATUS_ACTIVE,
                'settings_json'     => null,
                'last_recomputed'   => 0,
                'timecreated'       => $now,
                'timemodified'      => $now,
            ]);
    }

    /**
     * Fire a `rankings_updated` event with the supplied changes payload.
     * Returns the message sink set up beforehand so each test can drain
     * + assert.
     */
    private function trigger_event(int $boardid, array $changes): \phpunit_message_sink {
        $sink = $this->redirectMessages();
        event\rankings_updated::create([
            'context'  => \context_system::instance(),
            'objectid' => $boardid,
            'other'    => ['changes' => $changes],
        ])->trigger();
        return $sink;
    }

    /**
     * Filter a sink's messages down to the ones THIS plugin produced.
     *
     * @return array<int, \stdClass>
     */
    private function ours(\phpunit_message_sink $sink): array {
        $out = [];
        foreach ($sink->get_messages() as $m) {
            if ($m->component === self::COMPONENT
                    && $m->eventtype === self::PROVIDER) {
                $out[] = $m;
            }
        }
        return $out;
    }

    // ─── tests ──────────────────────────────────────────────────────

    /**
     * Required test: rank shift of 5+ on flag-ON dispatches a Moodle
     * message to the affected learner.
     */
    public function test_rank_change_triggers_message_when_flag_on(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        $this->set_notifications_flag(true);

        $owner = $this->create_user();
        $learner = $this->create_user();
        $boardid = $this->create_board((int) $owner->id);

        $sink = $this->trigger_event($boardid, [[
            'userid'   => (int) $learner->id,
            'old_rank' => 12,
            'new_rank' => 5,            // up 7 places
            'reason'   => message_helper::REASON_LARGE_MOVE,
        ]]);

        $messages = $this->ours($sink);
        $sink->close();

        $this->assertCount(1, $messages,
            'flag ON + 5+ rank move must dispatch exactly one message');
        $msg = $messages[0];
        $this->assertSame((int) $learner->id, (int) $msg->useridto);
        $this->assertNotEmpty($msg->subject);
        $this->assertNotEmpty($msg->fullmessage);
    }

    /**
     * Required test: flag OFF means the observer silently no-ops, no
     * matter how dramatic the rank change.
     */
    public function test_no_message_when_flag_off(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        // Force OFF explicitly — defends against another test leaving
        // the flag ON. Belt-and-braces over the registered default.
        $this->set_notifications_flag(false);

        $owner = $this->create_user();
        $learner = $this->create_user();
        $boardid = $this->create_board((int) $owner->id);

        $sink = $this->trigger_event($boardid, [[
            'userid'   => (int) $learner->id,
            'old_rank' => 50,
            'new_rank' => 1,            // dramatic shift
            'reason'   => message_helper::REASON_LARGE_MOVE,
        ]]);

        $messages = $this->ours($sink);
        $sink->close();

        $this->assertCount(0, $messages,
            'flag OFF must suppress every rank-change message');
        $this->assertSame(0, (int) $DB->count_records(
            'local_sentientia_lb_notify_log',
            ['boardid' => $boardid, 'userid' => $learner->id]
        ), 'flag OFF must not write a throttle row');
    }

    /**
     * Required test: a second qualifying rank shift inside the 24h
     * window does not dispatch another message.
     */
    public function test_throttle_blocks_duplicate_within_24h(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        $this->set_notifications_flag(true);

        $owner = $this->create_user();
        $learner = $this->create_user();
        $boardid = $this->create_board((int) $owner->id);

        // First dispatch — message expected.
        $sink1 = $this->trigger_event($boardid, [[
            'userid'   => (int) $learner->id,
            'old_rank' => 20,
            'new_rank' => 8,
            'reason'   => message_helper::REASON_LARGE_MOVE,
        ]]);
        $first = $this->ours($sink1);
        $sink1->close();
        $this->assertCount(1, $first,
            'first dispatch must send exactly one message');

        // The throttle log row must now exist.
        $logrow = $DB->get_record('local_sentientia_lb_notify_log',
            ['boardid' => $boardid, 'userid' => $learner->id], '*',
            MUST_EXIST);
        $this->assertGreaterThan(0, (int) $logrow->last_sent);

        // Second dispatch immediately — throttle must block it.
        $sink2 = $this->trigger_event($boardid, [[
            'userid'   => (int) $learner->id,
            'old_rank' => 8,
            'new_rank' => 1,
            'reason'   => message_helper::REASON_LARGE_MOVE,
        ]]);
        $second = $this->ours($sink2);
        $sink2->close();
        $this->assertCount(0, $second,
            'second dispatch within 24h must be throttled');

        // Backdate the log row beyond the 24h window — throttle releases.
        $DB->set_field('local_sentientia_lb_notify_log', 'last_sent',
            time() - (message_helper::THROTTLE_SECONDS + 60),
            ['id' => $logrow->id]);

        $sink3 = $this->trigger_event($boardid, [[
            'userid'   => (int) $learner->id,
            'old_rank' => 1,
            'new_rank' => 10,           // 9 down — still a large move
            'reason'   => message_helper::REASON_LARGE_MOVE,
        ]]);
        $third = $this->ours($sink3);
        $sink3->close();
        $this->assertCount(1, $third,
            'a dispatch after the 24h window must send again');
    }

    /**
     * Required test: a learner who enters the top 10 — even if the
     * positional movement is smaller than the LARGE_MOVE_THRESHOLD —
     * receives a celebration message.
     */
    public function test_top_10_entry_triggers_message(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        $this->set_notifications_flag(true);

        $owner = $this->create_user();
        $learner = $this->create_user();
        $boardid = $this->create_board((int) $owner->id);

        // Rank 11 → 9 is only a 2-position shift, well under the
        // LARGE_MOVE_THRESHOLD (5). But it crosses the top-10 gate, so
        // the celebration rule fires.
        $sink = $this->trigger_event($boardid, [[
            'userid'   => (int) $learner->id,
            'old_rank' => 11,
            'new_rank' => 9,
            'reason'   => message_helper::REASON_TOP10_ENTRY,
        ]]);

        $messages = $this->ours($sink);
        $sink->close();

        $this->assertCount(1, $messages,
            'top-10 entry must dispatch a celebration message');
        $this->assertStringContainsString('top',
            strtolower($messages[0]->subject),
            'top-10 entry subject must reference "top"');
    }

    // ─── supporting tests for the helper ─────────────────────────────

    public function test_classify_change_emits_top10_for_fresh_entry(): void {
        $this->assertSame(message_helper::REASON_TOP10_ENTRY,
            message_helper::classify_change(11, 9));
        $this->assertSame(message_helper::REASON_TOP10_ENTRY,
            message_helper::classify_change(0,  3),
            'no prior rank + new rank inside top-N counts as a top-10 entry');
    }

    public function test_classify_change_emits_large_move_for_5_plus(): void {
        $this->assertSame(message_helper::REASON_LARGE_MOVE,
            message_helper::classify_change(20, 15));   // exactly 5 up
        $this->assertSame(message_helper::REASON_LARGE_MOVE,
            message_helper::classify_change(15, 25));   // 10 down
    }

    public function test_classify_change_returns_null_for_minor_move(): void {
        $this->assertNull(message_helper::classify_change(20, 17),
            '3-position move outside top-N is not interesting');
        $this->assertNull(message_helper::classify_change(0, 50),
            'fresh entry far outside top-N is not interesting');
    }

    public function test_compute_changes_filters_quiet_users(): void {
        $changes = message_helper::compute_changes(
            ['1' => 5, '2' => 20, '3' => 19],   // old
            ['1' => 5, '2' => 21, '3' => 14]    // new (only #3 moved 5)
        );
        $this->assertCount(1, $changes);
        $this->assertSame(3, $changes[0]['userid']);
        $this->assertSame(message_helper::REASON_LARGE_MOVE,
            $changes[0]['reason']);
    }

    public function test_compute_changes_marks_top10_entry_reason(): void {
        $changes = message_helper::compute_changes(
            ['7' => 11],
            ['7' => 9]
        );
        $this->assertCount(1, $changes);
        $this->assertSame(message_helper::REASON_TOP10_ENTRY,
            $changes[0]['reason']);
    }

    public function test_opted_out_user_never_receives_message(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        $this->set_notifications_flag(true);

        $owner = $this->create_user();
        $learner = $this->create_user();
        $boardid = $this->create_board((int) $owner->id);

        optout_manager::opt_out((int) $learner->id);

        $sink = $this->trigger_event($boardid, [[
            'userid'   => (int) $learner->id,
            'old_rank' => 30,
            'new_rank' => 5,
            'reason'   => message_helper::REASON_LARGE_MOVE,
        ]]);

        $messages = $this->ours($sink);
        $sink->close();

        $this->assertCount(0, $messages,
            'opted-out user must never receive a rank-change message');
    }

    /**
     * Integration smoke: a real recompute over completion data triggers
     * the event, the observer dispatches, and the learner gets a message.
     * Proves the wiring works end-to-end through the event bus.
     */
    public function test_recompute_triggers_message_end_to_end(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        $this->set_notifications_flag(true);

        $owner = $this->create_user();
        $learner = $this->create_user();

        // Seed a completion for the learner.
        $course = $this->getDataGenerator()->create_course(
            ['enablecompletion' => 1]);
        $now = time();
        $DB->insert_record('course_completions', (object) [
            'userid'        => $learner->id,
            'course'        => $course->id,
            'timeenrolled'  => $now - 50,
            'timestarted'   => $now - 50,
            'timecompleted' => $now,
            'reaggregate'   => 0,
        ]);

        $boardid = board_manager::create([
            'name'     => 'Integration',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner->id,
            'tenantid' => 1,
        ]);

        // Pre-seed an entry that puts the learner at a high rank — so
        // the first real recompute looks like an improvement worth
        // celebrating (rank 12 -> 1 = both top-10 entry AND large move).
        $DB->insert_record('local_sentientia_lb_entries', (object) [
            'boardid'         => $boardid,
            'userid'          => $learner->id,
            'points'          => -999999,
            'secondary'       => 0,
            'userrank'        => 12,
            'costcenterid'    => 1,
            'last_recomputed' => $now - 200,
        ]);

        $sink = $this->redirectMessages();
        ranking_engine::recompute($boardid);
        $messages = $this->ours($sink);
        $sink->close();

        $this->assertGreaterThanOrEqual(1, count($messages),
            'a real recompute that shifts rank 12 -> 1 must dispatch a message');
        $msg = $messages[0];
        $this->assertSame((int) $learner->id, (int) $msg->useridto);
    }

    /**
     * Wave C5 chip lock-in: drive the chain through the scheduled-task
     * entry point `recompute_due_boards::execute()` rather than calling
     * `ranking_engine::recompute()` directly. This is the actual cron
     * surface a deployed Moodle hits every 2 minutes — the chip brief
     * names it explicitly, so it deserves a test that exercises the
     * wrapper + the master flag gate the wrapper carries.
     */
    public function test_recompute_due_boards_task_runs_full_chain(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        // Both flags ON — master + notifications.
        feature_flags::set('sentientia.leaderboards.enabled',
            0, true, (int) $GLOBALS['USER']->id, 'phpunit');
        $this->set_notifications_flag(true);

        $owner = $this->create_user();
        $learner = $this->create_user();

        $course = $this->getDataGenerator()->create_course(
            ['enablecompletion' => 1]);
        $now = time();
        $DB->insert_record('course_completions', (object) [
            'userid'        => $learner->id,
            'course'        => $course->id,
            'timeenrolled'  => $now - 50,
            'timestarted'   => $now - 50,
            'timecompleted' => $now,
            'reaggregate'   => 0,
        ]);

        $boardid = board_manager::create([
            'name'     => 'TaskWrapper',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner->id,
            'tenantid' => 1,
        ]);

        // Pre-seed a stale rank so the recompute looks like a large move.
        $DB->insert_record('local_sentientia_lb_entries', (object) [
            'boardid'         => $boardid,
            'userid'          => $learner->id,
            'points'          => -999999,
            'secondary'       => 0,
            'userrank'        => 15,
            'costcenterid'    => 1,
            'last_recomputed' => $now - 200,
        ]);

        // Backdate the board so board_manager::boards_due_for_recompute()
        // picks it up on this tick.
        $DB->set_field('local_sentientia_lb_boards', 'last_recomputed',
            $now - 9000, ['id' => $boardid]);

        $sink = $this->redirectMessages();
        $task = new \local_sentientia_leaderboard\task\recompute_due_boards();
        $task->execute();
        $messages = $this->ours($sink);
        $sink->close();

        $this->assertGreaterThanOrEqual(1, count($messages),
            'scheduled task entry point must drive the full event chain');
        $this->assertSame((int) $learner->id, (int) $messages[0]->useridto,
            'message must arrive at the affected learner');
    }

    /**
     * Wave C5 chip lock-in: when a recompute produces zero qualifying
     * rank changes, the `rankings_updated` event must NOT fire — this
     * defends Moodle's standard log from per-tick noise on quiet boards.
     * The contract is documented in ranking_engine.php lines 109-110.
     */
    public function test_recompute_skips_event_when_no_qualifying_changes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->set_notifications_flag(true);

        $u = $this->create_user();
        $course = $this->getDataGenerator()->create_course(
            ['enablecompletion' => 1]);
        $now = time();
        $DB->insert_record('course_completions', (object) [
            'userid'        => $u->id,
            'course'        => $course->id,
            'timeenrolled'  => $now - 50,
            'timestarted'   => $now - 50,
            'timecompleted' => $now,
            'reaggregate'   => 0,
        ]);

        $boardid = board_manager::create([
            'name'     => 'Quiet',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $u->id,
            'tenantid' => 1,
        ]);

        // First recompute primes the table with the learner at rank 1.
        ranking_engine::recompute($boardid);
        $this->assertSame(1, (int) $DB->get_field(
            'local_sentientia_lb_entries', 'userrank',
            ['boardid' => $boardid, 'userid' => $u->id]),
            'priming recompute must place the only learner at rank 1');

        // Second recompute with no data change: nobody moves, nobody
        // crosses the top-10 gate freshly (rank 1 was already inside).
        // Expect zero `rankings_updated` events and zero messages.
        $event_sink = $this->redirectEvents();
        $msg_sink   = $this->redirectMessages();
        ranking_engine::recompute($boardid);
        $events = $event_sink->get_events();
        $messages = $this->ours($msg_sink);
        $event_sink->close();
        $msg_sink->close();

        $ours = array_filter($events, function($e) {
            return $e instanceof event\rankings_updated;
        });
        $this->assertCount(0, $ours,
            'idempotent recompute with no rank shifts must skip the event');
        $this->assertCount(0, $messages,
            'no qualifying changes => no messages');
    }

    /**
     * Wave C5 chip lock-in: when the recompute does fire the event, its
     * payload must carry the expected shape — `objectid` set to the
     * boardid, `other.changes` populated with the userid + old_rank +
     * new_rank + reason quadruple per affected learner. This is the
     * exact contract observer.php reads against (lines 49-56), so a
     * regression here breaks the chain silently.
     */
    public function test_rankings_updated_event_carries_changes_payload(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Notifications flag OFF — we're testing the event emission
        // contract from recompute, not the downstream dispatch.
        $this->set_notifications_flag(false);

        $owner = $this->create_user();
        $learner = $this->create_user();

        $course = $this->getDataGenerator()->create_course(
            ['enablecompletion' => 1]);
        $now = time();
        $DB->insert_record('course_completions', (object) [
            'userid'        => $learner->id,
            'course'        => $course->id,
            'timeenrolled'  => $now - 50,
            'timestarted'   => $now - 50,
            'timecompleted' => $now,
            'reaggregate'   => 0,
        ]);

        $boardid = board_manager::create([
            'name'     => 'PayloadShape',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => (int) $course->id,
            'ownerid'  => (int) $owner->id,
            'tenantid' => 1,
        ]);

        // Pre-seed rank 30 so the upcoming recompute (puts learner at
        // rank 1) qualifies as BOTH a top-10 entry AND a large move.
        // top-10 entry wins because classify_change() returns the more
        // celebratory reason first.
        $DB->insert_record('local_sentientia_lb_entries', (object) [
            'boardid'         => $boardid,
            'userid'          => $learner->id,
            'points'          => -999999,
            'secondary'       => 0,
            'userrank'        => 30,
            'costcenterid'    => 1,
            'last_recomputed' => $now - 200,
        ]);

        $sink = $this->redirectEvents();
        ranking_engine::recompute($boardid);
        $events = $sink->get_events();
        $sink->close();

        $ours = array_values(array_filter($events, function($e) {
            return $e instanceof event\rankings_updated;
        }));
        $this->assertCount(1, $ours,
            'exactly one rankings_updated event must fire per recompute '
            . 'with qualifying changes');
        $event = $ours[0];
        $this->assertSame($boardid, (int) $event->objectid,
            'event objectid must be the recomputed board id');
        $this->assertArrayHasKey('changes', (array) $event->other,
            'event other-payload must carry a changes list');
        $changes = $event->other['changes'];
        $this->assertNotEmpty($changes,
            'changes payload must include the affected learner');

        $matching = array_values(array_filter($changes, function($c) use ($learner) {
            return (int) $c['userid'] === (int) $learner->id;
        }));
        $this->assertCount(1, $matching,
            'changes list must include the affected learner exactly once');
        $row = $matching[0];
        $this->assertSame((int) $learner->id, (int) $row['userid']);
        $this->assertSame(30, (int) $row['old_rank'],
            'old_rank must reflect the pre-recompute snapshot');
        $this->assertSame(1, (int) $row['new_rank'],
            'new_rank must reflect the post-recompute assigned rank');
        $this->assertSame(message_helper::REASON_TOP10_ENTRY,
            $row['reason'],
            'a 30 -> 1 shift crosses the top-10 gate AND exceeds the '
            . 'large-move threshold; top-10 wins per classify_change()');
    }
}
