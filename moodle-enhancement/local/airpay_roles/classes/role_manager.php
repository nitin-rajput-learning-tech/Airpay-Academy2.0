<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_roles;

defined('MOODLE_INTERNAL') || die();

/**
 * Role manager — the single point of truth for the airpay_roles UI's
 * read + write paths.
 *
 * Wraps Moodle core role functions (`role_change_permission`,
 * `role_assign`, `role_unassign`) and writes to the local audit log on
 * every mutation so the UI can present "who changed what when" without
 * trawling Moodle's standard log table.
 *
 * @package    local_airpay_roles
 * @copyright  2026 Airpay Payment Services
 */
class role_manager {

    /** Convert a free-text permission token to Moodle's CAP_* constant. */
    public static function permission_from_string(string $perm): int {
        return match (strtolower($perm)) {
            'allow'    => CAP_ALLOW,
            'prevent'  => CAP_PREVENT,
            'prohibit' => CAP_PROHIBIT,
            'inherit', 'unset', '' => CAP_INHERIT,
            default => throw new \invalid_parameter_exception(
                get_string('err_invalid_permission', 'local_airpay_roles')),
        };
    }

    /** Convert a CAP_* constant to a stable lang-string key suffix. */
    public static function permission_to_string(int $perm): string {
        return match ($perm) {
            CAP_ALLOW    => 'allow',
            CAP_PREVENT  => 'prevent',
            CAP_PROHIBIT => 'prohibit',
            default      => 'inherit',
        };
    }

    /**
     * List all roles with summary stats.
     *
     * Returns an indexed array (sorted by role.sortorder) of associative
     * rows shaped for direct JSON-marshalling to the datatable client.
     */
    public static function list_roles(string $search = '', string $archetype = ''): array {
        global $DB;

        $context = \context_system::instance();
        $allroles = role_fix_names(get_all_roles($context), $context, ROLENAME_ORIGINAL);

        // Pull capability counts in one query (avoids N+1 across 30+ roles).
        $capcounts = $DB->get_records_sql_menu("
            SELECT roleid, COUNT(*) AS cnt
              FROM {role_capabilities}
             WHERE permission != ?
          GROUP BY roleid", [CAP_INHERIT]);

        // Pull assignment counts the same way.
        $assigncounts = $DB->get_records_sql_menu("
            SELECT roleid, COUNT(DISTINCT userid) AS cnt
              FROM {role_assignments}
          GROUP BY roleid");

        $rows = [];
        foreach ($allroles as $role) {
            // Apply filters in PHP — list is small (<50 roles), keeps SQL simple.
            if ($archetype !== '' && $archetype !== 'all') {
                $rolearch = (string) ($role->archetype ?? '');
                if ($archetype === 'custom') {
                    if ($rolearch !== '') continue;
                } else if ($rolearch !== $archetype) {
                    continue;
                }
            }
            if ($search !== '') {
                $needle = \core_text::strtolower($search);
                $haystack = \core_text::strtolower(($role->localname ?? '') . ' ' . ($role->shortname ?? ''));
                if (strpos($haystack, $needle) === false) continue;
            }

            $rows[] = [
                'id'          => (int) $role->id,
                'name'        => format_string($role->localname ?? $role->shortname),
                'shortname'   => s($role->shortname),
                'archetype'   => (string) ($role->archetype ?? ''),
                'capcount'    => (int) ($capcounts[$role->id] ?? 0),
                'assigncount' => (int) ($assigncounts[$role->id] ?? 0),
                'sortorder'   => (int) $role->sortorder,
                'description' => format_text($role->description ?? '', FORMAT_HTML, ['noclean' => false]),
            ];
        }
        return $rows;
    }

    /**
     * Get a single role with metadata.
     *
     * @throws \moodle_exception if role not found.
     */
    public static function get_role(int $roleid): array {
        global $DB;
        $context = \context_system::instance();
        $allroles = role_fix_names(get_all_roles($context), $context, ROLENAME_ORIGINAL);
        if (!isset($allroles[$roleid])) {
            throw new \moodle_exception('err_role_not_found', 'local_airpay_roles');
        }
        $role = $allroles[$roleid];

        $capcounts = $DB->get_records_sql_menu("
            SELECT permission, COUNT(*) AS cnt
              FROM {role_capabilities}
             WHERE roleid = :rid
          GROUP BY permission", ['rid' => $roleid]);

        $assigncount = (int) $DB->count_records('role_assignments', ['roleid' => $roleid]);
        $auditcount  = (int) $DB->count_records('local_airpay_roles_auditlog', ['roleid' => $roleid]);

        return [
            'id'          => (int) $role->id,
            'name'        => format_string($role->localname ?? $role->shortname),
            'shortname'   => s($role->shortname),
            'archetype'   => (string) ($role->archetype ?? ''),
            'description' => format_text($role->description ?? '', FORMAT_HTML, ['noclean' => false]),
            'sortorder'   => (int) $role->sortorder,
            'caps_total'  => array_sum(array_map('intval', $capcounts)),
            'caps_allow'    => (int) ($capcounts[CAP_ALLOW]    ?? 0),
            'caps_prevent'  => (int) ($capcounts[CAP_PREVENT]  ?? 0),
            'caps_prohibit' => (int) ($capcounts[CAP_PROHIBIT] ?? 0),
            'assigncount' => $assigncount,
            'auditcount'  => $auditcount,
        ];
    }

    /**
     * Get the capability list for a role with their current permission.
     *
     * Returns ALL Moodle-registered capabilities, not just those with a
     * row in role_capabilities — so the UI can show "inherit" alongside
     * the explicitly-set ones.
     *
     * @param int    $roleid
     * @param string $search   substring filter on capability name
     * @param string $perm     'all'|'inherit'|'allow'|'prevent'|'prohibit'
     * @param int    $page     0-based
     * @param int    $perpage  page size (capped at 100)
     */
    public static function get_role_caps(int $roleid, string $search = '',
                                          string $perm = 'all',
                                          int $page = 0, int $perpage = 50): array {
        global $DB;
        $context = \context_system::instance();
        $allroles = get_all_roles($context);
        if (!isset($allroles[$roleid])) {
            throw new \moodle_exception('err_role_not_found', 'local_airpay_roles');
        }

        $perpage = max(10, min(100, $perpage));
        $page    = max(0, $page);

        // Pull all registered capabilities. fetch_context_capabilities() is
        // the documented Moodle API for this.
        $allcaps = get_all_capabilities();
        // The above returns name=>def. fetch_context_capabilities() filters
        // by context level — for system roles, all caps apply.

        // Pull current permissions for this role at system context.
        $currentperms = $DB->get_records_sql_menu("
            SELECT capability, permission
              FROM {role_capabilities}
             WHERE roleid = :rid
               AND contextid = :cid",
            ['rid' => $roleid, 'cid' => $context->id]);

        $rows = [];
        foreach ($allcaps as $capname => $capdef) {
            // Filter by perm bucket.
            $current = (int) ($currentperms[$capname] ?? CAP_INHERIT);
            if ($perm !== 'all') {
                $required = self::permission_from_string($perm);
                if ($current !== $required) continue;
            }
            // Filter by search term.
            if ($search !== '' && stripos($capname, $search) === false
                    && stripos((string) ($capdef['component'] ?? ''), $search) === false) {
                continue;
            }

            // Risk bitmask → human labels.
            $risks = [];
            $bitmask = (int) ($capdef['riskbitmask'] ?? 0);
            if ($bitmask & RISK_MANAGETRUST) $risks[] = 'trust';
            if ($bitmask & RISK_CONFIG)      $risks[] = 'config';
            if ($bitmask & RISK_XSS)         $risks[] = 'xss';
            if ($bitmask & RISK_PERSONAL)    $risks[] = 'personal';
            if ($bitmask & RISK_SPAM)        $risks[] = 'spam';
            if ($bitmask & RISK_DATALOSS)    $risks[] = 'dataloss';

            $rows[] = [
                'capability' => $capname,
                'component'  => (string) ($capdef['component'] ?? ''),
                'permission' => $current,
                'permission_label' => self::permission_to_string($current),
                'risks'      => $risks,
                'risks_text' => implode(', ', $risks) ?: '—',
            ];
        }

        $total = count($rows);
        // Stable sort by capability name.
        usort($rows, fn($a, $b) => strcmp($a['capability'], $b['capability']));
        $rows = array_slice($rows, $page * $perpage, $perpage);

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'perpage' => $perpage];
    }

    /**
     * Set a capability permission on a role and write an audit log entry.
     *
     * Wraps `role_change_permission()` (the canonical Moodle API) inside
     * a transaction with the audit insert so partial-state bugs are
     * impossible.
     *
     * @param int    $roleid
     * @param string $capability  capability name
     * @param string $permission  inherit|allow|prevent|prohibit
     * @param string $reason      optional admin justification
     * @return array audit log entry
     * @throws \moodle_exception
     */
    public static function update_capability(int $roleid, string $capability,
                                              string $permission, string $reason = ''): array {
        global $DB, $USER;

        $context = \context_system::instance();
        $allroles = get_all_roles($context);
        if (!isset($allroles[$roleid])) {
            throw new \moodle_exception('err_role_not_found', 'local_airpay_roles');
        }
        $role = $allroles[$roleid];

        // Defensive: don't let admins (even via UI) clobber the manager
        // archetype's site:config — that's how you lock yourself out.
        if ($role->shortname === 'manager' && $capability === 'moodle/site:config'
                && self::permission_from_string($permission) !== CAP_ALLOW) {
            throw new \moodle_exception('err_cannot_modify_admin', 'local_airpay_roles');
        }

        $allcaps = get_all_capabilities();
        if (!isset($allcaps[$capability])) {
            throw new \moodle_exception('err_capability_not_found', 'local_airpay_roles', '', $capability);
        }

        $newperm = self::permission_from_string($permission);

        // Capture old permission for the audit log BEFORE we mutate.
        $oldperm = (int) ($DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => $capability, 'contextid' => $context->id])
            ?: CAP_INHERIT);

        $tx = $DB->start_delegated_transaction();
        try {
            // Apply the change via Moodle core API.
            // role_change_permission accepts CAP_INHERIT to reset.
            role_change_permission($roleid, $context, $capability, $newperm);

            // Write the audit entry.
            $entry = (object) [
                'roleid'        => $roleid,
                'roleshortname' => $role->shortname,
                'action'        => $newperm === CAP_INHERIT ? 'capability_unset' : 'capability_set',
                'capability'    => $capability,
                'oldpermission' => $oldperm,
                'newpermission' => $newperm,
                'contextid'     => $context->id,
                'targetuserid'  => null,
                'changedby'     => (int) $USER->id,
                'reason'        => $reason !== '' ? $reason : null,
                'open_path'     => (string) ($USER->open_path ?? ''),
                'timecreated'   => time(),
            ];
            $entry->id = $DB->insert_record('local_airpay_roles_auditlog', $entry);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }

        return [
            'id'             => (int) $entry->id,
            'roleid'         => $roleid,
            'capability'     => $capability,
            'oldpermission'  => $oldperm,
            'newpermission'  => $newperm,
            'oldlabel'       => self::permission_to_string($oldperm),
            'newlabel'       => self::permission_to_string($newperm),
            'changedby'      => (int) $USER->id,
            'timecreated'    => $entry->timecreated,
        ];
    }

    /**
     * List audit log entries with optional filters.
     *
     * @param int    $roleid     0 = all roles
     * @param string $action     '' = all actions; otherwise one of the
     *                           audit_action_* keys
     * @param string $capability '' = all caps
     * @param int    $page       0-based
     * @param int    $perpage    capped at 100
     */
    public static function list_audit(int $roleid = 0, string $action = '',
                                       string $capability = '', int $page = 0,
                                       int $perpage = 50): array {
        global $DB;

        $perpage = max(10, min(100, $perpage));
        $page    = max(0, $page);

        $where = ['1=1'];
        $params = [];
        if ($roleid > 0)     { $where[] = 'a.roleid = :rid';        $params['rid'] = $roleid; }
        if ($action !== '')  { $where[] = 'a.action = :act';         $params['act'] = $action; }
        if ($capability!=='') { $where[] = 'a.capability = :cap';    $params['cap'] = $capability; }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_roles_auditlog} a WHERE $wheresql", $params);

        $records = $DB->get_records_sql("
            SELECT a.*, u.firstname, u.lastname, u.email
              FROM {local_airpay_roles_auditlog} a
         LEFT JOIN {user} u ON u.id = a.changedby
             WHERE $wheresql
          ORDER BY a.timecreated DESC, a.id DESC",
            $params, $page * $perpage, $perpage);

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'id'           => (int) $r->id,
                'roleid'       => (int) $r->roleid,
                'roleshortname'=> s($r->roleshortname),
                'action'       => $r->action,
                'capability'   => (string) ($r->capability ?? ''),
                'oldpermission'=> $r->oldpermission !== null ? (int) $r->oldpermission : null,
                'newpermission'=> $r->newpermission !== null ? (int) $r->newpermission : null,
                'oldlabel'     => $r->oldpermission !== null
                    ? self::permission_to_string((int) $r->oldpermission) : '—',
                'newlabel'     => $r->newpermission !== null
                    ? self::permission_to_string((int) $r->newpermission) : '—',
                'changedby'    => (int) $r->changedby,
                'changedby_name' => $r->firstname
                    ? fullname((object) ['firstname' => $r->firstname, 'lastname' => $r->lastname])
                    : '—',
                'reason'       => (string) ($r->reason ?? ''),
                'timecreated'  => (int) $r->timecreated,
                'when'         => userdate((int) $r->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
            ];
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'perpage' => $perpage];
    }

    /**
     * Build a CSV stream of all roles + their non-inherit capabilities.
     *
     * Used by exportcsv.php — yields each row as a flat array. Caller is
     * responsible for fputcsv to the output stream + Content-Disposition
     * header.
     *
     * @return \Generator yields header then one row per role-capability pair
     */
    public static function csv_iterator(): \Generator {
        global $DB;

        // Header row.
        yield ['Role ID', 'Role shortname', 'Role name', 'Archetype',
               'Capability', 'Component', 'Permission'];

        $context = \context_system::instance();
        $allroles = role_fix_names(get_all_roles($context), $context, ROLENAME_ORIGINAL);
        $allcaps  = get_all_capabilities();

        foreach ($allroles as $role) {
            $perms = $DB->get_records_sql_menu("
                SELECT capability, permission
                  FROM {role_capabilities}
                 WHERE roleid = :rid AND contextid = :cid
                   AND permission != :inherit",
                ['rid' => $role->id, 'cid' => $context->id, 'inherit' => CAP_INHERIT]);

            foreach ($perms as $cap => $perm) {
                yield [
                    (int) $role->id,
                    $role->shortname,
                    $role->localname ?? $role->shortname,
                    $role->archetype ?? '',
                    $cap,
                    $allcaps[$cap]['component'] ?? '',
                    self::permission_to_string((int) $perm),
                ];
            }
        }
    }
}
