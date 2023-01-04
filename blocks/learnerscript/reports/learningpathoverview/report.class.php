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

class report_learningpathoverview extends reportbase implements report {
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
        $columns = ['fullname','firstname','lastname','email','completedcourses', 'missingcourses', 'timespent'];
        $this->columns = ['userfield' => array('userfield'), 'learningpathoverview' => $columns];
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments'], ['name' => 'learningpath']); 
        $this->filters = array('usergroup');
        $this->orderable = array('');
        $this->defaultcolumn = 'u.id';
        $this->excludedroles = array("'student'");
    }
    function init() {
        global $DB;
        /*if(!isset($this->params['filter_courses'])){
            $this->initial_basicparams('courses');
            $coursefilter = array_keys($this->filterdata);
            $this->params['filter_courses'] = array_shift($coursefilter);
        }*/
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
        $courseid = $this->params['filter_course'];
        $this->sql = "SELECT DISTINCT u.id, u.id AS userid,CONCAT(u.firstname, ' ', u.lastname) AS fullname,username,firstname,lastname,email ";
        parent::select();
    }

    function from() {
        $this->sql .= " FROM {user} u ";
    }

    function joins() {
        $this->sql .= " JOIN {local_learningplan_user} lpu ON lpu.userid = u.id 
                        JOIN {local_learningplan} lp ON lp.id = lpu.planid";
        parent::joins();
    }

    function where() {
        global $DB, $USER;
        $userid = isset($this->params['filter_users']) ? $this->params['filter_users'] : array();
        $this->sql .= " WHERE 1 = 1 ";

        $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context(); //context_system::instance();
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
            $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path'); 
                if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
                    $this->sql .= "";
                } else  {
                    $this->sql .= $costcenterpathconcatsql;
                }
                if ($this->params['filter_organization']>0) {
                    $filter_organization = $this->params['filter_organization'];
                    $organizationsql[] = " concat('/',lp.open_path,'/') LIKE :organization{$filter_organization}";
                    $this->params["organization{$filter_organization}"] = '%/'.$filter_organization.'/%';
                    $this->sql .= " AND ( ".implode(' OR ', $organizationsql)." ) ";
                }

                if ($this->params['filter_departments']>0) {
                    $filter_departments = $this->params['filter_departments'];
                    $departmentsql[] = " concat('/',lp.open_path,'/') LIKE :department{$filter_departments}";
                    $this->params["department{$filter_departments}"] = '%/'.$filter_departments.'/%';
                    $this->sql .= " AND ( ".implode(' OR ', $departmentsql)." ) ";
                }


            // if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)){ 
            //     if ($this->params['filter_organization']>0) {
            //         $this->sql .= " AND lp.costcenter = " .$this->params['filter_organization'];
            //     }
            //     if ($this->params['filter_departments'] > 0) {
            //         $this->sql .= " AND lp.department = ".$this->params['filter_departments'];
            //     }
            // } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $categorycontext) && $ohs) { 
            //     $this->sql .= " AND lp.costcenter = " .$USER->open_costcenterid; 
            //     if ($this->params['filter_departments'] > 0) {
            //         $this->sql .= " AND lp.department = ".$this->params['filter_departments'];
            //     }
            // }else if(has_capability('local/costcenter:manage_owndepartments', $categorycontext) && $dhs) { 
            //     $this->sql .= " AND lp.costcenter = " .$USER->open_costcenterid . " AND lp.department = ". $USER->open_departmentid ;
            // } else {
            //     $this->sql .= " AND lp.costcenter = " .$USER->open_costcenterid . " AND lp.department = ". $USER->open_departmentid . " AND lp.subdepartment = " .$USER->open_subdepartment;
            // }
            // if ($this->params['filter_subdepartments'] > 0) {
            //     $this->sql .= " AND lp.subdepartment = ".$this->params['filter_subdepartments'];
            // } 
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
        
        if ($this->params['filter_course'] > SITEID) {
            $courseid = $this->params['filter_course'];
            $this->sql .= " AND ccom.courseid IN ($courseid)";
        }
        if ($this->params['filter_learningpath'] > 0) {
            $lpid = $this->params['filter_learningpath'];
            $this->sql .= " AND lp.id IN ($lpid)";
        }
        if ($this->params['filter_usergroup'] > 0) {
            $cohortid = $this->params['filter_usergroup'];
            $this->sql .= " AND u.id IN (SELECT DISTINCT cm.userid FROM {cohort_members} cm WHERE cm.cohortid = $cohortid)";
        }
        // if (!empty($this->params['filter_organization'])) {
        //     $costcenterids = $this->params['filter_organization'];
        //     $this->sql .= " AND c.open_costcenterid IN ($costcenterids) ";
        // }
        // if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) { 
        //     $departmentids = $this->params['filter_departments'];
        //     $this->sql .= " AND c.open_departmentid IN ($departmentids) ";
        // } 
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
        // echo $this->sql;exit;
        // if ($this->ls_startdate > 0 && $this->ls_enddate) {
        //     $this->sql .= " AND ra.timemodified BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        // }
    }
    /**
     * [get_rows description]
     * @param  array  $users [description]
     * @return [type]        [description]
     */
    public function get_rows($users = array()) {
        return $users;
    }
}
