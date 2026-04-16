<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Display star rating for an item — drop-in for BizLMS display_rating().
 *
 * @param int    $itemid   Course ID or other item ID
 * @param string $ratearea Plugin context string
 * @return string HTML
 */
function airpay_display_rating(int $itemid, string $ratearea): string {
    return \local_airpay_ratings\rating_manager::render($itemid, $ratearea);
}
