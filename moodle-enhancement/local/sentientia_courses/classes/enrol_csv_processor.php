<?php
/**
 * Phase F.4 (2026-05-08) — mass-enrol CSV: bulk-enrol users into courses.
 *
 * CSV format:
 *   email,courseshortname[,role]
 *   alice@airpay.in,COMPLIANCE-01,student
 *   bob@airpay.in,COMPLIANCE-01
 *
 * Default role = 'student' if not specified.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

class enrol_csv_processor {

    /** Default role if not specified in the CSV.
     *  Falls back to 'employee' (BizLMS) if 'student' isn't a defined role. */
    public const DEFAULT_ROLE = 'student';

    /** Common role aliases — translated to the local role shortname.
     *  Lets cross-Moodle CSVs work without renaming. */
    public const ROLE_ALIASES = [
        'student' => 'employee',
        'learner' => 'employee',
    ];

    /**
     * Process the uploaded CSV.
     *
     * @return array{
     *   total: int,
     *   succeeded: list<array{email:string, course:string, role:string}>,
     *   skipped: list<array{email:string, course:string, reason:string}>,
     *   failed: list<array{email:string, course:string, error:string}>,
     * }
     */
    public static function process(string $csv_content,
                                    int $caller_userid): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/enrol/manual/locallib.php');

        $rows = self::parse_csv($csv_content);
        $summary = [
            'total'     => count($rows),
            'succeeded' => [],
            'skipped'   => [],
            'failed'    => [],
        ];

        // Caller's tenant scope.
        $caller_tenant_top = 0;
        if (!is_siteadmin($caller_userid)) {
            $caller = $DB->get_record('user', ['id' => $caller_userid],
                'id, open_path');
            $parts = explode('/', trim((string) ($caller->open_path ?? ''), '/'));
            $caller_tenant_top = isset($parts[0]) && ctype_digit($parts[0])
                ? (int) $parts[0] : 0;
            if ($caller_tenant_top === 0) {
                throw new \moodle_exception('invalidtenant', 'local_sentientia_courses');
            }
        }

        // Pre-fetch role-shortname → roleid map.
        $role_map = [];
        foreach ($DB->get_records('role', null, '', 'id, shortname') as $r) {
            $role_map[strtolower($r->shortname)] = (int) $r->id;
        }

        // Pre-fetch enrol_manual instance loader (cached per course).
        $manual_instances = [];
        $get_manual_instance = function(int $courseid)
            use ($DB, &$manual_instances) {
            if (isset($manual_instances[$courseid])) {
                return $manual_instances[$courseid];
            }
            $instance = $DB->get_record('enrol',
                ['courseid' => $courseid, 'enrol' => 'manual',
                 'status' => 0]);
            $manual_instances[$courseid] = $instance ?: null;
            return $manual_instances[$courseid];
        };

        $manual_plugin = enrol_get_plugin('manual');

        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            $shortname = trim((string) ($row['courseshortname']
                ?? $row['shortname'] ?? ''));
            $role = strtolower(trim((string) ($row['role'] ?? '')))
                ?: self::DEFAULT_ROLE;

            if ($email === '' || $shortname === '') {
                $summary['failed'][] = [
                    'email' => $email, 'course' => $shortname,
                    'error' => 'Missing email or courseshortname.',
                ];
                continue;
            }

            // Lookup user.
            $user = $DB->get_record('user',
                ['email' => $email, 'deleted' => 0],
                'id, suspended, open_path');
            if (!$user) {
                $summary['skipped'][] = [
                    'email' => $email, 'course' => $shortname,
                    'reason' => 'User not found.',
                ];
                continue;
            }

            // Tenant guard.
            if ($caller_tenant_top > 0) {
                $u_parts = explode('/', trim((string) $user->open_path, '/'));
                $u_top = isset($u_parts[0]) && ctype_digit($u_parts[0])
                    ? (int) $u_parts[0] : 0;
                if ($u_top !== $caller_tenant_top) {
                    $summary['skipped'][] = [
                        'email' => $email, 'course' => $shortname,
                        'reason' => 'User in another tenant.',
                    ];
                    continue;
                }
            }

            // Lookup course.
            $course = $DB->get_record('course',
                ['shortname' => $shortname], 'id, fullname, visible');
            if (!$course) {
                $summary['skipped'][] = [
                    'email' => $email, 'course' => $shortname,
                    'reason' => 'Course not found by shortname.',
                ];
                continue;
            }
            if ((int) $course->visible !== 1) {
                $summary['skipped'][] = [
                    'email' => $email, 'course' => $shortname,
                    'reason' => 'Course is hidden.',
                ];
                continue;
            }

            // Lookup role — try direct then alias map.
            $resolved_role = $role;
            if (!isset($role_map[$resolved_role])
                && isset(self::ROLE_ALIASES[$resolved_role])
                && isset($role_map[self::ROLE_ALIASES[$resolved_role]])) {
                $resolved_role = self::ROLE_ALIASES[$resolved_role];
            }
            if (!isset($role_map[$resolved_role])) {
                $summary['failed'][] = [
                    'email' => $email, 'course' => $shortname,
                    'error' => "Unknown role '$role' (valid: "
                        . implode(', ', array_keys($role_map)) . ').',
                ];
                continue;
            }
            $roleid = $role_map[$resolved_role];
            $role = $resolved_role; // For the success log.

            // Lookup or create-skip the manual enrol instance.
            $instance = $get_manual_instance((int) $course->id);
            if (!$instance) {
                $summary['failed'][] = [
                    'email' => $email, 'course' => $shortname,
                    'error' => 'No active manual enrolment method on course.',
                ];
                continue;
            }

            // Already enrolled?
            $existing = $DB->record_exists('user_enrolments',
                ['enrolid' => $instance->id, 'userid' => $user->id]);
            if ($existing) {
                $summary['skipped'][] = [
                    'email' => $email, 'course' => $shortname,
                    'reason' => 'Already enrolled.',
                ];
                continue;
            }

            try {
                $manual_plugin->enrol_user($instance, (int) $user->id,
                    $roleid, 0, 0, ENROL_USER_ACTIVE);
                $summary['succeeded'][] = [
                    'email' => $email,
                    'course' => $shortname,
                    'role' => $role,
                ];
            } catch (\Throwable $e) {
                $summary['failed'][] = [
                    'email' => $email, 'course' => $shortname,
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
