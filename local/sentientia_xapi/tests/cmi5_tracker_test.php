<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * PHPUnit tests for the cmi5 session tracker.
 *
 * Covers: session creation on initialized, status progression,
 * terminal state enforcement, and tenant isolation.
 *
 * @package    local_sentientia_xapi
 * @category   phpunit
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_xapi\tests;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_xapi\lrs\cmi5_tracker;
use local_sentientia_xapi\model\statement;

/**
 * @covers \local_sentientia_xapi\lrs\cmi5_tracker
 */
class cmi5_tracker_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    private function make_cmi5_stmt(string $verb_key, string $registration, array $result = []): array {
        $verb_iri = cmi5_tracker::CMI5_VERBS[$verb_key] ?? 'http://adlnet.gov/expapi/verbs/' . $verb_key;
        $data = [
            'id'      => statement::generate_uuid(),
            'actor'   => ['objectType' => 'Agent', 'account' => ['homePage' => 'https://airpay.academy', 'name' => '1']],
            'verb'    => ['id' => $verb_iri, 'display' => ['en-US' => $verb_key]],
            'object'  => ['objectType' => 'Activity', 'id' => 'https://airpay.academy/mod/scorm/view.php?id=10'],
            'context' => ['registration' => $registration],
        ];
        if (!empty($result)) {
            $data['result'] = $result;
        }
        return $data;
    }

    // ─── Session lifecycle ─────────────────────────────────────────────

    public function test_session_created_on_initialized(): void {
        global $DB;
        $tracker = new cmi5_tracker();
        $reg     = statement::generate_uuid();

        $tracker->process($this->make_cmi5_stmt('initialized', $reg), 1, 42);

        $session = $DB->get_record('local_sentientia_xapi_cmi5', ['registration' => $reg]);
        $this->assertNotFalse($session);
        $this->assertEquals('initialized', $session->status);
        $this->assertEquals(42, $session->userid);
        $this->assertEquals(1, $session->costcenterid);
    }

    public function test_status_advances_to_passed(): void {
        global $DB;
        $tracker = new cmi5_tracker();
        $reg     = statement::generate_uuid();

        $tracker->process($this->make_cmi5_stmt('initialized', $reg), 1, 42);
        $tracker->process($this->make_cmi5_stmt('passed', $reg, [
            'score'   => ['scaled' => 0.9],
            'success' => true,
        ]), 1, 42);

        $session = $DB->get_record('local_sentientia_xapi_cmi5', ['registration' => $reg]);
        $this->assertEquals('passed', $session->status);
        $this->assertEqualsWithDelta(0.9, $session->score_scaled, 0.0001);
        $this->assertEquals(1, $session->success);
    }

    public function test_status_advances_to_failed(): void {
        global $DB;
        $tracker = new cmi5_tracker();
        $reg     = statement::generate_uuid();

        $tracker->process($this->make_cmi5_stmt('initialized', $reg), 1, 42);
        $tracker->process($this->make_cmi5_stmt('failed', $reg, [
            'score'   => ['scaled' => 0.4],
            'success' => false,
        ]), 1, 42);

        $session = $DB->get_record('local_sentientia_xapi_cmi5', ['registration' => $reg]);
        $this->assertEquals('failed', $session->status);
        $this->assertEquals(0, $session->success);
    }

    public function test_terminated_captures_duration(): void {
        global $DB;
        $tracker = new cmi5_tracker();
        $reg     = statement::generate_uuid();

        $tracker->process($this->make_cmi5_stmt('initialized', $reg), 1, 42);
        $tracker->process($this->make_cmi5_stmt('terminated', $reg, [
            'duration' => 'PT10M30S',
        ]), 1, 42);

        $session = $DB->get_record('local_sentientia_xapi_cmi5', ['registration' => $reg]);
        $this->assertEquals('terminated', $session->status);
        $this->assertEquals('PT10M30S', $session->duration);
        $this->assertNotNull($session->timeterminated);
    }

    // ─── Terminal state enforcement ────────────────────────────────────

    public function test_terminal_state_not_overwritten(): void {
        global $DB;
        $tracker = new cmi5_tracker();
        $reg     = statement::generate_uuid();

        $tracker->process($this->make_cmi5_stmt('initialized', $reg), 1, 42);
        $tracker->process($this->make_cmi5_stmt('terminated', $reg), 1, 42);

        // Attempt to advance from terminated → should be ignored.
        $tracker->process($this->make_cmi5_stmt('passed', $reg, ['score' => ['scaled' => 1.0]]), 1, 42);

        $session = $DB->get_record('local_sentientia_xapi_cmi5', ['registration' => $reg]);
        $this->assertEquals('terminated', $session->status, 'Terminated session must not be overwritten.');
    }

    // ─── Non-cmi5 verb no-ops ─────────────────────────────────────────

    public function test_non_cmi5_verb_noop(): void {
        global $DB;
        $tracker = new cmi5_tracker();
        $reg     = statement::generate_uuid();

        $stmt = [
            'id'      => statement::generate_uuid(),
            'actor'   => ['objectType' => 'Agent', 'mbox' => 'mailto:t@airpay.in'],
            'verb'    => ['id' => 'http://adlnet.gov/expapi/verbs/experienced'],
            'object'  => ['objectType' => 'Activity', 'id' => 'https://example.com'],
            'context' => ['registration' => $reg],
        ];

        $tracker->process($stmt, 1, 42);

        $this->assertFalse($DB->record_exists('local_sentientia_xapi_cmi5', ['registration' => $reg]),
            'Non-cmi5 verb must not create a session row.');
    }

    // ─── Tenant isolation ─────────────────────────────────────────────

    public function test_get_sessions_scoped_to_tenant(): void {
        $tracker = new cmi5_tracker();
        $reg1    = statement::generate_uuid();
        $reg2    = statement::generate_uuid();

        $tracker->process($this->make_cmi5_stmt('initialized', $reg1), 1, 10);
        $tracker->process($this->make_cmi5_stmt('initialized', $reg2), 77, 20);

        $sessions_t1  = $tracker->get_sessions(10, 1);
        $sessions_t77 = $tracker->get_sessions(20, 77);

        $this->assertCount(1, $sessions_t1);
        $this->assertCount(1, $sessions_t77);
        $this->assertEquals($reg1, $sessions_t1[0]->registration);
        $this->assertEquals($reg2, $sessions_t77[0]->registration);
    }

    // ─── Missing registration ──────────────────────────────────────────

    public function test_missing_registration_noop(): void {
        global $DB;
        $tracker = new cmi5_tracker();

        $stmt = [
            'id'     => statement::generate_uuid(),
            'actor'  => ['objectType' => 'Agent', 'mbox' => 'mailto:t@airpay.in'],
            'verb'   => ['id' => cmi5_tracker::CMI5_VERBS['initialized']],
            'object' => ['objectType' => 'Activity', 'id' => 'https://example.com'],
            // No context.registration — must be ignored.
        ];

        $count_before = $DB->count_records('local_sentientia_xapi_cmi5');
        $tracker->process($stmt, 1, 42);
        $this->assertEquals($count_before, $DB->count_records('local_sentientia_xapi_cmi5'));
    }
}
