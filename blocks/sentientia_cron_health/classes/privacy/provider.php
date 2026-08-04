<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Privacy provider for block_sentientia_cron_health.
// Implements null_provider — this block is a read-only admin widget
// over the core task_scheduled table. It owns no database tables and
// sets no user preferences.

namespace block_sentientia_cron_health\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
