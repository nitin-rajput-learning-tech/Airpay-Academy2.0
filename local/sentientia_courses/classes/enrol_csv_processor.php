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

        // Caller's tenant scope. ADR-031: only a cross-tenant caller (site
        // admin or :crosstenant holder) is unscoped (null); a scoped caller
        // whose tenant does not resolve gets invalidtenant.
        $caller_root = course_manager::enrol_scope_root($caller_userid);
        $caller_tenant_top = $caller_root ?? 0;

        // Pre-fetch role-shortname → roleid map.
        $role_map = [];
        foreach ($DB->get_records('role', null, '', 'id, shortname') as $r) {
            $role_map[strtolower($r->shortname)] = (int) $r->id;
        }

        // ADR-031 (follow-up): the roles each course accepts - the very list
        // the enrol modal offers (course_manager::enrol_role_choices()), so a
        // CSV can no longer give a role the modal hides ('administrator',
        // 'manager', ...). Cached per course.
        $role_choices = [];
        $get_role_choices = function(\stdClass $course)
            use (&$role_choices, $caller_root, $caller_userid) {
            $cid = (int) $course->id;
            if (!isset($role_choices[$cid])) {
                $role_choices[$cid] = course_manager::enrol_role_choices($course,
                    $caller_root, $caller_userid);
            }
            return $role_choices[$cid];
        };

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

            // Lookup user. ADR-031 (follow-up): bounded to the caller's
            // tenant BEFORE a row is picked. With allowaccountssameemail an
            // address can exist in two tenants, and get_record() used to hand
            // back whichever came first - the foreign account made the
            // caller's own user read "not found". Another tenant's user reads
            // exactly like a missing one, so the summary is no oracle.
            $matches = course_manager::users_by_email_in_scope($email, $caller_root,
                'id, suspended, open_path');
            if (!$matches) {
                $summary['skipped'][] = [
                    'email' => $email, 'course' => $shortname,
                    'reason' => 'User not found.',
                ];
                continue;
            }
            if (count($matches) > 1) {
                // Only ever the caller's own tenant's accounts (or, for a
                // cross-tenant caller, anyone's): never guess which one.
                $summary['failed'][] = [
                    'email' => $email, 'course' => $shortname,
                    'error' => 'Email matches more than one user.',
                ];
                continue;
            }
            $user = $matches[0];

            // Tenant guard - defence in depth behind the bounded lookup.
            if ($caller_tenant_top > 0
                    && !course_manager::path_in_tenant((string) $user->open_path, $caller_tenant_top)) {
                $summary['skipped'][] = [
                    'email' => $email, 'course' => $shortname,
                    'reason' => 'User not found.',
                ];
                continue;
            }

            // Lookup course. '*' because open_path is a BizLMS column a
            // vanilla schema does not have.
            $course = $DB->get_record('course',
                ['shortname' => $shortname], '*');
            // ADR-031: until 2026-09-25 any visible course of any tenant was
            // accepted. A scoped caller may only enrol into a course owned by,
            // shared to, or (legacy, no open_path) listed for their tenant;
            // anything else reads as not found.
            if ($course && $caller_tenant_top > 0
                    && !course_manager::course_in_enrol_scope($course, $caller_tenant_top)) {
                $course = false;
            }
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

            // ADR-031: only a role the enrol modal would offer for this
            // course - never guest / user / frontpage / administrator; for a
            // scoped caller only roles they may assign here, never manager,
            // coursecreator or a site-level role, and learner roles only in a
            // course their tenant does not own.
            if (!array_key_exists($roleid, $get_role_choices($course))) {
                $summary['failed'][] = [
                    'email' => $email, 'course' => $shortname,
                    'error' => "Role '$role' cannot be given in this course.",
                ];
                continue;
            }

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
