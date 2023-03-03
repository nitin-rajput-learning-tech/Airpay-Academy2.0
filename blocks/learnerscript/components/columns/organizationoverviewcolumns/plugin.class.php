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

/** LearnerScript Reports
  * A Moodle block for creating customizable reports
  * @package blocks
  * @subpackage learnerscript
  * @author: sowmya<sowmya@eabyas.in>
  * @date: 2016
  */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\reportbase;

class plugin_organizationoverviewcolumns extends pluginbase{
    public function init(){
        $this->fullname = get_string('organizationoverviewcolumns','block_learnerscript');
        $this->type = 'undefined';
        $this->form = false;
        $this->reporttypes = array();
    }
    public function summary($data){
        return format_string($data->columname);
    }
    public function colformat($data){
        $align = (isset($data->align))? $data->align : '';
        $size = (isset($data->size))? $data->size : '';
        $wrap = (isset($data->wrap))? $data->wrap : '';
        return array($align,$size,$wrap);
    }
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG, $OUTPUT;
        // print_r($row);
        // $costcenterrecord = $DB->get_record('local_costcenter',array('depth' => 1));


        switch ($data->column) {

            case 'totaldepartment':
                $departmentcount = $DB->count_records('local_costcenter',array('parentid' => $row->id, 'depth' => 2));
                $costcenterrecord->{$data->column} = $departmentcount;
            break;

            case 'totalcourses':
                $path="'/$row->id%'";
                $sql = "SELECT COUNT(c.id) FROM {course} c WHERE c.open_path LIKE $path";
                $coursecount = $DB->count_records_sql($sql,array());
                $costcenterrecord->{$data->column} = $coursecount;
            break;

            case 'totallp':
                $path="'/$row->id%'";
                $sql = "SELECT COUNT(lp.id) FROM {local_learningplan} lp WHERE lp.open_path LIKE $path";
                $lpcount = $DB->count_records_sql($sql,array());
                $costcenterrecord->{$data->column} = $lpcount;
            break;

            case 'totalilts':
                $path="'/$row->id%'";
                $sql = "SELECT COUNT(lc.id) FROM {local_classroom} lc WHERE lc.open_path LIKE $path";
                $classroomcount = $DB->count_records_sql($sql,array());
                $costcenterrecord->{$data->column} = $classroomcount;
            break;

            case 'totalprogram':
                $path="'/$row->id%'";
                $sql = "SELECT COUNT(lp.id) FROM {local_program} lp WHERE lp.open_path LIKE $path";
                $classroomcount = $DB->count_records_sql($sql,array());
                $costcenterrecord->{$data->column} = $classroomcount;
            break;

            case 'totalactuser':
                $path="'/$row->id%'";
                $sql = "SELECT COUNT(u.id) FROM {user} u WHERE u.suspended = 0 AND u.deleted = 0 AND u.open_path LIKE $path";
                $usercount = $DB->count_records_sql($sql,array());
                $costcenterrecord->{$data->column} = $usercount;
            break;

            default:
                $costcenterrecord->{$data->column} = isset($row->{$data->column}) ? $row->{$data->column} : $row->{$data->column};
            break;
        }
        return (isset($costcenterrecord->{$data->column})) ? $costcenterrecord->{$data->column} : 'NA';
    }
}