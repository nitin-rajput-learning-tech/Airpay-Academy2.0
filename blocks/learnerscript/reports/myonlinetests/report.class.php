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

class report_myonlinetests extends reportbase implements report {

	/**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns', 'permissions','filters');
        $this->columns =['onlinetestfield'=>['onlinetestfield'],'myonlinetests' => ['onlinetestname','achievedgrade','completionstatus','completiondate']];
        $this->filters = ['myonlinetestscolumns','completionstatus'];
        $this->orderable = array('onlinetestname');
        $this->defaultcolumn = 'ou.id';
        }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(ou.id) ";
    }
    function select() {
        $this->sql = "SELECT lo.id as onlinetestid,ou.userid,lo.quizid as testid, lo.name as              onlinetestname,
                      lo.timeopen as testopentime,lo.timeclose as testclosetime,
                      ou.status ";

        parent::select();
    }
    function from() {
        $this->sql .= "FROM {local_onlinetests} as lo ";
    }
    function joins() {
         $this->sql .= "JOIN {local_onlinetest_users} as ou ON ou.onlinetestid = lo.id ";
          parent::joins();
    }
    function where(){
        global $USER, $DB;
         $this->sql .=  " WHERE ou.userid = $USER->id AND lo.visible = 1 ";
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
            $fields = array('lo.name');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    } 
    function filters(){    
        if (!empty($this->params['filter_myonlinetestscolumns'])) {
            $this->sql .= " AND lo.id = :name ";
            $this->params['name'] = $this->params['filter_myonlinetestscolumns']; 
        }
        if ($this->params['filter_completionstatus'] > -1) {
        $this->sql .= " AND ou.status = :status ";
        $this->params['status'] = $this->params['filter_completionstatus'];
        }
    }    
    function get_rows($myonlinetests){
        return $myonlinetests;
    }
}
