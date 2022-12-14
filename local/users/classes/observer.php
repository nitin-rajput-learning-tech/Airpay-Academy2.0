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
 * Event observer for local_users. Dont let other user to view unauthorized users
 */
class local_users_observer extends \core\event\user_profile_viewed {


    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $DB, $CFG, $USER;

        $related_userid  = $event->data['objectid'];
        
        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
        $today = \local_costcenter\lib::get_userdate('d.m.Y');
        $ystart = strtotime($today);
        $presentday = \local_costcenter\lib::get_userdate('j', $ystart);
        $presentmonth = (int)\local_costcenter\lib::get_userdate('m', $ystart);
        $presentyear = \local_costcenter\lib::get_userdate('Y', $ystart);
        $data = new \stdclass();
        $alreadyintoday = $DB->get_field_sql("select userid from {local_uniquelogins} where userid = ? AND month = ?
         AND year = ?  AND day = ? AND userid != 2 ", [$related_userid, $presentmonth, $presentyear, $presentday]);
        if (empty($alreadyintoday)) {
            $data->userid = $related_userid;
            $data->day = $presentday;
            $data->month = $presentmonth;
            $data->year = $presentyear;
            $data->timemodified = time();
            $data->count_date = $ystart;
            $data->type = 'web';
            $DB->insert_record('local_uniquelogins', $data);
        }

    }
}
