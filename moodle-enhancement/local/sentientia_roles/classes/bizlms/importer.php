<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer as importer_contract;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;
use local_sentientia_platform\bizlms\tenant_resolver;

/**
 * The org_roles importer (ADR-032, mapping doc section 4): the BizLMS org-role tables become real role
 * assignments at the organisation's course category, plus one audit row for each assignment made.
 *
 * Sources: local_costcenter_permissions (local/costcenter) and local_org_dept_roles (local/assignroles). Both are
 * expected to be empty on production: current BizLMS code only deletes from the first, and nothing reads the
 * second. The real org roles are core role_assignments at the category context, which the restore carries. The
 * importer exists so that a row in either table is never silently lost.
 *
 * Targets:
 *  - core role_assignments (a declared core write: insert), at context_coursecat(local_costcenter.category),
 *    written directly and never through role_assign() (that fires role_assigned and resets caches);
 *  - local_sentientia_roles_auditlog, one 'role_assigned' row per assignment this import made.
 *
 * The category context must already exist. A missing one is a preflight blocker: the importer never calls
 * context_coursecat::instance(), which would create the context row and a path behind the tripwire's back.
 *
 * Map shape (every source row has exactly one primary row, subkey empty):
 *  - primary: the role assignment of the first valid user of the row (imported), or, when that assignment already
 *    exists, a fold into the existing row (reason already_assigned);
 *  - pos:N  the assignment of the user at position N of the comma list, for every further valid user;
 *  - aud:N  the audit row of the assignment at position N.
 * N is the 1-based position in the list, never the user id (legacymap holds no personal data).
 *
 * @package    local_sentientia_roles
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements importer_contract {

    /** Feature key. */
    public const FEATURE = 'org_roles';

    /** Legacy table of local/costcenter: managers assigned to an organisation. */
    public const PERMISSIONS = 'local_costcenter_permissions';

    /** Legacy table of local/assignroles: a user's role in a department. */
    public const DEPT_ROLES = 'local_org_dept_roles';

    /** Legacy table that owns the organisation's course category (read in place, owned by the org feature). */
    public const COSTCENTER = 'local_costcenter';

    /** The audit table this plugin owns. */
    public const AUDIT_TABLE = 'local_sentientia_roles_auditlog';

    /** The core table the assignments land in (a reviewed core write). */
    public const ASSIGNMENTS = 'role_assignments';

    /** Decision: which permission rows count. The signed value is value_1_only. */
    public const DECISION_VALUE_FILTER = 'org_roles.value_filter';

    /** Reason: the row has no role (roleid 0). */
    public const REASON_NO_ROLE = 'no_role';

    /** Reason: the row is not an assignment (value other than 1). Kept only in the legacy table. */
    public const REASON_VALUE_NOT_ASSIGNED = 'value_not_assigned';

    /** Reason: the role no longer exists. */
    public const REASON_ROLE_NOT_FOUND = 'role_not_found';

    /** Reason: the organisation is not in the org map (missing, or not an organisation row). */
    public const REASON_ORG_NOT_FOUND = 'org_not_found';

    /** Reason: no user of the row exists and is active. */
    public const REASON_NO_VALID_USER = 'no_valid_user';

    /** Reason: the role assignment already exists, so the row became part of it. */
    public const REASON_ALREADY_ASSIGNED = 'already_assigned';

    /** @var string The audit row's reason for an assignment that came from local_costcenter_permissions. */
    public const AUDIT_REASON_PERMISSIONS = 'bizlms_import:costcenter_permissions';

    /** @var string The audit row's reason for an assignment that came from local_org_dept_roles. */
    public const AUDIT_REASON_DEPT_ROLES = 'bizlms_import:org_dept_roles';

    /**
     * @return string
     */
    public function feature(): string {
        return self::FEATURE;
    }

    /**
     * @return string
     */
    public function component(): string {
        return 'local_sentientia_roles';
    }

    /**
     * The plugin version that ships this importer. There is no schema addition: the audit table and role_assignments
     * already exist.
     *
     * @return int
     */
    public function requires_version(): int {
        return 2026093001;
    }

    /**
     * The organisations (and their course categories) come from the org feature.
     *
     * @return string[]
     */
    public function depends(): array {
        return ['org'];
    }

    /**
     * Both tables are optional: local_org_dept_roles may not exist on production, and either may be empty.
     * The value column is an enum: an unexpected value blocks until the owner maps it.
     *
     * @return array<string, source_spec>
     */
    public function sources(): array {
        return [
            self::PERMISSIONS => new source_spec(self::PERMISSIONS, false, [
                'value' => [0 => 'not assigned', 1 => 'assigned'],
            ]),
            self::DEPT_ROLES => new source_spec(self::DEPT_ROLES, false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function declined_tables(): array {
        return [];
    }

    /**
     * @return string[]
     */
    public function target_tables(): array {
        return [self::AUDIT_TABLE];
    }

    /**
     * @return array<string, string>
     */
    public function core_writes(): array {
        return [
            self::ASSIGNMENTS => 'org_roles: the org role tables become role assignments at the organisation\'s '
                . 'category context, inserted directly because role_assign() fires role_assigned (mapping doc, org_roles)',
        ];
    }

    /**
     * None is declared on purpose. The audit table's open_path is the ACTOR's path, and the generic tenant verify
     * reads every row of the table, including the rows the role UI writes with an empty path (a site admin has none).
     * The importer checks the path of the rows it wrote in verify() instead. The tenant of an assignment is implicit
     * in the category context.
     *
     * @return array<string, string>
     */
    public function tenant_columns(): array {
        return [];
    }

    /**
     * @return reason[]
     */
    public function reasons(): array {
        return [
            new reason(self::REASON_NO_ROLE, false, false),
            new reason(self::REASON_VALUE_NOT_ASSIGNED, false, false),
            new reason(self::REASON_ROLE_NOT_FOUND, false, true),
            new reason(self::REASON_ORG_NOT_FOUND, false, true),
            new reason(self::REASON_NO_VALID_USER, false, false),
            new reason(self::REASON_ALREADY_ASSIGNED, false, false),
        ];
    }

    /**
     * @return decision[]
     */
    public function decisions(): array {
        return [
            new decision(self::DECISION_VALUE_FILTER, 'Which org-role permission rows count as an assignment', true,
                null, ['value_1_only']),
        ];
    }

    /**
     * Small tables: one outer transaction when the preflight total allows it.
     *
     * @return bool
     */
    public function atomic(): bool {
        return true;
    }

    /**
     * @return array
     */
    public function steps(): array {
        return [new permissions_step(), new dept_roles_step()];
    }

    /**
     * Read-only. A missing organisation context is a blocker: the importer never creates one.
     *
     * @param context $ctx
     * @return preflight
     */
    public function preflight(context $ctx): preflight {
        global $DB;
        $pf = new preflight();
        $legacy = $ctx->legacy;

        // Rows that would become an assignment, per source table: the columns that name the organisation.
        $candidates = [
            self::PERMISSIONS => ['columns' => ['costcenterid', 'roleid'], 'filter' => ['t.value = 1 AND t.roleid > 0', []],
                'orgcolumns' => ['costcenterid']],
            self::DEPT_ROLES => ['columns' => ['costcenterid', 'departmentid', 'roleid'], 'filter' => ['t.roleid > 0', []],
                'orgcolumns' => ['departmentid', 'costcenterid']],
        ];

        $orgids = [];
        $roleids = [];
        $total = 0;
        foreach ($candidates as $table => $spec) {
            if (!$legacy->exists($table)) {
                continue;
            }
            $after = 0;
            do {
                $rows = $legacy->page($table, $after, 1000, $spec['columns'], $spec['filter']);
                foreach ($rows as $id => $row) {
                    $after = (int) $id;
                    $total++;
                    $orgid = 0;
                    foreach ($spec['orgcolumns'] as $column) {
                        if ((int) ($row->{$column} ?? 0) > 0) {
                            $orgid = (int) $row->{$column};
                            break;
                        }
                    }
                    if ($orgid > 0) {
                        $orgids[$orgid] = true;
                    }
                    if ((int) $row->roleid > 0) {
                        $roleids[(int) $row->roleid] = true;
                    }
                }
            } while (count($rows) === 1000);
        }
        $pf->count('org_roles.assignment_rows', $total);
        if ($total === 0) {
            return $pf;
        }

        if (!$legacy->exists(self::COSTCENTER)) {
            return $pf->block('org_source_missing:' . self::COSTCENTER);
        }

        // Every organisation an assignment would land in needs an existing course category context.
        $orgs = org_contexts::for_orgs($legacy, array_keys($orgids));
        $nocategory = [];
        $nocontext = [];
        $notfound = 0;
        foreach (array_keys($orgids) as $orgid) {
            if (!isset($orgs[$orgid])) {
                $notfound++;
            } else if ($orgs[$orgid]->category <= 0) {
                $nocategory[] = $orgid;
            } else if ($orgs[$orgid]->contextid === null) {
                $nocontext[] = $orgid;
            }
        }
        $pf->count('org_roles.organisations', count($orgids));
        if ($nocategory) {
            sort($nocategory);
            $pf->block('org_without_category:' . count($nocategory) . ' ids=' . implode(',', array_slice($nocategory, 0, 20)));
        }
        if ($nocontext) {
            sort($nocontext);
            $pf->block('org_context_missing:' . count($nocontext) . ' ids=' . implode(',', array_slice($nocontext, 0, 20)));
        }
        if ($notfound) {
            $pf->warn('org_not_found:' . $notfound);
        }

        // A role that cannot be assigned at a category is still imported (BizLMS did), but the owner should see it.
        if ($roleids) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($roleids), SQL_PARAMS_NAMED, 'blmrole');
            $params['blmlevel'] = CONTEXT_COURSECAT;
            $levels = $DB->get_fieldset_sql(
                "SELECT rcl.roleid FROM {role_context_levels} rcl WHERE rcl.contextlevel = :blmlevel AND rcl.roleid $insql",
                $params);
            $missing = array_diff(array_keys($roleids), array_map('intval', $levels));
            if ($missing) {
                sort($missing);
                $pf->warn('role_not_assignable_at_category:' . count($missing) . ' ids=' . implode(',', array_slice($missing, 0, 20)));
            }
        }
        return $pf;
    }

    /**
     * Read-only checks of what this import wrote.
     *
     * @param context $ctx
     * @return string[]
     */
    public function verify(context $ctx): array {
        global $DB;
        $failures = [];
        $params = ['feature' => self::FEATURE, 'assignments' => self::ASSIGNMENTS, 'audit' => self::AUDIT_TABLE];

        // Every assignment the map points at still exists and sits at a course category context.
        $missing = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . legacymap::TABLE . "} m
          LEFT JOIN {role_assignments} ra ON ra.id = m.targetid
              WHERE m.feature = :feature AND m.targettable = :assignments
                AND m.outcome IN ('imported', 'folded') AND ra.id IS NULL", $params);
        if ($missing) {
            $failures[] = 'role_assignment_missing:' . $missing;
        }
        $params['level'] = CONTEXT_COURSECAT;
        $misplaced = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . legacymap::TABLE . "} m
               JOIN {role_assignments} ra ON ra.id = m.targetid
          LEFT JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :level
              WHERE m.feature = :feature AND m.targettable = :assignments
                AND m.outcome IN ('imported', 'folded') AND ctx.id IS NULL", $params);
        if ($misplaced) {
            $failures[] = 'role_assignment_not_at_category:' . $misplaced;
        }

        // One audit row per assignment this import created.
        $created = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {" . legacymap::TABLE . "} m
              WHERE m.feature = :feature AND m.targettable = :assignments AND m.outcome = 'imported'", $params);
        $audited = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {" . legacymap::TABLE . "} m
              WHERE m.feature = :feature AND m.targettable = :audit AND m.outcome = 'imported'", $params);
        if ($created !== $audited) {
            $failures[] = "audit_rows_differ:assignments={$created} audit={$audited}";
        }

        // The actor's path on the audit rows this import wrote is a normalised path with a registered root, or empty.
        $paths = $DB->get_records_sql(
            "SELECT MIN(a.id) AS id, a.open_path AS pathvalue, COUNT(1) AS n
               FROM {" . legacymap::TABLE . "} m
               JOIN {" . self::AUDIT_TABLE . "} a ON a.id = m.targetid
              WHERE m.feature = :feature AND m.targettable = :audit AND m.outcome = 'imported'
                AND a.open_path IS NOT NULL
           GROUP BY a.open_path", $params);
        foreach ($paths as $row) {
            if (!self::is_valid_path((string) $row->pathvalue)) {
                $failures[] = 'invalid_tenant_value:' . self::AUDIT_TABLE . '.open_path rows=' . (int) $row->n;
            }
        }
        return $failures;
    }

    /**
     * Outside any transaction. Role assignments written straight into the table do not reach the users' cached
     * access or the category caches: mark every touched context dirty and reset the caches role_assign() would.
     *
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void {
        global $DB;
        // The first column keys the result, so it must be unique: one row per (role, context).
        $touched = $DB->get_records_sql(
            "SELECT MIN(ra.id) AS id, ra.roleid, ra.contextid
               FROM {role_assignments} ra
               JOIN {" . legacymap::TABLE . "} m ON m.targetid = ra.id
              WHERE m.feature = :feature AND m.targettable = :assignments AND m.outcome = 'imported'
           GROUP BY ra.roleid, ra.contextid",
            ['feature' => self::FEATURE, 'assignments' => self::ASSIGNMENTS]);
        $dirty = [];
        foreach ($touched as $row) {
            $context = \context::instance_by_id((int) $row->contextid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            if (!isset($dirty[$context->id])) {
                $context->mark_dirty();
                $dirty[$context->id] = true;
            }
            \core_course_category::role_assignment_changed((int) $row->roleid, $context);
        }
    }

    /**
     * Is this a normalised path whose root is a registered tenant?
     *
     * @param string $value
     * @return bool
     */
    private static function is_valid_path(string $value): bool {
        $path = tenant_resolver::normalise($value);
        if ($path === null || $path !== $value) {
            return false;
        }
        try {
            \local_sentientia_platform\tenant::assert_valid((int) explode('/', ltrim($path, '/'))[0]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
