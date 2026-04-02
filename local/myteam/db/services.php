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
 */

defined('MOODLE_INTERNAL') || die;
$functions = array(
        'local_myteam_manageteam_view' => array(
                'classname'   => 'local_myteam_external',
                'methodname'  => 'manageteamview',
                'classpath'   => 'local/myteam/classes/external.php',
                'description' => 'Display the myteam Page',
                'type'        => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        ),
        'local_myteam_teamallocation_view' => array(
                'classname'   => 'local_myteam_external',
                'methodname'  => 'teamallocationview',
                'classpath'   => 'local/myteam/classes/external.php',
                'description' => 'Display the team Page',
                'type'        => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        ),
        'local_myteam_teamapprovals_view' => array(
                'classname'   => 'local_myteam_external',
                'methodname'  => 'teamapprovalsview',
                'classpath'   => 'local/myteam/classes/external.php',
                'description' => 'Display the myteam Page',
                'type'        => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        ),
        'local_myteam_myteamdisplaymodule_view' => array(
                'classname'   => 'local_myteam_external',
                'methodname'  => 'myteamdisplaymodulewise',
                'classpath'   => 'local/myteam/classes/external.php',
                'description' => 'Display the myteam Page',
                'type'        => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        ),
        'local_myteam_courseallocation_view' => array(
                'classname'   => 'local_myteam_external',
                'methodname'  => 'courseallocationdependencies',
                'classpath'   => 'local/myteam/classes/external.php',
                'description' => 'Display the myteam Page',
                'type'        => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        ),
        'local_myteam_modulecourse_allocation' => array(
                'classname'   => 'local_myteam_external',
                'methodname'  => 'modulecourseallocation',
                'classpath'   => 'local/myteam/classes/external.php',
                'description' => 'modulecourse allocation',
                'type'        => 'write',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        ),
        'local_myteam_teamapprovals_actions' => array(
                'classname'   => 'local_myteam_external',
                'methodname'  => 'teamapprovalsactions',
                'classpath'   => 'local/myteam/classes/external.php',
                'description' => 'Display the myteam Page',
                'type'        => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        ),
);
