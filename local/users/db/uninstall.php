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


function xmldb_local_users_uninstall() {
    global $DB;
    $dbman = $DB->get_manager();
    $table = new xmldb_table('user');
    if ($dbman->table_exists($table)) {

        $opencostcenterpathfield = new xmldb_field('open_path');
        if ($dbman->field_exists($table, $opencostcenterpathfield)) {
            $dbman->drop_field($table, $opencostcenterpathfield);
        }

        $supervisorfield = new xmldb_field('open_supervisorid');
        if ($dbman->field_exists($table, $supervisorfield)) {
            $dbman->drop_field($table, $supervisorfield);
        }

        $employeefield = new xmldb_field('open_employeeid');
        if ($dbman->field_exists($table, $employeefield)) {
            $dbman->drop_field($table, $employeefield);
        }

        $usermodfield = new xmldb_field('open_usermodified');
        if ($dbman->field_exists($table, $usermodfield)) {
            $dbman->drop_field($table, $usermodfield);
        }

        $desigfield = new xmldb_field('open_designation');
        if ($dbman->field_exists($table, $desigfield)) {
            $dbman->drop_field($table, $desigfield);
        }

        $openstatefield = new xmldb_field('open_state');
        if ($dbman->field_exists($table, $openstatefield)) {
            $dbman->drop_field($table, $openstatefield);
        }

        $jobfnfield = new xmldb_field('open_jobfunction');
        if ($dbman->field_exists($table, $jobfnfield)) {
            $dbman->drop_field($table, $jobfnfield);
        }

        $groupfield = new xmldb_field('open_group');
        if ($dbman->field_exists($table, $groupfield)) {
            $dbman->drop_field($table, $groupfield);
        }

        $qualifield = new xmldb_field('open_qualification');
        if ($dbman->field_exists($table, $qualifield)) {
            $dbman->drop_field($table, $qualifield);
        }

        $locafield = new xmldb_field('open_location');
        if ($dbman->field_exists($table, $locafield)) {
            $dbman->drop_field($table, $locafield);
        }

        $supempidfield = new xmldb_field('open_supervisorempid');
        if ($dbman->field_exists($table, $supempidfield)) {
            $dbman->drop_field($table, $supempidfield);
        }

        $openbandfield = new xmldb_field('open_band');
        if ($dbman->field_exists($table, $openbandfield)) {
            $dbman->drop_field($table, $openbandfield);
        }

        $openhrmsrolefield = new xmldb_field('open_hrmsrole');
        if ($dbman->field_exists($table, $openhrmsrolefield)) {
            $dbman->drop_field($table, $openhrmsrolefield);
        }

        $openzonefield = new xmldb_field('open_zone');
        if ($dbman->field_exists($table, $openzonefield)) {
            $dbman->drop_field($table, $openzonefield);
        }

        $openregionfield = new xmldb_field('open_region');
        if ($dbman->field_exists($table, $openregionfield)) {
            $dbman->drop_field($table, $openregionfield);
        }

        $opengradefield = new xmldb_field('open_grade');
        if ($dbman->field_exists($table, $opengradefield)) {
            $dbman->drop_field($table, $opengradefield);
        }

        $openteamfield = new xmldb_field('open_team');
        if ($dbman->field_exists($table, $openteamfield)) {
            $dbman->drop_field($table, $openteamfield);
        }

        $openclientfield = new xmldb_field('open_client');
        if ($dbman->field_exists($table, $openclientfield)) {
            $dbman->drop_field($table, $openclientfield);
        }

        $field = new xmldb_field('open_states');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field1 = new xmldb_field('open_district');
        if ($dbman->field_exists($table, $field1)) {
            $dbman->drop_field($table, $field1);
        }

        $field2 = new xmldb_field('open_subdistrict');
        if ($dbman->field_exists($table, $field2)) {
            $dbman->drop_field($table, $field2);
        }

        $field3 = new xmldb_field('open_village');
        if ($dbman->field_exists($table, $field3)) {
            $dbman->drop_field($table, $field3);
        }

        $field5 = new xmldb_field('open_joindate');
        if ($dbman->field_exists($table, $field5)) {
            $dbman->drop_field($table, $field5);
        }
        $field6 = new xmldb_field('gender');
        if ($dbman->field_exists($table, $field6)) {
            $dbman->drop_field($table, $field6);
        }

        $field7 = new xmldb_field('open_dateofbirth');
        if ($dbman->field_exists($table, $field7)) {
            $dbman->drop_field($table, $field7);
        }
        $field8 = new xmldb_field('open_employmenttype');
        if ($dbman->field_exists($table, $field8)) {
            $dbman->drop_field($table, $field8);
        }

        $prefix = new xmldb_field('open_prefix');
        if ($dbman->field_exists($table, $prefix)) {
            $dbman->drop_field($table, $prefix);
        }

    }
    return true;
}
