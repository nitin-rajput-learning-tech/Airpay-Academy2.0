<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Synthetic runs for the sync errors the HR web service wrote (mapping doc section 10, "Run matching" rule 1):
 * one per uploader and server-timezone day, never matched to a BizLMS run, cost centre 0. See synthetic_run_step.
 *
 * Only a database whose local_syncerrors has the production-only sync_file_name column can tell a web service
 * error from an upload error; without it every group of this step is empty and its errors are matched like any
 * other (preflight says so: no_sync_file_name_column).
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class service_day_step extends synthetic_run_step {

    public function key(): string {
        return 'users.service_runs';
    }

    protected function kind(): string {
        return sync_index::SERVICE;
    }

    protected function empty_reason(): string {
        return users_importer::REASON_NO_SERVICE;
    }
}
