<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This trainerdashboard is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This trainerdashboard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this trainerdashboard.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage block_trainerdashboard
 */
defined('MOODLE_INTERNAL') || die();

$functions = array(
     'block_trainerdashboard_get_trainerslist' => array(
        'classname' => 'block_trainerdashboard_external',
        'methodname' => 'get_trainerslist',
        'classpath' => 'blocks/trainerdashboard/classes/external.php',
        'description' => 'List the trainer Details',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'block_trainerdashboard_get_conductedtrainings' => array(
        'classname' => 'block_trainerdashboard_external',
        'methodname' => 'get_conductedtrainings',
        'classpath' => 'blocks/trainerdashboard/classes/external.php',
        'description' => 'Count of Training conducted in last 3 to 6Months and their stats details',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'block_trainerdashboard_get_trainermanhours' => array(
        'classname' => 'block_trainerdashboard_external',
        'methodname' => 'get_trainermanhours',
        'classpath' => 'blocks/trainerdashboard/classes/external.php',
        'description' => 'Trainer wise Manhours spend list',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'block_trainerdashboard_get_depttrainingavg' => array(
        'classname' => 'block_trainerdashboard_external',
        'methodname' => 'get_depttrainingavg',
        'classpath' => 'blocks/trainerdashboard/classes/external.php',
        'description' => 'Department wise training averages',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'block_trainerdashboard_get_upcomingtrainings' => array(
        'classname' => 'block_trainerdashboard_external',
        'methodname' => 'get_upcomingtrainings',
        'classpath' => 'blocks/trainerdashboard/classes/external.php',
        'description' => 'Next 3 to 6monts Scheduled training list and their stats',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'block_trainerdashboard_get_classrooms' => array(
        'classname' => 'block_trainerdashboard_external',
        'methodname' => 'get_classrooms',
        'classpath' => 'blocks/trainerdashboard/classes/external.php',
        'description' => 'trainers classrooms list',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
);
