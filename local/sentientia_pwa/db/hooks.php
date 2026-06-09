<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hook subscriptions for local_sentientia_pwa.
 *
 * Registers the new-style Moodle 5.2 hook callbacks. The legacy
 * function-name callback
 * `local_sentientia_pwa_before_standard_top_of_body_html()` in lib.php
 * is preserved as a thin shim so the same plugin still works on 5.1
 * deployments — on 5.2 it becomes a no-op because Moodle's
 * `process_legacy_callbacks()` skips function-name callbacks once a
 * matching hook subscription is registered.
 *
 * Migration record
 * ----------------
 * Phase B.3 web smoke (2026-05-23) on Moodle 5.2 surfaced this
 * deprecation notice:
 *   "Callback before_standard_top_of_body_html in local_sentientia_pwa
 *    component should be migrated to new hook callback for
 *    core\hook\output\before_standard_top_of_body_html_generation"
 * This file is the migration target. See classes/hook_callbacks.php
 * for the call-site implementation.
 *
 * @package    local_sentientia_pwa
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \local_sentientia_pwa\hook_callbacks::class . '::before_standard_top_of_body_html_generation',
        'priority' => 0,
    ],
];
