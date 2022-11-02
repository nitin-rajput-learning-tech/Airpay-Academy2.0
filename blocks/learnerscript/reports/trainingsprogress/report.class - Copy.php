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
        $this->columns = array('trainingsprogress' => array('lcid', 'completed', 'month','year','scheduled'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->filters = array('organization','departments');
        $this->sqlorder['column'] = 'month';
        $this->sqlorder['dir'] = 'desc';
        $this->orderable = array(' ');
    }
    
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT( distinct MONTH(FROM_UNIXTIME(lc.startdate)))";
    }
    function select() {
        $this->sql  = "SELECT 
                    distinct MONTH(FROM_UNIXTIME(lc.startdate)) AS month,
                    YEAR(FROM_UNIXTIME(lc.startdate)) AS year
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
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $this->sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $this->sql .= " AND lc.costcenter = :costcenterid ";
            $this->params['costcenterid'] = $USER->open_costcenterid; 
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
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

    function get_all_elements() {
        global $DB;
        $records = $DB->get_records_sql($this->sql, $this->params);
        foreach ($records as $record) {
            $report = new stdClass();
            $dateObj   = DateTime::createFromFormat('!m', $record->month);
            $monthName = $dateObj->format('F');
            $report->month = $monthName;
            $report->year = $record->year;
            $departmentarray = $this->department_selection();
            $departmentsql = $departmentarray[0];
            $params = $departmentarray[1];
            $csql = "SELECT count(id)  as t
                        FROM {local_classroom} c 
                        WHERE YEAR(FROM_UNIXTIME(c.startdate)) = $record->year
                        AND MONTH(FROM_UNIXTIME(c.startdate)) = $record->month AND c.status = 4 $departmentsql";
            $report->completed = $DB->count_records_sql($csql,$params);
            $trsql = "SELECT count(id)  as t
                        FROM {local_classroom} c 
                        WHERE YEAR(FROM_UNIXTIME(c.startdate)) = $record->year
                        AND MONTH(FROM_UNIXTIME(c.startdate)) = $record->month AND (c.status != 3) $departmentsql";
            $report->scheduled = $DB->count_records_sql($trsql,$params);            
            $data[] = $report;
        }
        return $data;
    }
    /**
     * [get_rows description]
     * @param  array  $trainermandays [description]
     * @return [type]        [description]
     **/
    public function get_rows($data = array()) {
        return $data;
    }

    public function department_selection() {
        global $USER, $DB;
        $systemcontext = context_system::instance();
        $params =array();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $sql .= " ";

            if (!empty($this->params['filter_organization'])) {
                $orgids = $this->params['filter_organization'];
                $sql .= " AND c.costcenter = :orgid ";
                $params['orgid'] = $orgids;
            }
        
            if (!empty($this->params['filter_departments'])) {
                $deps = $this->params['filter_departments'];
                $sql .= " AND c.department = :deptid ";
                $params['deptid'] = $deps;
            }
            
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $sql .= " AND c.costcenter = :costcenterid ";
            $params['costcenterid'] = $USER->open_costcenterid; 
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $sql .= " AND c.costcenter = :costcenterid AND c.department = :departmentid";
            $params['costcenterid'] = $USER->open_costcenterid; 
            $params['departmentid'] = $USER->open_departmentid; 
        }
        return array($sql, $params);
    }
}
