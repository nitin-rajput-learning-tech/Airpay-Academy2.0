<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Library callbacks of local_sentientia_platform.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Status checks this plugin adds to Site administration > Reports > Status
 * and to admin/cli/checks.php (ADR-032: the BizLMS import).
 *
 * @return \core\check\check[]
 */
function local_sentientia_platform_status_checks(): array {
    return [new \local_sentientia_platform\check\bizlms_import()];
}
