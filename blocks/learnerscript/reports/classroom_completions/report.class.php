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

class report_classroom_completions extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns', 'filters', 'permissions');
        $this->columns = array('classroomfield'=>['classroomfield'],
                                'userfield'=>['userfield'],
                                'classroomcompletionscolumns'=>['attendedsessions','totalsessions','usercompletionstatus','usercompletiondate']);
        $this->filters = array('organization','departments','user','classrooms','completionstatus');
        $this->defaultcolumn = 'lcu.id';
    }


    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(lcu.id) ";
    }

    function select() {
        $this->sql = " SELECT lcu.id , lc.id AS classroomid, lcu.userid,
                        (SELECT COUNT(lcs.id) 
                        FROM {local_classroom_sessions} lcs 
                        WHERE lcs.classroomid = lc.id) AS totalsessions,
                        (SELECT COUNT(lca.id) 
                        FROM {local_classroom_attendance} lca 
                        WHERE lca.classroomid = lc.id AND
                         lca.userid = u.id AND lca.status = 1) AS attendedsessions,
                        CASE
                            WHEN lcu.completion_status > 0 THEN 'Completed'
                            ELSE 'Not Completed'
                        END AS usercompletionstatus,
                        lcu.completiondate AS usercompletiondate  " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {local_classroom} lc ";
    }


    function joins() {
        $this->sql .=" JOIN {local_classroom_users} lcu ON lc.id = lcu.classroomid
                        JOIN {user} u ON u.id = lcu.userid ";

        parent::joins();
    }

    function where() {
        global $USER, $DB;

        $systemcontext = context_system::instance();
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
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $this->sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $this->sql .= " AND lc.costcenter = :costcenterid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $this->sql .= " AND lc.costcenter = :costcenterid AND lc.department = :departmentid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
            $this->params['departmentid'] = $USER->open_departmentid;
        }else{
            $this->sql .= " AND lcu.userid = :loggedinuserid ";
            $this->params['loggedinuserid'] = $USER->id;
        }

        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('lc.name',"CONCAT(u.firstname,' ',u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        if (!empty($this->params['filter_organization']) && $this->params['filter_organization'] > 0) {
            $this->sql .= " AND lc.costcenter = :orgid ";
            $this->params['orgid'] = $this->params['filter_organization'];
        }

        
        if (isset($this->params['filter_departments']) && $this->params['filter_departments'] >= 0 && $this->params['filter_departments'] != '') {

            $this->sql .= " AND lc.department = :deptid ";
            $this->params['deptid'] = $this->params['filter_departments'] ? $this->params['filter_departments'] : -1;

        }

        if (!empty($this->params['filter_classrooms']) && $this->params['filter_classrooms'] > 0) {
            $this->sql .= " AND lc.id = :classroomid ";
            $this->params['classroomid'] = $this->params['filter_classrooms'];
        }

        if (!empty($this->params['filter_user']) && $this->params['filter_user'] > 0) {
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $this->params['filter_user'];
        }

        if ($this->params['filter_completionstatus'] > -1){
            $this->sql .= " AND lcu.completion_status = :usercomplstatus ";
            $this->params['usercomplstatus'] = $this->params['filter_completionstatus'];
        }

    }

    /**
     * [get_rows description]
     * @param  array  $classroomcompletion [description]
     * @return [type] [description]
     **/
    public function get_rows($classroomcompletion = array()) {
        return $classroomcompletion;
    }
}
