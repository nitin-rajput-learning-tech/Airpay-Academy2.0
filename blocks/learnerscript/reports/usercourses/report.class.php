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
 * LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2017
 */

use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_usercourses extends reportbase implements report {

    private $relatedctxsql;
    private $datefiltersql;

    private $relatedctxparams;

    public function __construct($report, $reportproperties) {
        parent::__construct($report);
		$this->components = array('columns','ordering', 'filters', 'permissions', 'calcs', 'plot');
	 	$columns = ['timeenrolled', 'status','grade','totaltimespent', 'progressbar','completedassignments','completedquizzes', 'completedscorms', 'marks', 'badgesissued', 'completedactivities'];
        $this->columns = ['userfield'=>['userfield'],'usercoursescolumns' => $columns];
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
   		$this->parent = true;
   		$this->courselevel = true;
		$this->filters = array('contentprovider', 'course', 'user', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole', 'country');
		$this->orderable = array('fullname', 'timeenrolled', 'completedassignments', 'completedquizzes', 'completedscorms', 'completedactivities', 'marks', 'grade', 'badgesissued', 'totaltimespent');
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = array("'employee'");
	}
    function init() {
        global $DB;
        // if (!isset($this->params['filter_course'])) {
        //     $this->initial_basicparams('courses');
        //     $fcourses = array_keys($this->filterdata);
        //     $this->params['filter_course'] =array_shift($fcourses);
        // }
        $this->courseid = isset($this->params['filter_course']) && $this->params['filter_course'] > 0? $this->params['filter_course'] : SITEID; 
        $this->categoriesid = isset($this->params['filter_coursecategories']) ? $this->params['filter_coursecategories'] : 0; 
        $context = context_course::instance($this->courseid);
        list($this->relatedctxsql, $this->relatedctxparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED,'relatedctx');
        
        $this->params['contextlevel'] = CONTEXT_COURSE;
        $this->params['userid'] = $this->userid;
        $this->params['ej1_active'] = ENROL_USER_ACTIVE;
        $this->params['ej1_enabled'] = ENROL_INSTANCE_ENABLED;
        $this->params['ej1_now1'] = round(time(), -2); // improves db caching
        $this->params['ej1_now2'] = $this->params['ej1_now1'];
        $this->params['ej1_courseid'] = $this->courseid;
        $this->params['courseid'] = $this->courseid;
        $this->params['courseid1'] = $this->courseid;
        $this->params['roleid'] = $DB->get_field('role', 'id', array('shortname' => 'employee'));
        $this->params = array_merge($this->relatedctxparams, $this->params);

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
         $this->sql = "SELECT COUNT(DISTINCT u.id)";
    }

    function select() {
        $this->sql = "SELECT DISTINCT u.id AS userid,CONCAT(u.firstname,' ',u.lastname) AS fullname, u.email,  cc.timestarted AS timestarted,
                              u.timezone,cc.timecompleted AS timecompleted, $this->courseid AS courseid ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('timeenrolled', $this->selectedcolumns)) {
                $this->sql .= ", e.timecreated AS timeenrolled";
            }
        }
        parent::select();
    }

    function from() {
        $this->sql .= " FROM {user} u";
    }

    function joins() {
        $this->sql .=" JOIN {user_enrolments} ue ON ue.userid = u.id
                     JOIN {enrol} e ON e.id = ue.enrolid 
                     JOIN {role_assignments} ra ON ra.userid = ue.userid
                     JOIN {context} ct ON ct.id = ra.contextid
                     JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
                 LEFT JOIN {course_completions} as cc ON cc.userid = u.id AND cc.course = $this->courseid 
                 LEFT JOIN {course} c ON c.id = cc.course ";

        parent::joins();
    }

    function where() { 
        global $DB, $USER;
        $status = isset($this->params['filter_status']) ? $this->params['filter_status'] : '';
        $this->sql .= " WHERE u.id IN (SELECT ra.userid
                                         FROM {role_assignments} ra
                                        WHERE ra.roleid = :roleid AND ra.contextid $this->relatedctxsql $this->datefiltersql)
                                          AND u.id > 2 AND u.confirmed = 1 AND u.deleted = 0";
        if ($status == 'completed') {
            $this->sql .= " AND u.id IN (SELECT userid FROM {course_completions}
                                    WHERE course=$this->courseid AND timecompleted IS NOT NULL)";
        } 
        // $systemcontext = context_system::instance();
        // if (!is_siteadmin()) {
        //     $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
        //     if (!empty($scheduledreport)) {
        //     $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
        //     $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
        //     $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
        //     } else {
        //         $ohs = $dhs = 1;
        //     }
        // } 
        // if ($this->loggedinuserrole != 'dh') {
        //     if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
        //         $this->sql .= " ";
        //     }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
        //         $this->sql .= " AND u.open_costcenterid = :costcenterid ";
        //         $this->params['costcenterid'] = $USER->open_costcenterid;
        //     }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
        //         $this->sql .= " AND u.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid ";
        //         $this->params['costcenterid'] = $USER->open_costcenterid;
        //         $this->params['departmentid'] = $USER->open_departmentid;
        //     } else { 
        //         $this->sql .= " AND u.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid AND u.open_subdepartment =:subdepartment";
        //         $this->params['costcenterid'] = $USER->open_costcenterid;
        //         $this->params['departmentid'] = $USER->open_departmentid;
        //         $this->params['subdepartment'] = $USER->open_subdepartment;
        //     } 
        // } else {
        //     $this->sql .= " AND c.id IN ($this->courseslist)";
        // }
      $categorycontext =  (new \local_users\lib\accesslib())::get_module_context();
      $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path'); 
      if (is_siteadmin()) {
          $this->sql .= "";
      } else  {
          $this->sql .= $costcenterpathconcatsql;
      }
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ', u.lastname)", "u.email");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        if(isset($this->params['filter_users']) && $this->params['filter_users'] > 0) {
            $this->sql .= " AND u.id = :filter_users";
            $this->params['filter_users'] = $this->params['filter_users'];
        } 
        if (!empty($this->params['filter_organization'])) {
            $organization = $this->params['filter_organization'];
            $filter_organization[] = " concat('/',u.open_path,'/') LIKE :organizationparam_{$organization}";
            $this->params["organizationparam_{$organization}"] = '%/'.$organization.'/%';
            $this->sql .= " AND ( ".implode(' OR ', $filter_organization)." ) ";
        }
        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
            $department = $this->params['filter_departments'];
            $filter_department[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
            $this->params["departmentparam_{$department}"] = '%/'.$department.'/%';
            $this->sql .= " AND ( ".implode(' OR ', $filter_department)." ) ";
        }
        if (!empty($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) {
            $subdepartments = $this->params['filter_subdepartments'];
            $filter_subdepartments[] = " concat('/',u.open_path,'/') LIKE :subdepartmentsparam_{$subdepartments}";
            $this->params["subdepartmentsparam_{$subdepartments}"] = '%/'.$subdepartments.'/%';
            $this->sql .= " AND ( ".implode(' OR ', $filter_subdepartments)." ) ";
        }  
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $this->sql .= " AND c.open_contentvendor IN ($contentproviderids) ";
        }
        if (!empty($this->params['filter_country'])) {
            $countryval = isset($this->params['filter_country']) ? implode(',', $this->params['filter_country']) : 0; 
            $this->sql .= ' AND u.country IN ("' . implode('", "', $this->params['filter_country']) . '") ';
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
        if ($this->ls_startdate > 0 && $this->ls_enddate) {
            $this->sql .= " AND u.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
    }

	public function get_rows($users) {
		return $users;
	}

    public function column_queries($columnname, $usercourseid, $courseid = null) {

        $where = " AND %placeholder% = $usercourseid";
        $filtercourseid = isset($this->params['filter_course']) ? $this->params['filter_course'] : SITEID;

        switch ($columnname) {
            case 'grade':
                $identy = 'gg.userid';
                $query = "SELECT ROUND(((gg.finalgrade/gi.grademax)*100),2) AS grade
                                        FROM {grade_items} gi
                                        JOIN {grade_grades} gg ON gg.itemid = gi.id AND gi.itemtype = 'course'
                                        WHERE gi.courseid = $filtercourseid $where ";
                
            break;
            case 'totaltimespent':
                $identy = 'bt.userid';
                $query = "SELECT SUM(bt.timespent) AS totaltimespent FROM {block_ls_coursetimestats} AS bt WHERE bt.courseid = $filtercourseid $where ";
            break;
            case 'completedassignments':
                $identy = 'cmc.userid';
                $query = "SELECT COUNT(cm.id) AS completedassignments
                                        FROM {course_modules} AS cm
                                        JOIN {modules} AS m ON m.id = cm.module
                                        JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id
                                       WHERE m.name = 'assign' AND cm.visible = 1 AND cm.deletioninprogress = 0
                                         AND cm.course = $filtercourseid AND cmc.completionstate != 0 $where";  
            break;
            case 'completedquizzes': 
                $identy = 'cmc.userid';     
                $query = "SELECT COUNT(cm.id) AS completedquizzes
                                        FROM {course_modules} AS cm
                                        JOIN {modules} AS m ON m.id = cm.module
                                        JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id
                                       WHERE m.name = 'quiz' AND cm.visible = 1 AND cm.deletioninprogress = 0
                                         AND cm.course = $filtercourseid AND cmc.completionstate != 0 $where";
            break;
            case 'completedscorms':
                $identy = 'cmc.userid';
                $query = "SELECT COUNT(cm.id) AS completedscorms
                                        FROM {course_modules} AS cm
                                        JOIN {modules} AS m ON m.id = cm.module
                                        JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id
                                       WHERE m.name = 'scorm' AND cm.visible = 1 AND cm.deletioninprogress = 0
                                        AND cm.course = $filtercourseid AND cmc.completionstate != 0 $where";
            break;
            case 'marks':
                $identy = 'gg.userid';
                $query = "SELECT ROUND(gg.finalgrade, 2) AS marks FROM {grade_items} gi
                                         JOIN {grade_grades} gg ON gg.itemid = gi.id AND gi.itemtype = 'course'
                                        WHERE gi.courseid = $filtercourseid $where";
            break;
            case 'badgesissued':
                $identy = 'bi.userid';
                $query = "SELECT COUNT(bi.id) AS badgesissued FROM {badge_issued} as bi
                                        JOIN {badge} as b ON b.id = bi.badgeid
                                       WHERE  bi.visible = 1 AND b.status != 0
                                         AND b.status != 2 AND b.courseid = $filtercourseid $where";
            break;
            case 'completedactivities':
                $identy = 'cmc.userid';
                $query = "SELECT COUNT(cm.id) AS completedactivities
                                        FROM {course_modules} AS cm
                                        JOIN {modules} AS m ON m.id = cm.module
                                        JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id
                                       WHERE  cm.visible = 1 AND cm.deletioninprogress = 0 AND cm.course = $filtercourseid
                                         AND cmc.completionstate != 0 $where";
            break;
            default:
                return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
