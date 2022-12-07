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
use block_learnerscript\local\querylib;
defined('MOODLE_INTERNAL') || die();
class report_coursescompletions extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        global $DB;
        parent::__construct($report, $reportproperties);
        $this->columns = ['userfield' => ['userfield'], 'coursefield' => ['coursefield'], 'coursescompletionscolumns' => ['coursename','duration','enrolmentmethod', 'enrolledon','completion_percentage','completionstatus','completiondate','startdate','couponcode','couponissuedate','couponexpirydate','coursestartdate','completiondays','daystaken']];
        $this->components = array('columns', 'conditions', 'filters','permissions','orderable');
        $this->filters = array('organization', 'departments','subdepartments','course','user','completionstatus');
        $this->parent = true;
        $this->orderable = array('coursename');
        $this->defaultcolumn = 'ue.id';
    }

    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(ue.id) ";
    }

    function select() {
        $this->sql = " SELECT ue.id,u.id as userid
                        , DATE_FORMAT(FROM_UNIXTIME(ue.timecreated),'%d-%m-%Y %H:%i') as enrolledon
                        , cc.timecompleted 
                        , ue.timecreated as enrolstarted
                        , c.id as courseid 
                        , c.fullname as coursename
                        , c.open_coursecompletiondays as completiondays
                        , e.enrol as enrolmentmethod  " ;
//        $this->sql = " SELECT ue.id, c.id as courseid, u.id as userid,c.fullname as coursename,
 //                   cc.timecompleted AS completionstatus, cc.timecompleted AS completiondate " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {course} c ";
    }

    function joins() {
        $this->sql .=" JOIN {course_categories} cat ON cat.id = c.category
                        JOIN {enrol} e ON e.courseid = c.id  
                        JOIN {user_enrolments} ue ON ue.enrolid = e.id
                        JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 
                                        AND u.deleted = 0 AND u.suspended = 0
                        JOIN {local_costcenter} lc ON lc.id = u.open_costcenterid
                        JOIN {role_assignments} as ra ON ra.userid = u.id
                        JOIN {context} AS cxt ON cxt.id=ra.contextid AND cxt.contextlevel = 50 AND cxt.instanceid=c.id
                        JOIN {role} as r ON r.id = ra.roleid AND r.shortname = 'employee'
                        LEFT JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid ";

        parent::joins();
    }

    function where() {
        global $USER, $DB;
        $this->sql .= " WHERE c.id <> :siteid AND 
                        CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";
        $this->params['siteid'] = SITEID;

        $systemcontext = context_system::instance();
        // getscheduled report
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
            $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
            } else {
                $ohs = $dhs=1;
            }
        }
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $this->sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
            $this->sql .= " AND c.open_costcenterid = :costcenterid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
            $this->sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
            $this->params['departmentid'] = $USER->open_departmentid;
        }else{
            $this->sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid AND c.open_subdepartment = :subdepartmentid";
            $this->params['costcenterid'] = $USER->open_costcenterid;
            $this->params['departmentid'] = $USER->open_departmentid;
            $this->params['subdepartmentid'] = $USER->open_subdepartment;
        }

        parent::where();
    }


    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('c.fullname',"CONCAT(u.firstname,' ',u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        if (!empty($this->params['filter_organization'])) {
            $orgids = $this->params['filter_organization'];
            $this->sql .= " AND c.open_costcenterid = :orgid ";
            $this->params['orgid'] = $orgids;
        }

        if (!empty($this->params['filter_departments'])) {
            $departmentid = $this->params['filter_departments'];
            $this->sql .= " AND c.open_departmentid = :departmentid ";
            $this->params['departmentid'] = $departmentid;
        }

        if (!empty($this->params['filter_subdepartments']) && ($this->params['filter_subdepartments'] > 0)) {
            $subdepartmentid = $this->params['filter_subdepartments'];
            $this->sql .= " AND c.open_subdepartment = :subdepartmentid ";
            $this->params['subdepartmentid'] = $subdepartmentid;
        }

        if (!empty($this->params['filter_course'])) {
            $courseid = $this->params['filter_course'];
            $this->sql .= " AND c.id = :courseid ";
            $this->params['courseid'] = $courseid;
        }

        if (!empty($this->params['filter_user'])) {
            $userid = $this->params['filter_user'];
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $userid;
        }

        if ($this->params['filter_completionstatus'] == 1) {
           $this->sql .= " AND cc.timecompleted IS NOT NULL ";
        }

        if ($this->params['filter_completionstatus'] == 0) {
            $this->sql .= " AND cc.timecompleted IS NULL ";
        }
        // echo $this->sql;
        // print_r($this->params);exit;
    }

    public function get_rows($courseusers) {
        return $courseusers;
    }
}
