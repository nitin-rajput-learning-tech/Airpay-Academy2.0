<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * Anthropic API client for Sentientia LMS Skills Intelligence — skill
 * EXTRACTION.
 *
 * Two call modes (same 4-layer cost-defence pattern as sentientia_aiquiz):
 *
 *   - call_mock()  — deterministic fake extraction. Used when
 *                    sentientia.skillsai.live_api is OFF (default). Costs
 *                    nothing; lets the MVP demo end-to-end without spend.
 *
 *   - call_live()  — real HTTP POST to api.anthropic.com. Requires:
 *                      (a) sentientia.skillsai.enabled = ON
 *                      (b) sentientia.skillsai.live_api = ON
 *                      (c) local_sentientia_skillsai | api_key configured
 *                      (d) the caller passed the [CONFIRM] gate (enforced
 *                          at the UI layer in extract.php, NOT here)
 *
 * The mock honours the prompt version so a Hindi-locale author sees Hindi
 * mock skills end-to-end with no live spend. Mock skills carry a [MOCK]
 * marker in their evidence so a reviewer can never accidentally promote
 * them as canonical.
 *
 * NEVER log the API key. NEVER include the key in error_detail.
 *
 * @package local_sentientia_skillsai
 */
class anthropic_client {

    /** Default Anthropic model — kept in sync with admin setting `default_model`. */
    public const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /** Anthropic messages endpoint. */
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** Anthropic API version. */
    public const API_VERSION = '2023-06-01';

    /** Hard cap on output tokens. */
    public const MAX_OUTPUT_TOKENS = 4096;

    /** HTTP timeout, seconds. */
    public const HTTP_TIMEOUT = 60;

    /**
     * Top-level dispatcher. Routes to mock or live based on the flag.
     *
     * @param string     $sourcetext Source transcript/SOP/narration
     * @param int        $maxskills  Upper bound on extracted skills
     * @param string     $model      Anthropic model identifier
     * @param array|null $promptctx  Optional [version=>, template=>]
     * @return array {body, tokens_in, tokens_out, mode, error}
     */
    public static function extract(string $sourcetext, int $maxskills, string $model = self::DEFAULT_MODEL, ?array $promptctx = null): array {
        $promptctx = self::normalise_promptctx($promptctx);

        $islive = class_exists('\\local_sentientia_platform\\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.live_api');

        if (!$islive) {
            return self::call_mock($sourcetext, $maxskills, $promptctx);
        }

        return self::call_live($sourcetext, $maxskills, $model, $promptctx);
    }

    /**
     * Normalise the prompt-context array into a [version, template] pair.
     *
     * @param array|null $promptctx
     * @return array{version: string, template: ?string}
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
     * Deterministic mock response — used when live_api is OFF.
     *
     * Returns up to min($maxskills, 5) fixed skills shaped exactly like the
     * live API body. The skills are obviously fake (evidence contains
     * "[MOCK]") so a reviewer can't accidentally promote them silently.
     *
     * @param string     $sourcetext
     * @param int        $maxskills
     * @param array|null $promptctx
     * @return array
     */
    public static function call_mock(string $sourcetext, int $maxskills, ?array $promptctx = null): array {
        $promptctx = self::normalise_promptctx($promptctx);
        $hindi = ($promptctx['version'] === prompt_builder::VERSION_V2_HINDI);

        $maxskills = max(prompt_builder::MIN_SKILLS, min(prompt_builder::MAX_SKILLS, $maxskills));
        $count = min($maxskills, 5);

        $clean = trim((string) preg_replace('/\s+/u', ' ', trim($sourcetext)));
        $snippet = mb_substr($clean, 0, 60);
        if ($snippet === '') {
            $snippet = $hindi ? '(रिक्त स्रोत)' : '(empty source)';
        }

        $cats = ['Compliance', 'Technical', 'Process', 'Customer', 'Product'];
        $skills = [];
        for ($i = 1; $i <= $count; $i++) {
            if ($hindi) {
                $skills[] = [
                    'name'        => "[MOCK] कौशल {$i}",
                    'description' => "स्रोत \"{$snippet}\" से निकाला गया mock कौशल — Anthropic कॉल नहीं की गई।",
                    'category'    => $cats[($i - 1) % count($cats)],
                    'level'       => (($i - 1) % 5) + 1,
                    'confidence'  => 0.50 + 0.05 * $i,
                    'evidence'    => "[MOCK] feature flag sentientia.skillsai.live_api OFF है।",
                ];
            } else {
                $skills[] = [
                    'name'        => "[MOCK] Skill {$i}",
                    'description' => "Mock skill extracted from source \"{$snippet}\" — no Anthropic call was made.",
                    'category'    => $cats[($i - 1) % count($cats)],
                    'level'       => (($i - 1) % 5) + 1,
                    'confidence'  => 0.50 + 0.05 * $i,
                    'evidence'    => "[MOCK] feature flag sentientia.skillsai.live_api is OFF.",
                ];
            }
        }
        $body = json_encode(['skills' => $skills], JSON_UNESCAPED_UNICODE);

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
     * The caller MUST have checked the flags + [CONFIRM] gate. Failures are
     * returned as a result array (no thrown exceptions) so the UI can
     * persist the failed job for re-try / audit.
     *
     * @param string     $sourcetext
     * @param int        $maxskills
     * @param string     $model
     * @param array|null $promptctx
     * @return array {body, tokens_in, tokens_out, mode, error}
     */
    public static function call_live(string $sourcetext, int $maxskills, string $model, ?array $promptctx = null): array {
        $promptctx = self::normalise_promptctx($promptctx);

        $apikey = get_config('local_sentientia_skillsai', 'api_key');
        if (empty($apikey) || !is_string($apikey)) {
            return [
                'body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed', 'error' => 'api_key_not_set',
            ];
        }

        $system = prompt_builder::build_system_prompt($promptctx['version'], $promptctx['template']);
        $user   = prompt_builder::build_user_message($sourcetext, $maxskills, $promptctx['version']);

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
     *
     * @return bool
     */
    public static function is_live_ready(): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return false;
        }
        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.enabled')) {
            return false;
        }
        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.live_api')) {
            return false;
        }
        $key = get_config('local_sentientia_skillsai', 'api_key');
        return !empty($key);
    }
}
