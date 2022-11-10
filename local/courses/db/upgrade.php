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

function xmldb_local_courses_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2017111300) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('approvalreqd', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017111300, 'local', 'courses');
    }
    if ($oldversion < 2017111301) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('selfenrol', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017111301, 'local', 'courses');
    }
    if ($oldversion < 2017111302) {
        $table = new xmldb_table('course');
        $field = new xmldb_field('open_level', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017111302, 'local', 'courses');
    }
    if($oldversion < 2019091300.01){
        $table = new xmldb_table('course');
        $field = new xmldb_field('open_subdepartment', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019091300.01, 'local', 'courses');   
    }
    if($oldversion < 2019091300.08){
        $table = new xmldb_table('course');
        $field = new xmldb_field('open_securecourse');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '2',XMLDB_NOTNULL, null, 0, 0, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019091300.08, 'local', 'courses');
    }
    if($oldversion < 2019091300.09){
        $table = new xmldb_table('course');
        $field1 = new xmldb_field('open_hrmsrole');
        $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        $field2 = new xmldb_field('open_location');
        $field2->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        upgrade_plugin_savepoint(true, 2019091300.09, 'local', 'courses');
    }
    if($oldversion < 2019091300.10){
        $table = new xmldb_table('course');
        $field1 = new xmldb_field('open_departmentid');
        if ($dbman->field_exists($table, $field1)) {
            $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $dbman->change_field_type($table, $field1);
        }
        $field2 = new xmldb_field('open_subdepartment');
        if ($dbman->field_exists($table, $field2)) {
            $field2->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $dbman->change_field_type($table, $field2);
        }
        upgrade_plugin_savepoint(true, 2019091300.10, 'local', 'courses');
    }


    if ($oldversion < 2022101800) {
        
        $table = new xmldb_table('local_logs');
        $table1 = new xmldb_table('local_courseerrors');

        $index = new xmldb_index('module', XMLDB_INDEX_NOTUNIQUE, array('module'));

        if (!$dbman->index_exists($table,$index)) {
            $dbman->add_index($table,$index);
        }

        $index1 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        if (!$dbman->index_exists($table1,$index1)) {
            $dbman->add_index($table1,$index1);
        }

     upgrade_plugin_savepoint(true, 2022101800, 'local', 'courses');
   }
   if($oldversion < 2022101800.03){
    $table = new xmldb_table('local_emaillogs');
    $field1 = new xmldb_field('status');
    $field1->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
    if (!$dbman->field_exists($table, $field1)) {
        $dbman->add_field($table, $field1);
    }
    upgrade_plugin_savepoint(true, 2022101800.03, 'local', 'local_emaillogs');
}
    return true;
}
