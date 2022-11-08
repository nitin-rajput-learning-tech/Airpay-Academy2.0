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
 * @author Maheshchandra  <maheshchandra@eabyas.in>
 */
/**
 * Assign roles to users.
 * @package    local
 * @subpackage assignroles
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_assignroles\local;
require_once($CFG->dirroot . '/local/lib.php');
	    
class assignrole{

	/**
	 * Function to assign roles to multiple users at once
	 * @param [array] $users array of user ids
	 * @param [int] $roleid roleid
	 * @param [int] $contextid contextid of the role
	 */
	public function rolesassign($users,$roleid,$contextid){
		global $CFG;
	    require_once($CFG->dirroot . '/lib/accesslib.php');
		foreach($users as $key=>$user){
			 role_assign($roleid, $user, $contextid); 
	   	}
 	}
 	/**
	 * Function to assign roles to multiple users at once
	 * @param [int] $context context of the role
	 * @param [bool] $rolenamedisplay type of rolename display
	 * @param [bool] $withusercounts boolean to check to send count of users or not
	 * @param [optional] $user specific user to check optionally takes $USER
	 */
 	public function get_assignable_roles(\context $context, $rolenamedisplay = ROLENAME_ALIAS, $withusercounts = false, $user = null) {
	    global $USER, $DB;
	    // make sure there is a real user specified
	    if ($user === null) {
	        $userid = isset($USER->id) ? $USER->id : 0;
	    } else {
	        $userid = is_object($user) ? $user->id : $user;
	    }

	    if (!has_capability('moodle/role:assign', $context, $userid)) {
	        if ($withusercounts) {
	            return array(array(), array(), array());
	        } else {
	            return array();
	        }
	    }

	    $params = array();
	    $extrafields = '';

		$context = (new \local_costcenter\lib\accesslib())::get_module_context();	
		
	    if ($withusercounts) {
	        $extrafields = ", (SELECT count(u.id)
	                             FROM {role_assignments} cra JOIN {user} u ON cra.userid = u.id
	                             JOIN {local_costcenter} AS c on c.id=u.open_costcenterid
	                            WHERE cra.roleid = r.id AND u.deleted = 0";		
			if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context)){
	            $extrafields .= " AND cra.contextid = :conid AND u.open_costcenterid = :open_costcenterid ";
	        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $context)){
	            $extrafields .= " AND cra.contextid = :conid AND u.open_costcenterid = :open_costcenterid AND u.open_departmentid = :open_departmentid";
	        }
	        $extrafields .= " ) AS usercount";
			$params['conid'] = $context->id;
	        $params['open_costcenterid'] = $USER->open_costcenterid;
	        $params['open_departmentid'] = $USER->open_departmentid;
	    }
	
	    if (is_siteadmin($userid)  || has_capability('local/costcenter:assign_multiple_departments_manage', $context, $userid)) {
	        // show all roles allowed in this context to admins
	        $assignrestriction = "";
	    } else {
	        
	        $parents = $context->get_parent_context_ids(true);
	        $contexts = implode(',' , $parents);
	        $assignrestriction = "JOIN (SELECT DISTINCT raa.allowassign AS id
	                                      FROM {role_allow_assign} raa
	                                      JOIN {role_assignments} ra ON ra.roleid = raa.roleid
	                                      JOIN {user} u ON u.id = ra.userid
	                                     WHERE ra.userid = :userid AND ra.contextid IN ($contexts)";
	          
	        $assignrestriction .=  ") ar ON ar.id = r.id";
	        
	        $params['userid'] = $userid;

	    }

		$params['contextlevel'] = $context->contextlevel;
	    if ($coursecontext = $context->get_course_context(false)) {
	        $params['coursecontext'] = $coursecontext->id;
	    } else {
	        $params['coursecontext'] = 0; // no course aliases
	        $coursecontext = null;
	    }
		//$DB->set_debug(true);
	    $sql = "SELECT r.id, r.name, r.shortname, rn.name AS coursealias $extrafields
	              FROM {role} r
	              $assignrestriction 
				  LEFT JOIN {role_names} rn ON (rn.contextid = :coursecontext AND rn.roleid = r.id)
	             ";
		// if(!is_siteadmin()){
	        $sql .= "  JOIN {role_context_levels} rcl ON (rcl.contextlevel = :contextlevel AND r.id = rcl.roleid)";
		// }
	         
	    $sql .= " WHERE 1=1 ORDER BY r.sortorder ASC";
		
	    $roles = $DB->get_records_sql($sql, $params);
	    $rolenames = role_fix_names($roles, $coursecontext, $rolenamedisplay, true);
		//echo $sql;
		//$DB->set_debug(false);exit;
	    if (!$withusercounts) {
	        return $rolenames;
	    }

	    $rolecounts = array();
	    $nameswithcounts = array();
	    foreach ($roles as $role) {
	        $nameswithcounts[$role->id] = $rolenames[$role->id] . ' (' . $roles[$role->id]->usercount . ')';
	        $rolecounts[$role->id] = $roles[$role->id]->usercount;
	    }
	    return array($rolenames, $rolecounts, $nameswithcounts);
	}

}
