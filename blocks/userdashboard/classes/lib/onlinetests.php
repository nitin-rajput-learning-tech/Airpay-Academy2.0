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
 * @package BizLMS
 * @subpackage block_userdashboard
 */
namespace block_userdashboard\lib;

// use renderable;
// use renderer_base;
// use templatable;
// use context_course;
// use stdClass;
// use file_encode_url;


class onlinetests{

    public static function inprogress_onlinetests($filter_text='', $mobile=false, $page=0, $perpage=10, $limit=false) {
        global $DB, $USER;
        // $sql = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description  FROM {local_classroom} as lc
        //         JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
        //         WHERE  lc.status=4 and lcu.userid=$USER->id ";
        $sqlquery = "SELECT a.*, ou.timecreated, ou.timemodified as joindates";
        $sqlcount = "SELECT COUNT(a.id)";
        $sql = " FROM {local_onlinetests} a, {local_onlinetest_users} ou
            WHERE a.id = ou.onlinetestid AND ou.userid = {$USER->id}
            AND a.visible = 1 AND ou.status = 0";
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%{$filter_text}%%'";
        }
        $sql .= " ORDER BY ou.timecreated DESC";
        if ($mobile) {
            if ($limit) {
                $perpage = 5;
            } else {
                $perpage = $perpage;
            }
            $inprogress_onlinetests = $DB->get_records_sql($sqlquery . $sql, array(), $page*$perpage, $perpage);
            $count = $DB->count_records_sql($sqlcount . $sql);
            return array($inprogress_onlinetests, $count);
        } else {
                    $inprogress_onlinetests = $DB->get_records_sql($sqlquery . $sql);
                //$_SESSION['classcom'] = count($coursenames);
                return $inprogress_onlinetests;
            }
    }
    /**********End of the function********/


    public static function completed_onlinetests($filter_text='', $mobile=false, $page=0, $perpage=10){
        global $DB,$USER;
        $sqlquery = "SELECT a.*, ou.timecreated, ou.timemodified as joindates";
        $sqlcount = "SELECT COUNT(a.id)";
        $sql = " FROM {local_onlinetests} a, {local_onlinetest_users} ou
            WHERE a.id = ou.onlinetestid AND ou.userid = $USER->id
            AND a.visible = 1 AND ou.status = 1";
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%{$filter_text}%%'";
        }
        $sql .= " ORDER BY ou.timemodified DESC";
        if ($mobile) {
            $completed_onlinetests = $DB->get_records_sql($sqlquery . $sql, array(), $page*$perpage, $perpage);
            $count = $DB->count_records_sql($sqlcount . $sql);
            return array($completed_onlinetests, $count);
        } else {
                    $completed_onlinetests = $DB->get_records_sql($sqlquery . $sql);
                //$_SESSION['classcom'] = count($coursenames);
                return $completed_onlinetests;
            }
    }

} // end of class

