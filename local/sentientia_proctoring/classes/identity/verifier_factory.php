<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\identity;

defined('MOODLE_INTERNAL') || die();

/**
 * Identity verifier for the pre-B1 photo flow (submit_identity web service).
 *
 * B1 (ADR-038 D2, 2026-11-20): Nitin's decision D10 (post-demo plan section 6a) is "no automated ID-to-face matching
 * in v1". The `aws` provider sent a government-ID photo and a selfie to AWS Rekognition for exactly that, so it is
 * no longer returned: a site still set to `aws` gets error_no_provider and no photo leaves the server. The settings
 * page no longer offers the choice. The learner flow no longer uses this factory at all (identity is now a
 * confirmation in the quiz's pre-start check); the interface and aws_verifier stay in the code base for a possible
 * v2 under its own ADR.
 *
 * @package local_sentientia_proctoring
 */
class verifier_factory {
    public static function get_current(): identity_verifier_interface {
        $name = (string) (get_config('local_sentientia_proctoring', 'provider') ?: 'mock');
        return self::get($name);
    }
    public static function get(string $name): identity_verifier_interface {
        return match ($name) {
            'mock' => new mock_verifier(),
            default => throw new \moodle_exception('error_no_provider', 'local_sentientia_proctoring'),
        };
    }
}
