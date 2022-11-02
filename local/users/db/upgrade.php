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

function xmldb_local_users_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2016080911.05) {
        $table = new xmldb_table('user');
        $field1 = new xmldb_field('open_positionid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        $field2 = new xmldb_field('open_domainid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        upgrade_plugin_savepoint(true, 2016080911.05, 'local', 'user');
    }

    if ($oldversion < 2020032600) {
        $table = new xmldb_table('local_uniquelogins');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('day', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('month', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('year', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('count_date', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
        }
            upgrade_plugin_savepoint(true, 2020032600, 'local', 'users');
    }

    if ($oldversion < 2020032601) {
        $table = new xmldb_table('user');
        $field1 = new xmldb_field('open_notify_logins', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);

		if (!$dbman->field_exists($table, $field1)) {
			$dbman->add_field($table, $field1);
		}
		upgrade_plugin_savepoint(true, 2020032601, 'local', 'user');
	}

    //Adding indexes to local Pulgins
    if ($oldversion < 2022101800) {
   
    	$table = new xmldb_table('local_recompletion_qa');
    	$table1 = new xmldb_table('local_transcript_history');
    	$table2 = new xmldb_table('local_uniquelogins');
    	$table3 = new xmldb_table('local_positions');
    	$table4 = new xmldb_table('local_domains');

		$index = new xmldb_index('uniqueid', XMLDB_INDEX_NOTUNIQUE, array('uniqueid'));

		if (!$dbman->index_exists($table,$index)) {
			$dbman->add_index($table,$index);
		}

		$index1 = new xmldb_index('employee_id', XMLDB_INDEX_NOTUNIQUE, array('employee_id'));

		if (!$dbman->index_exists($table1,$index1)) {
			$dbman->add_index($table1,$index1);
		}

		$index2 = new xmldb_index('training_object_id', XMLDB_INDEX_NOTUNIQUE, array('training_object_id'));

		if (!$dbman->index_exists($table1,$index2)) {
			$dbman->add_index($table1,$index2);
		}

		$index3 = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

		if (!$dbman->index_exists($table1,$index3)) {
			$dbman->add_index($table1,$index3);
		}

		$index4 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

		if (!$dbman->index_exists($table1,$index4)) {
			$dbman->add_index($table1,$index4);
		}
    	
    	
		$index5 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

		if (!$dbman->index_exists($table2,$index5)) {
			$dbman->add_index($table2,$index5);
		}
    	
		$index6 = new xmldb_index('costcenter', XMLDB_INDEX_NOTUNIQUE, array('costcenter'));

		if (!$dbman->index_exists($table3,$index6)) {
			$dbman->add_index($table3,$index6);
		}

		$index7 = new xmldb_index('costcenter', XMLDB_INDEX_NOTUNIQUE, array('costcenter'));

		if (!$dbman->index_exists($table4,$index7)) {
			$dbman->add_index($table4,$index7);
		}

		upgrade_plugin_savepoint(true, 2022101800, 'local', 'users');
	}
	return true;
}
