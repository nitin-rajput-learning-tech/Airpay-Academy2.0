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
 * local_costcenter
 *
 * @package    local_costcenter
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_costcenter\functions;
require_once($CFG->dirroot . '/local/costcenter/lib.php');

class userlibfunctions{
	/* find department list
	@param need to pass costcenter value*/
	public function find_departments_list($costcenter){
	   
	    global $DB;
	    if($costcenter) {
		    $sql="select id,fullname from {local_costcenter} ";

		    $costcenters = explode(',',$costcenter);
	        list($relatedparentidsql, $relatedparentidparams) = $DB->get_in_or_equal($costcenters, SQL_PARAMS_NAMED, 'parentid');
	        $sql .= " where parentid $relatedparentidsql";

		    $sub_dep=$DB->get_records_sql($sql,$relatedparentidparams);

	      	return $sub_dep;
	  	}else {
	  		return $costcenter;
	  	}
	   
	    
	}
	/* find sub department list
	@param need to pass department value*/
	public function find_subdepartments_list($department){
	    global $DB;
	    $sql="select id,fullname from {local_costcenter} ";

	    $departments = explode(',',$department);
	    list($relatedparentidsql, $relatedparentidparams) = $DB->get_in_or_equal($departments, SQL_PARAMS_NAMED, 'parentid');
	    $sql .= " where parentid $relatedparentidsql";

	    $sub_dep=$DB->get_records_sql($sql,$relatedparentidparams);

	    return $sub_dep;
	}

	/* find supervisors list
	@param need to pass supervisor and userid optional value*/
	public function find_supervisor_list($supervisor,$userid=0){
		global $DB;
	    if($supervisor){
		    $sql="SELECT u.id,Concat(u.firstname,' ',u.lastname) as username from {user} as u where u.suspended = :suspended AND u.deleted = :deleted AND u.open_costcenterid = :costcenterid  AND u.id > 2";
		    if($userid){
		    	$sql .= " AND u.id != :userid";
		    }
		    $sub_dep=$DB->get_records_sql($sql,array('suspended' => 0,'deleted' => 0,'costcenterid' =>$supervisor ,'userid' => $userid));
		    
		    return $sub_dep;
	    }
	    
	}

	/* find department supervisors list
	@param need to pass supervisor and userid optional value*/
	public function find_dept_supervisor_list($supervisor,$userid=0){
	    if($supervisor){
	    global $DB;
	    $sql="SELECT u.id,Concat(u.firstname,' ',u.lastname) as username from {user} as u where u.suspended!=1 AND u.deleted!=1 AND u.open_departmentid= $supervisor AND u.id!= 1 AND u.id!=2";
	    if($userid){
	    	$sql .= " AND u.id != {$userid} AND u.id IN (SELECT open_supervisorid FROM {user} WHERE id = {$userid})";
	    }
	    $sub_dep=$DB->get_records_sql($sql);
	    
	      return $sub_dep;
	    }
	    
	}

}