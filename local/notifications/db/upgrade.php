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
   if ($oldversion < 2022101800.03) {
    $table = new xmldb_table('local_notification_info');
    if ($dbman->table_exists($table)) {
        $field = new xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if(!$dbman->field_exists($table, $field)){
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2022101800.03, 'local', 'notifications');
    }
}

    return true;
}
