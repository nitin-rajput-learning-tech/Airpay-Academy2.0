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
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;

class report_userparticipation extends reportbase {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'conditions', 'ordering', 'permissions', 'filters', 'plot');
        $this->parent = true;
        $this->columns = array('userfield' => array('userfield'), 'userparticipationcolumns' => array('coursesenrolled', 'coursesinprogress',
            'coursescompleted','coursesprogress', 'iltenrolled', 'iltinprogress', 'iltcompleted', 'iltprogress',
            'lpenrolled', 'lpinprogress','lpcompleted', 'lpprogress',
            'programenrolled','programinprogress','programcompleted', 'programprogress'));
        $this->orderable = array('fullname', 'email');        
        $this->filters = array('organization', 'departments','subdepartments', 'users');
        $this->defaultcolumn = 'u.id,u.open_path';
    }
    function count() {
      $this->sql  = " SELECT count(DISTINCT u.id) ";
    }

    function select() {
      $this->sql = " SELECT DISTINCT u.id , u.id AS userid, CONCAT(u.firstname,' ',u.lastname) AS fullname, u.*";
      parent::select();
    }
    
    function from() {
      $this->sql .= " FROM {user} as u";
    }

    function joins() {
      parent::joins();
    }

    function where() { 
        global $DB, $USER;
        $this->sql .= " WHERE u.id > :id AND u.deleted = :deleted AND u.suspended = :suspended ";
        $this->params['id'] = 2;
        $this->params['deleted'] = 0;
        $this->params['suspended'] = 0;
        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
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
        global $DB; 
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'%';
        }
        if ($this->params['filter_departments'] > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'%';
        }

        if ($this->params['filter_subdepartments'] > 0) {
            $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l3dept ";
            $this->params['l3dept'] = $l3dept.'%';
        }

        if ($this->params['filter_level4department'] > 0) {
            $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l4dept ";
            $this->params['l4dept'] = $l4dept.'%';
        }
        if ($this->params['filter_level5department'] > 0) {
            $l5dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level5department'], 'path');
            $this->sql .= " AND concat(u.open_path,'/') like :l5dept ";
            $this->params['l5dept'] = $l5dept.'%';
        }
        if ($this->params['filter_geostate'] > 0) {
            $this->sql .= " AND u.open_states = :filter_geostate ";
        }
        if ($this->params['filter_geodistrict'] > 0) {
            $this->sql .= " AND u.open_district = :filter_geodistrict ";
        }
        if ($this->params['filter_geosubdistrict'] > 0) {
            $this->sql .= " AND u.open_subdistrict = :filter_geosubdistrict ";
        }
        if ($this->params['filter_geovillage'] > 0) {
            $this->sql .= " AND u.open_village = :filter_geovillage ";
        }
        if (isset($this->params['filter_users'])
            && $this->params['filter_users'] >0
            && $this->params['filter_users'] != '_qf__force_multiselect_submission') {
            $userid = $this->params['filter_users'];
            $this->sql .= " AND u.id IN ($userid) ";
        }
        if (!empty($this->params['filter_country'])) {
            $countryval = isset($this->params['filter_country']) ? implode(',', $this->params['filter_country']) : 0; 
            $this->sql .= ' AND u.country IN ("' . implode('", "', $this->params['filter_country']) . '") ';
        }
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
          $this->sql .= " AND u.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate";
        } 
    }

    public function get_rows($users) {
        return $users;
    }
  
}
