<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for {@see event_journal}.
 *
 * Locks in:
 *   - write requires a valid event type
 *   - read_since returns events ordered by id ASC
 *   - read_since honours $last_event_id (no replays)
 *   - purge_old deletes events past the retention window
 *
 * @package    local_sentientia_leaderboard
 * @category   test
 * @covers     \local_sentientia_leaderboard\event_journal
 */
final class event_journal_test extends \advanced_testcase {

    public function test_write_requires_known_event_type(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        event_journal::write(1, 'not_a_real_type', []);
    }

    public function test_write_then_read_returns_payload(): void {
        $this->resetAfterTest();

        $id = event_journal::write(42, 'leaderboard.recomputed',
            ['boardid' => 42, 'recomputed_at' => 1234567890]);
        $this->assertGreaterThan(0, $id);

        $events = event_journal::read_since(42, 0);
        $this->assertCount(1, $events);
        $e = $events[0];
        $this->assertSame('leaderboard.recomputed', $e->type);
        $this->assertSame(42, $e->payload['boardid']);
    }

    public function test_read_since_honours_last_event_id(): void {
        $this->resetAfterTest();

        $a = event_journal::write(1, 'leaderboard.recomputed', ['ts' => 1]);
        $b = event_journal::write(1, 'leaderboard.recomputed', ['ts' => 2]);
        $c = event_journal::write(1, 'leaderboard.recomputed', ['ts' => 3]);

        $events = event_journal::read_since(1, $a);
        $this->assertCount(2, $events,
            'read_since must skip events <= last_event_id');
        $this->assertSame($b, $events[0]->id);
        $this->assertSame($c, $events[1]->id);
    }

    public function test_read_since_filters_by_boardid(): void {
        $this->resetAfterTest();

        event_journal::write(1, 'leaderboard.recomputed', []);
        event_journal::write(2, 'leaderboard.recomputed', []);
        event_journal::write(1, 'leaderboard.recomputed', []);

        $events = event_journal::read_since(1);
        $this->assertCount(2, $events,
            'read_since must filter by boardid');
    }

    public function test_purge_old_deletes_old_events(): void {
        global $DB;
        $this->resetAfterTest();

        $id1 = event_journal::write(1, 'leaderboard.recomputed', []);
        $id2 = event_journal::write(1, 'leaderboard.recomputed', []);

        // Force the first event into the past.
        $DB->set_field('local_sentientia_lb_events', 'timecreated',
            time() - (10 * 86400), ['id' => $id1]);

        $deleted = event_journal::purge_old(7 * 86400);
        $this->assertSame(1, $deleted);
        $this->assertFalse($DB->record_exists('local_sentientia_lb_events',
            ['id' => $id1]));
        $this->assertTrue($DB->record_exists('local_sentientia_lb_events',
            ['id' => $id2]));
    }

    public function test_latest_event_id_returns_max(): void {
        $this->resetAfterTest();

        $this->assertSame(0, event_journal::latest_event_id(99),
            'no events for a board returns 0');

        event_journal::write(99, 'leaderboard.recomputed', []);
        $second = event_journal::write(99, 'leaderboard.recomputed', []);
        $this->assertSame($second, event_journal::latest_event_id(99));
    }
}
