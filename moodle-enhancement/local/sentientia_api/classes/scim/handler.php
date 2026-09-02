<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_api\request_log;
use local_sentientia_users\user_manager;

/**
 * SCIM 2.0 request handler — Users resource + discovery (ADR-030 Wave B).
 *
 * Transport-neutral: scim/v2.php feeds it (method, path, query, body, auth
 * header) and emits whatever {status, body, headers} comes back, so the whole
 * protocol surface is unit-testable without HTTP.
 *
 * Gate order (fail closed): bearer client → feature flags for the client's
 * (customer, tenant) → per-client rate limit → route. Every operation is
 * tenant-scoped by the client's tenant root via open_path; a user outside the
 * scope is a 404 (existence is never leaked). All writes delegate to the
 * user_manager facade so events fire and the open_* field discipline holds.
 * DELETE is a soft deactivation (suspend + kill sessions), never a hard delete.
 *
 * @package local_sentientia_api
 */
class handler {

    /** @var string */
    public const FLAG_MASTER = 'sentientia.api.enabled';

    /** @var string */
    public const FLAG_SCIM = 'sentientia.api.scim.enabled';

    /** @var int */
    public const MAX_COUNT = 200;

    /** @var string */
    private string $baseurl;

    /** @var bool|null */
    private static ?bool $hasopenpath = null;

    /**
     * @param string $baseurl Absolute URL of scim/v2.php (no trailing slash)
     */
    public function __construct(string $baseurl) {
        $this->baseurl = rtrim($baseurl, '/');
    }

    /**
     * @param string      $method     HTTP method
     * @param string      $path       Path after v2.php, e.g. /Users/12
     * @param array       $query      filter, startIndex, count
     * @param string|null $rawbody    Request body
     * @param string      $authheader Authorization header value
     * @return array{status:int,body:?array,headers:array}
     */
    public function handle(string $method, string $path, array $query, ?string $rawbody, string $authheader): array {
        $method = strtoupper($method);
        $path   = '/' . trim($path, '/');
        $tenant = 0;
        try {
            $client = authenticator::authenticate($authheader);
            if (!$client) {
                $resp = response::error(401, get_string('scim_unauthorized', 'local_sentientia_api'));
                $resp['headers']['WWW-Authenticate'] = 'Bearer realm="Sentientia SCIM"';
                return $this->finish($resp, $method, $path, 0);
            }
            $tenant = (int) $client->costcenterid;
            if (!$this->flags_on($client)) {
                return $this->finish(response::error(503, get_string('scim_disabled', 'local_sentientia_api')), $method, $path, $tenant);
            }
            client::rate_check($client);
            $resp = $this->route($client, $method, $path, $query, $rawbody);
        } catch (scim_exception $e) {
            $resp = response::from_exception($e);
        } catch (\moodle_exception $e) {
            $resp = $this->map_moodle_exception($e);
        } catch (\Throwable $e) {
            debugging('sentientia_api scim: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $resp = response::error(500, get_string('scim_internal', 'local_sentientia_api'));
        }
        return $this->finish($resp, $method, $path, $tenant);
    }

    // ── Routing ─────────────────────────────────────────────────────────

    /**
     * @param \stdClass   $client
     * @param string      $method
     * @param string      $path
     * @param array       $query
     * @param string|null $rawbody
     * @return array
     */
    private function route(\stdClass $client, string $method, string $path, array $query, ?string $rawbody): array {
        $seg = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
        $res = strtolower($seg[0] ?? '');
        $id  = $seg[1] ?? null;

        if ($method === 'GET' && $res === 'serviceproviderconfig') {
            return response::service_provider_config($this->baseurl);
        }
        if ($method === 'GET' && $res === 'resourcetypes') {
            return response::resource_types($this->baseurl);
        }
        if ($method === 'GET' && $res === 'schemas') {
            return response::schemas($this->baseurl);
        }
        if ($res !== 'users') {
            throw new scim_exception(404, get_string('scim_notfound', 'local_sentientia_api'));
        }

        if ($id === null) {
            switch ($method) {
                case 'GET':
                    return $this->list_users($client, $query);
                case 'POST':
                    return $this->create_user($client, $this->json($rawbody));
            }
            throw new scim_exception(405, 'Method not allowed.');
        }

        $user = $this->find_user($client, (int) $id);
        if (!$user) {
            throw new scim_exception(404, get_string('scim_notfound', 'local_sentientia_api'));
        }
        switch ($method) {
            case 'GET':
                return $this->resource_response(200, $client, $user);
            case 'PUT':
                return $this->replace_user($client, $user, $this->json($rawbody));
            case 'PATCH':
                return $this->patch_user($client, $user, $this->json($rawbody));
            case 'DELETE':
                return $this->deactivate_user($client, $user);
        }
        throw new scim_exception(405, 'Method not allowed.');
    }

    // ── Operations ──────────────────────────────────────────────────────

    /**
     * @param \stdClass $client
     * @param array     $query
     * @return array
     */
    private function list_users(\stdClass $client, array $query): array {
        global $DB;
        [$tsql, $tparams] = $this->tenant_where($client, 'u');
        $where  = "u.deleted = 0 AND u.mnethostid = :mnet AND $tsql";
        $params = ['mnet' => (int) get_config('core', 'mnet_localhost_id')] + $tparams;

        $f = filter::parse((string) ($query['filter'] ?? ''));
        if ($f) {
            switch ($f['attr']) {
                case 'username':
                    $where .= ' AND u.username = :fv';
                    $params['fv'] = \core_text::strtolower($f['value']);
                    break;
                case 'email':
                    $where .= ' AND ' . $DB->sql_equal('u.email', ':fv', false);
                    $params['fv'] = $f['value'];
                    break;
                case 'id':
                    $where .= ' AND u.id = :fv';
                    $params['fv'] = (int) $f['value'];
                    break;
                case 'externalid':
                    $uid = mapper::userid_for((int) $client->id, $f['value']);
                    $where .= ' AND u.id = :fv';
                    $params['fv'] = $uid ?? -1;
                    break;
            }
        }

        $start = max(1, (int) ($query['startIndex'] ?? 1));
        $count = (int) ($query['count'] ?? 100);
        $count = $count <= 0 ? 100 : min($count, self::MAX_COUNT);

        $total = (int) $DB->count_records_sql("SELECT COUNT(u.id) FROM {user} u WHERE $where", $params);
        $rows  = $DB->get_records_sql("SELECT u.* FROM {user} u WHERE $where ORDER BY u.id ASC", $params, $start - 1, $count);

        $ext = mapper::externalids_for((int) $client->id, array_map('intval', array_keys($rows)));
        $resources = [];
        foreach ($rows as $u) {
            $resources[] = user_resource::to_scim($u, $ext[(int) $u->id] ?? null, $this->baseurl);
        }
        return response::list($resources, $total, $start, count($resources));
    }

    /**
     * @param \stdClass $client
     * @param array     $body
     * @return array
     */
    private function create_user(\stdClass $client, array $body): array {
        global $DB, $CFG;
        $in = user_resource::from_scim($body, true);
        $username = \core_text::strtolower(trim($in['username']));

        // Same externalId already provisioned by this client -> update instead of duplicating.
        $existingid = $in['externalid'] !== null ? mapper::userid_for((int) $client->id, $in['externalid']) : null;
        if ($existingid === null) {
            $byname = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
            if ($byname) {
                $scoped = $this->find_user($client, (int) $byname->id);
                if ($scoped && !empty($scoped->suspended)) {
                    $existingid = (int) $scoped->id;   // Re-provision a previously deactivated account.
                } else {
                    throw new scim_exception(409, get_string('scim_conflict_username', 'local_sentientia_api'), 'uniqueness');
                }
            }
        }
        if ($existingid !== null) {
            $user = $this->find_user($client, $existingid);
            if (!$user) {
                throw new scim_exception(409, get_string('scim_conflict_username', 'local_sentientia_api'), 'uniqueness');
            }
            $in['active'] = $in['active'] ?? true;
            $this->apply_changes($client, $user, $in);
            return $this->resource_response(200, $client, $this->reload($user->id));
        }

        $data = (object) [
            'username'  => $username,
            'email'     => $in['email'],
            'firstname' => ($in['firstname'] !== null && $in['firstname'] !== '')
                ? $in['firstname'] : ((strstr($username, '@', true) ?: $username)),
            'lastname'  => ($in['lastname'] !== null && $in['lastname'] !== '') ? $in['lastname'] : '-',
            'auth'      => (string) $client->auth,
        ];
        if ((int) $client->costcenterid > 0 && self::has_open_path()) {
            $data->open_path = '/' . (int) $client->costcenterid;
            $data->open_costcenterid = (int) $client->costcenterid;
        }
        $userid = user_manager::create($data);
        if ($in['active'] === false) {
            user_manager::suspend($userid, true);
        }
        if ($in['externalid'] !== null && $in['externalid'] !== '') {
            mapper::set((int) $client->id, $userid, $in['externalid']);
        }
        $resp = $this->resource_response(201, $client, $this->reload($userid));
        $resp['headers']['Location'] = $this->baseurl . '/Users/' . $userid;
        return $resp;
    }

    /**
     * @param \stdClass $client
     * @param \stdClass $user
     * @param array     $body
     * @return array
     */
    private function replace_user(\stdClass $client, \stdClass $user, array $body): array {
        $in = user_resource::from_scim($body, false);
        $this->apply_changes($client, $user, $in);
        return $this->resource_response(200, $client, $this->reload($user->id));
    }

    /**
     * @param \stdClass $client
     * @param \stdClass $user
     * @param array     $body
     * @return array
     */
    private function patch_user(\stdClass $client, \stdClass $user, array $body): array {
        $ops = $body['Operations'] ?? null;
        if (!is_array($ops) || !$ops) {
            throw new scim_exception(400, 'PatchOp requires a non-empty Operations array.', 'invalidSyntax');
        }
        $changes = user_resource::apply_patch($ops);
        $this->apply_changes($client, $user, $changes);
        return $this->resource_response(200, $client, $this->reload($user->id));
    }

    /**
     * SCIM DELETE = deactivate: suspend, kill sessions, drop the externalId
     * mapping. The account and its learning history are retained.
     *
     * @param \stdClass $client
     * @param \stdClass $user
     * @return array
     */
    private function deactivate_user(\stdClass $client, \stdClass $user): array {
        user_manager::suspend((int) $user->id, true);
        mapper::unmap_user((int) $client->id, (int) $user->id);
        return response::ok(204, null);
    }

    /**
     * Apply normalised changes (nulls = untouched) through user_manager.
     *
     * @param \stdClass $client
     * @param \stdClass $user
     * @param array     $in
     * @return void
     */
    private function apply_changes(\stdClass $client, \stdClass $user, array $in): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $upd = new \stdClass();
        foreach (['email', 'firstname', 'lastname'] as $f) {
            if (isset($in[$f]) && $in[$f] !== '' && (string) $in[$f] !== (string) $user->$f) {
                $upd->$f = $in[$f];
            }
        }
        if (count((array) $upd)) {
            user_manager::update((int) $user->id, $upd);
        }

        if (isset($in['username']) && $in['username'] !== '') {
            $new = \core_text::strtolower(trim($in['username']));
            if ($new !== (string) $user->username) {
                if ($DB->record_exists_select('user', 'username = :u AND mnethostid = :m AND id <> :id',
                        ['u' => $new, 'm' => $CFG->mnet_localhost_id, 'id' => $user->id])) {
                    throw new scim_exception(409, get_string('scim_conflict_username', 'local_sentientia_api'), 'uniqueness');
                }
                user_update_user((object) ['id' => (int) $user->id, 'username' => $new], false, true);
            }
        }

        if (isset($in['active'])) {
            $wantsuspended = !$in['active'];
            if ((bool) $user->suspended !== $wantsuspended) {
                user_manager::suspend((int) $user->id, $wantsuspended);
            }
        }

        if (isset($in['externalid'])) {
            mapper::set((int) $client->id, (int) $user->id, (string) $in['externalid']);
        }
    }

    // ── Scoping + helpers ───────────────────────────────────────────────

    /**
     * Tenant WHERE fragment for the client's scope (no $USER involved).
     *
     * @param \stdClass $client
     * @param string    $alias
     * @return array{0:string,1:array}
     */
    private function tenant_where(\stdClass $client, string $alias): array {
        $root = (int) $client->costcenterid;
        if ($root <= 0 || !self::has_open_path()) {
            return ['1=1', []];
        }
        $col = "$alias.open_path";
        return ["($col = :tpe OR $col LIKE :tpp)", ['tpe' => '/' . $root, 'tpp' => '/' . $root . '/%']];
    }

    /**
     * Fetch a live user inside the client's tenant scope, or null.
     *
     * @param \stdClass $client
     * @param int       $id
     * @return \stdClass|null
     */
    private function find_user(\stdClass $client, int $id): ?\stdClass {
        global $DB, $CFG;
        if ($id <= 0) {
            return null;
        }
        [$tsql, $tparams] = $this->tenant_where($client, 'u');
        $rec = $DB->get_record_sql(
            "SELECT u.* FROM {user} u WHERE u.id = :id AND u.deleted = 0 AND u.mnethostid = :mnet AND $tsql",
            ['id' => $id, 'mnet' => $CFG->mnet_localhost_id] + $tparams);
        return $rec ?: null;
    }

    /**
     * @param int $userid
     * @return \stdClass
     */
    private function reload(int $userid): \stdClass {
        global $DB;
        return $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    }

    /**
     * @param int       $status
     * @param \stdClass $client
     * @param \stdClass $user
     * @return array
     */
    private function resource_response(int $status, \stdClass $client, \stdClass $user): array {
        $body = user_resource::to_scim($user, mapper::externalid_for((int) $client->id, (int) $user->id), $this->baseurl);
        return response::ok($status, $body, ['ETag' => $body['meta']['version']]);
    }

    /**
     * @param string|null $raw
     * @return array
     */
    private function json(?string $raw): array {
        if ($raw === null || trim($raw) === '') {
            throw new scim_exception(400, get_string('scim_bad_json', 'local_sentientia_api'), 'invalidSyntax');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new scim_exception(400, get_string('scim_bad_json', 'local_sentientia_api'), 'invalidSyntax');
        }
        return $data;
    }

    /**
     * @param \stdClass $client
     * @return bool
     */
    private function flags_on(\stdClass $client): bool {
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return false;
        }
        $ff = '\local_sentientia_platform\feature_flags';
        $customer = (int) $client->customerid;
        $tenant   = (int) $client->costcenterid;
        return $ff::is_enabled_for(self::FLAG_MASTER, $customer, $tenant)
            && $ff::is_enabled_for(self::FLAG_SCIM, $customer, $tenant);
    }

    /**
     * user_manager's uniqueness errors become SCIM 409s; everything else 400.
     *
     * @param \moodle_exception $e
     * @return array
     */
    private function map_moodle_exception(\moodle_exception $e): array {
        switch ($e->errorcode) {
            case 'usernametaken':
                return response::error(409, get_string('scim_conflict_username', 'local_sentientia_api'), 'uniqueness');
            case 'emailtaken':
                return response::error(409, get_string('scim_conflict_email', 'local_sentientia_api'), 'uniqueness');
            case 'ratelimited':
                return response::error(429, $e->getMessage(), 'tooMany');
            default:
                return response::error(400, $e->getMessage(), 'invalidValue');
        }
    }

    /**
     * Does {user} carry the BizLMS open_path column? Cached per request.
     *
     * @return bool
     */
    public static function has_open_path(): bool {
        global $DB;
        if (self::$hasopenpath === null) {
            $dbman = $DB->get_manager();
            self::$hasopenpath = $dbman->field_exists(new \xmldb_table('user'), new \xmldb_field('open_path'));
        }
        return self::$hasopenpath;
    }

    /** Reset caches (PHPUnit). */
    public static function reset_static_caches(): void {
        self::$hasopenpath = null;
    }

    /**
     * Log (no PII: method + path + status) and return.
     *
     * @param array  $resp
     * @param string $method
     * @param string $path
     * @param int    $tenant
     * @return array
     */
    private function finish(array $resp, string $method, string $path, int $tenant): array {
        try {
            request_log::record(0, $tenant, $method . ' ' . preg_replace('/\/\d+/', '/{id}', $path),
                $method === 'GET' ? 'GET' : 'POST', (int) $resp['status'], 'scim2');
        } catch (\Throwable $ignored) {
            debugging('sentientia_api scim: request log failed: ' . $ignored->getMessage(), DEBUG_DEVELOPER);
        }
        return $resp;
    }
}
