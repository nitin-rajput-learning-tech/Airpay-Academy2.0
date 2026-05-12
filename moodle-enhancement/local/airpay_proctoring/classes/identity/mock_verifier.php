<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_proctoring\identity;

defined('MOODLE_INTERNAL') || die();

/**
 * Mock identity verifier — for dev + integration testing only.
 *
 * Always returns passed=true with a high score, UNLESS the selfie bytes
 * exactly match the string "FAIL" (testing failure paths).
 */
class mock_verifier implements identity_verifier_interface {
    public function get_name(): string { return 'mock'; }

    public function verify(string $id_photo_bytes, string $selfie_bytes): array {
        if ($selfie_bytes === 'FAIL') {
            return [
                'passed' => false, 'score' => 42.0,
                'error_code' => 'mock_fail',
                'error_msg'  => 'Mock-induced failure for testing',
            ];
        }
        // Deterministic score for assertion-friendly tests.
        $score = 92.0;
        return [
            'passed'     => true, 'score' => $score,
            'error_code' => null, 'error_msg' => null,
        ];
    }
}
