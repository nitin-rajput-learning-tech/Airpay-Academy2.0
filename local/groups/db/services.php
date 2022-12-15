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
 * local local_groups
 *
 * @package    local_groups
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_groups_submit_groupsform_form' => array(
            'classname'   => 'local_groups_external',
            'methodname'  => 'submit_groupsform_form',
            'classpath'   => 'local/groups/classes/external.php',
            'description' => 'Submit form',
            'type'        => 'write',
            'ajax' => true,
    ),
     'local_groups_status_confirm' => array(
            'classname'   => 'local_groups_external',
            'methodname'  => 'groups_status_confirm',
            'classpath'   => 'local/groups/classes/external.php',
            'description' => 'change the status',
            'type'        => 'write',
            'ajax' => true,
    ),
     'local_groups_managegroups_view' => array(
        'classname'   => 'local_groups_external',
        'methodname'  => 'managegroupsview',
        'classpath'   => 'local/groups/classes/external.php',
        'description' => 'Display the Group Page',
        'type'        => 'write',
        'ajax' => true
    ),
    'local_groups_delete_groups' => array(
            'classname'   => 'local_groups_external',
            'methodname'  => 'groups_delete_groups',
            'classpath'   => 'local/groups/classes/external.php',
            'description' => 'delete the groups',
            'type'        => 'write',
            'ajax' => true,
    ),
    'local_groups_departmentlist' => array(
            'classname'   => 'local_groups_external',
            'methodname'  => 'departmentlist',
            'classpath'   => 'local/groups/classes/external.php',
            'description' => 'list of departments under an organisation',
            'type'        => 'read',
            'ajax' => true,
    ),
    'local_groups_submit_licenceform' => array(
        'classname'   => 'local_groups_external',
        'methodname'  => 'submit_licenceform',
        'classpath'   => 'local/groups/classes/external.php',
        'description' => 'submit the Licence form',
        'type'        => 'write',
        'ajax' => true,
    )
);

