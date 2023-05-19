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
 * @copyright  shilpa 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    
    'block_achievements_manageachievementblockviewbadges' => array(
        'classname' => 'block_achievements_external',
        'methodname' => 'manageachievementblockviewbadges',
        'classpath' => 'blocks/achievements/externallib.php',
        'description' => 'Display badges tab',
        'ajax' => true,
        'type' => 'read'
    ),


    'block_achievements_manageachievementblockviewpoints' => array(
        'classname' => 'block_achievements_external',
        'methodname' => 'manageachievementblockviewpoints',
        'classpath' => 'blocks/achievements/externallib.php',
        'description' => 'Display points tab',
        'ajax' => true,
        'type' => 'read'
    ),

    'block_achievements_manageachievementblockviewcertifications' => array(
        'classname' => 'block_achievements_external',
        'methodname' => 'manageachievementblockviewcertifications',
        'classpath' => 'blocks/achievements/externallib.php',
        'description' => 'Display certifications tab',
        'ajax' => true,
        'type' => 'read'
    ),
    'block_achievements_get_user_certificates' => array(
        'classname' => 'block_achievements_external',
        'methodname' => 'get_user_certificates',
        'classpath' => 'blocks/achievements/externallib.php',
        'description' => 'get user certifications ',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    )

  

);

