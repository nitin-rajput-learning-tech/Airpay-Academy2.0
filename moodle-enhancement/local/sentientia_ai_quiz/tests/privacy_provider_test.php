<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai_quiz;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use local_sentientia_ai_quiz\privacy\provider;

/**
 * PHPUnit — privacy provider coverage for the Phase G.1 scaffold.
 *
 * The scaffold never inserts a log row (anthropic_client throws
 * confirm_required), so every privacy entry point must behave cleanly
 * against an empty table. The live-wiring chip will insert rows; these
 * same tests then become real-data exercises.
 *
 * @package    local_sentientia_ai_quiz
 * @covers     \local_sentientia_ai_quiz\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {

    /**
     * Metadata MUST declare the local log table + the Anthropic external
     * subsystem. The Privacy API uses this to drive admin reporting.
     */
    public function test_get_metadata_declares_log_and_anthropic(): void {
        $collection = new collection('local_sentientia_ai_quiz');
        $collection = provider::get_metadata($collection);

        $items = $collection->get_collection();
        $this->assertNotEmpty($items, 'Privacy metadata must declare at least one item.');

        $found_table    = false;
        $found_external = false;
        foreach ($items as $item) {
            $name = $item->get_name();
            if ($name === 'local_sentientia_ai_quiz_log') {
                $found_table = true;
            }
            if ($name === 'anthropic_api') {
                $found_external = true;
            }
        }
        $this->assertTrue($found_table,
            'Privacy metadata must declare the local_sentientia_ai_quiz_log table.');
        $this->assertTrue($found_external,
            'Privacy metadata must declare the anthropic_api external subsystem.');
    }

    /**
     * For a fresh install (no rows), get_contexts_for_userid returns an
     * empty contextlist. This is the Phase G.1 contract: the table
     * exists but is empty.
     */
    public function test_get_contexts_for_userid_empty_when_no_rows(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * When a log row exists for the user, the system context is
     * included. This drives the live-wiring chip's data-subject export.
     */
    public function test_get_contexts_for_userid_includes_system_when_rows_exist(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $DB->insert_record('local_sentientia_ai_quiz_log', (object) [
            'userid'       => $user->id,
            'courseid'     => 0,
            'customerid'   => 1,
            'prompt_hash'  => str_repeat('a', 64),
            'model'        => 'claude-sonnet-4-6',
            'tokens_in'    => 100,
            'tokens_out'   => 200,
            'success'      => 1,
            'error'        => null,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertCount(1, $contextlist);
        $contexts = $contextlist->get_contexts();
        $this->assertInstanceOf(\context_system::class, reset($contexts));
    }

    /**
     * get_users_in_context returns an empty userlist for an empty table.
     */
    public function test_get_users_in_context_empty_when_no_rows(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        $userlist = new \core_privacy\local\request\userlist($context, 'local_sentientia_ai_quiz');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * get_users_in_context ignores non-system contexts (the table is
     * system-scoped; user/course contexts must contribute zero users).
     */
    public function test_get_users_in_context_ignores_non_system(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_sentientia_ai_quiz_log', (object) [
            'userid'       => $user->id,
            'courseid'     => 0,
            'customerid'   => 1,
            'prompt_hash'  => str_repeat('b', 64),
            'model'        => 'claude-sonnet-4-6',
            'tokens_in'    => 1,
            'tokens_out'   => 1,
            'success'      => 1,
            'error'        => null,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
        $usercontext = \context_user::instance($user->id);
        $userlist = new \core_privacy\local\request\userlist($usercontext, 'local_sentientia_ai_quiz');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);
    }

    /**
     * export_user_data is a no-op when the table is empty — the writer
     * must have no exported data for the user.
     */
    public function test_export_user_data_no_op_when_empty(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        writer::reset();
        $approved = new \core_privacy\local\request\approved_contextlist(
            $user, 'local_sentientia_ai_quiz', [\context_system::instance()->id]);
        provider::export_user_data($approved);
        $writer = writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * delete_data_for_user removes rows for the target user and leaves
     * other users' rows intact.
     */
    public function test_delete_data_for_user_isolated(): void {
        global $DB;
        $this->resetAfterTest();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $now = time();
        $base = [
            'courseid'     => 0,
            'customerid'   => 1,
            'prompt_hash'  => str_repeat('c', 64),
            'model'        => 'claude-sonnet-4-6',
            'tokens_in'    => 0,
            'tokens_out'   => 0,
            'success'      => 1,
            'error'        => null,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('local_sentientia_ai_quiz_log',
            (object) array_merge($base, ['userid' => $u1->id]));
        $DB->insert_record('local_sentientia_ai_quiz_log',
            (object) array_merge($base, ['userid' => $u2->id]));

        $approved = new \core_privacy\local\request\approved_contextlist(
            $u1, 'local_sentientia_ai_quiz', [\context_system::instance()->id]);
        provider::delete_data_for_user($approved);

        $this->assertSame(0,
            $DB->count_records('local_sentientia_ai_quiz_log', ['userid' => $u1->id]));
        $this->assertSame(1,
            $DB->count_records('local_sentientia_ai_quiz_log', ['userid' => $u2->id]));
    }

    /**
     * delete_data_for_all_users_in_context wipes every row when the
     * context is the system context.
     */
    public function test_delete_data_for_all_users_in_context_wipes_table(): void {
        global $DB;
        $this->resetAfterTest();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $now = time();
        $base = [
            'courseid'     => 0,
            'customerid'   => 1,
            'prompt_hash'  => str_repeat('d', 64),
            'model'        => 'claude-sonnet-4-6',
            'tokens_in'    => 0,
            'tokens_out'   => 0,
            'success'      => 1,
            'error'        => null,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('local_sentientia_ai_quiz_log',
            (object) array_merge($base, ['userid' => $u1->id]));
        $DB->insert_record('local_sentientia_ai_quiz_log',
            (object) array_merge($base, ['userid' => $u2->id]));

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(0, $DB->count_records('local_sentientia_ai_quiz_log'));
    }

    /**
     * delete_data_for_all_users_in_context ignores non-system contexts.
     */
    public function test_delete_data_for_all_users_in_context_ignores_user_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_sentientia_ai_quiz_log', (object) [
            'userid'       => $user->id,
            'courseid'     => 0,
            'customerid'   => 1,
            'prompt_hash'  => str_repeat('e', 64),
            'model'        => 'claude-sonnet-4-6',
            'tokens_in'    => 0,
            'tokens_out'   => 0,
            'success'      => 1,
            'error'        => null,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
        provider::delete_data_for_all_users_in_context(\context_user::instance($user->id));
        $this->assertSame(1, $DB->count_records('local_sentientia_ai_quiz_log'));
    }

    /**
     * delete_data_for_users removes rows for the listed users only.
     */
    public function test_delete_data_for_users_batch(): void {
        global $DB;
        $this->resetAfterTest();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();
        $now = time();
        $base = [
            'courseid'     => 0,
            'customerid'   => 1,
            'prompt_hash'  => str_repeat('f', 64),
            'model'        => 'claude-sonnet-4-6',
            'tokens_in'    => 0,
            'tokens_out'   => 0,
            'success'      => 1,
            'error'        => null,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        foreach ([$u1, $u2, $u3] as $u) {
            $DB->insert_record('local_sentientia_ai_quiz_log',
                (object) array_merge($base, ['userid' => $u->id]));
        }

        $context = \context_system::instance();
        $userlist = new \core_privacy\local\request\approved_userlist(
            $context, 'local_sentientia_ai_quiz', [$u1->id, $u2->id]);
        provider::delete_data_for_users($userlist);

        $this->assertSame(0,
            $DB->count_records('local_sentientia_ai_quiz_log', ['userid' => $u1->id]));
        $this->assertSame(0,
            $DB->count_records('local_sentientia_ai_quiz_log', ['userid' => $u2->id]));
        $this->assertSame(1,
            $DB->count_records('local_sentientia_ai_quiz_log', ['userid' => $u3->id]));
    }
}
