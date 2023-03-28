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

class report_userwisecourseoverview extends reportbase {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER;
        parent::__construct($report, $reportproperties);
        $this->components = array('columns', 'conditions', 'ordering', 'permissions', 'filters');
        $this->parent = true;
        $this->columns = array('coursefield' => ['coursefield'],'userfield' => array('userfield'),'coursescompletionscolumns' => ['coursename','startdate','enddate','enrolledon','completion_percentage','completionstatus','completiondate']);
        $this->basicparams = array(['name' => 'user']);
        $this->defaultcolumn = 'u.id,c.id';      

    }

    function count() {
        $this->sql = "SELECT COUNT(ue.id) ";
    }

    function select() {
        $this->sql = " SELECT ue.id as enrolid ,e.enrol as enrol,u.id as userid, CONCAT(u.firstname,' ',u.lastname) AS fullname, u.*
                        , ue.timecreated as enrolledon
                        , c.startdate as startdate
                        , c.enddate as enddate
                        , cc.timecompleted 
                        , c.id as courseid 
                        , c.fullname as coursename
                        , cc.timecompleted as completiondate, c.open_path as course_open_path " ;
        parent::select();
    }

    function from() {
        $this->sql .= " FROM {course} c ";
    }

    function joins() {   
        /*       $this->sql .=" JOIN {course_categories} cat ON cat.id = c.category
            JOIN {context} AS cxt ON cxt.contextlevel = 50 AND cxt.instanceid=c.id
            JOIN {role_assignments} as ra ON cxt.id=ra.contextid AND ra.roleid = {$employeerole}
            JOIN {user} u ON ra.userid = u.id AND u.confirmed = 1
                            AND u.deleted = 0 AND u.suspended = 0
            JOIN {local_costcenter} lc ON concat('/',u.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1
            LEFT JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid "; */   
        $this->sql .= " JOIN {enrol} as e ON c.id =e.courseid
                    JOIN {user_enrolments} ue ON ue.enrolid = e.id
                    JOIN {user} as u ON u.id = ue.userid
                    JOIN {role_assignments} ra ON ra.userid = ue.userid
                    JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('employee','student')
                    LEFT JOIN {course_completions} AS cc ON cc.course = c.id AND cc.userid = u.id  ";
        parent::joins();
    }

    function where() {
        
        $this->sql .= " WHERE 1=1 AND u.id > 2 ";
        $this->sql .= " AND u.suspended = 0 AND u.deleted = 0 AND c.open_coursetype = 0";
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
            $fields = array('c.fullname',"CONCAT(u.firstname,' ',u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
     
        if (!empty($this->params['filter_user'])) {
            $userid = $this->params['filter_user'];
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $userid;
        }else{
            $this->sql .= " AND 1<>1 ";
        } 
       
    }

    public function get_rows($courseusers) {
     
        return $courseusers;
    }

}
