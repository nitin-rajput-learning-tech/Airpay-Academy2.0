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
    // CRS-11 (2026-10-07): interactive only behind the flag sentientia.ratings.widget, read-only otherwise.
    return \local_sentientia_ratings\rating_manager::render_for_viewer($itemid, $ratearea);
}
