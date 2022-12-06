<?php

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
 * Web service local plugin template external functions and service definitions.
 *
 * @package    local evalaution
 * @copyright  sreenivas 2017
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_learningplan_submit_learningplan_form' => array(
        'classname'   => 'local_learningplan_external',
        'methodname'  => 'submit_learningplan',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'Submit form',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_learningplan_deleteplan' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'delete_learningplan',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'deletion of learningplans',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_learningplan_toggleplan' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'toggle_learningplan',
        'description' => 'altering status of learningplans',
        'classpath'   => 'local/learningplan/classes/external.php',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_learningplan_lpcourse_enrol_form' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'lpcourse_enrol_form',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'enrol courses to learningplans',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_learningplan_unassign_course' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'lpcourse_unassign_course',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'unasssign courses from learningplans',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_learningplan_unassign_user' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'lpcourse_unassign_user',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'unasssign users from learningplans',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_learningplan_user_learningplans' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'userlearningplans',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'user learningplans',
        'ajax' => true,
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_learningplan_user_learningplancourses' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'userlearningplancourses',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'user learningplan courses',
        'ajax' => true,
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_learningplan_form_option_selector' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'learningplan_form_option_selector',
        'classpath' => 'local/learningplan/classes/external.php',
        'description' => 'All learningplan forms event handling',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_learningplan_get_upcominglps' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'get_upcominglps',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'Get upcoming learningplans',
        'ajax' => true,
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_learningplan_userdashboard_content' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'data_for_learningplans',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'Get user learningplans for dashboard',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_learningplan_userdashboard_content_paginated' => array(
        'classname' => 'local_learningplan_external',
        'methodname' => 'data_for_learningplans_paginated',
        'classpath'   => 'local/learningplan/classes/external.php',
        'description' => 'Get user learningplans for dashboard',
        'ajax' => true,
        'type' => 'read',
    )
);

