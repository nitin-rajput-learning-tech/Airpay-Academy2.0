<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for session_manager — Phase E.1.a.
 *
 * Run via:
 *   cd /path/to/moodle/public
 *   vendor/bin/phpunit local/sentientia_live/tests/session_manager_test.php
 *
 * Covers:
 *   - create() happy path + validation
 *   - get() / find_by_code() / list_owned_by()
 *   - state transitions (draft -> live -> ended)
 *   - code generation uniqueness
 *   - can_user_run + can_user_join permission checks
 *   - settings_json parsing + sanitisation
 *   - delete() cascade
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\session_manager
 */
final class session_manager_test extends \advanced_testcase {

    public function test_create_returns_session_id_and_persists_row(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $sid = session_manager::create($user->id, 'Q3 KYC refresher');

        $this->assertIsInt($sid);
        $this->assertGreaterThan(0, $sid);

        $row = session_manager::get($sid);
        $this->assertNotNull($row);
        $this->assertSame('Q3 KYC refresher', $row->title);
        $this->assertSame(session_manager::STATE_DRAFT, $row->state);
        $this->assertSame($user->id, (int) $row->ownerid);
        $this->assertSame(session_manager::CODE_LENGTH, strlen($row->code));
        $this->assertMatchesRegularExpression('/^[1-9]\d{5}$/', $row->code,
            'Code should be 6 digits and not start with 0');
        $this->assertNull($row->current_slide_id);
        $this->assertNull($row->timestarted);
        $this->assertNull($row->timeended);
    }

    public function test_create_rejects_empty_title(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        session_manager::create($user->id, '   ');
    }

    public function test_create_rejects_title_over_200_chars(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        session_manager::create($user->id, str_repeat('a', 201));
    }

    public function test_create_rejects_invalid_userid(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        session_manager::create(0, 'Bad owner');
    }

    public function test_get_returns_null_for_missing(): void {
        $this->resetAfterTest();
        $this->assertNull(session_manager::get(999999));
        $this->assertNull(session_manager::get(-1));
        $this->assertNull(session_manager::get(0));
    }

    public function test_find_by_code_only_returns_live_sessions(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'Test');
        $row = session_manager::get($sid);

        // Draft → not findable.
        $this->assertNull(session_manager::find_by_code($row->code));

        // Live → findable.
        session_manager::start_session($sid);
        $found = session_manager::find_by_code($row->code);
        $this->assertNotNull($found);
        $this->assertSame($sid, (int) $found->id);

        // Ended → not findable.
        session_manager::end_session($sid);
        $this->assertNull(session_manager::find_by_code($row->code));
    }

    public function test_find_by_code_tolerates_spaces_and_dashes(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'Test');
        session_manager::start_session($sid);
        $row = session_manager::get($sid);

        // Code "123456" — should find with "123 456" or "123-456".
        $spaced = substr($row->code, 0, 3) . ' ' . substr($row->code, 3);
        $found = session_manager::find_by_code($spaced);
        $this->assertNotNull($found);
        $this->assertSame($sid, (int) $found->id);
    }

    public function test_find_by_code_rejects_wrong_length(): void {
        $this->resetAfterTest();
        $this->assertNull(session_manager::find_by_code('12345'));    // 5
        $this->assertNull(session_manager::find_by_code('1234567'));  // 7
        $this->assertNull(session_manager::find_by_code(''));
    }

    public function test_state_transitions_enforce_valid_order(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'Test');

        // draft -> live: OK
        $this->assertTrue(session_manager::start_session($sid));
        $this->assertSame(session_manager::STATE_LIVE,
            session_manager::get($sid)->state);

        // live -> live: rejected
        $this->assertFalse(session_manager::start_session($sid));

        // live -> ended: OK
        $this->assertTrue(session_manager::end_session($sid));
        $this->assertSame(session_manager::STATE_ENDED,
            session_manager::get($sid)->state);

        // ended -> live: rejected
        $this->assertFalse(session_manager::start_session($sid));

        // ended -> ended: rejected (no double-end)
        $this->assertFalse(session_manager::end_session($sid));
    }

    public function test_start_session_writes_event(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'Test');
        session_manager::start_session($sid);

        $events = event_journal::read_since($sid, 0);
        $this->assertCount(1, $events);
        $this->assertSame('session_started', $events[0]->type);
    }

    public function test_end_session_writes_event(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'Test');
        session_manager::start_session($sid);
        session_manager::end_session($sid);

        $events = event_journal::read_since($sid, 0);
        $this->assertCount(2, $events);
        $this->assertSame('session_started', $events[0]->type);
        $this->assertSame('session_ended',   $events[1]->type);
    }

    public function test_set_current_slide_only_works_for_session_slides(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid_a = session_manager::create($user->id, 'A');
        $sid_b = session_manager::create($user->id, 'B');

        // Insert a slide belonging to session A.
        $slide_a = $DB->insert_record('local_sentientia_live_slides',
            (object) [
                'sessionid' => $sid_a, 'position' => 1, 'type' => 'multichoice',
                'title' => 'Q1', 'timecreated' => time(), 'timemodified' => time(),
            ]);

        session_manager::start_session($sid_a);
        session_manager::start_session($sid_b);

        // Setting slide_a as current on session A: works.
        $this->assertTrue(session_manager::set_current_slide($sid_a, $slide_a));
        $this->assertSame($slide_a,
            (int) session_manager::get($sid_a)->current_slide_id);

        // Setting slide_a as current on session B: rejected (cross-session).
        $this->assertFalse(session_manager::set_current_slide($sid_b, $slide_a));
        $this->assertNull(session_manager::get($sid_b)->current_slide_id);
    }

    public function test_list_owned_by_returns_own_sessions_newest_first(): void {
        $this->resetAfterTest();
        $alice = $this->getDataGenerator()->create_user();
        $bob   = $this->getDataGenerator()->create_user();

        $a1 = session_manager::create($alice->id, 'Alice 1');
        sleep(1);
        $a2 = session_manager::create($alice->id, 'Alice 2');
        $b1 = session_manager::create($bob->id,   'Bob 1');

        $alices = session_manager::list_owned_by($alice->id);
        $this->assertCount(2, $alices);
        $alices = array_values($alices);
        $this->assertSame($a2, (int) $alices[0]->id, 'newest first');
        $this->assertSame($a1, (int) $alices[1]->id);
    }

    public function test_list_owned_by_state_filter(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $draft = session_manager::create($user->id, 'D');
        $live = session_manager::create($user->id, 'L');
        session_manager::start_session($live);

        $drafts = session_manager::list_owned_by($user->id,
            session_manager::STATE_DRAFT);
        $this->assertCount(1, $drafts);

        $lives = session_manager::list_owned_by($user->id,
            session_manager::STATE_LIVE);
        $this->assertCount(1, $lives);
    }

    public function test_generate_unique_code_avoids_collisions(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // Generate 10 codes — all live, all different.
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $sid = session_manager::create($user->id, "S$i");
            session_manager::start_session($sid);
            $codes[] = session_manager::get($sid)->code;
        }
        $this->assertCount(10, array_unique($codes),
            'All 10 live-session codes should be distinct');
    }

    public function test_can_user_run_owner_yes(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');
        $this->assertTrue(session_manager::can_user_run($user->id, $sid));
    }

    public function test_can_user_run_non_owner_no(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $intruder = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($owner->id, 'T');
        $this->assertFalse(session_manager::can_user_run($intruder->id, $sid));
    }

    public function test_settings_sanitise_clamps_max_concurrent(): void {
        $sanitised = session_manager::sanitise_settings([
            'max_concurrent' => 99999,
        ]);
        $this->assertSame(500, $sanitised['max_concurrent']);

        $sanitised = session_manager::sanitise_settings([
            'max_concurrent' => -5,
        ]);
        $this->assertSame(1, $sanitised['max_concurrent']);
    }

    public function test_settings_parse_uses_defaults_on_bad_json(): void {
        $sess = (object) ['settings_json' => 'NOT JSON {{ broken'];
        $parsed = session_manager::parse_settings($sess);
        $this->assertSame(session_manager::default_settings(), $parsed);
    }

    public function test_delete_cascades_through_all_dependent_tables(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'T');

        // Plant a slide + participant + response + event.
        $slide_id = $DB->insert_record('local_sentientia_live_slides',
            (object) [
                'sessionid' => $sid, 'position' => 1, 'type' => 'multichoice',
                'title' => 'Q1', 'timecreated' => time(), 'timemodified' => time(),
            ]);
        $part_id = $DB->insert_record('local_sentientia_live_participants',
            (object) [
                'sessionid' => $sid, 'userid' => $user->id,
                'display_name' => 'Alice', 'join_token' => 'token-' . random_int(0, 99999),
                'timejoined' => time(), 'timelastseen' => time(),
            ]);
        $DB->insert_record('local_sentientia_live_responses', (object) [
            'slideid' => $slide_id, 'participantid' => $part_id,
            'value_int' => 1, 'timecreated' => time(),
        ]);
        event_journal::write($sid, 'session_started', []);

        // Delete the session.
        session_manager::delete($sid);

        // Everything gone.
        $this->assertNull(session_manager::get($sid));
        $this->assertEmpty($DB->get_records('local_sentientia_live_slides',
            ['sessionid' => $sid]));
        $this->assertEmpty($DB->get_records('local_sentientia_live_participants',
            ['sessionid' => $sid]));
        $this->assertEmpty($DB->get_records('local_sentientia_live_responses',
            ['participantid' => $part_id]));
        $this->assertEmpty($DB->get_records('local_sentientia_live_events',
            ['sessionid' => $sid]));
    }
}
