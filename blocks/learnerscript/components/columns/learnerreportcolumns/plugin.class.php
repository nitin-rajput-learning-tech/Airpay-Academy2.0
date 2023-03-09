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
 * @date: 2019
 */

use block_learnerscript\local\pluginbase;
use block_learnerscript\local\reportbase;
use local\user_course_details;

class plugin_learnerreportcolumns extends pluginbase
{
    public function init()
    {
        $this->fullname = get_string('coursescompletionscolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array();
    }
    public function summary($data)
    {
        return format_string($data->columname);
    }
    public function colformat($data)
    {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return array($align, $size, $wrap);
    }
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0)
    {
        global $CFG,$DB;   
       
        $finalassesresult = $this->get_finalassesment($row->courseid, $row->userid);
        switch ($data->column) {
            case 'progress':
                $course = $DB->get_record('course',array('id' => $row->courseid));
                $progress = \core_completion\progress::get_course_progress_percentage($course, $row->userid);
            /*    if(file_exists($CFG->dirroot.'/local/includes.php')){
                    require_once($CFG->dirroot.'/local/includes.php');
                    $includes = new \user_course_details();
                    $progress = $includes->user_course_completion_progress($row->courseid, $row->userid);
                } */
                if ($progress) {
                    $row->{$data->column} = $progress.'%';
                } else {
                    $row->{$data->column} = 0 .'%';
                }
                break; 
            case 'completionstatus':
                if (!is_null($row->completiondate)) {
                    $row->{$data->column} = 'Completed';
                } else {
                    $row->{$data->column} = 'Not Completed';
                }
                break;
            case 'completiondate':
                $row->{$data->column} = !empty($row->{$data->column}) ? date('d-m-Y', $row->{$data->column}) : 'NA';
                break;
            case 'enrolleddate':
                $row->{$data->column} = !empty($row->{$data->column}) ? date('d-m-Y', $row->{$data->column}) : 'NA';
                break;
            case 'finalassespassinggrade':
                $row->{$data->column} = $finalassesresult['passinggrade'];
                break;
            case 'finalassesachievedgrade':
                $row->{$data->column} = $finalassesresult['gradeachieved'];
                break;
                break;
            case 'finalgrade':
                $row->{$data->column} = $finalassesresult['finalgrade'];
                break;
                break;
            default:
                break;
        }

        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }


    function get_finalassesment($courseid, $userid)
    {
        global $DB;

        $sql = " SELECT gi.id FROM  {grade_items} gi WHERE gi.courseid= :courseid AND gi.itemtype = 'mod' AND gi.itemmodule = 'quiz' ";
        $coursequizids = $DB->get_fieldset_sql($sql, array('courseid' => $courseid));

        $coursesectionssql = "SELECT cs.sequence FROM {course_sections} cs WHERE cs.course = $courseid ORDER BY cs.section desc";
        $coursesections = $DB->get_records_sql($coursesectionssql);
        $quizid = 0;
        $finalassesmentgrade = '-';
        $gradeachieved = '-';
        $gradepass = '-';
        foreach ($coursesections as $cs) {
            $sequence = explode(',', $cs->sequence);
            $sections = (array_reverse($sequence));
            foreach ($sections as $qid) {
                $quizid = (in_array($qid, $coursequizids)) ? $qid : 0;
                break;
            }
        }

        if ($quizid) {
            $gradeitem = $DB->get_record_select('grade_items', 'id = :id', array('id' => $quizid), '*');
            $gradepass = ($gradeitem->gradepass) ? round($gradeitem->gradepass, 2) : '-';
            if ($gradeitem->id)
                $usergrade = $DB->get_record_sql("select * from {grade_grades} where itemid = $gradeitem->id AND userid = $userid");
            if ($usergrade) {
                $finalgrade = (round(($usergrade->finalgrade * 100) / $gradeitem->grademax)) . '%';
                $gradeachieved = (round($usergrade->finalgrade, 2));
            }
        }


        return array('passinggrade' => $gradepass, 'gradeachieved' => $gradeachieved, 'finalgrade' => $finalgrade);
    }
}
