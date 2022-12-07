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
 * LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2020
 */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;

class plugin_learningpathoverview extends pluginbase {

    public function init() {
        $this->fullname = get_string('learningpathoverview', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('learningpathoverview');
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

    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0, $reporttype = 'table') {
        global $DB, $CFG; 
        $learningpathid = isset($this->reportfilterparams['filter_learningpath']) ? $this->reportfilterparams['filter_learningpath'] : 0;
         
        switch($data->column) { 
            case 'completedcourses': 
                $courses = $DB->get_records_sql("SELECT DISTINCT c.id, c.fullname 
                        FROM {course} c 
                        JOIN {local_learningplan_courses} lpc ON c.id = lpc.courseid
                        WHERE lpc.planid = $learningpathid AND lpc.courseid IN (SELECT cc.course FROM {course_completions} cc WHERE cc.timecompleted IS NOT NULL AND cc.userid = $row->userid) AND c.visible = 1"); 
                foreach ($courses as $c) {
                    $data1 .= '<li><a href="'.$CFG->wwwroot.'/course/view.php?id='.$c->id.'" />'.$c->fullname.'</a></li>';
                }
                $row->{$data->column} = !empty($courses) ? $data1 : '--';
            break; 
            case 'missingcourses': 
                $courses = $DB->get_records_sql("SELECT DISTINCT c.id, c.fullname 
                        FROM {course} c 
                        JOIN {local_learningplan_courses} lpc ON c.id = lpc.courseid
                        WHERE lpc.planid = $learningpathid AND lpc.courseid NOT IN (SELECT cc.course FROM {course_completions} cc WHERE cc.timecompleted IS NOT NULL AND cc.userid = $row->userid) AND c.visible = 1"); 
                foreach ($courses as $c) {
                    $data1 .= '<li><a href="'.$CFG->wwwroot.'/course/view.php?id='.$c->id.'" />'.$c->fullname.'</a></li>';
                }
                $row->{$data->column} = !empty($courses) ? $data1 : '--';
            break; 
            case 'timespent': 
                $timespent = $DB->get_field_sql("SELECT SUM(timespent) FROM {block_ls_coursetimestats} WHERE userid = $row->userid AND courseid IN (SELECT DISTINCT c.id 
                        FROM {course} c 
                        JOIN {local_learningplan_courses} lpc ON c.id = lpc.courseid
                        WHERE lpc.planid = $learningpathid AND c.visible = 1)"); 
                if($reporttype == 'table'){
                    $row->{$data->column} = !empty($timespent) ? (new ls)->strTime($timespent) : '--';
                } else {
                    $row->{$data->column} = !empty($timespent) ? $timespent : 0;
                }
            break;
        } 
        return (isset($row->{$data->column}))? $row->{$data->column} : ' -- ';
    }
}
