<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Anthropic API client for Sentientia LMS AI Quiz Generation.
 *
 * Phase G.0 (MVP) ships TWO call modes:
 *
 *   - call_mock()  — deterministic 10-question fake response. Used when
 *                    sentientia.aiquiz.live_api is OFF (default). Costs
 *                    nothing; lets the MVP demo end-to-end without
 *                    spending money or needing an API key.
 *
 *   - call_live()  — real HTTP POST to api.anthropic.com. Required:
 *                      (a) sentientia.aiquiz.enabled = ON
 *                      (b) sentientia.aiquiz.live_api = ON
 *                      (c) local_sentientia_aiquiz | api_key configured
 *                      (d) The caller has passed the [CONFIRM] gate at
 *                          the UI layer (gate enforced by generate.php,
 *                          not by this client)
 *
 * The [CONFIRM] gate lives in the UI because it's a per-user-action
 * decision. This class is plumbing — it executes the call the UI
 * authorised. Tests use call_mock(); audit tests cover the gate logic
 * by setting both flags and stubbing call_live() out (see tests/).
 *
 * NEVER log the API key. NEVER include the key in error_detail.
 * NEVER chain calls — one generation = one call.
 *
 * @package local_sentientia_aiquiz
 */
class anthropic_client {

    /** Default Anthropic model — kept in sync with admin setting `default_model`. */
    public const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /** Anthropic messages endpoint. */
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** Anthropic API version pinned per docs (rev April 2024). */
    public const API_VERSION = '2023-06-01';

    /** Hard cap on output tokens. ~10 questions of multichoice fit in ~2000. */
    public const MAX_OUTPUT_TOKENS = 4096;

    /** HTTP timeout, seconds. */
    public const HTTP_TIMEOUT = 60;

    /**
     * Top-level dispatcher. Routes to mock or live based on the feature flag.
     *
     * @param string $sourcetext   Trainer-supplied source
     * @param int    $numrequested 1..MAX_QUESTIONS
     * @param string $model        Anthropic model identifier (e.g. 'claude-sonnet-4-6')
     * @return array {body: string, tokens_in: int, tokens_out: int, mode: 'mock'|'live'|'failed', error: ?string}
     */
    public static function generate(string $sourcetext, int $numrequested, string $model = self::DEFAULT_MODEL): array {
        $islive = class_exists('\\local_airpay_core\\feature_flags')
            && \local_airpay_core\feature_flags::is_enabled('sentientia.aiquiz.live_api');

        if (!$islive) {
            return self::call_mock($sourcetext, $numrequested);
        }

        return self::call_live($sourcetext, $numrequested, $model);
    }

    /**
     * Deterministic mock response — used when sentientia.aiquiz.live_api is OFF.
     *
     * Returns 10 questions (or $numrequested, whichever is smaller) shaped
     * exactly like the live API's body. Lets the UI + parser + persistence
     * paths be exercised without spending money or needing internet.
     *
     * The questions are obviously fake (mention "MOCK" in qtext) so a
     * reviewer can't accidentally push them through to learners.
     *
     * @param string $sourcetext
     * @param int    $numrequested
     * @return array
     */
    public static function call_mock(string $sourcetext, int $numrequested): array {
        $numrequested = max(1, min(prompt_builder::MAX_QUESTIONS, $numrequested));
        // Use the first 80 chars of source as a snippet for the mock stem so
        // the trainer sees their input reflected back — proves the pipeline
        // is wired end-to-end.
        $snippet = trim(preg_replace('/\s+/u', ' ', mb_substr(trim($sourcetext), 0, 80)));
        if ($snippet === '') {
            $snippet = '(empty source)';
        }

        $questions = [];
        for ($i = 1; $i <= $numrequested; $i++) {
            $questions[] = [
                'qtype'         => 'multichoice',
                'qtext'         => "[MOCK Q{$i}] Which statement best reflects the source about \"{$snippet}\"?",
                'qoptions'      => [
                    "Mock answer A for Q{$i}",
                    "Mock answer B for Q{$i}",
                    "Mock answer C for Q{$i} (CORRECT)",
                    "Mock answer D for Q{$i}",
                ],
                'qanswer_index' => 2,
                'qexplanation'  => "This is a mock explanation produced without calling Anthropic — feature flag sentientia.aiquiz.live_api is OFF.",
            ];
        }
        $body = json_encode(['questions' => $questions], JSON_UNESCAPED_UNICODE);

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
     *  - Checked sentientia.aiquiz.enabled = ON
     *  - Checked sentientia.aiquiz.live_api = ON
     *  - Verified the user passed the [CONFIRM] gate
     *  - Provided a valid model identifier
     *
     * Failures are returned as a result array (no thrown exceptions) so
     * the UI can persist the failed draft for re-try / audit.
     *
     * @param string $sourcetext
     * @param int    $numrequested
     * @param string $model
     * @return array {body, tokens_in, tokens_out, mode, error}
     */
    public static function call_live(string $sourcetext, int $numrequested, string $model): array {
        $apikey = get_config('local_sentientia_aiquiz', 'api_key');
        if (empty($apikey) || !is_string($apikey)) {
            return [
                'body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed', 'error' => 'api_key_not_set',
            ];
        }

        $system = prompt_builder::build_system_prompt();
        $user   = prompt_builder::build_user_message($sourcetext, $numrequested);

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

        $raw = curl_exec($ch);
        $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [
                'body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed',
                'error' => 'curl_error: ' . substr($curlerr ?: 'unknown', 0, 200),
            ];
        }

        if ($httpcode !== 200) {
            // Decode the error message but NEVER leak the API key.
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

        $decoded = json_decode((string)$raw, true);
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
        if (!\local_airpay_core\feature_flags::is_enabled('sentientia.aiquiz.enabled')) {
            return false;
        }
        if (!\local_airpay_core\feature_flags::is_enabled('sentientia.aiquiz.live_api')) {
            return false;
        }
        $key = get_config('local_sentientia_aiquiz', 'api_key');
        return !empty($key);
    }
}
