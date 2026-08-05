<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai;

defined('MOODLE_INTERNAL') || die();

/**
 * The gateway engine — routing, quota enforcement, and the single live
 * HTTP path to Anthropic. Consumers never call this directly; they go
 * through \local_sentientia_ai\client::complete().
 *
 * Routing (in order, first match wins):
 *   1. gateway flag OFF or live flag OFF  → MOCK (component mock, else
 *      generic; ledger mode 'mock', zero cost)
 *   2. no API key (central, then legacy fallback) → FAILED
 *      'api_key_not_set' (never silently mocks on a live-intent path —
 *      when both live flags are ON, a missing key is a config error the
 *      caller must see)
 *   3. quota exceeded (any of the three caps) → DENIED
 *      'quota_exceeded:<which>' (fail-closed; never degrades to mock so
 *      fake content can't impersonate a real generation)
 *   4. live HTTP call → LIVE or FAILED
 *
 * Every branch writes a ledger row. NEVER log or echo the API key.
 *
 * @package local_sentientia_ai
 */
class gateway {

    /** Anthropic messages endpoint. */
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** Anthropic API version header. */
    public const API_VERSION = '2023-06-01';

    /** Hard ceiling on output tokens per call, whatever the caller asks for. */
    public const HARD_MAX_TOKENS = 8192;

    /** Default per-call output-token cap when the caller doesn't specify. */
    public const DEFAULT_MAX_TOKENS = 4096;

    /** HTTP timeout, seconds. */
    public const HTTP_TIMEOUT = 60;

    /** Gateway master flag. */
    public const FLAG_GATEWAY = 'sentientia.ai.gateway.enabled';

    /** Org-level live-spend flag (signed Addendum A). */
    public const FLAG_LIVE = 'sentientia.ai.live_api.enabled';

    /**
     * ESTIMATED pricing per 1M tokens in USD [input, output]. A budgeting
     * signal for the ledger/quotas — not an invoice. Unknown models use
     * 'default' (priced at the top tier so estimates err on the safe,
     * quota-consuming side — fail-closed philosophy).
     */
    public const PRICING_PER_MTOK = [
        'claude-sonnet-4-6' => [3.00, 15.00],
        'claude-sonnet-4-5' => [3.00, 15.00],
        'claude-haiku-4-5'  => [1.00, 5.00],
        'claude-opus-4-1'   => [15.00, 75.00],
        'default'           => [15.00, 75.00],
    ];

    /**
     * Execute a normalised request (see client::complete() for the shape).
     *
     * @param array $req Normalised request from client::complete().
     * @return array {body, tokens_in, tokens_out, mode, error, ledgerid}
     */
    public static function execute(array $req): array {
        $gatewayon = self::flag(self::FLAG_GATEWAY);
        $liveon = self::flag(self::FLAG_LIVE);

        if (!$gatewayon || !$liveon) {
            $body = self::mock_body($req);
            $ledgerid = ledger::record($req, 'mock', 0, 0, 0.0, '');
            return self::result($body, 0, 0, 'mock', null, $ledgerid);
        }

        $apikey = self::resolve_api_key($req);
        if ($apikey === '') {
            $ledgerid = ledger::record($req, 'failed', 0, 0, 0.0, 'api_key_not_set');
            return self::result('', 0, 0, 'failed', 'api_key_not_set', $ledgerid);
        }

        $denied = self::check_quotas($req);
        if ($denied !== null) {
            $ledgerid = ledger::record($req, 'denied', 0, 0, 0.0, $denied);
            return self::result('', 0, 0, 'denied', $denied, $ledgerid);
        }

        return self::call_live($req, $apikey);
    }

    /**
     * Resolve a feature flag through the platform registry, fail-safe false.
     */
    protected static function flag(string $key): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return false;
        }
        try {
            return \local_sentientia_platform\feature_flags::is_enabled($key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Central key first; the calling component's legacy api_key setting as
     * a migration bridge (so a key configured pre-gateway keeps working
     * until ops consolidates onto the central one).
     */
    protected static function resolve_api_key(array $req): string {
        $key = (string) get_config('local_sentientia_ai', 'api_key');
        if ($key === '' && !empty($req['legacy_component'])) {
            $key = (string) get_config($req['legacy_component'], 'api_key');
        }
        return $key;
    }

    /**
     * Fail-closed quota checks against the ledger's live rows.
     *
     * A zero/empty cap means NO live allowance (never "unlimited") — the
     * signed Addendum-A budget is a hard ceiling, and an unset setting
     * must not become an unbounded one.
     *
     * @param array $req
     * @return string|null Denial reason, or null when all caps have headroom.
     */
    protected static function check_quotas(array $req): ?string {
        $dailyglobal = (int) get_config('local_sentientia_ai', 'daily_tokens_global');
        $dailycustomer = (int) get_config('local_sentientia_ai', 'daily_tokens_customer');
        $monthlycost = (float) get_config('local_sentientia_ai', 'monthly_cost_cap_usd');

        if ($dailyglobal <= 0 || $dailycustomer <= 0 || $monthlycost <= 0) {
            return 'quota_exceeded:cap_unset';
        }
        if (ledger::tokens_today() >= $dailyglobal) {
            return 'quota_exceeded:daily_global';
        }
        if (ledger::tokens_today((int) $req['customerid']) >= $dailycustomer) {
            return 'quota_exceeded:daily_customer';
        }
        if (ledger::cost_this_month() >= $monthlycost) {
            return 'quota_exceeded:monthly_cost';
        }
        return null;
    }

    /**
     * The component's mock (callable or literal string body), else a
     * generic deterministic mock that reflects the input back.
     *
     * @param array $req
     * @return string
     */
    protected static function mock_body(array $req): string {
        if (isset($req['mock'])) {
            if (is_callable($req['mock'])) {
                try {
                    return (string) call_user_func($req['mock'], $req);
                } catch (\Throwable $e) {
                    debugging('sentientia_ai mock callable threw: ' . $e->getMessage(),
                        DEBUG_DEVELOPER);
                }
            } else if (is_string($req['mock']) && $req['mock'] !== '') {
                return $req['mock'];
            }
        }
        $snippet = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $req['usertext'])), 0, 80);
        return '[MOCK sentientia_ai] Deterministic gateway mock for '
            . $req['component'] . '/' . $req['purpose']
            . ' — live flags OFF, zero spend. Input: "' . $snippet . '"';
    }

    /**
     * Estimated USD cost from the pricing map.
     *
     * @param string $model
     * @param int $tokensin
     * @param int $tokensout
     * @return float
     */
    public static function estimate_cost(string $model, int $tokensin, int $tokensout): float {
        $rates = self::PRICING_PER_MTOK[$model] ?? self::PRICING_PER_MTOK['default'];
        return round(($tokensin * $rates[0] + $tokensout * $rates[1]) / 1000000, 6);
    }

    /**
     * The single live HTTP path. Mirrors the hardened per-plugin clients
     * (aiquiz reference): result-array failures, no exceptions, no key in
     * any error string. PHP cURL, matching the house pattern in
     * local_sentientia_aiquiz\anthropic_client::call_live().
     *
     * @param array $req
     * @param string $apikey
     * @return array
     */
    protected static function call_live(array $req, string $apikey): array {
        $model = $req['model'] !== '' ? $req['model']
            : ((string) get_config('local_sentientia_ai', 'default_model') ?: 'claude-sonnet-4-6');

        // Structural no-spend guard: automated test runs can NEVER reach the
        // real API, whatever the flags/key/quota state says. This raw-cURL
        // path bypasses Moodle's \curl wrapper (and so its phpunit blocked-
        // hosts protection), so the guard lives here. Surfaced as a normal
        // 'failed' result, never an exception — same contract as any other
        // live-path failure. (Found the hard way: the first test run POSTed
        // a fake key to api.anthropic.com because install-applied setting
        // defaults gave the quota check headroom.)
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || defined('BEHAT_SITE_RUNNING')) {
            $ledgerid = ledger::record($req, 'failed', 0, 0, 0.0,
                'live_blocked_in_tests', $model);
            return self::result('', 0, 0, 'failed', 'live_blocked_in_tests', $ledgerid);
        }
        $maxtokens = min(max(1, (int) $req['max_tokens']), self::HARD_MAX_TOKENS);

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxtokens,
            'messages'   => [
                ['role' => 'user', 'content' => $req['usertext']],
            ],
        ];
        if ($req['system'] !== '') {
            $payload['system'] = $req['system'];
        }

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apikey,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
        ]);

        $raw = curl_exec($ch);
        $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $err = 'curl_error: ' . substr($curlerr ?: 'unknown', 0, 200);
            $ledgerid = ledger::record($req, 'failed', 0, 0, 0.0, $err, $model);
            return self::result('', 0, 0, 'failed', $err, $ledgerid);
        }

        if ($httpcode !== 200) {
            $err = "http_{$httpcode}";
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded) && isset($decoded['error']['message'])
                    && is_string($decoded['error']['message'])) {
                $err .= ': ' . substr($decoded['error']['message'], 0, 200);
            }
            $ledgerid = ledger::record($req, 'failed', 0, 0, 0.0, $err, $model);
            return self::result('', 0, 0, 'failed', $err, $ledgerid);
        }

        $decoded = json_decode((string) $raw, true);
        $text = '';
        $tokensin = 0;
        $tokensout = 0;
        if (is_array($decoded)) {
            if (isset($decoded['content'][0]['text']) && is_string($decoded['content'][0]['text'])) {
                $text = $decoded['content'][0]['text'];
            }
            if (isset($decoded['usage']['input_tokens']) && is_int($decoded['usage']['input_tokens'])) {
                $tokensin = $decoded['usage']['input_tokens'];
            }
            if (isset($decoded['usage']['output_tokens']) && is_int($decoded['usage']['output_tokens'])) {
                $tokensout = $decoded['usage']['output_tokens'];
            }
        }

        $cost = self::estimate_cost($model, $tokensin, $tokensout);

        if ($text === '') {
            $ledgerid = ledger::record($req, 'failed', $tokensin, $tokensout, $cost,
                'empty_response_body', $model);
            return self::result('', $tokensin, $tokensout, 'failed', 'empty_response_body', $ledgerid);
        }

        $ledgerid = ledger::record($req, 'live', $tokensin, $tokensout, $cost, '', $model);
        return self::result($text, $tokensin, $tokensout, 'live', null, $ledgerid);
    }

    /**
     * Shape the result array — a superset of the per-plugin clients'
     * historical shape so consumer migration is mechanical.
     *
     * @param string $body
     * @param int $in
     * @param int $out
     * @param string $mode
     * @param string|null $error
     * @param int $ledgerid
     * @return array
     */
    protected static function result(string $body, int $in, int $out,
            string $mode, ?string $error, int $ledgerid): array {
        return [
            'body'       => $body,
            'tokens_in'  => $in,
            'tokens_out' => $out,
            'mode'       => $mode,
            'error'      => $error,
            'ledgerid'   => $ledgerid,
        ];
    }
}
