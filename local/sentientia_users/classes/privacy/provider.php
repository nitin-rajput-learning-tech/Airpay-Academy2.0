<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase Z.1 (2026-05-08) — privacy provider for sentientia_users.
//
// This plugin extends Moodle's core {user} table via open_* fields
// (open_designation, open_employeeid, open_path, etc). Those fields are
// already exported by the core_user privacy provider since they live on
// the {user} row itself. No airpay-owned tables store additional PII,
// so we implement core_privacy\local\metadata\null_provider.

namespace local_sentientia_users\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
