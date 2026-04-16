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
}
