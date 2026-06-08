<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\identity;

defined('MOODLE_INTERNAL') || die();

/**
 * Identity verifier contract.
 *
 * Implementations: aws_verifier (AWS Rekognition), azure_verifier
 * (Azure Face API), mock_verifier (dev/testing).
 *
 * The verifier receives an ID photo + selfie (both as raw bytes),
 * returns a match score 0..100 plus a pass/fail decision.
 *
 * IMPORTANT: implementations MUST NOT persist the photo bytes. Only
 * the score is retained. The privacy guarantee depends on this.
 */
interface identity_verifier_interface {

    /**
     * Verify identity by comparing two face images.
     *
     * @param string $id_photo_bytes  Bytes of the ID photo
     * @param string $selfie_bytes    Bytes of the selfie
     * @return array {
     *   passed: bool,
     *   score: float (0..100),
     *   error_code: string|null,
     *   error_msg: string|null,
     * }
     */
    public function verify(string $id_photo_bytes, string $selfie_bytes): array;

    public function get_name(): string;
}
