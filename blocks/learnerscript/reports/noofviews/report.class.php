<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/** LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @subpackage learnerscript
 * @author: jahnavi<jahnavi@eabyas.in>
 * @date: 2018
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_noofviews extends reportbase implements report {
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report);
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $this->columns = array('noofviews' => array('learner', 'views'));
        $this->courselevel = false; 
        if ($this->loggedinuserrole != 'dh') {
            $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments'], ['name' => 'course'], ['name' => 'activities']); 
        }else if ($this->loggedinuserrole == 'dh') {
            $this->basicparams = array(['name' => 'subdepartments']); 
        } else {
            $this->basicparams = array(['name' => 'course'], ['name' => 'activities']);
        }
        $this->parent = false;
        $this->orderable = array( );
        $this->defaultcolumn = 'lsl.userid';
        $this->excludedroles = array("'student'");
    }
    public function init() {
      /* if(!isset($this->params['filter_courses'])){
            $this->initial_basicparams('courses');
            $this->params['filter_courses'] = array_shift(array_keys($this->filterdata));
        }*/
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
    }
    public function count() {
        $this->sql   = "SELECT COUNT(DISTINCT lsl.userid) ";
    }

    public function select() {
        $this->sql = "SELECT lsl.userid AS userid, COUNT(lsl.id) AS views";
        if (in_array('learner', $this->selectedcolumns)) {
            $this->sql .= ", CONCAT(u.firstname, ' ', u.lastname) AS learner";
        }
    }

    public function from() {
        $this->sql .= " FROM {logstore_standard_log} lsl";
    }

    public function joins() {
        $this->sql .= " JOIN {user} u ON u.id = lsl.userid
                        JOIN {course} c ON c.id = lsl.courseid";
    }

    public function where() {
        global $DB, $USER;
        $this->courseid = isset($this->params['filter_course']) ? $this->params['filter_course'] : SITEID;
        $learnersql  = (new querylib)->get_learners('', $this->courseid);
        $this->sql .=" WHERE lsl.crud = 'r' AND lsl.userid in ($learnersql) AND u.confirmed = 1 AND u.deleted = 0  ";
        if ($this->ls_startdate > 0 && $this->ls_enddate) {
            $this->sql .= " AND lsl.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
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
        // if ($this->loggedinuserrole != 'dh') {
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $this->sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
            $this->sql .= " AND u.open_costcenterid = :costcenterid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
            $this->sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
            $this->params['departmentid'] = $USER->open_departmentid;
        } else { 
            $this->sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid AND u.open_subdepartment = :subdepartment";
            $this->params['costcenterid'] = $USER->open_costcenterid;
            $this->params['departmentid'] = $USER->open_departmentid;
            $this->params['subdepartment'] = $USER->open_subdepartment;
        }
        // }
        parent::where();
    }

    public function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ' , u.lastname)");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    public function filters() {
        if (!empty($this->params['filter_activities'])) {
            $this->sql .= " AND lsl.contextinstanceid IN (".$this->params['filter_activities'].") AND lsl.contextlevel = 70 ";
        }
        if (!empty($this->params['filter_course']) && $this->params['filter_course'] <> SITEID  && !$this->scheduling) { 
            $this->sql .= " AND lsl.courseid IN (".$this->params['filter_course'].") ";
        }
        if (!empty($this->params['filter_organization'])) {
            $costcenterids = $this->params['filter_organization'];
            $this->sql .= " AND u.open_costcenterid IN ($costcenterids) ";
        }
        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) { 
            $departmentids = $this->params['filter_departments'];
            $this->sql .= " AND u.open_departmentid IN ($departmentids) ";
        }
        if (!empty($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) { 
            $subdepartmentids = $this->params['filter_subdepartments'];
            $this->sql .= " AND u.open_subdepartment IN ($subdepartmentids) ";
        }
    }
    /**
     * @param  array $activites Activites
     * @return array $reportarray Activities information
     */
    public function get_rows($activites) {
        return $activites;
    }
}
