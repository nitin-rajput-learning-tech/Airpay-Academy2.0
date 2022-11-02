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
 * @package     local_notifications
 * @author:     M Arun Kumar <arun@eabyas.in>
 *
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_local_notifications_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2017111300) {
        $table = new xmldb_table('local_notification_info');
        $field = new xmldb_field('adminbody',XMLDB_TYPE_TEXT, 'big', null, null, null,null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017111300, 'local', 'notifications');
    }
    if ($oldversion < 2017111301) {
        $table = new xmldb_table('local_notification_info');
        $field = new xmldb_field('moduletype', XMLDB_TYPE_CHAR, '250', null, null, null,null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('moduleid', XMLDB_TYPE_TEXT, 'big', null, null, null,null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2017111301, 'local', 'notifications');
    }
    if ($oldversion < 2017111305.01) {
        $table = new xmldb_table('local_notification_info');
        $field = new xmldb_field('completiondays', XMLDB_TYPE_INTEGER, '10', null, null, null,0, null);
        if(!$dbman->field_exists($table,  $field)){
            $dbman->add_field($table,  $field);
        }
        upgrade_plugin_savepoint(true, 2017111305.01, 'local', 'notifications');
    }
    // if ($oldversion < 2017111302) {
    //     $time = time();
    //     $notification_type_data = array(
    //     array('name' => 'Program','shortname' => 'program','parent_module' => '0','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Enrollment','shortname' => 'program_enrol','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Unenrollment','shortname' => 'program_unenroll','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Completion','shortname' => 'program_completion','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Level Completion','shortname' => 'program_level_completion','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Session Enrollment','shortname' => 'program_session_enrol','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Session Reschedule','shortname' => 'program_session_reschedule','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Session Attendance','shortname' => 'program_session_attendance','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Session Reminder (before startdate)','shortname' => 'program_session_reminder','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Session Cancel','shortname' => 'program_session_cancel','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL),
    //     array('name' => 'Program Session Completion','shortname' => 'program_session_completion','parent_module' => '51','usermodified' => NULL,'timemodified' => NULL)  
    //         );
    //     foreach($notification_type_data as $notification_type){
    //         if($DB->record_exists('local_notification_type', array('name' => $notification_type['name'], 'shortname' => $notification_type['shortname']))){
    //             $DB->delete_records('local_notification_type', $notification_type);
    //         } else {
    //                $notification_type_obj = (object)$notification_type;
    //             $DB->insert_record('local_notification_type', $notification_type_obj);
    //         }
    //     }
    //     $time = time();
    //     $strings = array(
    //         array('name' => '[program_name]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_stream]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_startdate]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_enddate]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_level]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_session_username]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_sessionsinfo]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_enroluserfulname]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_link]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_enroluseremail]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_session_useremail]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_session_trainername]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_session_attendance]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_session_startdate]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_session_enddate]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_completiondate]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_organization]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_course]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_creater]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_level_link]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_lc_course_link]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_lc_course_sessions_link]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_lc_course_creater]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_level_creater]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_lc_course_sessions_creater]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_level_completiondate]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_lc_course_completiondate]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_lc_course__session_completiondate]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_session_link]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_session_name]','module' => 'program','usermodified' => NULL,'timemodified' => NULL),
    //         array('name' => '[program_session_completiondate]','module' => 'program','usermodified' => NULL,'timemodified' => NULL) 
    //         );
    //     foreach($strings as $string){
    //         if($DB->record_exists('local_notification_strings', array('name' => $string['name'], 'module' => $string['module']))){
    //             $DB->delete_records('local_notification_strings', $string);
    //         } else {
    //             $string_obj = (object)$string;
    //             $DB->insert_record('local_notification_strings', $string_obj);
    //         }
    //     }
         
    //     upgrade_plugin_savepoint(true, 2017111302, 'local', 'notifications');
    // }
    if ($oldversion < 2017111305.04) {
        $table = new xmldb_table('local_notification_info');
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('attach_certificate', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            if(!$dbman->field_exists($table, $field)){
                $dbman->add_field($table, $field);
            }
            upgrade_plugin_savepoint(true, 2017111305.04, 'local', 'notifications');
        }
    }


    if ($oldversion < 2022101800) {
        
        $table = new xmldb_table('local_notification_info');
        
        $index = new xmldb_index('costcenterid', XMLDB_INDEX_NOTUNIQUE, array('costcenterid'));

        if (!$dbman->index_exists($table,$index)) {
            $dbman->add_index($table,$index);
        }

        $index1 = new xmldb_index('notificationid', XMLDB_INDEX_NOTUNIQUE, array('notificationid'));

        if (!$dbman->index_exists($table,$index1)) {
            $dbman->add_index($table,$index1);
        }
        
     upgrade_plugin_savepoint(true, 2022101800, 'local', 'notification_info');
   }
    return true;
}
