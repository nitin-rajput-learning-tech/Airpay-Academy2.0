<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * Anthropic API client for Sentientia LMS AI Content Translation.
 *
 * Phase T.0 (MVP) ships TWO call modes:
 *
 *   - call_mock()  — deterministic pseudo-translation. Used when
 *                    sentientia.translate.live_api is OFF (default). Costs
 *                    nothing; lets the MVP demo end-to-end without
 *                    spending money or needing an API key. The mock
 *                    output is the source text wrapped with a visible
 *                    "[MOCK <lang>]" banner so a reviewer cannot mistake
 *                    it for a real translation.
 *
 *   - call_live()  — real HTTP POST to api.anthropic.com. Required:
 *                      (a) sentientia.translate.enabled = ON
 *                      (b) sentientia.translate.live_api = ON
 *                      (c) local_sentientia_translate | api_key configured
 *                      (d) The caller has passed the [CONFIRM] gate at
 *                          the UI layer (gate enforced by translate.php,
 *                          not by this client)
 *
 * NEVER log the API key. NEVER include the key in error_detail.
 * NEVER chain calls — one translation = one call.
 *
 * @package local_sentientia_translate
 */
class anthropic_client {

    /** Default Anthropic model — kept in sync with admin setting `default_model`. */
    public const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /** Anthropic messages endpoint. */
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** Anthropic API version pinned per docs (rev April 2024). */
    public const API_VERSION = '2023-06-01';

    /** Hard cap on output tokens. Translations can be long — allow headroom. */
    public const MAX_OUTPUT_TOKENS = 8192;

    /** HTTP timeout, seconds. */
    public const HTTP_TIMEOUT = 90;

    /**
     * Top-level dispatcher. Routes to mock or live based on the feature flag.
     *
     * @param string   $sourcetext     Source text to translate
     * @param string   $targetlang     Target language code
     * @param string[] $protectedterms Brand terms to keep verbatim
     * @param string   $model          Anthropic model identifier
     * @return array {body, tokens_in, tokens_out, mode, error}
     */
    public static function generate(string $sourcetext, string $targetlang, array $protectedterms = [], string $model = self::DEFAULT_MODEL): array {
        $islive = class_exists('\\local_airpay_core\\feature_flags')
            && \local_airpay_core\feature_flags::is_enabled('sentientia.translate.live_api');

        if (!$islive) {
            return self::call_mock($sourcetext, $targetlang);
        }

        return self::call_live($sourcetext, $targetlang, $protectedterms, $model);
    }

    /**
     * Deterministic mock response — used when sentientia.translate.live_api is OFF.
     *
     * Returns a body shaped exactly like the live API's: a JSON object with
     * translated_text + target_lang + brand_terms_preserved. The
     * "translation" is the source text prefixed with a visible mock banner.
     * Brand-override substitution is NOT applied here — that is the
     * translate_engine's deterministic post-processing job, so it runs
     * identically for mock and live output.
     *
     * @param string $sourcetext
     * @param string $targetlang
     * @return array
     */
    public static function call_mock(string $sourcetext, string $targetlang): array {
        $banner = "[MOCK {$targetlang}] ";
        // Echo the source so the brand-override post-processing pass has
        // real tokens to act on (proves the pipeline is wired end-to-end).
        $translated = $banner . trim($sourcetext);

        $body = json_encode([
            'translated_text'       => $translated,
            'target_lang'           => $targetlang,
            'brand_terms_preserved' => [],
        ], JSON_UNESCAPED_UNICODE);

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
     *  - Checked sentientia.translate.enabled = ON
     *  - Checked sentientia.translate.live_api = ON
     *  - Verified the user passed the [CONFIRM] gate
     *  - Provided a valid model identifier
     *
     * Failures are returned as a result array (no thrown exceptions) so
     * the UI can persist the failed translation for re-try / audit.
     *
     * @param string   $sourcetext
     * @param string   $targetlang
     * @param string[] $protectedterms
     * @param string   $model
     * @return array {body, tokens_in, tokens_out, mode, error}
     */
    public static function call_live(string $sourcetext, string $targetlang, array $protectedterms, string $model): array {
        $apikey = get_config('local_sentientia_translate', 'api_key');
        if (empty($apikey) || !is_string($apikey)) {
            return [
                'body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed', 'error' => 'api_key_not_set',
            ];
        }

        $system = prompt_builder::build_system_prompt($targetlang, $protectedterms);
        $user   = prompt_builder::build_user_message($sourcetext, $targetlang);

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
     *
     * @return bool
     */
    public static function is_live_ready(): bool {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return false;
        }
        if (!\local_airpay_core\feature_flags::is_enabled('sentientia.translate.enabled')) {
            return false;
        }
        if (!\local_airpay_core\feature_flags::is_enabled('sentientia.translate.live_api')) {
            return false;
        }
        $key = get_config('local_sentientia_translate', 'api_key');
        return !empty($key);
    }
}
