<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

/**
 * Library callbacks for local_sentientia_talent.
 *
 * @package local_sentientia_talent
 */

/**
 * Inject Talent Mobility links into the user/custom navigation — but only
 * when the master feature flag is ON for the viewer's tenant (default OFF
 * means zero footprint on Airpay's current production).
 *
 * @param global_navigation $navigation
 */
function local_sentientia_talent_extend_navigation(global_navigation $navigation): void {
    global $USER;

    if (empty($USER->id) || isguestuser()) {
        return;
    }
    // Feature-flag gate — nothing renders when OFF.
    if (!\local_sentientia_talent\talent_manager::is_enabled()) {
        return;
    }

    $context = context_system::instance();

    // Learner-facing opportunity board (gated by sub-flag + capability).
    if (\local_sentientia_talent\talent_manager::opportunities_enabled()
            && has_capability('local/sentientia_talent:viewopportunities', $context)) {
        $navigation->add(
            get_string('nav_opportunities', 'local_sentientia_talent'),
            new moodle_url('/local/sentientia_talent/opportunities.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'sentientia_talent_opp',
            new pix_icon('i/star', '')
        );
    }

    // HR / manager console.
    if (has_capability('local/sentientia_talent:viewsuccession', $context)
            || has_capability('local/sentientia_talent:managecareerpaths', $context)
            || has_capability('local/sentientia_talent:manageopportunities', $context)) {
        $navigation->add(
            get_string('nav_console', 'local_sentientia_talent'),
            new moodle_url('/local/sentientia_talent/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'sentientia_talent_console',
            new pix_icon('i/users', '')
        );
    }
}
