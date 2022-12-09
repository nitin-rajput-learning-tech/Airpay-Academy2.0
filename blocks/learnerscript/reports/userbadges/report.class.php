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
 * @author: manikanta
 * @date: 2017
 */
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/badgeslib.php');
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;

class report_userbadges extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        if ($this->loggedinuserrole != 'user') {
            $this->basicparams = [['name' => 'users']];
        }
        $this->columns = array('userbadges' => array('name','issuername','coursename','timecreated','dateissued', 'description', 'criteria','expiredate'));
        $this->components = array('columns', 'filters', 'permissions',/* 'calcs',*/ 'plot'); 
        if ($this->loggedinuserrole != 'user' && $this->loggedinuserrole != 'dh') {
            $this->basicparams = [['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments'], ['name' => 'users']];
        } else if ($this->loggedinuserrole == 'dh') {
            $this->basicparams = array(['name' => 'subdepartments'],['name' => 'users']);
        } else if ($this->loggedinuserrole != 'user') {
            $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        }
        $this->filters = array('course', 'contentprovider', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');
        if (isset($this->role) && $this->role == 'user') {
            $this->parent = true;
        } else {
            $this->parent = false;
        }
        $this->orderable = array('name', 'issuername', 'timecreated', 'dateissued', 'description', 'expiredate');
        $this->defaultcolumn = 'b.id';
    }

    function init() {
        // if($this->role != 'employee' && !isset($this->params['filter_users'])){
        //     $this->initial_basicparams('users');
        //     $fusers = array_keys($this->filterdata);
        //     $this->params['filter_users'] = array_shift($fusers);
        // }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
    }
    function count() {
        $this->sql  = "SELECT COUNT(bi.id) ";
    }

    function select() {
        $this->sql = "SELECT bi.id, b.courseid, bi.userid, b.id as badgeid, c.fullname ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('name', $this->selectedcolumns)) {
                $this->sql .= ", b.name AS name";
            }
            if (in_array('issuername', $this->selectedcolumns)) {
                $this->sql .= ", b.issuername AS issuername";
            }
            if (in_array('timecreated', $this->selectedcolumns)) {
                $this->sql .= ", b.timecreated AS timecreated";
            }
            if (in_array('dateissued', $this->selectedcolumns)) {
                $this->sql .= ", bi.dateissued AS dateissued";
            }
            if (in_array('description', $this->selectedcolumns)) {
                $this->sql .= ", b.description AS description";
            }
            if (in_array('expiredate', $this->selectedcolumns)) {
                $this->sql .= ", b.expiredate AS expiredate";
            }
        }
    }

    function from() {
        $this->sql .= " FROM {badge_issued} as bi";
    }

    function joins() {
        parent::joins();
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;

        $this->sql .="  JOIN {badge} as b ON b.id = bi.badgeid
                        LEFT JOIN {course} as c ON b.courseid = c.id AND c.visible = 1
                        JOIN {enrol} e ON e.courseid = c.id AND e.status = 0";

        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND b.courseid IN ($this->rolewisecourses) ";
            } 
        }
    }

    function where() { 
        global $DB, $USER;
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->sql .=" WHERE  bi.visible = 1 AND b.status != 0 AND b.status != 2 AND b.status != 4
                        AND bi.userid = $userid ";
        if($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND bi.dateissued BETWEEN $this->ls_startdate AND $this->ls_enddate ";
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
        if ($this->loggedinuserrole != 'dh') {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
                $this->sql .= " ";
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
                $this->sql .= " AND c.open_costcenterid = :costcenterid ";
                $this->params['costcenterid'] = $USER->open_costcenterid;
            }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
                $this->sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid ";
                $this->params['costcenterid'] = $USER->open_costcenterid;
                $this->params['departmentid'] = $USER->open_departmentid;
            } else if($this->loggedinuserrole != 'user') { 
                $this->sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid AND c.open_subdepartment";
                $this->params['costcenterid'] = $USER->open_costcenterid;
                $this->params['departmentid'] = $USER->open_departmentid;
                $this->params['subdepartment'] = $USER->open_subdepartment;
            } 
        }
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('b.name','c.fullname');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND b.courseid IN ($this->rolewisecourses) ";
            }
        }
    }

    function filters() {
        if (!empty($this->params['filter_course']) && $this->params['filter_course'] <> SITEID  && !$this->scheduling) {
            $this->params['courseid'] = $this->params['filter_course'];
            $this->sql .= " AND b.courseid = :courseid";
        } 
        if (!empty($this->params['filter_organization'])) {
            $costcenterids = $this->params['filter_organization'];
            $this->sql .= " AND c.open_costcenterid IN ($costcenterids) ";
        }
        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
            $departmentids = $this->params['filter_departments'];
            $this->sql .= " AND c.open_departmentid IN ($departmentids) ";
        }
        if (!empty($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) {
            $subdepartmentids = $this->params['filter_subdepartments'];
            $this->sql .= " AND c.open_subdepartment IN ($subdepartmentids) ";
        }
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $this->sql .= " AND c.open_contentvendor IN ($contentproviderids) ";
        }

        $learningtype = isset($this->params['filter_learningtype']) ? implode(',', $this->params['filter_learningtype']) : 0; 
        $certification = isset($this->params['filter_certification']) ? implode(',', $this->params['filter_certification']) : 0;
        $certificationlevel = isset($this->params['filter_certificationlevel']) ? implode(',', $this->params['filter_certificationlevel']) : 0;
        $exam = isset($this->params['filter_exam']) ? implode(',', $this->params['filter_exam']) : 0;
        $solutionarea = isset($this->params['filter_solutionarea']) ? implode(',', $this->params['filter_solutionarea']) : 0;
        $technology = isset($this->params['filter_technology']) ? implode(',', $this->params['filter_technology']) : 0;
        $topic = isset($this->params['filter_topic']) ? implode(',', $this->params['filter_topic']) : 0;
        $vendor = isset($this->params['filter_vendor']) ? implode(',', $this->params['filter_vendor']) : 0;
        $level = isset($this->params['filter_level']) ? implode(',', $this->params['filter_level']) : 0;
        $language = isset($this->params['filter_language']) ? implode(',', $this->params['filter_language']) : 0;
        $jobrole = isset($this->params['filter_jobrole']) ? implode(',', $this->params['filter_jobrole']) : 0;

        $tagslist = array($learningtype, $certification, $certificationlevel, $exam, $solutionarea, $technology, $topic, $vendor, $level, $language, $jobrole); 
        if (array_sum($tagslist) > 0) {
            $tagslist = implode(',', $tagslist); 
            $tagcoursesql  = (new querylib)->gettagcourses($tagslist);
            if (!empty($tagcoursesql) && $tagcoursesql > 0) { 
                $this->sql .= " AND c.id IN (".$tagcoursesql.")";
            } else {
                $this->sql .= " AND c.id IN (0)";
            } 
        }
        
    }

    public function get_rows($badges) {
        global $DB, $CFG, $PAGE, $OUTPUT, $USER;
        $context = context_system::instance();
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $data = array();
        if (!empty($badges) && !empty($this->selectedcolumns)) {
            foreach ($badges as $badge) {
                $batchinstance = new badge($badge->badgeid);
                $context = $batchinstance->get_context();
                $badgeimage = print_badge_image($batchinstance, $context);
                $get_criteria = $PAGE->get_renderer('core_badges');
                $criteria = $get_criteria->print_badge_criteria($batchinstance);
                $courserecord = $DB->get_record('course',array('id'=>$badge->courseid));
                $completion_info = new completion_info($courserecord);
                $activityinforeport = new stdClass();
                $params = array();
                $params['userid'] = $userid;
                if ($this->ls_startdate >= 0 && $this->ls_enddate) {
                    $datefiltersql = " AND gg.timemodified BETWEEN :startdate AND :enddate ";
                    $datesql = " AND timemodified BETWEEN :startdate AND :enddate ";
                    $params['startdate'] = $this->ls_startdate;
                    $params['enddate'] = $this->ls_enddate;
                }
                $activityinforeport->name = '<a href="'. $CFG->wwwroot. '/badges/overview.php?id='.$badge->badgeid.'" target="_blank" class="edit">'.$badgeimage.'  '.$badge->name.'</a>';
                $activityinforeport->issuername = $badge->issuername;
                if($badge->courseid = NULL || empty($badge->courseid)){
                    $activityinforeport->coursename = $courserecord->fullname ? $courserecord->fullname : 'System';
                }else{
                    $reportid = $DB->get_field('block_learnerscript', 'id', array('type'=> 'courseprofile'), IGNORE_MULTIPLE);
                    $permissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
                    if(empty($reportid) || empty($permissions)){
                        $activityinforeport->coursename = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$courserecord->id.'" target="_blank" class="edit">'.($courserecord->fullname ? $courserecord->fullname : 'System').'</a>';
                    }else{
                        $activityinforeport->coursename = '<a href="'.$CFG->wwwroot.'/blocks/learnerscript/viewreport.php?id='.$reportid.'&filter_course='.$courserecord->id.'" target="_blank" class="edit">'.($courserecord->fullname ? $courserecord->fullname : 'System').'</a>';
                    }
                }
                $activityinforeport->timecreated = date('l, d F Y H:i A',$badge->timecreated);
                $activityinforeport->dateissued = date('l, d F Y H:i A',$badge->dateissued);
                if(!empty($badge->expiredate)){
                $activityinforeport->expiredate = date('l, d F Y H:i A',$badge->expiredate);
                }else{
                $activityinforeport->expiredate = "--";
                }
                $activityinforeport->description =$badge->description;
                $activityinforeport->criteria =$criteria;
                // $activityinforeport->recipients = '<a href="'. $CFG->wwwroot. '/badges/recipients.php?id='.$badge->badgeid.'" target="_blank" class="edit">'.$recipients.'</a>';
                $data[] = $activityinforeport;
            }
        }
        return $data;
    }
}
