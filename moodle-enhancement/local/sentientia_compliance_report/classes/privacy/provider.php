<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase Z.1 (2026-05-08) — privacy provider for local_sentientia_compliance_report.
// Implements null_provider — this plugin does not store personal data
// in airpay-owned tables. (User-impacting state lives on core Moodle
// tables exported by their respective core providers.)

namespace local_sentientia_compliance_report\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
