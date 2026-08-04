<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Privacy provider for block_sentientia_cert_health.
// Implements null_provider — this block is a read-only admin widget.
// It aggregates counts from the email delivery log owned by
// local_sentientia_emails and stores nothing of its own.

namespace block_sentientia_cert_health\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
