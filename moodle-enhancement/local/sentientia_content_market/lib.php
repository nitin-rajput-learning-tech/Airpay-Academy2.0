<?php
/**
 * Library functions for local_sentientia_content_market.
 *
 * Thin shim — complex logic lives in classes/. This file satisfies the
 * Moodle plugin loader convention and wires navigation hooks.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a "Content Marketplace" link to the navigation if the feature is enabled.
 *
 * @param global_navigation $nav
 */
function local_sentientia_content_market_extend_navigation(global_navigation $nav): void {
    // Gate on master feature flag.
    if (!class_exists('\local_sentientia_platform\feature_flags')) {
        return;
    }
    if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.content_market.enabled')) {
        return;
    }
    if (!isloggedin() || isguestuser()) {
        return;
    }
    $context = \context_system::instance();
    if (!has_capability('local/sentientia_content_market:view', $context)) {
        return;
    }

    $url  = new \moodle_url('/local/sentientia_content_market/index.php');
    $node = $nav->add(
        get_string('pluginname', 'local_sentientia_content_market'),
        $url,
        global_navigation::TYPE_CUSTOM,
        null,
        'sentientia_content_market',
        new \pix_icon('i/collection', '')
    );
    $node->showinflatnavigation = true;
}
