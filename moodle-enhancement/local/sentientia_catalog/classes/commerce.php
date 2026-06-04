<?php
/**
 * Commerce Manager — course pricing, cart, and checkout.
 *
 * Pricing is stored as user preferences (admin-set) on a per-course basis
 * using Moodle's custom fields or a lightweight custom table.
 * Cart is stored in session (guest) or user preferences (logged-in).
 *
 * @package    local_sentientia_catalog
 * @copyright  2026 Airpay Payment Services
 */

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

class commerce {

    /**
     * Get course price. Returns null if free, or price array if paid.
     * Pricing stored in course custom field 'open_price' or default 0 (free).
     */
    public static function get_course_price(int $courseid): array {
        global $DB;

        // Check if course has a price set (using existing open_ fields or custom config).
        $price = 0;
        $currency = 'INR';
        $is_free = true;

        // Try course-level price from config table.
        $priceconfig = get_config('local_sentientia_catalog', 'course_price_' . $courseid);
        if ($priceconfig !== false && $priceconfig > 0) {
            $price = (float)$priceconfig;
            $is_free = false;
        }

        return [
            'price'       => $price,
            'currency'    => $currency,
            'is_free'     => $is_free,
            'display'     => $is_free ? 'Free' : '₹' . number_format($price, 0),
            'price_class' => $is_free ? 'free' : 'paid',
        ];
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
        return $SESSION->airpay_cart ?? [];
    }

    /**
     * Add a course to cart.
     */
    public static function add_to_cart(int $courseid): bool {
        global $SESSION, $DB;

        if (!isset($SESSION->airpay_cart)) {
            $SESSION->airpay_cart = [];
        }

        // Check if already in cart.
        foreach ($SESSION->airpay_cart as $item) {
            if ($item['courseid'] === $courseid) {
                return false; // Already in cart.
            }
        }

        $course = $DB->get_record('course', ['id' => $courseid, 'visible' => 1], 'id, fullname, shortname');
        if (!$course) {
            return false;
        }

        $pricing = self::get_course_price($courseid);

        $SESSION->airpay_cart[] = [
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
        if (!isset($SESSION->airpay_cart)) return;

        $SESSION->airpay_cart = array_values(array_filter(
            $SESSION->airpay_cart,
            fn($item) => $item['courseid'] !== $courseid
        ));
    }

    /**
     * Get cart count (for navbar badge).
     */
    public static function get_cart_count(): int {
        global $SESSION;
        return count($SESSION->airpay_cart ?? []);
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
        $SESSION->airpay_cart = [];
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
                            COUNT(ue.id) as enrolcount
                       FROM {course} c
                  LEFT JOIN {enrol} e ON e.courseid = c.id
                  LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id
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

        $total = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course} c
             WHERE c.visible = 1 AND c.id > 1 AND (c.open_path = :pubexact OR c.open_path LIKE :pubprefix) $searchfilter",
            $params);

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.summary, c.summaryformat, c.timecreated,
                    COUNT(ue.id) as enrolcount
               FROM {course} c
          LEFT JOIN {enrol} e ON e.courseid = c.id
          LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id
              WHERE c.visible = 1 AND c.id > 1 AND (c.open_path = :pubexact OR c.open_path LIKE :pubprefix) $searchfilter
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
