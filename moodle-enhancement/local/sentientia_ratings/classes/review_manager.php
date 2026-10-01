<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings;

defined('MOODLE_INTERNAL') || die();

/**
 * The written reviews of an item, from the reviews the BizLMS import brought over (ADR-032).
 *
 * The surface is flag-gated (sentientia.ratings.reviews, OFF by default); this class is the only reader of the
 * table. What it does so the list is safe to show:
 *
 *  - Reviewers are limited to the VIEWER'S tenant (ADR-031, fail closed). The rows carry no tenant; a review
 *    list shows names, so each reviewer's current open_path decides. A viewer with no resolvable tenant sees
 *    nothing, and only a cross-tenant viewer sees every reviewer.
 *  - A blank review (no text, or only white space) and a deleted user's review are never listed. They are
 *    kept in the table (the import keeps history) and simply not shown.
 *  - The text is raw input (BizLMS cleaned nothing on the way in and printed it unescaped), so it is escaped
 *    here as plain text, line breaks kept.
 *  - The star rating beside a review is the reviewer's own rating of the item, joined on (user, item, area).
 *    BizLMS joined it through the like row and printed "N/A" for a reviewer who had not liked; this does not.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class review_manager {

    /** Feature flag of the review list. */
    public const FLAG = 'sentientia.ratings.reviews';

    /** Reviews on one page. */
    public const PERPAGE = 20;

    /**
     * Is the review surface switched on for the current user's customer and tenant?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return class_exists('\local_sentientia_platform\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
    }

    /**
     * One page of an item's reviews, newest first, as the viewer may see them.
     *
     * @param int $itemid
     * @param string $ratearea
     * @param \stdClass $viewer A user record with id and open_path.
     * @param int $page Zero-based.
     * @return array{total: int, reviews: array<int, array{reviewer: string, date: string, hasrating: bool,
     *         rating: int, reviewhtml: string}>}
     */
    public static function get_page(int $itemid, string $ratearea, \stdClass $viewer, int $page = 0): array {
        global $DB;

        $none = ['total' => 0, 'reviews' => []];
        $scope = \local_sentientia_platform\tenant::scope_path($viewer);
        if ($scope === null) {
            // Not cross-tenant and no tenant of their own: nothing, never everything.
            return $none;
        }
        [$tenantsql, $tenantparams] = \local_sentientia_platform\tenant::path_descendant_filter(
            $scope, 'u', 'open_path', 'rvt');

        $from = "FROM {local_sentientia_ratings_reviews} r
                 JOIN {user} u ON u.id = r.userid
            LEFT JOIN {local_sentientia_ratings} g
                   ON g.userid = r.userid AND g.itemid = r.itemid AND g.ratearea = r.ratearea
                WHERE r.itemid = :itemid AND r.ratearea = :area AND u.deleted = 0
                  AND r.review IS NOT NULL
                  AND NOT (" . $DB->sql_isempty('local_sentientia_ratings_reviews', 'r.review', true, true) . ")
                  AND {$tenantsql}";
        $params = ['itemid' => $itemid, 'area' => $ratearea] + $tenantparams;

        $total = (int) $DB->count_records_sql("SELECT COUNT(1) {$from}", $params);
        if ($total === 0) {
            return $none;
        }

        $rows = $DB->get_records_sql(
            "SELECT r.id, r.review, r.timecreated, g.rating,
                    u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
               {$from}
           ORDER BY r.timecreated DESC, r.id DESC",
            $params, max(0, $page) * self::PERPAGE, self::PERPAGE);

        $context = \context_system::instance();
        $reviews = [];
        foreach ($rows as $row) {
            if (trim((string) $row->review) === '') {
                // White space only: not caught by the SQL, and not worth a line.
                continue;
            }
            $reviews[] = [
                'reviewer' => fullname($row),
                'date' => userdate((int) $row->timecreated, get_string('strftimedatefullshort', 'langconfig')),
                'hasrating' => $row->rating !== null && (int) $row->rating > 0,
                'rating' => (int) $row->rating,
                // Escaped as plain text by format_text(FORMAT_PLAIN): never trusted, never raw.
                'reviewhtml' => format_text((string) $row->review, FORMAT_PLAIN,
                    ['context' => $context, 'filter' => false, 'para' => false]),
            ];
        }
        return ['total' => $total, 'reviews' => $reviews];
    }
}
