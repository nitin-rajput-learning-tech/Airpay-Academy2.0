<?php
/**
 * Hook callbacks for local_sentientia_catalog.
 *
 * Replaces the deprecated `local_sentientia_catalog_before_footer()` function
 * (Moodle pre-5.x callback pattern) with the Moodle 5.x hook system.
 *
 * @package    local_sentientia_catalog
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for output-related hooks.
 */
class hook_callbacks {

    /**
     * Inject cart count data into the page footer (for navbar badge).
     *
     * Migrated from the legacy `before_footer` function (Moodle 5.x hook system).
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $SESSION;
        $count = count($SESSION->airpay_cart ?? []);
        $hook->add_html('<span id="ap-cart-count-data" style="display:none;">' . (int)$count . '</span>');
    }
}
