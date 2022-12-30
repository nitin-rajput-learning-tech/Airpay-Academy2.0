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
use block_learnerscript\report;

class report_monthlycourselearners extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = array('columns','ordering', 'filters', 'permissions', 'plot');
        $columns = array('coursefield'=>['coursefield'], 'monthlycourselearnerscolumns' => ['year', 'month','monthyear', 'noofcompletions']);
        $this->columns = $columns;
        $this->filters = array('organization','departments', 'course');
        $this->orderable = array('monthyear', 'noofcompletions');
        $this->defaultcolumn = 'c.id';
        $this->groupcolumn = 'YEAR(FROM_UNIXTIME(cc.timecompleted)), MONTH(FROM_UNIXTIME(cc.timecompleted))';
    }

    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(c.id) ";
    }

    function select() {
        $this->sql = "SELECT c.id courseid, c.fullname as coursename,
    DATE_FORMAT( FROM_UNIXTIME( cc.timecompleted ) ,  '%M %Y' )   AS  'monthyear', DATE_FORMAT( FROM_UNIXTIME( cc.timecompleted ) ,  '%M' )   AS  'month', DATE_FORMAT( FROM_UNIXTIME( cc.timecompleted ) ,  '%M %Y' )   AS  'year',  count(cc.id) as noofcompletions " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {course} c ";
    }

    function joins() {
        $this->sql .=" JOIN {course_completions} cc ON c.id = cc.course
        JOIN {user} u ON u.id = cc.userid ";

        parent::joins();
    }

    function where() {
        global $USER, $DB;
        $this->sql .= " WHERE u.id > 2 AND u.deleted = 0 AND cc.timecompleted is not null ";

        $this->params['siteid'] = SITEID;

        $systemcontext = context_system::instance();
        // getscheduled report
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
            $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            // $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
            } else {
                $ohs = 1;
            }
        }
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $this->sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
            $this->sql .= " AND c.open_costcenterid = :costcenterid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
        }else{
            $this->sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
            $this->params['departmentid'] = $USER->open_departmentid;
        }

        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("c.fullname");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {

        if (!empty($this->params['filter_organization'])) {
            $orgids = $this->params['filter_organization'];
            $this->sql .= " AND u.open_costcenterid = :orgid ";
            $this->params['orgid'] = $orgids;
        }

        if (!empty($this->params['filter_departments'])) {
            $deptids = $this->params['filter_departments'];
            $this->sql .= " AND u.open_departmentid = :deptid ";
            $this->params['deptid'] = $deptids;
        }

        if(isset($this->params['filter_course']) && $this->params['filter_course'] > 0) {
            $this->sql .= " AND c.id = :courseid ";
            $this->params['courseid'] = $this->params['filter_course'];
        } else {
            $this->sql .= " AND c.id = 0 ";
        }
        // if ($this->ls_startdate > 0 && $this->ls_enddate) {
        //     $this->sql .= " AND u.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        // }
    }

    public function get_rows($courses) {
        return $courses;
    }
}