<?php
/**
 * Commerce Manager — course pricing, cart, and checkout.
 *
 * Pricing: a course's price is its enabled enrol_fee instance (cost and currency), the same source the order cart
 * (local_sentientia_cart) charges and its set-price tool writes (owner decision cart.price_source, 2026-10-07).
 * The config setting course_price_<id> is only a fallback for a course that has no fee instance.
 * The storefront basket is stored in the session ($SESSION->sentientia_cart), for guests and logged-in users alike.
 *
 * @package    local_sentientia_catalog
 * @copyright  2026 Airpay Payment Services
 */

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

class commerce {

    /**
     * The price a course is sold at, from Moodle's enrol_fee: the cost and currency of its first enabled fee
     * instance that has a cost above zero (lowest sortorder, then lowest id), or null when it has none.
     *
     * This is the order cart's rule (\local_sentientia_cart\cart_manager::get_course_price(): the first enabled
     * enrol_fee instance, ordered by sortorder). The one difference is deliberate and fails closed: the cart looks
     * at the first enabled instance only, this skips an instance whose cost is not above zero, so a course the
     * catalogue shows as paid can at worst be refused by the cart, never given away.
     *
     * @param int $courseid
     * @return array{cost: float, currency: string}|null
     */
    public static function enrol_fee_price(int $courseid): ?array {
        global $DB;
        $instances = $DB->get_records('enrol',
            ['courseid' => $courseid, 'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED],
            'sortorder ASC, id ASC', 'id, cost, currency');
        foreach ($instances as $instance) {
            // enrol.cost is a CHAR column: compare as a number in PHP, not in SQL.
            if (is_numeric($instance->cost) && (float) $instance->cost > 0) {
                $currency = strtoupper(trim((string) $instance->currency));
                return [
                    'cost'     => (float) $instance->cost,
                    'currency' => $currency !== '' ? $currency : 'INR',
                ];
            }
        }
        return null;
    }

    /**
     * Get the course price. Always returns an array; is_free says whether the course is free.
     *
     * cart.price_source (owner decision, 2026-10-07): the enrol_fee instance is the single source of truth, the
     * same as the order cart charges and the cart's own set-price tool writes
     * (local_sentientia_cart\external\set_course_price). The catalogue used to read only the config setting
     * course_price_<id>, which production never sets, so every course priced through enrol_fee (66 in the April
     * 2026 copy, 61 of them Public) read as Free and the basket's "enrollfree" action enrolled it for nothing. Now:
     *
     *   1. an enabled enrol_fee instance with a cost above zero decides (price, currency);
     *   2. otherwise the config setting course_price_<id> is only a fallback for a course that has no fee
     *      instance (it is still "paid" here, and the order cart then refuses it, as before);
     *   3. otherwise the course is free.
     *
     * Paid stays paid: a course production sells is never shown, or enrolled, as free. Reversible.
     */
    public static function get_course_price(int $courseid): array {
        $price = 0;
        $currency = 'INR';
        $is_free = true;
        $source = 'none';

        $fee = self::enrol_fee_price($courseid);
        if ($fee !== null) {
            $price = $fee['cost'];
            $currency = $fee['currency'];
            $is_free = false;
            $source = 'enrol_fee';
        } else {
            // Fallback for a course with no fee instance: the course-level config price.
            $priceconfig = get_config('local_sentientia_catalog', 'course_price_' . $courseid);
            if ($priceconfig !== false && $priceconfig > 0) {
                $price = (float)$priceconfig;
                $is_free = false;
                $source = 'config';
            }
        }

        return [
            'price'        => $price,
            'currency'     => $currency,
            'is_free'      => $is_free,
            'display'      => $is_free ? 'Free' : self::format_price($price, $currency),
            'price_class'  => $is_free ? 'free' : 'paid',
            'price_source' => $source,
        ];
    }

    /**
     * A price for display: the rupee sign for INR, the currency code for any other currency; paise only when the
     * price has them (499 reads "₹499", 499.5 reads "₹499.50").
     *
     * @param float $price
     * @param string $currency
     * @return string
     */
    private static function format_price(float $price, string $currency): string {
        $decimals = abs($price - round($price)) < 0.005 ? 0 : 2;
        $number = number_format($price, $decimals);
        return $currency === 'INR' ? '₹' . $number : $currency . ' ' . $number;
    }

    /**
     * Set course price (admin only).
     */
    public static function set_course_price(int $courseid, float $price): void {
        set_config('course_price_' . $courseid, $price, 'local_sentientia_catalog');
    }

    /**
     * Get cart contents from session.
     * Works for both guests and logged-in users.
     */
    public static function get_cart(): array {
        global $SESSION;
        return $SESSION->sentientia_cart ?? [];
    }

    /**
     * Add a course to cart.
     */
    public static function add_to_cart(int $courseid): bool {
        global $SESSION;

        if (!isset($SESSION->sentientia_cart)) {
            $SESSION->sentientia_cart = [];
        }

        // Check if already in cart.
        foreach ($SESSION->sentientia_cart as $item) {
            if ($item['courseid'] === $courseid) {
                return false; // Already in cart.
            }
        }

        // C2 fix (2026-09-03, docs/security/UAT-SECURITY-POSTURE-2026-09-03.md):
        // this used to be $DB->get_record('course', ['id' => $courseid,
        // 'visible' => 1], ...) — no tenant scoping — which let a
        // Public-tenant (/77) learner queue an Airpay/ZEEA internal course
        // into their cart by id. Reuses the same gate as course.php and
        // enrolment::enrol_now(). Fail closed: any denial (including a
        // non-existent course) just refuses the add, same as the old
        // "not found" behaviour.
        try {
            $course = catalog_manager::assert_course_visible_to_viewer($courseid);
        } catch (\moodle_exception $e) {
            return false;
        }

        $pricing = self::get_course_price($courseid);

        $SESSION->sentientia_cart[] = [
            'courseid'  => $courseid,
            'fullname'  => format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'price'     => $pricing['price'],
            'display'   => $pricing['display'],
            'is_free'   => $pricing['is_free'],
            'added'     => time(),
        ];

        return true;
    }

    /**
     * Remove a course from cart.
     */
    public static function remove_from_cart(int $courseid): void {
        global $SESSION;
        if (!isset($SESSION->sentientia_cart)) return;

        $SESSION->sentientia_cart = array_values(array_filter(
            $SESSION->sentientia_cart,
            fn($item) => $item['courseid'] !== $courseid
        ));
    }

    /**
     * Get cart count (for navbar badge).
     */
    public static function get_cart_count(): int {
        global $SESSION;
        return count($SESSION->sentientia_cart ?? []);
    }

    /**
     * Get cart total.
     */
    public static function get_cart_total(): array {
        $cart = self::get_cart();
        $total = 0;
        $free_count = 0;
        $paid_count = 0;

        foreach ($cart as $item) {
            if ($item['is_free']) {
                $free_count++;
            } else {
                $total += $item['price'];
                $paid_count++;
            }
        }

        return [
            'total'      => $total,
            'display'    => $total > 0 ? '₹' . number_format($total, 0) : 'Free',
            'count'      => count($cart),
            'free_count' => $free_count,
            'paid_count' => $paid_count,
            'all_free'   => ($paid_count === 0),
        ];
    }

    /**
     * Clear the cart.
     */
    public static function clear_cart(): void {
        global $SESSION;
        $SESSION->sentientia_cart = [];
    }

    /**
     * Get featured courses for public homepage (admin-configured).
     * Returns courses marked as "featured" for the public catalog.
     */
    public static function get_homepage_courses(int $limit = 6): array {
        global $DB, $CFG;

        // Get admin-selected featured course IDs.
        $featured_ids = get_config('local_sentientia_catalog', 'homepage_featured_courses');
        if (!empty($featured_ids)) {
            $ids = array_map('intval', array_filter(explode(',', $featured_ids)));
            if (!empty($ids)) {
                [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'fid');
                $courses = $DB->get_records_sql(
                    "SELECT c.id, c.fullname, c.shortname, c.summary, c.summaryformat,
                            COUNT(DISTINCT ue.userid) as enrolcount
                       FROM {course} c
                  LEFT JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
                  LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                      WHERE c.id $insql AND c.visible = 1
                   GROUP BY c.id, c.fullname, c.shortname, c.summary, c.summaryformat
                   ORDER BY FIELD(c.id, " . implode(',', $ids) . ")",
                    $params, 0, $limit);

                // Add pricing to each.
                $result = [];
                foreach ($courses as $c) {
                    $pricing = self::get_course_price($c->id);
                    $result[] = array_merge((array)$c, $pricing, [
                        'detailurl' => (new \moodle_url('/local/sentientia_catalog/course.php', ['id' => $c->id]))->out(false),
                    ]);
                }
                return $result;
            }
        }

        // Fallback: return top enrolled public tenant courses.
        return [];
    }

    /**
     * Get all public catalog courses with pricing (for guest browsing).
     */
    public static function get_public_catalog(string $search = '', string $sort = 'popular',
                                               int $page = 0, int $perpage = 12): array {
        global $DB;

        $public_tid = (int)get_config('local_sentientia_pages', 'public_tenant_id') ?: 77;
        $publicroot = '/' . $public_tid;

        $searchfilter = '';
        // /-boundary so /77 doesn't match /770, /777, etc.
        $params = [
            'pubexact'  => $publicroot,
            'pubprefix' => $DB->sql_like_escape($publicroot) . '/%',
        ];
        if (!empty($search)) {
            $searchfilter = "AND (c.fullname LIKE :s1 OR c.shortname LIKE :s2 OR c.summary LIKE :s3)";
            $searchterm = '%' . $DB->sql_like_escape($search) . '%';
            $params['s1'] = $searchterm;
            $params['s2'] = $searchterm;
            $params['s3'] = $searchterm;
        }

        $orderby = match($sort) {
            'newest' => 'c.timecreated DESC',
            'name' => 'c.fullname ASC',
            default => 'enrolcount DESC',
        };

        // CRS-14 (2026-10-07): the guest storefront lists ordinary courses only, in the COUNT and in the SELECT alike, so
        // a BizLMS exam or forum pseudo-course (open_coursetype 1) is neither offered nor counted. Exams have no fee
        // instance and guest and self enrolment are disabled on them, so a guest who saw one could neither buy nor join.
        $ordinary = catalog_manager::ordinary_courses_condition('c');

        $total = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course} c
             WHERE c.visible = 1 AND c.id > 1 AND $ordinary
               AND (c.open_path = :pubexact OR c.open_path LIKE :pubprefix) $searchfilter",
            $params);

        // The popularity count is learners, not enrolment rows: an imported BizLMS enrolment and its converted manual twin
        // are one learner, and a suspended enrolment or a disabled instance gives no seat (owner decision 2026-10-07).
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.summary, c.summaryformat, c.timecreated,
                    COUNT(DISTINCT ue.userid) as enrolcount
               FROM {course} c
          LEFT JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
          LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
              WHERE c.visible = 1 AND c.id > 1 AND $ordinary
                AND (c.open_path = :pubexact OR c.open_path LIKE :pubprefix) $searchfilter
           GROUP BY c.id, c.fullname, c.shortname, c.summary, c.summaryformat, c.timecreated
           ORDER BY $orderby",
            $params, $page * $perpage, $perpage);

        // Add pricing.
        $result = [];
        foreach ($courses as $c) {
            $pricing = self::get_course_price($c->id);
            $summary = shorten_text(strip_tags(format_string($c->summary)), 120);
            $result[] = array_merge((array)$c, $pricing, [
                'summary_short' => $summary,
                'enrolled_count' => (int)$c->enrolcount,
                'detailurl' => (new \moodle_url('/local/sentientia_catalog/course.php', ['id' => $c->id]))->out(false),
            ], catalog_manager::course_poster((int)$c->id));
        }

        return [
            'courses'  => $result,
            'total'    => $total,
            'page'     => $page,
            'perpage'  => $perpage,
            'pages'    => ceil($total / $perpage),
            'has_more' => (($page + 1) * $perpage) < $total,
        ];
    }
}
