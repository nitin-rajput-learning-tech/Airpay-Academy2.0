<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings;

defined('MOODLE_INTERNAL') || die();

/**
 * What the item reviews page shows (reviews.php): an item's average rating, how many learners liked and
 * disliked it, and the written reviews learners left, as the data of the review_list template.
 *
 * The average is always the one rating_manager computes; the other two parts are the NEW surfaces the BizLMS
 * import fills, and each ships behind its own flag, OFF by default (db/feature_flags.php). With both off there
 * is no page at all.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_summary {

    /**
     * The template data for an item, or null when both surfaces are switched off.
     *
     * @param int $itemid
     * @param string $ratearea One of rating_manager::AREAS.
     * @param \stdClass $viewer The user looking, with id and open_path.
     * @param int $page Zero-based page of reviews.
     * @return array|null Template data; the key total is the number of reviews the viewer may see, for paging.
     * @throws \moodle_exception invalidratearea or invaliditemid for a value the page does not accept.
     */
    public static function export(int $itemid, string $ratearea, \stdClass $viewer, int $page = 0): ?array {
        $showreviews = review_manager::enabled();
        $showreactions = reaction_manager::enabled();
        if (!$showreviews && !$showreactions) {
            return null;
        }
        if ($itemid <= 0) {
            throw new \moodle_exception('invaliditemid', 'local_sentientia_ratings');
        }
        if (!in_array($ratearea, rating_manager::AREAS, true)) {
            throw new \moodle_exception('invalidratearea', 'local_sentientia_ratings');
        }

        $average = rating_manager::get_average($itemid, $ratearea);
        $data = [
            'hasaverage' => $average->count > 0,
            'averagetext' => get_string('averagesummary', 'local_sentientia_ratings',
                (object) ['average' => $average->average, 'count' => $average->count]),
            'showreactions' => $showreactions,
            'showreviews' => $showreviews,
            'total' => 0,
            'hasreviews' => false,
            'reviews' => [],
        ];

        if ($showreactions) {
            $counts = reaction_manager::get_counts($itemid, $ratearea);
            $data['likestext'] = get_string('reactionlikes', 'local_sentientia_ratings', $counts->likes);
            $data['dislikestext'] = get_string('reactiondislikes', 'local_sentientia_ratings', $counts->dislikes);
        }

        if ($showreviews) {
            $result = review_manager::get_page($itemid, $ratearea, $viewer, $page);
            $data['total'] = $result['total'];
            foreach ($result['reviews'] as $review) {
                $review['ratingtext'] = $review['hasrating']
                    ? get_string('reviewrating', 'local_sentientia_ratings', $review['rating']) : '';
                $data['reviews'][] = $review;
            }
            $data['hasreviews'] = !empty($data['reviews']);
        }
        return $data;
    }
}
