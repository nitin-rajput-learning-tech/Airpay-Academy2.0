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
defined('MOODLE_INTERNAL') || die();
function xmldb_local_program_uninstall() {
	global $DB;
	$dbman = $DB->get_manager();
	$tablesarr = array('local_program', 'local_program_users', 'local_program_levels', 'local_program_level_courses', 'local_bcl_cmplt_criteria', 'local_bc_level_completions', 'local_bc_completion_criteria', 'local_program_trainers', 'local_program_trainerfb', 'local_program_test_score', '');
	foreach($tablesarr AS $tablename){
		$table = new xmldb_table($tablename);
		if ($dbman->table_exists($table)) {
			$dbman->drop_table($table);
		}
	}
	return true;
}