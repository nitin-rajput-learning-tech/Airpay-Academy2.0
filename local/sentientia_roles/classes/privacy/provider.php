<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for local_sentientia_roles.
 *
 * Stores PII in `local_sentientia_roles_auditlog` via two columns:
 * - `changedby`     — the admin who made the change
 * - `targetuserid`  — the user that was assigned/unassigned a role
 *
 * **Retention policy:** the audit log is append-only and retained for
 * compliance review. Per-user delete therefore *redacts* the
 * `changedby` / `targetuserid` references (sets them to 0) rather than
 * removing rows. This satisfies GDPR Right-to-be-Forgotten while
 * preserving the compliance trail (the audit shows that an event
 * happened, but no longer attributes it to the deleted user).
 *
 * @package    local_sentientia_roles
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_roles_auditlog',
            [
                'roleid'         => 'privacy:metadata:auditlog:roleid',
                'capability'     => 'privacy:metadata:auditlog:capability',
                'oldpermission'  => 'privacy:metadata:auditlog:oldpermission',
                'newpermission'  => 'privacy:metadata:auditlog:newpermission',
                'changedby'      => 'privacy:metadata:auditlog:changedby',
                'targetuserid'   => 'privacy:metadata:auditlog:targetuserid',
                'reason'         => 'privacy:metadata:auditlog:reason',
                'open_path'      => 'privacy:metadata:auditlog:open_path',
                'timecreated'    => 'privacy:metadata:auditlog:timecreated',
            ],
            'privacy:metadata:auditlog'
        );
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT contextid
                  FROM {local_sentientia_roles_auditlog}
                 WHERE changedby = :u1 OR targetuserid = :u2";
        $contextlist->add_from_sql($sql, ['u1' => $userid, 'u2' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        $sql = "SELECT changedby AS userid FROM {local_sentientia_roles_auditlog}
                 WHERE contextid = :ctx AND changedby > 0
                 UNION
                SELECT targetuserid AS userid FROM {local_sentientia_roles_auditlog}
                 WHERE contextid = :ctx2 AND targetuserid > 0";
        $userlist->add_from_sql('userid', $sql,
            ['ctx' => $context->id, 'ctx2' => $context->id]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        if (empty($contextlist->get_contextids())) return;

        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(),
            SQL_PARAMS_NAMED);
        $sql = "SELECT * FROM {local_sentientia_roles_auditlog}
                 WHERE contextid $insql
                   AND (changedby = :u1 OR targetuserid = :u2)
              ORDER BY timecreated DESC";
        $params = array_merge($inparams,
            ['u1' => $userid, 'u2' => $userid]);
        $rows = $DB->get_records_sql($sql, $params);

        if (empty($rows)) return;

        $entries = [];
        foreach ($rows as $r) {
            $entries[] = (object) [
                'role'          => (string) ($r->roleshortname ?? ''),
                'action'        => (string) $r->action,
                'capability'    => (string) ($r->capability ?? ''),
                'old_permission' => (int) ($r->oldpermission ?? 0),
                'new_permission' => (int) ($r->newpermission ?? 0),
                'reason'        => (string) ($r->reason ?? ''),
                'time_created'  => (int) $r->timecreated,
                'role_in_event' => ((int) $r->changedby === (int) $userid)
                    ? 'admin who made the change' : 'subject of the change',
            ];
        }
        // Use system context (the audit log is system-context-only).
        $context = \context_system::instance();
        writer::with_context($context)
            ->export_data(['Airpay Role Management — audit log'],
                (object) ['entries' => $entries]);
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        // Audit retention: redact rather than delete.
        $DB->execute("UPDATE {local_sentientia_roles_auditlog}
                         SET changedby = 0, targetuserid = NULL,
                             reason = NULL, open_path = NULL
                       WHERE contextid = :ctx",
            ['ctx' => $context->id]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        if (empty($contextlist->get_contextids())) return;

        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(),
            SQL_PARAMS_NAMED);

        // Redact changedby references.
        $params = array_merge($inparams, ['u' => $userid]);
        $DB->execute("UPDATE {local_sentientia_roles_auditlog}
                         SET changedby = 0, reason = NULL, open_path = NULL
                       WHERE contextid $insql AND changedby = :u", $params);

        // Redact targetuserid references.
        $DB->execute("UPDATE {local_sentientia_roles_auditlog}
                         SET targetuserid = NULL
                       WHERE contextid $insql AND targetuserid = :u", $params);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (empty($userids)) return;

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['ctx' => $context->id]);

        $DB->execute("UPDATE {local_sentientia_roles_auditlog}
                         SET changedby = 0, reason = NULL, open_path = NULL
                       WHERE contextid = :ctx AND changedby $insql", $params);
        $DB->execute("UPDATE {local_sentientia_roles_auditlog}
                         SET targetuserid = NULL
                       WHERE contextid = :ctx AND targetuserid $insql", $params);
    }
}
