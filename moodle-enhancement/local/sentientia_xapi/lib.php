<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Library functions for local_sentientia_xapi.
 *
 * Moodle plugin callback hooks. Keep slim — delegate to classes/*.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Called after plugin installation. No-op — install.xml handles schema.
 */
function local_sentientia_xapi_after_install(): void {
    // Seed default retention_days setting.
    set_config('retention_days', 730, 'local_sentientia_xapi');
}

/**
 * Extend the navigation if user has viewstatements capability.
 * Adds an "xAPI Statements" link under Site Admin → Plugins.
 *
 * @param \global_navigation $nav
 */
function local_sentientia_xapi_extend_navigation(\global_navigation $nav): void {
    // Navigation is handled via settings.php externalpage — no additional hook needed.
}
