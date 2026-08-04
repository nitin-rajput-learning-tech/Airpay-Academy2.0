<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Privacy provider for block_sentientia_recommendations.
// Implements null_provider — this block is a thin, read-only
// presentation layer. Recommendation data is stored and described by
// the local_sentientia_recommendations plugin's own privacy provider.

namespace block_sentientia_recommendations\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
