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
 * @package    local search
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_search_get_available_modules' => array(
        'classname' => 'local_search_external',
        'methodname' => 'get_available_modules',
        'classpath' => 'local/search/classes/external.php',
        'description' => 'Get modules information',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_search_get_filter_elements' => array(
        'classname' => 'local_search_external',
        'methodname' => 'get_filter_elements',
        'classpath' => 'local/search/classes/external.php',
        'description' => 'get list of filters',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_search_enrol_user_to_module' => array(
        'classname' => 'local_search_external',
        'methodname' => 'enrol_user_to_module',
        'classpath' => 'local/search/classes/external.php',
        'description' => 'Enrol users to modules',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_search_get_module_info' => array(
        'classname' => 'local_search_external',
        'methodname' => 'get_module_info',
        'classpath' => 'local/search/classes/external.php',
        'description' => 'get module info',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    )
);
