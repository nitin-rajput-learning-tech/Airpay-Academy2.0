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
 * @package BizLMS
 * @subpackage local_skillrepository
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_local_skillrepository_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2016031003) {
        $table = new xmldb_table('local_skill');
        $field = new xmldb_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2016031003, 'local', 'skillrepository');
    }
    if($oldversion < 2016031011){
        $table = new xmldb_table('local_course_levels');
        $field = new xmldb_field('costcenterid',XMLDB_TYPE_INTEGER, '10', null, null, null,null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2016031011, 'local', 'skillrepository');
    }
    if($oldversion < 2016031029.10){
        $table = new xmldb_table('local_course_levels');
        $field = new xmldb_field('sortorder',XMLDB_TYPE_INTEGER, '10', null, null, null,null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2016031029.10, 'local', 'skillrepository');
    }

    //local_skill
    if ($oldversion <  2022101800) {
        $table = new xmldb_table('local_skill');
        $table1 = new xmldb_table('local_skillmatrix');
        $table2 = new xmldb_table('local_skill_categories');
        $table3 = new xmldb_table('local_skill_categories');

        $index = new xmldb_index('costcenterid', XMLDB_INDEX_NOTUNIQUE, array('costcenterid'));

        if (!$dbman->index_exists($table,$index)) {
            $dbman->add_index($table,$index);
        }

        $index1 = new xmldb_index('parentid', XMLDB_INDEX_NOTUNIQUE, array('parentid'));
        if (!$dbman->index_exists($table,$index1)) {
            $dbman->add_index($table,$index1);
        }

        $index3 = new xmldb_index('costcenterid', XMLDB_INDEX_NOTUNIQUE, array('costcenterid'));
        if (!$dbman->index_exists($table1,$index3)) {
            $dbman->add_index($table1,$index3);
        }

        $index4 = new xmldb_index('skill_categoryid', XMLDB_INDEX_NOTUNIQUE, array('    skill_categoryid'));
        if (!$dbman->index_exists($table1,$index4)) {
            $dbman->add_index($table1,$index4);
        }

        $index5 = new xmldb_index('skillid', XMLDB_INDEX_NOTUNIQUE, array('skillid'));
        if (!$dbman->index_exists($table1,$index5)) {
            $dbman->add_index($table1,$index5);
        }

        $index6 = new xmldb_index('positionid', XMLDB_INDEX_NOTUNIQUE, array('positionid'));
        if (!$dbman->index_exists($table1,$index6)) {
            $dbman->add_index($table1,$index6);
        }

        $index7 = new xmldb_index('levelid', XMLDB_INDEX_NOTUNIQUE, array('levelid'));
        if (!$dbman->index_exists($table1,$index7)) {
            $dbman->add_index($table1,$index7);
        }
        
        $index8 = new xmldb_index('costcenterid', XMLDB_INDEX_NOTUNIQUE, array('costcenterid'));
        if (!$dbman->index_exists($table2,$index8)) {
            $dbman->add_index($table2,$index8);
        }

        $index9 = new xmldb_index('parentid', XMLDB_INDEX_NOTUNIQUE, array('parentid'));
        if (!$dbman->index_exists($table2,$index9)) {
            $dbman->add_index($table2,$index9);
        }
        
        $index10 = new xmldb_index('costcenterid', XMLDB_INDEX_NOTUNIQUE, array('costcenterid'));
        if (!$dbman->index_exists($table3,$index10)) {
            $dbman->add_index($table3,$index10);
        }
        upgrade_plugin_savepoint(true,2022101800, 'local', 'skillrepository');
    }
    return true;
}