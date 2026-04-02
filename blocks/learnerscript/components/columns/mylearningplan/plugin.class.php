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

class plugin_mylearningplan extends pluginbase {

    public function init() {
        $this->fullname = get_string('mylearningplan', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('mylearningplan');
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
        global $DB, $USER;
        switch ($data->column) {
            case 'enrolledon':
                $row->{$data->column} = date('d-M-Y',$row->{$data->column});
                break;
            case 'totalcourses':
                $sql = "SELECT COUNT(lc.id)
                        FROM {local_learningplan_courses} lc
                        JOIN {course} c ON c.id = lc.courseid 
                        WHERE lc.planid =:lplanid ";
                $totalcourses = $DB->count_records_sql($sql, array('lplanid'=>$row->learningpathid));
                $row->{$data->column} = $totalcourses;
                break;
            case 'mandatorycourses':
                $sql = "SELECT COUNT(lc.id)
                        FROM {local_learningplan_courses} lc
                        JOIN {course} c ON c.id = lc.courseid 
                        WHERE lc.planid =:lplanid AND lc.nextsetoperator =:mand ";
                $mandatorycourses = $DB->count_records_sql($sql, array('lplanid'=>$row->learningpathid,'mand'=>'and'));
                $row->{$data->column} = $mandatorycourses;
                break;
            case 'optionalcourses':
                $sql = "SELECT COUNT(lc.id)
                        FROM {local_learningplan_courses} lc 
                        JOIN {course} c ON c.id = lc.courseid 
                        WHERE lc.planid =:lplanid AND lc.nextsetoperator =:opt ";
                $optionalcourses = $DB->count_records_sql($sql, array('lplanid'=>$row->learningpathid,'opt'=>'or'));
                $row->{$data->column} = $optionalcourses;
                break;
            case 'completedcourses':
                $sql = "SELECT COUNT(cc.id)
                        FROM {course_completions} cc
                        JOIN {local_learningplan_courses} lc ON lc.courseid = cc.course 
                        JOIN {course} c ON c.id = lc.courseid 
                        WHERE lc.planid =:lplanid AND cc.userid =:userid 
                        AND cc.timecompleted IS NOT NULL ";

                $completedcourses = $DB->count_records_sql($sql,array('lplanid'=>$row->lplanid,
                                                                        'userid'=>$USER->id));

                $row->{$data->column} = $completedcourses;
                break;

            case 'completionstatus':
                $row->{$data->column} = ($row->{$data->column} == 1) ? 'Completed' : 'Not Completed';
                break;
            case 'completiondate':
                $row->{$data->column} = ($row->{$data->column}) ? date('d-M-Y',$row->{$data->column}) : 'NA';
                break;
        }
         return (isset($row->{$data->column}))? $row->{$data->column} : ' -- ';
    }
}