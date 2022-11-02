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

class report_traininghoursvsusersvstrainers extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('traininghoursvsusersvstrainers' => array('monthyear', 'totaltrainings', 
            'month','year','traininghours', 'trainingdays', 'userscovered','trainerscovered'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->filters = array('organization','departments');
        $this->sqlorder['column'] = 'year';
        $this->sqlorder['dir'] = 'desc';
        $this->orderable = array('monthyear','month','year', 'totaltrainings','userscovered','trainerscovered');
    }
    
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT( distinct MONTH(FROM_UNIXTIME(lc.startdate)))";
    }
    function select() {
        $sdepartmentarray = $this->department_selection('s');
        $sdepartmentsql = $sdepartmentarray[0];
        $this->params['sorgid'] = $sdepartmentarray[1]['sorgid']; 
        $this->params['sdeptid'] = $sdepartmentarray[1]['sdeptid'];

        $cdepartmentarray = $this->department_selection('c');
        $cdepartmentsql = $cdepartmentarray[0];
        $this->params['corgid'] = $cdepartmentarray[1]['corgid']; 
        $this->params['cdeptid'] = $cdepartmentarray[1]['cdeptid'];

        $udepartmentarray = $this->department_selection('u');
        $udepartmentsql = $udepartmentarray[0];
        $this->params['uorgid'] = $udepartmentarray[1]['uorgid']; 
        $this->params['udeptid'] = $udepartmentarray[1]['udeptid'];

        $tdepartmentarray = $this->department_selection('t');
        $tdepartmentsql = $tdepartmentarray[0];
        $this->params['torgid'] = $tdepartmentarray[1]['torgid']; 
        $this->params['tdeptid'] = $tdepartmentarray[1]['tdeptid'];


        $this->sql  = "SELECT distinct concat(MONTH(FROM_UNIXTIME(lc.startdate)), '/', YEAR(FROM_UNIXTIME(lc.startdate))) as monthyear, FROM_UNIXTIME(lc.startdate, '%M') AS month,
                    YEAR(FROM_UNIXTIME(lc.startdate)) AS year,
        (SELECT count(id) 
            FROM {local_classroom} c 
            WHERE YEAR(FROM_UNIXTIME(c.startdate)) = YEAR(FROM_UNIXTIME(lc.startdate))
            AND MONTH(FROM_UNIXTIME(c.startdate)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND (c.status = 1 OR c.status = 4) $sdepartmentsql)  as totaltrainings,
        (SELECT SUM(round(cs.duration/60, 2)) 
            FROM {local_classroom_sessions} cs
            JOIN {local_classroom} c ON cs.classroomid = c.id
            WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME(lc.startdate))
            AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND (c.status = 1 OR c.status = 4) $cdepartmentsql) as traininghours,

        (SELECT count(distinct cat.userid) 
            FROM {local_classroom_attendance} cat
            JOIN {local_classroom_sessions} cs  ON cat.sessionid = cs.id AND cat.status = 1
            JOIN {local_classroom} c ON cs.classroomid = c.id
            WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME(lc.startdate))
            AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND (c.status = 1 OR c.status = 4) $udepartmentsql)  as userscovered,
        (SELECT count(distinct cs.trainerid)
        FROM  {local_classroom_sessions} cs 
        JOIN {local_classroom} c ON cs.classroomid = c.id
        WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME(lc.startdate))
        AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME(lc.startdate)) AND (c.status = 1 OR c.status = 4) AND cs.attendance_status = 1 $tdepartmentsql) as trainerscovered
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
            $this->sql .= " AND lc.costcenter = :costcenterid ";
            $this->params['costcenterid'] = $USER->open_costcenterid; 
        }else{
            $this->sql .= " AND lc.costcenter = :costcenterid AND lc.department = :departmentid";
            $this->params['costcenterid'] = $USER->open_costcenterid; 
            $this->params['departmentid'] = $USER->open_departmentid; 
        }
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
    //         $departmentarray = $this->department_selection();
    //         $departmentsql = $departmentarray[0];
    //         $params = $departmentarray[1];
    //         $csql = "SELECT count(id)  as t
    //                     FROM {local_classroom} c 
    //                     WHERE YEAR(FROM_UNIXTIME(c.startdate)) = $record->year
    //                     AND MONTH(FROM_UNIXTIME(c.startdate)) = $record->month AND (c.status = 1 OR c.status = 4) $departmentsql";
    //         $report->totaltrainings = $DB->count_records_sql($csql,$params);
    //         $trsql = "SELECT SUM(cs.duration) AS sessionsduration
    //                     FROM {local_classroom_sessions} cs
    //                     JOIN {local_classroom} c ON cs.classroomid = c.id
    //                     WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = $record->year
    //                     AND MONTH(FROM_UNIXTIME(cs.timestart)) = $record->month AND (c.status = 1 OR c.status = 4) $departmentsql";

    //         $trainginghrs = $DB->get_record_sql($trsql,$params);
    //         $report->traininghours = round($trainginghrs->sessionsduration/60, 2);
    //         $report->trainingdays = round($trainginghrs->sessionsduration/60/8, 2);
    //         $usrsql = "SELECT count(distinct cat.userid) as userscovered
    //                     FROM {local_classroom_attendance} cat
    //                     JOIN {local_classroom_sessions} cs  ON cat.sessionid = cs.id AND cat.status = 1
    //                     JOIN {local_classroom} c ON cs.classroomid = c.id
    //                     WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = $record->year
    //                     AND MONTH(FROM_UNIXTIME(cs.timestart)) = $record->month AND (c.status = 1 OR c.status = 4) $departmentsql";
    //         $trainedusers = $DB->get_record_sql($usrsql,$params);
    //         $report->userscovered = $trainedusers->userscovered;
    //         $trsql = "SELECT count(distinct cs.trainerid) as trainerscovered
    //                     FROM  {local_classroom_sessions} cs 
    //                     JOIN {local_classroom} c ON cs.classroomid = c.id
    //                     WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = $record->year
    //                     AND MONTH(FROM_UNIXTIME(cs.timestart)) = $record->month AND (c.status = 1 OR c.status = 4) AND cs.attendance_status = 1 $departmentsql";
    //         $trainedusers = $DB->get_record_sql($trsql,$params);
    //         $report->trainerscovered = $trainedusers->trainerscovered;
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
        global $USER, $DB;
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
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $sql .= " AND c.costcenter = :".$nos."orgid ";
            $params[''.$nos.'orgid'] = $USER->open_costcenterid; 
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $sql .= " AND c.costcenter = :".$nos."orgid AND c.department = :".$nos."deptid";
            $params[''.$nos.'orgid'] = $USER->open_costcenterid; 
            $params[''.$nos.'deptid'] = $USER->open_departmentid; 
        }
        return array($sql, $params);
    }
}
