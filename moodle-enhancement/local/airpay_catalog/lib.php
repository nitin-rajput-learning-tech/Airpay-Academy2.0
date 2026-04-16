<?php
/**
 * Lib functions for airpay catalog — hooks into Moodle page rendering.
 *
 * @package    local_airpay_catalog
 * @copyright  2026 Airpay Payment Services
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject cart count data into every page (for navbar badge).
 * Called by Moodle's before_footer hook.
 */
function local_airpay_catalog_before_footer() {
    global $SESSION;
    $count = count($SESSION->airpay_cart ?? []);
    // Inject a hidden element that the navbar JS reads.
    echo '<span id="ap-cart-count-data" style="display:none;">' . (int)$count . '</span>';
}
