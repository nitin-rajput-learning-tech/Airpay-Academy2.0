<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_recommendations\anthropic_client
 *
 * No-spend guard: this client talks to Anthropic over raw PHP cURL, which
 * bypasses Moodle's \curl phpunit host blocking. Before 2026-09-22 nothing
 * stopped a suite run on a box with an API key configured from spending real
 * money against the live vendor. `local_sentientia_ai\gateway` had the guard;
 * these seven clients did not.
 *
 * This test exists so a future refactor cannot remove the guard quietly. It
 * deliberately CONFIGURES a key first, because an unset key short-circuits
 * earlier and would make the test pass for the wrong reason.
 *
 * @group tenant_isolation
 */
final class anthropic_client_nospend_test extends \advanced_testcase {

    public function test_live_call_is_refused_under_phpunit_even_with_a_key_set(): void {
        $this->resetAfterTest();
        set_config('api_key', 'sk-test-not-a-real-key', 'local_sentientia_recommendations');

        $result = anthropic_client::call_live(new \stdClass(), [], 3, 'claude-sonnet-4-5');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('live_blocked_in_tests', $result['error'],
            'the live Anthropic path must refuse to run under PHPUNIT_TEST; '
            . 'without this the suite spends real money');
        $this->assertNotSame('success', $result['mode'] ?? null);
    }

    public function test_the_guard_precedes_the_api_key_check(): void {
        $this->resetAfterTest();
        // No key configured at all. The guard must still be what answers, so
        // that the test above cannot pass merely because the key was missing.
        unset_config('api_key', 'local_sentientia_recommendations');

        $result = anthropic_client::call_live(new \stdClass(), [], 3, 'claude-sonnet-4-5');

        $this->assertSame('live_blocked_in_tests', $result['error'] ?? null,
            'the PHPUNIT guard must sit ahead of the api_key check');
    }
}
