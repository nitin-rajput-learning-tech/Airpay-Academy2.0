<?php
/**
 * Phase E.3 (2026-05-08) — bulk-CSV status change.
 *
 * Parse a CSV with header `email,action` and apply the action
 * (suspend|activate) to each matching user, honouring the same
 * tenant-scope and self/guest/admin guards as bulk_action external WS.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

class bulk_csv_processor {

    public const SUPPORTED_ACTIONS = ['suspend', 'activate'];

    /**
     * Parse CSV content and execute actions row-by-row.
     *
     * Expected CSV format (header line required):
     *   email,action
     *   alice@airpay.in,suspend
     *   bob@airpay.in,activate
     *
     * Returns a structured summary so the admin sees what landed.
     *
     * @param string $csv_content    Raw CSV text
     * @param int    $caller_userid  ID of the running admin
     * @return array{
     *   total: int,
     *   succeeded: list<array{email:string, action:string, userid:int}>,
     *   skipped: list<array{email:string, action:string, reason:string}>,
     *   failed: list<array{email:string, action:string, error:string}>,
     * }
     */
    public static function process(string $csv_content,
                                    int $caller_userid): array {
        global $DB, $USER;

        $rows = self::parse_csv($csv_content);
        $summary = [
            'total'     => count($rows),
            'succeeded' => [],
            'skipped'   => [],
            'failed'    => [],
        ];

        // Determine caller's tenant scope (mirrors bulk_action.php logic).
        $caller_tenant_top = 0;
        if (!is_siteadmin($caller_userid)) {
            $caller = $DB->get_record('user', ['id' => $caller_userid],
                'id, open_path');
            if ($caller) {
                $parts = explode('/', trim($caller->open_path ?? '', '/'));
                $caller_tenant_top = isset($parts[0])
                    && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
            }
            if ($caller_tenant_top === 0) {
                throw new \moodle_exception('invalidtenant',
                    'local_sentientia_users');
            }
        }

        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            $action = strtolower(trim((string) ($row['action'] ?? '')));

            if ($email === '' || $action === '') {
                $summary['failed'][] = [
                    'email' => $email, 'action' => $action,
                    'error' => 'Missing email or action.',
                ];
                continue;
            }
            if (!in_array($action, self::SUPPORTED_ACTIONS, true)) {
                $summary['failed'][] = [
                    'email' => $email, 'action' => $action,
                    'error' => 'Unsupported action (must be suspend|activate).',
                ];
                continue;
            }
            if (!validate_email($email)) {
                $summary['failed'][] = [
                    'email' => $email, 'action' => $action,
                    'error' => 'Invalid email format.',
                ];
                continue;
            }

            // Lookup user.
            $user = $DB->get_record('user',
                ['email' => $email, 'deleted' => 0],
                'id, suspended, open_path');
            if (!$user) {
                $summary['skipped'][] = [
                    'email' => $email, 'action' => $action,
                    'reason' => 'User not found.',
                ];
                continue;
            }

            // Self/guest/admin guard.
            if (in_array((int) $user->id, [(int) $caller_userid, 1, 2], true)) {
                $summary['skipped'][] = [
                    'email' => $email, 'action' => $action,
                    'reason' => 'Cannot act on self/guest/site admin.',
                ];
                continue;
            }

            // Tenant scope guard for non-siteadmins.
            if ($caller_tenant_top > 0) {
                $u_parts = explode('/', trim((string) $user->open_path, '/'));
                $u_top = isset($u_parts[0]) && ctype_digit($u_parts[0])
                    ? (int) $u_parts[0] : 0;
                if ($u_top !== $caller_tenant_top) {
                    $summary['skipped'][] = [
                        'email' => $email, 'action' => $action,
                        'reason' => 'User in another tenant.',
                    ];
                    continue;
                }
            }

            // Already in target state? — Skip without DB write.
            $target = ($action === 'suspend') ? 1 : 0;
            if ((int) $user->suspended === $target) {
                $summary['skipped'][] = [
                    'email' => $email, 'action' => $action,
                    'reason' => 'Already in target state.',
                ];
                continue;
            }

            try {
                $DB->set_field('user', 'suspended', $target, ['id' => $user->id]);
                $DB->set_field('user', 'timemodified', time(), ['id' => $user->id]);
                $summary['succeeded'][] = [
                    'email'   => $email,
                    'action'  => $action,
                    'userid'  => (int) $user->id,
                ];
            } catch (\Throwable $e) {
                $summary['failed'][] = [
                    'email' => $email, 'action' => $action,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * Lightweight CSV parser — header row maps each column to keys, then
     * yields associative rows. Handles quoted fields with embedded commas
     * but not escaped quotes (good enough for HR-exported sheets).
     */
    private static function parse_csv(string $content): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) return [];
        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);

        $rows = [];
        foreach ($lines as $i => $line) {
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
