<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Anthropic course-generation client for the Authoring Studio.
 *
 * TWO call modes, exactly like local_sentientia_aiquiz:
 *
 *   - call_mock()  — deterministic full-module fake (cards + all three
 *                    question types). Used when sentientia.authoring.live_api
 *                    is OFF (default). Zero cost, no key, no internet — makes
 *                    the studio demoable end to end.
 *   - call_live()  — real HTTP POST to api.anthropic.com. Requires:
 *                      (a) sentientia.authoring.enabled = ON
 *                      (b) sentientia.authoring.live_api = ON
 *                      (c) local_sentientia_authoring | anthropic_api_key set
 *                      (d) the caller passed the [CONFIRM] gate at the UI layer
 *
 * The [CONFIRM] gate lives in the UI (studio.php) because it is a per-user-
 * action decision. This class is plumbing — it executes the authorised call.
 * Tests use call_mock(); live is never exercised in tests.
 *
 * NEVER log the API key. NEVER include the key in error_detail.
 * NEVER chain calls — one generation = one call.
 *
 * @package local_sentientia_authoring
 */
class course_generator {

    /** Default Anthropic model. */
    public const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /** Anthropic messages endpoint. */
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** Anthropic API version. */
    public const API_VERSION = '2023-06-01';

    /** Output-token ceiling — a full module fits comfortably. */
    public const MAX_OUTPUT_TOKENS = 8192;

    /** HTTP timeout, seconds. */
    public const HTTP_TIMEOUT = 90;

    /**
     * Top-level dispatcher: routes to mock or live based on the live_api flag.
     *
     * @param string      $sourcetext   Trainer-supplied source.
     * @param int         $numcards     Desired card count.
     * @param int         $numquestions Desired question count.
     * @param string      $model        Anthropic model identifier.
     * @param string      $version      Prompt version (v1 | v2-hindi).
     * @param string|null $templatebody  Optional instructional-design template body.
     * @return array {body: string, tokens_in: int, tokens_out: int, mode: 'mock'|'live'|'failed', error: ?string}
     */
    public static function generate(string $sourcetext, int $numcards, int $numquestions,
            string $model = self::DEFAULT_MODEL, string $version = prompt_builder::VERSION_V1,
            ?string $templatebody = null): array {

        $islive = class_exists('\\local_sentientia_platform\\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.live_api');

        // ── Gateway delegation (ADR-028 Phase 2.3, 2026-08-05) ──────────
        // OPT-IN via sentientia.ai.gateway.enabled (the routing switch):
        // while OFF — the default — this plugin behaves byte- AND
        // side-effect-identically to the pre-gateway build (local mock, no
        // ledger writes; its own tests and any deployment without the
        // gateway are unaffected). When ON, routing goes through the
        // gateway: central key + spend ledger + fail-closed quotas become
        // layer 0 underneath this plugin's own gates
        // (sentientia.authoring.enabled / .live_api / studio.php's
        // [CONFIRM] — all still enforced there). Our mock is passed down
        // as a callable so mock fidelity (v2-hindi Devanagari cards +
        // all three question types) stays byte-identical. NOTE: this
        // plugin's legacy key lives under `anthropic_api_key`, which the
        // gateway's `legacy_component` bridge (it reads `api_key`) does
        // NOT pick up — on the gateway path the CENTRAL
        // local_sentientia_ai key is the one that counts. The authoring
        // live_api flag above remains authoritative for THIS plugin's
        // live intent. (Scope: Anthropic course generation only —
        // tts_client / ElevenLabs is outside the gateway.)
        $routegateway = class_exists('\\local_sentientia_ai\\client')
            && class_exists('\\local_sentientia_platform\\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled(
                \local_sentientia_ai\gateway::FLAG_GATEWAY);
        if ($routegateway) {
            $mockfn = function (array $req) use ($sourcetext, $numcards, $numquestions, $version): string {
                return self::call_mock($sourcetext, $numcards, $numquestions, $version)['body'];
            };
            if (!$islive) {
                // Explicit mock intent — reuse our mock directly but still
                // ledger the call for the org-wide usage picture.
                $result = \local_sentientia_ai\client::complete([
                    'component'        => 'local_sentientia_authoring',
                    'purpose'          => 'course_generation',
                    'usertext'         => '[mock-intent] ' . mb_substr($sourcetext, 0, 200),
                    'mock'             => $mockfn,
                    'legacy_component' => 'local_sentientia_authoring',
                ]);
                // Force mock semantics regardless of gateway flags: this
                // plugin's live gate said no.
                if ($result['mode'] !== 'mock') {
                    return self::call_mock($sourcetext, $numcards, $numquestions, $version);
                }
                return [
                    'body'       => $result['body'],
                    'tokens_in'  => 0,
                    'tokens_out' => 0,
                    'mode'       => 'mock',
                    'error'      => null,
                ];
            }
            $system = prompt_builder::build_system_prompt($version, $templatebody);
            $user   = prompt_builder::build_user_message($sourcetext, $numcards, $numquestions, $version);
            $result = \local_sentientia_ai\client::complete([
                'component'        => 'local_sentientia_authoring',
                'purpose'          => 'course_generation',
                'system'           => $system,
                'usertext'         => $user,
                'model'            => $model,
                'max_tokens'       => self::MAX_OUTPUT_TOKENS,
                'mock'             => $mockfn,
                'legacy_component' => 'local_sentientia_authoring',
            ]);
            // 'denied' (gateway quota) surfaces exactly like a failure so
            // the studio persists a retriable failed draft. 'mock' here
            // means the gateway's live flags are still OFF (Addendum-A
            // gating) — honest mock output, zero spend.
            return [
                'body'       => $result['body'],
                'tokens_in'  => $result['tokens_in'],
                'tokens_out' => $result['tokens_out'],
                'mode'       => $result['mode'] === 'denied' ? 'failed' : $result['mode'],
                'error'      => $result['error'],
            ];
        }
        // ── Standalone fallback (gateway not installed) ─────────────────

        if (!$islive) {
            return self::call_mock($sourcetext, $numcards, $numquestions, $version);
        }
        return self::call_live($sourcetext, $numcards, $numquestions, $model, $version, $templatebody);
    }

    /**
     * Deterministic mock response — full module shaped exactly like the live
     * API body. Produces $numcards cards and $numquestions questions cycling
     * through multichoice → mrq → match so all three types are exercised.
     * Hindi (v2-hindi) yields Devanagari content. The "[MOCK]" marker stays in
     * Latin so reviewers always spot mock content regardless of language.
     *
     * @param string $sourcetext
     * @param int    $numcards
     * @param int    $numquestions
     * @param string $version
     * @return array
     */
    public static function call_mock(string $sourcetext, int $numcards, int $numquestions,
            string $version = prompt_builder::VERSION_V1): array {

        $hindi = ($version === prompt_builder::VERSION_V2_HINDI);
        $numcards     = max(prompt_builder::MIN_CARDS, min(prompt_builder::MAX_CARDS, $numcards));
        $numquestions = max(prompt_builder::MIN_QUESTIONS, min(prompt_builder::MAX_QUESTIONS, $numquestions));

        $clean = trim((string) preg_replace('/\s+/u', ' ', trim($sourcetext)));
        $snippet = mb_substr($clean, 0, 60);
        if ($snippet === '') {
            $snippet = $hindi ? '(रिक्त स्रोत)' : '(empty source)';
        }

        $cards = [];
        for ($i = 1; $i <= $numcards; $i++) {
            if ($hindi) {
                $cards[] = [
                    'cardtype'  => ($i % 4 === 0) ? 'flip' : 'concept',
                    'heading'   => "[MOCK कार्ड {$i}]",
                    'body'      => "स्रोत \"{$snippet}\" पर आधारित नकली कार्ड सामग्री (कार्ड {$i})।",
                    'flip_back' => ($i % 4 === 0) ? "नकली flip उत्तर (कार्ड {$i})।" : null,
                    'narration' => "यह कार्ड {$i} हेतु नकली narration है। कोई live TTS कॉल नहीं की गई।",
                ];
            } else {
                $cards[] = [
                    'cardtype'  => ($i % 4 === 0) ? 'flip' : 'concept',
                    'heading'   => "[MOCK Card {$i}]",
                    'body'      => "Mock card content about \"{$snippet}\" (card {$i}).",
                    'flip_back' => ($i % 4 === 0) ? "Mock flip-back answer for card {$i}." : null,
                    'narration' => "This is mock narration for card {$i}. No live TTS call was made.",
                ];
            }
            // Drop the null flip_back so the payload mirrors the live contract.
            if ($cards[count($cards) - 1]['flip_back'] === null) {
                unset($cards[count($cards) - 1]['flip_back']);
            }
        }

        $questions = [];
        for ($i = 1; $i <= $numquestions; $i++) {
            $type = ['multichoice', 'mrq', 'match'][($i - 1) % 3];
            if ($type === 'multichoice') {
                $questions[] = self::mock_multichoice($i, $hindi);
            } else if ($type === 'mrq') {
                $questions[] = self::mock_mrq($i, $hindi);
            } else {
                $questions[] = self::mock_match($i, $hindi);
            }
        }

        $body = json_encode(['cards' => $cards, 'questions' => $questions], JSON_UNESCAPED_UNICODE);
        return [
            'body'       => $body,
            'tokens_in'  => 0,
            'tokens_out' => 0,
            'mode'       => 'mock',
            'error'      => null,
        ];
    }

    /**
     * @param int  $i
     * @param bool $hindi
     * @return array
     */
    private static function mock_multichoice(int $i, bool $hindi): array {
        if ($hindi) {
            return [
                'qtype' => 'multichoice',
                'qtext' => "[MOCK प्रश्न {$i}] कौन-सा कथन सर्वाधिक उपयुक्त है?",
                'qoptions' => ["नकली विकल्प A", "नकली विकल्प B", "नकली विकल्प C (सही)", "नकली विकल्प D"],
                'qanswer_index' => 2,
                'qfeedback_correct'   => "सही! यह एक नकली प्रतिक्रिया है।",
                'qfeedback_incorrect' => "पुनः प्रयास करें — यह नकली प्रतिक्रिया है।",
                'qexplanation' => "नकली व्याख्या — कोई Anthropic कॉल नहीं की गई।",
            ];
        }
        return [
            'qtype' => 'multichoice',
            'qtext' => "[MOCK Q{$i}] Which statement best reflects the source?",
            'qoptions' => ["Mock option A", "Mock option B", "Mock option C (CORRECT)", "Mock option D"],
            'qanswer_index' => 2,
            'qfeedback_correct'   => "Correct! This is mock feedback.",
            'qfeedback_incorrect' => "Try again — this is mock feedback.",
            'qexplanation' => "Mock explanation — no Anthropic call was made.",
        ];
    }

    /**
     * @param int  $i
     * @param bool $hindi
     * @return array
     */
    private static function mock_mrq(int $i, bool $hindi): array {
        if ($hindi) {
            return [
                'qtype' => 'mrq',
                'qtext' => "[MOCK MRQ {$i}] निम्न में से कौन-से सही हैं? (एक से अधिक)",
                'qoptions' => ["नकली सही 1", "नकली गलत", "नकली सही 2", "नकली गलत 2"],
                'qanswer_indices' => [0, 2],
                'qfeedback_correct'   => "सही! दोनों उपयुक्त विकल्प चुने गए।",
                'qfeedback_incorrect' => "सभी सही विकल्प चुनें — यह नकली प्रतिक्रिया है।",
            ];
        }
        return [
            'qtype' => 'mrq',
            'qtext' => "[MOCK MRQ {$i}] Which of these apply? (select all that apply)",
            'qoptions' => ["Mock correct 1", "Mock wrong", "Mock correct 2", "Mock wrong 2"],
            'qanswer_indices' => [0, 2],
            'qfeedback_correct'   => "Correct! You picked both right options.",
            'qfeedback_incorrect' => "Select every correct option — this is mock feedback.",
        ];
    }

    /**
     * @param int  $i
     * @param bool $hindi
     * @return array
     */
    private static function mock_match(int $i, bool $hindi): array {
        if ($hindi) {
            return [
                'qtype' => 'match',
                'qtext' => "[MOCK MATCH {$i}] बाएँ को दाएँ से मिलाएँ।",
                'qpairs' => [
                    ['left' => "नकली पद A", 'right' => "नकली अर्थ 1"],
                    ['left' => "नकली पद B", 'right' => "नकली अर्थ 2"],
                    ['left' => "नकली पद C", 'right' => "नकली अर्थ 3"],
                ],
                'qfeedback_correct'   => "सही मिलान! नकली प्रतिक्रिया।",
                'qfeedback_incorrect' => "मिलान पुनः जाँचें — नकली प्रतिक्रिया।",
            ];
        }
        return [
            'qtype' => 'match',
            'qtext' => "[MOCK MATCH {$i}] Match each term to its meaning.",
            'qpairs' => [
                ['left' => "Mock term A", 'right' => "Mock meaning 1"],
                ['left' => "Mock term B", 'right' => "Mock meaning 2"],
                ['left' => "Mock term C", 'right' => "Mock meaning 3"],
            ],
            'qfeedback_correct'   => "Correct match! Mock feedback.",
            'qfeedback_incorrect' => "Recheck your pairings — mock feedback.",
        ];
    }

    /**
     * Live API call to api.anthropic.com. Failures are returned as a result
     * array (no thrown exceptions) so the UI can persist the failed draft.
     *
     * @param string      $sourcetext
     * @param int         $numcards
     * @param int         $numquestions
     * @param string      $model
     * @param string      $version
     * @param string|null $templatebody
     * @return array {body, tokens_in, tokens_out, mode, error}
     */
    public static function call_live(string $sourcetext, int $numcards, int $numquestions,
            string $model, string $version = prompt_builder::VERSION_V1,
            ?string $templatebody = null): array {

        $apikey = get_config('local_sentientia_authoring', 'anthropic_api_key');
        if (empty($apikey) || !is_string($apikey)) {
            return ['body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed', 'error' => 'anthropic_api_key_not_set'];
        }

        $system = prompt_builder::build_system_prompt($version, $templatebody);
        $user   = prompt_builder::build_user_message($sourcetext, $numcards, $numquestions, $version);

        $payload = [
            'model'      => $model,
            'max_tokens' => self::MAX_OUTPUT_TOKENS,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
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
        $raw      = curl_exec($ch);
        $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['body' => '', 'tokens_in' => 0, 'tokens_out' => 0,
                'mode' => 'failed', 'error' => 'curl_error: ' . substr($curlerr ?: 'unknown', 0, 200)];
        }
        if ($httpcode !== 200) {
            $msg = "http_{$httpcode}";
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded) && isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                $msg .= ': ' . substr($decoded['error']['message'], 0, 200);
            }
            return ['body' => '', 'tokens_in' => 0, 'tokens_out' => 0, 'mode' => 'failed', 'error' => $msg];
        }

        $decoded = json_decode((string) $raw, true);
        $text = '';
        $tin = 0;
        $tout = 0;
        if (is_array($decoded)) {
            if (isset($decoded['content'][0]['text']) && is_string($decoded['content'][0]['text'])) {
                $text = $decoded['content'][0]['text'];
            }
            if (isset($decoded['usage']['input_tokens']) && is_int($decoded['usage']['input_tokens'])) {
                $tin = $decoded['usage']['input_tokens'];
            }
            if (isset($decoded['usage']['output_tokens']) && is_int($decoded['usage']['output_tokens'])) {
                $tout = $decoded['usage']['output_tokens'];
            }
        }
        if ($text === '') {
            return ['body' => '', 'tokens_in' => $tin, 'tokens_out' => $tout,
                'mode' => 'failed', 'error' => 'empty_response_body'];
        }
        return ['body' => $text, 'tokens_in' => $tin, 'tokens_out' => $tout, 'mode' => 'live', 'error' => null];
    }

    /**
     * Is the studio configured for a live Anthropic call? Used by the UI to
     * choose the mode badge.
     *
     * @return bool
     */
    public static function is_live_ready(): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return false;
        }
        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.enabled')) {
            return false;
        }
        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.live_api')) {
            return false;
        }
        $key = get_config('local_sentientia_authoring', 'anthropic_api_key');
        return !empty($key);
    }
}
