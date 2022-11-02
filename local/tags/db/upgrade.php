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

function xmldb_local_tags_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();  

    //local_tags
    if ($oldversion <  2022101800) {

        $table = new xmldb_table('local_tags');
        $table1 = new xmldb_table('local_tag_mapping');

        $index = new xmldb_index('tagid', XMLDB_INDEX_NOTUNIQUE, array('tagid'));
          if (!$dbman->index_exists($table,$index)) {
            $dbman->add_index($table,$index);
           }

        $index1 = new xmldb_index('taginstanceid', XMLDB_INDEX_NOTUNIQUE, array('taginstanceid'));
          if (!$dbman->index_exists($table,$index1)) {
            $dbman->add_index($table,$index1);
           }

        $index2 = new xmldb_index('open_costcenterid', XMLDB_INDEX_NOTUNIQUE, array('open_costcenterid'));
          if (!$dbman->index_exists($table,$index2)) {
            $dbman->add_index($table,$index2);
           }

        $index3 = new xmldb_index('open_departmentid', XMLDB_INDEX_NOTUNIQUE, array('open_departmentid'));
          if (!$dbman->index_exists($table,$index3)) {
            $dbman->add_index($table,$index3);
           }


        $index4 = new xmldb_index('tagid', XMLDB_INDEX_NOTUNIQUE, array('tagid'));
          if (!$dbman->index_exists($table1,$index4)) {
            $dbman->add_index($table1,$index4);
           }

        $index5 = new xmldb_index('tagitemid', XMLDB_INDEX_NOTUNIQUE, array('tagitemid'));
          if (!$dbman->index_exists($table1,$index5)) {
            $dbman->add_index($table1,$index5);
           }

        upgrade_plugin_savepoint(true,2022101800, 'local', 'tags');
    }  
    return true;
}
