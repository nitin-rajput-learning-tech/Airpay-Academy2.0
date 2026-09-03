<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * PHPUnit tests — H3 fix (UAT-SECURITY-POSTURE-2026-09-03): per-client
 * rate limiting on the /lrs/statements endpoint.
 *
 * Locks in:
 *   - budget()/window() fall back to the documented defaults (600/60)
 *     and read admin config when set
 *   - check_and_increment() allows exactly `budget` hits then throws
 *     rate_limit_exceeded on the next one
 *   - rate_limit_exceeded carries a Retry-After-sized retryafter (1..window)
 *   - different clientids are metered independently (no cross-client bleed)
 *   - a client that is over budget in one window is allowed again once
 *     the window rolls over
 *   - prune() removes lapsed counter rows
 *   - authenticator::check_bearer()/check_basic() resolve a distinct
 *     clientid per registered client row, and the site-wide fallback
 *     credentials resolve to the SITE_BEARER_CLIENTID / SITE_BASIC_CLIENTID
 *     sentinels rather than 0 (so the fallback path is metered too)
 *
 * @package    local_sentientia_xapi
 * @category   phpunit
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_xapi\tests;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_xapi\lrs\authenticator;
use local_sentientia_xapi\lrs\rate_limiter;
use local_sentientia_xapi\lrs\rate_limit_exceeded;

/**
 * @covers \local_sentientia_xapi\lrs\rate_limiter
 * @covers \local_sentientia_xapi\lrs\authenticator
 */
final class lrs_rate_limiter_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    // ─── Defaults + config ────────────────────────────────────────────

    public function test_budget_and_window_defaults(): void {
        $this->assertSame(600, rate_limiter::budget());
        $this->assertSame(60, rate_limiter::window());
    }

    public function test_budget_and_window_read_config(): void {
        set_config('lrs_rate_limit', 5, 'local_sentientia_xapi');
        set_config('lrs_rate_window', 30, 'local_sentientia_xapi');
        $this->assertSame(5, rate_limiter::budget());
        $this->assertSame(30, rate_limiter::window());
    }

    // ─── Enforcement ──────────────────────────────────────────────────

    public function test_budget_enforced_then_throws(): void {
        set_config('lrs_rate_limit', 3, 'local_sentientia_xapi');
        set_config('lrs_rate_window', 60, 'local_sentientia_xapi');

        // 3 hits allowed for this client.
        rate_limiter::check_and_increment(42);
        rate_limiter::check_and_increment(42);
        rate_limiter::check_and_increment(42);

        // 4th must throw, carrying a positive, in-window retryafter.
        try {
            rate_limiter::check_and_increment(42);
            $this->fail('Expected rate_limit_exceeded to be thrown.');
        } catch (rate_limit_exceeded $e) {
            $this->assertGreaterThanOrEqual(1, $e->retryafter);
            $this->assertLessThanOrEqual(60, $e->retryafter);
        }
    }

    public function test_clients_are_metered_independently(): void {
        set_config('lrs_rate_limit', 1, 'local_sentientia_xapi');
        set_config('lrs_rate_window', 60, 'local_sentientia_xapi');

        // Client 1 uses its one hit and gets throttled.
        rate_limiter::check_and_increment(1);
        $this->expectException(rate_limit_exceeded::class);
        rate_limiter::check_and_increment(1);
    }

    public function test_a_different_client_is_not_affected_by_anothers_budget(): void {
        set_config('lrs_rate_limit', 1, 'local_sentientia_xapi');
        set_config('lrs_rate_window', 60, 'local_sentientia_xapi');

        rate_limiter::check_and_increment(1);
        // Client 2's own first hit must succeed — no cross-client bleed.
        rate_limiter::check_and_increment(2);
        $this->assertTrue(true);
    }

    public function test_site_wide_fallback_sentinels_are_metered_like_any_client(): void {
        set_config('lrs_rate_limit', 1, 'local_sentientia_xapi');
        set_config('lrs_rate_window', 60, 'local_sentientia_xapi');

        rate_limiter::check_and_increment(authenticator::SITE_BEARER_CLIENTID);
        $this->expectException(rate_limit_exceeded::class);
        rate_limiter::check_and_increment(authenticator::SITE_BEARER_CLIENTID);
    }

    public function test_window_rollover_resets_the_budget(): void {
        global $DB;
        set_config('lrs_rate_limit', 1, 'local_sentientia_xapi');
        set_config('lrs_rate_window', 60, 'local_sentientia_xapi');

        rate_limiter::check_and_increment(99);
        // Simulate the window having rolled over by backdating the row.
        $DB->set_field('local_sentientia_xapi_lrs_rate', 'windowstart', time() - 120,
            ['clientid' => 99]);

        // A fresh window means a fresh budget — must not throw.
        rate_limiter::check_and_increment(99);
        $this->assertTrue(true);
    }

    public function test_unauthenticated_clientid_zero_is_denied(): void {
        // Defensive fail-safe only — statements.php never reaches the
        // rate limiter for an unauthenticated request (401 comes first).
        $this->expectException(rate_limit_exceeded::class);
        rate_limiter::check_and_increment(0);
    }

    // ─── Pruning ──────────────────────────────────────────────────────

    public function test_prune_removes_lapsed_windows(): void {
        global $DB;
        set_config('lrs_rate_window', 60, 'local_sentientia_xapi');

        $DB->insert_record('local_sentientia_xapi_lrs_rate', (object) [
            'clientid' => 7, 'windowstart' => time() - 10000, 'hits' => 5,
            'timemodified' => time() - 10000,
        ]);
        $deleted = rate_limiter::prune();
        $this->assertGreaterThanOrEqual(1, $deleted);
    }

    // ─── Authenticator clientid resolution ────────────────────────────

    public function test_registered_bearer_client_resolves_its_own_clientid(): void {
        global $DB;
        $token = 'a-very-secret-bearer-token';
        $id = $DB->insert_record('local_sentientia_xapi_clients', (object) [
            'costcenterid' => 1, 'name' => 'Test bearer client',
            'token_hash' => hash('sha256', $token),
            'basic_user' => null, 'basic_pass_hash' => null,
            'ip_allowlist' => null, 'enabled' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        $result = (new authenticator())->authenticate_request();
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $this->assertTrue($result['ok']);
        $this->assertSame((int) $id, $result['clientid']);
    }

    public function test_registered_basic_client_resolves_its_own_clientid(): void {
        global $DB;
        $id = $DB->insert_record('local_sentientia_xapi_clients', (object) [
            'costcenterid' => 1, 'name' => 'Test basic client',
            'token_hash' => null, 'basic_user' => 'lrsclient',
            'basic_pass_hash' => hash('sha256', 'sekret'),
            'ip_allowlist' => null, 'enabled' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('lrsclient:sekret');
        $result = (new authenticator())->authenticate_request();
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $this->assertTrue($result['ok']);
        $this->assertSame((int) $id, $result['clientid']);
    }

    public function test_site_wide_bearer_fallback_resolves_to_sentinel_not_zero(): void {
        set_config('lrs_token', 'site-wide-secret', 'local_sentientia_xapi');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer site-wide-secret';
        $result = (new authenticator())->authenticate_request();
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $this->assertTrue($result['ok']);
        $this->assertSame(authenticator::SITE_BEARER_CLIENTID, $result['clientid']);
    }

    public function test_site_wide_basic_fallback_resolves_to_sentinel_not_zero(): void {
        set_config('lrs_basic_user', 'siteuser', 'local_sentientia_xapi');
        set_config('lrs_basic_pass', 'sitepass', 'local_sentientia_xapi');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('siteuser:sitepass');
        $result = (new authenticator())->authenticate_request();
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $this->assertTrue($result['ok']);
        $this->assertSame(authenticator::SITE_BASIC_CLIENTID, $result['clientid']);
    }

    public function test_failed_auth_resolves_clientid_zero(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer not-a-real-token';
        $result = (new authenticator())->authenticate_request();
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['clientid']);
    }
}
