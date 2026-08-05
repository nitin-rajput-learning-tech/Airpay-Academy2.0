<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_sentientia_assistant\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests — proves DPDP/GDPR export + erasure actually work for the
 * two userid-keyed tables (chat_log + agent_audit). Guards against regressing back to
 * a null_provider (runbook §5/§6).
 *
 * @package    local_sentientia_assistant
 * @category   test
 * @covers     \local_sentientia_assistant\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {

    /** Seed one chat-log row + one agent-audit row for a user. */
    private function seed(int $userid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_sentientia_chat_log', (object) [
            'userid'      => $userid,
            'role'        => 'user',
            'message'     => "test message for {$userid}",
            'model'       => 'mock',
            'tokens_in'   => 1,
            'tokens_out'  => 2,
            'timecreated' => $now,
        ]);
        $DB->insert_record('local_sentientia_agent_audit', (object) [
            'userid'          => $userid,
            'costcenterid'    => 1,
            'tool'            => 'enrol_user',
            'args_json'       => '{"courseid":2}',
            'proposed_by'     => 'llm',
            'outcome'         => 'confirmed',
            'detail'          => 'ok',
            'idempotency_key' => "k{$userid}",
            'timecreated'     => $now,
        ]);
    }

    public function test_metadata_declares_real_tables(): void {
        $this->resetAfterTest();
        $collection = provider::get_metadata(new collection('local_sentientia_assistant'));
        // A real provider declares at least the two userid tables (+ the Anthropic link).
        $this->assertGreaterThanOrEqual(2, count($collection->get_collection()));
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        // assertContainsEquals (loose ==) — Moodle returns context ids as strings from some
        // code paths, so a strict assertContains(int, [...]) can spuriously fail.
        $this->assertContainsEquals(\context_system::instance()->id, $contextlist->get_contextids());
    }

    public function test_export_writes_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $systemcontext = \context_system::instance();
        $this->export_context_data_for_user((int) $user->id, $systemcontext, 'local_sentientia_assistant');
        $this->assertTrue(writer::with_context($systemcontext)->has_any_data());
    }

    public function test_delete_for_user_targets_only_that_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->seed((int) $user->id);
        $this->seed((int) $other->id);

        $approved = new approved_contextlist($user, 'local_sentientia_assistant',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($approved);

        $this->assertEquals(0, $DB->count_records('local_sentientia_chat_log', ['userid' => $user->id]));
        $this->assertEquals(0, $DB->count_records('local_sentientia_agent_audit', ['userid' => $user->id]));
        // The other learner's data must be untouched.
        $this->assertEquals(1, $DB->count_records('local_sentientia_chat_log', ['userid' => $other->id]));
        $this->assertEquals(1, $DB->count_records('local_sentientia_agent_audit', ['userid' => $other->id]));
    }
}
