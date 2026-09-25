<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_notifications;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;

/**
 * ADR-031: who may see which notification-log rows.
 *
 * local/sentientia_notifications:viewlogs is a manager-archetype capability
 * (tenant admins hold it at system context) and it is a legitimate in-tenant
 * function, so it stays. What changes is WHERE: logs.php and log_detail.php
 * used to show every tenant's recipients, subjects and full message bodies to
 * any holder. The log table has no tenant column, so a row belongs to the
 * tenant of its recipient (u.open_path).
 *
 * @package local_sentientia_notifications
 */
class log_access {

    /**
     * WHERE fragment confining log rows to the viewer's tenant. The query
     * must join the recipient as `u`. '1=1' for a cross-tenant viewer, '1=0'
     * for a viewer with no tenant; rows with no recipient or a recipient with
     * no tenant are visible to cross-tenant viewers only.
     *
     * @return array{0: string, 1: array}
     */
    public static function scope_sql(): array {
        return tenant::path_filter('u');
    }

    /**
     * One log row the current viewer may see.
     *
     * Deliberately not tenant::require_path_access() on the recipient's path:
     * that helper lets an empty path through.
     *
     * @param int $id
     * @return \stdClass
     * @throws \dml_missing_record_exception|\moodle_exception error_outoftenant
     */
    public static function get_visible_log(int $id): \stdClass {
        global $DB;
        if (tenant::is_cross_tenant()) {
            return $DB->get_record('local_sentientia_notif_log', ['id' => $id], '*', MUST_EXIST);
        }
        [$tnsql, $tnargs] = self::scope_sql();
        $log = $DB->get_record_sql(
            "SELECT l.*
               FROM {local_sentientia_notif_log} l
               JOIN {user} u ON u.id = l.userid
              WHERE l.id = :logid AND $tnsql",
            ['logid' => $id] + $tnargs);
        if (!$log) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return $log;
    }

    /**
     * Per-status counts of the rows the viewer may see (the filter badges).
     *
     * @return array status => stdClass{status, n}
     */
    public static function status_counts(): array {
        global $DB;
        [$tnsql, $tnargs] = self::scope_sql();
        return $DB->get_records_sql(
            "SELECT l.status, COUNT(*) AS n
               FROM {local_sentientia_notif_log} l
          LEFT JOIN {user} u ON u.id = l.userid
              WHERE $tnsql
           GROUP BY l.status
           ORDER BY n DESC", $tnargs);
    }
}
