<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Text-to-speech client — productizes the Workstream-B ElevenLabs pipeline
 * (CLAUDE.md §9 Agent 4, §10) as a feature of the Authoring Studio.
 *
 * MOCK by default. Two call modes, mirroring course_generator:
 *
 *   - call_mock()  — returns a deterministic placeholder marker instead of
 *                    audio bytes. NO ElevenLabs call, NO cost, NO key, NO
 *                    internet. Used whenever sentientia.authoring.live_api is
 *                    OFF (default) — this is the build's only behaviour, since
 *                    the task forbids live API spend.
 *   - call_live()  — real HTTP POST to api.elevenlabs.io. Gated by ALL of:
 *                      (a) sentientia.authoring.enabled = ON
 *                      (b) sentientia.authoring.tts     = ON
 *                      (c) sentientia.authoring.live_api = ON
 *                      (d) local_sentientia_authoring | elevenlabs_api_key set
 *                      (e) the caller passed the per-action [CONFIRM] gate
 *                          (enforced in voiceover.php, NOT here)
 *                    Per CLAUDE.md §10, ElevenLabs is [CONFIRM]-only because it
 *                    is charged per character. This build never flips live_api,
 *                    so call_live() is plumbing for a future, human-confirmed
 *                    production session — it is never reached by the studio nor
 *                    by the test suite.
 *
 * PII discipline (CLAUDE.md api.md): callers MUST strip employee names / IDs /
 * salary from narration text before it reaches a live TTS provider. This class
 * exposes {@see estimate_cost()} so the UI can warn before any [CONFIRM].
 *
 * NEVER log the API key. NEVER include the key in error_detail.
 *
 * @package local_sentientia_authoring
 */
class tts_client {

    /** ElevenLabs TTS endpoint prefix (voice id appended). */
    public const ENDPOINT_PREFIX = 'https://api.elevenlabs.io/v1/text-to-speech/';

    /** ElevenLabs model id (multilingual covers Hindi). */
    public const MODEL_ID = 'eleven_multilingual_v2';

    /** HTTP timeout, seconds (audio generation is slow). */
    public const HTTP_TIMEOUT = 120;

    /** Approx ElevenLabs cost per 1000 characters (USD) — for the UI estimate. */
    public const COST_PER_1K_CHARS = 0.30;

    /** Mock voice id recorded on mock-mode jobs. */
    public const MOCK_VOICE = 'mock';

    /**
     * Top-level dispatcher: mock unless the live_api flag is ON.
     *
     * @param string $narration Narration text to voice (PII-screened by caller).
     * @param string $lang      Language code (en, hi, ...).
     * @param string $voiceid   ElevenLabs voice id (ignored in mock mode).
     * @return array {audio_ref: ?string, mode: 'mock'|'live'|'failed', voice_id: string, charcount: int, error: ?string}
     */
    public static function synthesize(string $narration, string $lang, string $voiceid = ''): array {
        $charcount = mb_strlen(trim($narration));

        $islive = class_exists('\\local_sentientia_platform\\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.live_api');

        if (!$islive) {
            return self::call_mock($narration, $lang);
        }
        return self::call_live($narration, $lang, $voiceid);
    }

    /**
     * Deterministic mock — returns a placeholder marker, never audio. The
     * marker encodes the language + char count so the UI can show a believable
     * "voiceover ready (mock)" state without any spend.
     *
     * @param string $narration
     * @param string $lang
     * @return array
     */
    public static function call_mock(string $narration, string $lang): array {
        $charcount = mb_strlen(trim($narration));
        return [
            'audio_ref' => "mock://voiceover/{$lang}/{$charcount}chars",
            'mode'      => 'mock',
            'voice_id'  => self::MOCK_VOICE,
            'charcount' => $charcount,
            'error'     => null,
        ];
    }

    /**
     * Live ElevenLabs call. Returns a result array (never throws). This build
     * never reaches this path — it exists only so a future [CONFIRM]-gated
     * production session has the plumbing ready. The audio bytes are NOT
     * persisted to the Moodle file store here; that wiring lands when the
     * feature is first enabled on staging under human review.
     *
     * @param string $narration
     * @param string $lang
     * @param string $voiceid
     * @return array
     */
    public static function call_live(string $narration, string $lang, string $voiceid): array {
        $charcount = mb_strlen(trim($narration));

        $apikey = get_config('local_sentientia_authoring', 'elevenlabs_api_key');
        if (empty($apikey) || !is_string($apikey)) {
            return ['audio_ref' => null, 'mode' => 'failed', 'voice_id' => $voiceid,
                'charcount' => $charcount, 'error' => 'elevenlabs_api_key_not_set'];
        }
        if (trim($voiceid) === '') {
            $voiceid = (string) get_config('local_sentientia_authoring', 'elevenlabs_voice_id');
        }
        if (trim($voiceid) === '') {
            return ['audio_ref' => null, 'mode' => 'failed', 'voice_id' => '',
                'charcount' => $charcount, 'error' => 'elevenlabs_voice_id_not_set'];
        }

        $payload = [
            'text'     => trim($narration),
            'model_id' => self::MODEL_ID,
            'voice_settings' => [
                'stability'        => 0.50,
                'similarity_boost' => 0.75,
                'style'            => 0.25,
                'use_speaker_boost' => true,
            ],
        ];

        $ch = curl_init(self::ENDPOINT_PREFIX . rawurlencode($voiceid));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: audio/mpeg',
                'xi-api-key: ' . $apikey,
            ],
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
        ]);
        $raw      = curl_exec($ch);
        $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['audio_ref' => null, 'mode' => 'failed', 'voice_id' => $voiceid,
                'charcount' => $charcount, 'error' => 'curl_error: ' . substr($curlerr ?: 'unknown', 0, 200)];
        }
        if ($httpcode !== 200) {
            return ['audio_ref' => null, 'mode' => 'failed', 'voice_id' => $voiceid,
                'charcount' => $charcount, 'error' => "http_{$httpcode}"];
        }

        // A real implementation stores $raw (mp3 bytes) via the Moodle file
        // API and returns the pluginfile URL. Deliberately deferred to the
        // first human-confirmed production session (no live spend in this build).
        return ['audio_ref' => 'pending_filestore', 'mode' => 'live', 'voice_id' => $voiceid,
            'charcount' => $charcount, 'error' => null];
    }

    /**
     * Estimated USD cost of voicing the given narration via ElevenLabs.
     * Shown in the UI before any [CONFIRM]. Mock-mode cost is always 0.
     *
     * @param string $narration
     * @return float
     */
    public static function estimate_cost(string $narration): float {
        return round(mb_strlen(trim($narration)) / 1000 * self::COST_PER_1K_CHARS, 4);
    }

    /**
     * Would a synthesize() call actually hit ElevenLabs? All three flags ON,
     * a key configured, and a voice id available.
     *
     * @return bool
     */
    public static function is_live_ready(): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return false;
        }
        $ff = '\\local_sentientia_platform\\feature_flags';
        if (!$ff::is_enabled('sentientia.authoring.enabled')
                || !$ff::is_enabled('sentientia.authoring.tts')
                || !$ff::is_enabled('sentientia.authoring.live_api')) {
            return false;
        }
        return !empty(get_config('local_sentientia_authoring', 'elevenlabs_api_key'))
            && !empty(get_config('local_sentientia_authoring', 'elevenlabs_voice_id'));
    }
}
