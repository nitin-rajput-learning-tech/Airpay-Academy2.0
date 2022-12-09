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

class report_departmentwiseclassrooms extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('departmentwiseclassrooms' => array('organization','completed','scheduled'));
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->filters = array('organization','departments', 'subdepartments', 'startendtime');
        $this->sqlorder['column'] = 'organization';
        $this->sqlorder['dir'] = 'desc';
        $this->orderable = array();
        $this->defaultcolumn = 'c.id';
    }
    
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT( distinct c.id)";
    }
    function select() { 
        $timesql = $this->timesql();
        $this->sql  = " select concat(c.fullname, '/', c.shortname) as organization,
            (SELECT count(id)
                FROM {local_classroom} lc
                WHERE  lc.status = 4 AND lc.costcenter = c.id $timesql
                ) as completed,
                (SELECT count(id) 
                FROM {local_classroom} lc 
                WHERE lc.status != 3  AND lc.costcenter = c.id $timesql
                ) as scheduled
        ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_costcenter} c ";
    }
    function joins() {
        parent::joins();
    }
    function where($count){
        global $USER, $DB;
        $this->sql .= " WHERE 1=1 AND c.visible = 1 ";
        // getscheduled report
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
            $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
            } else {
                $ohs = $dhs = 1;
            }
        }
        parent::where();
    }
   
    function search(){
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(c.fullname,' ',c.shortname)");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 
    function filters(){
        if (!empty($this->params['filter_organization'])) {
            $orgids = $this->params['filter_organization'];
            $this->sql .= " AND c.id = :orgid ";
            $this->params['orgid'] = $orgids;
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

    public function timesql() {
        $sql = '';
        if(!empty($this->params['filter_starttime']) AND $this->params['filter_starttime']['enabled'] == 1){
            $filter_starttime = $this->params['filter_starttime'];
            $start_year=$filter_starttime['year'];
            $start_month=$filter_starttime['month'];
            $start_day=$filter_starttime['day'];
            $start_hour=$filter_starttime['hour'];
            $start_minute=$filter_starttime['minute'];
            $start_second=0;
            $filter_starttime_con=mktime($start_hour, $start_minute, 0, $start_month, $start_day, $start_year);
            $sql.=" AND lc.startdate >= '$filter_starttime_con' ";
        }
        if(!empty($this->params['filter_endtime']) AND $this->params['filter_endtime']['enabled'] == 1){
            $filter_endtime = $this->params['filter_endtime'];
            $end_year=$filter_endtime['year'];
            $end_month=$filter_endtime['month'];
            $end_day=$filter_endtime['day'];
            $end_hour=$filter_endtime['hour'];
            $end_minute=$filter_endtime['minute'];
            $end_second=0;
            $filter_endtime_con=mktime($end_hour, $end_minute, 0, $end_month, $end_day, $end_year);
            $sql.=" AND lc.enddate <= '$filter_endtime_con' ";
        }
        return $sql;
    }
}
