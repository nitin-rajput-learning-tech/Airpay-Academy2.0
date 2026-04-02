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

class report_myprograms extends reportbase implements report {

	/**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns','permissions','filters');
        $this->columns = ['programfield'=>['programfield'],'myprograms' => [
        'levelscount','programname',
            'completedlevelscount','completion_status','completiondate']];
        $this->filters = ['myprogramscolumn','completionstatus'];
        $this->orderable = array('programname');
        $this->defaultcolumn = 'pu.id';

    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = " SELECT count(pu.id) ";
    }
    function select() {
        $this->sql = "SELECT pu.id,lp.id as programid,pu.completion_status as completion_status,pu.completiondate,lp.name as programname";

        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_program} as lp ";
    }
    function joins() {
         $this->sql .= " JOIN {local_program_users} as pu ON pu.programid = lp.id ";
          parent::joins();
    }
    function where(){
        global $USER, $DB;
         $this->sql .=  " WHERE pu.userid = $USER->id AND lp.visible = 1 ";
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
    //  echo $this->sql;
    // exit;
    }
   
    function search(){
        if(isset($this->search) && $this->search) {
            $fields = array("lp.name");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters(){    
        if (!empty($this->params['filter_myprogramscolumn'])) {
            $this->sql .= " AND lp.id = :programid ";
            $this->params['programid'] = $this->params['filter_myprogramscolumn'];
        }
    
        if ((isset($this->params['filter_completionstatus'])) && ($this->params['filter_completionstatus'] != -1)) {
           $this->sql .= " AND pu.completion_status = :status ";
           $this->params['status']= $this->params['filter_completionstatus'];

        }
    }    
    function get_rows($myprograms){
        return $myprograms;
    }
 }
