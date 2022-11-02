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
defined('MOODLE_INTERNAL') || die();
class report_categories extends reportbase {

    public function __construct($report, $reportproperties) {
        global $USER, $DB;
        parent::__construct($report,$reportproperties);
        $this->components = array('columns', 'conditions', 'filters', 'permissions', 'calcs', 'plot');
        $this->columns = array('categoryfield' => ['categoryfield']);
        $this->courselevel = true;
        $this->parent = false;
        $this->defaultcolumn = 'id';
    }
    function count() {
        $this->sql = "SELECT COUNT(id)";
    }

    function select() {
        $this->sql = "SELECT * ";
    }
    
    function from() {
        $this->sql .=" FROM {course_categories}";
    }

    function filters() {}

    function where() {
        global $DB;
        $this->sql .=" WHERE 1 = 1 AND visible = 1";
        // getscheduled report
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
        if ($this->conditionsenabled) {
            $conditions = implode(',', $this->conditionfinalelements);
            if (empty($conditions)) {
                return array(array(), 0);
            }
            $this->sql .= " AND id IN ( $conditions )";
        }
        if ($this->ls_startdate > 0 && $this->ls_enddate) {
            $this->sql .= " AND timemodified BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
        parent::where();
    }

    function search() {
       if (isset($this->search) && $this->search) {
            $fields = array("name", "description", "parent");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    public function get_rows($elements, $sqlorder = '') {
        return $elements;
    }

}
