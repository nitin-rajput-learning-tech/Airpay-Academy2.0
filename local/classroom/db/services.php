<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms
 * @subpackage local_classroom
 */
defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_classroom_get_classrooms' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_classrooms',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'returns classrooms based on the status send',
        'ajax' => true,
        'type' => 'read'
    ),
    'local_classroom_submit_instance' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroom_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_classroom_deleteclassroom' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'delete_classroom_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_classroom_form_course_selector' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroom_course_selector',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_classroom_deletesession' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'delete_session_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_classroom_deleteclassroomevaluation' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'delete_classroomevaluation_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write',
    ),
    'local_classroom_form_option_selector' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroom_form_option_selector',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_classroom_session_submit_instance' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroom_session_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_classroom_course_submit_instance' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroom_course_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_classroom_deleteclassroomcourse' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'delete_classroomcourse_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write',
    ),
    // 'local_classroom_createcategory' => array(
    //     'classname' => 'local_classroom_external',
    //     'methodname' => 'createcategory_instance',
    //     'classpath' => 'local/classroom/externallib.php',
    //     // 'description' => 'All class room forms event handling',
    //     'ajax' => true,
    //     'type' => 'write',
    // ),
    'local_classroom_completion_settings_submit_instance' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroom_completion_settings_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
     'local_classroom_manageclassroomStatus' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'manageclassroomStatus_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_classroomviewsessions' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomviewsessions',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read'
    ),
     'local_classroom_classroomviewcourses' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomviewcourses',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read'
    ),
      'local_classroom_classroomviewusers' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomviewusers',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read'
    ),
       'local_classroom_classroomviewfeedbacks' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomviewfeedbacks',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read'
    ),
        'local_classroom_classroomviewcompletioninfo' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomviewcompletioninfo',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read'
    ),
        'local_classroom_classroomviewtargetaudience' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomviewtargetaudience',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read'
    ),

    'local_classroom_classroomviewrequestedusers' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomviewrequestedusers',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read'
    ),
    'local_classroom_classroomlastchildpopup' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomlastchildpopup',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'last form handling',
        'ajax' => true,
        'type' => 'read'
    ),
    'local_classroom_unenrollclassroom' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'unenroll_classroom_instance',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'write'
    ),
    'local_classroom_classroomviewwaitinglistusers' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomviewwaitinglistusers',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'All class room forms event handling',
        'ajax' => true,
        'type' => 'read'
    ),
    'local_classroom_get_mobile_classrooms' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_user_classrooms',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'Get user Classrooms',
        'ajax' => true,
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_get_classroom_sessions' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_classroom_sessions',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get sessions list',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_get_weekly_sessions' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_weekly_sessions',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get week wise sessions list',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_get_today_sessions' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_today_sessions',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get today sessions list',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_get_classroom_sessions_page' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_classroom_sessions_page',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get sessions list',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_get_classroom_courses' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_classroom_courses',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get courses list',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_get_classroom_trainers' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_classroom_trainers',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get trainers list',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_get_classroom_completions' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_classroom_completions',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get completions',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_get_classroom_feedbacks' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'classroomfeedbacks',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get feedbacks',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_userdashboard_content' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'data_for_classrooms',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'User enrolled classroom info for userdashboard',
        'ajax' => true,
        'type' => 'read'
    ),
    'local_classroom_userdashboard_content_paginated' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'data_for_classrooms_paginated',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'User enrolled classroom info for userdashboard',
        'ajax' => true,
        'type' => 'read'
    ),
    'local_classroom_get_sessions_by_daytype' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_sessions_by_daytype',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get sessions list by day type',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_classroom_get_classroom_info' => array(
        'classname' => 'local_classroom_external',
        'methodname' => 'get_classroom_info',
        'classpath' => 'local/classroom/externallib.php',
        'description' => 'get classroom info',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    )
);