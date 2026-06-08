<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-6 (2026-05-16) — HRMS bulk import engine for 24-column Darwinbox / SAP CSV.
 *
 * Two-pass behaviour:
 *   Pass 1 — parse CSV, validate each row, insert/update users with
 *            open_supervisorid = NULL. Track every row that referenced a
 *            reportingmanager_empid for the second pass.
 *   Pass 2 — walk the manager-link queue, look up each manager by
 *            open_employeeid in the SAME tenant, and SET open_supervisorid.
 *            Unresolved managers (e.g. manager belongs to a different
 *            CSV/tenant) are logged as warnings, not errors — they don't
 *            block the user creation.
 *
 * Field map (CSV column → mdl_user column or open_* custom field):
 *
 *   company_code          → org lookup, sets open_path top-level + open_costcenterid
 *   username              → username (lower-cased)
 *   password              → password (hashed) + force_password_change toggle
 *   employee_code         → idnumber + open_employeeid
 *   prefix                → open_prefix (Mr|Ms|Dr|...)
 *   first_name            → firstname
 *   last_name             → lastname
 *   gender                → gender (M|F|O|U)
 *   email                 → email (lower-cased)
 *   bussiness_unit_code   → 2nd-level org lookup, segment of open_path
 *   department_code       → 3rd-level org lookup, segment of open_path
 *   subdepartment_code    → 4th-level org lookup, segment of open_path
 *   reportingmanager_empid → resolved in pass 2 → open_supervisorid
 *   language              → lang (default 'en')
 *   designation           → open_designation
 *   employment_type       → open_employmenttype
 *   region                → open_region
 *   grade                 → open_grade
 *   date_of_birth         → open_dateofbirth (unix timestamp)
 *   date_of_joining       → open_joindate     (unix timestamp)
 *   mobileno              → phone1
 *   employee_status       → suspended (1 if anything other than Active)
 *   timezone              → timezone (validated against Moodle list)
 *   force_password_change → set as user_preference
 *
 * @package local_sentientia_users
 */
class hrms_importer {

    /** All recognised columns in their canonical order. */
    public const STANDARD_COLUMNS = [
        'company_code', 'username', 'password', 'employee_code', 'prefix',
        'first_name', 'last_name', 'gender', 'email', 'bussiness_unit_code',
        'department_code', 'subdepartment_code', 'reportingmanager_empid',
        'language', 'designation', 'employment_type', 'region', 'grade',
        'date_of_birth', 'date_of_joining', 'mobileno', 'employee_status',
        'timezone', 'force_password_change',
    ];

    /** Subset that must be non-empty in every row. */
    public const MANDATORY_COLUMNS = [
        'first_name', 'last_name', 'username', 'company_code',
        'employee_code', 'employee_status', 'gender', 'email',
    ];

    /** Accepted values for `gender`. */
    public const GENDER_MAP = [
        'm' => 'M', 'male' => 'M',
        'f' => 'F', 'female' => 'F',
        'o' => 'O', 'other' => 'O',
        'u' => 'U', 'unspecified' => 'U', '' => 'U',
    ];

    /** Accepted values for `employee_status`. Anything not 'active' → suspended=1. */
    public const STATUS_ACTIVE_VALUES = [
        'active', 'a', '1', 'true', 'employed',
    ];

    /** Accepted values for `prefix`. */
    public const PREFIX_VALUES = [
        'Mr', 'Mrs', 'Ms', 'Dr', 'Prof', 'Miss',
    ];

    /** Accepted values for `employment_type`. */
    public const EMPLOYMENT_TYPE_VALUES = [
        'Permanent', 'Contract', 'Intern', 'Probation', 'Consultant', 'Temporary',
    ];

    /**
     * Run a full import on the given CSV content. Caller is the admin who
     * triggered the run (recorded for audit). Returns the run_id of the
     * inserted sync_runs row — caller can render success/error summary
     * by reading that row + its sync_errors children.
     *
     * @param string $csv_content   Raw CSV bytes
     * @param int    $caller_userid mdl_user.id of the operator
     * @param string $filename      Original filename for audit (optional)
     * @param string $source        'web' | 'cron' | 'api'
     * @return int  run_id of the sync_runs row created
     */
    public static function import_csv(string $csv_content, int $caller_userid,
                                       string $filename = '',
                                       string $source = 'web'): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        // Compute caller tenant scope. Non-siteadmins can only create users
        // under their own tenant root.
        $caller_costcenterid = self::caller_tenant_root($caller_userid);

        $now = time();
        $run_id = (int) $DB->insert_record('local_sentientia_users_sync_runs', (object) [
            'filename'     => mb_substr(trim($filename), 0, 255),
            'source'       => in_array($source, ['web', 'cron', 'api'], true) ? $source : 'web',
            'costcenterid' => $caller_costcenterid,
            'totalrows'    => 0,
            'usercreated'  => $caller_userid,
            'status'       => 'running',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        try {
            $rows = self::parse_csv($csv_content);
            $DB->set_field('local_sentientia_users_sync_runs', 'totalrows',
                count($rows), ['id' => $run_id]);
        } catch (\Throwable $e) {
            // CSV itself is broken — fail the whole run.
            $DB->update_record('local_sentientia_users_sync_runs', (object) [
                'id'            => $run_id,
                'status'        => 'failed',
                'error_summary' => 'CSV parse error: ' . $e->getMessage(),
                'timemodified'  => time(),
            ]);
            return $run_id;
        }

        // Stats counters.
        $stats = [
            'insertedcount' => 0, 'updatedcount' => 0, 'skippedcount' => 0,
            'errorcount'    => 0, 'warningcount' => 0, 'suspendedcount' => 0,
        ];

        // Pass-1 manager queue: ['userid' => N, 'manager_empid' => 'EMP123', 'csv_line_number' => 5]
        $manager_queue = [];

        // ── PASS 1: insert/update users ────────────────────────────────────
        foreach ($rows as $idx => $row) {
            $line_num = $idx + 2;  // header = line 1, first data row = line 2
            $result = self::process_row($row, $line_num, $run_id,
                $caller_userid, $caller_costcenterid);

            switch ($result['action']) {
                case 'inserted': $stats['insertedcount']++; break;
                case 'updated':  $stats['updatedcount']++;  break;
                case 'skipped':  $stats['skippedcount']++;  break;
                case 'failed':   $stats['errorcount']++;    break;
            }
            if (!empty($result['suspended'])) {
                $stats['suspendedcount']++;
            }
            if (!empty($result['manager_empid']) && !empty($result['userid'])) {
                $manager_queue[] = [
                    'userid'          => (int) $result['userid'],
                    'manager_empid'   => $result['manager_empid'],
                    'csv_line_number' => $line_num,
                    'email'           => $row['email'] ?? '-',
                    'employee_code'   => $row['employee_code'] ?? '-',
                ];
            }
        }

        // ── PASS 2: resolve manager links ──────────────────────────────────
        if (!empty($manager_queue)) {
            $stats['warningcount'] += self::resolve_manager_links(
                $manager_queue, $run_id, $caller_userid, $caller_costcenterid);
        }

        // ── Finalise sync_runs row ─────────────────────────────────────────
        $DB->update_record('local_sentientia_users_sync_runs', (object) [
            'id'             => $run_id,
            'status'         => 'completed',
            'insertedcount'  => $stats['insertedcount'],
            'updatedcount'   => $stats['updatedcount'],
            'skippedcount'   => $stats['skippedcount'],
            'errorcount'     => $stats['errorcount'],
            'warningcount'   => $stats['warningcount'],
            'suspendedcount' => $stats['suspendedcount'],
            'timemodified'   => time(),
        ]);

        return $run_id;
    }

    /**
     * Resolve `manager_empid` → userid for every queued row, then SET
     * open_supervisorid on the freshly-inserted user. Unresolved managers
     * are logged as warnings (not errors) — they may resolve next sync.
     *
     * @return int Number of warnings written for unresolved managers.
     */
    private static function resolve_manager_links(array $queue, int $run_id,
                                                    int $caller_userid,
                                                    int $caller_costcenterid): int {
        global $DB;

        // Collect every manager_empid into one IN-clause so we hit the DB
        // exactly once for the lookup.
        $empids = array_unique(array_column($queue, 'manager_empid'));
        if (empty($empids)) {
            return 0;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($empids, SQL_PARAMS_NAMED, 'emp');
        $manager_rows = $DB->get_records_sql(
            "SELECT id, open_employeeid, open_path
               FROM {user}
              WHERE deleted = 0
                AND suspended = 0
                AND open_employeeid $insql",
            $inparams
        );

        // Index by employee_code for O(1) lookup. If the same employee_code
        // exists in multiple rows, last-write-wins (shouldn't happen — it's
        // supposed to be unique, but be defensive).
        $by_empid = [];
        foreach ($manager_rows as $m) {
            if (!empty($m->open_employeeid)) {
                $by_empid[$m->open_employeeid] = $m;
            }
        }

        $warning_count = 0;
        foreach ($queue as $q) {
            $manager = $by_empid[$q['manager_empid']] ?? null;
            if (!$manager) {
                self::write_log_row($run_id, $q['csv_line_number'], [
                    'email'         => $q['email'],
                    'employee_code' => $q['employee_code'],
                    'firstname'     => '',
                    'lastname'      => '',
                    'username'      => '-',
                ], [
                    'Manager (employee_code=' . $q['manager_empid']
                        . ') not found in Moodle. open_supervisorid left NULL.',
                ], [], 'warning', $caller_userid);
                $warning_count++;
                continue;
            }
            // Tenant-scope check: caller (non-siteadmin) can only link
            // managers within their tenant tree.
            if ($caller_costcenterid > 0
                && !self::path_starts_with_tenant($manager->open_path, $caller_costcenterid)) {
                self::write_log_row($run_id, $q['csv_line_number'], [
                    'email'         => $q['email'],
                    'employee_code' => $q['employee_code'],
                    'firstname'     => '',
                    'lastname'      => '',
                    'username'      => '-',
                ], [
                    'Manager (employee_code=' . $q['manager_empid']
                        . ') is outside caller tenant scope. open_supervisorid left NULL.',
                ], [], 'warning', $caller_userid);
                $warning_count++;
                continue;
            }
            // SET the supervisor link.
            $DB->set_field('user', 'open_supervisorid', (int) $manager->id,
                ['id' => $q['userid']]);
        }

        return $warning_count;
    }

    /**
     * Process a single CSV row. Returns:
     *   ['action' => 'inserted'|'updated'|'skipped'|'failed',
     *    'userid' => int|0,
     *    'manager_empid' => string|null,
     *    'suspended' => bool]
     */
    private static function process_row(array $row, int $line_num, int $run_id,
                                          int $caller_userid,
                                          int $caller_costcenterid): array {
        global $DB;

        $errors   = [];
        $missing  = [];

        // ── 1. Mandatory-field check ───────────────────────────────────
        foreach (self::MANDATORY_COLUMNS as $field) {
            if (trim((string) ($row[$field] ?? '')) === '') {
                $missing[] = $field;
                $errors[] = "Missing required field: $field";
            }
        }

        // Normalise inputs once.
        $email        = strtolower(trim((string) ($row['email'] ?? '')));
        $username     = strtolower(trim((string) ($row['username'] ?? '')));
        $employee_code = trim((string) ($row['employee_code'] ?? ''));
        $firstname    = trim((string) ($row['first_name'] ?? ''));
        $lastname     = trim((string) ($row['last_name'] ?? ''));

        // ── 2. Format validations ──────────────────────────────────────
        if ($email !== '' && !validate_email($email)) {
            $errors[] = "Invalid email format: $email";
        }
        if ($username !== '' && !preg_match('/^[a-z0-9._@\\-]+$/', $username)) {
            $errors[] = "Invalid username format (lowercase alphanum + ._-@): $username";
        }
        $gender_norm = self::normalize_gender($row['gender'] ?? '');
        if ($gender_norm === null && !in_array('gender', $missing, true)) {
            $errors[] = "Invalid gender value: " . ($row['gender'] ?? '');
        }
        if (!empty($row['mobileno'])) {
            $mob = preg_replace('/\D/', '', (string) $row['mobileno']);
            if (strlen($mob) < 7 || strlen($mob) > 15) {
                $errors[] = "Invalid mobile number: " . $row['mobileno'];
            }
        }
        $dob_ts = self::parse_date($row['date_of_birth'] ?? '', 'date_of_birth', $errors);
        $doj_ts = self::parse_date($row['date_of_joining'] ?? '', 'date_of_joining', $errors);

        // ── 3. Org cascade lookup (company_code → BU → dept → subdept) ─
        $org_resolution = self::resolve_org_path(
            $row['company_code']         ?? '',
            $row['bussiness_unit_code']  ?? '',
            $row['department_code']      ?? '',
            $row['subdepartment_code']   ?? '',
            $errors
        );

        // ── 4. Tenant guard for non-siteadmin caller ───────────────────
        if ($caller_costcenterid > 0
            && $org_resolution['costcenterid'] > 0
            && $org_resolution['costcenterid'] !== $caller_costcenterid) {
            $errors[] = "Row's company_code resolves to a tenant outside your scope.";
        }

        // ── 5. Existing-user lookup (3-way: email, username, employee_code) ─
        $existing = self::find_existing_user($email, $username, $employee_code);
        if ($existing === 'multiple') {
            $errors[] = 'Multiple existing users match this row (email/username/employee_code clash).';
        }

        // Fail early if any errors so far.
        if (!empty($errors)) {
            self::write_log_row($run_id, $line_num, [
                'email'         => $email ?: '-',
                'employee_code' => $employee_code ?: '-',
                'username'      => $username ?: '-',
                'firstname'     => $firstname,
                'lastname'      => $lastname,
            ], $errors, $missing, 'error', $caller_userid);
            return ['action' => 'failed', 'userid' => 0,
                    'manager_empid' => null, 'suspended' => false];
        }

        // ── 6. Build the user object ───────────────────────────────────
        $is_active = self::is_status_active($row['employee_status'] ?? '');
        $userdata = (object) [
            'username'           => $username,
            'email'              => $email,
            'firstname'          => $firstname,
            'lastname'           => $lastname,
            'gender'             => $gender_norm,
            'idnumber'           => $employee_code,
            'open_employeeid'    => $employee_code,
            'open_prefix'        => self::normalize_prefix($row['prefix'] ?? ''),
            'open_designation'   => trim((string) ($row['designation']     ?? '')),
            'open_employmenttype' => self::normalize_employment_type($row['employment_type'] ?? ''),
            'open_region'        => trim((string) ($row['region']          ?? '')),
            'open_grade'         => trim((string) ($row['grade']           ?? '')),
            'open_dateofbirth'   => $dob_ts ?: null,
            'open_joindate'      => $doj_ts ?: null,
            'phone1'             => preg_replace('/\D/', '', (string) ($row['mobileno'] ?? '')),
            'lang'               => self::normalize_lang($row['language'] ?? ''),
            'timezone'           => self::normalize_timezone($row['timezone'] ?? ''),
            'suspended'          => $is_active ? 0 : 1,
            'open_path'          => $org_resolution['open_path'] ?: ('/' . $caller_costcenterid),
            'open_costcenterid'  => $org_resolution['costcenterid']
                                    ?: $caller_costcenterid,
            'auth'               => 'manual',
            'mnethostid'         => 1,
            'confirmed'          => 1,
        ];

        // Set department levels if cascade resolved them.
        foreach (['department', 'subdepartment',
                  'level4department', 'level5department'] as $key) {
            if (!empty($org_resolution[$key])) {
                $userdata->{'open_' . $key} = $org_resolution[$key];
            }
        }

        // ── 7. Insert or update ─────────────────────────────────────────
        $now = time();
        $manager_empid = trim((string) ($row['reportingmanager_empid'] ?? '')) ?: null;
        $force_pwd = !empty($row['force_password_change']) ? 1 : 0;

        try {
            if ($existing && $existing !== 'multiple') {
                // Update existing.
                $userdata->id           = (int) $existing->id;
                $userdata->timemodified = $now;
                // Don't clobber confirmed/auth if already set differently.
                unset($userdata->auth);
                unset($userdata->mnethostid);
                unset($userdata->confirmed);
                // Password update only if column non-empty.
                $pw = trim((string) ($row['password'] ?? ''));
                if ($pw !== '' && self::is_strong_password($pw)) {
                    $userdata->password = hash_internal_user_password($pw);
                }
                \user_update_user($userdata, false, false);
                if ($force_pwd) {
                    set_user_preference('auth_forcepasswordchange', 1,
                        (int) $existing->id);
                }
                return ['action' => 'updated', 'userid' => (int) $existing->id,
                        'manager_empid' => $manager_empid,
                        'suspended' => !$is_active];
            }

            // Insert new.
            $pw = trim((string) ($row['password'] ?? ''));
            if ($pw !== '' && self::is_strong_password($pw)) {
                $userdata->password = hash_internal_user_password($pw);
            } else {
                // Generate a random password — admin can trigger "forgot password" reset.
                $userdata->password = hash_internal_user_password(generate_password(20));
            }
            $userdata->timecreated  = $now;
            $userdata->timemodified = $now;
            $newid = \user_create_user($userdata, false, false);
            if ($force_pwd) {
                set_user_preference('auth_forcepasswordchange', 1, (int) $newid);
            }
            return ['action' => 'inserted', 'userid' => (int) $newid,
                    'manager_empid' => $manager_empid,
                    'suspended' => !$is_active];
        } catch (\Throwable $e) {
            self::write_log_row($run_id, $line_num, [
                'email'         => $email,
                'employee_code' => $employee_code,
                'username'      => $username,
                'firstname'     => $firstname,
                'lastname'      => $lastname,
            ], ['Database error: ' . $e->getMessage()], [], 'error', $caller_userid);
            return ['action' => 'failed', 'userid' => 0,
                    'manager_empid' => null, 'suspended' => false];
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Find an existing user matching ANY of (email, username, employee_code).
     * Returns:
     *   - the user record if exactly one match
     *   - 'multiple' if 2+ different users match
     *   - null if no match
     */
    private static function find_existing_user(string $email, string $username,
                                                 string $employee_code) {
        global $DB;
        $params = [
            'email'    => $email,
            'username' => $username,
            'empid'    => $employee_code,
        ];
        $rows = $DB->get_records_sql(
            "SELECT id, email, username, open_employeeid, open_path
               FROM {user}
              WHERE deleted = 0
                AND (email = :email
                  OR username = :username
                  OR (open_employeeid IS NOT NULL AND open_employeeid <> '' AND open_employeeid = :empid))",
            $params
        );
        if (empty($rows)) {
            return null;
        }
        if (count($rows) === 1) {
            return reset($rows);
        }
        return 'multiple';
    }

    /**
     * Cascade lookup: company_code → BU → dept → subdept against
     * local_airpay_org.shortname under the right parentid.
     *
     * Returns:
     *   ['costcenterid' => int, 'department' => int, 'subdepartment' => int,
     *    'level4department' => int, 'open_path' => string]
     * Any level that fails to resolve returns 0 for its id and shortens
     * the open_path. Errors collected in $errors_out.
     */
    private static function resolve_org_path(string $company_code, string $bu_code,
                                               string $dept_code, string $subdept_code,
                                               array &$errors_out): array {
        global $DB;
        $result = [
            'costcenterid'     => 0,
            'department'       => 0,
            'subdepartment'    => 0,
            'level4department' => 0,
            'open_path'        => '',
        ];

        if ($company_code === '') {
            return $result;
        }
        // Level 1 — company.
        $org = $DB->get_record('local_airpay_org',
            ['shortname' => trim($company_code), 'parentid' => 0]);
        if (!$org) {
            $errors_out[] = "company_code='$company_code' not found in org tree.";
            return $result;
        }
        $result['costcenterid'] = (int) $org->id;
        $result['open_path']    = '/' . $org->id;
        $current_parent = (int) $org->id;

        $segments = [
            ['key' => 'department',       'val' => $bu_code,      'col' => 'bussiness_unit_code'],
            ['key' => 'subdepartment',    'val' => $dept_code,    'col' => 'department_code'],
            ['key' => 'level4department', 'val' => $subdept_code, 'col' => 'subdepartment_code'],
        ];
        foreach ($segments as $seg) {
            $val = trim((string) $seg['val']);
            if ($val === '') {
                break;  // skip remaining levels if this one empty
            }
            $child = $DB->get_record('local_airpay_org',
                ['shortname' => $val, 'parentid' => $current_parent]);
            if (!$child) {
                $errors_out[] = "{$seg['col']}='$val' not found under parent org id $current_parent.";
                break;
            }
            $result[$seg['key']] = (int) $child->id;
            $result['open_path'] .= '/' . $child->id;
            $current_parent = (int) $child->id;
        }
        return $result;
    }

    /**
     * Return the top-level org id (= costcenterid) for the caller from their
     * open_path. 0 for siteadmin (= no restriction).
     */
    private static function caller_tenant_root(int $userid): int {
        if (is_siteadmin($userid)) {
            return 0;
        }
        global $DB;
        $path = (string) ($DB->get_field('user', 'open_path', ['id' => $userid]) ?? '');
        $parts = explode('/', trim($path, '/'));
        return isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    }

    /** True iff $path starts with /$tenant_root or equals /$tenant_root. */
    private static function path_starts_with_tenant(?string $path, int $tenant_root): bool {
        $path = (string) $path;
        $expected = '/' . $tenant_root;
        return $path === $expected
            || str_starts_with($path, $expected . '/');
    }

    private static function normalize_gender(string $raw): ?string {
        $key = strtolower(trim($raw));
        return self::GENDER_MAP[$key] ?? null;
    }

    private static function normalize_prefix(string $raw): string {
        $val = trim($raw);
        if ($val === '') {
            return '';
        }
        foreach (self::PREFIX_VALUES as $p) {
            if (strcasecmp($p, $val) === 0) {
                return $p;
            }
        }
        return '';
    }

    private static function normalize_employment_type(string $raw): string {
        $val = trim($raw);
        if ($val === '') {
            return '';
        }
        foreach (self::EMPLOYMENT_TYPE_VALUES as $t) {
            if (strcasecmp($t, $val) === 0) {
                return $t;
            }
        }
        return '';
    }

    private static function normalize_lang(string $raw): string {
        $val = strtolower(trim($raw));
        // Common 2-char Moodle lang codes used at Airpay; fall back to 'en'.
        $known = ['en', 'hi', 'kn', 'mr', 'sw', 'es', 'fr', 'de'];
        return in_array($val, $known, true) ? $val : 'en';
    }

    private static function normalize_timezone(string $raw): string {
        $val = trim($raw);
        if ($val === '') {
            return '99';  // 99 = use server default in Moodle
        }
        try {
            new \DateTimeZone($val);
            return $val;
        } catch (\Throwable $e) {
            return '99';
        }
    }

    private static function is_status_active(string $raw): bool {
        $val = strtolower(trim($raw));
        return in_array($val, self::STATUS_ACTIVE_VALUES, true);
    }

    /**
     * Try DD-MM-YYYY first (BizLMS HRMS exports use this), then YYYY-MM-DD,
     * then DD/MM/YYYY. Returns unix timestamp or 0 on failure.
     * If the cell is empty, returns 0 silently (date_of_birth is optional).
     */
    private static function parse_date(string $raw, string $field_name,
                                         array &$errors_out): int {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }
        $formats = ['d-m-Y', 'Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat('!' . $fmt, $raw);
            if ($dt !== false && $dt->format($fmt) === $raw) {
                return $dt->getTimestamp();
            }
        }
        $errors_out[] = "Could not parse $field_name='$raw' (try DD-MM-YYYY or YYYY-MM-DD).";
        return 0;
    }

    /**
     * Light password-policy check — must be >= 8 chars + contain at least
     * one digit + one letter. Bypass Moodle's full policy here because the
     * HRMS-supplied password is usually a system-generated one we trust.
     */
    private static function is_strong_password(string $pw): bool {
        return strlen($pw) >= 8
            && preg_match('/[A-Za-z]/', $pw)
            && preg_match('/\d/', $pw);
    }

    /**
     * Parse CSV using Moodle's standard csv_import_reader, falling back to
     * str_getcsv for simple cases. Returns an array of associative rows
     * keyed by canonical column name (lowercased + underscored).
     *
     * Throws if the header is missing required columns.
     */
    private static function parse_csv(string $content): array {
        // Normalise line endings and BOM.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }
        $header_line = array_shift($lines);
        $header = str_getcsv($header_line);
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);

        // Verify the header contains all mandatory columns.
        $missing = array_diff(self::MANDATORY_COLUMNS, $header);
        if (!empty($missing)) {
            throw new \moodle_exception('error_csv_header_missing',
                'local_sentientia_users', '', implode(', ', $missing));
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = str_getcsv($line);
            $row = [];
            foreach ($header as $idx => $key) {
                if (in_array($key, self::STANDARD_COLUMNS, true)) {
                    $row[$key] = $fields[$idx] ?? '';
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Write one row into local_sentientia_users_sync_errors.
     */
    private static function write_log_row(int $run_id, int $line_num, array $identity,
                                            array $errors, array $missing_fields,
                                            string $severity, int $modified_by): void {
        global $DB;
        $DB->insert_record('local_sentientia_users_sync_errors', (object) [
            'runid'            => $run_id,
            'csv_line_number'  => $line_num,
            'email'            => mb_substr((string) ($identity['email'] ?: '-'), 0, 254),
            'employee_code'    => mb_substr((string) ($identity['employee_code'] ?: '-'), 0, 100),
            'username'         => mb_substr((string) ($identity['username'] ?: '-'), 0, 100),
            'firstname'        => mb_substr((string) ($identity['firstname'] ?? ''), 0, 100),
            'lastname'         => mb_substr((string) ($identity['lastname']  ?? ''), 0, 100),
            'error_message'    => implode(' | ', $errors),
            'mandatory_fields' => implode(',', $missing_fields),
            'severity'         => in_array($severity, ['error', 'warning'], true) ? $severity : 'error',
            'modified_by'      => $modified_by,
            'timecreated'      => time(),
        ]);
    }
}
