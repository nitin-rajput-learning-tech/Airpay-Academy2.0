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
 * Web service for mod assign
 * @package    local_program
 * @subpackage db
 * @since      Moodle 2.4
 * @copyright  2018 Arun Kumar M <arun@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_program_submit_instance' => array(
        'classname' => 'local_program_external',
        'methodname' => 'program_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_deleteprogram' => array(
        'classname' => 'local_program_external',
        'methodname' => 'delete_program_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_form_course_selector' => array(
        'classname' => 'local_program_external',
        'methodname' => 'program_course_selector',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_program_deletesession' => array(
        'classname' => 'local_program_external',
        'methodname' => 'delete_session_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_form_option_selector' => array(
        'classname' => 'local_program_external',
        'methodname' => 'program_form_option_selector',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_program_session_submit_instance' => array(
        'classname' => 'local_program_external',
        'methodname' => 'program_session_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_course_submit_instance' => array(
        'classname' => 'local_program_external',
        'methodname' => 'program_course_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_deleteprogramcourse' => array(
        'classname' => 'local_program_external',
        'methodname' => 'delete_programcourse_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write',
    ),
    // 'local_program_createcategory' => array(
    //     'classname' => 'local_program_external',
    //     'methodname' => 'createcategory_instance',
    //     'classpath' => 'local/program/externallib.php',
    //     // 'description' => 'All class room forms event handling',
    //     'ajax' => true,
    //     'type' => 'write',
    // ),
    'local_program_completion_settings_submit_instance' => array(
        'classname' => 'local_program_external',
        'methodname' => 'program_completion_settings_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_addlevel_submit_instance' => array(
        'classname' => 'local_program_external',
        'methodname' => 'manageprogramlevels',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_unassign_course' => array(
        'classname' => 'local_program_external',
        'methodname' => 'bclevel_unassign_course',
        'classpath' => 'local/program/externallib.php',
        'description' => 'unasssign courses from program level',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_session_enrolments' => array(
        'classname' => 'local_program_external',
        'methodname' => 'bc_session_enrolments',
        'classpath' => 'local/program/externallib.php',
        'description' => 'Session enrolments',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_deletelevel' => array(
        'classname' => 'local_program_external',
        'methodname' => 'delete_level_instance',
        'classpath' => 'local/program/externallib.php',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_stream_submit_instance' => array(
        'classname' => 'local_program_external',
        'methodname' => 'manageprogramstreams',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_deletestream' => array(
        'classname' => 'local_program_external',
        'methodname' => 'delete_stream_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_manageprogramStatus' => array(
        'classname' => 'local_program_external',
        'methodname' => 'manageprogramStatus_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_inactiveprogram' => array(
        'classname' => 'local_program_external',
        'methodname' => 'inactive_program_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_activeprogram' => array(
        'classname' => 'local_program_external',
        'methodname' => 'active_program_instance',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_program_streams' => array(
        'classname' => 'local_program_external',
        'methodname' => 'organization_streams',
        'classpath' => 'local/program/externallib.php',
        'description' => 'All programs display event handling',
        'ajax' => true,
        'type' => 'read'
    ),
    'local_program_userdashboard_content' => array(
        'classname'    => 'local_program_external',
        'methodname'   => 'data_for_programs',
        'classpath'    => 'local/program/externallib.php',
        'description'  => 'Load the data for the program courses.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'local_program_userdashboard_content_paginated' => array(
        'classname'    => 'local_program_external',
        'methodname'   => 'data_for_programs_paginated',
        'classpath'    => 'local/program/externallib.php',
        'description'  => 'Load the data for the program courses.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'local_program_unenrol_user' => array(
        'classname'    => 'local_program_external',
        'methodname'   => 'unenrol_user',
        'classpath'    => 'local/program/externallib.php',
        'description'  => 'Unenrol user to the program.',
        'type'         => 'write',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'local_program_program_completion_settings_submit_instance' => array(
        'classname'    => 'local_program_external',
        'methodname'   => 'program_completion_settings',
        'classpath'    => 'local/program/externallib.php',
        'description'  => 'Set Program completion criteria.',
        'type'         => 'write',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'local_program_level_completion_settings_submit_instance' => array(
        'classname'    => 'local_program_external',
        'methodname'   => 'level_completion_settings',
        'classpath'    => 'local/program/externallib.php',
        'description'  => 'Set Program level completion criteria.',
        'type'         => 'write',
        'capabilities' => '',
        'ajax'         => true,
    ),
);