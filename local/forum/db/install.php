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
function xmldb_local_forum_install()
{
    global $CFG, $DB, $USER;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes
    $table = new xmldb_table('course');
    if ($dbman->table_exists($table)) {

        $field1 = new xmldb_field('open_module');
        $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        $field1 = new xmldb_field('open_coursetype', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
    }
    // $time = time();
    // $initcontent = array('name' => 'Online Exam', 'shortname' => 'forum', 'parent_module' => '0', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'forum');
    // $parentid = $DB->get_field('local_notification_type', 'id', array('shortname' => 'forum'));
    // if (!$parentid) {
    //     $parentid = $DB->insert_record('local_notification_type', $initcontent);
    // }
    // $notification_type_data = array(
    //     array('name' => 'Online Exam Enrollment', 'shortname' => 'forum_enrol', 'parent_module' => $parentid, 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'forum'),
    //     array('name' => 'Online Exam Completion', 'shortname' => 'forum_complete', 'parent_module' => $parentid, 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'forum'),
    //     array('name' => 'Online Exam Unenrollment', 'shortname' => 'forum_unenroll', 'parent_module' => $parentid, 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'forum'),

    // );
    // foreach ($notification_type_data as $notification_type) {
    //     unset($notification_type['timecreated']);
    //     if (!$DB->record_exists('local_notification_type',  $notification_type)) {
    //         $notification_type['timecreated'] = $time;
    //         $DB->insert_record('local_notification_type', $notification_type);
    //     }
    // }
    // $strings = array(
    //     array('name' => '[forum_title]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[enroluser_fullname]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[enroluser_email]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_code]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_enrolstartdate]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_enrolenddate]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_completiondays]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_department]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_link]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_duedate]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_description]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_url]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_description]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_image]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_completiondate]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_reminderdays]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL),
    //     array('name' => '[forum_categoryname]', 'module' => 'forum', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL)
    // );
    // foreach ($strings as $string) {
    //     unset($string['timecreated']);
    //     if (!$DB->record_exists('local_notification_strings', $string)) {
    //         $string_obj = (object)$string;
    //         $string_obj->timecreated = $time;
    //         $DB->insert_record('local_notification_strings', $string_obj);
    //     }
    // }
}
