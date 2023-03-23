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

use block_learnerscript\local\reportbase;
use block_learnerscript\local\querylib;
use block_learnerscript\report;

class report_mycoursess extends reportbase implements report
{

    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties)
    {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns', 'permissions', 'filters');
        $this->columns = ['coursefield' => ['coursefield'], 'mycoursess' => ['coursename', 'coursetotal', 'completionstatus', 'completiondate']];
        //$this->filters = ['mycoursescolumn'];
        $this->orderable = array('coursename');
        $this->defaultcolumn = 'ue.id';
    }
    function init()
    {
        parent::init();
    }
    function count()
    {
        $this->sql = "SELECT count(ue.id)";
    }
    function select()
    {
        $this->sql = "SELECT ue.id, c.id as courseid, c.open_categoryid,cc.timecompleted as completiondate,c.fullname as coursename";

        parent::select();
    }
    function from()
    {
        $this->sql .= " FROM {user_enrolments} as ue";
    }
    function joins()
    {
        $this->sql .= " JOIN {enrol} as e ON e.id = ue.enrolid
                JOIN {role_assignments} as ra ON ra.userid = ue.userid
                JOIN {context} AS cxt ON cxt.id = ra.contextid AND cxt.contextlevel = 50
                                        AND cxt.instanceid = e.courseid
                JOIN {role} as r ON r.id = ra.roleid AND r.shortname  IN ('employee','student')
                JOIN {course} as c ON c.id = e.courseid
                LEFT JOIN {course_completions} as cc ON cc.course = e.courseid and cc.userid = ue.userid ";
        parent::joins();
    }
    function where()
    {
        global $USER, $DB;
        $this->sql .=  " WHERE ue.userid = $USER->id AND c.visible = 1 AND c.open_coursetype = :type";
        $this->params['type'] = 0; //CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') 
        // getscheduled report
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid' => $this->reportid, 'sendinguserid' => $USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
                $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
                $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid' => $scheduledreport->roleid, 'capability' => 'local/costcenter:manage_ownorganization']);
                $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid' => $scheduledreport->roleid, 'capability' => 'local/costcenter:manage_owndepartments']);
            } else {
                $ohs = $dhs = 1;
            }
        }
        parent::where();
    }
    function search()
    {
        if (isset($this->search) && $this->search) {
            $fields = array("c.fullname");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters()
    {
        if (!empty($this->params['filter_mycoursescolumn'])) {
            $this->sql .= " AND c.id = :coursename";
            $this->params['coursename'] = $this->params['filter_mycoursescolumn'];
        }

        if($this->ls_startdate > 0 && $this->ls_enddate > 0){
            $this->sql .= " AND cc.timecompleted > :report_startdate ";
            $this->params['report_startdate'] = $this->ls_startdate;

            $this->sql .= " AND cc.timecompleted < :report_enddate ";
            $this->params['report_enddate'] = $this->ls_enddate;
        }
    }
    function get_rows($mycourses)
    {
        return $mycourses;
    }
}
