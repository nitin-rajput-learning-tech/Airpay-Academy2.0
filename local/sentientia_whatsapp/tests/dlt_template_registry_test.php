<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_whatsapp\dlt_template_registry
 *
 * Phase A1 iter 2. Regression suite for the DLT template registry.
 */
class dlt_template_registry_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_upsert_creates_then_updates(): void {
        $id1 = dlt_template_registry::upsert([
            'template_key' => 'test_one',
            'channel'      => 'whatsapp',
            'body'         => 'Hi {{firstname}}.',
        ]);
        $this->assertGreaterThan(0, $id1);

        // Re-upsert with same key+channel+language should update, not duplicate.
        $id2 = dlt_template_registry::upsert([
            'template_key' => 'test_one',
            'channel'      => 'whatsapp',
            'body'         => 'Hello {{firstname}}, updated.',
        ]);
        $this->assertSame($id1, $id2);

        $row = dlt_template_registry::get('test_one', 'whatsapp');
        $this->assertStringContainsString('updated', $row->body);
    }

    public function test_upsert_rejects_invalid_channel(): void {
        $this->expectException(\moodle_exception::class);
        dlt_template_registry::upsert([
            'template_key' => 'x',
            'channel'      => 'telegram',  // not in VALID_CHANNELS
            'body'         => 'x',
        ]);
    }

    public function test_get_approved_returns_null_until_approved(): void {
        $id = dlt_template_registry::upsert([
            'template_key' => 'approve_test',
            'channel'      => 'whatsapp',
            'body'         => 'body',
            'status'       => 'pending',
        ]);
        $this->assertNull(dlt_template_registry::get_approved(
            'approve_test', 'whatsapp'));

        dlt_template_registry::transition_status($id, 'approved');
        $this->assertNotNull(dlt_template_registry::get_approved(
            'approve_test', 'whatsapp'));
    }

    public function test_extract_variables_finds_placeholders(): void {
        $vars = dlt_template_registry::extract_variables(
            'Hi {{firstname}}, complete {{coursename}} by {{duedate}}.'
        );
        $this->assertSame(['firstname', 'coursename', 'duedate'], $vars);
    }

    public function test_extract_variables_dedupes(): void {
        $vars = dlt_template_registry::extract_variables(
            '{{firstname}} {{firstname}} {{coursename}}'
        );
        $this->assertSame(['firstname', 'coursename'], $vars);
    }

    public function test_render_substitutes_variables(): void {
        $result = dlt_template_registry::render(
            'Hi {{firstname}}, complete {{coursename}}.',
            ['firstname' => 'Sarah', 'coursename' => 'AML']
        );
        $this->assertSame('Hi Sarah, complete AML.', $result);
    }

    public function test_render_keeps_missing_placeholders_visible(): void {
        // Missing variables stay as {{name}} in the output so QA sees them.
        $result = dlt_template_registry::render(
            'Hi {{firstname}}, complete {{missing}}.',
            ['firstname' => 'Sarah']
        );
        $this->assertSame('Hi Sarah, complete {{missing}}.', $result);
    }

    public function test_transition_status_records_timestamps(): void {
        $id = dlt_template_registry::upsert([
            'template_key' => 'time_test',
            'channel'      => 'sms',
            'body'         => 'body',
        ]);

        dlt_template_registry::transition_status($id, 'submitted');
        $row = dlt_template_registry::get('time_test', 'sms');
        $this->assertNotEmpty($row->submitted_at);
        $this->assertEmpty($row->approved_at);

        dlt_template_registry::transition_status($id, 'approved');
        $row = dlt_template_registry::get('time_test', 'sms');
        $this->assertNotEmpty($row->approved_at);
    }

    public function test_transition_to_rejected_captures_reason(): void {
        $id = dlt_template_registry::upsert([
            'template_key' => 'reject_test',
            'channel'      => 'whatsapp',
            'body'         => 'body',
        ]);
        dlt_template_registry::transition_status($id, 'rejected',
            'Promotional content not allowed for transactional category');

        $row = dlt_template_registry::get('reject_test', 'whatsapp');
        $this->assertSame('rejected', $row->status);
        $this->assertStringContainsString('Promotional content',
            $row->rejection_reason);
    }
}
