<?php
/**
 * KeKa HRMS Client — syncs employees, departments, handles JML events.
 *
 * Authentication: OAuth 2.0 with API key.
 * Endpoints: Core HR (employees, departments, groups, exit).
 * Webhooks: employee.hired, employee.terminated, employee.transferred.
 *
 * 2026-08-07 hardening (KeKa JML investigation, 2026-08-05):
 *  - Single canonical upsert path ({@see upsert_employee}) shared by the
 *    webhook AND the reconciliation task — the duplicate-implementation
 *    risk that forced the 2026-05-07 task deletion (INTEGRATIONS-AUDIT.md
 *    §3.2) cannot recur because there is only one implementation now.
 *  - Identity matching by open_employeeid FIRST, email second. KeKa
 *    employee numbers are immutable; email addresses are not. Matching
 *    email-first meant an email change in KeKa created a duplicate user.
 *  - User writes go through user_create_user()/user_update_user() so the
 *    real \core\event\user_created / user_updated events fire (no more
 *    forged event + raw $DB->insert_record).
 *  - Leaver hardening: employeeId fallback lookup, session kill on
 *    suspend, suspend routed through user_update_user().
 *  - Tenant placement: department-code → org shortname mapping preferred,
 *    name-match fallback, and webhook-created users default under a
 *    validated org path (setting keka_default_orgpath, default /1) so an
 *    unmatched KeKa department can no longer yield a TENANTLESS user.
 *  - Manager sync: KeKa reportsTo → open_supervisorid, two-pass pattern
 *    borrowed from local_sentientia_users\hrms_importer.
 *
 * LIVE-CONTRACT ASSUMPTIONS (unverified — see README "KeKa contract
 * verification"): event names, payload field shapes (employeeNumber /
 * department / reportsTo variants) and KeKa egress IPs are assumed from
 * KeKa's public developer docs, not verified against a live tenant.
 *
 * @package    local_sentientia_integrations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_integrations;

defined('MOODLE_INTERNAL') || die();

class keka_client {

    /** Platform feature flag gating the inbound webhook endpoint. */
    public const FLAG_WEBHOOK = 'sentientia.hrms.webhook.enabled';

    /** Platform feature flag gating the scheduled reconciliation pull. */
    public const FLAG_RECONCILE = 'sentientia.hrms.reconcile.enabled';

    /** KeKa API base URL. */
    private string $base_url;

    /** OAuth access token. */
    private ?string $access_token = null;

    /** @var bool|null Cached "does {user}.open_employeeid exist" schema check. */
    private static ?bool $hasemployeeidcolumn = null;

    public function __construct() {
        $this->base_url = get_config('local_sentientia_integrations', 'keka_base_url') ?: 'https://api.keka.com';
    }

    // ─── Feature gates ───────────────────────────────────────────────────

    /**
     * Is the inbound KeKa webhook allowed to process events?
     *
     * Two gates, both required:
     *   1. Platform flag sentientia.hrms.webhook.enabled (default OFF).
     *      Resolved at global scope — the webhook is an unauthenticated
     *      server-to-server call, there is no $USER tenant to consult.
     *   2. The plugin's hrms_enable admin setting (default 0). Before
     *      2026-08-07 NOTHING read this setting — the webhook went live
     *      the moment webhook_secret was configured.
     */
    public static function webhook_enabled(): bool {
        return self::gate_open(self::FLAG_WEBHOOK);
    }

    /**
     * Is the scheduled reconciliation pull allowed to run?
     * Same two-gate structure as {@see webhook_enabled}.
     */
    public static function reconcile_enabled(): bool {
        return self::gate_open(self::FLAG_RECONCILE);
    }

    /**
     * Shared flag + setting gate. Fails safe (OFF) when the platform
     * plugin is absent.
     */
    private static function gate_open(string $flag): bool {
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return false;
        }
        if (!\local_sentientia_platform\feature_flags::is_enabled_for($flag, 0, 0)) {
            return false;
        }
        return (bool) get_config('local_sentientia_integrations', 'hrms_enable');
    }

    // ─── KeKa API ────────────────────────────────────────────────────────

    /**
     * Authenticate with KeKa using API key → get access token.
     */
    public function authenticate(): bool {
        $api_key = get_config('local_sentientia_integrations', 'keka_api_key');
        $client_id = get_config('local_sentientia_integrations', 'keka_client_id');
        $client_secret = get_config('local_sentientia_integrations', 'keka_client_secret');

        if (empty($api_key) && empty($client_id)) {
            return false;
        }

        // Method 1: API Key token generation.
        if (!empty($api_key)) {
            $response = $this->http_post('/connect/token', [
                'grant_type' => 'kekaapi',
                'scope'      => 'kekaapi',
                'api_key'    => $api_key,
            ], 'form');

            if ($response && isset($response['access_token'])) {
                $this->access_token = $response['access_token'];
                return true;
            }
        }

        // Method 2: OAuth client credentials.
        if (!empty($client_id) && !empty($client_secret)) {
            $response = $this->http_post('/connect/token', [
                'grant_type'    => 'client_credentials',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'scope'         => 'kekaapi',
            ], 'form');

            if ($response && isset($response['access_token'])) {
                $this->access_token = $response['access_token'];
                return true;
            }
        }

        return false;
    }

    /**
     * Get all employees from KeKa.
     *
     * @param int $page Page number (1-based)
     * @param int $pagesize Page size
     * @return array {data: [{id, firstName, lastName, email, department, ...}], hasMore: bool}
     */
    public function get_employees(int $page = 1, int $pagesize = 100): array {
        return $this->http_get('/v1/hris/employees', [
            'pageNumber' => $page,
            'pageSize'   => $pagesize,
        ]);
    }

    /**
     * Get single employee by ID.
     */
    public function get_employee(string $employee_id): ?array {
        // M1 (UAT security posture 2026-09-03): the id can originate from a webhook payload,
        // so it is URL-encoded before it becomes a path segment of the outbound request.
        return $this->http_get('/v1/hris/employees/' . rawurlencode((string) $employee_id));
    }

    /**
     * Get departments from KeKa.
     */
    public function get_departments(): array {
        return $this->http_get('/v1/hris/departments') ?: [];
    }

    /**
     * Get locations from KeKa.
     */
    public function get_locations(): array {
        return $this->http_get('/v1/hris/locations') ?: [];
    }

    // ─── Sync ────────────────────────────────────────────────────────────

    /**
     * Sync employees from KeKa → Moodle.
     * Creates new users, updates existing, suspends terminated, then
     * resolves manager links in a second pass (so a manager who appears
     * later in the same pull still resolves).
     *
     * @return array {created: int, updated: int, suspended: int, skipped: int,
     *                errors: int, manager_links: int}
     */
    public function sync_employees(): array {
        if (!$this->authenticate()) {
            return ['created' => 0, 'updated' => 0, 'suspended' => 0, 'skipped' => 0,
                    'errors' => 1, 'manager_links' => 0,
                    'error_message' => 'Authentication failed'];
        }

        $stats = ['created' => 0, 'updated' => 0, 'suspended' => 0,
                  'skipped' => 0, 'errors' => 0, 'manager_links' => 0];
        $managerqueue = [];
        $page = 1;

        do {
            $result = $this->get_employees($page, 100);
            $employees = $result['data'] ?? $result ?? [];

            if (empty($employees) || !is_array($employees)) {
                break;
            }

            foreach ($employees as $emp) {
                try {
                    $outcome = $this->upsert_employee($emp);
                    $this->tally($outcome, $stats);
                    if (!empty($outcome['manager_empid']) && !empty($outcome['userid'])) {
                        $managerqueue[] = [
                            'userid'        => (int) $outcome['userid'],
                            'manager_empid' => $outcome['manager_empid'],
                        ];
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    debugging('KeKa sync error: ' . $e->getMessage());
                }
            }

            $page++;
            $hasmore = $result['hasMore'] ?? (count($employees) >= 100);
        } while ($hasmore && $page <= 100); // Safety limit.

        // PASS 2 — manager links, one IN-clause lookup for the whole pull
        // (two-pass pattern from local_sentientia_users\hrms_importer).
        $stats['manager_links'] = self::resolve_manager_links($managerqueue);

        return $stats;
    }

    /**
     * Fold one upsert outcome into the running stats array.
     */
    private function tally(array $outcome, array &$stats): void {
        $key = ['created' => 'created', 'updated' => 'updated',
                'suspended' => 'suspended', 'skipped' => 'skipped',
                'error' => 'errors'][$outcome['action']] ?? 'errors';
        $stats[$key]++;
    }

    /**
     * Upsert a single KeKa employee record — THE canonical JML write path,
     * shared by the webhook receiver and the reconciliation task.
     *
     * Identity precedence: open_employeeid first (immutable in KeKa),
     * email second (mutable — email-first matching used to create a
     * duplicate user whenever an address changed in KeKa).
     *
     * @param array $emp Raw KeKa employee shape (camelCase — see class docblock)
     * @return array {action: created|updated|suspended|skipped|error,
     *                userid: int, manager_empid: ?string, message: string}
     */
    public function upsert_employee(array $emp): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $email = strtolower(trim($emp['email'] ?? $emp['workEmail'] ?? ''));
        $employeeid = trim((string) ($emp['employeeNumber'] ?? $emp['id'] ?? ''));

        if ($email === '' && $employeeid === '') {
            return ['action' => 'error', 'userid' => 0, 'manager_empid' => null,
                    'message' => 'Payload carries neither email nor employee id'];
        }

        // Map KeKa fields to Moodle.
        $userdata = [
            'email'               => $email,
            'firstname'           => trim((string) ($emp['firstName'] ?? '')),
            'lastname'            => trim((string) ($emp['lastName'] ?? '')),
            'open_employeeid'     => $employeeid,
            'open_designation'    => trim((string) ($emp['jobTitle'] ?? $emp['designation'] ?? '')),
            'open_location'       => trim((string) (is_array($emp['location'] ?? null)
                                        ? ($emp['location']['name'] ?? '') : ($emp['location'] ?? ''))),
            'open_employmenttype' => trim((string) ($emp['employmentType'] ?? '')),
        ];

        // ASSUMPTION (live contract unverified): reportsTo may arrive as a
        // scalar employee number or an object {id, employeeNumber}.
        $rep = $emp['reportsTo'] ?? $emp['reportingManager'] ?? null;
        $manager_empid = is_array($rep)
            ? trim((string) ($rep['employeeNumber'] ?? $rep['id'] ?? ''))
            : trim((string) $rep);
        $manager_empid = $manager_empid !== '' ? $manager_empid : null;

        // Tenant placement.
        $org = $this->resolve_org_path($emp);

        // Status normalisation.
        $status = $emp['status'] ?? $emp['employeeStatus'] ?? 'active';
        $is_terminated = in_array(strtolower((string) $status),
            ['inactive', 'terminated', 'exited', 'relieved'], true);

        $existing = self::find_existing_user($employeeid, $email);

        if ($existing) {
            if ($is_terminated) {
                if (!$existing->suspended) {
                    self::suspend_user($existing);
                    return ['action' => 'suspended', 'userid' => (int) $existing->id,
                            'manager_empid' => null, 'message' => 'Leaver suspended'];
                }
                return ['action' => 'skipped', 'userid' => (int) $existing->id,
                        'manager_empid' => null, 'message' => 'Already suspended'];
            }

            // Mover: update changed fields only. A MAPPED org path may move
            // the user; the default fallback path never overwrites an
            // existing placement (an unmatched KeKa department must not
            // silently re-tenant someone to the default).
            if ($org['mapped'] && !empty($org['path'])) {
                $userdata['open_path'] = $org['path'];
            }
            $update = new \stdClass();
            $update->id = (int) $existing->id;
            $changed = false;
            foreach ($userdata as $field => $value) {
                if ($value !== '' && $value !== null
                        && property_exists($existing, $field)
                        && $existing->$field !== $value) {
                    $update->$field = $value;
                    $changed = true;
                }
            }
            if ($changed) {
                \user_update_user($update, false, true);
                return ['action' => 'updated', 'userid' => (int) $existing->id,
                        'manager_empid' => $manager_empid, 'message' => 'Mover updated'];
            }
            return ['action' => 'skipped', 'userid' => (int) $existing->id,
                    'manager_empid' => $manager_empid, 'message' => 'No changes'];
        }

        if ($is_terminated) {
            // Never create an account for someone who already left.
            return ['action' => 'skipped', 'userid' => 0, 'manager_empid' => null,
                    'message' => 'Terminated employee with no existing account'];
        }
        if ($email === '') {
            return ['action' => 'error', 'userid' => 0, 'manager_empid' => null,
                    'message' => 'Cannot create a user without an email address'];
        }

        // Joiner: create via user_create_user() so the REAL
        // \core\event\user_created fires (lifecycle observer feeds off it).
        $userdata['open_path'] = $org['path']; // Mapped, or validated default — never tenantless.
        $newuser = (object) array_merge([
            'auth'       => 'manual',
            'confirmed'  => 1,
            'mnethostid' => 1,
            'username'   => $email,
            'password'   => hash_internal_user_password(generate_password(20)),
        ], array_filter($userdata, fn($v) => $v !== null && $v !== ''));

        $userid = \user_create_user($newuser, false, true);

        return ['action' => 'created', 'userid' => (int) $userid,
                'manager_empid' => $manager_empid, 'message' => 'Joiner created'];
    }

    /**
     * Suspend a user the correct way: through user_update_user() (fires
     * \core\event\user_updated, stamps timemodified) and kill their live
     * sessions so a leaver cannot keep an authenticated browser open.
     */
    public static function suspend_user(\stdClass $user): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $update = new \stdClass();
        $update->id = (int) $user->id;
        $update->suspended = 1;
        \user_update_user($update, false, true);

        if (method_exists('\core\session\manager', 'destroy_user_sessions')) {
            \core\session\manager::destroy_user_sessions((int) $user->id);
        } else {
            // Pre-4.5 name (kill_user_sessions deprecated by MDL-66161).
            \core\session\manager::kill_user_sessions((int) $user->id);
        }
    }

    /**
     * Find the Moodle user for a KeKa identity — open_employeeid first,
     * email second.
     *
     * @param string $employeeid KeKa employee number ('' to skip)
     * @param string $email      Lowercased email ('' to skip)
     * @return \stdClass|null
     */
    public static function find_existing_user(string $employeeid, string $email): ?\stdClass {
        global $DB;

        if ($employeeid !== '' && self::has_employeeid_column()) {
            $matches = $DB->get_records_select('user',
                "deleted = 0 AND open_employeeid = :empid",
                ['empid' => $employeeid], 'id ASC');
            if (count($matches) > 1) {
                debugging("KeKa sync: multiple users share open_employeeid={$employeeid}"
                    . ' — using the oldest account', DEBUG_DEVELOPER);
            }
            if ($matches) {
                return reset($matches);
            }
        }

        if ($email !== '') {
            $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0],
                '*', IGNORE_MULTIPLE);
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Resolve a KeKa employee's department to a sentientia_org path.
     *
     * Order of preference:
     *   1. Department CODE → {local_sentientia_org}.shortname (exact,
     *      case-insensitive). Codes survive display-name edits.
     *   2. Department NAME → fullname exact match, then the legacy LIKE
     *      fallback (kept for backwards compatibility with names that
     *      embed the org name, e.g. "Finance (Airpay)").
     *   3. Validated default path from keka_default_orgpath (default /1)
     *      — 'mapped' is false so callers can restrict it to creations.
     *
     * Phase-0A note preserved: queries {local_sentientia_org} (BizLMS
     * {local_costcenter} is legacy), guarded so stock-Moodle test DBs
     * without the table skip the mapping instead of throwing.
     *
     * @param array $emp Raw KeKa employee payload
     * @return array {path: ?string, mapped: bool}
     */
    private function resolve_org_path(array $emp): array {
        global $DB;

        $manager = $DB->get_manager();
        if (!$manager->table_exists('local_sentientia_org')) {
            return ['path' => null, 'mapped' => false];
        }

        // ASSUMPTION (live contract unverified): department may arrive as a
        // string name or an object {id, code, name}; a departmentCode
        // sibling key may also exist.
        $dept = $emp['department'] ?? null;
        $deptcode = trim((string) ($emp['departmentCode']
            ?? (is_array($dept) ? ($dept['code'] ?? '') : '')));
        $deptname = trim(is_array($dept)
            ? (string) ($dept['name'] ?? '') : (string) $dept);

        if ($deptcode !== '') {
            $org = $DB->get_record_select('local_sentientia_org',
                $DB->sql_equal('shortname', ':code', false),
                ['code' => $deptcode], 'id, path', IGNORE_MULTIPLE);
            if ($org && !empty($org->path)) {
                return ['path' => $org->path, 'mapped' => true];
            }
        }

        if ($deptname !== '') {
            $org = $DB->get_record_select('local_sentientia_org',
                $DB->sql_equal('fullname', ':name', false),
                ['name' => $deptname], 'id, path', IGNORE_MULTIPLE);
            if (!$org) {
                $org = $DB->get_record_select('local_sentientia_org',
                    $DB->sql_like('fullname', ':name'),
                    ['name' => '%' . $DB->sql_like_escape($deptname) . '%'],
                    'id, path', IGNORE_MULTIPLE);
            }
            if ($org && !empty($org->path)) {
                return ['path' => $org->path, 'mapped' => true];
            }
        }

        return ['path' => $this->default_org_path(), 'mapped' => false];
    }

    /**
     * The validated default org path for webhook-created users whose KeKa
     * department could not be mapped. Never returns a path whose org rows
     * don't exist; returns null only when the org tree is empty.
     */
    private function default_org_path(): ?string {
        global $DB;

        $default = trim((string) get_config('local_sentientia_integrations', 'keka_default_orgpath'));
        if ($default === '' || !preg_match('~^(/\d+)+$~', $default)) {
            $default = '/1';
        }

        // Exact configured node exists → use it.
        if ($DB->record_exists('local_sentientia_org', ['path' => $default])) {
            return $default;
        }

        // Fall back to the configured tenant ROOT if that exists.
        $root = (int) explode('/', trim($default, '/'))[0];
        if ($root > 0 && $DB->record_exists('local_sentientia_org', ['id' => $root, 'parentid' => 0])) {
            return '/' . $root;
        }

        // Last resort: the first root org in the tree. On customer-zero
        // that is Airpay (/1); on a Customer-N install it is whatever
        // their root tenant is — no hardcoded id.
        $roots = $DB->get_records('local_sentientia_org', ['parentid' => 0],
            'id ASC', 'id, path', 0, 1);
        if ($roots) {
            $first = reset($roots);
            return !empty($first->path) ? $first->path : '/' . $first->id;
        }

        return null;
    }

    /**
     * PASS 2 of the sync — resolve queued manager employee-numbers to
     * userids and stamp open_supervisorid. One IN-clause lookup for the
     * whole queue (pattern from hrms_importer::resolve_manager_links()).
     *
     * Unresolved managers are left NULL — they resolve on the next
     * reconciliation once the manager's own account exists.
     *
     * @param array $queue [['userid' => int, 'manager_empid' => string], ...]
     * @return int Number of supervisor links written.
     */
    public static function resolve_manager_links(array $queue): int {
        global $DB;

        $queue = array_values(array_filter($queue,
            fn($q) => !empty($q['userid']) && !empty($q['manager_empid'])));
        if (empty($queue) || !self::has_employeeid_column()) {
            return 0;
        }

        $empids = array_unique(array_column($queue, 'manager_empid'));
        [$insql, $inparams] = $DB->get_in_or_equal($empids, SQL_PARAMS_NAMED, 'emp');
        $managers = $DB->get_records_select('user',
            "deleted = 0 AND suspended = 0 AND open_employeeid $insql",
            $inparams, 'id ASC', 'id, open_employeeid');

        $byempid = [];
        foreach ($managers as $m) {
            if ((string) $m->open_employeeid !== '') {
                $byempid[$m->open_employeeid] = (int) $m->id;
            }
        }

        $linked = 0;
        foreach ($queue as $q) {
            $managerid = $byempid[$q['manager_empid']] ?? null;
            if ($managerid === null) {
                debugging("KeKa sync: manager employee-number {$q['manager_empid']}"
                    . ' not found — open_supervisorid left NULL', DEBUG_DEVELOPER);
                continue;
            }
            if ($managerid === (int) $q['userid']) {
                continue; // Never self-supervise.
            }
            $DB->set_field('user', 'open_supervisorid', $managerid, ['id' => $q['userid']]);
            $linked++;
        }

        return $linked;
    }

    /**
     * Does {user}.open_employeeid exist on this DB? (BizLMS column —
     * present on production, absent on stock Moodle.) Cached per request.
     */
    private static function has_employeeid_column(): bool {
        global $DB;
        if (self::$hasemployeeidcolumn === null) {
            // get_manager() first — it loads ddllib (xmldb_* class defs).
            $dbman = $DB->get_manager();
            $table = new \xmldb_table('user');
            $field = new \xmldb_field('open_employeeid', XMLDB_TYPE_CHAR, '255');
            self::$hasemployeeidcolumn = $dbman->field_exists($table, $field);
        }
        return self::$hasemployeeidcolumn;
    }

    /**
     * Reset per-request static caches (needed by PHPUnit, where the
     * bizlms_fixture adds columns AFTER the first schema probe).
     */
    public static function reset_static_caches(): void {
        self::$hasemployeeidcolumn = null;
    }

    // ─── Webhook dispatch ───────────────────────────────────────────────

    /**
     * Handle incoming webhook from KeKa.
     *
     * ASSUMPTION (live contract unverified): event names below come from
     * KeKa's public webhook docs and have not been verified against a live
     * KeKa tenant — see README "KeKa contract verification".
     *
     * @param string $event_type  Event type (employee.hired, employee.terminated, employee.transferred)
     * @param array  $payload     Webhook payload
     * @return array {success: bool, message: string}
     */
    public static function handle_webhook(string $event_type, array $payload): array {
        $client = new self();

        switch ($event_type) {
            case 'employee.hired':
            case 'employee.transferred':
            case 'employee.updated':
                // Fetch the authoritative record from KeKa, then upsert.
                $emp_id = trim((string) ($payload['employeeId'] ?? $payload['id'] ?? ''));
                if ($emp_id === '') {
                    return ['success' => false, 'message' => 'Missing employee ID'];
                }
                if (!$client->authenticate()) {
                    return ['success' => false, 'message' => 'Auth failed'];
                }
                $emp = $client->get_employee($emp_id);
                if (!$emp) {
                    return ['success' => false, 'message' => 'Employee not found in KeKa'];
                }
                $outcome = $client->upsert_employee($emp);
                if (!empty($outcome['manager_empid']) && !empty($outcome['userid'])) {
                    self::resolve_manager_links([[
                        'userid'        => $outcome['userid'],
                        'manager_empid' => $outcome['manager_empid'],
                    ]]);
                }
                $ok = $outcome['action'] !== 'error';
                return ['success' => $ok,
                        'message' => "{$event_type}: {$outcome['action']} — {$outcome['message']}"];

            case 'employee.terminated':
            case 'employee.exited':
                // Leaver. Payload may lack email — employeeId fallback added
                // 2026-08-07 (identity precedence: employeeid, then email).
                $emp_id = trim((string) ($payload['employeeId'] ?? $payload['id'] ?? ''));
                $email = strtolower(trim((string) ($payload['email'] ?? $payload['workEmail'] ?? '')));
                $user = self::find_existing_user($emp_id, $email);
                if (!$user) {
                    return ['success' => false, 'message' => 'User not found for termination'];
                }
                if (!$user->suspended) {
                    self::suspend_user($user);
                }
                return ['success' => true, 'message' => "Leaver suspended: userid={$user->id}"];

            default:
                return ['success' => false, 'message' => "Unknown event: {$event_type}"];
        }
    }

    // ─── HTTP plumbing ──────────────────────────────────────────────────

    /**
     * HTTP GET request to KeKa API.
     */
    private function http_get(string $endpoint, array $params = []): ?array {
        $url = $this->base_url . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->access_token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpcode === 200 && $response) ? json_decode($response, true) : null;
    }

    /**
     * HTTP POST request to KeKa API.
     */
    private function http_post(string $endpoint, array $data, string $type = 'json'): ?array {
        $url = $this->base_url . $endpoint;

        $ch = curl_init($url);
        $headers = ['Accept: application/json'];

        if ($type === 'form') {
            $body = http_build_query($data);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            $body = json_encode($data);
            $headers[] = 'Content-Type: application/json';
        }

        if ($this->access_token) {
            $headers[] = 'Authorization: Bearer ' . $this->access_token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpcode >= 200 && $httpcode < 300 && $response) ? json_decode($response, true) : null;
    }
}
