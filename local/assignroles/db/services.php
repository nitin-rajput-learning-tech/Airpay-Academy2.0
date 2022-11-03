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
 * @package    local location
 * @copyright  srilakshmi 2017
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
	'local_assignroles_submit_assignrole_form' => array(
		'classname' => 'local_assignroles_external',
		'methodname' => 'submit_assignrole_form',
		'classpath' => 'local/assignroles/classes/external.php',
		'description' => 'Submit form',
		'type' => 'write',
		'ajax' => true,
	),
	'local_assignroles_unassign_role' => array(
		'classname' => 'local_assignroles_external',
		'methodname' => 'local_unassign_role',
		'classpath' => 'local/assignroles/classes/external.php',
		'description' => 'Unassign Role',
		'type' => 'write',
		'ajax' => true,	
	),
	'local_assignroles_form_option_selector' => array(
		'classname' => 'local_assignroles_external',
		'methodname' => 'assignrole_form_option_selector',
		'classpath' => 'local/assignroles/classes/external.php',
		'description' => 'Form Option selection',
		'type' => 'Read',
		'ajax' => true,	
	),
	
);
