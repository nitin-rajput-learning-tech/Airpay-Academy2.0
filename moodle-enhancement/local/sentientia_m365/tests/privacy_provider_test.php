<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_m365;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use local_sentientia_m365\privacy\provider;

/**
 * PHPUnit tests for the privacy provider — Phase C.1.
 *
 * Covers:
 *   - get_metadata() returns the token table + the Microsoft Graph
 *     external location.
 *   - get_contexts_for_userid() returns system context when the user
 *     has a token row.
 *   - get_users_in_context() lists the user IDs with token rows.
 *   - export_user_data() masks the encrypted ciphertext (never leaks
 *     a usable credential to the DSAR ZIP).
 *   - delete_data_for_user() removes only the targeted user's rows.
 *   - delete_data_for_users() bulk-deletes the supplied user IDs.
 *
 * @package    local_sentientia_m365
 * @covers     \local_sentientia_m365\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {

    public function test_metadata_declares_token_table_and_graph_endpoint(): void {
        $collection = new \core_privacy\local\metadata\collection('local_sentientia_m365');
        $collection = provider::get_metadata($collection);

        $items = $collection->get_collection();
        $this->assertNotEmpty($items);

        $found_token_table = false;
        $found_graph       = false;
        foreach ($items as $item) {
            if ($item->get_name() === 'local_sentientia_m365_tokens') {
                $found_token_table = true;
            }
            if ($item->get_name() === 'microsoft_graph') {
                $found_graph = true;
            }
        }
        $this->assertTrue($found_token_table,
            'Privacy metadata must declare local_sentientia_m365_tokens');
        $this->assertTrue($found_graph,
            'Privacy metadata must declare microsoft_graph external location');
    }

    public function test_get_contexts_for_userid_returns_system_when_row_exists(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user = $this->getDataGenerator()->create_user();
        msal_client::store_tokens($user->id, 1, 'a', 'r', 3600, 'openid');

        $contexts = provider::get_contexts_for_userid($user->id);
        $contextids = $contexts->get_contextids();
        $this->assertCount(1, $contextids);

        $context = \context::instance_by_id($contextids[0]);
        $this->assertSame(CONTEXT_SYSTEM, $context->contextlevel);
    }

    public function test_get_contexts_for_userid_returns_empty_when_no_row(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $contexts = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contexts->get_contextids());
    }

    public function test_get_users_in_context_returns_token_holders(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        $user_c = $this->getDataGenerator()->create_user();

        msal_client::store_tokens($user_a->id, 1, 'a', 'r', 3600, 'openid');
        msal_client::store_tokens($user_b->id, 1, 'a', 'r', 3600, 'openid');
        // user_c has no row.

        $context = \context_system::instance();
        $userlist = new \core_privacy\local\request\userlist($context, 'local_sentientia_m365');
        provider::get_users_in_context($userlist);

        $ids = $userlist->get_userids();
        $this->assertContains((int)$user_a->id, $ids);
        $this->assertContains((int)$user_b->id, $ids);
        $this->assertNotContains((int)$user_c->id, $ids);
    }

    public function test_export_masks_encrypted_columns(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user = $this->getDataGenerator()->create_user();
        $rowid = msal_client::store_tokens(
            $user->id, 1,
            'super-secret-access-token',
            'super-secret-refresh-token',
            3600,
            'openid profile offline_access User.Read'
        );

        $context = \context_system::instance();
        $approved = new approved_contextlist(
            $user, 'local_sentientia_m365', [$context->id]);
        writer::reset();
        provider::export_user_data($approved);

        $exported = writer::with_context($context)->get_data(
            ['Sentientia Microsoft 365', 'Tokens', (string)$rowid]);
        $this->assertNotEmpty($exported);
        $this->assertSame('[encrypted]', $exported->access_token_enc);
        $this->assertSame('[encrypted]', $exported->refresh_token_enc);

        // Metadata is preserved.
        $this->assertSame('openid profile offline_access User.Read',
            $exported->scopes);
        $this->assertSame(1, $exported->customerid);

        // Ciphertext does NOT appear anywhere in the exported payload —
        // the mask must be complete, not partial.
        $serialized = json_encode($exported);
        $this->assertStringNotContainsString(
            'super-secret-access-token', $serialized);
        $this->assertStringNotContainsString(
            'super-secret-refresh-token', $serialized);
    }

    public function test_delete_for_user_removes_only_their_rows(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        msal_client::store_tokens($user_a->id, 1, 'a', 'r', 3600, 'openid');
        msal_client::store_tokens($user_b->id, 1, 'a', 'r', 3600, 'openid');

        $context = \context_system::instance();
        $approved = new approved_contextlist(
            $user_a, 'local_sentientia_m365', [$context->id]);
        provider::delete_data_for_user($approved);

        $this->assertNull(msal_client::load_tokens($user_a->id, 1),
            'user A row should be deleted');
        $this->assertNotNull(msal_client::load_tokens($user_b->id, 1),
            'user B row should still be present');
    }

    public function test_delete_for_users_bulk_deletes(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        $user_c = $this->getDataGenerator()->create_user();
        msal_client::store_tokens($user_a->id, 1, 'a', 'r', 3600, 'openid');
        msal_client::store_tokens($user_b->id, 1, 'a', 'r', 3600, 'openid');
        msal_client::store_tokens($user_c->id, 1, 'a', 'r', 3600, 'openid');

        $context = \context_system::instance();
        $userlist = new approved_userlist(
            $context, 'local_sentientia_m365', [$user_a->id, $user_c->id]);
        provider::delete_data_for_users($userlist);

        $this->assertNull(msal_client::load_tokens($user_a->id, 1));
        $this->assertNotNull(msal_client::load_tokens($user_b->id, 1));
        $this->assertNull(msal_client::load_tokens($user_c->id, 1));
    }

    public function test_delete_for_all_users_in_context_clears_table(): void {
        $this->resetAfterTest();
        \core\encryption::create_key();
        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        msal_client::store_tokens($user_a->id, 1, 'a', 'r', 3600, 'openid');
        msal_client::store_tokens($user_b->id, 1, 'a', 'r', 3600, 'openid');

        $context = \context_system::instance();
        provider::delete_data_for_all_users_in_context($context);

        $this->assertNull(msal_client::load_tokens($user_a->id, 1));
        $this->assertNull(msal_client::load_tokens($user_b->id, 1));
    }
}
