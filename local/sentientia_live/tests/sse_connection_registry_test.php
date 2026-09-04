<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for sse_connection_registry — H4 remediation (2026-09-04).
 *
 * Covers acquire()/heartbeat()/release()/prune() and the two caps
 * (global via sse_max_connections, fixed per-actor cap of 2) that
 * stream.php enforces before it will open a Server-Sent-Events stream.
 * See UAT-SECURITY-POSTURE-2026-09-03.md finding H4.
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\sse_connection_registry
 */
final class sse_connection_registry_test extends \advanced_testcase {

    public function test_acquire_inserts_row_and_returns_ok(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $conn = sse_connection_registry::acquire($sid, $user->id, 'u:' . $user->id, 8, 15);

        $this->assertTrue($conn->ok);
        $this->assertGreaterThan(0, $conn->id);
        $this->assertNull($conn->reason);
        $this->assertSame(1, sse_connection_registry::count_active());
    }

    public function test_release_frees_the_slot_and_is_idempotent(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $conn = sse_connection_registry::acquire($sid, $user->id, 'u:' . $user->id, 8, 15);
        $this->assertTrue($conn->ok);

        sse_connection_registry::release($conn->id);
        $this->assertSame(0, sse_connection_registry::count_active());

        // A second release for the same id must not error — it simply
        // matches zero rows. stream.php relies on this via a
        // register_shutdown_function that can, in edge cases, run
        // alongside an explicit release earlier in the same request.
        sse_connection_registry::release($conn->id);
        $this->assertSame(0, sse_connection_registry::count_active());
    }

    public function test_heartbeat_updates_timeheartbeat(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $conn = sse_connection_registry::acquire($sid, $user->id, 'u:' . $user->id, 8, 15);

        // Backdate the row so we can observe heartbeat() move it forward.
        $DB->set_field('local_sentientia_live_sse', 'timeheartbeat', 1, ['id' => $conn->id]);
        sse_connection_registry::heartbeat($conn->id);

        $row = $DB->get_record('local_sentientia_live_sse', ['id' => $conn->id]);
        $this->assertGreaterThan(1, (int) $row->timeheartbeat);
    }

    public function test_global_cap_rejects_once_reached(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        // Distinct actor keys so this exercises only the GLOBAL cap, not
        // the fixed per-actor cap (MAX_PER_ACTOR = 2).
        $ok1 = sse_connection_registry::acquire($sid, null, 'p:1', 2, 15);
        $ok2 = sse_connection_registry::acquire($sid, null, 'p:2', 2, 15);
        $this->assertTrue($ok1->ok);
        $this->assertTrue($ok2->ok);

        $blocked = sse_connection_registry::acquire($sid, null, 'p:3', 2, 15);
        $this->assertFalse($blocked->ok);
        $this->assertSame('global', $blocked->reason);
        $this->assertNull($blocked->id);

        // The blocked attempt must not have inserted a row.
        $this->assertSame(2, sse_connection_registry::count_active());
    }

    public function test_per_actor_cap_rejects_a_third_stream_for_the_same_actor(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $ok1 = sse_connection_registry::acquire($sid, null, 'p:42', 100, 15);
        $ok2 = sse_connection_registry::acquire($sid, null, 'p:42', 100, 15);
        $this->assertTrue($ok1->ok);
        $this->assertTrue($ok2->ok);

        $blocked = sse_connection_registry::acquire($sid, null, 'p:42', 100, 15);
        $this->assertFalse($blocked->ok);
        $this->assertSame('peractor', $blocked->reason);

        // A different actor is unaffected by another actor's cap, even
        // though the global cap (100) is nowhere near reached.
        $otheractor = sse_connection_registry::acquire($sid, null, 'p:43', 100, 15);
        $this->assertTrue($otheractor->ok);
    }

    public function test_acquire_falls_back_to_default_when_max_connections_non_positive(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        // max_connections <= 0 must fall back to DEFAULT_MAX_CONNECTIONS
        // (8) — a misconfigured setting must never mean "uncapped".
        for ($i = 0; $i < sse_connection_registry::DEFAULT_MAX_CONNECTIONS; $i++) {
            $conn = sse_connection_registry::acquire($sid, null, 'p:' . $i, 0, 15);
            $this->assertTrue($conn->ok, "connection $i should have been accepted");
        }
        $blocked = sse_connection_registry::acquire($sid, null, 'p:overflow', 0, 15);
        $this->assertFalse($blocked->ok);
        $this->assertSame('global', $blocked->reason);
    }

    public function test_prune_removes_stale_rows_and_keeps_fresh_ones(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $fresh = sse_connection_registry::acquire($sid, null, 'p:fresh', 8, 15);
        $stale = sse_connection_registry::acquire($sid, null, 'p:stale', 8, 15);
        $this->assertTrue($fresh->ok);
        $this->assertTrue($stale->ok);

        // Backdate the "stale" row beyond 2x the 15s heartbeat interval
        // (STALE_HEARTBEAT_MULTIPLIER).
        $DB->set_field('local_sentientia_live_sse', 'timeheartbeat',
            time() - 100, ['id' => $stale->id]);

        $deleted = sse_connection_registry::prune(15);

        $this->assertSame(1, $deleted);
        $this->assertSame(1, sse_connection_registry::count_active());
        $this->assertTrue($DB->record_exists('local_sentientia_live_sse', ['id' => $fresh->id]));
        $this->assertFalse($DB->record_exists('local_sentientia_live_sse', ['id' => $stale->id]));
    }

    public function test_acquire_prunes_stale_rows_before_checking_caps(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        $stale = sse_connection_registry::acquire($sid, null, 'p:stale', 1, 15);
        $this->assertTrue($stale->ok);
        $DB->set_field('local_sentientia_live_sse', 'timeheartbeat',
            time() - 1000, ['id' => $stale->id]);

        // With max_connections=1, a naive check would see the stale row
        // and reject this — acquire() must prune it first so the global
        // cap reflects only genuinely live connections.
        $fresh = sse_connection_registry::acquire($sid, null, 'p:fresh', 1, 15);
        $this->assertTrue($fresh->ok);
        $this->assertSame(1, sse_connection_registry::count_active());
    }

    public function test_count_active_for_actor(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        sse_connection_registry::acquire($sid, null, 'p:99', 8, 15);
        sse_connection_registry::acquire($sid, null, 'p:99', 8, 15);
        sse_connection_registry::acquire($sid, null, 'p:100', 8, 15);

        $this->assertSame(2, sse_connection_registry::count_active_for_actor('p:99'));
        $this->assertSame(1, sse_connection_registry::count_active_for_actor('p:100'));
        $this->assertSame(0, sse_connection_registry::count_active_for_actor('p:none'));
    }
}
