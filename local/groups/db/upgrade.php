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
defined('MOODLE_INTERNAL') || die();

function xmldb_local_groups_upgrade($oldversion) {
	global $DB, $CFG;

	$dbman = $DB->get_manager();

	if ($oldversion < 2022101300) {
    	
    	$table = new xmldb_table('local_groups');
		$index = new xmldb_index('departmentid', XMLDB_INDEX_NOTUNIQUE, array('departmentid'));

		if (!$dbman->index_exists($table,$index)) {
			$dbman->add_index($table,$index);
		}

	upgrade_plugin_savepoint(true, 2022101300, 'local', 'groups');
	}
	if($oldversion < 2022101300.03){

        $table = new xmldb_table('local_groups');
        $field1 = new xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        upgrade_plugin_savepoint(true, 2022101300.03, 'local', 'groups');
    }

	return true;
}