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
 * local local_costcenter
 *
 * @package    local_costcenter
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_costcenter_submit_costcenterform_form' => array(
            'classname'   => 'local_costcenter_external',
            'methodname'  => 'submit_costcenterform_form',
            'classpath'   => 'local/costcenter/classes/external.php',
            'description' => 'Submit form',
            'type'        => 'write',
            'ajax' => true,
    ),
     'local_costcenter_status_confirm' => array(
            'classname'   => 'local_costcenter_external',
            'methodname'  => 'costcenter_status_confirm',
            'classpath'   => 'local/costcenter/classes/external.php',
            'description' => 'change the status',
            'type'        => 'write',
            'ajax' => true,
    ),
    'local_costcenter_delete_costcenter' => array(
            'classname'   => 'local_costcenter_external',
            'methodname'  => 'costcenter_delete_costcenter',
            'classpath'   => 'local/costcenter/classes/external.php',
            'description' => 'delete the costcenter',
            'type'        => 'write',
            'ajax' => true,
    ),
    'local_costcenter_departmentlist' => array(
            'classname'   => 'local_costcenter_external',
            'methodname'  => 'departmentlist',
            'classpath'   => 'local/costcenter/classes/external.php',
            'description' => 'list of departments under an organisation',
            'type'        => 'read',
            'ajax' => true,
    ),
    'local_costcenter_subdepartmentlist' => array(
            'classname'   => 'local_costcenter_external',
            'methodname'  => 'subdepartmentlist',
            'classpath'   => 'local/costcenter/classes/external.php',
            'description' => 'list of subdepartments under an organisation/department',
            'type'        => 'read',
            'ajax' => true,
    ),
    'local_costcenter_departmentview' => array(
        'classname'   => 'local_costcenter_external',
        'methodname'  => 'departmentview',
        'classpath'   => 'local/costcenter/classes/external.php',
        'description' => 'departments view page',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_costcenter_form_option_selector' => array(
        'classname'   => 'local_costcenter_external',
        'methodname'  => 'form_option_selector',
        'classpath'   => 'local/costcenter/classes/external.php',
        'description' => 'Get dynamic form options related to organisation',
        'type'        => 'read',
        'ajax' => true,
    ),
     'local_costcenter_department_create' => array(
        'classname'   => 'local_costcenter_external',
        'methodname'  => 'department_create',
        'classpath'   => 'local/costcenter/classes/external.php',
        'description' => 'Department Creation',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_costcenter_generate_shortcode' => array(
        'classname'   => 'local_costcenter_external',
        'methodname'  => 'generate_shortcode',
        'classpath'   => 'local/costcenter/classes/external.php',
        'description' => 'generate shortcode',
        'type'        => 'write',
        'ajax' => true,
    )
);

