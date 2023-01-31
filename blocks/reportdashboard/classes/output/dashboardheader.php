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

/**
 * LearnerScript Report Dashboard Header
 *
 * @package    block_reportdashboard
 * @copyright  2017 eAbyas Info Solutions
 * @license    http://www.gnu.org/copyleft/gpl.reportdashboard GNU GPL v3 or later
 */
namespace block_reportdashboard\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;
use context_system;
use block_learnerscript\local\ls as ls;
use block_reportdashboard\local\reportdashboard as reportdashboard;
use block_learnerscript\local\querylib as querylib;

class dashboardheader implements renderable, templatable {
    public $editingon;
    public function __construct($data) {
        $this->editingon = $data->editingon;
        $this->configuredinstances = $data->configuredinstances;
        isset($data->getdashboardname) ? $this->getdashboardname = $data->getdashboardname : null;
        $this->dashboardurl = $data->dashboardurl;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $PAGE, $USER, $CFG;

        $data = array();
        $systemcontext = context_system::instance(); 
        $userroleid = isset($USER->access['rsw']['/1']) ? $USER->access['rsw']['/1'] : 0; 
        $user_costcenterid = explode('/', $USER->open_path)[1];
        $userrole = $DB->get_field_sql("SELECT shortname FROM {role} WHERE id = $userroleid");
        if (!empty($_SESSION['role'])) {
            $data['currentrole'] = $_SESSION['role'];
            $data['dashboardrole'] = isset($userrole) ? $userrole : '';
        } else {
            $data['currentrole'] = '';
            $data['dashboardrole'] = '';
        }
        $sql = "SELECT r.id, 
                CASE
                    WHEN r.name != '' THEN r.name
                    ELSE r.shortname
                END AS name
                FROM {role} r
                JOIN {role_context_levels} rcl ON rcl.roleid = r.id 
                WHERE rcl.contextlevel IN (10,40)";

        $roles = $DB->get_records_sql_menu($sql);
        $emproleid = $DB->get_field('role','id',array('shortname'=>'user'));
        $roles[$emproleid] = 'Employee';
        $oh = has_capability('block/learnerscript:managereports', $systemcontext);
        if (is_siteadmin() || $oh) {
            $data['switchrole'] = true;
        }
        $unusedroles = array('guest', 'frontpage'); 
        foreach ($roles as $key => $value) {
            $roleshortname = $DB->get_field('role', 'shortname', array('id' => $key));
            if (in_array($roleshortname, $unusedroles)) {
                continue;
            }
            $active = '';
            if ($roleshortname == $_SESSION['role']) {
                $active = 'active';
            }
            $data['roles'][] = ['roleshortname' => $roleshortname, 'rolename' => $value,
                                'active' => $active];
        }
        $data['editingon'] = $this->editingon;

        $data['issiteadmin'] = (is_siteadmin() || has_capability('block/learnerscript:managereports',$systemcontext)) ? true : false;
        $data['dashboardurl'] = $this->dashboardurl;
        $data['configuredinstances'] = $this->configuredinstances;
        $dashboardlist = $this->get_dashboard_reportscount();
        foreach ($dashboardlist as $dlist) {

            $dlist['dashboardname'] = $dlist['name'];

            $distarray[] = $dlist; 
        }
        $data['sesskey'] = sesskey();
        if (count($dashboardlist)) {
            $data['get_dashboardname'] = $distarray;
        }

        $data['reporttilestatus'] = $PAGE->blocks->is_known_block_type('reporttiles', false);
        $data['reportdashboardstatus'] = $PAGE->blocks->is_known_block_type('reportdashboard', false);
        $data['reportwidgetstatus'] = ($data['reporttilestatus'] || $data['reportdashboardstatus']) ? true : false;

        //for department dropdown in Ohdashboard
        $data['departmentfilter'] = 0;
        $data['departmentslist'] = array();
        // if (!is_siteadmin() && has_capability('block/learnerscript:managereports',$systemcontext)) {  
        if (!is_siteadmin()) {
            // $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            // if (!empty($scheduledreport)) {
            // $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            // $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            // $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
            // } else {
                $ohs = $dhs = 1;
            // }
        }
        // if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        //     $costcentersql = " SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.parentid = 0
        //             AND lc.depth = 1 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $costcenters = $DB->get_records_sql($costcentersql);
        //     if($this->dashboardurl == 'Compliances'){
        //         if(isset($_SESSION['compliance_id_array']) && !empty($_SESSION['compliance_id_array'])){
        //             if(isset($_SESSION['costcenter']) && !empty($_SESSION['costcenter'])){
        //                 $deptcostcenterid = $_SESSION['costcenter'];
        //             }else{
        //                 $deptcostcenterid = key($costcenters);
        //             }
        //         }else{
        //             $deptcostcenterid = key($costcenters);
        //         }
        //     }else{
        //         $deptcostcenterid = key($costcenters);
        //     }
        //     if (empty($deptcostcenterid)) {
        //         $deptcostcenterid = 0;
        //     }
        //     $sql = " SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.parentid = $deptcostcenterid AND
        //             lc.depth = 2 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $departments = $DB->get_records_sql($sql);
        //     if($this->dashboardurl == 'Compliances'){
        //         if(isset($_SESSION['compliance_id_array']) && !empty($_SESSION['compliance_id_array'])){
        //             if(isset($_SESSION['departments']) && !empty($_SESSION['departments'])){
        //                 $subdeptdeptid = $_SESSION['departments'];
        //             }else{
        //                 $subdeptdeptid = key($departments);
        //             }
        //         }else{
        //              $subdeptdeptid = key($departments);
        //         }
        //     }else{
        //         $subdeptdeptid = key($departments);
        //     }
        //     $subsql = " SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.parentid = $subdeptdeptid AND
        //             lc.depth = 3 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $subdepartments = $DB->get_records_sql($subsql);
        //     $data['departmentfilter'] = 1;
        //     $data['costcenterfilter'] = 1;
        //     $data['subdepartmentfilter'] = 1;
        // } else if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $_SESSION['role'] == 'oh') {
        //     $costcentersql = " SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.id = $user_costcenterid
        //             AND lc.depth = 1 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $costcenters = $DB->get_records_sql($costcentersql);
        //     $deptcostcenterid = key($costcenters);
        //     $sql = "SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.parentid = $deptcostcenterid
        //             AND lc.depth = 2 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $departments = $DB->get_records_sql($sql);
        //     if($this->dashboardurl == 'Compliances'){
        //         if(isset($_SESSION['compliance_id_array']) && !empty($_SESSION['compliance_id_array'])){
        //             if($_SESSION['departments'] > 0){
        //                 $subdeptdeptid = $_SESSION['departments'];
        //             }else{
        //                 $subdeptdeptid = '';
        //             }
        //         }else{
        //             $subdeptdeptid = '';
        //         }
        //     }else{
        //         $subdeptdeptid = key($departments);
        //     }
        //     if (empty($subdeptdeptid)) {
        //         $subdeptdeptid = 0;
        //     }
        //     //$subdeptdeptid = key($departments);
        //     $subsql = " SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.parentid = $subdeptdeptid AND
        //             lc.depth = 3 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $subdepartments = $DB->get_records_sql($subsql);
        //     $data['departmentfilter'] = 1;
        //     $data['subdepartmentfilter'] = 1;
        // } else if ((!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $_SESSION['role'] == 'dh')) {
        //     $costcentersql = " SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.id = $user_costcenterid
        //             AND lc.depth = 1 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $costcenters = $DB->get_records_sql($costcentersql);
        //     $deptcostcenterid = key($costcenters);
        //     $sql = "SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.parentid = $deptcostcenterid
        //             AND lc.depth = 2 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $departments = $DB->get_records_sql($sql);
        //     $subdeptdeptid = key($departments);
        //     $subsql = " SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.parentid = $subdeptdeptid AND
        //             lc.depth = 3 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $subdepartments = $DB->get_records_sql($subsql);
        //     $data['subdepartmentfilter'] = 1;
        // } else {
        //     $costcentersql = " SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.id = $user_costcenterid
        //             AND lc.depth = 1 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $costcenters = $DB->get_records_sql($costcentersql);
        //     $deptcostcenterid = key($costcenters);
        //     $sql = "SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.parentid = $deptcostcenterid
        //             AND lc.depth = 2 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $departments = $DB->get_records_sql($sql);
        //     $subdeptdeptid = key($departments);
        //     if(!empty($subdeptdeptid)){
        //     $subsql = " SELECT lc.id, lc.fullname
        //             FROM {local_costcenter} lc
        //             WHERE lc.parentid = $subdeptdeptid AND
        //             lc.depth = 3 AND lc.visible = 1
        //             ORDER BY lc.id ASC ";
        //     $subdepartments = $DB->get_records_sql($subsql);
        // }else{
        //      $subdepartments =array();
        // }
        $filtersarray = ['organization', 'departments', 'subdepartments', 'level4department', 'level5department'];
        $depth = $USER->useraccess['currentroleinfo']['depth'];
        if(count($USER->useraccess['currentroleinfo']['contextinfo']) > 1){
            $depth--;
        }
        $admin = is_siteadmin();
        $firstreport = $DB->get_record('block_learnerscript', []);
        foreach($filtersarray AS $filter){
            require_once($CFG->dirroot.'/blocks/learnerscript/components/filters/'.$filter.'/plugin.class.php');
            $class = 'plugin_'.$filter;
            $filterclass = new $class($firstreport);
            if($admin || $depth < $filterclass->enabledepth()){
                // var_dump();
                // var_dump($filter);
                ${$filter.'_options'} = $filterclass->filter_data();
                $data['enable_'.$filter] = true;
            }else{
                $data['enable_'.$filter] = false;

            }
        }
        // }
        $costcenterslist = array();

        if($organization_options){
            foreach ($organization_options as $id => $value) {
                $organisationlist[] = ['id' => $id, 'fullname' => $value, 'selected' => ''];
            }
        } 

        if(empty($departments_options)){
            $departmentslist[0] = ['id' => -1, 'fullname' => 'Select Department'];
        } else {
            foreach ($departments_options as $id => $value) {
                $departmentslist[] = ['id' => $id, 'fullname' => $value, 'selected' => ''];
            }
        }

        if($subdepartments_options){
            foreach ($subdepartments_options as $id => $value) {
                $subdepartmentslist[] = ['id' => $id, 'fullname' => $value, 'selected' => ''];
            }
        }
        if($level4department_options){
            foreach ($level4department_options as $id => $value) {
                $level4departmentlist[] = ['id' => $id, 'fullname' => $value, 'selected' => ''];
            }
        }
        if($level5department_options){
            foreach ($level5department_options as $id => $value) {
                $level5departmentlist[] = ['id' => $id, 'fullname' => $value, 'selected' => ''];
            }
        }
        $coursedepartmentid = key($departments);        

        $data['departmentslist'] = $departmentslist;
        $data['costcenterslist'] = $organisationlist;
        $data['subdepartmentslist'] = $subdepartmentslist;
        $data['level4departmentlist'] = $level4departmentlist;
        $data['level5departmentlist'] = $level5departmentlist;
        // $session_costcenter = array(); $session_department = array(); $session_subdepartment = array();
        // $session_costcenter = $_SESSION['costcenter'] ? $_SESSION['costcenter'] : '';
        // $session_department = $_SESSION['department'] ? $_SESSION['department'] : '';
        // $session_subdepartment = $_SESSION['subdepartment'] ? $_SESSION['subdepartment'] : '';
        // $data['session_costcenter'] = $session_costcenter;
        // $data['session_department'] = $session_department; 
        // $data['session_subdepartment'] = $session_subdepartment;  
        $data['courselist'] = array();
        $data['compliancelist'] = array();
        
        if($this->dashboardurl == 'Course'){ 
            // if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
            if ($_SESSION['role'] != 'dh') {
                if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) { 
                    $depcourseslist = $DB->get_records_sql("SELECT c.id FROM {course} c WHERE c.open_costcenterid IN ($deptcostcenterid)"); 
                    $learnercourseslist = $DB->get_records_sql("SELECT c.id FROM {course} c 
                        JOIN {enrol} e ON e.courseid = c.id AND e.status = 0 
                      JOIN {user_enrolments} ue on ue.enrolid = e.id AND ue.status = 0
                      JOIN {role_assignments}  ra ON ra.userid = ue.userid
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                      JOIN {context} ctx ON ctx.instanceid = c.id 
                      JOIN {user} AS u ON u.id = ue.userid 
                      JOIN {local_costcenter} lc ON lc.id = u.open_costcenterid 
                      AND ra.contextid = ctx.id AND ctx.contextlevel = 50 AND c.visible = 1 
                      WHERE u.open_costcenterid = $deptcostcenterid "); 
                    if (empty($learnercourseslist) && !empty($depcourseslist)) {
                        $totalcourses = $depcourseslist; 
                    } else if (!empty($learnercourseslist) && empty($depcourseslist)) {
                        $totalcourses = $learnercourseslist;
                    } else if (!empty($learnercourseslist) && !empty($depcourseslist)) {
                        $totalcourses = array_merge($learnercourseslist, $depcourseslist);
                    } else if (!empty($learnercourseslist) && !empty($depcourseslist)) {
                        $totalcourses = array();
                    }
                } else if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {  
                    $depcourseslist = $DB->get_records_sql("SELECT c.id FROM {course} c WHERE c.open_costcenterid IN ($deptcostcenterid) "); 
                    $learnercourseslist = $DB->get_records_sql("SELECT c.id FROM {course} c 
                        JOIN {enrol} e ON e.courseid = c.id AND e.status = 0 
                      JOIN {user_enrolments} ue on ue.enrolid = e.id AND ue.status = 0
                      JOIN {role_assignments}  ra ON ra.userid = ue.userid
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                      JOIN {context} ctx ON ctx.instanceid = c.id 
                      JOIN {user} AS u ON u.id = ue.userid 
                      JOIN {local_costcenter} lc ON lc.id = u.open_costcenterid 
                      AND ra.contextid = ctx.id AND ctx.contextlevel = 50 AND c.visible = 1 
                      WHERE u.open_costcenterid = $deptcostcenterid "); 
                    if (empty($learnercourseslist) && !empty($depcourseslist)) {
                        $totalcourses = $depcourseslist; 
                    } else if (!empty($learnercourseslist) && empty($depcourseslist)) {
                        $totalcourses = $learnercourseslist;
                    } else if (!empty($learnercourseslist) && !empty($depcourseslist)) {
                        $totalcourses = array_merge($learnercourseslist, $depcourseslist);
                    } else if (!empty($learnercourseslist) && !empty($depcourseslist)) {
                        $totalcourses = array();
                    }
                }else {
                    $depcourseslist = $DB->get_records_sql("SELECT c.id FROM {course} c WHERE c.open_costcenterid IN ($deptcostcenterid) AND c.open_departmentid = $USER->open_departmentid "); 
                    $learnercourseslist = $DB->get_records_sql("SELECT c.id FROM {course} c 
                        JOIN {enrol} e ON e.courseid = c.id AND e.status = 0 
                      JOIN {user_enrolments} ue on ue.enrolid = e.id AND ue.status = 0
                      JOIN {role_assignments}  ra ON ra.userid = ue.userid
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                      JOIN {context} ctx ON ctx.instanceid = c.id 
                      JOIN {user} AS u ON u.id = ue.userid 
                      JOIN {local_costcenter} lc ON lc.id = u.open_costcenterid 
                      AND ra.contextid = ctx.id AND ctx.contextlevel = 50 AND c.visible = 1 
                      WHERE u.open_costcenterid = $deptcostcenterid AND u.open_departmentid = $USER->open_departmentid"); 
                    if (empty($learnercourseslist) && !empty($depcourseslist)) {
                        $totalcourses = $depcourseslist; 
                    } else if (!empty($learnercourseslist) && empty($depcourseslist)) {
                        $totalcourses = $learnercourseslist;
                    } else if (!empty($learnercourseslist) && !empty($depcourseslist)) {
                        $totalcourses = array_merge($learnercourseslist, $depcourseslist);
                    } else if (!empty($learnercourseslist) && !empty($depcourseslist)) {
                        $totalcourses = array();
                    }
                } 
            } else { 
                $depcourseslist = $DB->get_records_sql("SELECT c.id FROM {course} c WHERE c.visible = 1 AND c.open_costcenterid = $user_costcenterid AND c.open_departmentid = $USER->open_departmentid"); 
                $learnercourseslist = $DB->get_records_sql("SELECT c.id FROM {course} c 
                    JOIN {enrol} e ON e.courseid = c.id AND e.status = 0 
                  JOIN {user_enrolments} ue on ue.enrolid = e.id AND ue.status = 0
                  JOIN {role_assignments}  ra ON ra.userid = ue.userid
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                  JOIN {context} ctx ON ctx.instanceid = c.id 
                  JOIN {user} AS u ON u.id = ue.userid 
                  JOIN {local_costcenter} lc ON lc.id = u.open_costcenterid 
                  AND ra.contextid = ctx.id AND ctx.contextlevel = 50 AND c.visible = 1 
                  WHERE u.open_costcenterid = $user_costcenterid AND u.open_departmentid = $USER->open_departmentid"); 
                if (empty($learnercourseslist) && !empty($depcourseslist)) {
                    $totalcourses = $depcourseslist; 
                } else if (!empty($learnercourseslist) && empty($depcourseslist)) {
                    $totalcourses = $learnercourseslist;
                } else if (!empty($learnercourseslist) && !empty($depcourseslist)) {
                    $totalcourses = array_merge($learnercourseslist, $depcourseslist);
                } else if (!empty($learnercourseslist) && !empty($depcourseslist)) {
                    $totalcourses = array();
                }
            } 
            foreach ($totalcourses as $totalcourse) {
                $list[] = $totalcourse->id;
            }
            $courseslist = !empty($totalcourses) ? implode(',', array_unique($list)) : 0;
            $dashboardcourse = $DB->get_records_sql("SELECT c.id, c.fullname FROM {course} c WHERE c.id IN ($courseslist) ORDER BY id DESC");

            if (!empty($dashboardcourse)) {
                $data['courselist'] = array_values($dashboardcourse);
                $data['coursedashboard'] = 1;
            } else {
                $data['courselist'] = array(array('id' => '0', 'fullname' => 'Select course'));
                $data['coursedashboard'] = 1;
            } 
        } else {
            $data['coursedashboard'] = 0;
        }
        if($this->dashboardurl == 'Compliances'){ 
           $sql = " SELECT lcc.id,lcc.name FROM {local_compliance} lcc JOIN {local_courses_orgwisevendors} lco ON lco.contentvendorid = lcc.contentvendor WHERE 1=1 ";
            if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) { 
                $costcenter = $DB->get_record_sql("SELECT id FROM {local_costcenter} WHERE 1=1 AND depth = 1");
                $sql .= " AND lcc.costcenter IN (".$costcenter->id.",0) AND lco.costcenterid = " . $costcenter->id;
            } else if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $_SESSION['role'] == 'oh') {  
                $sql .= " AND lcc.costcenter IN (". $user_costcenterid.", 0) AND lco.costcenterid = " . $user_costcenterid;
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $_SESSION['role'] == 'dh'){ 
                $sql .= " AND lcc.costcenter IN (". $user_costcenterid .", 0) AND lcc.department IN (". $USER->open_departmentid.", -1) AND lco.costcenterid = " . $user_costcenterid ; 
            }else {
                $sql .= " AND lcc.costcenter IN (". $user_costcenterid .", 0) AND lcc.department IN (". $USER->open_departmentid.", -1) AND lcc.subdepartment IN (". $USER->open_subdepartment .", -1) AND lco.costcenterid = " . $user_costcenterid;
                
            }
            
            if(!empty($_SESSION['compliance_id_array'])) {
                $query= "SELECT lcc.id,lcc.name FROM {local_compliance} lcc JOIN {local_courses_orgwisevendors} lco ON lco.contentvendorid = lcc.contentvendor WHERE 1=1 "; 
                // $query .= " AND lcc.id IN (". $_SESSION['compliance_id_array'] .")";
                if($_SESSION['costcenter'] > 0){
                    $query .= " AND lcc.costcenter IN (". $_SESSION['costcenter'] .",0) AND lco.costcenterid = " . $_SESSION['costcenter'];
                }
                if(!empty($_SESSION['departments']) && $_SESSION['departments'] > 0){
                    $query .= " AND lcc.department IN (". $_SESSION['departments'] .",-1)";
                }
                if(!empty($_SESSION['subdepartment']) && $_SESSION['subdepartment'] > 0){
                    $query .= " AND lcc.subdepartment IN (". $_SESSION['subdepartment'] .",-1)";
                }
                $list=$DB->get_records_sql_menu($query); 
            }else{
               $list=$DB->get_records_sql_menu($sql);
            } 
            $depcompliancelist = array('0' => 'All') + $list;
            if (!empty($depcompliancelist)) {
                foreach ($depcompliancelist AS $key => $value) {
                    $compliancelist['id']=$key;                 
                    $compliancelist['name']=$value;
                    if($_SESSION['compliance_id_array'] == 0) {
                        $compliancelist['class']='selectedcompliance';                        
                    } elseif($key > 0) {
                        $compliancelist['class']='optioncompliance';
                    } else {
                        $compliancelist['class']='complianceall';                        
                    }
                    if(in_array($key, explode(',',$_SESSION['compliance_id_array']))){
                        $compliancelist['selected']='selected';
                    } else {
                        $compliancelist['selected']='';
                    }
                    $compliaceslists[] = $compliancelist;
                }
                $data['compliancelist'] = $compliaceslists;
                $data['compliancedashboard'] = 1;
            } else {
                $data['compliancelist'] = array('0' => 'Select compliance');
                $data['compliancedashboard'] = 1;
            } 
        } else {
            $data['compliancedashboard'] = 0;
        }
        return $data;
    }

    public function get_dashboard_reportscount() {
        global $DB, $USER;
        $role = $_SESSION['role'];
        if (!empty($role) && !is_siteadmin()) {
            $sql = "SELECT DISTINCT(subpagepattern) FROM {block_instances} WHERE pagetypepattern = 'blocks-reportdashboard-dashboard-$role' AND subpagepattern IN ('Maindashboard', 'Learnerdashboard', 'Examdashboard', 'Certification', 'Compliances', 'Dashboard', 'Course') ORDER BY CASE subpagepattern
                  WHEN 'Maindashboard' THEN 1
                  WHEN 'Learnerdashboard' THEN 2
                  WHEN 'Examdashboard' THEN 3
                  WHEN 'Certification' THEN 4
                  WHEN 'Compliances' THEN 5 
                  WHEN 'Dashboard' THEN 6 
                  WHEN 'Course' THEN 7
                  ELSE 8 
               END";
        } else {
            $sql = "SELECT DISTINCT(subpagepattern) FROM {block_instances} WHERE pagetypepattern = 'blocks-reportdashboard-dashboard' AND subpagepattern IN ('Maindashboard', 'Learnerdashboard', 'Examdashboard', 'Certification', 'Compliances', 'Dashboard', 'Course') ORDER BY CASE subpagepattern
                  WHEN 'Maindashboard' THEN 1
                  WHEN 'Learnerdashboard' THEN 2
                  WHEN 'Examdashboard' THEN 3
                  WHEN 'Certification' THEN 4 
                  WHEN 'Compliances' THEN 5
                  WHEN 'Dashboard' THEN 6 
                  WHEN 'Course' THEN 7
                  ELSE 8
               END";
        }
        $getreports = $DB->get_records_sql($sql);
        $dashboardname = array();
        $i = 0;
        if (!empty($getreports)) {
            foreach ($getreports as $getreport) {
                $dashboardname[$getreport->subpagepattern] = $getreport->subpagepattern;
            }
        } else {
            $dashboardname['Dashboard'] = 'Dashboard';
        }
        foreach ($dashboardname as $key => $value) {
            // if ($value != 'Dashboard' && !(new reportdashboard)->is_dashboardempty($key)) {
            //     continue;
            // }
            // $concatsql = $DB->sql_like('subpagepattern', ':subpagepattern');
            $params = array();
            $params['subpagepattern'] = '%' . $key . '%';
            $getdashboardname[$i]['name'] = ucfirst($value);
            $getdashboardname[$i]['pagetypepattern'] = $value;
            $getdashboardname[$i]['random'] = $i; 
            if ($value == 'Examdashboard' || $value == 'Learnerdashboard' || $value == 'Maindashboard' || $value == 'Certification' || $value == 'Compliances') { 
                $getdashboardname[$i]['default'] = 0;
            } else {
                $getdashboardname[$i]['default'] = 1;
            }
            $i++;
        }
        return $getdashboardname;
    }
}
