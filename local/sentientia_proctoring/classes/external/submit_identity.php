<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Submit identity verification. Client sends ID + selfie as base64.
 *
 * We decode, pass to the verifier, persist score only, free memory.
 */
class submit_identity extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, ''),
            'id_b64'    => new external_value(PARAM_RAW, 'Base64 ID photo'),
            'selfie_b64' => new external_value(PARAM_RAW, 'Base64 selfie'),
        ]);
    }
    public static function execute(int $sessionid, string $id_b64, string $selfie_b64): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('sessionid', 'id_b64', 'selfie_b64'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_proctoring:attempt', $ctx);

        // ── B7 fix: per-user rate limit ─────────────────────────────────
        // Without this an attacker can submit 28 MB (2 × 14 MB raw) per
        // request unlimited times, exhausting PHP memory + AWS quota.
        // 5 submits/hour is generous (legitimate UX: 1 submit per session).
        //
        // N1 fix (Phase 8.2 re-audit): replaced fixed-hour bucket with
        // sliding-window. The old `floor(time() / 3600)` key meant that
        // at the hour boundary an attacker could submit 5+5=10 in 2
        // seconds because the bucket key flipped. The sliding window
        // stores recent submission timestamps and rejects when more
        // than 5 fall within the trailing 3600 seconds, no matter
        // when the boundary lands.
        $cache = \cache::make('local_sentientia_proctoring', 'identity_rate');
        $key   = 'u:' . (int) $USER->id;
        $now   = time();
        $window = 3600;          // trailing one hour
        $limit  = 5;             // 5 submissions per trailing hour
        $stamps = $cache->get($key);
        $stamps = is_array($stamps) ? $stamps : [];
        // Prune entries older than the window.
        $stamps = array_values(array_filter($stamps,
            static fn($t) => is_int($t) && ($now - $t) < $window));
        if (count($stamps) >= $limit) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring',
                '', 'Rate limit: too many identity submissions in the last hour');
        }
        $stamps[] = $now;
        $cache->set($key, $stamps);

        // ── B7 fix: tighten size cap from 14M to 5.5M (raw ≈ 4MB) ──────
        // An ID + selfie at full HD is well under 2 MB each. The old
        // 14M cap was lazy and let attackers ship oversize blobs.
        if (strlen($params['id_b64']) > 5_500_000
                || strlen($params['selfie_b64']) > 5_500_000) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring',
                '', 'Photo too large');
        }

        // ── B7 fix: base64_decode strict-mode error handling ───────────
        // The original `?: ''` swallowed false-on-error and let an
        // empty string through. We want an explicit reject.
        $id_bytes     = base64_decode($params['id_b64'], true);
        $selfie_bytes = base64_decode($params['selfie_b64'], true);
        if ($id_bytes === false || $selfie_bytes === false
                || $id_bytes === '' || $selfie_bytes === '') {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring',
                '', 'Invalid photo encoding');
        }

        // ── B7 fix: MIME sniff — must be JPEG or PNG ───────────────────
        // Don't trust client-supplied content-type. Sniff magic bytes.
        // Without this, attacker can ship arbitrary bytes (zip bomb,
        // polyglot) at AWS Rekognition and burn the quota.
        $is_jpeg = static fn(string $b): bool =>
            strlen($b) >= 3 && substr($b, 0, 3) === "\xFF\xD8\xFF";
        $is_png = static fn(string $b): bool =>
            strlen($b) >= 8 && substr($b, 0, 8) === "\x89PNG\r\n\x1A\n";
        if (!($is_jpeg($id_bytes)     || $is_png($id_bytes))
                || !($is_jpeg($selfie_bytes) || $is_png($selfie_bytes))) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring',
                '', 'Unsupported image format (JPEG or PNG only)');
        }

        $id_row = \local_sentientia_proctoring\session_manager::submit_identity(
            (int) $params['sessionid'], (int) $USER->id, $id_bytes, $selfie_bytes);

        return [
            'passed'      => (bool) $id_row->passed,
            'match_score' => (float) $id_row->match_score,
            'error_code'  => (string) ($id_row->error_code ?? ''),
            'error_msg'   => (string) ($id_row->error_msg ?? ''),
        ];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'passed'      => new external_value(PARAM_BOOL, ''),
            'match_score' => new external_value(PARAM_FLOAT, ''),
            'error_code'  => new external_value(PARAM_TEXT, ''),
            'error_msg'   => new external_value(PARAM_TEXT, ''),
        ]);
    }
}
