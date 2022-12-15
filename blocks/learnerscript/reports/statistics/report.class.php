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
 * @author: eAbyas Info Solutions
 * @date: 2017
 */
use block_learnerscript\local\ls as ls;
use block_learnerscript\local\reportbase; 
use block_learnerscript\local\querylib;

class report_statistics extends reportbase {

	function init() {
		$this->ls_startdate=0;
        $this->ls_enddate = time();
	}
	public function __construct($report, $reportproperties) {
		parent::__construct($report);
		$this->components = array('customsql', 'filters', 'permissions', 'calcs', 'plot');
		$this->parent = true;
	}
	function prepare_sql($sql) {
		global $DB, $USER, $CFG, $COURSE, $SESSION;
		$expirydate = strtotime("+90 days");
        $sql = str_replace('%%EXPIRYDATE%%', $expirydate, $sql);
		$sql = str_replace('%%LS_STARTDATE%%', $this->ls_startdate, $sql);
		$sql = str_replace('%%LS_ENDDATE%%', $this->ls_enddate, $sql);
		$sql = str_replace('%%LS_ROLE%%', $this->role, $sql);
		$systemcontext = context_system::instance();
		// Enable debug mode from SQL query.
		$this->config->debug = (strpos($sql, '%%DEBUG%%') !== false) ? true : false;
		$sessiontimeout = $DB->get_field('config', 'value', array('name' => 'sessiontimeout'));
	
		// Pass special custom undefined variable as filter.
		// Security warning !!! can be used for sql injection.
		// Use %%FILTER_VAR%% in your sql code with caution.
		$filter_var = optional_param('filter_var', '', PARAM_RAW);
		if (!empty($filter_var)) {
			$sql = str_replace('%%FILTER_VAR%%', $filter_var, $sql);
		}

		$sql = str_replace('%%SESSIONTIMEOUT%%', $sessiontimeout, $sql);
		$sql = str_replace('%%USERID%%', $this->userid, $sql);
		$sql = str_replace('%%COURSEID%%', $this->courseid, $sql);
		$sql = str_replace('%%CATEGORYID%%', $COURSE->category, $sql);
		// $this->courseid = $this->courseid || $COURSE->id; 
		if (($this->courseid != SITEID) && preg_match("/%%LS_COURSEID:([^%]+)%%/i", $sql, $output)) { 
			$replace = ' AND ' . $output[1] . ' = ' . $this->courseid; 
			$sql = str_replace('%%LS_COURSEID:' . $output[1] . '%%', $replace, $sql); 
		}

		if (!is_siteadmin() && $_SESSION['role'] != 'manager') {
	        if (preg_match("/%%DASHBOARDROLE:([^%]+)%%/i", $sql, $output)) { 
	        	if ($_SESSION['role'] == 'user') {
	        		$currentrole = "'employee'";
	        	} else { 
	        		$currentrole = "'".$_SESSION['role']."'";
	        	}
	            $replace = ' AND ' . $output[1] . ' =  ' . $currentrole . ' ';
	            $sql = str_replace('%%DASHBOARDROLE:' . $output[1] . '%%', $replace, $sql);
	        }
	    }
	   
		if (preg_match("/%%FILTER_COURSE:([^%]+)%%/i", $sql, $output) && $this->courseid>1) {
			$replace = ' AND ' . $output[1] . ' = ' . $this->courseid;
			$sql = str_replace('%%FILTER_COURSE:' . $output[1] . '%%', $replace, $sql);
		} 
		
		
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
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
            if (preg_match("/%%FILTER_ORGANIZATION:([^%]+)%%/i", $sql, $output) && $this->costcenterid > 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $this->costcenterid;
				$sql = str_replace('%%FILTER_ORGANIZATION:' . $output[1] . '%%', $replace, $sql);
	        }
            if (preg_match_all("/%%FILTER_ORGANIZATION:([^%]+)%%/i", $sql, $output) && $this->costcenterid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $this->costcenterid;
					$sql = str_replace('%%FILTER_ORGANIZATION:' . $out . '%%', $replace, $sql);
	        	}
	        }
	        if (preg_match("/%%FILTER_ORGANIZATIONS:([^%]+)%%/i", $sql, $output) && $this->costcenterid > 0) {
	        	$replace = ' AND ('.$output[1].' = '.$this->costcenterid.' OR '.$output[1].' = 0)';
				$sql = str_replace('%%FILTER_ORGANIZATIONS:' . $output[1] . '%%', $replace, $sql);
	        }
            if (preg_match_all("/%%FILTER_ORGANIZATIONS:([^%]+)%%/i", $sql, $output) && $this->costcenterid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$this->costcenterid.' OR '.$out.' = 0)' ;
					$sql = str_replace('%%FILTER_ORGANIZATIONS:' . $out . '%%', $replace, $sql);
	        	}
	        }
		    if (preg_match("/%%FILTER_DEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->departmentid > 0) {
	        	$replace = ' AND ('.$output[1].' = '.$this->departmentid.' OR '.$output[1].' = 0 OR '.$output[1].' = -1)';
				$sql = str_replace('%%FILTER_DEPARTMENTS:' . $output[1] . '%%', $replace, $sql);
	        }
		    if (preg_match_all("/%%FILTER_DEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->departmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$this->departmentid.' OR '.$out.' = 0 OR '.$out.' = -1)';
					$sql = str_replace('%%FILTER_DEPARTMENTS:' . $out . '%%', $replace, $sql);
	    		}
	        }
		    if (preg_match("/%%FILTER_DEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->departmentid > 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $this->departmentid;
				$sql = str_replace('%%FILTER_DEPARTMENT:' . $output[1] . '%%', $replace, $sql);
	        }	        
		    if (preg_match_all("/%%FILTER_DEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->departmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $this->departmentid;
					$sql = str_replace('%%FILTER_DEPARTMENT:' . $out . '%%', $replace, $sql);
	    		}
	        }
	        
	        if (preg_match("/%%FILTER_SUBDEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	$replace = ' AND ('.$output[1].' = '.$this->subdepartmentid.' OR '.$output[1].' = 0 OR '.$output[1].' = -1)';
				$sql = str_replace('%%FILTER_SUBDEPARTMENTS:' . $output[1] . '%%', $replace, $sql);
	        }
		    if (preg_match_all("/%%FILTER_SUBDEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$this->subdepartmentid.' OR '.$out.' = 0 OR '.$out.' = -1)';
					$sql = str_replace('%%FILTER_SUBDEPARTMENTS:' . $out . '%%', $replace, $sql);
	    		}
	        }
		    if (preg_match("/%%FILTER_SUBDEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $this->subdepartmentid;
				$sql = str_replace('%%FILTER_SUBDEPARTMENT:' . $output[1] . '%%', $replace, $sql);
	        }	        
		    if (preg_match_all("/%%FILTER_SUBDEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $this->subdepartmentid;
					$sql = str_replace('%%FILTER_SUBDEPARTMENT:' . $out . '%%', $replace, $sql);
	    		}
	        }	        	        
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){ 
            if (preg_match("/%%FILTER_ORGANIZATION:([^%]+)%%/i", $sql, $output) && $USER->open_costcenterid >= 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $USER->open_costcenterid;
				$sql = str_replace('%%FILTER_ORGANIZATION:' . $output[1] . '%%', $replace, $sql);
	        }
            if (preg_match_all("/%%FILTER_ORGANIZATION:([^%]+)%%/i", $sql, $output) && $USER->open_costcenterid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $USER->open_costcenterid;
					$sql = str_replace('%%FILTER_ORGANIZATION:' . $out . '%%', $replace, $sql);
	        	}
	        }
	        if (preg_match("/%%FILTER_ORGANIZATIONS:([^%]+)%%/i", $sql, $output) && $this->costcenterid >= 0) {
	        	$replace = ' AND ('.$output[1].' = '.$USER->open_costcenterid.' OR '.$output[1].' = 0)';
				$sql = str_replace('%%FILTER_ORGANIZATIONS:' . $output[1] . '%%', $replace, $sql);
	        }
            if (preg_match_all("/%%FILTER_ORGANIZATIONS:([^%]+)%%/i", $sql, $output) && $this->costcenterid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$USER->open_costcenterid.' OR '.$out.' = 0)' ;
					$sql = str_replace('%%FILTER_ORGANIZATIONS:' . $out . '%%', $replace, $sql);
	        	}
	        }
		    if (preg_match("/%%FILTER_DEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->departmentid > 0) {
	        	$replace = ' AND ('.$output[1].' = '.$this->departmentid.' OR '.$output[1].' = 0 OR '.$output[1].' = -1)';
				$sql = str_replace('%%FILTER_DEPARTMENTS:' . $output[1] . '%%', $replace, $sql);
	        }
		    if (preg_match_all("/%%FILTER_DEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->departmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$this->departmentid.' OR '.$out.' = 0 OR '.$out.' = -1)';
					$sql = str_replace('%%FILTER_DEPARTMENTS:' . $out . '%%', $replace, $sql);
	    		}
	        }
		    if (preg_match("/%%FILTER_DEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->departmentid > 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $this->departmentid;
				$sql = str_replace('%%FILTER_DEPARTMENT:' . $output[1] . '%%', $replace, $sql);
	        }	        
		    if (preg_match_all("/%%FILTER_DEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->departmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $this->departmentid;
					$sql = str_replace('%%FILTER_DEPARTMENT:' . $out . '%%', $replace, $sql);
	    		}
	        }
	        if (preg_match("/%%FILTER_SUBDEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	$replace = ' AND ('.$output[1].' = '.$this->subdepartmentid.' OR '.$output[1].' = 0 OR '.$output[1].' = -1)';
				$sql = str_replace('%%FILTER_SUBDEPARTMENTS:' . $output[1] . '%%', $replace, $sql);
	        }
		    if (preg_match_all("/%%FILTER_SUBDEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$this->subdepartmentid.' OR '.$out.' = 0 OR '.$out.' = -1)';
					$sql = str_replace('%%FILTER_SUBDEPARTMENTS:' . $out . '%%', $replace, $sql);
	    		}
	        }
		    if (preg_match("/%%FILTER_SUBDEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $this->subdepartmentid;
				$sql = str_replace('%%FILTER_SUBDEPARTMENT:' . $output[1] . '%%', $replace, $sql);
	        }	        
		    if (preg_match_all("/%%FILTER_SUBDEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $this->subdepartmentid;
					$sql = str_replace('%%FILTER_SUBDEPARTMENT:' . $out . '%%', $replace, $sql);
	    		}
	        }	
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){ 
            if (preg_match("/%%FILTER_ORGANIZATION:([^%]+)%%/i", $sql, $output) && $USER->open_costcenterid >= 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $USER->open_costcenterid;
				$sql = str_replace('%%FILTER_ORGANIZATION:' . $output[1] . '%%', $replace, $sql);
	        }
            if (preg_match_all("/%%FILTER_ORGANIZATION:([^%]+)%%/i", $sql, $output) && $USER->open_costcenterid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $USER->open_costcenterid;
					$sql = str_replace('%%FILTER_ORGANIZATION:' . $out . '%%', $replace, $sql);
	        	}
	        }
	        if (preg_match("/%%FILTER_ORGANIZATIONS:([^%]+)%%/i", $sql, $output) && $this->costcenterid >= 0) {
	        	$replace = ' AND ('.$output[1].' = '.$USER->open_costcenterid.' OR '.$output[1].' = 0)';
				$sql = str_replace('%%FILTER_ORGANIZATIONS:' . $output[1] . '%%', $replace, $sql);
	        }
            if (preg_match_all("/%%FILTER_ORGANIZATIONS:([^%]+)%%/i", $sql, $output) && $this->costcenterid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$USER->open_costcenterid.' OR '.$out.' = 0)' ;
					$sql = str_replace('%%FILTER_ORGANIZATIONS:' . $out . '%%', $replace, $sql);
	        	}
	        }	        	        
		    if (preg_match("/%%FILTER_DEPARTMENTS:([^%]+)%%/i", $sql, $output) && $USER->open_departmentid >= 0) {
	        	$replace = ' AND ('.$output[1].' = '.$USER->open_departmentid.' OR '.$output[1].' = 0 OR '.$output[1].' = -1)';
				$sql = str_replace('%%FILTER_DEPARTMENTS:' . $output[1] . '%%', $replace, $sql);
	        }
		    if (preg_match_all("/%%FILTER_DEPARTMENTS:([^%]+)%%/i", $sql, $output) && $USER->open_departmentid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$USER->open_departmentid.' OR '.$out.' = 0 OR '.$out.' = -1)';
					$sql = str_replace('%%FILTER_DEPARTMENTS:' . $out . '%%', $replace, $sql);
	    		}
	        }
		    if (preg_match("/%%FILTER_DEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->departmentid >= 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $USER->open_departmentid;
				$sql = str_replace('%%FILTER_DEPARTMENT:' . $output[1] . '%%', $replace, $sql);
	        }	        
		    if (preg_match_all("/%%FILTER_DEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->departmentid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $USER->open_departmentid;
					$sql = str_replace('%%FILTER_DEPARTMENT:' . $out . '%%', $replace, $sql);
	    		}
	        }
	        if (preg_match("/%%FILTER_SUBDEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	$replace = ' AND ('.$output[1].' = '.$this->subdepartmentid.' OR '.$output[1].' = 0 OR '.$output[1].' = -1)';
				$sql = str_replace('%%FILTER_SUBDEPARTMENTS:' . $output[1] . '%%', $replace, $sql);
	        }
		    if (preg_match_all("/%%FILTER_SUBDEPARTMENTS:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$this->subdepartmentid.' OR '.$out.' = 0 OR '.$out.' = -1)';
					$sql = str_replace('%%FILTER_SUBDEPARTMENTS:' . $out . '%%', $replace, $sql);
	    		}
	        }
		    if (preg_match("/%%FILTER_SUBDEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $this->subdepartmentid;
				$sql = str_replace('%%FILTER_SUBDEPARTMENT:' . $output[1] . '%%', $replace, $sql);
	        }	        
		    if (preg_match_all("/%%FILTER_SUBDEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->subdepartmentid > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $this->subdepartmentid;
					$sql = str_replace('%%FILTER_SUBDEPARTMENT:' . $out . '%%', $replace, $sql);
	    		}
	        }		        
        } else { 
        	if (preg_match("/%%FILTER_ORGANIZATION:([^%]+)%%/i", $sql, $output) && $USER->open_costcenterid >= 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $USER->open_costcenterid;
				$sql = str_replace('%%FILTER_ORGANIZATION:' . $output[1] . '%%', $replace, $sql);
	        }
            if (preg_match_all("/%%FILTER_ORGANIZATION:([^%]+)%%/i", $sql, $output) && $USER->open_costcenterid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $USER->open_costcenterid;
					$sql = str_replace('%%FILTER_ORGANIZATION:' . $out . '%%', $replace, $sql);
	        	}
	        }
	        if (preg_match("/%%FILTER_ORGANIZATIONS:([^%]+)%%/i", $sql, $output) && $this->costcenterid >= 0) {
	        	$replace = ' AND ('.$output[1].' = '.$USER->open_costcenterid.' OR '.$output[1].' = 0)';
				$sql = str_replace('%%FILTER_ORGANIZATIONS:' . $output[1] . '%%', $replace, $sql);
	        }
            if (preg_match_all("/%%FILTER_ORGANIZATIONS:([^%]+)%%/i", $sql, $output) && $this->costcenterid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$USER->open_costcenterid.' OR '.$out.' = 0)' ;
					$sql = str_replace('%%FILTER_ORGANIZATIONS:' . $out . '%%', $replace, $sql);
	        	}
	        }	        	        
		    if (preg_match("/%%FILTER_DEPARTMENTS:([^%]+)%%/i", $sql, $output) && $USER->open_departmentid >= 0) {
	        	$replace = ' AND ('.$output[1].' = '.$USER->open_departmentid.' OR '.$output[1].' = 0 OR '.$output[1].' = -1)';
				$sql = str_replace('%%FILTER_DEPARTMENTS:' . $output[1] . '%%', $replace, $sql);
	        }
		    if (preg_match_all("/%%FILTER_DEPARTMENTS:([^%]+)%%/i", $sql, $output) && $USER->open_departmentid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$USER->open_departmentid.' OR '.$out.' = 0 OR '.$out.' = -1)';
					$sql = str_replace('%%FILTER_DEPARTMENTS:' . $out . '%%', $replace, $sql);
	    		}
	        }
		    if (preg_match("/%%FILTER_DEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->departmentid >= 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $USER->open_departmentid;
				$sql = str_replace('%%FILTER_DEPARTMENT:' . $output[1] . '%%', $replace, $sql);
	        }	        
		    if (preg_match_all("/%%FILTER_DEPARTMENT:([^%]+)%%/i", $sql, $output) && $this->departmentid >= 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $USER->open_departmentid;
					$sql = str_replace('%%FILTER_DEPARTMENT:' . $out . '%%', $replace, $sql);
	    		}
	        }
	        if (preg_match("/%%FILTER_SUBDEPARTMENTS:([^%]+)%%/i", $sql, $output) && $USER->open_subdepartment > 0) {
	        	$replace = ' AND ('.$output[1].' = '.$USER->open_subdepartment.' OR '.$output[1].' = 0 OR '.$output[1].' = -1)';
				$sql = str_replace('%%FILTER_SUBDEPARTMENTS:' . $output[1] . '%%', $replace, $sql);
	        }
		    if (preg_match_all("/%%FILTER_SUBDEPARTMENTS:([^%]+)%%/i", $sql, $output) && $USER->open_subdepartment > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ('.$out.' = '.$USER->open_subdepartment.' OR '.$out.' = 0 OR '.$out.' = -1)';
					$sql = str_replace('%%FILTER_SUBDEPARTMENTS:' . $out . '%%', $replace, $sql);
	    		}
	        }
		    if (preg_match("/%%FILTER_SUBDEPARTMENT:([^%]+)%%/i", $sql, $output) && $USER->open_subdepartment > 0) {
	        	$replace = ' AND ' . $output[1] . ' = ' . $USER->open_subdepartment;
				$sql = str_replace('%%FILTER_SUBDEPARTMENT:' . $output[1] . '%%', $replace, $sql);
	        }	        
		    if (preg_match_all("/%%FILTER_SUBDEPARTMENT:([^%]+)%%/i", $sql, $output) && $USER->open_subdepartment > 0) {
	        	foreach($output[1] as $out){
		        	$replace = ' AND ' . $out . ' = ' . $USER->open_subdepartment;
					$sql = str_replace('%%FILTER_SUBDEPARTMENT:' . $out . '%%', $replace, $sql);
	    		}
	        }		        
        }

        if (preg_match("/%%DHQUERY:([^%]+)%%/i", $sql, $output)) { 
        	if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
	            $coursesql  = (new querylib)->getcourseslist($this->costcenterid, $this->departmentid, $this->subdepartmentid);
	        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){ 
	            $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $this->departmentid, $this->subdepartmentid);
	        } else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){ 
	            $coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $USER->open_departmentid, $this->subdepartmentid); 
	        } else {
	        	$coursesql  = (new querylib)->getcourseslist($USER->open_costcenterid, $USER->open_departmentid, $USER->open_subdepartment);
	        }
	    	if (empty($coursesql)) {
            	$replace = ' AND ' . $output[1] . ' IN  (0) '; 
            } else {
            	$replace = ' AND ' . $output[1] . ' IN  (' . $coursesql . ') ';
            }
            $sql = str_replace('%%DHQUERY:' . $output[1] . '%%', $replace, $sql);
        }

    	if (preg_match("/%%FILTER_ONLINECOURSES:([^%]+)%%/i", $sql, $output) && $this->onlinecourseid > 0) {
        	$replace = ' AND ' . $output[1] . ' = ' . $this->onlinecourseid;
			$sql = str_replace('%%FILTER_ONLINECOURSES:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_LABS:([^%]+)%%/i", $sql, $output) && $this->labid > 0) {
        	$replace = ' AND ' . $output[1] . ' = ' . $this->labid;
			$sql = str_replace('%%FILTER_LABS:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_ASSESSMENTS:([^%]+)%%/i", $sql, $output) && $this->assessmentid > 0) {
        	$replace = ' AND ' . $output[1] . ' = ' . $this->assessmentid;
			$sql = str_replace('%%FILTER_ASSESSMENTS:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_WEBINARS:([^%]+)%%/i", $sql, $output) && $this->webinarid > 0) {
        	$replace = ' AND ' . $output[1] . ' = ' . $this->webinarid;
			$sql = str_replace('%%FILTER_WEBINARS:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_CLASSROOMS:([^%]+)%%/i", $sql, $output) && $this->classroomid > 0) {
        	$replace = ' AND ' . $output[1] . ' = ' . $this->classroomid;
			$sql = str_replace('%%FILTER_CLASSROOMS:' . $output[1] . '%%', $replace, $sql);
        }
        if (preg_match("/%%FILTER_LEARNINGPATH:([^%]+)%%/i", $sql, $output) && $this->learningpathid > 0) {
        	$replace = ' AND ' . $output[1] . ' = ' . $this->learningpathid;
			$sql = str_replace('%%FILTER_LEARNINGPATH:' . $output[1] . '%%', $replace, $sql);
        }


		// See http://en.wikipedia.org/wiki/Year_2038_problem
		$sql = str_replace(array('%%STARTTIME%%', '%%ENDTIME%%'), array('0', '2145938400'), $sql);
		$sql = str_replace('%%WWWROOT%%', $CFG->wwwroot, $sql);
		$sql = preg_replace('/%{2}[^%]+%{2}/i', '', $sql);

		$sql = str_replace('?', '[[QUESTIONMARK]]', $sql);
		return $sql;
	}

	function execute_query($sql) {
		global $remoteDB, $DB, $CFG;

		$sql = preg_replace('/\bprefix_(?=\w+)/i', $CFG->prefix, $sql);

		// Use a custom $DB (and not current system's $DB)
		// todo: major security issue
		// $remoteDBhost = get_config('block_learnerscript', 'dbhost');
		// if (empty($remoteDBhost)) {
		// 	$remoteDBhost = $CFG->dbhost;
		// }
		// $remoteDBname = get_config('block_learnerscript', 'dbname');
		// if (empty($remoteDBname)) {
		// 	$remoteDBname = $CFG->dbname;
		// }
		// $remoteDBuser = get_config('block_learnerscript', 'dbuser');
		// if (empty($remoteDBuser)) {
		// 	$remoteDBuser = $CFG->dbuser;
		// }
		// $remoteDBpass = get_config('block_learnerscript', 'dbpass');
		// if (empty($remoteDBpass)) {
		// 	$remoteDBpass = $CFG->dbpass;
		// }

		// $db_class = get_class($DB);
		// $remoteDB = new $db_class();
		// $remoteDB->connect($remoteDBhost, $remoteDBuser, $remoteDBpass, $remoteDBname, $CFG->prefix);

		$starttime = microtime(true);

		if (preg_match('/\b(INSERT|INTO|CREATE)\b/i', $sql)) {
			// Run special (dangerous) queries directly.
			$results = $DB->execute($sql);
		} else {
			$results = $DB->get_recordset_sql($sql, null, 0, 1);
		}

		// Update the execution time in the DB.
		// $updaterecord = $DB->get_record('block_learnerscript', array('id' => $this->config->id));
		$lastexecutiontime = round((microtime(true) - $starttime) * 1000);
		$this->config->lastexecutiontime = $lastexecutiontime;

		// $DB->update_record('block_learnerscript', $updaterecord);
        $DB->set_field('block_learnerscript', 'lastexecutiontime', $lastexecutiontime,  array('id' => $this->config->id));
		return $results;
	}

	function create_report($blockinstanceid = null, $start = 0, $length = -1, $search = '') {
		global $DB, $CFG, $PAGE;

		$PAGE->requires->jquery_plugin('ui-css');
		//$PAGE->requires->js('/blocks/learnerscript/js/tooltip.js');

		$components = (new ls)->cr_unserialize($this->config->components);

		$filters = (isset($components['filters']['elements'])) ? $components['filters']['elements'] : array();
		$calcs = (isset($components['calcs']['elements'])) ? $components['calcs']['elements'] : array();

		$tablehead = array();
		$finalcalcs = array();
		$finaltable = array();
		$tablehead = array();

		$components = (new ls)->cr_unserialize($this->config->components);
		$config = (isset($components['customsql']['config'])) ? $components['customsql']['config'] : new stdclass;
		$totalrecords = 0;

		$sql = '';
		if (isset($config->querysql)) {
			// FILTERS
			$sql = $config->querysql;
			if (!empty($filters)) {
				foreach ($filters as $f) {
					require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' . $f['pluginname'] . '/plugin.class.php');
					$classname = 'plugin_' . $f['pluginname'];
					$class = new $classname($this->config);
					$sql = $class->execute($sql, $f['formdata']);
				}
			}

			$sql = $this->prepare_sql($sql);

			if ($rs = $this->execute_query($sql)) {
				foreach ($rs as $row) {
					if (empty($finaltable)) {
						foreach ($row as $colname => $value) {
							$tablehead[] = ucfirst(str_replace('_', ' ', $colname));
						}
					}
					$array_row = array_values((array) $row);
					foreach ($array_row as $ii => $cell) {
						$array_row[$ii] = str_replace('[[QUESTIONMARK]]', '?', $cell);
					}
					$totalrecords++; 
					if ($this->config->name == 'Maximum time spent on LMS' || $this->config->name == 'Average time spend' || $this->config->name == 'Total Time Spend') { 
						if ($array_row[0] > 0) {
							$array_row[0] = (new ls)->strTime($array_row[0]); 
						}
					} else if ($this->config->name == 'Maximum time spent in course' || $this->config->name == 'Maximum time spent in activity level') {
						if ($array_row[1] > 0) {
							$array_row[1] = (new ls)->strTime($array_row[1]); 
						}
					}
		            if (preg_match_all("/Completion Percentage/i", $this->config->name) ) {
						$array_row[0] = "<h1>".round($array_row[0]) . '%'."</h1>";
			        }
					$finaltable[] = $array_row;
				}
			}
		}
		$this->sql = $sql;
		$this->totalrecords = $totalrecords;
		if ($blockinstanceid == null) {
			$blockinstanceid = $this->config->id;
		}

		// Calcs

		$finalcalcs = $this->get_calcs($finaltable, $tablehead);

		$table = new stdclass;
		$table->id = 'reporttable_' . $blockinstanceid . '';
		$table->data = $finaltable;
		$table->head = $tablehead;

		$calcs = new html_table();
		$calcs->id = 'calcstable';
		$calcs->data = array($finalcalcs);
		$calcs->head = $tablehead;

		if (!$this->finalreport) {
			$this->finalreport = new StdClass;
		}
		$this->finalreport->table = $table;
		$this->finalreport->calcs = $calcs;

		return true;
	}

}
