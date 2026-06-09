<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Hook subscriptions for theme_sentientia.
 *
 * Registers the new-style Moodle 5.2 hook callbacks. The legacy
 * function-name callbacks in lib.php (e.g.
 * `theme_sentientia_before_standard_top_of_body_html`) are intentionally
 * left in place as no-ops on 5.2 (Moodle's `process_legacy_callbacks()`
 * skips them once a new hook subscription is registered) so the same
 * codebase still works on 5.1 deployments that haven't migrated yet.
 *
 * Migration record
 * ----------------
 * Phase B.3 web smoke (2026-05-23) surfaced this deprecation notice:
 *   "Callback before_standard_top_of_body_html in theme_sentientia
 *    component should be migrated to new hook callback for
 *    core\hook\output\before_standard_top_of_body_html_generation"
 * This file is the migration target. See classes/hook_callbacks.php
 * for the call-site implementation.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \theme_sentientia\hook_callbacks::class . '::before_standard_top_of_body_html_generation',
        'priority' => 0,
    ],
];
