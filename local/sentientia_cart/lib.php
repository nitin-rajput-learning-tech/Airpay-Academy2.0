<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Add cart link to the user menu (top-right).
 *
 * Only show for users in tenants where cart is enabled (see admin setting
 * "enabled_tenants"). Airpay tenant employees see training as a benefit
 * so cart is typically disabled there.
 */
function local_sentientia_cart_extend_navigation_user_settings(navigation_node $navigation, $user, $context) {
    global $USER;
    if ($USER->id != $user->id) {
        return;
    }
    if (!\local_sentientia_cart\cart_manager::is_enabled_for_user($USER)) {
        return;
    }
    $url = new moodle_url('/local/sentientia_cart/index.php');
    $navigation->add(get_string('mycartlong', 'local_sentientia_cart'), $url,
        navigation_node::TYPE_SETTING, null, 'airpaycart',
        new pix_icon('i/shoppingcart', ''));
}

/**
 * Pricing helper — get price for a course.
 * Returns null if course is free / not for sale.
 */
function local_sentientia_cart_get_course_price(int $courseid): ?float {
    global $DB;
    // Use Moodle's "enrol_fee" plugin record on the course if present.
    // Fall back to a custom field "courseprice" if admin set one.
    $instance = $DB->get_record_sql(
        "SELECT cost, currency FROM {enrol}
          WHERE courseid = :cid AND enrol = 'fee' AND status = 0
          ORDER BY sortorder ASC LIMIT 1",
        ['cid' => $courseid]
    );
    if ($instance && !empty($instance->cost) && (float) $instance->cost > 0) {
        return (float) $instance->cost;
    }
    return null;  // free / not for sale
}
