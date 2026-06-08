<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Anthropic API client for Sentientia LMS AI Quiz Generation.
 *
 * Phase G.0 (MVP) shipped TWO call modes:
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
 * Phase G.1 (2026-05-25) — every call mode now accepts an optional
 * `$promptctx` array describing which prompt version + customer
 * template to use:
 *
 *     [
 *       'version'  => 'v1' | 'v2-hindi',
 *       'template' => null | '<customer-pasted prompt body>',
 *     ]
 *
 * The mock client also honours the version (produces Devanagari mock
 * questions when v2-hindi is requested) so a trainer driving the UI in
 * Hindi sees Hindi mock content end-to-end with no live spend.
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
     * @param string     $sourcetext   Trainer-supplied source
     * @param int        $numrequested 1..MAX_QUESTIONS
     * @param string     $model        Anthropic model identifier (e.g. 'claude-sonnet-4-6')
     * @param array|null $promptctx    Optional [version=>?, template=>?] from
     *                                  prompt_builder::resolve_for(). Defaults
     *                                  to v1 / no template (Phase G.0 behaviour).
     * @return array {body: string, tokens_in: int, tokens_out: int, mode: 'mock'|'live'|'failed', error: ?string}
     */
    public static function generate(string $sourcetext, int $numrequested, string $model = self::DEFAULT_MODEL, ?array $promptctx = null): array {
        $promptctx = self::normalise_promptctx($promptctx);

        $islive = class_exists('\\local_sentientia_platform\\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.aiquiz.live_api');

        if (!$islive) {
            return self::call_mock($sourcetext, $numrequested, $promptctx);
        }

        return self::call_live($sourcetext, $numrequested, $model, $promptctx);
    }

    /**
     * Normalise the prompt-context array into a [version, template] pair.
     *
     * Accepts null / partial input and fills in sane defaults so callers
     * don't have to construct the full shape themselves. Unknown versions
     * fall back to v1.
     *
     * @param array|null $promptctx
     * @return array {version: string, template: ?string}
     */
    private static function normalise_promptctx(?array $promptctx): array {
        $version = prompt_builder::VERSION_V1;
        $template = null;

        if (is_array($promptctx)) {
            if (isset($promptctx['version']) && is_string($promptctx['version'])
                    && in_array($promptctx['version'], prompt_builder::valid_versions(), true)) {
                $version = $promptctx['version'];
            }
            if (isset($promptctx['template']) && is_string($promptctx['template'])
                    && trim($promptctx['template']) !== '') {
                $template = $promptctx['template'];
            }
        }

        return ['version' => $version, 'template' => $template];
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
     * Phase G.1 — when `$promptctx['version']` is `v2-hindi`, the mock
     * payload uses Devanagari stems / options / explanation so a Hindi-
     * locale trainer sees Hindi UI content end-to-end without any live
     * spend. The `[MOCK]` marker stays in Latin so reviewers spot it
     * regardless of language.
     *
     * @param string     $sourcetext
     * @param int        $numrequested
     * @param array|null $promptctx Optional [version=>, template=>]
     * @return array
     */
    public static function call_mock(string $sourcetext, int $numrequested, ?array $promptctx = null): array {
        $promptctx = self::normalise_promptctx($promptctx);
        $hindi = ($promptctx['version'] === prompt_builder::VERSION_V2_HINDI);

        $numrequested = max(1, min(prompt_builder::MAX_QUESTIONS, $numrequested));
        // Use the first 80 chars (unicode-safe) of source as a snippet for
        // the mock stem so the trainer sees their input reflected back —
        // proves the pipeline is wired end-to-end.
        $clean = trim((string) preg_replace('/\s+/u', ' ', trim($sourcetext)));
        $snippet = mb_substr($clean, 0, 80);
        if ($snippet === '') {
            $snippet = $hindi ? '(रिक्त स्रोत)' : '(empty source)';
        }

        $questions = [];
        for ($i = 1; $i <= $numrequested; $i++) {
            if ($hindi) {
                $questions[] = [
                    'qtype'         => 'multichoice',
                    'qtext'         => "[MOCK प्रश्न {$i}] स्रोत \"{$snippet}\" के अनुसार कौन-सा कथन सर्वाधिक उपयुक्त है?",
                    'qoptions'      => [
                        "नकली विकल्प A (प्रश्न {$i})",
                        "नकली विकल्प B (प्रश्न {$i})",
                        "नकली विकल्प C (प्रश्न {$i}) — सही",
                        "नकली विकल्प D (प्रश्न {$i})",
                    ],
                    'qanswer_index' => 2,
                    'qexplanation'  => "यह एक mock व्याख्या है — Anthropic कॉल नहीं की गई (feature flag sentientia.aiquiz.live_api OFF है)।",
                ];
            } else {
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
     * Phase G.1 — `$promptctx` selects the system prompt body:
     *   - When `$promptctx['template']` is a non-empty string, that
     *     literal text becomes the system prompt (admin override).
     *   - Otherwise the version-dispatched baseline runs (v1 = English,
     *     v2-hindi = Hindi).
     * The user-message wrapper always follows the version's locale.
     *
     * @param string     $sourcetext
     * @param int        $numrequested
     * @param string     $model
     * @param array|null $promptctx
     * @return array {body, tokens_in, tokens_out, mode, error}
     */
    public static function call_live(string $sourcetext, int $numrequested, string $model, ?array $promptctx = null): array {
        $promptctx = self::normalise_promptctx($promptctx);

        $apikey = get_config('local_sentientia_aiquiz', 'api_key');
        if (empty($apikey) || !is_string($apikey)) {
            return [
                'body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed', 'error' => 'api_key_not_set',
            ];
        }

        $system = prompt_builder::build_system_prompt($promptctx['version'], $promptctx['template']);
        $user   = prompt_builder::build_user_message($sourcetext, $numrequested, $promptctx['version']);

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
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
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
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return false;
        }
        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.aiquiz.enabled')) {
            return false;
        }
        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.aiquiz.live_api')) {
            return false;
        }
        $key = get_config('local_sentientia_aiquiz', 'api_key');
        return !empty($key);
    }
}
