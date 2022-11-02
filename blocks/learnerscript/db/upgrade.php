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

/**
 * Version details
 *
 * LearnerScript Reports - A Moodle block for creating customizable reports
 *
 * @package     block_learnerscript
 * @author:     eAbyas Info Solutions
 * @date:       2017
 *
 * @copyright  eAbyas Info Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_block_learnerscript_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019052004) {
        $table1 = new xmldb_table('block_ls_coursetimestats');
        $table2 = new xmldb_table('block_ls_modtimestats');

        $field1 = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $field2 = new xmldb_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table = new xmldb_table('block_ls_schedule');
        $field = new xmldb_field('contextlevel', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $field3= new xmldb_field('sessionid');  

        // Conditionally launch add field enddate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Adding fields to table tool_dataprivacy_ctxexpired.
        $table1->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table1->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table1->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table1->add_field('timespent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table1->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table1->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table tool_dataprivacy_ctxexpired.
        $table1->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table1)) {
            $dbman->create_table($table1);             
        }

        // Adding fields to table tool_dataprivacy_ctxexpired.
        $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table2->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('timespent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table2->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table tool_dataprivacy_ctxexpired.
        $table2->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table2)) {
            $dbman->create_table($table2);            
        }
        if (!$dbman->field_exists($table1, $field1)) {
            $dbman->add_field($table1, $field1);
        } 
        if (!$dbman->field_exists($table2, $field2)) {
            $dbman->add_field($table2, $field2);
        }
        if (!$dbman->field_exists($table2, $field1)) {
            $dbman->add_field($table2, $field1);
        }

        if ($dbman->field_exists($table1, $field3)) {          
             $dbman->drop_field($table1, $field3);    
        }   
        if ($dbman->field_exists($table2, $field3)) {   
            $dbman->drop_field($table2, $field3);    
        }   

        upgrade_plugin_savepoint(true, 2019052004, 'block', 'learnerscript');
        return true;
    }

    if ($oldversion < 2019052005.2) {
        $table = new xmldb_table('block_learnerscript');
        $field = new xmldb_field('category', XMLDB_TYPE_TEXT, '225', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {   
            $dbman->add_field($table, $field);    
        }   

        upgrade_plugin_savepoint(true, 2019052005.2, 'block', 'learnerscript');
    }

    return true;
}