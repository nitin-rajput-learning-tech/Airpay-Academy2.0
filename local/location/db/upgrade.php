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
function xmldb_local_location_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

 if ($oldversion < 2022101800) {
        
        $table = new xmldb_table('local_location_institutes');
        $table1 = new xmldb_table('local_location_room');

        $index = new xmldb_index('costcenter', XMLDB_INDEX_NOTUNIQUE, array('costcenter'));

        if (!$dbman->index_exists($table,$index)) {
            $dbman->add_index($table,$index);
        }
        
        $index1 = new xmldb_index('instituteid', XMLDB_INDEX_NOTUNIQUE, array('instituteid'));

        if (!$dbman->index_exists($table1,$index1)) {
            $dbman->add_index($table1,$index1);
        }

     upgrade_plugin_savepoint(true, 2022101800, 'local', 'location');
    }
    if ($oldversion < 2022101800.01) {
        $table = new xmldb_table('local_location_institutes');
        $field = new xmldb_field('address', XMLDB_TYPE_TEXT,'big', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $table = new xmldb_table('local_location_room');
        $field = new xmldb_field('address', XMLDB_TYPE_TEXT,'big', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }
        upgrade_plugin_savepoint(true, 2022101800.01, 'local', 'location');
    }
    
    return true;
}
