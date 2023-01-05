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
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_usersprogress extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('userfield' => ['userfield'], 'usersprogress' => array('userid','firstname','clsenrolled', 'clscompleted','crtsenrolled', 'crtscompleted','prgsenrolled', 'prgscompleted','csenrolled', 'cscompleted','lpsenrolled','lpscompleted','lesenrolled','lescompleted','losenrolled','loscompleted'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->filters = array('organization','departments', 'subdepartments');
        $this->sqlorder['column'] = 'firstname';
        $this->sqlorder['dir'] = 'asc';
        $this->orderable = array(' ');
    }
    
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT( distinct u.id) ";
    }
    function select() {
        $this->sql  = "SELECT distinct u.id as userid, u.firstname, u.lastname,
            (SELECT count(cu.id)
                        FROM {local_classroom} c 
                        JOIN {local_classroom_users} cu ON cu.classroomid = c.id
                        WHERE  c.visible = 1 AND c.status != 3 AND cu.userid = u.id) as clsenrolled,
            (SELECT count(cu.id)
                        FROM {local_classroom} c 
                        JOIN {local_classroom_users} cu ON cu.classroomid = c.id
                        WHERE  c.visible = 1 AND c.status = 4 AND cu.completion_status = 1 AND cu.userid = u.id ) as clscompleted,
            (SELECT count(cu.id)
                        FROM {local_certification} c 
                        JOIN {local_certification_users} cu ON cu.certificationid = c.id
                        WHERE  c.visible = 1 AND c.status != 3 AND cu.userid = u.id ) as crtsenrolled,
            (SELECT count(cu.id)
                        FROM {local_certification} c 
                        JOIN {local_certification_users} cu ON cu.certificationid = c.id
                        WHERE  c.visible = 1 AND c.status = 4 AND cu.completion_status = 1 AND cu.userid = u.id ) as crtscompleted,
            (SELECT count(pu.id)
                        FROM {local_program} p 
                        JOIN {local_program_users} pu ON pu.programid = p.id
                        WHERE  p.visible = 1 AND p.visible = 1 AND p.status != 3 AND pu.userid = u.id) as prgsenrolled,
            (SELECT count(pu.id)
                        FROM {local_program} p
                        JOIN {local_program_users} pu ON pu.programid = p.id
                        WHERE p.visible = 1 AND  p.status = 4 AND pu.completion_status = 1 AND pu.userid = u.id) as prgscompleted,

            (select count(ue.id) from {user_enrolments} ue 
                        JOIN {enrol} e ON ue.enrolid = e.id 
                        JOIN {course} c ON e.courseid = c.id AND concat(',',c.open_identifiedas, ',') LIKE concat('%,',3,',%') 
                        where c.visible = 1 AND ue.userid = u.id
                        ) as csenrolled,
            (select count(cc.id) from {course_completions} cc 
                        JOIN {course} c ON c.id = cc.course AND concat(',',c.open_identifiedas, ',') LIKE concat('%,',3,',%')
                        JOIN {enrol} e ON c.id = e.courseid 
                        JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = cc.userid
                        where c.visible = 1 AND cc.userid = u.id AND cc.timecompleted is not NULL
                        ) as cscompleted,
            (SELECT count(lpu.id)
                        FROM {local_learningplan} lp 
                        JOIN {local_learningplan_user} lpu ON lpu.planid = lp.id
                        WHERE  lp.visible = 1 AND lpu.userid = u.id) as lpsenrolled,
            (SELECT count(lpu.id)
                        FROM {local_learningplan} lp
                        JOIN {local_learningplan_user} lpu ON lpu.planid = lp.id
                        WHERE lp.visible = 1 AND lpu.status = 1 AND lpu.userid = u.id) as lpscompleted,
            (SELECT count(leu.id)
                        FROM {local_evaluations} le
                        JOIN {local_evaluation_users} leu ON leu.evaluationid = le.id
                        WHERE le.visible = 1 AND  leu.userid = u.id) as lesenrolled,
            (SELECT count(leu.id)
                        FROM {local_evaluations} le
                        JOIN {local_evaluation_users} leu ON leu.evaluationid = le.id
                        WHERE le.visible = 1 AND leu.status = 1 AND leu.userid = u.id) as lescompleted,

            (SELECT count(lou.id)
                        FROM {local_onlinetests} lo
                        JOIN {local_onlinetest_users} lou ON lou.onlinetestid = lo.id
                        WHERE lo.visible = 1 AND  lou.userid = u.id) as losenrolled,
            (SELECT count(lou.id)
                        FROM {local_onlinetests} lo
                        JOIN {local_onlinetest_users} lou ON lou.onlinetestid = lo.id
                        WHERE lo.visible = 1 AND lou.status = 1 AND lou.userid = u.id) as loscompleted
                 ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {user} u ";
    }
    function joins() {
        parent::joins();
    }
    function where($count){
        global $USER, $DB;
        $this->sql .= " WHERE 1=1 AND u.id > 2 ";
        $this->sql .= " AND u.suspended = 0 AND u.deleted = 0 ";
        // $systemcontext = context_system::instance();
        $categorycontext =  (new \local_users\lib\accesslib())::get_module_context();
        // getscheduled report
        // if (!is_siteadmin()) {
        //     $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
        //     if (!empty($scheduledreport)) {
        //     $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
        //     $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
        //     $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
        //     } else {
        //         $ohs = $dhs = 1;
        //     }
        // }
        // if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
        //     $this->sql .= " ";
        // }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
        //     $this->sql .= " AND u.open_costcenterid = :costcenterid ";
        //     $this->params['costcenterid'] = $USER->open_costcenterid; 
        // }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
        //     $this->sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid";
        //     $this->params['costcenterid'] = $USER->open_costcenterid; 
        //     $this->params['departmentid'] = $USER->open_departmentid; 
        // }else{
        //     $this->sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid AND u.open_subdepartment = :subdepartment";
        //     $this->params['costcenterid'] = $USER->open_costcenterid; 
        //     $this->params['departmentid'] = $USER->open_departmentid;
        //     $this->params['subdepartment'] = $USER->open_subdepartment;  
        // }

      $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path'); 

      if (is_siteadmin()) {
          $this->sql .= "";
      } else  {
          $this->sql .= $costcenterpathconcatsql;
      }
        parent::where();
    }
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname,' ',u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 
    function filters(){

        if (!empty($this->params['filter_organization'])) {
            $organization = $this->params['filter_organization'];
            $filter_organization[] = " concat('/',u.open_path,'/') LIKE :organizationparam_{$organization}";
            $this->params["organizationparam_{$organization}"] = '%/'.$organization.'/%';
            $this->sql .= " AND ( ".implode(' OR ', $filter_organization)." ) ";
        }

        if (!empty($this->params['filter_departments'])) {
            $department = $this->params['filter_departments'];
            $filter_department[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
            $this->params["departmentparam_{$department}"] = '%/'.$department.'/%';
            $this->sql .= " AND ( ".implode(' OR ', $filter_department)." ) ";
        }

        if (!empty($this->params['filter_subdepartments'])) {
            $subdepartments = $this->params['filter_subdepartments'];
            $filter_subdepartments[] = " concat('/',u.open_path,'/') LIKE :subdepartmentsparam_{$subdepartments}";
            $this->params["subdepartmentsparam_{$subdepartments}"] = '%/'.$subdepartments.'/%';
            $this->sql .= " AND ( ".implode(' OR ', $filter_subdepartments)." ) ";
        }
    }

    /**
     * [get_rows description]
     * @param  array  $trainermandays [description]
     * @return [type]        [description]
     **/
    public function get_rows($data = array()) {
        return $data;
    }
}
