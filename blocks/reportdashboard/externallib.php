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
 * LearnerScript Dashboard block plugin installation.
 *
 * @package    block_reportdashboard
 * @author     Arun Kumar Mukka
 * @copyright  2018 eAbyas Info Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_learnerscript\local\ls;
use block_learnerscript\local\reportbase;
use block_reportdashboard\local\reportdashboard;
global $CFG, $DB, $USER, $OUTPUT, $COURSE;
require_login();
class block_reportdashboard_external extends external_api {
    public static function userlist_parameters() {
        return new external_function_parameters(
            array(
                'term' => new external_value(PARAM_TEXT, 'The current search term in the search box', false, ''),
                '_type' => new external_value(PARAM_TEXT, 'A "request type", default query', false, ''),
                'query' => new external_value(PARAM_TEXT, 'Query', false, ''),
                'action' => new external_value(PARAM_TEXT, 'Action', false, ''),
                'userlist' => new external_value(PARAM_TEXT, 'Users list', false, ''),
                'reportid' => new external_value(PARAM_INT, 'Report ID', false, 0),
                'maximumSelectionLength' => new external_value(PARAM_INT, 'Maximum Selection Length to Search', false, 0),
                'setminimumInputLength' => new external_value(PARAM_INT, 'Minimum Input Length to Search', false, 2)
            )
        );
    }
    public static function userlist($term, $_type, $query, $action, $userlist, $reportid, $maximumSelectionLength, $setminimumInputLength) {
        global $DB;
        // $users = get_users(true, $term, true);
       $users = $DB->get_records_sql("SELECT * FROM {user} WHERE id > 2 AND deleted = 0 AND (firstname LIKE '%" . $term . "%' OR lastname LIKE '%" . $term . "%' OR username LIKE '%" . $term . "%' OR email LIKE '%" . $term . "%' )");
        $reportclass = (new ls)->create_reportclass($reportid);
        $reportclass->courseid = $reportclass->config->courseid;
        if ($reportclass->config->courseid == SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($reportclass->config->courseid);
        }
        $data = array();

        $permissions = (isset($reportclass->componentdata['permissions'])) ? $reportclass->componentdata['permissions'] : array();
        
        foreach ($users as $user) {
            if(is_siteadmin($user) || (new ls)->is_manager($user->id)){
              $data[] = ['id' => $user->id, 'text' => fullname($user)];
              break;
            }
            if(!empty($permissions['elements'])){
                $contextlevel = $_SESSION['ls_contextlevel'];
                $userroles = (new ls)->get_currentuser_roles($user->id);
                foreach ($userroles as $userrole) {
                    $reportclass->role = $userrole;
                    if ($reportclass->check_permissions($user->id, $context)) {
                        $data[] = ['id' => $user->id, 'text' => fullname($user)];
                        break;
                    }
                }
            }
        }
        $return = ['total_count' => count($data), 'items' => $data];
        $data = json_encode($return);
        return $data;
    }
    public static function userlist_returns() {
        return new external_value(PARAM_RAW, 'data');
    }

    public static function reportlist_parameters() {
        return new external_function_parameters(
            array(
                'search' => new external_value(PARAM_RAW, 'Search value', false, ''),
            )
        );
    }
    public static function reportlist($search) {
        $context = context_system::instance();
        $search = 'admin';
        $sql = "SELECT id, name FROM {block_learnerscript} WHERE visible = 1 AND name LIKE '%$search%'";
        $courselist = $DB->get_records_sql($sql);
        $activitylist = array();
        foreach ($courselist as $cl) {
            global $CFG;
            if (!empty($cl)) {
                $checkpermissions = (new reportbase($cl->id))->check_permissions($USER->id, $context);
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
        $data = json_encode($return);
        return $data;
    }

    public static function reportlist_returns() {
        return new external_value(PARAM_RAW, 'data');
    }

    public static function sendemails_parameters() {
        return new external_function_parameters(
            array(
                'reportid' => new external_value(PARAM_INT, 'Report ID', false, 0),
                'instance' => new external_value(PARAM_INT, 'Reprot Instance', false),
                'pageurl' => new external_value(PARAM_LOCALURL, 'Page URL', false, ''),
            )
        );

    }
    public static function sendemails($reportid, $instance, $pageurl) {
        global $CFG, $PAGE;
        $PAGE->set_context(context_system::instance());
        $pageurl = $pageurl ? $pageurl : $CFG->wwwroot . '/blocks/reportdashboard/dashboard.php';
        require_once($CFG->dirroot . '/blocks/reportdashboard/email_form.php');
        $emailform = new analytics_emailform($pageurl, array('reportid' => $reportid, 'AjaxForm' => true, 'instance' => $instance));
        $return = $emailform->render();
        $data = json_encode($return);
        return $data;
    }

    public static function sendemails_returns() {
        return new external_value(PARAM_RAW, 'data');
    }

    public static function inplace_editable_dashboard_parameters() {
        return new external_function_parameters(
            array(
                'prevoiusdashboardname' => new external_value(PARAM_TEXT, 'The Prevoius Dashboard Name', false, ''),
                'pagetypepattern' => new external_value(PARAM_TEXT, 'The Page Patten Type', false, ''),
                'subpagepattern' => new external_value(PARAM_TEXT, 'The Sub Page Patten Type', false, ''),
                'value' => new external_value(PARAM_TEXT, 'The Dashboard Name', false, ''),
            )
        );
    }
    public static function inplace_editable_dashboard($prevoiusdashboardname, $pagetypepattern, $subpagepattern, $value) {
        global $DB, $PAGE;
        $explodepetten = explode('-', $pagetypepattern);
        $dashboardname = str_replace (' ', '', $value);
        if (strlen($dashboardname) > 30 || empty($dashboardname)) {
            return $prevoiusdashboardname;
        }
        $update = $DB->execute("UPDATE {block_instances} SET subpagepattern = '$dashboardname' WHERE subpagepattern = '$subpagepattern'");
        if ($update) {
            return $dashboardname;
        } else {
            return false;
        }
    }
    public static function inplace_editable_dashboard_returns() {
        return new external_value(PARAM_RAW, 'data');
    }

    public static function addtiles_to_dashboard_is_allowed_from_ajax() {
        return true;
    }
    public static function addtiles_to_dashboard_parameters() {
        return new external_function_parameters(
            array(
                'role' => new external_value(PARAM_TEXT, 'Role', false),
                'dashboardurl' => new external_value(PARAM_TEXT, 'Created Dashboard Name', false),
            )
        );
    }
    public static function addtiles_to_dashboard($role, $dashboardurl) {
        global $PAGE, $CFG, $DB;
        $PAGE->set_context(context_system::instance());
        $context = context_system::instance();
        if ((has_capability('block/learnerscript:managereports', $context) ||
            has_capability('block/learnerscript:manageownreports', $context) ||
            is_siteadmin())) {
            require_once $CFG->dirroot . '/blocks/reportdashboard/reporttiles_form.php';
            $seturl = !empty($role) ? '/blocks/reportdashboard/dashboard.php?role='.$role : '/blocks/reportdashboard/dashboard.php';
            if($dashboardurl != ''){
                $seturl = !empty($role) ? '/blocks/reportdashboard/dashboard.php?role='.$role.'&dashboardurl='.$dashboardurl.'' :'/blocks/reportdashboard/dashboard.php?dashboardurl='.$dashboardurl.'';
            }
            $staticreports = $DB->get_records_sql("SELECT id FROM {block_learnerscript}
                                                WHERE type ='statistics' AND visible=1 AND global = 1");
            $reporttiles = new reporttiles_form($CFG->wwwroot.$seturl);
            if(!empty($staticreports)){
                $return = $reporttiles->render();
            } else{
                $return = '<div class="alert alert-info">'.get_string('statisticsreportsnotavailable',  'block_reportdashboard').'</div>';
            }
        } else {
            $terms_data = array();
            $terms_data['error'] = true;
            $terms_data['type'] = 'Warning';
            $terms_data['cap'] = true;
            $terms_data['msg'] = get_string('badpermissions', 'block_learnerscript');
            $return = $terms_data;
        }
        $data = json_encode($return);
        return $data;
    }
    public static function addtiles_to_dashboard_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    public static function addwidget_to_dashboard_is_allowed_from_ajax() {
        return true;
    }
    public static function addwidget_to_dashboard_parameters() {
        return new external_function_parameters(
            array(
                'role' => new external_value(PARAM_TEXT, 'Role', false),
                'dashboardurl' => new external_value(PARAM_TEXT, 'Created Dashboard Name', false),
            )
        );
    }
    public static function addwidget_to_dashboard($role, $dashboardurl) {
        global $PAGE, $CFG, $DB;
        $PAGE->set_context(context_system::instance());
        $context = context_system::instance();
        if ((has_capability('block/learnerscript:managereports', $context) ||
            has_capability('block/learnerscript:manageownreports', $context) ||
            is_siteadmin())) {
            $seturl = !empty($role) ? '/blocks/reportdashboard/dashboard.php?role='.$role : '/blocks/reportdashboard/dashboard.php';
            if($dashboardurl != ''){
                $seturl = !empty($role) ? '/blocks/reportdashboard/dashboard.php?role='.$role.'&dashboardurl='.$dashboardurl.'' :'/blocks/reportdashboard/dashboard.php?dashboardurl='.$dashboardurl.'';
            }
            $coursels = false;
            $parentcheck = false;
            if ($dashboardurl == 'Course') {
                $coursels = true;
                $parentcheck = false;
            }
            require_once $CFG->dirroot . '/blocks/reportdashboard/reportselect_form.php';
            $reportselect = new reportselect_form($CFG->wwwroot.$seturl, array('coursels' => $coursels, 'parentcheck' => $parentcheck));
            $rolereports = (new ls)->listofreportsbyrole($coursels, false, $parentcheck);
            if(!empty($rolereports)) {
                $return = $reportselect->render();
            } else{
                $return = '<div class="alert alert-info">'.get_string('customreportsnotavailable',  'block_reportdashboard').'</div>';
            }
        } else {
            $terms_data = array();
            $terms_data['error'] = true;
            $terms_data['type'] = 'Warning';
            $terms_data['cap'] = true;
            $terms_data['msg'] = get_string('badpermissions', 'block_learnerscript');
            $return = $terms_data;
        }
        $data = json_encode($return);
        return $data;
    }
    public static function addwidget_to_dashboard_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    public static function complianceslist_parameters() {
        return new external_function_parameters(
            array(
                'costcenter' => new external_value(PARAM_INT, 'costcenter', false),
                'department' => new external_value(PARAM_INT, 'department', false),
                'subdepartment' => new external_value(PARAM_INT, 'subdepartment', false),
                'complianceid' => new external_value(PARAM_RAW, 'complianceid', false)
            )
        );
    }
    public static function complianceslist($costcenter=NULL, $department=NULL, $subdepartment=NULL, $complianceid=NULL) {
        global $PAGE, $CFG, $DB, $USER;
        $complianceid=json_decode($complianceid, true);
        $complianceid=implode(",",$complianceid);
        $sql= "SELECT lc.id as cid,lc.name as compliancename 
                FROM {local_compliance} lc 
                JOIN {local_courses_orgwisevendors} lco ON lco.contentvendorid = lc.contentvendor 
                WHERE 1=1 ";
        $systemcontext = context_system::instance();
        if (!is_siteadmin()) {
            $ohs = $dhs = 1;
        }

        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) { 
            if($costcenter>0) {

                $sql .=" AND lc.costcenter IN (".$costcenter.", 0) AND lco.costcenterid = ".$costcenter;
                $dashboardcostcenter = " AND u.open_costcenterid =".$costcenter;

            }            
        } else if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {

            $sql .= " AND lc.costcenter IN (". $USER->open_costcenterid.", 0) AND lco.costcenterid = ".$USER->open_costcenterid;
            $dashboardcostcenter = " AND u.open_costcenterid  =". $USER->open_costcenterid; 

        } else if (!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) {

            $sql .= "  AND  lc.costcenter IN (". $USER->open_costcenterid.", 0) AND lc.department IN (". $USER->open_departmentid.", -1) AND lco.costcenterid = ".$USER->open_costcenterid;
            $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid; 
        } else { 

            $sql .= "  AND  lc.costcenter IN (". $USER->open_costcenterid.", 0) AND lc.department IN (". $USER->open_departmentid.", -1) AND lc.subdepartment IN (". $USER->open_subdepartment.", -1) AND lco.costcenterid = ".$USER->open_costcenterid;
            $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid ." AND u.open_subdepartment =". $USER->open_subdepartment ;
        }

        if($department > 0) {

            $sql .= " AND lc.department IN (". $department.", -1) ";
            $dashboardcostcenter .= " AND u.open_departmentid = ".$department;

        }
        if($subdepartment > 0) {

            $sql .= " AND lc.subdepartment IN (". $subdepartment.", -1) ";
            $dashboardcostcenter .= " AND u.open_subdepartment = ".$subdepartment;

        }
        if($complianceid!='') {
            $ccompliance = $complianceid;
            $ccompliancelist = explode(',',$ccompliance);
            if(in_array("0",$ccompliancelist)) {
                $all = $DB->get_fieldset_sql("SELECT lc.id as cid 
                    FROM {local_compliance} lc ");
                array_unshift($all, 0);
            } else {
                $sql .= " AND lc.id IN (".implode(',', $ccompliancelist).")";
            }
            
            $_SESSION['compliance_id_array']=$complianceid;
            $_SESSION['costcenter']=$costcenter;
            $_SESSION['departments']=$department;
            $_SESSION['subdepartment']=$subdepartment; 
        }
        if($complianceid == 0){
             $_SESSION['compliance_id_array']='';
        }
        $sql .= " GROUP BY lc.id ";
        $compliances = $DB->get_records_sql($sql);
        $compliancename = '';
        $i = 1;
        foreach($compliances as $compliance) {
            $section_values=array();
            $sections=$DB->get_records_sql_menu("SELECT mlcs.id 
                FROM {local_compliance_sections} mlcs 
                JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid 
                JOIN {local_courses_orgwisevendors} lco ON lco.contentvendorid = mlc.contentvendor

                WHERE mlc.id=$compliance->cid");
                $sections = array_keys($sections);
                $totalsectionpercentage=0;
                foreach ($sections as $id => $record) {
                     $sql = $DB->get_field_sql("
                        SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as 'sectionpercenatge' 
                            FROM {compliance_user_sec_comp} mlusc 
                            JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid 
                            JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
                            JOIN {local_compliance_modules} mlcm ON mlcm.complianceid = mlc.id AND mlcm.modulename = 'certification' AND mlcm.sectionid = mlcs.id
                            JOIN {local_certification_users} AS lcu ON lcu.certificationid = mlcm.moduleid AND mlusc.userid = lcu.userid
                            JOIN {user} u ON u.id = mlusc.userid 
                            WHERE 1=1 {$dashboardcostcenter} AND mlcs.id= {$record} AND lcu.completion_status =1 AND (lcu.expirydate =0 OR lcu.expirydate >= UNIX_TIMESTAMP()) GROUP BY mlusc.sectionid

                            UNION

                            SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as 'sectionpercenatge' FROM {compliance_user_sec_comp} mlusc 
                            JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid 
                            JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
                            JOIN {local_compliance_modules} mlcm ON mlcm.complianceid = mlc.id AND mlcm.modulename = 'course' AND mlcm.sectionid = mlcs.id
                            JOIN {course} c ON c.id = mlcm.moduleid
                            JOIN {course_completions} AS cc ON cc.course = c.id AND cc.userid = mlusc.userid
                            JOIN {user} u ON u.id = mlusc.userid
                            WHERE  1 = 1 {$dashboardcostcenter} AND cc.timecompleted IS NOT NULL AND mlcs.id='".$record."' GROUP BY mlusc.sectionid");
                    $spercentage=ROUND($sql*100,2);
                    if ($spercentage>100) { 
                        $spercentage=100;
                    }
                    array_push($section_values,$spercentage);
                    $totalsectionpercentage=$totalsectionpercentage+$spercentage;
                }
                $max_value=max($section_values);
                $compliancepercentage = ROUND(($totalsectionpercentage/count($sections)));
                if(empty($sections)){
                    $compliancepercentage=0;
                }
                $percentage = !empty($compliancepercentage) ? ROUND($compliancepercentage, 0) : '0';



                if ($percentage>100) {
                    $percentage = 100;
                }
                $compliance_criteria = $DB->get_field_sql("SELECT sectiontracking 
                    FROM {local_compliance_completion} mlcc 
                    WHERE mlcc.complianceid=$compliance->cid");
                if ($compliance_criteria== 'AND' || $compliance_criteria== 'OR') {
                    $percentage=ROUND($max_value,0);
                }
                $compliance->compliancepercentage=$percentage;
                if ($compliance->compliancepercentage == 100) {
                    $status = 'complete';
                } elseif($compliance->compliancepercentage > 80 && $compliance->compliancepercentage < 100) {
                    $status = 'pending';
                } elseif($compliance->compliancepercentage < 80) {
                    $status = 'inprogress';
                }
                if ($i == 1) {
                    $activetab = 'active';
                    ++$i;
                } else {
                    $activetab = '';
                }
            $compliancename .= '<a class="compliance nav-link mb-3 '. $status .' '. $activetab .'" id="v-pills-home-tab" data-toggle="pill" href="#v-pills-home" role="tab" aria-controls="v-pills-home" aria-selected="true" data-complianceid = '. $compliance->cid .'><span>'.$compliance->compliancename.'</span><div class="circle_bg"><div class="circle_status"></div></div></a>';
        }
        $return = array(
            'compliancetabs' => json_encode($compliancename));
        return $return;
    }
    public static function complianceslist_returns() {
        return new external_single_structure(array(
            'compliancetabs' => new external_value(PARAM_RAW, 'compliancetabs')                        
        ));
    }
    public static function compliancedetails_parameters() {
        return new external_function_parameters(
            array(
                'complianceid' => new external_value(PARAM_INT, 'complianceid', false),
                'costcenter' => new external_value(PARAM_INT, 'costcenter', false),
                'department' => new external_value(PARAM_INT, 'department', false),                                
                'subdepartment' => new external_value(PARAM_INT, 'subdepartment', false)                                
            )
        );
    }
    public static function compliancedetails($complianceid, $costcenter=NULL, $department=NULL, $subdepartment=NULL) {
        global $DB, $CFG, $USER;
        $systemcontext = context_system::instance();
        if (!is_siteadmin()) {
            $ohs = $dhs = 1;
        }
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) { 
            if ($costcenter>0) {
                $sql .=" AND lc.costcenter IN (".$costcenter.", 0) ";
                $dashboardcostcenter = " AND u.open_costcenterid =".$costcenter;
            }            
            if ($department > 0) {
                $sql .= " AND lc.department IN (". $department.", -1) ";
                $dashboardcostcenter .= " AND u.open_departmentid = ".$department;
            }
            if ($subdepartment > 0) {
                $sql .= " AND lc.subdepartment IN (". $subdepartment.", -1) ";
                $dashboardcostcenter .= " AND u.open_subdepartment = ".$subdepartment;
            }
        } else if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {
            $sql .= " AND lc.costcenter IN (". $USER->open_costcenterid.", 0) ";
            $dashboardcostcenter = " AND u.open_costcenterid  =". $USER->open_costcenterid;
            if ($department > 0) {
                $sql .= " AND lc.department IN (". $department.", -1) ";
                $dashboardcostcenter .= " AND u.open_departmentid = ".$department;
            }
            if ($subdepartment > 0) {
                $sql .= " AND lc.subdepartment IN (". $subdepartment.", -1) ";
                $dashboardcostcenter .= " AND u.open_subdepartment = ".$subdepartment;
            }        
        } else if (!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $ohs) { 
            $sql .= "  AND  lc.costcenter IN (". $USER->open_costcenterid.", 0) AND lc.department IN (". $USER->open_departmentid.", -1) ";
            $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid;
            if ($subdepartment > 0) {
                $sql .= " AND lc.subdepartment IN (". $subdepartment.", -1) ";
                $dashboardcostcenter .= " AND u.open_subdepartment = ".$subdepartment;
            }
        } else { 
            $sql .= "  AND  lc.costcenter IN (". $USER->open_costcenterid.", 0) AND lc.department IN (". $USER->open_departmentid.", -1) AND lc.subdepartment IN (". $USER->open_subdepartment.", -1) ";
            $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid ." AND u.open_subdepartment =". $USER->open_subdepartment;
        }
         
        if (!$complianceid) { 
            $complianceid=0; 
        }
        $section_values=array();
        $sections = $DB->get_records_sql_menu("SELECT mlcs.id 
            FROM {local_compliance_sections} mlcs 
            JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid 
            WHERE mlc.id=$complianceid");
         $sections = array_keys($sections);
         $totalsectionpercentage=0;
         foreach ($sections as $id => $record) {
             $sql = $DB->get_field_sql("SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as 'sectionpercenatge' 
                FROM {compliance_user_sec_comp} mlusc 
                JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid 
                JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
                JOIN {local_compliance_modules} mlcm ON mlcm.complianceid = mlc.id AND mlcm.modulename = 'certification' AND mlcm.sectionid= mlcs.id
                JOIN {local_certification_users} AS lcu ON lcu.certificationid = mlcm.moduleid AND mlusc.userid = lcu.userid 
                JOIN {user} u ON u.id = mlusc.userid 
                WHERE 1=1 {$dashboardcostcenter} AND mlcs.id= {$record} AND lcu.completion_status =1 AND (lcu.expirydate =0 OR lcu.expirydate >= UNIX_TIMESTAMP()) GROUP BY mlusc.sectionid
                UNION
                SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as 'sectionpercenatge' 
                FROM {compliance_user_sec_comp} mlusc 
                JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid 
                JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
                JOIN {local_compliance_modules} mlcm ON mlcm.complianceid = mlc.id AND mlcm.modulename = 'course' AND mlcm.sectionid= mlcs.id
                JOIN {course} c ON c.id = mlcm.moduleid
                JOIN {course_completions} AS cc ON cc.course = c.id AND cc.userid = mlusc.userid
                JOIN {user} u ON u.id = mlusc.userid
                WHERE  1 = 1 {$dashboardcostcenter} AND cc.timecompleted IS NOT NULL AND mlcs.id='".$record."' GROUP BY mlusc.sectionid");

            $spercentage=ROUND($sql*100,2);
            if ($spercentage>100) { 
                $spercentage=100;
            }
             array_push($section_values,$spercentage);
            $totalsectionpercentage=$totalsectionpercentage+$spercentage;
         }
        $max_value=max($section_values);
        $compliancepercentage = ROUND(($totalsectionpercentage/count($sections)));
        if (empty($sections)) { 
            $compliancepercentage=0; 
        }
        $percentage = !empty($compliancepercentage) ? ROUND($compliancepercentage, 0) : '0';
        if ($percentage>100) { 
            $percentage = 100; 
        }
        $compliance_criteria = $DB->get_field_sql("SELECT sectiontracking 
            FROM {local_compliance_completion} mlcc 
            WHERE mlcc.complianceid=$complianceid");
        if ($compliance_criteria== 'AND' || $compliance_criteria== 'OR') {
            $percentage=ROUND($max_value,0);
        }
        $overallcompliancename = $DB->get_field_sql("SELECT name 
            FROM {local_compliance} 
            WHERE id =". $complianceid);

        $overallpercentage = '<div class="reporttile_title text-center" title="Learners">'. $overallcompliancename .'</div><div class="tiles_information"><table width="100%" class="report_tiles_table"><tbody><tr><td>Overall compliance<b><span>'. $percentage .'%</span></b></td></tr></tbody></table></div>';
	$compliancetracking = $DB->get_field_sql("SELECT sectiontracking 
            FROM {local_compliance_completion} lcs 
            WHERE lcs.complianceid =". $complianceid);

        $track = !empty($compliancetracking) ? $compliancetracking : 'N/A';
        if ($track == 'AND' || $track == 'OR') {
            $trackig = 'Compliance will be achieved when any of the below sections are achieved';
        } else if($track == 'ALL') {
            $trackig = 'Compliance will be achieved when all the below sections are achieved';
        } else {
            $trackig = '';
        }        
        $tracking = "<span>". $trackig ."</span>";
        $sections = $DB->get_records_sql("SELECT id, name 
            FROM {local_compliance_sections} lcs 
            WHERE lcs.complianceid =". $complianceid);

        foreach($sections as $section) {
           $requirement = $DB->get_record_sql("SELECT mlcs.name as section, mlcs.userscount as requiredusers
                FROM {local_compliance_sections} mlcs
                WHERE mlcs.id = ".$section->id);
            $secreqs = $DB->get_records_sql("SELECT id, coursetracking, certificationtracking, moduletracking 
                FROM {local_compliance_sec_comp} 
                WHERE sectionid = {$section->id} 
                ORDER BY id ASC ");
            $track = array();
            $trackings = array();
            foreach($secreqs as $secreq){
                if (!empty($secreq->coursetracking)) {
                    $trackings['crs'] = $secreq->coursetracking;    
                }
                if (!empty($secreq->certificationtracking)) {
                    $trackings['cert'] = $secreq->certificationtracking;    
                }
                $track[] = $trackings;
                $modulereq = $secreq->moduletracking;
            }
            $requirementtracking = end($track);
            $textcrs = array();
            $textcertcriteria = array();
            if (!empty($secreqs)){
                    switch (strtolower($requirementtracking['crs'])) {
                        case 'all':
                            $textcrs[] = 'ALL';
                            break;
                        case 'or':
                            $textcrs[] = 'ANY';
                        break;
                        case 'and':
                            $textcrs[] = 'SELECTED';
                        break;
                    }
                    switch (strtolower($requirementtracking['cert'])) {
                        case 'all':
                            $textcertcriteria[] = 'ALL';
                            break;
                        case 'or':
                            $textcertcriteria[] = 'ANY';
                        break;
                        case 'and':
                            $textcertcriteria[] = 'SELECTED';
                        break;
                    }
            } else {
                $textcrs[] = '';
                $textcertcriteria[] = '';
            }
            $criteriacrs = implode('', $textcrs);
            $criteriacert = implode('', $textcertcriteria);
            $requiredusers = !empty($requirement->requiredusers) ? $requirement->requiredusers : 'N/A';
            $coursereq = !empty($criteriacrs) ? $criteriacrs : '';
            $certificationreq = !empty($criteriacert) ? $criteriacert : '';
            //$modulereq = !empty($modulereq) ? $modulereq : 'N/A';
            $completesection = '<div class="tab-content" id="v-pills-tabContent"><div class="tab-pane fade show active" id="v-pills-home" role="tabpanel" aria-labelledby="v-pills-home-tab"><div class="requirement_block"><div class="row"><div class="col-md-9"><div class="compliance_report_block"><div class="row"><div class="col-md-4 pr-0"><table class="table complaince_table" ><thead><tr><td>'. $section->name .'</td><td></td></tr></thead><tbody><tr><td>Required users:</td><td>'. $requiredusers .'</td></tr><tr>';
        if ($coursereq) {
            $completesection .= '<td>Course/Exam requirements</td><td>'. $coursereq .'</td>';
        }
            $completesection .= '</tr><tr>';
        if ($certificationreq) {
            $completesection .= '<td>Certification requirements:</td><td>'. $certificationreq .'</td>';
        }
            $completesection .= '</tr></tbody></table></div><div class="col-md-8 pr-0"><table width="100%" class="generaltable dependencyfilter dataTable no-footer dtr-inline table complaince_table" id="reporttable_1234"><thead><tr><th>Course/Exam/Certification</th><th>Completed</th><th></th></tr></thead><tbody>';
            $expirydate = strtotime("+90 days");
            $secmodules = $DB->get_records_sql("SELECT c.id AS id, c.fullname AS crscert, mlcm.modulename AS modulename, 0 AS upcomingexpiry, 0 AS eol, 
                    (SELECT COUNT(DISTINCT cc.userid) 
                        FROM {course_completions} AS cc 
                        JOIN {user} AS u ON u.id= cc.userid 
                        WHERE 1=1 {$dashboardcostcenter} AND cc.course = c.id AND cc.timecompleted IS NOT NULL) AS completedusers
                    FROM {local_compliance_modules} AS mlcm
                    JOIN {course} AS c ON c.id = mlcm.moduleid AND mlcm.modulename = 'course'
                    LEFT JOIN {course_completions} AS cc ON cc.course = c.id
                    WHERE mlcm.sectionid = {$section->id}
                    GROUP BY mlcm.moduleid
                    UNION
                    SELECT lc.id AS id, lc.name AS crscert, mlcm.modulename AS modulename,
                        (SELECT COUNT(lc.expirydate)
                            FROM {local_certification_users} AS lc 
                            JOIN {user} AS u ON u.id = lc.userid
                            WHERE lc.certificationid = cc.certificationid {$dashboardcostcenter} AND lc.completion_status > 0 AND lc.expirydate BETWEEN UNIX_TIMESTAMP() AND $expirydate) AS upcomingexpiry,
                        (SELECT COUNT(lc1.eol) 
                            FROM {local_certification} AS lc1
                            WHERE lc1.id = cc.certificationid AND from_unixtime(lc1.eol) BETWEEN CURDATE() AND (CURDATE() + 90)) AS eol,
                        (SELECT COUNT(lcc.id) 
                            FROM {local_certification_users} AS lcc
                            JOIN {user} AS u ON u.id = lcc.userid 
                            WHERE 1=1 {$dashboardcostcenter} AND lcc.certificationid = lc.id AND lcc.completion_status =1 AND (lcc.expirydate =0 OR lcc.expirydate >= UNIX_TIMESTAMP())) AS completedusers
                    FROM {local_compliance_modules} AS mlcm
                    JOIN {local_certification} lc ON lc.id = mlcm.moduleid AND mlcm.modulename='certification'
                    LEFT JOIN {local_certification_users} cc ON cc.certificationid = lc.id
                    WHERE mlcm.sectionid = {$section->id}
                    GROUP BY mlcm.moduleid");
            $compliancecoursesid = $DB->get_field('block_learnerscript', 'id', array('type' => 'compliancecourseuserslist'), IGNORE_MULTIPLE);
            $compliancecertid = $DB->get_field('block_learnerscript', 'id', array('type' => 'compliancecertificationuserslist'), IGNORE_MULTIPLE);
            foreach($secmodules as $secmodule) {
            $compliancecourses = new moodle_url('/blocks/learnerscript/viewreport.php', array('id' => $compliancecoursesid, 'filter_course' => $secmodule->id));
            $compliancecert = new moodle_url('/blocks/learnerscript/viewreport.php', array('id' => $compliancecertid, 'filter_certificates' => $secmodule->id, 'filter_status' => 'completed'));
            $compliancecertexp = new moodle_url('/blocks/learnerscript/viewreport.php', array('id' => $compliancecertid, 'filter_certificates' => $secmodule->id, 'filter_status' => 'upcomingexpiry'));

                $completesection .= '<tr class="section_details"><td>'. $secmodule->crscert .'</td>';
                if($secmodule->modulename == 'course') {
                    $completesection .= '<td style="text-align:center">'. html_writer::tag('a', $secmodule->completedusers, array('href' => $compliancecourses, 'class'=> 'sectionuser', 'data-tableid' => 'reporttable_'.$compliancecoursesid, 'data-reportid'=>  $compliancecoursesid)) .'</td>';
                } else { 
                    $completesection .= '<td style="text-align:center">'. html_writer::tag('a', $secmodule->completedusers, array('href' => $compliancecert, 'class'=> 'sectionuser', 'data-tableid' => 'reporttable_'.$compliancecertid, 'filter_status'=> 'completed','data-reportid'=>  $compliancecertid)) .'</td>';
                    if($secmodule->completedusers > 0 && !empty($secmodule->upcomingexpiry)) {
                        $completesection .= '<td>'. html_writer::tag('a', '<span class="notification_bell_icon"></span>', array('href' => $compliancecertexp, 'class'=> 'sectionuser', 'filter_status'=> 'upcomingexpiry','data-tableid' => 'reporttable_'.$compliancecertid, 'data-reportid'=>  $compliancecertid)) .'</td></tr>';
                    }
                }
            }
           $sqlsec= $DB->get_field_sql(
           "SELECT ((SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as sectionpercenatge
            FROM {compliance_user_sec_comp} mlusc 
            JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid
            JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
            JOIN {local_compliance_modules} lcm ON lcm.complianceid = mlc.id AND lcm.modulename = 'certification' AND lcm.sectionid= mlcs.id
            JOIN {local_certification_users} lcu ON lcu.certificationid = lcm.moduleid AND mlusc.userid = lcu.userid
            JOIN {user} u ON u.id = mlusc.userid
            WHERE  1 = 1 {$dashboardcostcenter} AND mlusc.sectionid ={$section->id} AND lcu.completion_status =1 AND (lcu.expirydate =0 OR lcu.expirydate >= UNIX_TIMESTAMP()))
            
            +

            (SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as sectionpercenatge
            FROM {compliance_user_sec_comp} mlusc 
            JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid
            JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
            JOIN {local_compliance_modules} lcm ON lcm.complianceid = mlc.id AND lcm.modulename = 'course' AND lcm.sectionid= mlcs.id
            JOIN {course} c ON c.id = lcm.moduleid
            JOIN {course_completions} AS cc ON cc.course = c.id AND cc.userid = mlusc.userid
            JOIN {user} u ON u.id = mlusc.userid
            WHERE  1 = 1 {$dashboardcostcenter} AND cc.timecompleted IS NOT NULL AND mlusc.sectionid = {$section->id}))as total");
            $sectionpercentage = ROUND($sqlsec*100, 2);
            $secpercentage = !empty($sectionpercentage) ? ROUND($sectionpercentage, 0) : '0';
            if ($secpercentage>100) {
                $secpercentage=100;
            }
            $completesection .= '</tbody></table></div></div></div></div><div class="col-md-3 complaince_progress_block_bg"><div class="complaince_progress_block"><h6 class="font-weight-bold text-center pt-3 mb-0 pb-4">Compliance</h6><div class="progress-radial-container"><div class="progress-radial progress-89 setsize" id="aggregateattcircle"><div class="overlay setsize"><p class="circular_value">'. $secpercentage .'%</p></div></div></div></div></div></div></div></div></div>';
           $datasection[] = $completesection;
        }

        $return = array(
            'overallpercentage' => $overallpercentage,            
            'sections' => json_encode($datasection),
            'tracking' => $tracking,
            'compliance' => json_encode($data)
        );
        return $return;
    }
    public static function compliancedetails_returns() {
        return new external_single_structure(array(
            'overallpercentage' => new external_value(PARAM_RAW, 'overallpercentage'),
            'sections' => new external_value(PARAM_RAW, 'sections'),
            'tracking' => new external_value(PARAM_RAW, 'tracking'),
            'compliance' => new external_value(PARAM_RAW, 'compliance')
        ));
    }    
}
