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

namespace local_airpay_org;

defined('MOODLE_INTERNAL') || die();

/**
 * Access library — fork of \local_costcenter\lib\accesslib.
 *
 * Provides role detection, category-context queries, and costcenter-path
 * resolution. All methods are static for drop-in replacement.
 *
 * API contract preserved from BizLMS so core_renderer.php can switch
 * from \local_costcenter\lib\accesslib to \local_airpay_org\accesslib
 * with a namespace change only.
 *
 * @package    local_airpay_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accesslib {

    /**
     * Match this path AND all DESCENDANTS (e.g. /1 matches /1, /1/2, /1/2/3).
     */
    public const LOWER_AND_SAME = 'lowerandsamepath';

    /**
     * Match this path AND all ANCESTORS (e.g. /1/2/3 also matches /1/2 and /1).
     */
    public const UPPER_AND_SAME = 'upperandsamepath';

    /**
     * Get all roles assigned to a user at category-level contexts.
     *
     * Returns role assignments at CONTEXT_COURSECAT (level 40) which is
     * where BizLMS places org-level admin roles. Each result includes
     * the role ID, context, path, depth, role name, and shortname.
     *
     * Fork of: \local_costcenter\lib\accesslib::get_user_roles_in_catgeorycontexts()
     * Note: original BizLMS method had a typo ("catgeory") — we preserve it
     * in the alias for backward compat but use the correct spelling internally.
     *
     * @param int $userid
     * @return array Array of role assignment objects
     */
    public static function get_user_roles_in_category_contexts(int $userid): array {
        global $DB;

        $sql = "SELECT ra.id AS raid, ra.roleid, ra.contextid, r.shortname AS rolecode,
                       r.name AS rolename, ctx.path, ctx.depth, ctx.instanceid
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND ctx.contextlevel = :catlevel
              ORDER BY ctx.depth ASC, r.sortorder ASC";

        $records = $DB->get_records_sql($sql, [
            'userid'   => $userid,
            'catlevel' => CONTEXT_COURSECAT,
        ]);

        return array_values($records);
    }

    /**
     * Backward-compat alias preserving the BizLMS typo.
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_roles_in_catgeorycontexts(int $userid): array {
        return self::get_user_roles_in_category_contexts($userid);
    }

    /**
     * Get a single field from a course category record.
     *
     * Fork of: \local_costcenter\lib\accesslib::get_category_info()
     *
     * @param int    $categoryid  The course_categories.id
     * @param string $field       Field to return (e.g. 'name', 'path')
     * @return mixed|false  The field value, or false if not found
     */
    public static function get_category_info(int $categoryid, string $field = 'name') {
        global $DB;

        $allowed = ['id', 'name', 'path', 'parent', 'depth', 'idnumber',
                     'description', 'visible', 'sortorder'];
        if (!in_array($field, $allowed, true)) {
            $field = 'name';
        }

        return $DB->get_field('course_categories', $field, ['id' => $categoryid]);
    }

    /**
     * Get the costcenter/org path associated with a context.
     *
     * Resolves a category context to the org hierarchy path by walking
     * up to the root category and matching it against the org table.
     *
     * Fork of: \local_costcenter\lib\accesslib::get_costcenterpath_context()
     *
     * @param \context $context  A category-level context
     * @return string  The org path (e.g. "/1/2") or empty string
     */
    public static function get_costcenterpath_context(\context $context): string {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSECAT) {
            return '';
        }

        $categoryid = $context->instanceid;
        $categorypath = $DB->get_field('course_categories', 'path', ['id' => $categoryid]);
        if (empty($categorypath)) {
            return '';
        }

        // Category path is like "/1/5/12" — the root category maps to a costcenter.
        $catids = array_values(array_filter(explode('/', $categorypath)));
        if (empty($catids)) {
            return '';
        }

        // Try to find matching org by root category ID.
        $rootcatid = (int) $catids[0];
        $org = $DB->get_record('local_airpay_org', ['id' => $rootcatid]);

        // Fallback: try local_costcenter during transition.
        if (!$org) {
            $org = self::get_costcenter_record($rootcatid);
        }

        return $org->path ?? '';
    }

    /**
     * Get system context (convenience wrapper).
     *
     * Fork of: \local_costcenter\lib\accesslib::get_module_context()
     *
     * @return \context_system
     */
    public static function get_module_context(): \context_system {
        return \context_system::instance();
    }

    /**
     * Get full org/costcenter record by ID.
     *
     * Reads from local_airpay_org first, falls back to local_costcenter
     * during the transition period.
     *
     * Fork of: \local_costcenter\lib\accesslib::get_costcenter_info()
     *
     * @param int $orgid  The org/costcenter ID
     * @return object|false  The org record or false
     */
    public static function get_costcenter_info(int $orgid) {
        global $DB;

        $record = $DB->get_record('local_airpay_org', ['id' => $orgid]);
        if ($record) {
            return $record;
        }

        return self::get_costcenter_record($orgid);
    }

    /**
     * Set user role switch state (stores in session).
     *
     * Fork of: \local_costcenter\lib\accesslib::set_user_role_switch()
     *
     * @param int $roleid     The role to switch to
     * @param int $contextid  The context for the role
     * @return void
     */
    public static function set_user_role_switch(int $roleid, int $contextid): void {
        global $USER, $SESSION;

        $SESSION->airpay_switchrole = (object) [
            'roleid'    => $roleid,
            'contextid' => $contextid,
        ];

        $USER->useraccess['currentroleinfo'] = [
            'roleid'    => $roleid,
            'contextid' => $contextid,
        ];
    }

    /**
     * Get the costcenter paths from the current user's role switch state.
     *
     * Fork of: \local_costcenter\lib\accesslib::get_user_role_switch_path()
     * Used in coursedetails.php to restrict course visibility by org.
     *
     * @return array  Array of costcenter path strings, or empty
     */
    public static function get_user_role_switch_path(): array {
        global $USER;

        if (empty($USER->useraccess['currentroleinfo']['contextinfo'])) {
            return [];
        }

        $paths = [];
        foreach ($USER->useraccess['currentroleinfo']['contextinfo'] as $info) {
            if (!empty($info['costcenterpath'])) {
                $paths[] = $info['costcenterpath'];
            }
        }

        return $paths;
    }

    /**
     * Build a SQL concat expression for costcenter path filtering.
     *
     * Fork of: \local_costcenter\lib\accesslib::get_costcenter_path_field_concatsql()
     * Used in coursedetails.php to build WHERE clauses for tenant-scoped queries.
     *
     * @param string $columnname  Column to filter (default 'path')
     * @param string|null $costcenterpath  Override path (null = auto from user)
     * @param string $datatype  Filter type: 'lowerandsamepath', 'samepath', etc.
     * @return string  SQL fragment for WHERE clause, or empty string
     */
    public static function get_costcenter_path_field_concatsql(
        string $columnname = 'path',
        ?string $costcenterpath = null,
        string $datatype = 'lowerandsamepath'
    ): string {
        // DEPRECATED: returns raw SQL string for backward compat.
        // New code should use get_costcenter_path_sql() which returns params.
        [$sql, ] = self::get_costcenter_path_sql($columnname, $costcenterpath, $datatype);
        return $sql;
    }

    /**
     * Build parameterised SQL for costcenter path filtering.
     *
     * Returns [$sql_fragment, $params] for safe use in queries.
     * Replaces the unsafe string-concat version.
     *
     * @param string      $columnname
     * @param string|null $costcenterpath
     * @param string      $datatype
     * @return array  [string $sql, array $params]
     */
    public static function get_costcenter_path_sql(
        string $columnname = 'path',
        ?string $costcenterpath = null,
        string $datatype = 'lowerandsamepath'
    ): array {
        if ($costcenterpath === null) {
            $paths = self::get_user_role_switch_path();
            $costcenterpath = $paths[0] ?? '';
        }

        if (empty($costcenterpath)) {
            return ['', []];
        }

        $uid = uniqid('ccp_');
        if ($datatype === 'lowerandsamepath') {
            $sql = " AND ({$columnname} = :{$uid}_exact OR {$columnname} LIKE :{$uid}_like)";
            $params = [
                "{$uid}_exact" => $costcenterpath,
                "{$uid}_like"  => $costcenterpath . '/%',
            ];
        } else {
            $sql = " AND {$columnname} LIKE :{$uid}_like";
            $params = ["{$uid}_like" => $costcenterpath . '%'];
        }

        return [$sql, $params];
    }

    /**
     * Check if user can manage multiple organizations (siteadmin-tier).
     * Checks both old (local/costcenter:*) and new (local/airpay_org:*) caps.
     *
     * @param \context|null $context
     * @return bool
     */
    public static function can_manage_multi(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        return is_siteadmin()
            || has_capability('local/airpay_org:manage_multiorganizations', $context)
            || has_capability('local/costcenter:manage_multiorganizations', $context);
    }

    /**
     * Check if user can view organizations.
     *
     * @param \context|null $context
     * @return bool
     */
    public static function can_view(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        return has_capability('local/airpay_org:view', $context)
            || has_capability('local/costcenter:view', $context);
    }

    /**
     * Check if user can manage organizations.
     *
     * @param \context|null $context
     * @return bool
     */
    public static function can_manage(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        return has_capability('local/airpay_org:manage', $context)
            || has_capability('local/costcenter:manage', $context);
    }

    /**
     * Check if user is an organization head (manage own org).
     *
     * @param \context|null $context
     * @return bool
     */
    public static function is_org_head(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        return has_capability('local/airpay_org:manage_ownorganization', $context)
            || has_capability('local/costcenter:manage_ownorganization', $context);
    }

    /**
     * Check if user is a department head (manage own departments).
     *
     * @param \context|null $context
     * @return bool
     */
    public static function is_dept_head(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        return has_capability('local/airpay_org:manage_owndepartments', $context)
            || has_capability('local/costcenter:manage_owndepartments', $context);
    }

    /**
     * Check if user can manage classrooms.
     *
     * @param \context|null $context
     * @return bool
     */
    public static function can_manage_classroom(?\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        return has_capability('local/airpay_classroom:manage', $context)
            || has_capability('local/classroom:manageclassroom', $context);
    }

    /**
     * Read from BizLMS local_costcenter table (transition fallback).
     *
     * During migration, local_airpay_org may not have data yet.
     * This reads from the original table if it exists.
     *
     * @param int $orgid
     * @return object|false
     */
    private static function get_costcenter_record(int $orgid) {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_costcenter')) {
            return false;
        }

        return $DB->get_record('local_costcenter', ['id' => $orgid]);
    }

    /**
     * Build a SQL fragment that matches a column against a costcenter path,
     * optionally including ancestors (UPPER_AND_SAME) or descendants (LOWER_AND_SAME).
     *
     * Returns [$sql, $params] for safe parameterised use. The fragment looks like
     * ` AND ( col = :exact_xxx OR col LIKE :like_xxx OR col = :anc1_xxx OR ... )`
     * (parens included so callers can paste it inside an existing WHERE).
     *
     * Replaces BizLMS `costcenterpath_match_sql()` which interpolated user input
     * directly into SQL — this version is injection-safe + LIKE-wildcard-escaped.
     *
     * Example for UPPER_AND_SAME with path '/1/2/3':
     *   matches /1/2/3, /1/2/3/X, /1/2, /1
     *
     * @param string $costcenterpath  e.g. '/1/2/3'
     * @param string $columnname      e.g. 'u.open_path'
     * @param string $datatype        self::LOWER_AND_SAME | self::UPPER_AND_SAME
     * @return array [string $sql, array $params]
     */
    public static function costcenterpath_match_sql(
        string $costcenterpath,
        string $columnname,
        string $datatype = self::LOWER_AND_SAME
    ): array {
        global $DB;

        if (empty($costcenterpath) || $costcenterpath === '/') {
            return ['', []];
        }

        $uid = uniqid('ccpm_');
        $clauses = [];
        $params = [];

        // Always include exact match + LOWER_AND_SAME prefix.
        $clauses[] = "{$columnname} = :{$uid}_exact";
        $params["{$uid}_exact"] = $costcenterpath;
        $clauses[] = "{$columnname} LIKE :{$uid}_like";
        $params["{$uid}_like"] = $DB->sql_like_escape($costcenterpath) . '/%';

        if ($datatype === self::UPPER_AND_SAME) {
            // Also walk up the path adding each ancestor as an exact match.
            $ancestor = $costcenterpath;
            $i = 0;
            while (($ancestor = self::strip_last_segment($ancestor)) !== '') {
                $clauses[] = "{$columnname} = :{$uid}_anc{$i}";
                $params["{$uid}_anc{$i}"] = $ancestor;
                $i++;
                if ($i > 20) { break; } // sanity: paths shouldn't be 20+ deep
            }
        }

        $sql = ' AND ( ' . implode(' OR ', $clauses) . ' ) ';
        return [$sql, $params];
    }

    /**
     * Build a SQL fragment that matches a column against the CURRENT user's
     * costcenter path(s). Reads from the `open_path` field on the user record.
     *
     * Returns [$sql, $params]. Returns ['', []] for siteadmins (they see all).
     *
     * Replaces BizLMS `userpath_match_sql()` which used the deprecated
     * local_userdata table; this version uses our open_path field directly.
     *
     * @param string $columnname  e.g. 'c.open_path'
     * @param string $datatype    self::LOWER_AND_SAME | self::UPPER_AND_SAME
     * @return array [string $sql, array $params]
     */
    public static function userpath_match_sql(
        string $columnname,
        string $datatype = self::LOWER_AND_SAME
    ): array {
        global $USER;

        if (is_siteadmin()) {
            return ['', []];
        }

        $userpath = $USER->open_path ?? '';
        if (empty($userpath)) {
            return ['', []];
        }

        return self::costcenterpath_match_sql($userpath, $columnname, $datatype);
    }

    /**
     * Resolve a costcenter path to its course-category context (cached).
     *
     * Returns the context for the category linked to that costcenter, or
     * the system context as a safe fallback. Cached per-path for the
     * duration of the request via a Moodle application cache.
     *
     * Replaces BizLMS `costcenterpath_contextdata()` which used string
     * interpolation in the lookup query — this version is safe + uses
     * the new `local_airpay_org` table first, falling back to the
     * legacy `local_costcenter` if present.
     *
     * @param string $costcenterpath  e.g. '/1/2/3'
     * @return \context  Either the category context or context_system as fallback
     */
    public static function costcenterpath_contextdata(string $costcenterpath): \context {
        global $DB;

        $fallback = \context_system::instance();
        if (empty($costcenterpath)) {
            return $fallback;
        }

        // Per-request memoisation (avoids repeated DB hits).
        static $cache = [];
        if (isset($cache[$costcenterpath])) {
            return $cache[$costcenterpath];
        }

        // The category linkage lives only on legacy local_costcenter (BizLMS).
        // local_airpay_org has no `category` column — we don't need one because
        // categories are looked up by path, not by org id.
        $categoryid = null;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_costcenter')) {
            try {
                $categoryid = $DB->get_field('local_costcenter', 'category', ['path' => $costcenterpath]);
            } catch (\dml_exception $e) {
                // Field might not exist on a different costcenter schema.
                $categoryid = null;
            }
        }

        if ($categoryid) {
            try {
                $context = \context_coursecat::instance((int) $categoryid);
                $cache[$costcenterpath] = $context;
                return $context;
            } catch (\dml_exception $e) {
                // Stale category id — fall through to system.
            }
        }

        $cache[$costcenterpath] = $fallback;
        return $fallback;
    }

    /**
     * Resolve the TOP-LEVEL tenant course category id for a user's open_path.
     *
     * Sentientia's multi-tenant isolation requires scoping catalog,
     * onboarding, and recommendation views to the user's tenant. This
     * method takes a full open_path (e.g. '/1/79/115') and returns the
     * `course_categories.id` for the user's TOP tenant ('/1' → the AIRPAY
     * category root). Sub-org scoping is intentionally NOT applied — many
     * org-wide compliance courses live at the tenant root and must remain
     * visible to all sub-org employees.
     *
     * Resolution order (defensive — each step gracefully falls through):
     *   1. **BizLMS canonical** — `local_costcenter.category` keyed by the
     *      top-level org path. This is the canonical mapping on production
     *      (where the BizLMS costcenter plugin is installed).
     *   2. **Sentientia-native fallback** — match `local_airpay_org.shortname`
     *      ↔ `course_categories.idnumber` for the same top org. This is
     *      a deterministic 1:1 convention that lets multi-tenant scoping
     *      work on a vanilla-Moodle Sentientia deployment without BizLMS
     *      (and on dev environments where local_costcenter isn't installed).
     *   3. **null** — caller MUST fail closed (render no tenant-scoped
     *      content rather than leak everything). Returning the system
     *      context's instanceid (0) would be a tenant-isolation hole.
     *
     * @param string $open_path  User's full org path, e.g. '/1' or '/1/2/3'
     * @return int|null          Category id of the user's top tenant, or null
     */
    public static function get_tenant_category_id(string $open_path): ?int {
        global $DB;

        if ($open_path === '') {
            return null;
        }
        $parts = array_values(array_filter(explode('/', $open_path)));
        if (empty($parts)) {
            return null;
        }
        $top_id   = (int) $parts[0];
        $top_path = '/' . $top_id;

        // Per-request memoisation.
        static $cache = [];
        if (array_key_exists($top_path, $cache)) {
            return $cache[$top_path];
        }

        // (1) BizLMS canonical lookup.
        $manager = $DB->get_manager();
        if ($manager->table_exists('local_costcenter')) {
            try {
                $catid = (int) $DB->get_field('local_costcenter', 'category', ['path' => $top_path]);
                if ($catid > 0) {
                    return $cache[$top_path] = $catid;
                }
            } catch (\Throwable $e) {
                // Schema mismatch — fall through.
            }
        }

        // (2) Sentientia-native fallback: org.shortname ↔ category.idnumber.
        try {
            $shortname = $DB->get_field('local_airpay_org', 'shortname',
                ['id' => $top_id, 'depth' => 1]);
            if (!empty($shortname)) {
                $catid = (int) $DB->get_field('course_categories', 'id',
                    ['idnumber' => $shortname, 'depth' => 1]);
                if ($catid > 0) {
                    return $cache[$top_path] = $catid;
                }
            }
        } catch (\Throwable $e) {
            // local_airpay_org or column missing — fall through.
        }

        // (3) Defensive fail-closed.
        return $cache[$top_path] = null;
    }

    /**
     * Strip the last `/segment` from a path. Returns '' once the path
     * has no more segments to strip.
     *
     *   '/1/2/3'  → '/1/2'
     *   '/1/2'    → '/1'
     *   '/1'      → ''
     *   '/'       → ''
     */
    private static function strip_last_segment(string $path): string {
        $path = rtrim($path, '/');
        if ($path === '' || $path === '/') {
            return '';
        }
        $idx = strrpos($path, '/');
        if ($idx === false || $idx === 0) {
            return '';
        }
        return substr($path, 0, $idx);
    }
}
