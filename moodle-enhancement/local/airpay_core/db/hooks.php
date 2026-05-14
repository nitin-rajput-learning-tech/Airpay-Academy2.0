<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hook callback registration for local_airpay_core.
 *
 * Currently registers one hook:
 *   - before_footer_html_generation — injects the mobile bottom-nav for
 *     logged-in non-guest users on screens <590px. (Phase B0 Player iter 5)
 *
 * @package local_airpay_core
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\output\before_footer_html_generation::class,
        'callback' => 'local_airpay_core\\hook_callbacks::inject_mobile_bottom_nav',
        // Run AFTER local_airpay_assistant so the assistant fab can read
        // the bottom-nav's height and position itself above it (CSS
        // already handles the offset via env(safe-area-inset-bottom)).
        'priority' => 100,
    ],
];
