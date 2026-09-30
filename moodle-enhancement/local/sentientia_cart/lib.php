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
 *
 * A thin wrapper: the logic is \local_sentientia_cart\cart_manager::get_course_price(),
 * which the add-to-cart path calls directly, because Moodle does not load this
 * file for a caller that reaches the cart through the autoloader alone.
 */
function local_sentientia_cart_get_course_price(int $courseid): ?float {
    return \local_sentientia_cart\cart_manager::get_course_price($courseid);
}
