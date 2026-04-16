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
     * Drop-in replacement for BizLMS display_rating().
     *
     * @param int    $itemid
     * @param string $ratearea
     * @return string HTML
     */
    public static function render(int $itemid, string $ratearea): string {
        $avg = self::get_average($itemid, $ratearea);
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $class = $i <= round($avg->average) ? 'fa fa-star' : 'fa fa-star-o';
            $color = $i <= round($avg->average) ? 'color:#efce2e' : 'color:#9c9b97';
            $stars .= "<i class=\"{$class}\" style=\"{$color};font-size:16px\"></i>";
        }
        $counttext = $avg->count > 0
            ? "<span class=\"text-muted\">({$avg->average} / {$avg->count} ratings)</span>"
            : "<span class=\"text-muted\">" . get_string('noratings', 'local_airpay_ratings') . "</span>";

        return "<div class=\"airpay-rating\">{$stars} {$counttext}</div>";
    }
}
