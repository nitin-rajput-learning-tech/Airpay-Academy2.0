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

class report_mycertification extends reportbase implements report {

	/**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
       
        $this->columns = array('mycertification' => array('certificatename','startdate','enddate','certificatestatus','totalsessions','attendedsessions','usercompletionstatus','usercompletiondate'));
        $this->components = array('columns', 'filters', 'permissions');
        $this->filters = ['mycertificates','completionstatus'];
        $this->orderable = array('certificatename');
        $this->defaultcolumn = 'cu.id';
    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT count(cu.id)";
    }
    function select() {
        $this->sql = "SELECT cu.id, lc.id as certificationid, lc.name as certificatename,
                                lc.totalsessions,cu.attended_sessions as attendedsessions,
                                lc.startdate,lc.enddate,lc.status as certificatestatus,
                                cu.completion_status as usercompletionstatus,
                                cu.completiondate as usercompletiondate";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_certification} as lc ";
    }
    function joins() {
        global $USER,$DB;
         $this->sql .= " JOIN {local_certification_users} as cu ON cu.certificationid = lc.id ";
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
          parent::joins();
    }
    function where(){
        global $USER, $DB;
         $this->sql .=  " WHERE cu.userid = $USER->id AND lc.status IN (1,4) 
                          AND lc.visible = 1 ";
        //echo $this->sql;
         parent::where();
    }
	function search(){
        if (isset($this->search) && $this->search) {
                $fields = array("lc.name");
                $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
                $fields .= " LIKE '%" . $this->search . "%' ";
                $this->sql .= " AND ($fields) ";
        }
    }  
    function filters() {       
        if (isset($this->params['filter_organization']) && !empty($this->params['filter_organization'])) {
            $this->sql .= " AND lc.costcenterid = :orgid ";;
            $this->params['orgid']= $this->params['filter_organization'];
        }
        if (!empty($this->params['filter_mycertificates'])) {
            $this->sql .= " AND lc.id = :name ";
            $this->params['name'] = $this->params['filter_mycertificates']; 
        }
        if ($this->params['filter_completionstatus'] > -1) {
            $this->sql .= " AND cu.completion_status = :status ";
            $this->params['status']= $this->params['filter_completionstatus'];
        }

    }        
    function get_rows($mycertifications){
            return $mycertifications;
        }
    }
