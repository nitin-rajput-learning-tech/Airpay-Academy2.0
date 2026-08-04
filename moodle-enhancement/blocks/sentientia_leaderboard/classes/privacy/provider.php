<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Privacy provider for block_sentientia_leaderboard.
// Implements null_provider — this block is a read-only presentation
// layer. Leaderboard standings and points are stored and described by
// the local_sentientia_leaderboard plugin's own privacy provider.

namespace block_sentientia_leaderboard\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
