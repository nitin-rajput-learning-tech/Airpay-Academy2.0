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

class report_usersdata extends reportbase {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'conditions', 'ordering', 'permissions', 'filters', 'plot');
        $this->parent = true;
        $this->columns = ['userfield'=>['userfield']];
        $this->filters = ['organization', 'user'];
        $this->orderable = array('employeename');
        $this->defaultcolumn = 'u.id';
    }
    function init()
    {
        parent::init();
    }

    function count()
    {
        $this->sql = " SELECT COUNT(u.id) ";
    }
    function select()
    {
      $this->sql = " SELECT u.id as userid, CONCAT(u.firstname,' ',u.lastname) AS fullname, u.*";
        parent::select();
    }
    function from()
    {
        $this->sql .= " FROM {user} u ";
    }
    function joins()
    {
        $this->sql .= "JOIN {local_costcenter} lc ON concat('/',u.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1 ";
        parent::joins();
    }
    function where()
    {
        global $USER, $DB;

        $this->sql .= " WHERE u.id > :id AND u.deleted = :deleted AND u.suspended = :suspended ";
        $this->params['id'] = 2;
        $this->params['deleted'] = 0;
        $this->params['suspended'] = 0;
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');
        // getscheduled report       
        if (is_siteadmin()) {
            $this->sql .= "";
        } else {
            $this->sql .= $costcenterpathconcatsql;
        }
        if ($this->conditionsenabled) {
            $conditions = implode(',', $this->conditionfinalelements);
            if (empty($conditions)) {
                return array(array(), 0);
            }
            $this->sql .= " AND u.id IN ( $conditions )";
        }

        parent::where();
    }
    function search()
    {
        if (isset($this->search) && $this->search) {
            $fields = array("CONCAT(u.firstname, ' ' , u.lastname)", "lc.fullname");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters()
    {
        if (!empty($this->params['filter_organization'])  && $this->params['filter_organization'] > 0) {
            $organization = $this->params['filter_organization'];
            $filter_organization[] = " concat('/',u.open_path,'/') LIKE :organizationparam_{$organization}";
            $this->params["organizationparam_{$organization}"] = '%/' . $organization . '/%';
            $this->sql .= " AND ( " . implode(' OR ', $filter_organization) . " ) ";
        }

       
        if (!empty($this->params['filter_user'])) {
            $userid = $this->params['filter_user'];
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $userid;
        }
    }
    public function get_rows($users)
    {
        return $users;
    }
}
