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
 * Classroom Upgrade
 *
 * @package     local_program
 * @author:     M Arun Kumar <arun@eabyas.in>
 *
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_local_program_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();
   /* if ($oldversion < 2017050464) {
        $table = new xmldb_table('local_program');

        $field = new xmldb_field('totalusers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('activeusers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('totallevels', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('totalcourses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2017050464, 'local', 'program');
    }

    if ($oldversion < 2017050465) {
        $table = new xmldb_table('local_program_stream');

        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, 'big', null, null, null,null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        upgrade_plugin_savepoint(true, 2017050465, 'local', 'program');
    }

    if ($oldversion < 2017050465) {
        $table = new xmldb_table('local_program_levels');

        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, 'big', null, null, null,null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050465, 'local', 'program');
    }
    if ($oldversion < 2017050464) {
        $table = new xmldb_table('local_program_levels');

        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, 'big', null, null, null,null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('totalcourses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('position', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2017050464, 'local', 'program');
    }

    if ($oldversion < 2017050464) {
        $table = new xmldb_table('local_program_level_courses');

        $field = new xmldb_field('totalusers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('activeusers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('totalsessions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('activesessions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('position', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2017050464, 'local', 'program');
    }

    if ($oldversion < 2017050464) {
        $table = new xmldb_table('local_bc_course_sessions');

        $field = new xmldb_field('mincapacity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('maxcapacity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('totalusers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('activeusers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2017050464, 'local', 'program');
    }

    if ($oldversion < 2017050464) {
        $table = new xmldb_table('local_program_users');

        $field = new xmldb_field('levelids', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, NULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050464, 'local', 'program');
    }

    if ($oldversion < 2017050464) {
        $table = new xmldb_table('local_bc_level_completions');

        $field = new xmldb_field('bclcids', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, NULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050464, 'local', 'program');
    }
    if ($oldversion < 2017050467) {
        $table = new xmldb_table('local_bc_session_signups');

        $field = new xmldb_field('enrolstatus', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 1);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2017050467, 'local', 'program');
    }
    */
    // if ($oldversion < 2017050476) {
    //     $time = time();
    //     $notification_type_data = array(
    //     array('name' => 'program','shortname' => 'program','parent_module' => '0','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'program Enrollment','shortname' => 'program_enrol','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'program Unenrollment','shortname' => 'program_unenroll','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'program Completion','shortname' => 'program_completion','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'program Level Completion','shortname' => 'program_level_completion','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'program Course Completion','shortname' => 'program_course_completion','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Session Enrollment','shortname' => 'session_enrol','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Session Unenrollment','shortname' => 'session_unenroll','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Session Reschedule','shortname' => 'session_reschedule','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Session Attendance','shortname' => 'session_attendance','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Session Remainder','shortname' => 'session_reminder','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Session Cancel','shortname' => 'session_cancel','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Session Completion','shortname' => 'session_completion','parent_module' => '51','usercreated' => '2','timecreated' => $time,'usermodified' => NULL,'timemodified' => NULL)   
    //         );
    //     foreach($notification_type_data as $notification_type){
    //         if($DB->record_exists('local_notification_type', array('name' => $notification_type['name'], 'shortname' => $notification_type['shortname']))){
    //             $DB->delete_records('local_notification_type', $notification_type);
    //         }
    //     }

    //     upgrade_plugin_savepoint(true, 2017050476, 'local', 'program');
    // }
    if ($oldversion < 2017050478) {
        $table = new xmldb_table('local_program');

        $field = new xmldb_field('department', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('subdepartment', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2017050478, 'local', 'program');
    }
    if ($oldversion < 2017050479) {
        $table = new xmldb_table('local_program');

        $field = new xmldb_field('open_group', XMLDB_TYPE_CHAR, '100', null, null, null, NULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('open_hrmsrole', XMLDB_TYPE_CHAR, '100', null, null, null, NULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('open_designation', XMLDB_TYPE_CHAR, '100', null, null, null, NULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('open_location', XMLDB_TYPE_CHAR, '100', null, null, null, NULL);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('selfenrol', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2017050479, 'local', 'program');
    }
    if($oldversion < 2019101701){
        $table = new xmldb_table('local_program');
        $field = new xmldb_field('subdepartment', XMLDB_TYPE_CHAR, '50', XMLDB_NOTNULL, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('open_points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019101701, 'local', 'program');   
    }
       if ($oldversion < 2019112504) {
        $table = new xmldb_table('local_program_stream');
        $field = new xmldb_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
         upgrade_plugin_savepoint(true, 2019112504, 'local', 'program');
    }


     if ($oldversion < 2022101800) {
        
        $table = new xmldb_table('local_program');
        $table1 = new xmldb_table('local_program_stream');
        $table2 = new xmldb_table('local_program_test_score');
        $table3 = new xmldb_table('local_program_trainerfb');
        $table4 = new xmldb_table('local_program_users');
        $table5 = new xmldb_table('local_bcl_cmplt_criteria');
        $table6 = new xmldb_table('local_bc_completion_criteria');
        $table7 = new xmldb_table('local_bc_course_sessions');
        $table8 = new xmldb_table('local_bc_level_completions');
        $table9 = new xmldb_table('local_bc_session_signups');
        $table10 = new xmldb_table('local_bc_session_trainerfb');
        $table11 = new xmldb_table('local_bc_session_trainers');
        
        $index = new xmldb_index('certificateid', XMLDB_INDEX_NOTUNIQUE, array('certificateid'));

        if (!$dbman->index_exists($table,$index)) {
            $dbman->add_index($table,$index);
        }
        
       
        $index1 = new xmldb_index('costcenterid', XMLDB_INDEX_NOTUNIQUE, array('costcenterid'));

        if (!$dbman->index_exists($table1,$index1)) {
            $dbman->add_index($table1,$index1);
        }
 
      
        $index2 = new xmldb_index('levelid', XMLDB_INDEX_NOTUNIQUE, array('levelid'));

        if (!$dbman->index_exists($table2,$index2)) {
            $dbman->add_index($table2,$index2);
        }

        $index3 = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        if (!$dbman->index_exists($table2,$index3)) {
            $dbman->add_index($table2,$index3);
        }

        $index4 = new xmldb_index('testid', XMLDB_INDEX_NOTUNIQUE, array('testid'));

        if (!$dbman->index_exists($table2,$index4)) {
            $dbman->add_index($table2,$index4);
        }
        
    
        $index5 = new xmldb_index('trainerid', XMLDB_INDEX_NOTUNIQUE, array('trainerid'));

        if (!$dbman->index_exists($table3,$index5)) {
            $dbman->add_index($table3,$index5);
        }
        
        $index6 = new xmldb_index('typeid', XMLDB_INDEX_NOTUNIQUE, array('typeid'));

        if (!$dbman->index_exists($table4,$index6)) {
            $dbman->add_index($table4,$index6);
        }

        $index7 = new xmldb_index('levelid', XMLDB_INDEX_NOTUNIQUE, array('levelid'));

        if (!$dbman->index_exists($table5,$index7)) {
            $dbman->add_index($table5,$index7);
        }

         $index8 = new xmldb_index('sessionids', XMLDB_INDEX_NOTUNIQUE, array('sessionids'));

        if (!$dbman->index_exists($table5,$index8)) {
            $dbman->add_index($table5,$index8);
        }

         $index9 = new xmldb_index('courseids', XMLDB_INDEX_NOTUNIQUE, array('courseids'));

        if (!$dbman->index_exists($table5,$index9)) {
            $dbman->add_index($table5,$index9);
        }
        
        $index10 = new xmldb_index('sessionids', XMLDB_INDEX_NOTUNIQUE, array('sessionids'));

        if (!$dbman->index_exists($table6,$index10)) {
            $dbman->add_index($table6,$index10);
        }

        $index11 = new xmldb_index('courseids', XMLDB_INDEX_NOTUNIQUE, array('courseids'));

        if (!$dbman->index_exists($table6,$index11)) {
            $dbman->add_index($table6,$index11);
        }
        
        $index12 = new xmldb_index('programid', XMLDB_INDEX_NOTUNIQUE, array('programid'));

        if (!$dbman->index_exists($table7,$index12)) {
            $dbman->add_index($table7,$index12);
        }

        $index13 = new xmldb_index('levelid', XMLDB_INDEX_NOTUNIQUE, array('levelid'));

        if (!$dbman->index_exists($table7,$index13)) {
            $dbman->add_index($table7,$index13);
        }

        $index14 = new xmldb_index('instituteid', XMLDB_INDEX_NOTUNIQUE, array('instituteid'));

        if (!$dbman->index_exists($table7,$index14)) {
            $dbman->add_index($table7,$index14);
        }

        $index15 = new xmldb_index('roomid', XMLDB_INDEX_NOTUNIQUE, array('roomid'));

        if (!$dbman->index_exists($table7,$index15)) {
            $dbman->add_index($table7,$index15);
        }

        $index16 = new xmldb_index('moduleid', XMLDB_INDEX_NOTUNIQUE, array('moduleid'));

        if (!$dbman->index_exists($table7,$index16)) {
            $dbman->add_index($table7,$index16);
        }

        
        $index17 = new xmldb_index('levelid', XMLDB_INDEX_NOTUNIQUE, array('levelid'));

        if (!$dbman->index_exists($table8,$index17)) {
            $dbman->add_index($table8,$index17);
        }

         $index18 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        if (!$dbman->index_exists($table8,$index18)) {
            $dbman->add_index($table8,$index18);
        }

         $index19 = new xmldb_index('bclcids', XMLDB_INDEX_NOTUNIQUE, array('bclcids'));

        if (!$dbman->index_exists($table8,$index19)) {
            $dbman->add_index($table8,$index19);
        }
        
        $index20 = new xmldb_index('programid', XMLDB_INDEX_NOTUNIQUE, array('programid'));

         if (!$dbman->index_exists($table8,$index20)) {
            $dbman->add_index($table8,$index20);
        }

        $index21 = new xmldb_index('levelid', XMLDB_INDEX_NOTUNIQUE, array('levelid'));

        if (!$dbman->index_exists($table9,$index21)) {
            $dbman->add_index($table9,$index21);
        }

        $index22 = new xmldb_index('bclcid', XMLDB_INDEX_NOTUNIQUE, array('bclcid'));

        if (!$dbman->index_exists($table9,$index22)) {
            $dbman->add_index($table9,$index22);
        }
     
       $index23 = new xmldb_index('supervisorid', XMLDB_INDEX_NOTUNIQUE, array('supervisorid'));

        if (!$dbman->index_exists($table9,$index23)) {
            $dbman->add_index($table9,$index23);
        }
        
        $index24 = new xmldb_index('levelid', XMLDB_INDEX_NOTUNIQUE, array('levelid'));

        if (!$dbman->index_exists($table10,$index24)) {
            $dbman->add_index($table10,$index24);
        }

        $index25 = new xmldb_index('bclcid', XMLDB_INDEX_NOTUNIQUE, array('bclcid'));

        if (!$dbman->index_exists($table10,$index25)) {
            $dbman->add_index($table10,$index25);
        }

        $index26 = new xmldb_index('sessionid', XMLDB_INDEX_NOTUNIQUE, array('sessionid'));

        if (!$dbman->index_exists($table10,$index26)) {
            $dbman->add_index($table10,$index26);
        }
     
       $index27 = new xmldb_index('trainerid', XMLDB_INDEX_NOTUNIQUE, array('trainerid'));

        if (!$dbman->index_exists($table10,$index27)) {
            $dbman->add_index($table10,$index27);
        }

        $index28 = new xmldb_index('levelid', XMLDB_INDEX_NOTUNIQUE, array('levelid'));

        if (!$dbman->index_exists($table11,$index28)) {
            $dbman->add_index($table11,$index28);
        }

        $index29 = new xmldb_index('bclcid', XMLDB_INDEX_NOTUNIQUE, array('bclcid'));

        if (!$dbman->index_exists($table11,$index29)) {
            $dbman->add_index($table11,$index29);
        }

        $index30 = new xmldb_index('sessionid', XMLDB_INDEX_NOTUNIQUE, array('sessionid'));

        if (!$dbman->index_exists($table11,$index30)) {
            $dbman->add_index($table11,$index30);
        }

      upgrade_plugin_savepoint(true, 2022101800, 'local', 'program');
    }



    return true;
}
