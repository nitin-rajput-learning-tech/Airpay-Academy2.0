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
 * @subpackage block_learnerscript
 */
use block_learnerscript\local\pluginbase;

class plugin_learningplancompletionscolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('learningplancompletionscolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array();
    }

    public function summary($data) {
        return format_string($data->columname);
    }

    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return array($align, $size, $wrap);
    }

    // Data -> Plugin configuration data.
    // Row -> Complet user row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG;
            switch ($data->column) {
                case 'completionstatus':
                    $row->{$data->column} = ($row->completionstatus) ? 'Completed' : 'Not Completed';
                break;              
                case 'completiondate':
                    $row->{$data->column} = $row->{$data->column} ? date('d-m-Y',$row->{$data->column}) : '--';
                break;
                case 'totalcourse':
                    $totalcourse = $DB->count_records('local_learningplan_courses',array('planid' => $row->learningpathid));
                    $row->{$data->column} = $totalcourse ? $totalcourse : 0;
                break;
                case 'totalcoursecompleted':
                    $completedcourse = "SELECT count(distinct cc.id) FROM {course_completions} as cc
                        WHERE cc.userid =:userid AND cc.timecompleted IS NOT NULL AND cc.course IN (SELECT courseid FROM {local_learningplan_courses} WHERE planid =:lpid)" ;
                    $totalcompletecourse = $DB->count_records_sql($completedcourse, array('userid' => $row->userid, 'lpid' => $row->learningpathid));
                    $row->{$data->column} = $totalcompletecourse ? $totalcompletecourse : 0;
                break;
                case 'inprogresscourse':
                    $completedcourse = "SELECT count(ul.id) FROM {user_lastaccess} AS ul
                        WHERE ul.userid =:userid
                        AND ul.courseid IN (SELECT llc.courseid FROM {local_learningplan_courses} AS llc WHERE llc.planid =:lpid)
                        AND ul.courseid NOT IN (SELECT cc.course FROM mdl_course_completions AS cc WHERE cc.userid = ul.userid and cc.timecompleted IS NOT NULL)" ;
                    $totalinprogresscourse = $DB->count_records_sql($completedcourse, array('userid' => $row->userid, 'lpid' => $row->learningpathid));
                    $row->{$data->column} = $totalinprogresscourse ? $totalinprogresscourse : 0;
                break;
    /*             case 'enrolldays':
                    $pdiffdays = date('d-m-Y', $row->timecreated);
                    $odiffdays = date('d-m-Y');
                    $newpdate = date_create("$pdiffdays");
                    $existingpdate = date_create("$odiffdays");
                    $diffdays = date_diff($newpdate, $existingpdate);
                    $row->{$data->column} = $diffdays->format("%a days");
                break; */

            }
            
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }

}
