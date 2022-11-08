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
 * This file keeps track of upgrades to the ltiprovider plugin
 *
 * @package    local
 * @subpackage learningplan
 * @copyright  2017 Anilkumar.cheguri <anil@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_local_assignroles_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

       if ($oldversion < 2022101400) {
        
        $table = new xmldb_table('local_org_dept_roles');
        $index = new xmldb_index('costcenterid', XMLDB_INDEX_NOTUNIQUE, array('costcenterid'));

        if (!$dbman->index_exists($table,$index)) {
            $dbman->add_index($table,$index);
        }

        $index1 = new xmldb_index('departmentid', XMLDB_INDEX_NOTUNIQUE, array('departmentid'));

        if (!$dbman->index_exists($table,$index1)) {
            $dbman->add_index($table,$index1);
        }

        $index2 = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        if (!$dbman->index_exists($table,$index2)) {
            $dbman->add_index($table,$index2);
        }

        $index3 = new xmldb_index('roleid', XMLDB_INDEX_NOTUNIQUE, array('roleid'));

        if (!$dbman->index_exists($table,$index3)) {
            $dbman->add_index($table,$index3);
        }

      upgrade_plugin_savepoint(true, 2022101400, 'local', 'org_dept_roles');
    }


    return true;
}
