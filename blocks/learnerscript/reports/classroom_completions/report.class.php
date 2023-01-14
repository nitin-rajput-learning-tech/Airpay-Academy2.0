<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage block_learnerscript
 */

use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_classroom_completions extends reportbase implements report {
    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        parent::__construct($report);
        $this->parent = true;
        $this->components = array('columns', 'filters', 'permissions');
        $this->columns = array('classroomfield'=>['classroomfield'],
                                'userfield'=>['userfield'],
                                'classroomcompletionscolumns'=>['attendedsessions','totalsessions','usercompletionstatus','usercompletiondate']);
        $this->filters = array('organization','departments', 'subdepartments', 'user','classrooms','completionstatus');
        $this->defaultcolumn = 'lcu.id';
    }


    function init() {
        parent::init();
    }

    function count() {
        $this->sql = "SELECT COUNT(lcu.id) ";
    }

    function select() {
        $this->sql = " SELECT lcu.id as userenrolid , lc.id AS classroomid, lcu.userid, u.*,
                        (SELECT COUNT(lcs.id) 
                        FROM {local_classroom_sessions} lcs 
                        WHERE lcs.classroomid = lc.id) AS totalsessions,
                        (SELECT COUNT(lca.id) 
                        FROM {local_classroom_attendance} lca 
                        WHERE lca.classroomid = lc.id AND
                         lca.userid = u.id AND lca.status = 1) AS attendedsessions,
                        CASE
                            WHEN lcu.completion_status > 0 THEN 'Completed'
                            ELSE 'Not Completed'
                        END AS usercompletionstatus,
                        lcu.completiondate AS usercompletiondate,CONCAT(u.firstname, ' ', u.lastname) AS fullname, u.username, u.firstname, u.lastname, u.email, lc.open_path as class_open_path  " ;

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {local_classroom} lc ";
    }

    function joins() {
        $this->sql .=" JOIN {local_classroom_users} lcu ON lc.id = lcu.classroomid
                        JOIN {user} u ON u.id = lcu.userid ";

        parent::joins();
    }

    function where() {
        global $USER, $DB;

        $systemcontext = context_system::instance();

        $this->sql .= (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
        parent::where();
    }

    function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('lc.name',"CONCAT(u.firstname,' ',u.lastname)",'u.email','u.open_employeeid');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {

        if (!empty($this->params['filter_organization']) && $this->params['filter_organization'] > 0) {
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

        if (!empty($this->params['filter_classrooms']) && $this->params['filter_classrooms'] > 0) {
            $this->sql .= " AND lc.id = :classroomid ";
            $this->params['classroomid'] = $this->params['filter_classrooms'];
        }

        if (!empty($this->params['filter_user']) && $this->params['filter_user'] > 0) {
            $this->sql .= " AND u.id = :userid ";
            $this->params['userid'] = $this->params['filter_user'];
        }

        if ($this->params['filter_completionstatus'] > -1){
            $this->sql .= " AND lcu.completion_status = :usercomplstatus ";
            $this->params['usercomplstatus'] = $this->params['filter_completionstatus'];
        }

    }

    /**
     * [get_rows description]
     * @param  array  $classroomcompletion [description]
     * @return [type] [description]
     **/
    public function get_rows($classroomcompletion = array()) {
        return $classroomcompletion;
    }
}
