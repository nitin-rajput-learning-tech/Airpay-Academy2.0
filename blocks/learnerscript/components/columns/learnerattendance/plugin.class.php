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
 * @date: 2017
 */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;

class plugin_learnerattendance extends pluginbase {

    public function init() {
        $this->fullname = get_string('learnerattendance', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('learnerattendance');
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
        global $DB, $CFG;
        if(!empty($row->action) && $row->action == 'loggedout'){
            $timelogout = !empty($row->timecreated) ? $row->timecreated : '-' ;
            $logout = $timelogout;
        }
         $sql = "SELECT lsl.timecreated FROM {logstore_standard_log} lsl WHERE 1 = 1 AND lsl.target = 'user' AND lsl.crud = 'r' AND lsl.action LIKE 'loggedin' AND lsl.timecreated < $timelogout AND lsl.userid = $row->userid ORDER BY id DESC LIMIT 1 ";
        $tlogin = $DB->get_field_sql($sql);
        $timelogin = $tlogin ? $tlogin :'' ; 
        $login = $timelogin;
        if(isset($timelogin) && !empty($timelogin)){
             $sql1 = "SELECT lsl.timecreated FROM {logstore_standard_log} lsl WHERE 1 = 1 AND lsl.target = 'user' AND lsl.crud = 'r' AND lsl.action LIKE 'loggedout' AND lsl.timecreated > $timelogin AND lsl.timecreated < $timelogout AND lsl.userid = $row->userid";
            $emptyloggedin = $DB->get_field_sql($sql1) ;
        }else{
            $emptyloggedin = '';
        }
       
        if(empty($emptyloggedin)){
            $timelogin = $timelogin;
        }else{
            $timelogin ='';
        }

        if(isset($timelogin) && !empty($timelogin) ){
        switch ($data->column) {
            case 'date':
                if(!empty($row->action) && $row->action == 'loggedout'){
                   $row->{$data->column} = !empty($timelogout) ? userdate($timelogin,'%d-%m-%Y') : '-'; 
                }               
                break;
            case 'timeloggedout':
                if(!empty($row->action) && $row->action == 'loggedout'){
                    // $row->{$data->column} = !empty($timelogout) ? gmdate('h:i:s A', $timelogout) : '-';
                    $row->{$data->column} = !empty($timelogout) ? strftime('%r', $timelogout) : '-';
                }
                
                break;
            case 'timeloggedin':
                $row->{$data->column} = !empty($timelogin) ? strftime('%r', $timelogin) : '-';
                
                break;
            case 'totaltimespent':
                    $row->{$data->column} = !empty($timelogout) ? (new ls)->strTime($logout - $login): '-';                
                break;
            case 'activitycompleted':
                $courses = $DB->get_records_sql("SELECT c.fullname FROM {course} c JOIN {logstore_standard_log} cc ON cc.courseid = c.id WHERE cc.relateduserid = $row->userid AND cc.objecttable LIKE '%completion%' AND cc.timecreated BETWEEN $login AND $logout");
                foreach ($courses as $course) {
                    $courseslist[] = $course->fullname;
                }

                $modules = $DB->get_fieldset_select('modules', 'name', '', array('visible' => 1));
                foreach ($modules as $modulename) {
                    $aliases[] = $modulename;
                    $activities[] = "'$modulename'";
                    $fields1[] = "COALESCE($modulename.name,'')";
                }
                $activitynames = implode(', ', $fields1);

                $activitiessql = "SELECT CONCAT($activitynames) AS activityname FROM {course_modules} as main
                       JOIN {modules} m ON main.module = m.id 
                       JOIN {logstore_standard_log} cmc ON cmc.contextinstanceid = main.id AND cmc.courseid = main.course";
                foreach ($aliases as $alias) {
                    $activitiessql .= " LEFT JOIN {".$alias."} AS $alias ON $alias.id = main.instance AND m.name = '$alias'";
                }
                $activitiessql .= "  WHERE m.visible = 1 AND main.visible = 1 AND  cmc.timecreated BETWEEN $login AND $logout AND cmc.relateduserid = $row->userid AND cmc.objecttable LIKE '%completion%'";
                $activities = $DB->get_records_sql($activitiessql);

                foreach($activities as $activity) {
                    $activitylist[] = $activity->activityname;
                }
                if(!empty($courseslist) && !empty($activitylist)){
                    $completionlist = implode(',', array_merge($activitylist, $courseslist));
                }else if(empty($courseslist) && !empty($activitylist)){
                    $completionlist = implode(',', $activitylist);

                }else if(!empty($courseslist) && empty($activitylist)){
                    $completionlist = implode(',', $courseslist);
                }else{
                    $completionlist = '';
                }
                $row->{$data->column} = !empty($completionlist) ? $completionlist : '-';
                break;
        }
        return (isset($row->{$data->column}))? $row->{$data->column} : ' -- ';
    }
        
    }
}
