<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for event_journal — Phase E.1.b.
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\event_journal
 */
final class event_journal_test extends \advanced_testcase {

    public function test_write_inserts_row_with_decoded_payload(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $event_id = event_journal::write($sid, 'session_started', [
            'started_at' => 1234567890,
        ]);
        $this->assertGreaterThan(0, $event_id);

        $events = event_journal::read_since($sid, 0);
        $this->assertCount(1, $events);
        $this->assertSame('session_started', $events[0]->type);
        $this->assertSame(1234567890, $events[0]->payload['started_at']);
    }

    public function test_write_rejects_unknown_event_type(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        event_journal::write(1, 'totally_made_up_type', []);
    }

    public function test_read_since_returns_in_id_order(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $id1 = event_journal::write($sid, 'session_started', []);
        $id2 = event_journal::write($sid, 'slide_changed', ['slide_id' => 1]);
        $id3 = event_journal::write($sid, 'response_added', ['count_now' => 1]);

        $events = event_journal::read_since($sid, 0);
        $this->assertCount(3, $events);
        $this->assertSame($id1, $events[0]->id);
        $this->assertSame($id2, $events[1]->id);
        $this->assertSame($id3, $events[2]->id);
    }

    public function test_read_since_filters_by_last_event_id(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $id1 = event_journal::write($sid, 'session_started', []);
        $id2 = event_journal::write($sid, 'slide_changed', ['slide_id' => 1]);
        $id3 = event_journal::write($sid, 'response_added', ['count_now' => 1]);

        // Resume from after event 1 — should get 2 + 3.
        $events = event_journal::read_since($sid, $id1);
        $this->assertCount(2, $events);
        $this->assertSame($id2, $events[0]->id);
        $this->assertSame($id3, $events[1]->id);

        // Resume from after the latest — should get nothing.
        $events = event_journal::read_since($sid, $id3);
        $this->assertCount(0, $events);
    }

    public function test_read_since_isolates_sessions(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid_a = session_manager::create($user->id, 'A');
        $sid_b = session_manager::create($user->id, 'B');

        event_journal::write($sid_a, 'session_started', []);
        event_journal::write($sid_b, 'session_started', []);

        $a_events = event_journal::read_since($sid_a, 0);
        $b_events = event_journal::read_since($sid_b, 0);

        $this->assertCount(1, $a_events, 'Session A should only see its own events');
        $this->assertCount(1, $b_events, 'Session B should only see its own events');
    }

    public function test_read_since_caps_at_batch_max(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        // Insert 105 events.
        for ($i = 0; $i < 105; $i++) {
            event_journal::write($sid, 'response_added', ['n' => $i]);
        }

        $batch = event_journal::read_since($sid, 0);
        $this->assertCount(event_journal::POLL_BATCH_MAX, $batch,
            'Should cap at POLL_BATCH_MAX (default 100)');
    }

    public function test_latest_event_id(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $this->assertSame(0, event_journal::latest_event_id($sid),
            'Empty session should return 0');

        event_journal::write($sid, 'session_started', []);
        $id2 = event_journal::write($sid, 'slide_changed', []);
        $id3 = event_journal::write($sid, 'response_added', []);

        $this->assertSame($id3, event_journal::latest_event_id($sid));
    }

    public function test_count_by_type(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        event_journal::write($sid, 'response_added', ['count_now' => 1]);
        event_journal::write($sid, 'response_added', ['count_now' => 2]);
        event_journal::write($sid, 'response_added', ['count_now' => 3]);
        event_journal::write($sid, 'slide_changed', []);

        $this->assertSame(3,
            event_journal::count_by_type($sid, 'response_added'));
        $this->assertSame(1,
            event_journal::count_by_type($sid, 'slide_changed'));
        $this->assertSame(0,
            event_journal::count_by_type($sid, 'session_started'));
    }

    public function test_purge_old_only_deletes_from_ended_sessions(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // Session A — ended 2 days ago. Should be purged.
        $sid_a = session_manager::create($user->id, 'A');
        session_manager::start_session($sid_a);
        session_manager::end_session($sid_a);
        $DB->set_field('local_sentientia_live_sessions', 'timeended',
            time() - (2 * 86400), ['id' => $sid_a]);
        event_journal::write($sid_a, 'response_added', []);

        // Session B — still live. Should NOT be purged.
        $sid_b = session_manager::create($user->id, 'B');
        session_manager::start_session($sid_b);
        event_journal::write($sid_b, 'response_added', []);

        // Session C — ended 12h ago, INSIDE 24h retention window. Should NOT be purged.
        $sid_c = session_manager::create($user->id, 'C');
        session_manager::start_session($sid_c);
        session_manager::end_session($sid_c);
        $DB->set_field('local_sentientia_live_sessions', 'timeended',
            time() - (12 * 3600), ['id' => $sid_c]);
        event_journal::write($sid_c, 'response_added', []);

        $purged = event_journal::purge_old(event_journal::DEFAULT_RETENTION_SECONDS);

        // Session A had events for its lifecycle (start + end) + 1 we wrote = 3.
        // Plus the data we wrote for B and C. Let's just check by session:
        $this->assertGreaterThan(0, $purged);
        $this->assertEmpty(event_journal::read_since($sid_a, 0),
            'Session A events should be purged');
        $this->assertNotEmpty(event_journal::read_since($sid_b, 0),
            'Session B (still live) should NOT be purged');
        $this->assertNotEmpty(event_journal::read_since($sid_c, 0),
            'Session C (inside retention window) should NOT be purged');
    }
}
