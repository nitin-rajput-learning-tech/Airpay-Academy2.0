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
 * Web service definitions for local_airpay_courses.
 *
 * @package    local_airpay_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_courses_toggle_visibility' => [
        'classname'    => 'local_airpay_courses\external\toggle_visibility',
        'description'  => 'Show or hide a course',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_courses:visibility',
    ],
    'local_airpay_courses_delete_course' => [
        'classname'    => 'local_airpay_courses\external\delete_course',
        'description'  => 'Delete a course',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_courses:delete',
    ],
];
