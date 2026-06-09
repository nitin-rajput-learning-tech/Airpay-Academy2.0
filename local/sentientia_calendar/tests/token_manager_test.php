<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_calendar;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_calendar\token_manager
 *
 * Locks in the token lifecycle:
 *   - new token is 64 chars and URL-safe
 *   - get_or_create is idempotent (one active token per user)
 *   - regenerate revokes old, creates new, both rows present
 *   - find_active_token returns null for revoked / malformed / unknown
 *   - mark_used increments counters and stamps IP
 *
 * @package    local_sentientia_calendar
 * @category   test
 */
final class token_manager_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_generate_token_string_is_64_chars_urlsafe(): void {
        $token = token_manager::generate_token_string();
        $this->assertSame(64, strlen($token), 'Token must be exactly 64 chars');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $token,
            'Token must contain only URL-safe alphanumerics');
    }

    public function test_generated_tokens_are_unique(): void {
        $seen = [];
        for ($i = 0; $i < 100; $i++) {
            $t = token_manager::generate_token_string();
            $this->assertArrayNotHasKey($t, $seen, 'Duplicate token generated');
            $seen[$t] = true;
        }
    }

    public function test_get_or_create_for_user_is_idempotent(): void {
        $user = $this->getDataGenerator()->create_user();
        $first  = token_manager::get_or_create_for_user((int) $user->id);
        $second = token_manager::get_or_create_for_user((int) $user->id);
        $this->assertSame($first, $second,
            'Second call must return the same active token');
    }

    public function test_create_for_user_persists_to_db(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $token = token_manager::create_for_user((int) $user->id);

        $row = $DB->get_record(token_manager::TABLE, ['token' => $token], '*', MUST_EXIST);
        $this->assertEquals($user->id, $row->userid);
        $this->assertSame('0', (string) $row->revoked);
        $this->assertGreaterThan(0, (int) $row->timecreated);
    }

    public function test_regenerate_revokes_old_and_creates_new(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $first  = token_manager::create_for_user((int) $user->id);
        $second = token_manager::regenerate_for_user((int) $user->id);

        $this->assertNotSame($first, $second, 'Regenerate must yield a fresh token');

        // Old row marked revoked.
        $old = $DB->get_record(token_manager::TABLE, ['token' => $first], '*', MUST_EXIST);
        $this->assertSame('1', (string) $old->revoked, 'Old token must be revoked');

        // New row exists and is active.
        $new = $DB->get_record(token_manager::TABLE, ['token' => $second], '*', MUST_EXIST);
        $this->assertSame('0', (string) $new->revoked, 'New token must be active');

        // Exactly one active token for this user.
        $active = $DB->count_records(token_manager::TABLE,
            ['userid' => $user->id, 'revoked' => 0]);
        $this->assertSame(1, $active, 'User should have exactly one active token');
    }

    public function test_find_active_token_returns_row_for_valid(): void {
        $user = $this->getDataGenerator()->create_user();
        $token = token_manager::create_for_user((int) $user->id);
        $row = token_manager::find_active_token($token);
        $this->assertNotNull($row);
        $this->assertEquals($user->id, $row->userid);
    }

    public function test_find_active_token_returns_null_for_revoked(): void {
        $user = $this->getDataGenerator()->create_user();
        $token = token_manager::create_for_user((int) $user->id);
        token_manager::regenerate_for_user((int) $user->id);
        $this->assertNull(token_manager::find_active_token($token),
            'Revoked tokens must NOT authenticate');
    }

    public function test_find_active_token_returns_null_for_unknown(): void {
        // 64-char alphanumeric that doesn't exist.
        $fake = str_repeat('A', 64);
        $this->assertNull(token_manager::find_active_token($fake));
    }

    public function test_find_active_token_returns_null_for_malformed_short(): void {
        $this->assertNull(token_manager::find_active_token('abc'));
    }

    public function test_find_active_token_returns_null_for_malformed_long(): void {
        $this->assertNull(token_manager::find_active_token(str_repeat('A', 80)));
    }

    public function test_find_active_token_returns_null_for_illegal_chars(): void {
        $bad = str_repeat('!', 64);
        $this->assertNull(token_manager::find_active_token($bad));
    }

    public function test_find_active_token_returns_null_for_empty(): void {
        $this->assertNull(token_manager::find_active_token(''));
    }

    public function test_mark_used_increments_counters(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $token = token_manager::create_for_user((int) $user->id);
        $row = $DB->get_record(token_manager::TABLE, ['token' => $token], '*', MUST_EXIST);

        $this->assertSame('0', (string) $row->use_count);
        $this->assertNull($row->last_used_at);

        token_manager::mark_used((int) $row->id, '203.0.113.42');

        $updated = $DB->get_record(token_manager::TABLE, ['id' => $row->id], '*', MUST_EXIST);
        $this->assertSame('1', (string) $updated->use_count);
        $this->assertGreaterThan(0, (int) $updated->last_used_at);
        $this->assertSame('203.0.113.42', $updated->last_used_ip);

        // A second call increments to 2.
        token_manager::mark_used((int) $row->id, '203.0.113.42');
        $updated2 = $DB->get_record(token_manager::TABLE, ['id' => $row->id], '*', MUST_EXIST);
        $this->assertSame('2', (string) $updated2->use_count);
    }

    public function test_mark_used_is_noop_for_unknown_id(): void {
        // Should not throw; counts as "best effort" audit.
        token_manager::mark_used(999999, '127.0.0.1');
        $this->assertTrue(true);  // reaching here is the assertion
    }

    public function test_tenant_isolation_two_users_get_different_tokens(): void {
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $t1 = token_manager::create_for_user((int) $u1->id);
        $t2 = token_manager::create_for_user((int) $u2->id);
        $this->assertNotSame($t1, $t2);

        // u1's token resolves to u1, never to u2.
        $row1 = token_manager::find_active_token($t1);
        $this->assertEquals($u1->id, $row1->userid);
        $this->assertNotEquals($u2->id, $row1->userid);

        // And the reverse.
        $row2 = token_manager::find_active_token($t2);
        $this->assertEquals($u2->id, $row2->userid);
    }

    public function test_revoke_all_for_user_revokes_only_their_active_tokens(): void {
        global $DB;
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $t1 = token_manager::create_for_user((int) $u1->id);
        $t2 = token_manager::create_for_user((int) $u2->id);

        $count = token_manager::revoke_all_for_user((int) $u1->id);
        $this->assertSame(1, $count);

        // u1's token now revoked.
        $row1 = $DB->get_record(token_manager::TABLE, ['token' => $t1], '*', MUST_EXIST);
        $this->assertSame('1', (string) $row1->revoked);

        // u2's token still active — no cross-user leakage.
        $row2 = $DB->get_record(token_manager::TABLE, ['token' => $t2], '*', MUST_EXIST);
        $this->assertSame('0', (string) $row2->revoked);
    }

    public function test_build_subscription_url_includes_token(): void {
        // Fresh test fixture — using the live generator to avoid embedding
        // any constant in the test source (which a static lint will flag
        // as a "hardcoded credential").
        $fixture = token_manager::generate_token_string();
        $url = token_manager::build_subscription_url($fixture);
        $this->assertStringContainsString('/local/sentientia_calendar/ics.php',
            $url->out(false));
        $this->assertStringContainsString('token=' . $fixture, $url->out(false));
    }
}
