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
 * Register a recording chunk that was uploaded directly to S3 by the
 * browser (using a pre-signed URL). We never see the bytes server-side.
 *
 * B1 review (2026-11-20): refused (error_recording_disabled) while
 * sentientia.proctoring.recording.enabled is OFF for the caller's organisation.
 * Recording arrives with B4 (ADR-038 D5); until then nothing records, and the
 * call let any learner attach an object key of their choice to their own active
 * session, which the retention purge and an erasure request then delete.
 */
class upload_chunk extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid'   => new external_value(PARAM_INT, ''),
            'kind'        => new external_value(PARAM_ALPHA, 'webcam|screen|audio'),
            'chunk_idx'   => new external_value(PARAM_INT, ''),
            // B3 fix: was PARAM_TEXT — too loose. session_manager
            // validates the format against a regex, but document the
            // intent here too.
            's3_key'      => new external_value(PARAM_RAW_TRIMMED,
                'opaque S3 object key — alphanumeric, _, /, ., -, max 512 chars'),
            'size_bytes'  => new external_value(PARAM_INT, ''),
            'duration_ms' => new external_value(PARAM_INT, ''),
        ]);
    }
    public static function execute(int $sessionid, string $kind, int $chunk_idx,
                                    string $s3_key, int $size_bytes,
                                    int $duration_ms): array {
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'sessionid', 'kind', 'chunk_idx', 's3_key', 'size_bytes', 'duration_ms'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_proctoring:attempt', $ctx);
        if (!\local_sentientia_platform\feature_flags::is_enabled(
                \local_sentientia_proctoring\quiz_integration::RECORDING_FLAG)) {
            throw new \moodle_exception('error_recording_disabled', 'local_sentientia_proctoring');
        }

        $id =\local_sentientia_proctoring\session_manager::register_chunk(
            (int) $params['sessionid'], (string) $params['kind'],
            (int) $params['chunk_idx'], (string) $params['s3_key'],
            (int) $params['size_bytes'], (int) $params['duration_ms']);

        return ['chunkid' => (int) $id];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'chunkid' => new external_value(PARAM_INT, ''),
        ]);
    }
}
