<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * List children of an org node — the data layer behind the hierarchy
 * cascade filter used by Manage Users, Manage Courses, Manage Programs,
 * Classrooms, every admin list page that wants 5-level org filtering.
 *
 * BizLMS parity (2026-05-15 audit): ports the equivalent of
 * `local_costcenter/form-options-selector` AJAX endpoint with
 * `costcenter_element_selector` action — except cleaner. The legacy
 * BizLMS endpoint hard-coded the depth-to-table mapping; here we use
 * the sentientia_org table's `depth` column directly.
 *
 * Contract:
 *   - input:  parentid (int, 0 = list root tenants), visible_only (bool)
 *   - output: rows[{id, name, path, depth, has_children}]
 *
 * Tenant scoping: callers who are not cross-tenant only see children whose
 * path is inside, equal to, or an ancestor of their own caller path
 * (USER->open_path tree). Cross-tenant callers (ADR-031: site admin or
 * :crosstenant) see everything. A caller whose tenant does not resolve sees
 * nothing.
 */
class list_children extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'parentid' => new external_value(PARAM_INT,
                'Parent org id (0 = top-level tenants)', VALUE_DEFAULT, 0),
            'visible_only' => new external_value(PARAM_BOOL,
                'Hide soft-deleted/hidden orgs', VALUE_DEFAULT, true),
        ]);
    }

    public static function execute(int $parentid = 0, bool $visible_only = true): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('parentid', 'visible_only'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_users:view', $context);

        // Scoping: only return children that live under the caller's own org
        // path. This matches BizLMS behaviour where a department admin couldn't
        // see other departments. ADR-031: decided up front, and FAIL CLOSED -
        // for a caller with no resolvable tenant the path used to be '', and
        // strpos($orgpath, '' . '/') === 0 is true for every org path, so every
        // tenant's tree came back.
        $crosstenant = \local_sentientia_platform\tenant::is_cross_tenant();
        $caller_path = '';
        if (!$crosstenant) {
            $trimmed = trim((string) ($USER->open_path ?? ''), '/');
            if ($trimmed === '' || \local_sentientia_platform\tenant::root_for_user($USER) <= 0) {
                return ['rows' => []];
            }
            $caller_path = '/' . $trimmed;
        }

        $children = \local_sentientia_org\org_manager::get_children(
            $params['parentid'], $params['visible_only']);

        // Pre-compute has_children for every row in one query — saves N+1
        // round-trips when the cascade renders deep trees.
        $childids = array_map(fn($r) => (int) $r->id, $children);
        $has_children_map = [];
        if (!empty($childids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($childids,
                SQL_PARAMS_NAMED, 'parent');
            $sql = "SELECT parentid, COUNT(id) AS n
                      FROM {local_sentientia_org}
                     WHERE parentid {$insql} AND visible = 1
                  GROUP BY parentid";
            $rs = $DB->get_records_sql($sql, $inparams);
            foreach ($rs as $row) {
                $has_children_map[(int) $row->parentid] = ((int) $row->n) > 0;
            }
        }

        $rows = [];
        foreach ($children as $org) {
            // Apply the tenant filter to callers who are not cross-tenant.
            if (!$crosstenant) {
                $orgpath = rtrim((string) ($org->path ?? ''), '/');
                if ($orgpath === '') {
                    continue;  // A path-less legacy row belongs to no tenant.
                }
                $inside = ($orgpath === $caller_path)
                    || (strpos($orgpath, $caller_path . '/') === 0)
                    || (strpos($caller_path, $orgpath . '/') === 0);
                if (!$inside) {
                    continue;
                }
            }
            $rows[] = [
                'id'           => (int) $org->id,
                'name'         => (string) $org->fullname,
                'shortname'    => (string) ($org->shortname ?? ''),
                'path'         => (string) ($org->path ?? ''),
                'depth'        => (int) ($org->depth ?? 1),
                'parentid'     => (int) ($org->parentid ?? 0),
                'has_children' => !empty($has_children_map[(int) $org->id]),
            ];
        }

        return ['rows' => $rows];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT, 'Org id'),
                    'name'         => new external_value(PARAM_TEXT, 'Display name'),
                    'shortname'    => new external_value(PARAM_TEXT, 'Machine name'),
                    'path'         => new external_value(PARAM_TEXT, 'Hierarchy path'),
                    'depth'        => new external_value(PARAM_INT, 'Depth (1=tenant, 2=division, ...)'),
                    'parentid'     => new external_value(PARAM_INT, 'Parent org id'),
                    'has_children' => new external_value(PARAM_BOOL,
                        'True if this org has visible children — UI uses this to know whether to render the next cascade level'),
                ])
            ),
        ]);
    }
}
