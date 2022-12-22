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
 * @package Bizlms 
 * @subpackage local_classroom
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_local_classroom_upgrade($oldversion)
{
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2017050404) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', 'shortname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050404, 'local', 'classroom');
    }
    if ($oldversion < 2017050405) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('config', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050405, 'local', 'classroom');
    }
    if ($oldversion < 2017050406) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('manage_approval', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field1 = new xmldb_field('allow_multi_session', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        upgrade_plugin_savepoint(true, 2017050406, 'local', 'classroom');
    }
    if ($oldversion < 2017050410) {
        $table = new xmldb_table('local_classroom_courses');
        $field = new xmldb_field('course_duration', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050410, 'local', 'classroom');
    }
    if ($oldversion < 2017050411) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('cr_category', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050411, 'local', 'classroom');
    }
    if ($oldversion < 2017050413) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('nomination_startdate', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field1 = new xmldb_field('nomination_enddate', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        upgrade_plugin_savepoint(true, 2017050413, 'local', 'classroom');
    }
    if ($oldversion < 2017050415) {
        $table = new xmldb_table('local_classroom_sessions');
        $field = new xmldb_field('timestart', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field1 = new xmldb_field('timefinish', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        $field2 = new xmldb_field('sessiontimezone', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        $field3 = new xmldb_field('attendance_status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, '0', null);
        if (!$dbman->field_exists($table, $field3)) {
            $dbman->add_field($table, $field3);
        }
        upgrade_plugin_savepoint(true, 2017050415, 'local', 'classroom');
    }
    if ($oldversion < 2017050417) {
        $table = new xmldb_table('local_classroom_sessions');
        $field = new xmldb_field('classroomidid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'classroomid');
        }
        upgrade_plugin_savepoint(true, 2017050417, 'local', 'classroom');
    }
    if ($oldversion < 2017050418) {
        $table = new xmldb_table('local_classroom_sessions');
        $field = new xmldb_field('institueid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'instituteid');
        }
        upgrade_plugin_savepoint(true, 2017050418, 'local', 'classroom');
    }
    if ($oldversion < 2017050419) {
        $table = new xmldb_table('local_classroom_sessions');
        $field = new xmldb_field('capacity', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050419, 'local', 'classroom');
    }
    if ($oldversion < 2017050421) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('department', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050421, 'local', 'classroom');
    }
    if ($oldversion < 2017050422) {
        $table = new xmldb_table('local_classroom_signups');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'local_classroom_attendance');
        }
        upgrade_plugin_savepoint(true, 2017050422, 'local', 'classroom');
    }
    if ($oldversion < 2017050424) {
        $table = new xmldb_table('local_classroom_sessions');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050424, 'local', 'classroom');
    }
    if ($oldversion < 2017050425) {
        $table = new xmldb_table('local_classroom_trainerfb');
        $field = new xmldb_field('classroomidid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'classroomid');
        }
        upgrade_plugin_savepoint(true, 2017050425, 'local', 'classroom');
    }
    if ($oldversion < 2017050430) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('classroomlogo', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050430, 'local', 'classroom');
    }
    if ($oldversion < 2017050433) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('actualsessions', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'activesessions');
        }
        $field1 = new xmldb_field('attendees', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if ($dbman->field_exists($table, $field1)) {
            $dbman->rename_field($table, $field1, 'activeusers');
        }
        $field2 = new xmldb_field('enrolled_users', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if ($dbman->field_exists($table, $field2)) {
            $dbman->rename_field($table, $field2, 'totalusers');
        }
        $field3 = new xmldb_field('status', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if ($dbman->field_exists($table, $field3)) {
            $dbman->rename_field($table, $field3, 'status');
        }
        upgrade_plugin_savepoint(true, 2017050433, 'local', 'classroom');
    }
    if ($oldversion < 2017050436) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('department', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050436, 'local', 'classroom');
    }
    if ($oldversion < 2017050439) {
        $table = new xmldb_table('local_classroom_sessions');
        $field = new xmldb_field('moduletype', XMLDB_TYPE_CHAR, '250', null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('moduleid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050439, 'local', 'classroom');
    }
    if ($oldversion < 2017050441) {
        $table = new xmldb_table('local_classroom_sessions');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '250', null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050441, 'local', 'classroom');
    }
    if ($oldversion < 2017050444) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('completiondate', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_classroom_users');
        $field = new xmldb_field('completiondate', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050444, 'local', 'classroom');
    }
    if ($oldversion < 2017050448) {
        $table = new xmldb_table('local_classroom_completion');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

            $table->add_field('classroomid', XMLDB_TYPE_INTEGER, '10', null, null, null);

            $table->add_field('sessiontracking', XMLDB_TYPE_CHAR, '225', null, null, null, "OR");

            $table->add_field('sessionids', XMLDB_TYPE_TEXT, 'big', null, null, null, NULL);

            $table->add_field('coursetracking', XMLDB_TYPE_CHAR, '225', null, null, null, "OR");

            $table->add_field('courseids', XMLDB_TYPE_TEXT, 'big', null, null, null, NULL);

            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);



            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2017050448, 'local', 'classroom');
    }
    // OL-1042 Add Target Audience to Classrooms//
    if ($oldversion < 2017050453) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('open_group', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('open_hrmsrole', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('open_designation', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('open_location', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050453, 'local', 'classroom');
    }
    // OL-1042 Add Target Audience to Classrooms//
    if ($oldversion < 2017050454) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('approvalreqd', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050454, 'local', 'classroom');
    }

    if ($oldversion < 2017050455) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('open_points', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050455, 'local', 'classroom');
    }
    if ($oldversion < 2017050464) {
        $table = new xmldb_table('local_classroom_waitlist');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

            $table->add_field('classroomid', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('enroltype', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('enrolstatus', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2017050464, 'local', 'classroom');
    }
    if ($oldversion < 2017050466) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('allow_waitinglistusers', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017050466, 'local', 'classroom');
    }
    if ($oldversion < 2017050467) {
        $time = time();
        $initcontent = array('name' => 'Classroom', 'shortname' => 'classroom', 'parent_module' => '0', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'classroom');
        $parentid = $DB->get_field('local_notification_type', 'id', array('shortname' => 'classroom'));
        if (!$parentid) {
            $parentid = $DB->insert_record('local_notification_type', $initcontent);
        }

        $notification_type_data = array(array('name' => 'Classroom Waiting List', 'shortname' => 'classroom_enrolwaiting', 'parent_module' => $parentid, 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'classroom'));
        foreach ($notification_type_data as $notification_type) {
            unset($notification_type['timecreated']);
            if (!$DB->record_exists('local_notification_type',  $notification_type)) {
                $notification_type['timecreated'] = $time;
                $DB->insert_record('local_notification_type', $notification_type);
            }
        }
        $strings = array(
            array('name' => '[classroom_waitinglist_order]', 'module' => 'classroom', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
            array('name' => '[classroom_waitinguserfulname]', 'module' => 'classroom', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
            array('name' => '[classroom_waitinguseremail]', 'module' => 'classroom', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL)
        );
        foreach ($strings as $string) {
            unset($string['timecreated']);
            if (!$DB->record_exists('local_notification_strings', $string)) {
                $string_obj = (object)$string;
                $string_obj->timecreated = $time;
                $DB->insert_record('local_notification_strings', $string_obj);
            }
        }
        upgrade_plugin_savepoint(true, 2017050467, 'local', 'classroom');
    }
    if ($oldversion < 2019093004) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('subdepartment', XMLDB_TYPE_CHAR, '50', XMLDB_NOTNULL, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019093004, 'local', 'classroom');
    }
    if ($oldversion < 2019093004.12) {
        $table = new xmldb_table('local_classroom');
        $field = new xmldb_field('selfenrol', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2019093004.12, 'local', 'classroom');
    }


    if ($oldversion < 2022101800) {

        $table = new xmldb_table('local_notification_strings');
        $table1 = new xmldb_table('local_emaillogs');
        $table2 = new xmldb_table('local_classroom_waitlist');
        $table3 = new xmldb_table('local_classroom_users');
        $table4 = new xmldb_table('local_classroom_trainerfb');
        $table5 = new xmldb_table('local_classroom_test_score');
        $table6 = new xmldb_table('local_classroom_sessions');
        $table7 = new xmldb_table('local_classroom_courses');
        $table8 = new xmldb_table('local_classroom');

        $index = new xmldb_index('module', XMLDB_INDEX_NOTUNIQUE, array('module'));

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index1 = new xmldb_index('notification_infoid', XMLDB_INDEX_NOTUNIQUE, array('notification_infoid'));

        if (!$dbman->index_exists($table1, $index1)) {
            $dbman->add_index($table1, $index1);
        }

        $index2 = new xmldb_index('from_userid', XMLDB_INDEX_NOTUNIQUE, array('from_userid'));

        if (!$dbman->index_exists($table1, $index2)) {
            $dbman->add_index($table1, $index2);
        }

        $index3 = new xmldb_index('to_userid', XMLDB_INDEX_NOTUNIQUE, array('to_userid'));

        if (!$dbman->index_exists($table1, $index3)) {
            $dbman->add_index($table1, $index3);
        }

        $index4 = new xmldb_index('batchid', XMLDB_INDEX_NOTUNIQUE, array('batchid'));

        if (!$dbman->index_exists($table1, $index4)) {
            $dbman->add_index($table1, $index4);
        }

        $index5 = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        if (!$dbman->index_exists($table1, $index5)) {
            $dbman->add_index($table1, $index5);
        }

        $index6 = new xmldb_index('moduleid', XMLDB_INDEX_NOTUNIQUE, array('moduleid'));

        if (!$dbman->index_exists($table1, $index6)) {
            $dbman->add_index($table1, $index6);
        }

        $index7 = new xmldb_index('classroomid', XMLDB_INDEX_NOTUNIQUE, array('classroomid'));

        if (!$dbman->index_exists($table2, $index7)) {
            $dbman->add_index($table2, $index7);
        }

        $index8 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        if (!$dbman->index_exists($table2, $index8)) {
            $dbman->add_index($table2, $index8);
        }


        $index9 = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        if (!$dbman->index_exists($table3, $index9)) {
            $dbman->add_index($table3, $index9);
        }

        $index10 = new xmldb_index('supervisorid', XMLDB_INDEX_NOTUNIQUE, array('supervisorid'));

        if (!$dbman->index_exists($table3, $index10)) {
            $dbman->add_index($table3, $index10);
        }

        $index11 = new xmldb_index('trainerid', XMLDB_INDEX_NOTUNIQUE, array('trainerid'));

        if (!$dbman->index_exists($table4, $index11)) {
            $dbman->add_index($table4, $index11);
        }

        $index12 = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        if (!$dbman->index_exists($table5, $index12)) {
            $dbman->add_index($table5, $index12);
        }

        $index13 = new xmldb_index('testid', XMLDB_INDEX_NOTUNIQUE, array('testid'));

        if (!$dbman->index_exists($table5, $index13)) {
            $dbman->add_index($table5, $index13);
        }

        $index14 = new xmldb_index('instituteid', XMLDB_INDEX_NOTUNIQUE, array('instituteid'));

        if (!$dbman->index_exists($table6, $index14)) {
            $dbman->add_index($table6, $index14);
        }

        $index15 = new xmldb_index('roomid', XMLDB_INDEX_NOTUNIQUE, array('roomid'));

        if (!$dbman->index_exists($table6, $index15)) {
            $dbman->add_index($table6, $index15);
        }

        $index16 = new xmldb_index('roomid', XMLDB_INDEX_NOTUNIQUE, array('roomid'));

        if (!$dbman->index_exists($table6, $index16)) {
            $dbman->add_index($table6, $index16);
        }

        $index17 = new xmldb_index('pretestid', XMLDB_INDEX_NOTUNIQUE, array('pretestid'));

        if (!$dbman->index_exists($table7, $index17)) {
            $dbman->add_index($table7, $index17);
        }

        $index18 = new xmldb_index('posttestid', XMLDB_INDEX_NOTUNIQUE, array('posttestid'));

        if (!$dbman->index_exists($table7, $index18)) {
            $dbman->add_index($table7, $index18);
        }

        $index19 = new xmldb_index('subdepartment', XMLDB_INDEX_NOTUNIQUE, array('subdepartment'));

        if (!$dbman->index_exists($table8, $index19)) {
            $dbman->add_index($table8, $index19);
        }

        $index20 = new xmldb_index('certificateid', XMLDB_INDEX_NOTUNIQUE, array('certificateid'));

        if (!$dbman->index_exists($table8, $index20)) {
            $dbman->add_index($table8, $index20);
        }


        upgrade_plugin_savepoint(true, 2022101800, 'local', 'classroom');
    }
    if($oldversion < 2022101800.03){
       
        $table = new xmldb_table('local_classroom');
        $field1 = new xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        upgrade_plugin_savepoint(true, 2022101800.03, 'local', 'classroom');
    }
    if ($oldversion < 2022101800.04) {
        $table = new xmldb_table('local_classroom');
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
        upgrade_plugin_savepoint(true, 2022101800.04, 'local', 'classroom');
    }
    if ($oldversion < 2022101800.05) {
        $table = new xmldb_table('local_classroom');
        $field1 = new xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

    if ($dbman->field_exists($table, $field1)) {
        $dbman->rename_field($table, $field1, 'open_path');
    }
    upgrade_plugin_savepoint(true, 2022101800.05, 'local', 'classroom');
}
    return true;
}
