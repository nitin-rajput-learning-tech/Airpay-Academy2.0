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
 * @package   local
 * @subpackage  users
 * @author eabyas  <info@eabyas.in>
 **/

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_users_submit_create_user_form' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'submit_create_user_form',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Submit form',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_users_delete_user' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'delete_user',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'deleting of user',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_users_suspend_user' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'suspend_local_user',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'suspending of user',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_users_get_departments_list' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'get_departments_list',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Get Departments List',
        'type'        => 'read',
        'ajax' => true,
    ),
    'local_users_get_supervisors_list' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'get_supervisors_list',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Get Supervisors List',
        'type'        => 'read',
        'ajax' => true,
    ),
    'local_users_manageusers_view' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'manageusersview',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Display the Users Page',
        'type'        => 'write',
        'ajax' => true
    ),
    'local_users_syncerrors_view' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'managesyncerrors',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Display the Sync errors Page',
            'type'        => 'write',
        'ajax' => true
    ),
    'local_users_syncstatics_view' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'managesyncstatics',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Display the Sync Statics Page',
        'type'        => 'write',
        'ajax' => true
    ),
    'local_users_delete_syncstatics' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'deletesyncstatics',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'delete the Sync Statics Page',
        'type'        => 'write',
        'ajax' => true
    ),
    'local_users_profile_moduledata' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'profilemoduledata',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'display module data in profile',
        'type'        => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_users_profile_data' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'profiledata',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'display the profile data',
        'type'        => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_users_dashboard_stats' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'dashboard_stats',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Dashboard stats for mobile',
        'type'        => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_users_pending_activities' => array(
        'classname' => 'local_users_external',
        'methodname' => 'pending_activities',
        'description' => 'Get pending_activities',
        'classpath' => 'local/users/classes/external.php',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_users_get_grade_items' => array(
        'classname' => 'local_users_external',
        'methodname' => 'get_grade_items',
        'classpath' => 'local/users/classes/externallib.php',
        'description' => 'Returns the complete list of grade items for users in a course',
        'type' => 'read',
        'capabilities' => 'gradereport/user:view',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_users_get_course_grades' => array(
        'classname' => 'local_users_external',
        'methodname' => 'get_course_grades',
        'classpath' => 'local/users/classes/externallib.php',
        'description' => 'Get the given user courses final grades',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_users_get_positions_list' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'get_positions_list',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Get Positions List',
        'type'        => 'read',
        'ajax' => true,
    ),
    'local_users_get_domains_list' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'get_domains_list',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Get Positions List',
        'type'        => 'read',
        'ajax' => true,
    ),
    'local_users_profile_skilldata' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'profileskilldata',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'display module data in profile',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_users_skillprofile' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'profileskilldatatabs',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'display module data in profile',
        'type'        => 'write',
        'ajax' => true,
    ),
     'local_users_create_update_user' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'local_create_update_user',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Create or update profile',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_users_form_option_selector' => array(
        'classname'   => 'local_users_external',
        'methodname'  => 'form_option_selector',
        'classpath'   => 'local/users/classes/external.php',
        'description' => 'Get dynamic form options related to users',
        'type'        => 'read',
        'ajax' => true,
    ),
);
