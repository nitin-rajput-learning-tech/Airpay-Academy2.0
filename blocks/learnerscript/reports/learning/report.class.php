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
 * @author: Revanth Kumar Grandhi
 * @date: 2021
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\ls as ls;

class report_learning extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report, $reportproperties);
        $this->parent = true;
        $this->columns = array('learningcolumns' => array('learningformat', 'enrolments', 'inprogress', 'completed', 'completionpercentage', 'upcomingdeadline', 'completiondeadline'));
        $this->components = array('columns', 'filters', 'permissions', 'plot');
        $this->courselevel = true;
        $this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']);
        $this->filters = array('contentprovider','learningtype', 'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');        
        $this->orderable = array('learningformat', 'enrolments', 'inprogress', 'completed', 'completionpercentage', 'upcomingdeadline', 'completiondeadline');
        $this->searchable = array('');
        $this->defaultcolumn = 'bll.moduleid';
        $this->excludedroles = array("'employee'");
    }
    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT bll.moduleid),bll.moduletype";
    }
    public function select() {
        $this->sql = "SELECT bll.moduleid, bll.moduletype AS learningformat ";
        parent::select();
    }
    public function from() {
        if ($this->params['filter_organization']>0) {
            $costcenter = " AND bll.costcenterid IN (" .$this->params['filter_organization'] .','. 0 .") AND bll.user_costcenterid = ".$this->params['filter_organization'] ;
        }
        if (!empty($this->params['filter_departments']) && $this->params['filter_departments'] > 0) {
            $costcenter .= " AND bll.departmentid IN (" .$this->params['filter_departments'] .','. 0 .") AND bll.user_departmentid = ".$this->params['filter_departments'] ;
        }
        if (!empty($this->params['filter_subdepartments']) && $this->params['filter_subdepartments'] > 0) {
            $costcenter .= " AND bll.subdepartment IN (" .$this->params['filter_subdepartments'] .','. 0 .") AND bll.user_subdepartment = ".$this->params['filter_subdepartments'] ;
        }
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $contentprovideridsf= " AND bll.open_contentvendor IN ($contentproviderids) ";
        }
        $learningtype = isset($this->params['filter_learningtype']) ? implode(',', $this->params['filter_learningtype']) : 0;        
        $solutionarea = isset($this->params['filter_solutionarea']) ? implode(',', $this->params['filter_solutionarea']) : 0;
        $technology = isset($this->params['filter_technology']) ? implode(',', $this->params['filter_technology']) : 0;
        $topic = isset($this->params['filter_topic']) ? implode(',', $this->params['filter_topic']) : 0;
        $vendor = isset($this->params['filter_vendor']) ? implode(',', $this->params['filter_vendor']) : 0;
        $level = isset($this->params['filter_level']) ? implode(',', $this->params['filter_level']) : 0;
        $language = isset($this->params['filter_language']) ? implode(',', $this->params['filter_language']) : 0;
        $jobrole = isset($this->params['filter_jobrole']) ? implode(',', $this->params['filter_jobrole']) : 0;

        $tagslist = array($learningtype, $solutionarea, $technology, $topic, $vendor, $level, $language, $jobrole); 
        if (array_sum($tagslist) > 0) {
            $tagslist = implode(',', $tagslist); 
            $tagcoursesql  = (new querylib)->gettagcourses($tagslist);
            if (!empty($tagcoursesql) && $tagcoursesql > 0) { 
                $learningtypf= " AND bll.learningformatid IN (".$tagcoursesql.")";
            } else {
                $learningtypf= " AND bll.learningformatid IN (0)";
            } 
        }        
        $this->sql .= " FROM {block_ls_learningformats}  AS bll
                        WHERE 1 = 1 AND bll.moduleid !=7  {$contentprovideridsf} {$learningtypf} {$costcenter}" ;
    }
    public function joins() { 
        parent::joins();
    }
    public function where() { 
        $this->sql .= "  ";
        if (!is_siteadmin($this->userid) && !(new ls)->is_manager($this->userid, $this->contextlevel, $this->role)) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND bll.learningformatid IN ($this->rolewisecourses) ";
            } 
        } 
        parent::where();
    }
    public function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('name');
            $fields = implode(" LIKE '%$this->search%' ", $fields);
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    public function filters() { 
        global $DB, $USER;
    }
    
    public function groupby() {
        $this->sql .= " GROUP BY bll.moduleid, bll.moduletype";
    }
    /**
     * [get_rows description]
     * @param  array  $users [description]
     * @return [type]        [description]
     */
    public function get_rows($learning = array()) {
        return $learning;
    }
}
