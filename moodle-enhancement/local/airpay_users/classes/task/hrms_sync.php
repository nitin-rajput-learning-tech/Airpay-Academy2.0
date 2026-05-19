<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_users\task;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #16 (2026-05-16) — scheduled task that pulls a HRMS CSV from a
 * configured source and runs it through `hrms_importer::import_csv()`.
 *
 * Closes audit item #4 from parity-audit-2026-05-15/airpay_users.md
 * (cron-driven HRMS sync — BizLMS had `classes/task/servicesync.php`
 * running hourly via `db/tasks.php`).
 *
 * Source modes:
 *   `disabled` — task is a no-op (default; admin must opt in)
 *   `url`      — fetch via HTTP GET (optional Authorization header)
 *   `filesystem` — read from a configured absolute path
 *
 * Idempotency: `hrms_importer` matches existing users by email OR username
 * OR employee_code and UPDATES them rather than inserting duplicates. So a
 * daily cron that pulls the same export is safe to re-run.
 *
 * Schedule: registered in `db/tasks.php` to run at 02:30 every day.
 * Admin can change via Site Administration → Server → Scheduled tasks.
 *
 * @package local_airpay_users
 */
class hrms_sync extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_hrms_sync', 'local_airpay_users');
    }

    public function execute(): void {
        $mode = (string) get_config('local_airpay_users', 'hrms_sync_mode');
        if ($mode === '' || $mode === 'disabled') {
            mtrace('local_airpay_users hrms_sync: disabled (no source configured)');
            return;
        }

        mtrace('local_airpay_users hrms_sync: starting (mode=' . $mode . ')');

        try {
            $csv = $this->fetch_csv($mode);
        } catch (\Throwable $e) {
            mtrace('local_airpay_users hrms_sync: fetch failed: ' . $e->getMessage());
            return;
        }

        if ($csv === '' || $csv === false) {
            mtrace('local_airpay_users hrms_sync: empty CSV — nothing to do');
            return;
        }

        // Run the importer as the configured cron runner user. Default to
        // siteadmin (user.id=2 on a stock Moodle) so the tenant guard
        // doesn't constrain the cron's reach across tenants.
        $runner_userid = (int) (get_config('local_airpay_users', 'hrms_sync_user_id')
            ?: 2);
        $filename = 'hrms_' . date('Ymd_His') . '.csv';

        $run_id = \local_airpay_users\hrms_importer::import_csv(
            $csv, $runner_userid, $filename, 'cron');

        // Record last-success timestamp for the admin UI.
        set_config('hrms_sync_last_run',    time(),   'local_airpay_users');
        set_config('hrms_sync_last_run_id', $run_id,  'local_airpay_users');

        global $DB;
        $run = $DB->get_record('local_airpay_users_sync_runs', ['id' => $run_id]);
        if ($run) {
            mtrace(sprintf(
                'local_airpay_users hrms_sync: run #%d finished. '
                . 'rows=%d inserted=%d updated=%d errors=%d warnings=%d',
                $run_id,
                (int) $run->totalrows,
                (int) $run->insertedcount,
                (int) $run->updatedcount,
                (int) $run->errorcount,
                (int) $run->warningcount
            ));
        }
    }

    /**
     * Fetch CSV content from the configured source.
     *
     * @throws \moodle_exception on misconfiguration or fetch failure
     */
    private function fetch_csv(string $mode): string {
        switch ($mode) {
            case 'url':
                return $this->fetch_url();
            case 'filesystem':
                return $this->fetch_filesystem();
            default:
                throw new \moodle_exception('hrms_sync_invalid_mode',
                    'local_airpay_users', '', $mode);
        }
    }

    /**
     * HTTP GET fetch via Moodle's curl wrapper (handles proxy + cert config).
     */
    private function fetch_url(): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $url = (string) get_config('local_airpay_users', 'hrms_sync_url');
        if ($url === '') {
            throw new \moodle_exception('hrms_sync_url_empty', 'local_airpay_users');
        }

        $auth_header = (string) get_config('local_airpay_users',
            'hrms_sync_auth_header');

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_TIMEOUT'        => 300,  // 5min hard cap
            'CURLOPT_FOLLOWLOCATION' => true,
        ]);
        $headers = [];
        if ($auth_header !== '') {
            $headers[] = $auth_header;
        }
        if (!empty($headers)) {
            $curl->setHeader($headers);
        }

        $content = $curl->get($url);
        $info = $curl->get_info();
        $http_code = (int) ($info['http_code'] ?? 0);
        if ($http_code < 200 || $http_code >= 300) {
            throw new \moodle_exception('hrms_sync_url_http_error',
                'local_airpay_users', '', "HTTP $http_code from $url");
        }
        return (string) $content;
    }

    /**
     * Read CSV from an absolute filesystem path.
     */
    private function fetch_filesystem(): string {
        $path = (string) get_config('local_airpay_users', 'hrms_sync_path');
        if ($path === '') {
            throw new \moodle_exception('hrms_sync_path_empty', 'local_airpay_users');
        }
        // Constrain to absolute paths to avoid CWD-relative surprises.
        if ($path[0] !== '/' && !preg_match('/^[A-Z]:\\\\/', $path)) {
            throw new \moodle_exception('hrms_sync_path_not_absolute',
                'local_airpay_users', '', $path);
        }
        if (!is_readable($path)) {
            throw new \moodle_exception('hrms_sync_path_not_readable',
                'local_airpay_users', '', $path);
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new \moodle_exception('hrms_sync_path_read_failed',
                'local_airpay_users', '', $path);
        }
        return $content;
    }
}
