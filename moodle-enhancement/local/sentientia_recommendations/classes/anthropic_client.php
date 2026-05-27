<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * Anthropic API client for Sentientia LMS AI Course Recommendations.
 *
 * Phase H.0 (MVP) ships TWO call modes:
 *
 *   - call_mock()  — deterministic recommendation list. Used when
 *                    sentientia.recommendations.live_api is OFF (default).
 *                    Costs nothing; lets the MVP demo end-to-end without
 *                    spending money or needing an API key.
 *
 *   - call_live()  — real HTTP POST to api.anthropic.com. Required:
 *                      (a) sentientia.recommendations.enabled = ON
 *                      (b) sentientia.recommendations.live_api = ON
 *                      (c) local_sentientia_recommendations | api_key configured
 *                      (d) The caller has passed the [CONFIRM] gate at
 *                          the UI / cron layer (gate enforced by
 *                          generate.php and the cron task, not by this
 *                          client)
 *
 * The [CONFIRM] gate lives outside this class because it's a per-action
 * decision. This class is plumbing — it executes the call the UI / cron
 * authorised. Tests use call_mock(); audit tests cover the gate logic
 * by setting both flags and stubbing call_live() out (see tests/).
 *
 * NEVER log the API key. NEVER include the key in error_detail.
 * NEVER chain calls — one generation = one call.
 *
 * @package local_sentientia_recommendations
 */
class anthropic_client {

    /** Default Anthropic model — kept in sync with admin setting `default_model`. */
    public const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /** Anthropic messages endpoint. */
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** Anthropic API version pinned per docs (rev April 2024). */
    public const API_VERSION = '2023-06-01';

    /** Hard cap on output tokens. ~10 recommendations fit comfortably in ~2000. */
    public const MAX_OUTPUT_TOKENS = 2048;

    /** HTTP timeout, seconds. */
    public const HTTP_TIMEOUT = 60;

    /**
     * Top-level dispatcher. Routes to mock or live based on the feature flag.
     *
     * @param \stdClass $profile      Learner profile object (see prompt_builder::build_user_message)
     * @param array     $candidates   Candidate course list
     * @param int       $numrequested 1..MAX_RECOMMENDATIONS
     * @param string    $model        Anthropic model identifier
     * @return array {body: string, tokens_in: int, tokens_out: int, mode: 'mock'|'live'|'failed', error: ?string}
     */
    public static function generate(\stdClass $profile, array $candidates, int $numrequested, string $model = self::DEFAULT_MODEL): array {
        $islive = class_exists('\\local_airpay_core\\feature_flags')
            && \local_airpay_core\feature_flags::is_enabled('sentientia.recommendations.live_api');

        if (!$islive) {
            return self::call_mock($profile, $candidates, $numrequested);
        }

        return self::call_live($profile, $candidates, $numrequested, $model);
    }

    /**
     * Deterministic mock response — used when sentientia.recommendations.live_api is OFF.
     *
     * Picks the first $numrequested candidates that the learner has NOT
     * already completed. Returns a fully-shaped Anthropic-style body so
     * the parser + persistence layers can be exercised. Reasoning strings
     * start with "[MOCK]" so a reviewer cannot mistake the output for
     * real Claude-generated content.
     *
     * @param \stdClass $profile
     * @param array     $candidates
     * @param int       $numrequested
     * @return array
     */
    public static function call_mock(\stdClass $profile, array $candidates, int $numrequested): array {
        $numrequested = max(prompt_builder::MIN_RECOMMENDATIONS,
            min(prompt_builder::MAX_RECOMMENDATIONS, $numrequested));

        $completed = isset($profile->completed) && is_array($profile->completed)
            ? array_map('intval', $profile->completed) : [];

        $recs = [];
        $rank = 1;
        foreach ($candidates as $c) {
            if (!isset($c->id)) {
                continue;
            }
            $cid = (int)$c->id;
            if ($cid <= 0) {
                continue;
            }
            if (in_array($cid, $completed, true)) {
                continue;
            }
            $name = isset($c->fullname) ? (string)$c->fullname : "Course #{$cid}";
            // Deterministic decreasing score so order is stable.
            $score = max(10, 95 - ($rank * 5));
            $recs[] = [
                'course_id' => $cid,
                'score'     => $score,
                'reasoning' => "[MOCK] {$name} is a sensible next step based on this learner's profile (sentientia.recommendations.live_api is OFF).",
            ];
            $rank++;
            if (count($recs) >= $numrequested) {
                break;
            }
        }

        $body = json_encode(['recommendations' => $recs], JSON_UNESCAPED_UNICODE);
        return [
            'body'       => $body,
            'tokens_in'  => 0,
            'tokens_out' => 0,
            'mode'       => 'mock',
            'error'      => null,
        ];
    }

    /**
     * Live API call to api.anthropic.com.
     *
     * The caller MUST have:
     *  - Checked sentientia.recommendations.enabled = ON
     *  - Checked sentientia.recommendations.live_api = ON
     *  - Verified the user / task passed the [CONFIRM] gate
     *  - Provided a valid model identifier
     *
     * Failures are returned as a result array (no thrown exceptions) so
     * the caller can persist the failed batch for re-try / audit.
     *
     * @param \stdClass $profile
     * @param array     $candidates
     * @param int       $numrequested
     * @param string    $model
     * @return array {body, tokens_in, tokens_out, mode, error}
     */
    public static function call_live(\stdClass $profile, array $candidates, int $numrequested, string $model): array {
        $apikey = get_config('local_sentientia_recommendations', 'api_key');
        if (empty($apikey) || !is_string($apikey)) {
            return [
                'body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed', 'error' => 'api_key_not_set',
            ];
        }

        $system = prompt_builder::build_system_prompt();
        $user   = prompt_builder::build_user_message($profile, $candidates, $numrequested);

        $payload = [
            'model'      => $model,
            'max_tokens' => self::MAX_OUTPUT_TOKENS,
            'system'     => $system,
            'messages'   => [
                ['role' => 'user', 'content' => $user],
            ],
        ];

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apikey,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
        ]);

        $raw      = curl_exec($ch);
        $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [
                'body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed',
                'error' => 'curl_error: ' . substr($curlerr ?: 'unknown', 0, 200),
            ];
        }

        if ($httpcode !== 200) {
            $msg = "http_{$httpcode}";
            $decoded = json_decode((string)$raw, true);
            if (is_array($decoded) && isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                $msg .= ': ' . substr($decoded['error']['message'], 0, 200);
            }
            return [
                'body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed', 'error' => $msg,
            ];
        }

        $decoded    = json_decode((string)$raw, true);
        $text       = '';
        $tokens_in  = 0;
        $tokens_out = 0;
        if (is_array($decoded)) {
            if (isset($decoded['content'][0]['text']) && is_string($decoded['content'][0]['text'])) {
                $text = $decoded['content'][0]['text'];
            }
            if (isset($decoded['usage']['input_tokens']) && is_int($decoded['usage']['input_tokens'])) {
                $tokens_in = $decoded['usage']['input_tokens'];
            }
            if (isset($decoded['usage']['output_tokens']) && is_int($decoded['usage']['output_tokens'])) {
                $tokens_out = $decoded['usage']['output_tokens'];
            }
        }

        if ($text === '') {
            return [
                'body' => '', 'tokens_in' => $tokens_in, 'tokens_out' => $tokens_out,
                'mode' => 'failed', 'error' => 'empty_response_body',
            ];
        }

        return [
            'body'       => $text,
            'tokens_in'  => $tokens_in,
            'tokens_out' => $tokens_out,
            'mode'       => 'live',
            'error'      => null,
        ];
    }

    /**
     * Is the feature flag enabled AND an API key configured?
     * Used by the UI to decide whether to show the "Live API" badge.
     *
     * @return bool
     */
    public static function is_live_ready(): bool {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return false;
        }
        if (!\local_airpay_core\feature_flags::is_enabled('sentientia.recommendations.enabled')) {
            return false;
        }
        if (!\local_airpay_core\feature_flags::is_enabled('sentientia.recommendations.live_api')) {
            return false;
        }
        $key = get_config('local_sentientia_recommendations', 'api_key');
        return !empty($key);
    }
}
