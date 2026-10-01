<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Synthetic runs for the sync errors that match no BizLMS run (mapping doc section 10, "Run matching" rule 4):
 * one per uploader and server-timezone day. See synthetic_run_step.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class orphan_day_step extends synthetic_run_step {

    public function key(): string {
        return 'users.orphan_runs';
    }

    protected function kind(): string {
        return sync_index::ORPHAN;
    }

    protected function empty_reason(): string {
        return users_importer::REASON_NO_UNATTACHED;
    }
}
