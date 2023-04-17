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

function xmldb_local_evaluation_upgrade($oldversion) {
	global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2019030702.04) {
        $table = new xmldb_table('local_evaluations');
        $field = new xmldb_field('evaluationmode', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, 'SE');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019030702.04, 'local', 'evaluation');
    }
    if($oldversion < 2019030702.05){
        $table = new xmldb_table('local_evaluation_completed');
        $field = new xmldb_field('evaluatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019030702.05, 'local', 'evaluation');
    }

    if ($oldversion < 2019030702.07) {

            $time = time();
            $initcontent = array('name' => 'Feedback','shortname' => 'feedback','parent_module' => '0','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL, 'pluginname' => 'evaluation');
            $parentid = $DB->get_field('local_notification_type', 'id', array('shortname' => 'feedback'));
            if(!$parentid){
                $parentid = $DB->insert_record('local_notification_type', $initcontent);
            }


            $notification_type_data = array(
            array('name' => 'Feedback Unenrollment','shortname' => 'feedback_unenrollment','parent_module' => $parentid,'usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL, 'pluginname' => 'evaluation'),  
            );
            foreach($notification_type_data as $notification_type){
                unset($notification_type['timecreated']);
                if(!$DB->record_exists('local_notification_type',  $notification_type)){
                    $notification_type['timecreated'] = $time;
                    $DB->insert_record('local_notification_type', $notification_type);
                }
            }


        //Adding unenroldate string//
        $strings = array( 
            array('name' => '[feedback_unenroldate]','module' => 'feedback','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL)
        );
        foreach($strings as $string){
            unset($string['timecreated']);
            if(!$DB->record_exists('local_notification_strings', $string)){
                $string_obj = (object)$string;
                $string_obj->timecreated = $time;
                $DB->insert_record('local_notification_strings', $string_obj);
            }
        }

        upgrade_plugin_savepoint(true, 2019030702.07, 'local', 'evaluation');
    }
    if($oldversion < 2019030702.09){
        $table = new xmldb_table('local_evaluations');
        $field = new xmldb_field('deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null,'0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019030702.09, 'local', 'evaluation');
    }


    if($oldversion < 2022101800){

        $table = new xmldb_table('local_evaluations');
        $table1 = new xmldb_table('local_evaluation_completed');
        $table2 = new xmldb_table('local_evaluation_template');
        $table3 = new xmldb_table('local_evaluation_users');
        $table4 = new xmldb_table('local_eval_completedtmp');


        $index = new xmldb_index('course', XMLDB_INDEX_NOTUNIQUE, array('course'));

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index1 = new xmldb_index('costcenterid', XMLDB_INDEX_NOTUNIQUE, array('costcenterid'));

        if (!$dbman->index_exists($table, $index1)) {
            $dbman->add_index($table, $index1);
        }

        $index2 = new xmldb_index('departmentid', XMLDB_INDEX_NOTUNIQUE, array('departmentid'));

        if (!$dbman->index_exists($table, $index2)) {
            $dbman->add_index($table, $index2);
        }

        $index3 = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        if (!$dbman->index_exists($table1, $index3)) {
            $dbman->add_index($table1, $index3);
        }


       $index4 = new xmldb_index('costcenterid', XMLDB_INDEX_NOTUNIQUE, array('costcenterid'));

        if (!$dbman->index_exists($table2, $index4)) {
            $dbman->add_index($table2, $index4);
        }

        $index5 = new xmldb_index('departmentid', XMLDB_INDEX_NOTUNIQUE, array('departmentid'));

        if (!$dbman->index_exists($table2, $index5)) {
            $dbman->add_index($table2, $index5);
        }

        $index7 = new xmldb_index('creatorid', XMLDB_INDEX_NOTUNIQUE, array('creatorid'));

        if (!$dbman->index_exists($table3, $index7)) {
            $dbman->add_index($table3, $index7);
        }
        
        $index8 = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        if (!$dbman->index_exists($table4, $index8)) {
            $dbman->add_index($table4, $index8);
        }
    
    
     upgrade_plugin_savepoint(true, 2022101800, 'local', 'evaluation');
    }
    if($oldversion < 2022101800.02){
    $table1 = new xmldb_table('local_evaluations');
    $table2 = new xmldb_table('local_evaluation_template');
    $field1 = new xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    if (!$dbman->field_exists($table1, $field1)) {
        $dbman->add_field($table1, $field1);
    }
    if (!$dbman->field_exists($table2, $field1)) {
        $dbman->add_field($table2, $field1);
    }
    upgrade_plugin_savepoint(true, 2022101800.02, 'local', 'evaluation');
    }
    if($oldversion < 2022101800.04){
    $table = new xmldb_table('local_evaluations');
    if ($dbman->table_exists($table)) {

          $field = new xmldb_field('open_states');
          $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field)) {
              $dbman->add_field($table, $field);
          }

          $field1 = new xmldb_field('open_district');
          $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field1)) {
              $dbman->add_field($table, $field1);
          }

          $field2 = new xmldb_field('open_subdistrict');
          $field2->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field2)) {
              $dbman->add_field($table, $field2);
          }

          $field3 = new xmldb_field('open_village');
          $field3->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field3)) {
              $dbman->add_field($table, $field3);
          }

    }
    upgrade_plugin_savepoint(true, 2022101800.04, 'local', 'evaluation');
}
if($oldversion < 2022101800.05){
    $table = new xmldb_table('local_evaluations');
    if ($dbman->table_exists($table)) {

          $field = new xmldb_field('open_group');
          $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field)) {
              $dbman->add_field($table, $field);
          }
    }
    upgrade_plugin_savepoint(true, 2022101800.05, 'local', 'evaluation');
}
if($oldversion < 2022101800.06){
    $table = new xmldb_table('local_evaluations');
    if ($dbman->table_exists($table)) {

          $field = new xmldb_field('open_designation');
          $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field)) {
              $dbman->add_field($table, $field);
          }
    }
    upgrade_plugin_savepoint(true, 2022101800.06, 'local', 'evaluation');
}
if($oldversion < 2022101800.07){
    $DB->delete_records('local_notification_type', ['shortname' => 'feedback_due']);
    upgrade_plugin_savepoint(true, 2022101800.07, 'local', 'evaluation');
}
    return true;
}
