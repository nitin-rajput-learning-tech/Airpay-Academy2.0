<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings;

defined('MOODLE_INTERNAL') || die();

/**
 * Like and dislike counts of an item, from the reactions the BizLMS import brought over (ADR-032).
 *
 * BizLMS showed a count beside each thumb on course tiles. The counts here are site-wide per item, as there,
 * and count status 1 (like) and 2 (dislike) only: any other stored value is carried over by the import and is
 * never counted. The surface is flag-gated (sentientia.ratings.reactions, OFF by default); this class is the
 * only reader of the table.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reaction_manager {

    /** Feature flag of the reaction counts. */
    public const FLAG = 'sentientia.ratings.reactions';

    /** likestatus of a like. */
    public const LIKE = 1;

    /** likestatus of a dislike. */
    public const DISLIKE = 2;

    /**
     * Is the reaction surface switched on for the current user's customer and tenant?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return class_exists('\local_sentientia_platform\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
    }

    /**
     * How many learners liked and disliked an item.
     *
     * @param int $itemid
     * @param string $ratearea
     * @return \stdClass {likes: int, dislikes: int}
     */
    public static function get_counts(int $itemid, string $ratearea): \stdClass {
        global $DB;

        $counts = (object) ['likes' => 0, 'dislikes' => 0];
        $rows = $DB->get_records_sql(
            "SELECT likestatus, COUNT(1) AS n
               FROM {local_sentientia_ratings_reactions}
              WHERE itemid = :itemid AND ratearea = :area AND likestatus IN (:like, :dislike)
           GROUP BY likestatus",
            ['itemid' => $itemid, 'area' => $ratearea, 'like' => self::LIKE, 'dislike' => self::DISLIKE]);
        foreach ($rows as $row) {
            if ((int) $row->likestatus === self::LIKE) {
                $counts->likes = (int) $row->n;
            } else {
                $counts->dislikes = (int) $row->n;
            }
        }
        return $counts;
    }
}
