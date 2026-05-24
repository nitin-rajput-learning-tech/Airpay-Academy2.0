<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Daily cleanup task — purges revoked tokens older than the retention
 * window (default 90 days, see {@see token_manager::RETENTION_DAYS}).
 *
 * Why: revoked tokens are kept around briefly so we can answer "who
 * used a leaked token, when?" if an audit demands it. After 90 days
 * the diagnostic value is gone and the rows just bloat the table.
 * Active tokens are never purged here; users who never re-fetch their
 * feed simply have a long-lived (but unused) token.
 *
 * @package local_sentientia_calendar
 */

namespace local_sentientia_calendar\task;

defined('MOODLE_INTERNAL') || die();

class purge_old_tokens extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_purge_old_tokens', 'local_sentientia_calendar');
    }

    public function execute(): void {
        global $DB;

        $retention_days = (int) (\local_sentientia_calendar\token_manager::RETENTION_DAYS);
        $cutoff = time() - ($retention_days * 86400);

        $count = $DB->count_records_select(
            \local_sentientia_calendar\token_manager::TABLE,
            'revoked = 1 AND timemodified < :cutoff',
            ['cutoff' => $cutoff]
        );

        if ($count === 0) {
            mtrace('local_sentientia_calendar purge_old_tokens: nothing to purge');
            return;
        }

        $DB->delete_records_select(
            \local_sentientia_calendar\token_manager::TABLE,
            'revoked = 1 AND timemodified < :cutoff',
            ['cutoff' => $cutoff]
        );
        mtrace("local_sentientia_calendar purge_old_tokens: deleted $count "
            . "revoked tokens older than $retention_days days");
    }
}
