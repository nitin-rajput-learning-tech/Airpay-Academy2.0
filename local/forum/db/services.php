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
 * local forum
 *
 * @package    local_forum
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_forum_submit_create_forum_form' => array(
        'classname'   => 'local_forum_external',
        'methodname'  => 'submit_create_forum_form',
        'classpath'   => 'local/forum/classes/external.php',
        'description' => 'Submit form',
        'type'        => 'write',
        'ajax' => true,
    ),
    
    'local_forum_deleteforum' => array(
        'classname' => 'local_forum_external',
        'methodname' => 'delete_forum',
        'classpath'   => 'local/forum/classes/external.php',
        'description' => 'deletion of forum',
        'ajax' => true,
        'type' => 'write'
    ),
    // 'local_forum_form_option_selector' => array(
    //     'classname' => 'local_forum_external',
    //     'methodname' => 'global_filters_form_option_selector',
    //     'classpath' => 'local/forum/classes/external.php',
    //     'description' => 'All global filters forms event handling',
    //     'ajax' => true,
    //     'type' => 'read',
    // ), 
    'local_forum_forum_view' => array(
        'classname' => 'local_forum_external',
        'methodname' => 'forum_view',
        'classpath' => 'local/forum/classes/external.php',
        'description' => 'List all forum in card view',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_forum_course_update_status' => array(
        'classname' => 'local_forum_external',
        'methodname' => 'course_update_status',
        'classpath' => 'local/forum/classes/external.php',
        'description' => 'List all forum in card view',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_forum_userdashboard_content' => array(
        'classname'    => 'local_forum_external',
        'methodname'   => 'data_for_forum',
        'classpath'    => 'local/forum/classes/external.php',
        'description'  => 'Load the data for the elearning forum in Userdashboard.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'local_forum_userdashboard_content_paginated' => array(
        'classname'    => 'local_forum_external',
        'methodname'   => 'data_for_forum_paginated',
        'classpath'    => 'local/forum/classes/external.php',
        'description'  => 'Load the data for the elearning forum in Userdashboard.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'local_forum_subscribe' => array(
        'classname' => 'local_forum_external',
        'methodname' => 'forum_subscribe',
        'classpath' => 'local/forum/classes/external.php',
        'description' => 'List all forum in card view',
        'ajax' => true,
        'type' => 'read',
    ),

   );

