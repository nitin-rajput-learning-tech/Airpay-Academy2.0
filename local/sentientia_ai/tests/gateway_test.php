<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai;

/**
 * Gateway routing + quota + ledger tests — the golden-set eval harness's
 * structural half. No test in this file ever performs HTTP: every path
 * that could reach call_live() is cut off earlier (flags OFF, key unset,
 * or quota zero — the fail-closed branches ARE the test subjects).
 *
 * @package local_sentientia_ai
 * @covers \local_sentientia_ai\gateway
 * @covers \local_sentientia_ai\client
 * @covers \local_sentientia_ai\ledger
 */
final class gateway_test extends \advanced_testcase {

    /**
     * The platform flag resolver holds raw PHP statics (self::$registry /
     * self::$overrides) that survive resetAfterTest's DB rollback — flush
     * them so every test starts from the true DB state.
     */
    protected function setUp(): void {
        parent::setUp();
        if (class_exists('\\local_sentientia_platform\\feature_flags')) {
            \local_sentientia_platform\feature_flags::invalidate_caches();
        }
    }

    /**
     * Baseline: flags default OFF → every call mocks, costs nothing,
     * and writes a 'mock' ledger row.
     */
    public function test_default_flags_route_to_mock(): void {
        global $DB;
        $this->resetAfterTest();

        $result = client::complete([
            'component' => 'local_sentientia_aiquiz',
            'purpose'   => 'quiz_generation',
            'usertext'  => 'Generate questions about POSH compliance.',
        ]);

        $this->assertSame('mock', $result['mode']);
        $this->assertNull($result['error']);
        $this->assertStringContainsString('[MOCK sentientia_ai]', $result['body']);
        $this->assertSame(0, $result['tokens_in']);
        $this->assertSame(0, $result['tokens_out']);

        $row = $DB->get_record('local_sentientia_ai_ledger', ['id' => $result['ledgerid']], '*', MUST_EXIST);
        $this->assertSame('mock', $row->mode);
        $this->assertSame('local_sentientia_aiquiz', $row->component);
        $this->assertSame('quiz_generation', $row->purpose);
        $this->assertEquals(0, (float) $row->estcost);
    }

    /**
     * The component's own mock callable wins over the generic mock —
     * component mock fidelity (e.g. aiquiz's Devanagari mocks) survives
     * the gateway migration.
     */
    public function test_component_mock_callable_is_used(): void {
        $this->resetAfterTest();

        $result = client::complete([
            'component' => 'local_sentientia_aiquiz',
            'purpose'   => 'quiz_generation',
            'usertext'  => 'source text',
            'mock'      => function (array $req): string {
                return json_encode(['questions' => [], 'echo' => $req['purpose']]);
            },
        ]);

        $this->assertSame('mock', $result['mode']);
        $decoded = json_decode($result['body'], true);
        $this->assertSame('quiz_generation', $decoded['echo']);
    }

    /**
     * Live-intent path with no key: FAILED api_key_not_set — never a
     * silent mock, never an HTTP attempt.
     */
    public function test_live_flags_without_key_fail_closed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_live_flags();

        $result = client::complete([
            'component' => 'local_sentientia_aiquiz',
            'purpose'   => 'quiz_generation',
            'usertext'  => 'source',
        ]);

        $this->assertSame('failed', $result['mode']);
        $this->assertSame('api_key_not_set', $result['error']);
        $row = $DB->get_record('local_sentientia_ai_ledger', ['id' => $result['ledgerid']], '*', MUST_EXIST);
        $this->assertSame('failed', $row->mode);
    }

    /**
     * Zero caps mean NO live allowance (the Addendum-A hard-ceiling rule):
     * key present + flags on + caps blanked by an admin → DENIED cap_unset.
     * (Caps must be zeroed explicitly here: install applies the settings
     * DEFAULTS, so a fresh site never has truly-unset caps.)
     */
    public function test_zero_caps_deny_live(): void {
        $this->resetAfterTest();
        $this->enable_live_flags();
        set_config('api_key', 'test-key-never-used', 'local_sentientia_ai');
        set_config('daily_tokens_global', 0, 'local_sentientia_ai');
        set_config('daily_tokens_customer', 0, 'local_sentientia_ai');
        set_config('monthly_cost_cap_usd', 0, 'local_sentientia_ai');

        $result = client::complete([
            'component' => 'local_sentientia_aiquiz',
            'purpose'   => 'quiz_generation',
            'usertext'  => 'source',
        ]);

        $this->assertSame('denied', $result['mode']);
        $this->assertSame('quota_exceeded:cap_unset', $result['error']);
    }

    /**
     * The structural no-spend guard: even with flags ON, a key, and quota
     * headroom (the install defaults), a test run must terminate at
     * live_blocked_in_tests — never at the real endpoint.
     */
    public function test_live_path_is_structurally_blocked_in_tests(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_live_flags();
        set_config('api_key', 'test-key-never-used', 'local_sentientia_ai');
        set_config('daily_tokens_global', 1000000, 'local_sentientia_ai');
        set_config('daily_tokens_customer', 1000000, 'local_sentientia_ai');
        set_config('monthly_cost_cap_usd', 100, 'local_sentientia_ai');

        $result = client::complete([
            'component' => 'local_sentientia_aiquiz',
            'purpose'   => 'quiz_generation',
            'usertext'  => 'source',
        ]);

        $this->assertSame('failed', $result['mode']);
        $this->assertSame('live_blocked_in_tests', $result['error']);
        $row = $DB->get_record('local_sentientia_ai_ledger', ['id' => $result['ledgerid']], '*', MUST_EXIST);
        $this->assertSame('live_blocked_in_tests', $row->error);
    }

    /**
     * A ledger already at the daily global cap denies the next live call.
     */
    public function test_daily_global_quota_denies(): void {
        $this->resetAfterTest();
        $this->enable_live_flags();
        set_config('api_key', 'test-key-never-used', 'local_sentientia_ai');
        set_config('daily_tokens_global', 1000, 'local_sentientia_ai');
        set_config('daily_tokens_customer', 1000000, 'local_sentientia_ai');
        set_config('monthly_cost_cap_usd', 100, 'local_sentientia_ai');

        // Consume the day's allowance with a recorded live call.
        ledger::record($this->req(), 'live', 600, 400, 0.01, '', 'claude-sonnet-4-6');

        $result = client::complete([
            'component' => 'local_sentientia_aiquiz',
            'purpose'   => 'quiz_generation',
            'usertext'  => 'source',
        ]);

        $this->assertSame('denied', $result['mode']);
        $this->assertSame('quota_exceeded:daily_global', $result['error']);
    }

    /**
     * Month-to-date estimated cost at the cap denies live calls.
     */
    public function test_monthly_cost_cap_denies(): void {
        $this->resetAfterTest();
        $this->enable_live_flags();
        set_config('api_key', 'test-key-never-used', 'local_sentientia_ai');
        set_config('daily_tokens_global', 1000000, 'local_sentientia_ai');
        set_config('daily_tokens_customer', 1000000, 'local_sentientia_ai');
        set_config('monthly_cost_cap_usd', 5, 'local_sentientia_ai');

        ledger::record($this->req(), 'live', 100000, 200000, 5.00, '', 'claude-sonnet-4-6');

        $result = client::complete([
            'component' => 'local_sentientia_aiquiz',
            'purpose'   => 'quiz_generation',
            'usertext'  => 'source',
        ]);

        $this->assertSame('denied', $result['mode']);
        $this->assertSame('quota_exceeded:monthly_cost', $result['error']);
    }

    /**
     * Mock + denied rows never count toward quota aggregates.
     */
    public function test_quota_aggregates_ignore_mock_and_denied(): void {
        $this->resetAfterTest();

        ledger::record($this->req(), 'mock', 0, 0, 0.0, '');
        ledger::record($this->req(), 'denied', 0, 0, 0.0, 'quota_exceeded:cap_unset');
        ledger::record($this->req(), 'live', 100, 50, 0.001, '', 'claude-sonnet-4-6');
        ledger::record($this->req(), 'failed', 40, 0, 0.0001, 'http_500', 'claude-sonnet-4-6');

        // live 150 + failed-with-usage 40 = 190; mock/denied excluded.
        $this->assertSame(190, ledger::tokens_today());
        $this->assertEqualsWithDelta(0.0011, ledger::cost_this_month(), 0.000001);
    }

    /**
     * Cost estimation follows the pricing map; unknown models use the
     * conservative default tier.
     */
    public function test_estimate_cost(): void {
        // sonnet: (1M * 3.00 + 1M * 15.00) / 1M = 18.00
        $this->assertEqualsWithDelta(18.0, gateway::estimate_cost('claude-sonnet-4-6', 1000000, 1000000), 0.0001);
        // unknown model → default (opus-tier) pricing, errs expensive.
        $this->assertEqualsWithDelta(90.0, gateway::estimate_cost('mystery-model', 1000000, 1000000), 0.0001);
    }

    /**
     * Malformed requests are programming errors and fail loudly.
     */
    public function test_missing_required_fields_throw(): void {
        $this->expectException(\coding_exception::class);
        client::complete(['component' => 'local_sentientia_aiquiz']);
    }

    /**
     * Flip both gateway flags ON via the platform registry (skips the
     * test if the platform plugin isn't installed in this Moodle).
     */
    private function enable_live_flags(): void {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            $this->markTestSkipped('local_sentientia_platform not installed');
        }
        \local_sentientia_platform\feature_flags::set(gateway::FLAG_GATEWAY, 0, true, 2, 'phpunit');
        \local_sentientia_platform\feature_flags::set(gateway::FLAG_LIVE, 0, true, 2, 'phpunit');
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    /**
     * A minimal normalised request for direct ledger writes.
     */
    private function req(): array {
        return [
            'component'  => 'local_sentientia_aiquiz',
            'purpose'    => 'quiz_generation',
            'userid'     => 2,
            'customerid' => 1,
            'tenantid'   => 1,
        ];
    }
}
