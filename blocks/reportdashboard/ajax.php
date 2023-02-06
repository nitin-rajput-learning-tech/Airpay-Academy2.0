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
 * LearnerScript report dashboard Services.
 * @package  block_reportdashboard
 * @author Naveen kumar <naveen@eabyas.in>
 */
define('AJAX_SCRIPT', true);
require_once('../../config.php');
use block_learnerscript\local\reportbase;
use block_learnerscript\local\ls as ls;
global $CFG, $DB, $USER, $OUTPUT, $COURSE;
$rawjson = file_get_contents('php://input');

$requests = json_decode($rawjson, true);
$action = optional_param('action', $requests['action'], PARAM_TEXT);
$search = optional_param('term', $requests['term'], PARAM_RAW);
$frequency = optional_param('frequency', $requests['frequency'], PARAM_TEXT);
$reportid = optional_param('reportid', $requests['reportid'], PARAM_INT);
$reporttype = optional_param('selreport', $requests['selreport'], PARAM_RAW);
$blockinstanceid = optional_param('blockinstanceid', $requests['blockinstanceid'], PARAM_INT);
$usersearch = optional_param('term', $requests['term'], PARAM_TEXT);
$instance = optional_param('instance', $requests['instance'], PARAM_INT);
$oldname = optional_param('oldname', $requests['oldname'], PARAM_RAW);
$newname = optional_param('newname', $requests['newname'], PARAM_RAW);
$role = optional_param('role', $requests['role'], PARAM_RAW);
$costcenterid = optional_param('costcenter', $requests['costcenter'], PARAM_INT);
$departmentid = optional_param('department', $requests['department'], PARAM_INT);
$subdepartmentid = optional_param('subdepartment', $requests['subdepartment'], PARAM_INT);
$l4departmentid = optional_param('l4department', $requests['l4department'], PARAM_INT);
require_login();
$context = context_system::instance();
$PAGE->set_context($context);

switch ($action) {
    case 'generatedreport':
        $html = '';
        $report = $DB->get_record('block_learnerscript', array('id' => $reportid));
        if (!$report) {
            print_error('reportdoesnotexists', 'block_learnerscript');
        }

        if (!$report->global) {
            $html .= "";
        } else {
            require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
            require_once($CFG->dirroot . '/blocks/learnerscript/locallib.php');
            $reportclassname = 'report_' . $report->type;
            $reportclass = new $reportclassname($report);
            $renderer = $PAGE->get_renderer('block_reportdashboard');
            $html .= $renderer->generate_dashboardreport($reportclass, $reporttype, $blockinstanceid);
        }
        echo $html;
        exit;
        break;
    case 'userlist':
        $users = get_users(true, $usersearch);
        foreach ($users as $user) {
            $data[] = ['id' => $user->id, 'text' => $user->firstname.' '.$user->lastname];
        }
        $return = ['total' => count($data), 'items' => $data];
        break;
        break;
    case 'reportlist':
        $sql = "SELECT id,name
                FROM {block_learnerscript}
                WHERE visible = 1 AND name LIKE '%$search%'";
        $courselist = $DB->get_records_sql($sql);
        $activitylist = array();
        foreach ($courselist as $cl) {
            global $CFG;
            if (!empty($cl)) {
                $checkpermissions = (new reportbase($cl->id))->check_permissions($USER->id,
                    $context);
                if (!empty($checkpermissions) || has_capability('block/learnerscript:managereports', $context)) {
                    $modulelink = html_writer::link(new moodle_url('/blocks/learnerscript/viewreport.php',
                        array('id' => $cl->id)), $cl->name, array('id' => 'viewmore_id'));
                    $activitylist[] = ['id' => $cl->id, 'text' => $modulelink];
                }
            }
        }
        $termsdata = array();
        $termsdata['total_count'] = count($activitylist);
        $termsdata['incomplete_results'] = true;
        $termsdata['items'] = $activitylist;
        $return = $termsdata;
        break;
    case 'sendemails':
        require_once($CFG->dirroot . '/blocks/reportdashboard/email_form.php');
        $emailform = new analytics_emailform($CFG->wwwroot . '/blocks/reportdashboard/dashboard.php',
            array('reportid' => $reportid, 'AjaxForm' => true, 'instance' => $instance));
        $return = $emailform->render();
        break;
    case 'dashboardtiles':
        $reportclass = (new ls)->create_reportclass($reportid);
        $reportclass->create_report($blockinstanceid);
        $return = $reportclass->finalreport->table;
        break;
    case 'updatedashboard':
        $subpagepattern = strtolower($oldname);
        $newsubpagepattern = strtolower($newname);
        $subpagetype = $newname;
        $instances = $DB->get_records('block_instances',
            array('subpagepattern' => $subpagepattern));
        foreach ($instances as $instance) {
            // $instance->subpagepattern = $pagetypepattern;
            $instance->subpagepattern = $newsubpagepattern;
            $DB->update_record('block_instances', $instance);
            $positions = $DB->get_records('block_positions',
                array('blockinstanceid' => $instance->id));
            foreach ($positions as $position) {
                $position->subpage = $subpagetype;
                $DB->update_record('block_positions', $position);
            }
        }
        break; 
    case 'departmentlist':    
        unset($_SESSION['departments']);unset($_SESSION['subdepartment']);unset($_SESSION['compliance_id_array']);
        if ($costcenterid > 0) {
            $sql = "SELECT lc.id, lc.fullname FROM {local_costcenter} lc WHERE lc.parentid = $costcenterid AND depth = 2 ";
            // if (!empty($depids)) {
            //     $sql .= " AND cc.departmentid IN ($depids, $department)";
            // } else {
            //     $sql .= " AND cc.departmentid = $department";
            // }
            $departments = $DB->get_records_sql_menu($sql);
            if (!empty($departments)) {
                $return = array('0' => 'All') + $departments;
            } else {
                $return = array('-1' => 'Select Department');
            }
        } else {
            $return = array('-1' => 'Select Department');
        }
        break;
    case 'subdepartmentlist': 
        unset($_SESSION['subdepartment']);unset($_SESSION['compliance_id_array']);
        if ($departmentid > 0) {
            $sql = "SELECT lc.id, lc.fullname FROM {local_costcenter} lc WHERE lc.parentid = $departmentid AND lc.depth = 3 ";
            $subdepartments = $DB->get_records_sql_menu($sql);
            if (!empty($subdepartments)) {
                $return = array('0' => 'All') + $subdepartments;
            } else {
                $return = array('-1' => 'All');
            }
        } else {
            $return = array('-1' => 'All');
        }
        break;
    case 'l4departmentlist':
        if ($subdepartmentid > 0) {
            $sql = "SELECT lc.id, lc.fullname FROM {local_costcenter} lc WHERE lc.parentid = {$subdepartmentid} AND lc.depth = 4 ";
            $l4departments = $DB->get_records_sql_menu($sql);
            if (!empty($l4departments)) {
                $return = array(0 => 'All') + $l4departments;
            } else {
                $return = array(0 => 'All');
            }
        } else {
            $return = array(0 => 'All');
        }
    break;
    case 'l5departmentlist':
        if ($l4departmentid > 0) {
            $sql = "SELECT lc.id, lc.fullname FROM {local_costcenter} lc WHERE lc.parentid = {$l4departmentid} AND lc.depth = 5 ";
            $l5departments = $DB->get_records_sql_menu($sql);
            if (!empty($l5departments)) {
                $return = array(0 => 'All') + $l5departments;
            } else {
                $return = array(0 => 'All');
            }
        } else {
            $return = array(0 => 'All');
        }
    break;
    /*case 'onlinecourselist': 
        if ($costcenterid >= 0) {
            $sql = "SELECT c.id, c.fullname AS onlinecourse
                FROM {course} c 
                JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat
                WHERE 1 = 1 AND clf.name = 'Online Course' AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND c.open_costcenterid IN ($costcenterid, 0) ";
            if ($departmentid > 0) {
                $sql .= " AND c.open_departmentid IN ($departmentid, 0) ";
            }
            if ($subdepartmentid > 0) {
                $sql .= " AND c.open_subdepartment IN ($subdepartmentid, 0) ";
            }
            $onlinecourses = $DB->get_records_sql_menu($sql);
            if (!empty($onlinecourses)) {
                $return = array('0' => 'Filter by course') + $onlinecourses;
            } else {
                $return = array('-1' => 'Filter by course');
            }
        } else {
            $return = array('' => 'Filter by course');
        }
        break;    
    case 'departmentcompliances':
        unset($_SESSION['compliance_id_array']);
        $sql = "SELECT lcc.id, lcc.name AS compliancename
            FROM {local_compliance} lcc 
            JOIN {local_courses_orgwisevendors} lco ON lco.contentvendorid = lcc.contentvendor
            WHERE 1 = 1 ";
        if($costcenterid > 0){
            $sql .= " AND lcc.costcenter IN ($costcenterid, 0) AND lco.costcenterid = $costcenterid";
        }
        if ($departmentid > 0) {
            $sql .= " AND lcc.department IN ($departmentid, -1) ";
        }
        if ($subdepartmentid > 0) {
            $sql .= " AND lcc.subdepartment IN ($subdepartmentid, -1) ";
        }
        $compliances = $DB->get_records_sql_menu($sql);
        if (!empty($compliances)) {
             $return = array('0' => 'ALL') + $compliances;
        } else {
             $return = array('0' => 'Select Compliance');
        }
        break;
    case 'lablist': 
        if ($costcenterid >= 0) {
            $sql = "SELECT c.id, c.fullname AS lab
                FROM {course} c 
                JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat
                WHERE 1 = 1 AND clf.name = 'Lab' AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND c.open_costcenterid IN ($costcenterid, 0) ";
            if ($departmentid > 0) {
                $sql .= " AND c.open_departmentid IN ($departmentid, 0) ";
            }
            if ($subdepartmentid > 0) {
                $sql .= " AND c.open_subdepartment IN ($subdepartmentid, 0) ";
            }
            $labs = $DB->get_records_sql_menu($sql);
            if (!empty($labs)) {
                $return = array('0' => 'Filter by course') + $labs;
            } else {
                $return = array('-1' => 'Filter by course');
            }
        } else {
            $return = array('' => 'Filter by course');
        }
        break;
    case 'assessmentlist': 
        if ($costcenterid >= 0) {
            $sql = "SELECT c.id, c.fullname AS assessment
                FROM {course} c 
                JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat
                WHERE 1 = 1 AND clf.name = 'Assessment' AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND c.open_costcenterid IN ($costcenterid, 0) ";
            if ($departmentid > 0) {
                $sql .= " AND c.open_departmentid IN ($departmentid, 0) ";
            }
            if ($subdepartmentid > 0) {
                $sql .= " AND c.open_subdepartment IN ($subdepartmentid, 0) ";
            }
            $assessments = $DB->get_records_sql_menu($sql);
            if (!empty($assessments)) {
                $return = array('0' => 'Filter by course') + $assessments;
            } else {
                $return = array('-1' => 'Filter by course');
            }
        } else {
            $return = array('' => 'Filter by course');
        }
        break;
    case 'webinarlist': 
        if ($costcenterid > 0) {
            $sql = "SELECT c.id, c.fullname AS webinar
                FROM {course} c 
                JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat
                WHERE 1 = 1 AND clf.name = 'Webinar' AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND c.open_costcenterid IN ($costcenterid, 0) ";
            if ($departmentid > 0) {
                $sql .= " AND c.open_departmentid = $departmentid";
            }
            if ($subdepartmentid > 0) {
                $sql .= " AND c.open_subdepartment = $subdepartmentid ";
            }
            $webinars = $DB->get_records_sql_menu($sql);
            if (!empty($webinars)) {
                $return = array('0' => 'Filter by course') + $webinars;
            } else {
                $return = array('-1' => 'Filter by course');
            }
        } else {
            $return = array('' => 'Filter by course');
        }
        break;
    case 'classroomlist': 
        if ($costcenterid >= 0) {
            $sql = "SELECT lc.id, lc.name 
                FROM {local_classroom} lc 
                WHERE 1 = 1 AND lc.costcenter IN ($costcenterid, 0) ";
            if ($departmentid > 0) {
                $sql .= " AND lc.department IN ($departmentid, -1) ";
            }
            if ($subdepartmentid > 0) {
                $sql .= " AND lc.subdepartment IN ($subdepartmentid, -1) ";
            }
            $classrooms = $DB->get_records_sql_menu($sql);
            if (!empty($classrooms)) {
                $return = array('0' => 'Filter by course') + $classrooms;
            } else {
                $return = array('-1' => 'Filter by course');
            }
        } else {
            $return = array('' => 'Filter by course');
        }
        break;
    case 'programlist': 
        if ($costcenterid >= 0) {
            $sql = "SELECT lp.id, lp.name 
                FROM {local_program} lp 
                WHERE 1 = 1 AND lp.costcenter IN ($costcenterid, 0) ";
            if ($departmentid > 0) {
                $sql .= " AND lp.department IN ($departmentid, -1) ";
            } 
            if ($subdepartmentid > 0) {
                $sql .= " AND lp.subdepartment IN ($subdepartmentid, -1) ";
            }
            $programs = $DB->get_records_sql_menu($sql);
            if (!empty($programs)) {
                $return = array('0' => 'Filter by course') + $programs;
            } else {
                $return = array('-1' => 'Filter by course');
            }
        } else {
            $return = array('' => 'Filter by course');
        }
        break;      */  
    case 'learningpathlist': 
        if ($costcenterid >= 0) {
            $sql = "SELECT lp.id, lp.name 
                FROM {local_learningplan} lp
                WHERE 1 = 1 AND lp.costcenter IN ($costcenterid, 0) ";
            if ($departmentid > 0) {
                $sql .= " AND lp.department IN ($departmentid, -1) ";
            }
            if ($subdepartmentid > 0) {
                $sql .= " AND lp.subdepartment IN ($subdepartmentid, -1) ";
            }
            $classrooms = $DB->get_records_sql_menu($sql);
            if (!empty($classrooms)) {
                $return = array('0' => 'Filter by course') + $classrooms;
            } else {
                $return = array('-1' => 'Filter by course');
            }
        } else {
           $return = array('' => 'Filter by course');
        }
        break; 
    case 'departmentcourses':
        if ($costcenterid > 0) { 
            $orgsql .= " SELECT c.id FROM {course} c WHERE c.visible = 1 "; 
            if ($costcenterid > 0) {
                $orgsql .= " AND c.open_costcenterid = " .$costcenterid;
            }
            if ($departmentid > 0) {
                $orgsql .= " AND c.open_departmentid = " .$departmentid;
            }
            if ($subdepartmentid > 0) {
                $orgsql .= " AND c.open_subdepartment = " . $subdepartmentid;
            }
            $userssql = " ";
            $userssql .= " SELECT c.id FROM {course} c 
                        JOIN {enrol} e ON e.courseid = c.id AND e.status = 0 
                      JOIN {user_enrolments} ue on ue.enrolid = e.id AND ue.status = 0
                      JOIN {role_assignments}  ra ON ra.userid = ue.userid
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'employee'
                      JOIN {context} ctx ON ctx.instanceid = c.id 
                      JOIN {user} AS u ON u.id = ue.userid 
                      JOIN {local_costcenter} lc ON lc.id = u.open_costcenterid 
                      AND ra.contextid = ctx.id AND ctx.contextlevel = 50 AND c.visible = 1 
                      WHERE 1 = 1 ";
            if ($costcenterid > 0) {
                $userssql .= " AND u.open_costcenterid = " .$costcenterid;
            }
            if ($departmentid > 0) {
                $userssql .= " AND u.open_departmentid = " .$departmentid;
            }          
            if ($subdepartmentid > 0) {
                $userssql .= " AND u.open_subdepartment = " . $subdepartmentid;
            }
            $orgcourses = $DB->get_records_sql($orgsql); 
            $usercourses = $DB->get_records_sql($userssql); 

            if (empty($usercourses) && !empty($orgcourses)) {
                $totalcourses = $orgcourses; 
            } else if (!empty($usercourses) && empty($orgcourses)) {
                $totalcourses = $usercourses;
            } else if (!empty($usercourses) && !empty($orgcourses)) {
                $totalcourses = array_merge($usercourses, $orgcourses);
            } else if (!empty($usercourses) && !empty($orgcourses)) {
                $totalcourses = array();
            }
            foreach ($totalcourses as $totalcourse) {
                $list[] = $totalcourse->id;
            } 
            $list = !empty($totalcourses) ? implode(',', array_unique($list)) : 0; 
            $sql = "SELECT c.id, c.fullname FROM {course} c WHERE c.id IN (" . $list . ")"; 

            $courses = $DB->get_records_sql_menu($sql); 
            if (!empty($courses)) {
                $return = $courses;
            } else {
                $return = array('1' => 'Select course');
            }
        } else {
            $return = array('' => 'Select Course');
        } 
        break;
}
echo json_encode($return);
