<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_proctoring\identity;

defined('MOODLE_INTERNAL') || die();

class verifier_factory {
    public static function get_current(): identity_verifier_interface {
        $name = (string) (get_config('local_airpay_proctoring', 'provider') ?: 'mock');
        return self::get($name);
    }
    public static function get(string $name): identity_verifier_interface {
        return match ($name) {
            'aws'  => new aws_verifier(),
            'mock' => new mock_verifier(),
            default => throw new \moodle_exception('error_no_provider', 'local_airpay_proctoring'),
        };
    }
}
