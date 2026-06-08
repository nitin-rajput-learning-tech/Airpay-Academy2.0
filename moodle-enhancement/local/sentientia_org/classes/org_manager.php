<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * Organization hierarchy manager.
 *
 * CRUD operations for the org tree. Replaces direct queries against
 * {local_costcenter} found throughout dashboard.php, compliance_engine,
 * analytics_manager, and homepage.php.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org_manager {

    /** @var string Primary table — use this after migration. */
    private const TABLE = 'local_sentientia_org';

    /** @var string Legacy table — fallback during transition. */
    private const LEGACY_TABLE = 'local_costcenter';

    /**
     * Get an org record by ID.
     *
     * Reads from local_sentientia_org first, falls back to local_costcenter.
     *
     * @param int $orgid
     * @return object|false
     */
    public static function get(int $orgid) {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $orgid]);
        if ($record) {
            return $record;
        }

        return self::legacy_get($orgid);
    }

    /**
     * Get org display name by ID.
     *
     * Replaces: $DB->get_field('local_costcenter', 'fullname', [...])
     * Used in dashboard.php line 282, analytics drilldown, etc.
     *
     * @param int $orgid
     * @return string
     */
    public static function get_name(int $orgid): string {
        global $DB;

        $name = $DB->get_field(self::TABLE, 'fullname', ['id' => $orgid]);
        if ($name !== false) {
            return $name;
        }

        // Legacy fallback.
        $name = self::legacy_field($orgid, 'fullname');
        return $name ?: '';
    }

    /**
     * Get org by hierarchical path.
     *
     * Replaces: $DB->get_field('local_costcenter', 'fullname', ['path' => $toporg])
     *
     * @param string $path  e.g. "/1" or "/77"
     * @return object|false
     */
    public static function get_by_path(string $path) {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['path' => $path]);
        if ($record) {
            return $record;
        }

        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return $DB->get_record(self::LEGACY_TABLE, ['path' => $path]);
        }

        return false;
    }

    /**
     * Get name by path.
     *
     * Shorthand for dashboard tenant scope label.
     *
     * @param string $path
     * @return string
     */
    public static function get_name_by_path(string $path): string {
        $org = self::get_by_path($path);
        return $org->fullname ?? '';
    }

    /**
     * Get all child orgs under a parent.
     *
     * Replaces the compliance_engine pattern of querying children by parentid.
     *
     * @param int  $parentid
     * @param bool $visibleonly
     * @return array
     */
    public static function get_children(int $parentid, bool $visibleonly = true): array {
        global $DB;

        $conditions = ['parentid' => $parentid];
        if ($visibleonly) {
            $conditions['visible'] = 1;
        }

        $records = $DB->get_records(self::TABLE, $conditions, 'sortorder ASC');
        if (!empty($records)) {
            return $records;
        }

        // Legacy fallback.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return $DB->get_records(self::LEGACY_TABLE, $conditions, 'sortorder ASC');
        }

        return [];
    }

    /**
     * Get all orgs under a path prefix (descendants).
     *
     * Replaces the pattern: WHERE path LIKE '/77/%'
     * Used in homepage, catalog, compliance for tenant scoping.
     *
     * @param string $pathprefix  e.g. "/77"
     * @return array  Array of org records
     */
    public static function get_descendants(string $pathprefix): array {
        global $DB;

        $records = $DB->get_records_select(
            self::TABLE,
            "path LIKE :pathlike",
            ['pathlike' => $pathprefix . '/%']
        );

        if (!empty($records)) {
            return $records;
        }

        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return $DB->get_records_select(
                self::LEGACY_TABLE,
                "path LIKE :pathlike",
                ['pathlike' => $pathprefix . '/%']
            );
        }

        return [];
    }

    /**
     * Get all root-level tenants (depth=1, parentid=0).
     *
     * @return array
     */
    public static function get_tenants(): array {
        global $DB;

        $records = $DB->get_records(self::TABLE, ['depth' => 1], 'sortorder ASC');
        if (!empty($records)) {
            return $records;
        }

        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            return $DB->get_records(self::LEGACY_TABLE, ['parentid' => 0], 'id ASC');
        }

        return [];
    }

    /**
     * Get all org IDs under a path (for IN clause queries).
     *
     * @param string $pathprefix
     * @return array  Array of int IDs
     */
    public static function get_descendant_ids(string $pathprefix): array {
        $descendants = self::get_descendants($pathprefix);
        return array_map(function ($o) {
            return (int) $o->id;
        }, $descendants);
    }

    /**
     * W1-1 BizLMS parity (2026-05-15) — turn the client-side cascade
     * filter state into a single SQL fragment + params for a WS WHERE
     * clause.
     *
     * The 5-level cascade can have up to 5 non-zero values, but only the
     * DEEPEST one matters for filtering — that's the smallest subtree we
     * want to scope the result set to. We resolve that org's full path
     * once and then match either the path itself OR any descendant.
     *
     * Each plugin's WS just does:
     *     [$orgsql, $orgargs] =
     *         \local_sentientia_org\org_manager::cascade_where_sql(
     *             $client_filters, 'c', 'open_path');
     *     if ($orgsql !== '') {
     *         $where[] = $orgsql;
     *         $sqlparams = array_merge($sqlparams, $orgargs);
     *     }
     *
     * @param array  $client_filters    decoded JSON from `filters` param
     * @param string $tablealias        e.g. 'c' for {course} or 'u' for {user}
     * @param string $pathcolumn        column on that table that holds the
     *                                  hierarchy path (default: open_path)
     * @return array  [$sqlfragment, $params]  empty string if no cascade set
     */
    public static function cascade_where_sql(array $client_filters,
            string $tablealias, string $pathcolumn = 'open_path'): array {
        global $DB;

        // Find the deepest non-zero level.
        $deepest = 0;
        for ($lvl = 5; $lvl >= 1; $lvl--) {
            $val = (int) ($client_filters['org_l' . $lvl] ?? 0);
            if ($val > 0) { $deepest = $val; break; }
        }
        if ($deepest === 0) {
            return ['', []];
        }

        $org = $DB->get_record(self::TABLE, ['id' => $deepest], 'path');
        if (!$org || empty($org->path)) {
            return ['', []];
        }

        // Use unique param names so callers can pass alongside theirs.
        $exactkey  = 'orgcascade_exact_' . $tablealias;
        $prefixkey = 'orgcascade_prefix_' . $tablealias;
        $sql = "({$tablealias}.{$pathcolumn} = :{$exactkey}"
             . " OR {$tablealias}.{$pathcolumn} LIKE :{$prefixkey})";
        $args = [
            $exactkey  => rtrim($org->path, '/'),
            $prefixkey => $DB->sql_like_escape(rtrim($org->path, '/') . '/') . '%',
        ];
        return [$sql, $args];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Write operations — CRUD (added April 2026)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Create a new org node.
     *
     * Computes path/depth from parentid automatically. parentid=0 creates a
     * top-level tenant. Non-zero parentid inherits its parent's path and
     * depth+1.
     *
     * @param object $data  Must have ->fullname. May have shortname, description,
     *                      parentid, visible, sortorder, brand_color, button_color,
     *                      hover_color, theme_scheme.
     * @return int  New org ID.
     * @throws \moodle_exception
     */
    public static function create(object $data): int {
        global $DB;

        if (empty($data->fullname)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_org');
        }

        $parentid = (int) ($data->parentid ?? 0);
        $depth = 1;
        $parent_path = '';

        if ($parentid > 0) {
            $parent = $DB->get_record(self::TABLE, ['id' => $parentid], 'id, path, depth');
            if (!$parent) {
                throw new \moodle_exception('invalidparent', 'local_sentientia_org');
            }
            $depth = ((int) $parent->depth) + 1;
            $parent_path = $parent->path;
        }

        $record = (object) [
            'fullname'     => trim($data->fullname),
            'shortname'    => !empty($data->shortname) ? trim($data->shortname) : '',
            'description'  => $data->description ?? '',
            'parentid'     => $parentid,
            'depth'        => $depth,
            'visible'      => (int) ($data->visible ?? 1),
            'sortorder'    => (int) ($data->sortorder ?? 0),
            'brand_color'  => $data->brand_color ?? null,
            'button_color' => $data->button_color ?? null,
            'hover_color'  => $data->hover_color ?? null,
            'theme_scheme' => $data->theme_scheme ?? null,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];

        // Insert without path first (we need the new ID to compute it).
        $newid = $DB->insert_record(self::TABLE, $record);

        // Now patch path = parent_path + / + newid (or /newid for tenant).
        $path = $parentid > 0 ? $parent_path . '/' . $newid : '/' . $newid;
        $DB->set_field(self::TABLE, 'path', $path, ['id' => $newid]);

        return $newid;
    }

    /**
     * Update an existing org. Cannot change parentid or path through this
     * method — use move_branch() for that to recompute descendants.
     *
     * @param int    $orgid
     * @param object $data
     * @return bool
     */
    public static function update(int $orgid, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $orgid], '*', MUST_EXIST);

        $record = (object) [
            'id' => $orgid,
            'timemodified' => time(),
        ];

        if (isset($data->fullname))     $record->fullname    = trim($data->fullname);
        if (isset($data->shortname))    $record->shortname   = trim($data->shortname);
        if (isset($data->description))  $record->description = $data->description;
        if (isset($data->visible))      $record->visible     = (int) $data->visible;
        if (isset($data->sortorder))    $record->sortorder   = (int) $data->sortorder;
        if (isset($data->brand_color))  $record->brand_color = $data->brand_color;
        if (isset($data->button_color)) $record->button_color = $data->button_color;
        if (isset($data->hover_color))  $record->hover_color  = $data->hover_color;
        if (isset($data->theme_scheme)) $record->theme_scheme = $data->theme_scheme;

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * Toggle org visibility (active <-> hidden).
     *
     * @param int       $orgid
     * @param bool|null $visible  null = toggle, true = show, false = hide
     * @return bool  New visibility state.
     */
    public static function toggle_visibility(int $orgid, ?bool $visible = null): bool {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['id' => $orgid], 'id, visible', MUST_EXIST);
        $newstate = $visible ?? !((bool) $existing->visible);
        $DB->update_record(self::TABLE, (object) [
            'id' => $orgid,
            'visible' => $newstate ? 1 : 0,
            'timemodified' => time(),
        ]);
        return $newstate;
    }

    /**
     * Count users assigned to this org (open_path matches the org's path
     * prefix, including descendants).
     *
     * @param int $orgid
     * @return int
     */
    public static function count_users(int $orgid): int {
        global $DB;
        $org = $DB->get_record(self::TABLE, ['id' => $orgid], 'id, path');
        if (!$org || empty($org->path)) {
            return 0;
        }
        // C2 fix: match the org itself OR its descendants (slash-bounded
        // + sql_like_escape) — '/1' must NOT match '/10' or '/177'.
        $like = $DB->sql_like_escape(rtrim($org->path, '/') . '/') . '%';
        return $DB->count_records_select('user',
            'deleted = 0 AND (open_path = :exact OR open_path LIKE :p)',
            ['exact' => $org->path, 'p' => $like]);
    }

    /**
     * Count direct + indirect child orgs.
     *
     * @param int $orgid
     * @return int
     */
    public static function count_descendants(int $orgid): int {
        global $DB;
        $org = $DB->get_record(self::TABLE, ['id' => $orgid], 'id, path');
        if (!$org || empty($org->path)) {
            return 0;
        }
        // C2 fix: sql_like_escape the prefix; the / boundary was already
        // present and correct.
        return $DB->count_records_select(self::TABLE,
            "path LIKE :p",
            ['p' => $DB->sql_like_escape($org->path . '/') . '%']);
    }

    /**
     * Delete an org node. Refuses if it has descendants or users assigned.
     *
     * Tenants (depth=1) are not deletable through this method — use
     * toggle_visibility() to hide them instead. Removing a tenant would
     * orphan all its sub-orgs and users.
     *
     * @param int $orgid
     * @return bool
     * @throws \moodle_exception when blocked.
     */
    public static function delete(int $orgid): bool {
        global $DB;

        // H2 fix: wrap in a transaction with SELECT ... FOR UPDATE so a
        // concurrent create() under this parent can't insert a child
        // between the count_descendants check and the delete.
        $tx = $DB->start_delegated_transaction();
        try {
            $table = self::TABLE;
            $org = $DB->get_record_sql(
                "SELECT * FROM {{$table}} WHERE id = :id FOR UPDATE",
                ['id' => $orgid]);
            if (!$org) {
                throw new \moodle_exception('orgnotfound', 'local_sentientia_org');
            }

            if ((int) $org->depth === 1) {
                throw new \moodle_exception('cannotdeletetenant', 'local_sentientia_org');
            }
            if (self::count_descendants($orgid) > 0) {
                throw new \moodle_exception('orghaschildren', 'local_sentientia_org');
            }
            if (self::count_users($orgid) > 0) {
                throw new \moodle_exception('orghasusers', 'local_sentientia_org');
            }

            $DB->delete_records(self::TABLE, ['id' => $orgid]);
            $tx->allow_commit();
            return true;
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        return false;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Legacy helpers
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Fallback read from legacy BizLMS table.
     *
     * @param int $orgid
     * @return object|false
     */
    private static function legacy_get(int $orgid) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::LEGACY_TABLE)) {
            return false;
        }

        return $DB->get_record(self::LEGACY_TABLE, ['id' => $orgid]);
    }

    /**
     * Fallback field read from legacy table.
     *
     * @param int    $orgid
     * @param string $field
     * @return mixed|false
     */
    private static function legacy_field(int $orgid, string $field) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::LEGACY_TABLE)) {
            return false;
        }

        return $DB->get_field(self::LEGACY_TABLE, $field, ['id' => $orgid]);
    }
}
