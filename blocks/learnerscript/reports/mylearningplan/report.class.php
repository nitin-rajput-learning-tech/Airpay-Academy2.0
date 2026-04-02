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

class report_mylearningplan extends reportbase implements report {

	/**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);

        $this->parent = true;
        $this->components = array('columns','permissions','filters');
        $this->columns = ['learningpathfield'=>['learningpathfield'],'mylearningplan' => ['learningplanname','totalcourses','mandatorycourses','optionalcourses','completedcourses','completionstatus','completiondate']];
        $this->filters = ['mylearningpathsscolumn','completionstatus'];
        $this->orderable = array('learningplanname');
        $this->defaultcolumn = 'lu.id';
    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT count(lu.id) ";
    }
    function select() {
        $this->sql = "SELECT lu.id, ll.id as learningpathid,lu.status AS completionstatus,ll.name as learningplanname, lu.completiondate as completiondate ";

        parent::select();
    }
    function from() {
        $this->sql .= "FROM {local_learningplan} as ll ";
    }
    function joins() {
         $this->sql .= "  JOIN {local_learningplan_user} as lu ON ll.id = lu.planid 
                JOIN {user} as u ON u.id = lu.userid ";
          parent::joins();
    }
    function where(){
        global $USER, $DB;
         $this->sql .=  " WHERE u.id = $USER->id AND ll.visible = 1 ";
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
            $fields = array('ll.name');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 
    function filters(){  
        if (!empty($this->params['filter_mylearningpathsscolumn'])) {
            $this->sql .= " AND ll.id = :name ";
            $this->params['name'] = $this->params['filter_mylearningpathsscolumn']; 
        }
        if ((isset($this->params['filter_completionstatus'])) && ($this->params['filter_completionstatus'] != -1)) {
            $lpid = $this->params['filter_completionstatus'];
            if($lpid == 1){
                $this->sql .= " AND lu.status = $lpid";
            }else{
                $this->sql .= " AND lu.status IS NULL";
            }
        }
     
    }
        function get_rows($mylearningplans){
            return $mylearningplans;
        }
}
