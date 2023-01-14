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

class report_coursesoverview extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->components = array('columns','ordering', 'filters', 'permissions', 'plot');
        $columns = array('coursefield'=>['coursefield'], 'coursesoverviewcolumns' => ['noofenrollments', 'noofcompletions']);   
        $this->columns = $columns;
        $this->filters = array('organization','departments', 'subdepartments', 'course');
        $this->orderable = array('coursename', 'noofenrollments', 'noofcompletions');
        $this->defaultcolumn = 'c.id';
    }

    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(c.id) ";
    }

    function select() {
        $this->sql = "SELECT c.id courseid, c.fullname as coursename, c.open_path as course_open_path,
                    (SELECT COUNT(DISTINCT(ue.id))
                        FROM {user_enrolments} ue
                        JOIN {user} u ON ue.userid = u.id 
                        JOIN {enrol} e ON e.id = ue.enrolid
                        JOIN {role_assignments} ra ON ra.userid = ue.userid
                        JOIN {context} cxt ON cxt.id = ra.contextid
                        JOIN {role} r ON r.id = ra.roleid    
                        WHERE u.deleted = 0 
                            AND u.suspended = 0 AND r.shortname = 'employee' 
                            AND e.courseid = c.id ) as noofenrollments,
                    (SELECT COUNT(DISTINCT(ue.id))
                        FROM {user_enrolments} ue
                        JOIN {user} u ON ue.userid = u.id 
                        JOIN {enrol} e ON e.id = ue.enrolid
                        JOIN {role_assignments} ra ON ra.userid = ue.userid
                        JOIN {context} cxt ON cxt.id = ra.contextid
                        JOIN {role} r ON r.id = ra.roleid
                        JOIN {course_completions} cc ON e.courseid = cc.course 
                            AND cc.userid = u.id AND cc.timecompleted IS NOT NULL
                        WHERE u.deleted = 0 
                            AND u.suspended = 0 AND r.shortname = 'employee' 
                            AND e.courseid = c.id ) as noofcompletions " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {course} c ";
    }

    function joins() {
        $this->sql .=" JOIN {course_categories} cat ON cat.id = c.category ";

        parent::joins();
    }

    function where() {
        global $USER, $DB;
        $this->sql .= " WHERE c.id <> :siteid ";
        // $this->sql .= " WHERE c.id <> :siteid AND 
        //         CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";

        $this->params['siteid'] = SITEID;

        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        $costcenterpathconcatsql = (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        // getscheduled report
        // if (!is_siteadmin()) {
        //     $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
        //     if (!empty($scheduledreport)) {
        //     $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
        //     $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
        //     $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
        //     } else {
        //         $ohs = $dh=1;
        //     }
        // }
        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
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
    if(isset($this->params['filter_organization']) && $this->params['filter_organization'] > 0) {
        $organizations = explode(',', $this->params['filter_organization']);
        $orgsql = [];
        foreach($organizations AS $organisation){
            $orgsql[] = " concat('/',c.open_path,'/') LIKE :organisationparam_{$organisation}";
            $this->params["organisationparam_{$organisation}"] = '%/'.$organisation.'/%';
        }
        if(!empty($orgsql)){
            $this->sql .= " AND ( ".implode(' OR ', $orgsql)." ) ";
        }
    }

    if(isset($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
        $departments = explode(',', $this->params['filter_departments']);
        $deptsql = [];
        foreach($departments AS $department){
            $deptsql[] = " concat('/',c.open_path,'/') LIKE :departmentparam_{$department}";
            $this->params["departmentparam_{$department}"] = '%/'.$department.'/%';
        }
        if(!empty($deptsql)){
            $this->sql .= " AND ( ".implode(' OR ', $deptsql)." ) ";
        }
    }

    if(isset($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) {
        $subdepartments = explode(',', $this->params['filter_subdepartments']);
        $subdeptsql = [];
        foreach($subdepartments AS $subdepartment){
            $subdeptsql[] = " concat('/',c.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
            $this->params["subdepartmentparam_{$subdepartment}"] = '%/'.$subdepartment.'/%';
        }
        if(!empty($subdeptsql)){
            $this->sql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
        }
    }

    // if (!empty($params['department4level'])) {
    //     $depart4level = explode(',', $params['department4level']);
    //     $department4levelsql = [];
    //     foreach($depart4level AS $department4level){
    //         $department4levelsql[] = " concat('/',u.open_path,'/') LIKE :department4levelparam_{$department4level}";
    //         $params["department4levelparam_{$department4level}"] = '%/'.$department4level.'/%';
    //     }
    //     if(!empty($department4levelsql)){
    //         $sql .= " AND ( ".implode(' OR ', $department4levelsql)." ) ";
    //     }
    // }
    // if (!empty($params['department5level'])) {
    //     $depart5level = explode(',', $params['department5level']);
    //     $department5levelsql = [];
    //     foreach($depart5level AS $department5level){
    //         $department5levelsql[] = " concat('/',u.open_path,'/') LIKE :department5levelparam_{$department5level}";
    //         $params["department5levelparam_{$department5level}"] = '%/'.$department5level.'/%';
    //     }
    //     if(!empty($department5levelsql)){
    //         $sql .= " AND ( ".implode(' OR ', $department5levelsql)." ) ";
    //     }
    // }
        // if(isset($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
        //     $this->sql .= " AND c.open_departmentid = :departmentid ";
        //     $this->params['departmentid'] = $this->params['filter_departments'];
        // }
        // if(isset($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) {
        //     $this->sql .= " AND c.open_subdepartment = :subdepartmentid ";
        //     $this->params['subdepartmentid'] = $this->params['filter_subdepartments'];
        // }

        if(isset($this->params['filter_course']) && $this->params['filter_course'] > 0) {
            $this->sql .= " AND c.id = :courseid ";
            $this->params['courseid'] = $this->params['filter_course'];
        }
        // echo $this->sql;
        // print_r($this->params);exit;
        // if ($this->ls_startdate > 0 && $this->ls_enddate) {
        //     $this->sql .= " AND u.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        // }
    }

    public function get_rows($courses) {
        return $courses;
    }
}
