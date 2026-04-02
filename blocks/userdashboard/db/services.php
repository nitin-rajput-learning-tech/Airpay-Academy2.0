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
 * Learning plan webservice functions.
 *
 *
 * @package    block_userdashboard
 * @copyright  2018 hemalataha c arun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
  
    // added by hema
    'block_userdashboard_data_for_elearning_courses' => array(
        'classname'    => 'block_userdashboard\external',
        'methodname'   => 'data_for_elearning_courses',
        'classpath'    => '',
        'description'  => 'Load the data for the elearning courses.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
     
    ),

    'block_userdashboard_data_for_classroom_courses' => array(
        'classname'    => 'block_userdashboard\external',
        'methodname'   => 'data_for_classroom_courses',
        'classpath'    => '',
        'description'  => 'Load the data for the classroom courses.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
     
    ),
    'block_userdashboard_data_for_program_courses' => array(
        'classname'    => 'block_userdashboard\external',
        'methodname'   => 'data_for_program_courses',
        'classpath'    => '',
        'description'  => 'Load the data for the program courses.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'block_userdashboard_data_for_certification_courses' => array(
        'classname'    => 'block_userdashboard\external',
        'methodname'   => 'data_for_certification_courses',
        'classpath'    => '',
        'description'  => 'Load the data for the certification courses.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'block_userdashboard_data_for_xseed' => array(
        'classname'    => 'block_userdashboard\external',
        'methodname'   => 'data_for_xseed',
        'classpath'    => '',
        'description'  => 'Load the data for the xseed.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'block_userdashboard_data_for_learningplan_courses' => array(
        'classname'    => 'block_userdashboard\external',
        'methodname'   => 'data_for_learningplan_courses',
        'classpath'    => '',
        'description'  => 'Load the data for the learningplan courses.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'block_userdashboard_data_for_evaluation_courses' => array(
        'classname'    => 'block_userdashboard\external',
        'methodname'   => 'data_for_evaluation_courses',
        'classpath'    => '',
        'description'  => 'Load the data for the feedback courses.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
    'block_userdashboard_data_for_onlinetest_courses' => array(
        'classname'    => 'block_userdashboard\external',
        'methodname'   => 'data_for_onlinetests_courses',
        'classpath'    => '',
        'description'  => 'Load the data for the onlinetests courses.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true,
    ),
        'block_userdashboard_for_allcounts' => array(
        'classname'    => 'block_userdashboard\external',
        'methodname'   => 'data_for_allcounts',
        'classpath'    => '',
        'description'  => 'Get user counts for mobile dashboard',
        'type'         => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
);

