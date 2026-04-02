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
function xmldb_local_ratings_upgrade($oldversion) {
	global $DB, $CFG;
	$dbman = $DB->get_manager();
	 if ($oldversion < 2022101800) {

        $table = new xmldb_table('local_ratings_likes');
        $table1 = new xmldb_table('local_like');
        $table2 = new xmldb_table('local_rating');
        $table3 = new xmldb_table('local_comment');

        $index = new xmldb_index('module_id', XMLDB_INDEX_NOTUNIQUE, array('module_id'));

        if (!$dbman->index_exists($table,$index)) {
            $dbman->add_index($table,$index);
        }
        
        $index1 = new xmldb_index('itemid', XMLDB_INDEX_NOTUNIQUE, array('itemid'));

        if (!$dbman->index_exists($table1,$index1)) {
            $dbman->add_index($table1,$index1);
        }

        $index2 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        if (!$dbman->index_exists($table1,$index2)) {
            $dbman->add_index($table1,$index2);
        }
 
        $index3 = new xmldb_index('itemid', XMLDB_INDEX_NOTUNIQUE, array('itemid'));

        if (!$dbman->index_exists($table2,$index3)) {
            $dbman->add_index($table2,$index3);
        }

        $index4 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        if (!$dbman->index_exists($table2,$index4)) {
            $dbman->add_index($table2,$index4);
        }
        
        $index5 = new xmldb_index('itemid', XMLDB_INDEX_NOTUNIQUE, array('itemid'));

        if (!$dbman->index_exists($table3,$index5)) {
            $dbman->add_index($table3,$index5);
        }

        $index6 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        if (!$dbman->index_exists($table3,$index6)) {
            $dbman->add_index($table3,$index6);
        }

     upgrade_plugin_savepoint(true, 2022101800, 'local', 'rating');
    }


	return true;
}
