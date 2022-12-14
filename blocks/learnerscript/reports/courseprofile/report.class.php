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
 * LearnerScript
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: Arun Kumar M
 * @date: 2017
 */
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;
defined('MOODLE_INTERNAL') || die();
class report_courseprofile extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        global $DB;
        parent::__construct($report, $reportproperties);
        $coursecolumns = $DB->get_columns('course');
        $usercolumns = $DB->get_columns('user');
        $columns = ['enrolments', 'completed', 'activities', 'progress', 'avggrade',
                    'enrolmethods', 'highgrade', 'lowgrade', 'badges', 'totaltimespent'];
        $this->columns = ['coursefield' => ['coursefield'] ,
                          'coursescolumns' => $columns];
        $this->conditions = ['courses' => array_keys($coursecolumns),
                             'user' => array_keys($usercolumns)];
        $this->components = array('columns', 'conditions', 'ordering', 'filters',
                                    'permissions', 'plot');
        $this->courselevel = true; 
        if ($this->loggedinuserrole != 'dh') {
            $this->basicparams = [['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments'], ['name' => 'course', 'singleselection' => false, 'placeholder' => false,
                                    'maxlength' => 5]]; 
        } else {
            $this->basicparams = [['name' => 'subdepartments'],['name' => 'course', 'singleselection' => false, 'placeholder' => false, 'maxlength' => 5]];
        } 
        // $this->filters = array('contentprovider', 'learningtype', 'certification', 'certificationlevel', 'exam', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');
        $this->parent = true;
        $this->exports = false;
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = array("'employee'");
    }
    public function init() {
        //  if (!isset($this->params['filter_course'])) {
        //     $this->initial_basicparams('courses');
        //     $fcourse = array_keys($this->filterdata);
        //     $this->params['filter_course'] = array_shift($fcourse);
        // }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
       $this->categoriesid = isset($this->params['filter_coursecategories']) ? $this->params['filter_coursecategories'] : 0;  
    }
    public function count() {
       $this->sql = "SELECT count(main.id)";

    }

    public function select() {
      $this->sql = "SELECT main.id, main.id AS courseid, main.fullname AS fullname, main.id AS course ";
      parent::select();
    }

    public function from() {
      $this->sql .= " FROM {course} as main JOIN {course_categories} as cat ON main.category = cat.id";
    }
    public function joins() { 
        parent::joins();
    }

    public function where() { 
        $this->sql .= " WHERE main.visible = 1 AND main.id <> :siteid ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND main.id IN ($this->rolewisecourses) ";
            } 
        } 

        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND main.id IN ($this->rolewisecourses) ";
            } 
        }
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('main.fullname');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() { 
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
 
        if (!$this->scheduling) {
            if ($this->loggedinuserrole != 'dh') {
                if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
                    $coursesql  = (new querylib)->getcourseslist($this->params['filter_organization'], $this->params['filter_departments'],$this->params['filter_subdepartments']);
                }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){ 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $this->params['filter_departments']);
                }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){ 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $this->params['filter_departments'],$this->params['filter_subdepartments']);
                } else { 
                    $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $USER->open_departmentid,$USER->open_subdepartment); 
                } 
                if (!empty($coursesql)) { 
                    $this->sql .= " AND main.id IN (".$coursesql.")";
                } else {
                    $this->sql .= " AND main.id IN (0)";
                }
            } else {
                $this->sql .= " AND main.id IN ($this->courseslist)";
            } 
        } else {
            $coursesql  = (new querylib)->getcourseslist($this->params['filter_organization'], $this->params['filter_departments'],$this->params['filter_subdepartments']); 
            if (!empty($coursesql)) { 
                $this->sql .= " AND main.id IN (".$coursesql.")";
            } else {
                $this->sql .= " AND main.id IN (0)";
            }
        }

        $courseids = isset($this->params['filter_course']) &&
                        $this->params['filter_course'] > 0 ? $this->params['filter_course'] : array();
        if (empty($courseids)) {
            return array(array(), 0);
        }
        if (is_array($courseids)) {
            $courseids = implode(',', $courseids);
        }
        if (!empty($this->params['filter_course']) && $this->params['filter_course'] <> SITEID  && !$this->scheduling) {
            $this->sql .= " AND main.id IN ($courseids) ";
        } 
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND main.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
    }
    public function get_rows($courses) {
        return $courses;
    }
    public function column_queries($columnname, $courseid, $courses = null) { 
        global $DB, $USER;
        if($courses){
            $learnersql  = (new querylib)->get_learners('', $courses);
        }else{
            $learnersql  = (new querylib)->get_learners('', '%courseid%');
        }
        $where = " AND %placeholder% = $courseid"; 
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
        $concatsql = " ";
        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
            $concatsql .= " ";
        } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {
            $concatsql .= " AND u.open_costcenterid = " . $USER->open_costcenterid;
        }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) {
           $concatsql .= " AND u.open_costcenterid = " . $USER->open_costcenterid . " AND u.open_departmentid = " . $USER->open_departmentid;
        } else {
            $concatsql .= " AND u.open_costcenterid = " . $USER->open_costcenterid . " AND u.open_departmentid = " . $USER->open_departmentid ." AND u.open_subdepartment = " .$USER->open_subdepartment;;
        } 
        $departmentsql = " "; 
        if (!empty($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) {
            $departmentsql .= " AND u.open_subdepartment = " . $this->params['filter_subdepartments'];
        }
        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
            $departmentsql .= " AND u.open_departmentid = " . $this->params['filter_departments'];
        } 
        if (!empty($this->params['filter_organization'])) {
            $costcenterids = $this->params['filter_organization'];
            $departmentsql .= " AND u.open_costcenterid IN ($costcenterids) ";
        }
        switch ($columnname) {
            case 'progress':
                $identy = 'e.courseid';
                $query  = "SELECT ROUND((c1.completed / c2.enrolled) * 100, 2) AS progress FROM ((SELECT COUNT(DISTINCT(ue.id)) AS completed
                        FROM {user_enrolments} ue
                        JOIN {user} u ON ue.userid = u.id 
                        JOIN {enrol} e ON e.id = ue.enrolid
                        JOIN {role_assignments} ra ON ra.userid = ue.userid
                        JOIN {context} cxt ON cxt.id = ra.contextid
                        JOIN {role} r ON r.id = ra.roleid
                        JOIN {course_completions} cc ON e.courseid = cc.course 
                            AND cc.userid = u.id AND cc.timecompleted IS NOT NULL 
                        JOIN {course} c ON c.id = e.courseid AND c.id = cxt.instanceid
                        WHERE u.deleted = 0 
                            AND u.suspended = 0 AND r.shortname = 'employee' $concatsql $departmentsql AND cc.course = e.courseid $where ) 
                          AS c1,
                       (SELECT COUNT(DISTINCT(ue.id)) AS enrolled
                        FROM {user_enrolments} ue
                        JOIN {user} u ON ue.userid = u.id 
                        JOIN {enrol} e ON e.id = ue.enrolid
                        JOIN {role_assignments} ra ON ra.userid = ue.userid
                        JOIN {context} cxt ON cxt.id = ra.contextid
                        JOIN {role} r ON r.id = ra.roleid 
                        JOIN {course} c ON c.id = e.courseid AND c.id = cxt.instanceid    
                        WHERE u.deleted = 0 
                            AND u.suspended = 0 AND r.shortname = 'employee'  $concatsql $departmentsql $where ) AS c2 )";
                break;
            case 'activities':
                $identy = 'course';
                $query  = "SELECT COUNT(id) as activities  FROM {course_modules} where 1=1 AND visible = 1 $where ";
            break;
            case 'enrolments':
                $identy = 'ct.instanceid';
                $query  = "SELECT COUNT(DISTINCT ue.userid) AS enrolled 
                                     FROM {user_enrolments} ue
                                     JOIN {enrol} e ON e.id = ue.enrolid 
                                     JOIN {role_assignments} ra ON ra.userid = ue.userid
                                     JOIN {context} ct ON ct.id = ra.contextid
                                     JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
                                     JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
                                     JOIN {course} c ON c.id = e.courseid AND c.id = ct.instanceid
                                    WHERE 1 = 1 $concatsql $departmentsql $where ";
            break;
            case 'completed':
                $identy = 'ct.instanceid';
                $query ="SELECT COUNT(DISTINCT cc.userid) AS completed 
                                     FROM {user_enrolments} ue
                                     JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0 AND ue.status = 0
                                     JOIN {role_assignments} ra ON ra.userid = ue.userid
                                     JOIN {context} ct ON ct.id = ra.contextid
                                     JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname = 'employee'
                                     JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0
                                     JOIN {course_completions} as cc ON cc.course = ct.instanceid AND cc.timecompleted > 0 AND cc.userid = ue.userid 
                                     JOIN {course} c ON c.id = e.courseid AND c.id = ct.instanceid
                                    WHERE 1 = 1 $concatsql $departmentsql AND cc.course = e.courseid $where "; 
            break;
            case 'enrolmethods':
                $identy = 'courseid';
                $query = "SELECT COUNT(id) AS enrolmethods FROM {enrol} WHERE status = 0 $where ";
            break;
            case 'highgrade':
                $identy = 'gi.courseid';
                $query = "SELECT  ROUND(MAX(finalgrade),2) as highgrade 
                          FROM {grade_grades} g  
                          JOIN {grade_items} gi ON gi.itemtype = 'course' AND g.itemid = gi.id 
                         WHERE g.finalgrade IS NOT NULL $where ";
            break;
            case 'lowgrade':
                $identy = 'gi.courseid';
                $query = "SELECT  ROUND(MIN(finalgrade),2) as lowgrade 
                          FROM {grade_grades} g  
                          JOIN {grade_items} gi ON gi.itemtype = 'course' AND g.itemid = gi.id 
                         WHERE g.finalgrade IS NOT NULL $where ";
            break;
            case 'avggrade':
                $identy = 'gi.courseid';
                $query = "SELECT  ROUND(AVG(finalgrade),2) as avggrade 
                          FROM {grade_grades} g 
                          JOIN {grade_items} gi ON gi.itemtype = 'course' AND g.itemid = gi.id 
                         WHERE g.finalgrade IS NOT NULL $where ";
            break;
            case 'badges':
                $identy = 'b.courseid';
                $query = "SELECT COUNT(b.id) AS badges  FROM {badge} b WHERE b.status != 0  AND b.status != 2 $where ";
            break;
            case 'totaltimespent':
                $identy = 'bt.courseid';
                $courses = 'bt.courseid';
                $query = "SELECT SUM(bt.timespent) AS totaltimespent  from {block_ls_coursetimestats} AS bt 
                           WHERE 1 = 1 AND bt.userid IN ($learnersql) $where ";
            break;

            default:
            return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        $query = str_replace('%courseid%', $courses, $query);
        return $query;
    }
    public function create_report($blockinstanceid = null) {
        global $DB, $CFG;
        $components = (new ls)->cr_unserialize($this->config->components);
        $courseids = isset($this->params['filter_course']) &&
                        $this->params['filter_course'] > 0 ? $this->params['filter_course'] : SITEID;

        $conditions = (isset($components['conditions']['elements'])) ? $components['conditions']['elements'] : array();
        $filters = (isset($components['filters']['elements'])) ? $components['filters']['elements'] : array();
        $columns = (isset($components['columns']['elements'])) ? $components['columns']['elements'] : array();
        $ordering = (isset($components['ordering']['elements'])) ? $components['ordering']['elements'] : array();
        $columnnames  = array();

        foreach ($columns as $key => $column) {
            if (isset($column['formdata']->column)) {
                $columnnames[$column['formdata']->column] = $column['formdata']->columname;
                $this->selectedcolumns[] = $column['formdata']->column;
            }
        } 
        $finalelements = array();
        $sqlorder = '';
        $orderingdata = array();

        if ($this->ordercolumn) {
            $this->sqlorder = $this->selectedcolumns[$this->ordercolumn['column']] . " " . $this->ordercolumn['dir'];
        } else if (!empty($ordering)) {
            foreach ($ordering as $o) {
                require_once($CFG->dirroot.'/blocks/learnerscript/components/ordering/'.$o['pluginname'].'/
                                plugin.class.php');
                $classname = 'plugin_'.$o['pluginname'];
                $classorder = new $classname($this->config);
                if ($classorder->sql) {
                    $orderingdata = $o['formdata'];
                    $this->sqlorder = $classorder->execute($orderingdata);
                }
            }
        }
        $conditionfinalelements = array();
        if (!empty($conditions)) {
            $this->conditionsenabled = true;
            $conditionfinalelements = $this->elements_by_conditions($components['conditions']);
        }
        $this->params['siteid'] = SITEID;
        $this->build_query(true);

        try {
            $this->totalrecords = $DB->count_records_sql($this->sql, $this->params);
        } catch (dml_exception $e) {
            $this->totalrecords = 0;
        }

        $this->build_query();
        $this->sql .= " GROUP by main.id ";
        if (is_array($this->sqlorder) && !empty($this->sqlorder)) {
            $this->sql .= " ORDER BY ". $this->sqlorder['column'] .' '. $this->sqlorder['dir'];
        } else {
            if (!empty($sqlorder)) {
                $this->sql .= " ORDER BY main.$sqlorder ";
            } else {
                $this->sql .= " ORDER BY main.id DESC ";
            }
        }
        if(is_siteadmin($this->userid) || (new ls)->is_manager($this->userid, $this->contextlevel, $this->role)){
            $finalelements = $this->get_all_elements();
            $rows = $this->get_rows($finalelements);
        }else{
            if($this->rolewisecourses != ''){
                $finalelements = $this->get_all_elements();
                $rows = $this->get_rows($finalelements);
            }else{
                $finalelements = $this->get_all_elements();
                $rows = $this->get_rows($finalelements);
            }
        } 
        // print_object($rows);
        $rows = $this->get_rows($finalelements);
        $reporttable = array();
        $tablehead = array();
        $tablealign = array();
        $tablesize = array();
        $tablewrap = array();
        $firstrow = true;
        $pluginscache = array();

        if ($this->config->type == "topic_wise_performance") {
            $columns = (new ls)->learnerscript_sections_dynamic_columns($columns, $this->config, $this->params);
        } 
        if ($rows) {
            $tempcols = array(); 

            foreach ($rows as $r) { 

                foreach ($columns as $c) { 
                    // print_object($columns);exit;
                    $c = (array) $c;
                    if (empty($c)) {
                        continue;
                    }
                    require_once($CFG->dirroot . '/blocks/learnerscript/components/columns/' . $c['pluginname'] . '/plugin.class.php');
                    $classname = 'plugin_' . $c['pluginname'];

                    if (!isset($pluginscache[$classname])) {
                        $class = new $classname($this->config, $c);
                        $pluginscache[$classname] = $class;
                    } else {
                        $class = $pluginscache[$classname];
                    }
                    $class->reportfilterparams = $this->params;
                    if (isset($c['formdata']->column)) {
                        if (!empty($this->params['filter_users'])) {
                            $userrecord = $DB->get_record('user', array('id' => $this->params['filter_users']));
                            $this->currentuser = $userrecord;
                        }
                        if(method_exists($this, 'column_queries')){
                            if(isset($r->course)){
                                $c['formdata']->subquery = $this->column_queries($c['formdata']->column, $r->id, $r->course);
                                $this->currentcourseid = $r->course;
                            }else if(isset($r->user)){
                                $c['formdata']->subquery = $this->column_queries($c['formdata']->column, $r->id, $r->user);
                            }else{
                                $c['formdata']->subquery = $this->column_queries($c['formdata']->column, $r->id);
                            }
                        }
                        $tempcols[$c['formdata']->columname][] = $class->execute($c['formdata'], $r,
                                                                            $this->currentuser,
                                                                            $this->currentcourseid,
                                                                            $this->starttime,
                                                                            $this->endtime);
                    }

                    if ($firstrow) {
                        if (isset($c['formdata']->column)) {
                            $columnheading = !empty($c['formdata']->columname) ? $c['formdata']->columname : $c['formdata']->column;
                            $tablehead[$c['formdata']->columname] = $columnheading;
                        }
                        list($align, $size, $wrap) = $class->colformat($c['formdata']);
                        $tablealign[] = $align;
                        $tablesize[] = $size ? $size . '%' : '';
                        $tablewrap[] = $wrap;
                    }

                }
                $firstrow = false;

            }
            $reporttable = $tempcols;
        }
        // EXPAND ROWS.
        $finaltable = array();
        $newcols = array();
        $i = 0;
        foreach ($reporttable as $key => $row) {
            $r = array_values($row);
            $r[] = $key;
            $finaltable[] = array_reverse($r);
            $i++;
        }
        // CALCS.
        $finalheadcalcs = $this->get_calcs($finaltable, $tablehead);
        $finalcalcs = $finalheadcalcs->data;

        if ($blockinstanceid == null) {
            $blockinstanceid = $this->config->id;
        }

        // Make the table, head, columns, etc...

        $table = new html_table;
        $table->data = $finaltable;
        if (is_array($courseids)) {
            for ($i = 0; $i < (COUNT($courseids) +1); $i++) {
                $table->head[] = '';
            }
        } else {
            for ($i = 0; $i < 2; $i++) {
                $table->head[] = '';
            }
        }
        $table->size = $tablesize;
        $table->align = $tablealign;
        $table->wrap = $tablewrap;
        $table->width = (isset($components['columns']['config'])) ? $components['columns']['config']->tablewidth : '';
        $table->summary = $this->config->summary;
        $table->tablealign = (isset($components['columns']['config'])) ? $components['columns']['config']->tablealign : 'center';
        $table->cellpadding = (isset($components['columns']['config'])) ? $components['columns']['config']->cellpadding : '5';
        $table->cellspacing = (isset($components['columns']['config'])) ? $components['columns']['config']->cellspacing : '1';

        if (!$this->finalreport) {
            $this->finalreport = new stdClass;
        }
        $this->finalreport->table = $table;
        $this->finalreport->calcs = null;
        return true;
    }
}
