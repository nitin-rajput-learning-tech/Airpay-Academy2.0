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

class report_learnercertificationssummary extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->columns = array('learnercertificationssummarycolumns' => array('learner','certification','inprogress','completed', 'upcomingexpiry', 'upcomingendoflife','upcomingdeadline','overduedeadline'));
        $this->components = array('columns', 'filters', 'permissions', 'orderable','plot');
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->filters = array('user');
        $this->orderable = array('learner','certification','inprogress','completed','upcomingdeadline','overduedeadline');
        $this->defaultcolumn = 'lc.id';
    }
    function init() {
        parent::init();
    }
    function count() {
        $this->sql = " SELECT COUNT(DISTINCT lc.id) ";
    }
    function select() {
        $this->sql = " SELECT lc.id AS certificationid, u.id, CONCAT(u.firstname, ' ', u.lastname) AS learner, lc.id AS certificationid, lc.name AS certification ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {user} as u 
                        JOIN {local_certification_users} as lcu ON u.id = lcu.userid 
                        JOIN {local_certification} as lc on lc.id = lcu.certificationid ";
    }
    function joins() { 
        parent::joins();
    }
    function where() { 
        global $USER, $DB;
        $this->sql .= " WHERE 1 = 1 ";
        parent::where();
    }
    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ', u.lastname)");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters() {    
        global $DB, $CFG,$OUTPUT, $USER;
        $expirydate = strtotime("+90 days");
        $systemcontext = context_system::instance();
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
        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
                if ($this->params['filter_organization']>0) {
                    $this->sql .= " AND lc.costcenter IN (".$this->params['filter_organization'].", 0) AND u.open_costcenterid =". $this->params['filter_organization'];
                    $subqueryfilter .= " AND lc.costcenter = " .$this->params['filter_organization'];
                }
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND lc.department IN (".$this->params['filter_departments'].",-1) AND u.open_departmentid=".$this->params['filter_departments'];
                    $subqueryfilter .= " AND lc.department = ".$this->params['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {
                $this->sql .= " AND lc.costcenter IN (" .$USER->open_costcenterid .", 0) AND u.open_costcenterid =". $USER->open_costcenterid;
                $subqueryfilter .= " AND lc.costcenter = " .$USER->open_costcenterid;            
                if ($this->params['filter_departments'] > 0) {
                    $this->sql .= " AND lc.department IN (".$this->params['filter_departments'].",-1) AND u.open_departmentid=".$this->params['filter_departments'];
                    $subqueryfilter .= " AND lc.department = ".$this->params['filter_departments'];
                }
            }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) {
                $this->sql .= " AND lc.costcenter IN (" .$USER->open_costcenterid . ", 0) AND lc.department IN (".$USER->open_departmentid.",-1) AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid;
            } else {
                $this->sql .= " AND lc.costcenter IN (" .$USER->open_costcenterid . ", 0) AND lc.department IN (".$USER->open_departmentid.",-1) AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid ." AND lc.department IN (" .$USER->open_subdepartment .", 0 ) AND u.open_subdepartment =".$USER->open_subdepartment;               
            }

            if ($this->params['filter_subdepartments'] > 0) {
                $this->sql .= " AND lc.subdepartment IN (".$this->params['filter_subdepartments'].",-1) AND u.open_subdepartment=".$this->params['filter_subdepartments'];
                    $subqueryfilter .= " AND lc.subdepartment = ".$this->params['filter_subdepartments'];
            }
        }
        if (isset($this->params['filter_user'])) {
            $this->sql .= " AND u.id = ".$this->params['filter_user'];
        }
        if(!empty($this->params['filter_status'])) {
            if($this->params['filter_status'] == 'completed') {
                $this->sql .= " AND lcu.completion_status = 1 AND (lcu.expirydate =0 OR lcu.expirydate >= UNIX_TIMESTAMP())";
            } else if ($this->params['filter_status'] == 'inprogress') {
                $this->sql .= " AND lcu.completion_status = 0 ";
            } else if($this->params['filter_status'] == 'overdue') {
                $this->sql .= " AND lcu.certdeadline < UNIX_TIMESTAMP() AND lcu.completion_status = 0 AND lcu.certdeadline != 0 ";
            } else if($this->params['filter_status'] == 'upcoming') {
                $this->sql .= " AND lcu.certdeadline > UNIX_TIMESTAMP() AND lcu.completion_status = 0 AND lcu.certdeadline != 0 ";
            } else if($this->params['filter_status'] == 'upcomingexpiry') {
                $this->sql .= " AND lcu.expirydate BETWEEN UNIX_TIMESTAMP() AND {$expirydate} AND lcu.completion_status =1 ";
            } else if($this->params['filter_status'] == 'upcomingendoflife') {
                $this->sql .= "  AND lc.eol BETWEEN UNIX_TIMESTAMP() AND {$expirydate} ";
            }
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND lc.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }                     
    }    
    /**
     * [get_rows description]
     * @param  array  $users [description]
     * @return [type]        [description]
     */
    function get_rows($courses = array()) {
        return $courses;
    }
}
