<?php
namespace local_airpay_ratings;

defined('MOODLE_INTERNAL') || die();

/**
 * Rating manager — star ratings for courses, classrooms, etc.
 *
 * Replaces BizLMS local_ratings with a clean implementation.
 * Falls back to BizLMS tables during transition.
 *
 * @package    local_airpay_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating_manager {

    private const TABLE = 'local_airpay_ratings';
    private const LEGACY_TABLE = 'local_rating';

    /**
     * Get average rating for an item.
     *
     * @param int    $itemid
     * @param string $ratearea
     * @return object {average, count}
     */
    public static function get_average(int $itemid, string $ratearea): object {
        global $DB;

        $result = (object) ['average' => 0, 'count' => 0];

        // Try Airpay table first.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists(self::TABLE)) {
            $rec = $DB->get_record_sql(
                "SELECT AVG(rating) AS avg_rating, COUNT(id) AS cnt
                   FROM {" . self::TABLE . "}
                  WHERE itemid = :itemid AND ratearea = :area AND rating > 0",
                ['itemid' => $itemid, 'area' => $ratearea]
            );
            if ($rec && $rec->cnt > 0) {
                $result->average = round((float) $rec->avg_rating, 1);
                $result->count = (int) $rec->cnt;
                return $result;
            }
        }

        // Fallback: BizLMS table.
        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            $rec = $DB->get_record_sql(
                "SELECT AVG(rating) AS avg_rating, COUNT(id) AS cnt
                   FROM {" . self::LEGACY_TABLE . "}
                  WHERE itemid = :itemid AND ratearea = :area AND rating > 0",
                ['itemid' => $itemid, 'area' => $ratearea]
            );
            if ($rec && $rec->cnt > 0) {
                $result->average = round((float) $rec->avg_rating, 1);
                $result->count = (int) $rec->cnt;
            }
        }

        return $result;
    }

    /**
     * Get current user's rating for an item.
     *
     * @param int    $itemid
     * @param string $ratearea
     * @param int|null $userid
     * @return int  0-5 (0 = not rated)
     */
    public static function get_user_rating(int $itemid, string $ratearea, ?int $userid = null): int {
        global $DB, $USER;
        $userid = $userid ?? $USER->id;

        $dbman = $DB->get_manager();

        if ($dbman->table_exists(self::TABLE)) {
            $val = $DB->get_field(self::TABLE, 'rating', [
                'itemid' => $itemid, 'ratearea' => $ratearea, 'userid' => $userid,
            ]);
            if ($val !== false) {
                return (int) $val;
            }
        }

        if ($dbman->table_exists(self::LEGACY_TABLE)) {
            $val = $DB->get_field(self::LEGACY_TABLE, 'rating', [
                'itemid' => $itemid, 'ratearea' => $ratearea, 'userid' => $userid,
            ]);
            return $val !== false ? (int) $val : 0;
        }

        return 0;
    }

    /**
     * Render star rating HTML for a course/item.
     *
     * W1-3 (2026-05-15) — stars are now **interactive** buttons wired to the
     * `local_airpay_ratings_submit_rating` web service via the `rating_widget`
     * AMD module. Pages that want clickable ratings need to call:
     *
     *     $PAGE->requires->js_call_amd('local_airpay_ratings/rating_widget', 'init');
     *
     * Pages that want read-only stars can pass $interactive=false; the markup
     * still renders but the AMD module won't pick it up because the
     * `data-airpay-rating` attribute is omitted.
     *
     * @param int    $itemid
     * @param string $ratearea
     * @param bool   $interactive  Default true. Set false for read-only display.
     * @param int|null $userid     Defaults to $USER->id. Pass 0 to skip user lookup.
     * @return string HTML
     */
    public static function render(int $itemid, string $ratearea,
                                   bool $interactive = true, ?int $userid = null): string {
        global $USER;
        $userid = $userid ?? (int) ($USER->id ?? 0);

        $avg = self::get_average($itemid, $ratearea);
        $myrating = ($interactive && $userid > 1)
            ? self::get_user_rating($itemid, $ratearea, $userid) : 0;

        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            // Filled state: prefer the user's own rating, fall back to the
            // rounded average so a never-rated user still sees the consensus.
            $filled = ($myrating > 0) ? ($i <= $myrating) : ($i <= round($avg->average));
            $iconcls = $filled ? 'fa fa-star' : 'fa fa-star-o';
            $color   = $filled ? '#efce2e'   : '#9c9b97';

            if ($interactive) {
                $aria = s(get_string('rateaccessibility', 'local_airpay_ratings', $i));
                $stars .= '<button type="button" class="airpay-rating__star btn btn-link p-0 m-0"'
                    . ' data-rating="' . $i . '" aria-label="' . $aria . '">'
                    . '<i class="' . $iconcls . '" style="color:' . $color . ';font-size:18px"></i>'
                    . '</button>';
            } else {
                $stars .= '<i class="' . $iconcls . '" style="color:' . $color . ';font-size:16px"></i>';
            }
        }

        $counttext = $avg->count > 0
            ? '<span class="airpay-rating__count text-muted">(' . $avg->average
                . ' / ' . $avg->count . ')</span>'
            : '<span class="airpay-rating__count text-muted">'
                . s(get_string('noratings', 'local_airpay_ratings')) . '</span>';

        $extra = $interactive
            ? ' data-airpay-rating data-itemid="' . $itemid
                . '" data-ratearea="' . s($ratearea)
                . '" data-my-rating="' . $myrating . '"'
            : '';

        return '<div class="airpay-rating"' . $extra . '>' . $stars . ' ' . $counttext . '</div>';
    }

    /**
     * W1-3 (2026-05-15) — submit (insert-or-update) a rating for a user.
     *
     * Uses Moodle's $DB API. A small race window exists between the SELECT
     * and the INSERT — if a concurrent rate fires in the same instant, the
     * UNIQUE (userid, itemid, ratearea) index will reject the dup with a
     * `dml_write_exception`. We catch that and retry as an UPDATE, which is
     * the correct semantics anyway (one user, one rating per item).
     *
     * @param int    $itemid
     * @param string $ratearea
     * @param int    $userid    Must be a real user (>1; rejects guest=1 + system=0)
     * @param int    $rating    1-5
     * @return int  ID of the rating row (existing or newly-created)
     * @throws \moodle_exception  If rating is out of bounds or userid is invalid.
     */
    public static function submit_rating(int $itemid, string $ratearea,
                                          int $userid, int $rating): int {
        global $DB;

        if ($rating < 1 || $rating > 5) {
            throw new \moodle_exception('invalidrating', 'local_airpay_ratings');
        }
        if ($userid <= 1) {
            throw new \moodle_exception('cannotrateasguest', 'local_airpay_ratings');
        }
        if ($itemid <= 0) {
            throw new \moodle_exception('invaliditemid', 'local_airpay_ratings');
        }
        if (empty($ratearea) || strlen($ratearea) > 100) {
            throw new \moodle_exception('invalidratearea', 'local_airpay_ratings');
        }

        $key = ['userid' => $userid, 'itemid' => $itemid, 'ratearea' => $ratearea];
        $now = time();

        // Try the update path first (the hot-path for the typical "user
        // revises their rating" case). If no row exists, fall through to
        // insert. Catch the rare race-window dup and retry as update.
        $existing = $DB->get_record(self::TABLE, $key);
        if ($existing) {
            $existing->rating       = $rating;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return (int) $existing->id;
        }

        try {
            return (int) $DB->insert_record(self::TABLE, (object) [
                'itemid'       => $itemid,
                'ratearea'     => $ratearea,
                'userid'       => $userid,
                'rating'       => $rating,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        } catch (\dml_write_exception $e) {
            // UNIQUE collision — a concurrent submit beat us. Re-read and update.
            $existing = $DB->get_record(self::TABLE, $key, '*', MUST_EXIST);
            $existing->rating       = $rating;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return (int) $existing->id;
        }
    }
}
