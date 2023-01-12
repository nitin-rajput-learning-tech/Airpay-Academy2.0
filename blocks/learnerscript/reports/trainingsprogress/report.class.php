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

class report_trainingsprogress extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('trainingsprogress' => array('monthyear','completed', 'month','year','scheduled'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->filters = array('organization','departments', 'subdepartments');
        $this->sqlorder['column'] = 'year';
        $this->sqlorder['dir'] = 'desc';
        $this->orderable = array('monthyear','completed', 'month','year','scheduled');
        $this->defaultcolumn = 'YEAR(FROM_UNIXTIME(lc.startdate))';
    }
    
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT( distinct MONTH(FROM_UNIXTIME(lc.startdate)))";
    }
    function select() {
        // $sdepartmentarray = $this->department_selection('s');
        // $sdepartmentsql = $sdepartmentarray[0];
        // $this->params['sorgid'] = $sdepartmentarray[1]['sorgid']; 
        // $this->params['sdeptid'] = $sdepartmentarray[1]['sdeptid'];

        // $cdepartmentarray = $this->department_selection('c');
        // $cdepartmentsql = $cdepartmentarray[0];
        // $this->params['corgid'] = $cdepartmentarray[1]['corgid']; 
        // $this->params['cdeptid'] = $cdepartmentarray[1]['cdeptid'];
        $this->sql  = "SELECT distinct concat(MONTH(FROM_UNIXTIME(lc.startdate)), '/', YEAR(FROM_UNIXTIME(lc.startdate))) as monthyear, FROM_UNIXTIME(lc.startdate, '%M') AS month,
                    YEAR(FROM_UNIXTIME(lc.startdate)) AS year,
                    (SELECT count(id)
                        FROM {local_classroom} c 
                        WHERE YEAR(FROM_UNIXTIME(c.startdate)) = YEAR(FROM_UNIXTIME(lc.startdate))
                        AND MONTH(FROM_UNIXTIME(c.startdate)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND c.status = 4 
                        $cdepartmentsql) as completed,
                        (SELECT count(id) 
                        FROM {local_classroom} c 
                        WHERE YEAR(FROM_UNIXTIME(c.startdate)) = YEAR(FROM_UNIXTIME(lc.startdate))
                        AND MONTH(FROM_UNIXTIME(c.startdate)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND c.status != 3 
                        $sdepartmentsql) as scheduled
                 ";
        parent::select();                
    }
    function from() {
        $this->sql .= " FROM {local_classroom} lc ";
    }
    function joins() {
        parent::joins();
    }
    function where($count){
        global $USER, $DB;
        $this->sql .= " WHERE 1=1 ";
        $this->sql .= " AND (lc.status = 1 OR lc.status = 4) ";
        $systemcontext = context_system::instance();
        // getscheduled report
        // if (!is_siteadmin()) {
        //     $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
        //     if (!empty($scheduledreport)) {
        //     $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
        //     $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
        //     $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
        //     } else {
        //         $ohs =  $dhs =1;
        //     }
        // }
        // if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
        //     $this->sql .= " ";
        // }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
        //     $this->sql .= " AND lc.costcenter = :costcenterid ";
        //     $this->params['costcenterid'] = $USER->open_costcenterid; 
        // }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
        //     $this->sql .= " AND lc.costcenter = :costcenterid AND lc.department = :departmentid";
        //     $this->params['costcenterid'] = $USER->open_costcenterid; 
        //     $this->params['departmentid'] = $USER->open_departmentid; 
        // }else{
        //     $this->sql .= " AND lc.costcenter = :costcenterid AND lc.department = :departmentid AND lc.subdepartment = :subdepartment";
        //     $this->params['costcenterid'] = $USER->open_costcenterid; 
        //     $this->params['departmentid'] = $USER->open_departmentid; 
        //     $this->params['subdepartment'] = $USER->open_subdepartment; 
        // }
        // if ($count)
        // $this->sql .= " group by MONTH(FROM_UNIXTIME(lc.startdate)) ";

        parent::where();
    }
   
    function search(){
    } 
    function filters(){        
    }

    // function get_all_elements() {
    //     global $DB;
    //     $records = $DB->get_records_sql($this->sql, $this->params);
    //     foreach ($records as $record) {
    //         $report = new stdClass();
    //         $dateObj   = DateTime::createFromFormat('!m', $record->month);
    //         $monthName = $dateObj->format('F');
    //         $report->month = $monthName;
    //         $report->year = $record->year;
    //         $departmentarray = $this->department_selection('s');
    //         $departmentsql = $departmentarray[0];
    //         $params = $departmentarray[1];
    //         print_object($departmentarray[1]['sorgid']);
    //         $csql = "SELECT count(id)  as t
    //                     FROM {local_classroom} c 
    //                     WHERE YEAR(FROM_UNIXTIME(c.startdate)) = $record->year
    //                     AND MONTH(FROM_UNIXTIME(c.startdate)) = $record->month AND c.status = 4 $departmentsql";
    //         $report->completed = $DB->count_records_sql($csql,$params);
    //         $trsql = "SELECT count(id)  as t
    //                     FROM {local_classroom} c 
    //                     WHERE YEAR(FROM_UNIXTIME(c.startdate)) = $record->year
    //                     AND MONTH(FROM_UNIXTIME(c.startdate)) = $record->month AND (c.status != 3) $departmentsql";
    //         $report->scheduled = $DB->count_records_sql($trsql,$params);            
    //         $data[] = $report;
    //     }
    //     return $data;
    // }
    /**
     * [get_rows description]
     * @param  array  $trainermandays [description]
     * @return [type]        [description]
     **/
    public function get_rows($data = array()) {
        return $data;
    }

    public function department_selection($nos) {
        global $USER;
        $systemcontext = context_system::instance();
        $params =array();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $sql .= " ";

            if (!empty($this->params['filter_organization'])) {
                $orgids = $this->params['filter_organization'];
                $sql .= " AND c.costcenter = :".$nos."orgid ";
                $params[''.$nos.'orgid'] = $orgids;
            }
        
            if (!empty($this->params['filter_departments'])) {
                $deps = $this->params['filter_departments'];
                $sql .= " AND c.department = :".$nos."deptid ";
                $params[''.$nos.'deptid'] = $deps;
            }
            if (!empty($this->params['filter_subdepartments'])) {
                $subdeps = $this->params['filter_subdepartments'];
                $sql .= " AND c.subdepartment = :".$nos."subdeptid ";
                $params[''.$nos.'subdeptid'] = $subdeps;
            }
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $sql .= " AND c.costcenter = :".$nos."orgid ";
            $params[''.$nos.'orgid'] = $USER->open_costcenterid; 
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $sql .= " AND c.costcenter = :".$nos."orgid AND c.department = :".$nos."deptid";
            $params[''.$nos.'orgid'] = $USER->open_costcenterid; 
            $params[''.$nos.'deptid'] = $USER->open_departmentid; 
        }else{
            $sql .= " AND c.costcenter = :".$nos."orgid AND c.department = :".$nos."deptid AND c.subdepartment = :".$nos."subdeptid ";
            $params[''.$nos.'orgid'] = $USER->open_costcenterid; 
            $params[''.$nos.'deptid'] = $USER->open_departmentid;
            $params[''.$nos.'subdeptid'] = $USER->open_subdepartment;
        }
        return array($sql, $params);
    }
}
