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

defined('MOODLE_INTERNAL') || die();

function xmldb_block_my_event_calendar_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();
    if ($oldversion < 2017111302) {
        $table = new xmldb_table('event');
        
        $pluginfield = new xmldb_field('plugin',XMLDB_TYPE_CHAR, '225', null,null,null,null);
        if (!$dbman->field_exists($table, $pluginfield)) {
            $dbman->add_field($table, $pluginfield);
        }
        $instancefield = new xmldb_field('plugin_instance', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
        if (!$dbman->field_exists($table, $instancefield)) {
            $dbman->add_field($table, $instancefield);
        }
        $relateduseridfield = new xmldb_field('relateduserid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
        if (!$dbman->field_exists($table, $relateduseridfield)) {
            $dbman->add_field($table, $relateduseridfield);
        }
        $eventtypefield = new xmldb_field('local_eventtype', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        if (!$dbman->field_exists($table, $eventtypefield)) {
            $dbman->add_field($table, $eventtypefield);
        }
        $pluginitemfield = new xmldb_field('plugin_itemid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
        if (!$dbman->field_exists($table, $eventtypefield)) {
            $dbman->add_field($table, $eventtypefield);
        }
        upgrade_plugin_savepoint(true, 2017111301, 'block', 'my_event_calendar');
    }
    
    if ($oldversion < 2017111303) {
        $table = new xmldb_table('event');
        
        $pluginitemfield = new xmldb_field('plugin_itemid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, '0');
        if (!$dbman->field_exists($table, $pluginitemfield)) {
            $dbman->add_field($table, $pluginitemfield);
        }
        upgrade_plugin_savepoint(true, 2017111303, 'block', 'my_event_calendar');
    }
    
    return true;
}