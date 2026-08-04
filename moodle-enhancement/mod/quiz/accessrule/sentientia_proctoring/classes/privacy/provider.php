<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Privacy provider for quizaccess_sentientia_proctoring.
// Implements null_provider — the quizaccess_sentientia_proctor table
// holds per-quiz configuration only (quizid, enabled, thresholds) with
// no user linkage. Proctoring evidence (recordings, identity checks,
// flags) is stored and described by local_sentientia_proctoring's own
// privacy provider.

namespace quizaccess_sentientia_proctoring\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
