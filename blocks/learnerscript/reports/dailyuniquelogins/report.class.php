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

class report_dailyuniquelogins extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('dailyuniquelogins' => array(/*'employeename', 'email',  */'usercount',/*'day',*/ 'month', 'monthname','year'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->filters = array('organization','departments');
        $this->groupcolumn = 'YEAR(FROM_UNIXTIME(lsl.timecreated)), MONTH(FROM_UNIXTIME(lsl.timecreated))';
        $this->sqlorder['column'] = 'YEAR(FROM_UNIXTIME(lsl.timecreated)), MONTH(FROM_UNIXTIME(lsl.timecreated))';
        // $this->sqlorder['dir'] = 'desc';
        $this->orderable = array(' ');
    }
    
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT count(DISTINCT(concat(YEAR(FROM_UNIXTIME(lsl.timecreated)), MONTH(FROM_UNIXTIME(lsl.timecreated))))) ";
    }
    function select() {
        // $this->sql  = "SELECT COUNT(l.id) as usercount, day, month, MONTHNAME(FROM_UNIXTIME(l.count_date))  as monthname, year
                 // ";
        $this->sql  = "SELECT lsl.userid, COUNT(DISTINCT(lsl.userid)) as usercount, YEAR(FROM_UNIXTIME(lsl.timecreated)) AS year, MONTH(FROM_UNIXTIME(lsl.timecreated)) as month, MONTHNAME(FROM_UNIXTIME(lsl.timecreated)) AS monthname ";//, concat(u.firstname,' ', u.lastname) AS employeename, u.email

        parent::select();
    }
    function from() {
        $this->sql .= " FROM {logstore_standard_log} as lsl ";
    }
    function joins() {
        $this->sql .= " JOIN {user} u ON u.id = lsl.userid ";
        parent::joins();
    }
    function where($count){
        global $USER, $DB;
        // $this->sql .= " WHERE 1=1 group by day, month, year";
        $this->sql .= " WHERE lsl.action LIKE '%loggedin%' and lsl.userid > 2 ";
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
            $this->sql .= " AND u.open_costcenterid = :costcenterid ";
            $this->params['costcenterid'] = $USER->open_costcenterid; 
        }else{
            $this->sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid";
            $this->params['costcenterid'] = $USER->open_costcenterid; 
            $this->params['departmentid'] = $USER->open_departmentid; 
        }
        parent::where();
    }
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array('l.month','l.year');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 
    function filters(){  
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
