<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

/**
 * SCIM Groups mapped onto the organisation tree (ADR-030 Wave C).
 *
 *   Group.id          = local_sentientia_org.id
 *   Group.displayName = org fullname
 *   Group.externalId  = org shortname (when set)
 *   Group.members     = users whose open_path equals the org path (direct placement)
 *
 * Groups are READ-ONLY structurally — the hierarchy is a customer decision made
 * in the Organisation admin, so POST/PUT/DELETE answer 501. PATCH on members
 * moves users between orgs (add = place in this org, remove = return to the
 * tenant root), always inside the client's tenant scope.
 *
 * @package local_sentientia_api
 */
class group_resource {

    /** @var string */
    public const ORG_TABLE = 'local_sentientia_org';

    /**
     * Tenant WHERE fragment for orgs (path under the client's root).
     *
     * @param int    $tenantroot
     * @param string $alias
     * @return array{0:string,1:array}
     */
    public static function tenant_where(int $tenantroot, string $alias = 'o'): array {
        if ($tenantroot <= 0) {
            return ['1=1', []];
        }
        return ["($alias.path = :gpe OR $alias.path LIKE :gpp)", ['gpe' => '/' . $tenantroot, 'gpp' => '/' . $tenantroot . '/%']];
    }

    /**
     * Orgs visible to the client, paged.
     *
     * @param int $tenantroot
     * @param int $offset
     * @param int $limit
     * @return array{0:\stdClass[],1:int} rows, total
     */
    public static function list(int $tenantroot, int $offset, int $limit): array {
        global $DB;
        [$tsql, $tparams] = self::tenant_where($tenantroot);
        $where = "o.visible = 1 AND $tsql";
        $total = (int) $DB->count_records_sql("SELECT COUNT(o.id) FROM {" . self::ORG_TABLE . "} o WHERE $where", $tparams);
        $rows = $DB->get_records_sql("SELECT o.* FROM {" . self::ORG_TABLE . "} o WHERE $where ORDER BY o.path ASC, o.id ASC",
            $tparams, $offset, $limit);
        return [array_values($rows), $total];
    }

    /**
     * One org inside the client's scope, or null.
     *
     * @param int $tenantroot
     * @param int $orgid
     * @return \stdClass|null
     */
    public static function find(int $tenantroot, int $orgid): ?\stdClass {
        global $DB;
        if ($orgid <= 0) {
            return null;
        }
        [$tsql, $tparams] = self::tenant_where($tenantroot);
        $rec = $DB->get_record_sql("SELECT o.* FROM {" . self::ORG_TABLE . "} o WHERE o.id = :id AND $tsql",
            ['id' => $orgid] + $tparams);
        return $rec ?: null;
    }

    /**
     * Direct members of an org (users placed exactly at its path).
     *
     * @param \stdClass $org
     * @return \stdClass[] id, firstname, lastname
     */
    public static function members(\stdClass $org): array {
        global $DB, $CFG;
        if (!handler::has_open_path() || (string) $org->path === '') {
            return [];
        }
        return $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname FROM {user} u
              WHERE u.deleted = 0 AND u.mnethostid = :mnet AND u.open_path = :p ORDER BY u.id ASC",
            ['mnet' => $CFG->mnet_localhost_id, 'p' => (string) $org->path]);
    }

    /**
     * @param \stdClass $org
     * @param string    $baseurl
     * @param bool      $withmembers
     * @return array
     */
    public static function to_scim(\stdClass $org, string $baseurl, bool $withmembers = true): array {
        $out = [
            'schemas'     => [response::SCHEMA_GROUP],
            'id'          => (string) $org->id,
            'displayName' => (string) $org->fullname,
            'meta'        => [
                'resourceType' => 'Group',
                'created'      => gmdate('Y-m-d\TH:i:s\Z', (int) $org->timecreated ?: time()),
                'lastModified' => gmdate('Y-m-d\TH:i:s\Z', (int) ($org->timemodified ?: $org->timecreated) ?: time()),
                'location'     => $baseurl . '/Groups/' . (int) $org->id,
                'version'      => 'W/"' . (int) ($org->timemodified ?: $org->timecreated) . '"',
            ],
        ];
        if (!empty($org->shortname)) {
            $out['externalId'] = (string) $org->shortname;
        }
        if ($withmembers) {
            $out['members'] = [];
            foreach (self::members($org) as $m) {
                $out['members'][] = [
                    'value'   => (string) $m->id,
                    'display' => trim($m->firstname . ' ' . $m->lastname),
                    '$ref'    => $baseurl . '/Users/' . (int) $m->id,
                ];
            }
        }
        return $out;
    }

    /**
     * Parse PatchOp operations on Group.members into add/remove user id lists.
     * Supported: add/replace with value [{value:id}], remove with path
     * members[value eq "id"] or remove with value [{value:id}]. Other paths ignored.
     *
     * @param array $ops
     * @return array{add:int[],remove:int[],ignored:string[]}
     * @throws scim_exception
     */
    public static function parse_member_ops(array $ops): array {
        $r = ['add' => [], 'remove' => [], 'ignored' => []];
        foreach ($ops as $op) {
            if (!is_array($op)) {
                throw new scim_exception(400, 'Malformed PatchOp operation.', 'invalidSyntax');
            }
            $kind  = strtolower((string) ($op['op'] ?? ''));
            $path  = trim((string) ($op['path'] ?? ''));
            $value = $op['value'] ?? null;
            if (!in_array($kind, ['add', 'replace', 'remove'], true)) {
                throw new scim_exception(400, 'Unsupported PATCH op "' . $kind . '".', 'invalidSyntax');
            }
            // remove members[value eq "12"]
            if ($kind === 'remove' && preg_match('/^members\[\s*value\s+eq\s+"?(\d+)"?\s*\]$/i', $path, $m)) {
                $r['remove'][] = (int) $m[1];
                continue;
            }
            $ismembers = strtolower($path) === 'members' || ($path === '' && is_array($value) && array_key_exists('members', $value));
            if (!$ismembers) {
                if ($path !== '') {
                    $r['ignored'][] = $path;
                }
                continue;
            }
            $list = $path === '' ? ($value['members'] ?? []) : $value;
            if (is_array($list) && isset($list['value'])) {
                $list = [$list];
            }
            foreach ((array) $list as $item) {
                $id = is_array($item) ? (int) ($item['value'] ?? 0) : (int) $item;
                if ($id > 0) {
                    $r[$kind === 'remove' ? 'remove' : 'add'][] = $id;
                }
            }
        }
        $r['add'] = array_values(array_unique($r['add']));
        $r['remove'] = array_values(array_unique($r['remove']));
        return $r;
    }

    /**
     * Root path ('/N') for an org path.
     *
     * @param string $path
     * @return string
     */
    public static function root_path(string $path): string {
        $parts = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
        return isset($parts[0]) ? '/' . $parts[0] : '';
    }
}
