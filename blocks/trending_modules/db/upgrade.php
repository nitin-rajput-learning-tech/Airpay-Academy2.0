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
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
* @author eabyas <info@eabyas.in>
* @package BizLMS
* @subpackage block_trending_courses
*/
defined('MOODLE_INTERNAL') || die();
function xmldb_block_trending_modules_upgrade($oldversion){
	global $DB;
	$dbman = $DB->get_manager();
	if ($oldversion < 2019120900.05) {
		$table = new xmldb_table('block_trending_modules');
		$field1 = new xmldb_field('enrolled_users', XMLDB_TYPE_INTEGER, '10');
		if ($dbman->field_exists($table, $field1)) {
	        $dbman->rename_field($table, $field1, 'enrollments');
	    }
	    $field2 = new xmldb_field('completed_users', XMLDB_TYPE_INTEGER, '10');
		if ($dbman->field_exists($table, $field2)) {
	        $dbman->rename_field($table, $field2, 'completions');
	    }
	    $newfields = array('week_enrollments', 'month_enrollments', 'week_completions', 'month_completions');
	    foreach($newfields AS $newfield){
	    	$field = new xmldb_field($newfield, XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
	    	if (!$dbman->field_exists($table, $field)) {
	        	$dbman->add_field($table, $field);
			}
	    }
	    upgrade_plugin_savepoint(true, 2019120900.05, 'block', 'trending_modules');
	}
	if($oldversion < 2019120901.03){
		$table = new xmldb_table('block_trending_modules');
    	$field = new xmldb_field('module_status', XMLDB_TYPE_INTEGER, '10', null, null, null, '1');
    	if (!$dbman->field_exists($table, $field)) {
        	$dbman->add_field($table, $field);
		}
    	upgrade_plugin_savepoint(true, 2019120901.03, 'block', 'trending_modules');
	}
	return true;
}