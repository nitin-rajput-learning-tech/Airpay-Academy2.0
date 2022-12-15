<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License AS published by
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
 * @author: <jahnavi@eabyas.in>
 * @date: 2020
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\ls as ls;
use block_learnerscript\report;

class report_activestudents extends reportbase implements report {
    /**
     * [__construct description]
     * @param [type] $report           [description]
     * @param [type] $reportproperties [description]
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->courselevel = true;
        $this->components = array('columns', 'filters', 'permissions', 'plot');
        $columns = ['learner', 'email', 'sessionjoinedat'];
        $this->columns = ['bigbluebuttonfields' => $columns];
        $this->orderable = array('learner', 'email', 'sessionjoinedat'); 
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments'], ['name' => 'sessions']);
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = array("'student'");
    }
    function init() {
        global $DB; 
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams AS $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
    }

    function count() {
        $this->sql = "SELECT COUNT(DISTINCT u.id) ";
    }

    function select() {
        $this->sql = "SELECT DISTINCT u.id, CONCAT(u.firstname, ' ', u.lastname) AS learner, u.email, MAX(bbl.timecreated) AS sessionjoinedat, bbl.bigbluebuttonbnid ";
        parent::select();
    }

    function from() {
        $this->sql .= " FROM {user} u ";
    }

    function joins() {
        $this->sql .= " JOIN {bigbluebuttonbn_logs} bbl ON bbl.userid = u.id  
                        JOIN {course_modules} cm ON cm.instance = bbl.bigbluebuttonbnid 
                        JOIN {modules} m ON m.id = cm.module AND m.name = 'bigbluebuttonbn'";
        parent::joins();
    }

    function where() { 
        global $DB, $USER;
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
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $this->sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
            $this->sql .= " AND u.open_costcenterid = :costcenterid ";
            $this->params['costcenterid'] = $USER->open_costcenterid;
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
            $this->sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid ";
                $this->params['costcenterid'] = $USER->open_costcenterid;
                $this->params['departmentid'] = $USER->open_departmentid;
        }else { 
                $this->sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid AND u.open_subdepartment = :subdepartment";
                $this->params['costcenterid'] = $USER->open_costcenterid;
                $this->params['departmentid'] = $USER->open_departmentid;
                $this->params['subdepartment'] = $USER->open_subdepartment;
        }
        parent::where();
    }

    function search() {
        
    }

    function filters() { 
        global $DB;
        if (isset($this->params['filter_session']) && $this->params['filter_session'] > 0) {
            $this->sql .= " JOIN {user_enrolments} ue ON ue.userid = bbl.userid 
                        JOIN {enrol} e ON e.id = ue.enrolid 
                        JOIN {role_assignments} ra ON ra.userid = ue.userid
                        JOIN {context} ct ON ct.id = ra.contextid
                        JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'employee' 
                        JOIN {course} c ON c.id = bbl.courseid"; 
            $this->sql .= " WHERE bbl.log = 'Join' AND u.confirmed = 1 AND u.deleted = 0 "; 
            if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
                if ($this->rolewisecourses != '') {
                    $this->sql .= " AND bbl.courseid IN ($this->rolewisecourses) ";
                }
            } 
            if (isset($this->search) && $this->search) {
                $fields = array("CONCAT(u.firstname, ' ', u.lastname)");
                $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
                $fields .= " LIKE '%" . $this->search . "%' ";
                $this->sql .= " AND ($fields) ";
            }
            $sessionid = $this->params['filter_session'];
            $this->sql .= " AND bbl.bigbluebuttonbnid IN ($sessionid) "; 
            $courseid = $DB->get_field_sql("SELECT course FROM {bigbluebuttonbn} WHERE id = $sessionid"); 
            $this->sql .= " AND ct.instanceid = $courseid ";

          
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
        if ($this->ls_startdate > 0 && $this->ls_enddate) {
            $this->sql .= " AND bbl.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
    }
    /**
     * [get_rows description]
     * @param  array  $users [description]
     * @return [type]        [description]
     */
    public function get_rows($users = array()) {
        return $users;
    }
    public function column_queries($columnname, $activityid, $courseid = null) {
        $where = " AND %placeholder% = $activityid";
        $filtercourseid = $this->params['filter_course'];

        switch ($columnname) {      
            default:
                return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
