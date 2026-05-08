<?php
/**
 * Phase E.4 (2026-05-08) — bulk-CSV new user import.
 *
 * Parse a CSV with rows of new users → create them via user_manager::create.
 * Mirrors bulk_csv_processor's structured-summary pattern, but for create
 * instead of suspend/activate.
 *
 * @package    local_airpay_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_users;

defined('MOODLE_INTERNAL') || die();

class bulk_import_processor {

    /**
     * Required CSV header columns (case-insensitive). Any extra columns
     * matching the open_* allow-list are mapped onto the new user.
     */
    public const REQUIRED_COLS = ['email', 'firstname', 'lastname', 'username'];

    /** open_* columns the import is allowed to set. */
    public const OPTIONAL_COLS = [
        'employeeid', 'designation', 'department', 'team',
        'grade', 'zone', 'region', 'location', 'employmenttype', 'client',
    ];

    /**
     * Process the uploaded CSV.
     *
     * @return array{
     *   total: int,
     *   succeeded: list<array{email:string, username:string, userid:int}>,
     *   skipped: list<array{email:string, reason:string}>,
     *   failed: list<array{email:string, error:string}>,
     * }
     */
    public static function process(string $csv_content,
                                    int $caller_userid): array {
        global $DB, $CFG;

        $rows = self::parse_csv($csv_content);
        $summary = [
            'total'     => count($rows),
            'succeeded' => [],
            'skipped'   => [],
            'failed'    => [],
        ];

        // Caller's tenant scope: non-siteadmin can only create users in
        // their own tenant root.
        $caller_tenant_top = 0;
        $caller_path = '';
        if (!is_siteadmin($caller_userid)) {
            $caller = $DB->get_record('user', ['id' => $caller_userid],
                'id, open_path');
            $caller_path = (string) ($caller->open_path ?? '');
            $parts = explode('/', trim($caller_path, '/'));
            $caller_tenant_top = isset($parts[0]) && ctype_digit($parts[0])
                ? (int) $parts[0] : 0;
            if ($caller_tenant_top === 0) {
                throw new \moodle_exception('invalidtenant',
                    'local_airpay_users');
            }
        }

        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            $username = strtolower(trim((string) ($row['username'] ?? '')));
            $firstname = trim((string) ($row['firstname'] ?? ''));
            $lastname = trim((string) ($row['lastname'] ?? ''));

            // Required-field check.
            if ($email === '' || $username === ''
                || $firstname === '' || $lastname === '') {
                $summary['failed'][] = [
                    'email' => $email,
                    'error' => 'Missing required field (email/username/firstname/lastname).',
                ];
                continue;
            }
            if (!validate_email($email)) {
                $summary['failed'][] = [
                    'email' => $email,
                    'error' => 'Invalid email format.',
                ];
                continue;
            }

            // Skip if the user already exists (don't overwrite).
            if ($DB->record_exists('user',
                ['email' => $email, 'deleted' => 0])) {
                $summary['skipped'][] = [
                    'email' => $email,
                    'reason' => 'User with that email already exists.',
                ];
                continue;
            }
            if ($DB->record_exists('user',
                ['username' => $username,
                 'mnethostid' => $CFG->mnet_localhost_id])) {
                $summary['skipped'][] = [
                    'email' => $email,
                    'reason' => 'Username already taken.',
                ];
                continue;
            }

            // Build the create() payload.
            $data = (object) [
                'username'  => $username,
                'email'     => $email,
                'firstname' => $firstname,
                'lastname'  => $lastname,
                'auth'      => 'manual',
                // Generate a secure random password — admin should reset
                // via the standard "forgot password" flow, OR send a
                // welcome email separately.
                'password'  => generate_password(20),
            ];

            // Map optional open_* columns from the CSV.
            foreach (self::OPTIONAL_COLS as $col) {
                if (!empty($row[$col])) {
                    $key = 'open_' . $col;
                    $data->$key = trim((string) $row[$col]);
                }
            }

            // Tenant scope: assign open_path from caller for non-siteadmin.
            if ($caller_tenant_top > 0) {
                $data->open_path = '/' . $caller_tenant_top;
            } elseif (!empty($row['open_path'])) {
                $data->open_path = '/' . trim((string) $row['open_path'], '/');
            }

            try {
                $newid = user_manager::create($data);
                $summary['succeeded'][] = [
                    'email'    => $email,
                    'username' => $username,
                    'userid'   => (int) $newid,
                ];
            } catch (\Throwable $e) {
                $summary['failed'][] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    private static function parse_csv(string $content): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) return [];
        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);

        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $fields = str_getcsv($line);
            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = $fields[$idx] ?? '';
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
