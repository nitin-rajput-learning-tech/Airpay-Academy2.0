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
class report_coursewithcategories extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        global $DB;
        parent::__construct($report, $reportproperties);
        $this->columns = ['coursesfield' => ['coursesfield']];

        $coursecolumns = $DB->get_columns('course');
        $usercolumns = $DB->get_columns('user');
        $this->conditions = ['courses' => array_keys($coursecolumns),
                             'user' => array_keys($usercolumns)];

        $this->components = array('columns', 'conditions', 'ordering', 'filters','permissions', 'plot'); 
        $this->filters = array('coursecategories');
        $this->parent = true;
        $this->orderable = array('coursename', 'enrolments', 'completed', 'activities', 'avggrade','progress', 'highgrade', 'lowgrade', 'badges', 'totaltimespent', 'fullname');

        $this->searchable = array('main.fullname', 'cat.name');
        $this->defaultcolumn = 'main.id';
        $this->excludedroles = array("'employee'");
    }

    public function init() {
        $this->categoriesid = isset($this->params['filter_coursecategories']) ? $this->params['filter_coursecategories'] : 0; 
        
    }
    public function count() {
       $this->sql = "SELECT COUNT(main.id)";

    }

    public function select() {
      $this->sql = "SELECT main.id, main.*, main.id AS courseid, main.fullname AS coursename";
      parent::select();
    }

    public function from() {
      $this->sql .= " FROM {course} as main 
                    JOIN {course_categories} as cat ON main.category = cat.id 
                    JOIN mdl_tag_instance as ti ON main.id=ti.itemid 
                    JOIN mdl_tag as t ON t.id = ti.tagid";
    }
    public function joins() { 
      parent::joins();
    }

    public function where() {
        global $DB, $USER;
        $this->sql .= " WHERE main.visible = 1 ";
        parent::where();
    }

    public function search() {
        if (isset($this->search) && $this->search) {
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $this->searchable);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    public function filters() { 
        global $DB, $USER;
        $systemcontext = context_system::instance();
        if (!empty($this->params['filter_coursecategories'])) {
            $categoryids = $this->params['filter_coursecategories'];
            $this->sql .= " AND main.category IN ($categoryids) ";
        } 
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND main.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
    }

    public function groupby() {
        $this->sql .= " GROUP BY main.id";
    }

    public function get_rows($courses) {
        return $courses;
    }

    public function column_queries($columnname, $courseid, $courses = null) { 
        global $DB, $USER;
    }
}
