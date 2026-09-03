<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * PHPUnit tests for statement_validator.
 *
 * Covers: valid statements, malformed actor/verb/object, score range
 * violations, UUID checks, and timestamp validation.
 *
 * @package    local_sentientia_xapi
 * @category   phpunit
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_xapi\tests;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_xapi\validator\statement_validator;
use local_sentientia_xapi\model\statement;

/**
 * @covers \local_sentientia_xapi\validator\statement_validator
 */
class statement_validator_test extends \advanced_testcase {

    private function make_valid(): array {
        return [
            'id'     => statement::generate_uuid(),
            'actor'  => [
                'objectType' => 'Agent',
                'account'    => [
                    'homePage' => 'https://airpay.academy',
                    'name'     => '42',
                ],
            ],
            'verb'   => [
                'id'      => 'http://adlnet.gov/expapi/verbs/completed',
                'display' => ['en-US' => 'completed'],
            ],
            'object' => [
                'objectType' => 'Activity',
                'id'         => 'https://airpay.academy/course/view.php?id=5',
            ],
        ];
    }

    // ─── Valid statement ───────────────────────────────────────────────

    public function test_valid_statement(): void {
        $v = new statement_validator();
        $this->assertTrue($v->validate($this->make_valid()));
        $this->assertEmpty($v->get_errors());
    }

    public function test_valid_with_mbox_actor(): void {
        $data          = $this->make_valid();
        $data['actor'] = [
            'objectType' => 'Agent',
            'mbox'       => 'mailto:test@airpay.in',
        ];
        $v = new statement_validator();
        $this->assertTrue($v->validate($data));
    }

    public function test_valid_with_result_and_score(): void {
        $data           = $this->make_valid();
        $data['result'] = [
            'score'      => ['scaled' => 0.85, 'raw' => 85.0, 'min' => 0.0, 'max' => 100.0],
            'success'    => true,
            'completion' => true,
        ];
        $v = new statement_validator();
        $this->assertTrue($v->validate($data));
    }

    public function test_valid_with_context_registration(): void {
        $data            = $this->make_valid();
        $data['context'] = ['registration' => statement::generate_uuid()];
        $v = new statement_validator();
        $this->assertTrue($v->validate($data));
    }

    public function test_valid_without_optional_id(): void {
        $data = $this->make_valid();
        unset($data['id']);
        $v = new statement_validator();
        $this->assertTrue($v->validate($data));
    }

    // ─── Actor validation ──────────────────────────────────────────────

    public function test_missing_actor(): void {
        $data = $this->make_valid();
        unset($data['actor']);
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
        $this->assertNotEmpty($v->get_errors());
    }

    public function test_actor_with_no_ifi(): void {
        $data          = $this->make_valid();
        $data['actor'] = ['objectType' => 'Agent', 'name' => 'No IFI'];
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_actor_with_multiple_ifis(): void {
        $data          = $this->make_valid();
        $data['actor'] = [
            'objectType' => 'Agent',
            'mbox'       => 'mailto:test@airpay.in',
            'account'    => ['homePage' => 'https://airpay.academy', 'name' => '1'],
        ];
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_actor_mbox_invalid_format(): void {
        $data          = $this->make_valid();
        $data['actor'] = ['objectType' => 'Agent', 'mbox' => 'test@airpay.in'];  // Missing mailto:.
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_actor_account_missing_name(): void {
        $data          = $this->make_valid();
        $data['actor'] = ['objectType' => 'Agent', 'account' => ['homePage' => 'https://airpay.academy']];
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    // ─── Verb validation ──────────────────────────────────────────────

    public function test_missing_verb(): void {
        $data = $this->make_valid();
        unset($data['verb']);
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_verb_with_no_id(): void {
        $data         = $this->make_valid();
        $data['verb'] = ['display' => ['en-US' => 'completed']];
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_verb_id_not_iri(): void {
        $data         = $this->make_valid();
        $data['verb'] = ['id' => 'completed'];  // Not an IRI.
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    // ─── Object validation ────────────────────────────────────────────

    public function test_missing_object(): void {
        $data = $this->make_valid();
        unset($data['object']);
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_object_id_not_iri(): void {
        $data           = $this->make_valid();
        $data['object'] = ['objectType' => 'Activity', 'id' => 'not-an-iri'];
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    // ─── Result / score validation ────────────────────────────────────

    public function test_score_scaled_out_of_range(): void {
        $data           = $this->make_valid();
        $data['result'] = ['score' => ['scaled' => 1.5]];  // > 1.0
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_score_scaled_negative_out_of_range(): void {
        $data           = $this->make_valid();
        $data['result'] = ['score' => ['scaled' => -2.0]];
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_score_raw_exceeds_max(): void {
        $data           = $this->make_valid();
        $data['result'] = ['score' => ['raw' => 110.0, 'max' => 100.0]];
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    // ─── Context / UUID validation ────────────────────────────────────

    public function test_invalid_registration_uuid(): void {
        $data            = $this->make_valid();
        $data['context'] = ['registration' => 'not-a-uuid'];
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_invalid_statement_id_uuid(): void {
        $data       = $this->make_valid();
        $data['id'] = 'not-a-uuid';
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    // ─── Timestamp validation ─────────────────────────────────────────

    public function test_invalid_timestamp(): void {
        $data              = $this->make_valid();
        $data['timestamp'] = '2026-99-99T00:00:00Z';
        $v = new statement_validator();
        $this->assertFalse($v->validate($data));
    }

    public function test_valid_iso8601_timestamp(): void {
        $data              = $this->make_valid();
        $data['timestamp'] = '2026-06-16T09:00:00+05:30';
        $v = new statement_validator();
        $this->assertTrue($v->validate($data));
    }

    // ─── Non-array input ─────────────────────────────────────────────

    public function test_non_array_input(): void {
        $v = new statement_validator();
        $this->assertFalse($v->validate('string'));
        $this->assertFalse($v->validate(null));
        $this->assertFalse($v->validate(42));
    }
}
