<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;
use local_sentientia_recompletion\bizlms\sources;

/**
 * The rows of the reset history page (history.php), in one testable place.
 *
 * Two rules decide which rows a caller is shown:
 *
 * - Tenant scope (ADR-031): cross-tenant callers see every row (the LEFT JOIN keeps redacted userid-0 rows), a
 *   scoped caller only rows about their own tenant's users, a caller with no tenant nothing.
 * - Imported rows (owner decision recompletion.legacy_rows_on_history_page, 2026-10-07): a reset the BizLMS import
 *   wrote (source = legacy) is a history reader row and shares the evidence_view flag, as the evidence page does.
 *   While the flag is OFF the page is what it was before the import: the Legacy badge and the "~" estimated
 *   times are not shown at all. Resets the Sentientia engine wrote are always shown. The imported-rule marker on
 *   index.php is a safety label on configuration, not history, and stays visible.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_reader {

    /**
     * The JOIN and the WHERE (over h = history, u = user) of the rows a caller may see.
     *
     * @param int $courseid Narrow to one course (0 = all).
     * @param int $userid Narrow to one learner (0 = all).
     * @param bool $legacyrows Whether imported (source = legacy) rows are shown: the evidence_view flag.
     * @return array{0: string, 1: string, 2: array} [join, where, params]
     */
    public static function filter(int $courseid, int $userid, bool $legacyrows): array {
        if (tenant::is_cross_tenant()) {
            $join = 'LEFT JOIN {user} u ON u.id = h.userid';
            $where = '1=1';
            $params = [];
        } else {
            $join = 'JOIN {user} u ON u.id = h.userid';
            [$where, $params] = rule_access::history_user_filter('u');
        }
        // The narrowing is applied on top of the tenant filter, never instead of it.
        if ($courseid > 0) {
            $where .= ' AND h.courseid = :hfcourse';
            $params['hfcourse'] = $courseid;
        }
        if ($userid > 0) {
            $where .= ' AND h.userid = :hfuser';
            $params['hfuser'] = $userid;
        }
        if (!$legacyrows) {
            $where .= ' AND h.source <> :hfsrc';
            $params['hfsrc'] = sources::LEGACY;
        }
        return [$join, $where, $params];
    }

    /**
     * One page of the history, newest first, and the number of rows the caller may see in all.
     *
     * @param int $courseid Narrow to one course (0 = all).
     * @param int $userid Narrow to one learner (0 = all).
     * @param int $page Zero-based page.
     * @param int $perpage
     * @param bool $legacyrows Whether imported rows are shown (the evidence_view flag).
     * @return array{0: int, 1: \stdClass[]} [total, rows]
     */
    public static function page(int $courseid, int $userid, int $page, int $perpage, bool $legacyrows): array {
        global $DB;
        [$join, $where, $params] = self::filter($courseid, $userid, $legacyrows);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_sentientia_recompletion_history} h
               $join
              WHERE $where", $params);
        // B8 fix: LIMIT/OFFSET go through the 5th/6th arguments of get_records_sql(), never into the SQL string.
        $rows = $DB->get_records_sql(
            "SELECT h.*, u.firstname, u.lastname, u.email, u.deleted AS user_deleted,
                    c.fullname AS course_name
               FROM {local_sentientia_recompletion_history} h
               $join
          LEFT JOIN {course} c ON c.id = h.courseid
              WHERE $where
              ORDER BY h.timecreated DESC, h.id DESC",
            $params, max(0, $page) * $perpage, $perpage);
        return [$total, $rows];
    }
}
