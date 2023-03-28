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

class report_departmentoverview extends reportbase {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'conditions', 'ordering', 'permissions', 'filters', 'plot');
        $this->parent = true;
        $this->columns = array('departmentoverviewcolumns' => array('department_name','coursecount','iltcount','programcount','plancount','activeusers'));
        $this->orderable = array('department_name','coursecount','iltcount','programcount','plancount','activeusers');        
        $this->filters = array('organization', 'departments');
        $this->defaultcolumn = 'lc.id';
    }
    function count() {
      $this->sql  = " SELECT count(DISTINCT lc.id) ";
    }

    function select() {
      $this->sql = " SELECT lc.id, lc.fullname as department_name,lc.path ";
      parent::select();
    }
    
    function from() {
      $this->sql .= " FROM {local_costcenter} as lc";
    }

    function joins() {
      parent::joins();
    }

    function where() { 
        $this->sql .= " WHERE lc.depth = 2 ";        
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.path');
        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("lc.fullname");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        } 
    }

    function filters() {
        global $DB; 
        if ($this->params['filter_organization'] > 0) {
            $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
            $this->sql .= " AND concat(lc.path,'/') like :orgpath ";
            $this->params['orgpath'] = $orgpath.'/%';
        }
        if ($this->params['filter_departments']  > 0) {
            $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
            $this->sql .= " AND concat(lc.path,'/') like :l2dept ";
            $this->params['l2dept'] = $l2dept.'/%';
        }    
    }

    public function get_rows($data) {
        return $data;
    }
  
}

