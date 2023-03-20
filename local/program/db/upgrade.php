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
 * program Upgrade
 *
 * @package     local_program
 * @author:     M Arun Kumar <arun@eabyas.in>
 *
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_local_program_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2022101800.05) {

        $DB->delete_records('local_notification_strings',array('module'=>'program'));
        $DB->delete_records('local_notification_type',array('pluginname'=>'program'));

        $time = time();
        $initcontent = array('name' => 'Program','shortname' => 'program','parent_module' => '0','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL, 'pluginname' => 'program');
        $parentid = $DB->get_field('local_notification_type', 'id', array('shortname' => 'program'));
        if(!$parentid){
            $parentid = $DB->insert_record('local_notification_type', $initcontent);
        }
        $notification_type_data = array(
            array('name' => 'Program Enrollment','shortname' => 'program_enrol','parent_module' => $parentid,'usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL, 'pluginname' => 'program'),
            array('name' => 'Program Unenrollment','shortname' => 'program_unenroll','parent_module' => $parentid,'usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL, 'pluginname' => 'program'),
            array('name' => 'Program Completion','shortname' => 'program_completion','parent_module' => $parentid,'usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL, 'pluginname' => 'program'),
            array('name' => 'Program Level Completion','shortname' => 'program_level_completion','parent_module' => $parentid,'usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL, 'pluginname' => 'program')
        );
        foreach($notification_type_data as $notification_type){
            unset($notification_type['timecreated']);
            if(!$DB->record_exists('local_notification_type',  $notification_type)){
                $notification_type['timecreated'] = $time;
                $DB->insert_record('local_notification_type', $notification_type);
            }
        }
        $strings = array(
            array('name' => '[program_name]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_startdate]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_enddate]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_level]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_enroluserfulname]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_link]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_enroluseremail]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_completiondate]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_organization]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_course]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_creater]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_level_link]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_lc_course_link]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_lc_course_creater]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_level_creater]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_level_completiondate]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
            array('name' => '[program_lc_course_completiondate]','module' => 'program','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL)
        );
        foreach($strings as $string){
            unset($string['timecreated']);
            if(!$DB->record_exists('local_notification_strings', $string)){
                $string_obj = (object)$string;
                $string_obj->timecreated = $time;
                $DB->insert_record('local_notification_strings', $string_obj);
            }
        }

        upgrade_plugin_savepoint(true, 2022101800.05, 'local', 'program');
    }
    //added by sachin for skill and level
    if ($oldversion < 2022101800.06) {
        $table = new xmldb_table('local_program');
        $field = new xmldb_field('open_skill', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
		$field1 = new xmldb_field('open_level', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        upgrade_plugin_savepoint(true, 2022101800.06, 'local', 'program');
    }

    return true;
}
