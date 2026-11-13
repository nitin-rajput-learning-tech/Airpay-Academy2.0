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
 * Privacy provider for enrol_program.
 *
 * @package    enrol_program
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_program\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * enrol_program stores no personal data: a copy of enrol_self (itself a null_provider): no tables; enrolments live in core user_enrolments, handled by core_enrol.
 *
 * Added 2026-11-13. The plugin had no privacy provider, so core's
 * test_all_providers_compliant failed for it and the privacy registry could
 * not say whether it held personal data.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * The language string that explains why this plugin stores no data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
