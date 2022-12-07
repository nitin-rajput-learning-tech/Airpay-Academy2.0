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
 * @package block_learnerscript
 */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;
use core_completion\progress;

class plugin_classroomcolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('classroomcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('classroomcolumns');
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

    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG,$OUTPUT;
        switch ($data->column) {
            case 'enrolmentdate':
                if(!isset($row->enrolmentdate) && isset($data->subquery)){
                    $enrolmentdate = $DB->get_field_sql($data->subquery);
                }else{
                    $enrolmentdate = $row->{$data->column};
                }
                $row->{$data->column} = !empty($enrolmentdate) ? strftime('%d-%m-%Y', $enrolmentdate) : '--';
                break;
            case 'completiondate':
                if(!isset($row->completiondate) && isset($data->subquery)){
                    $completiondate = $DB->get_field_sql($data->subquery);
                }else{
                    $sessionsql = "SELECT lca.sessionid FROM {local_classroom_attendance} lca WHERE lca.userid = {$row->userid} AND lca.classroomid = {$row->classroomid} ";
                    $sessionid = $DB->get_field_sql($sessionsql);
                    $sql = "SELECT bll.completiondate  
                        FROM {block_ls_learningformats} as bll 
                        WHERE 1 = 1 AND bll.id = $row->id AND bll.moduleid = 10 AND bll.learningformatid = {$row->classroomid} AND bll.userid = {$row->userid}"; //AND lca.sessionid = $sessionid ";
                    $completiondate = $DB->get_field_sql($sql);
                }
                $row->{$data->column} = !empty($completiondate) ? strftime('%d-%m-%Y', $completiondate) : '--';
                break;
            case 'coursedate':
                if(!isset($row->coursedate) && isset($data->subquery)){
                    $coursedate = $DB->get_field_sql($data->subquery);
                }else{
                     $coursedate = $row->{$data->column};
                    // $coursesql = "SELECT lca.timecreated FROM {local_classroom_attendance} lca WHERE lca.userid = {$row->userid} AND lca.classroomid = {$row->classroomid} ";
                    $coursesql = "SELECT lcs.timestart 
                                    FROM {local_classroom_attendance} as lbu 
                                    JOIN {local_classroom} as lb ON lbu.classroomid = lb.id 
                                    JOIN {local_classroom_sessions} as lcs ON lcs.classroomid = lbu.classroomid AND lbu.sessionid = lcs.id
                                    WHERE lcs.classroomid = {$row->classroomid} AND lbu.userid = {$row->userid} AND lcs.id = {$row->sessionid}";
                     $coursedate = $DB->get_field_sql($coursesql);
                }
                $row->{$data->column} = !empty($coursedate) ? strftime('%d-%m-%Y', $coursedate) : '--';
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}
