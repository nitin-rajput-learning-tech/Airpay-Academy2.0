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
 * @subpackage local_courses
 */


require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $OUTPUT,$USER,$CFG,$PAGE;
require_once($CFG->libdir . '/formslib.php');

Class custom_filter extends moodleform{
     public function definition() {  
        global $DB,$CFG, $PAGE;
        $mform = $this->_form;
    }
    /** Function Used For Dynamic Search Fields
    $label->name of the field
    $name->name of the select
    $function->function used get the data in dropdown list
    **/
    public function filters($label,$name,$functions = false){
        global $DB,$CFG, $PAGE;

        $mform = $this->_form;
        $select_designation =$mform->addElement('select',$name,$label,$functions,array('class'=>'idnumber','data-placeholder'=> get_string('select')));
        $mform->setType($name,PARAM_RAW);
        $select_designation->setMultiple(true);
         
    }
    /**Hidden Field in the form used this function**/
    public function hidden($name,$value){
		global $DB,$CFG, $PAGE;
        $mform = $this->_form;
        $mform->addElement('hidden',$name);
        $mform->setType($name,PARAM_INT);
	    $mform->setDefault($name,$value);
        
        $mform->addElement('hidden',$name);
        $mform->setType($name,PARAM_INT);
        $mform->setDefault($name,$value);
    }

    /**Submit Button for the form used in this function**/
    public function buttonsub(){
        $this->add_action_buttons(true, get_string('filter'));
    }
    
    /**These below fucntion are used to fetch the data and display in the select box**/
    
    /**Function to get the all users emails**/
	public function get_all_users_emails($costcenter,$data = false){
		global $DB;
		$allusers_emails = array();
		if($data){
			list($implodeemailssql, $implodeemailsparams) = $DB->get_in_or_equal($data, SQL_PARAMS_NAMED, 'param', true, false);
			$users_emails = $DB->get_records_sql("SELECT id,email FROM {user} WHERE id  $implodeemailssql", [$implodeemailsparams]);
		}else{
			if($costcenter){
				$sql = "select u.id,u.email from {user} as u where u.deleted = 0 and u.suspended = 0 and u.id>2 and u.open_costcenterid = ?"; 
				$users_emails = $DB->get_records_sql($sql, [$costcenter] );
			}else{
				$sql = "select u.id,u.email from {user} as u where u.deleted = 0 and u.suspended = 0 and u.id>2";
				$users_emails = $DB->get_records_sql($sql);
			}
			
			$allusers_emails['-1'] = get_string('all');
		}
		
		if($users_emails){
			foreach($users_emails as $users_email){
				$allusers_emails["$users_email->id"] = $users_email->email;
			}
		}
		return $allusers_emails;
	}
     /**End of the code **/
     
     /**Function to get the all users employee id's**/ 
	public function get_allusers_employeeids($costcenter,$data = false){
		global $DB;
		$allusers_employees = array();
		$sql = "SELECT u.idnumber as idnumber_key, u.idnumber as idnumber_value
            from {user} as u
            where u.deleted = 0 and u.suspended = 0";
		if($data){
			foreach($data as $value) {
				$ndata[$value] = "'".$value."'";
			}
			
			$implodeidnumber = implode(',', $ndata);
			
			$sql .= " AND u.idnumber IN ($implodeidnumber) ";
		}else{
			if($costcenter){
				$sql .= " AND u.open_costcenterid=$costcenter"; 
			}
			$allusers_employees['-1'] = get_string('all');
		}
		$sql .= " GROUP BY u.idnumber";
		$employeeids_list = $DB->get_records_sql($sql);
		if($employeeids_list){
		    foreach($employeeids_list as $employeeids){
			   $data_id=preg_replace("/[^0-9,.]/", "", $employeeids->idnumber_value);
			   $allusers_employees["$employeeids->idnumber_key"] = $data_id;
		    }
		}
		return $allusers_employees;
	}
     /**End of the code**/
     
     /**Function to get the allDepartments**/
	public function get_alldepartments($costcenter,$data = false){
		global $DB;
		$allusers_emails = array();
		$sql = "SELECT u.id as idnumber_value, u.department as cid, c.fullname AS departmentname
                    FROM {user} AS u
                    LEFT JOIN {local_costcenter} AS c ON c.id = u.department
                    WHERE 1=1 ";
		if($data){
			$implodedepartments = implode(',', $data);
			$sql .= " AND c.id IN ($implodedepartments) ";
		}else{
			if($costcenter){
				$sql .= " AND u.open_costcenterid = $costcenter";
			}
			$allusers_emails['-1'] = get_string('all');
		}
		$sql .= " GROUP by u.department ";
		$users_department = $DB->get_records_sql($sql);
		
		if($users_department){
			foreach($users_department as $users_departments){
				$allusers_emails["$users_departments->cid"] = $users_departments->departmentname;
			}
		}
		return $allusers_emails;
     }
     /**End of the Code**/
     /**Function to get the all Sub departments**/ 
	public function get_allsubdepartments($costcenter,$data = false){
		global $DB;
		$allusers_emails = array();
		$sql = "SELECT u.id as idnumber_value, u.open_subdepartment as cid, c.fullname AS departmentname
                    FROM {user} AS u
                    LEFT JOIN {local_costcenter} AS c ON c.id = u.open_subdepartment
                    WHERE 1=1";
		if($data){
			$implodedepartments = implode(',', $data);
			$sql .= " AND c.id IN ($implodedepartments)";
		}else{
			if($costcenter){
				$sql .= " AND u.open_costcenterid = $costcenter";
			}
			$allusers_emails['-1'] = get_string('all');
		}
		$sql .= " GROUP by u.open_subdepartment ";
		$users_emails = $DB->get_records_sql($sql); 
		
		if($users_emails){
			foreach($users_emails as $users_email){
				$allusers_emails["$users_email->cid"] = $users_email->departmentname;
			}
		}
		return $allusers_emails;
	}
    /**End of the code**/    

    /**Function to get the all band users**/
	public function get_allband_users($costcenter,$data = false){
		global $DB;
		$allusers_emails = array();
		$sql = "SELECT  band as bkey, band 
                    FROM {user} as u
                    WHERE u.deleted = 0 and u.suspended = 0 and u.id>2";
		if($data){
			foreach($data as $value) {
				$ndata[$value] = "'".$value."'";
			}
			$implodebands = implode(',', $ndata);
			$sql .= " AND u.open_band IN ($implodebands) ";
		}else{
			if(isset($costcenter) && $costcenter ){
				$sql .= " AND u.open_costcenterid=$costcenter ";
			}
			$allusers_emails['-1'] = get_string('all');
		}
		$users_emails = $DB->get_records_sql($sql);
		if(!empty($users_emails)){
			foreach($users_emails as $users_email){
				$allusers_emails["$users_email->bkey"] = $users_email->band;
			}
		}
		return $allusers_emails;
	}
   /**End of the code**/
 
     /**Function to get the all costcenters**/
	public function get_allcostcenters($data = false){
		global $DB;
		$departments = array();
		$sql = "SELECT id,fullname from {local_costcenter} WHERE visible =1 and parentid IN(0,1) ";
		if($data){
			$implodecostcenters = implode(',', $data);
			$sql .= " AND id IN ($implodecostcenters)";
		}else{
			$sql .= "";
			$departments['-1'] = get_string('all');
		}
		$depts = $DB->get_records_sql($sql);
		if($depts){
			foreach($depts as $dept){
				$departments["$dept->id"] = $dept->fullname;
			}
		}
		return $departments;
	}
	/**End of the code**/
    /**Function to get the all employee Designation**/
	function get_employeedesignation($costcenter,$data = false){
		global $DB;
		$allusers_employees = array();
		$sql = "SELECT id as idnumber_value, designation as designation_key from {local_userdata} WHERE 1=1"; 
		if($data){
			$implodedesignations = implode(',', $data);
			$sql .= " AND id IN ($implodedesignations) ";
		}else{
			if($costcenter){
				 $sql .= " AND costcenterid=$costcenter";
			}
			$allusers_employees['-1'] = get_string('all');
		}
		$employeeids_list = $DB->get_records_sql($sql);      
	   
		if($employeeids_list){
			foreach($employeeids_list as $employeeids){
				$allusers_employees["$employeeids->idnumber_value"] = $employeeids->designation_key;
			}
		}
		return $allusers_employees;
	}
	/** End of the code **/
    /** Function to get the all supervisor **/
	public function get_supervisors($costcenter,$like,$page){
     	global $DB;
        if($costcenter){
            $sql = "SELECT distinct(u.idnumber) as idnumber_key, u.id as idnumber_value,Concat(u.firstname,' ',u.lastname) as firstname
                from {user} as u
                where u.deleted = 0 and u.suspended = 0 and u.open_costcenterid={$costcenter}";
        }else{
            $sql = "SELECT distinct(u.idnumber) as idnumber_key, u.id as idnumber_value,Concat(u.firstname,' ',u.lastname)as firstname
            from {user} as u
            where u.deleted = 0 and u.suspended = 0";
        }
        if($like){
            $sql .= " AND Concat(u.firstname,' ',u.lastname) LIKE '%%$like%%'";
        }
        $total_ids = $DB->get_records_sql($sql);
        if($page > 1){
            $page = $page-1;
            $length = $page*50;
            $sql .= " LIMIT $length, 50";
        }else{
            $sql .= " LIMIT 0,50";
        }
        $employeeids_list = $DB->get_records_sql($sql);
        $allusers_employees = array();
        
        if($employeeids_list){
            foreach($employeeids_list as $employeeids){
               $allusers_employees[] = ['id'=>$employeeids->idnumber_value,'filtername'=>$employeeids->firstname];
            }
        }
        
        $dataobject = new stdClass();
        $dataobject->total_count = count($total_ids);
        $dataobject->incomplete_results = false;
        $dataobject->items = $allusers_employees;
        
        return $dataobject;
	}
	/** End of the code **/
    /** Function to get the all Supervisor List **/
	function get_supervisoridlist_filter($category) { //==For supervisor filter===//
	    global $DB;
	 
	    if($category=='agent')
			$supervisoridlist = $DB->get_records_sql("SELECT u.*,ud.* FROM {user} u JOIN {local_userdata} ud ON u.id =ud.supervisorid WHERE u.id >1 AND u.deleted=0 AND u.suspended=0 AND ud.supervisorid!='null'   Group by ud.supervisorid");
	    else
	       $supervisoridlist = $DB->get_records_sql("SELECT u.*,ud.* FROM {user} u JOIN {local_userdata} ud ON u.id =ud.supervisorid WHERE u.id >1 AND u.deleted=0 AND u.suspended=0 AND ud.supervisorid!='null'  Group by ud.supervisorid");
	       
		  if(!empty($supervisoridlist)){
		  $option=array('-1' => get_string('all'));
		  }         
	    foreach($supervisoridlist  as $supervisor) {
	        $option[$supervisor->supervisorid]=$supervisor->firstname.' '.$supervisor->lastname;
	   }
	   return $option;
	}
	/** End of the code **/
	/** Function to get the Designation **/
	public function get_designation(){
	    global $DB;
	   
	     $users_emails = $DB->get_records('local_userdata');
	     $allusers_emails = array();
	     $allusers_emails['-1'] = get_string('all');
	    if($users_emails){
	        foreach($users_emails as $users_email){
	              $allusers_emails["$users_email->subdepartment"]=$DB->get_field('local_costcenter','fullname',array('id'=>$users_email->subdepartment));
	        }
	    }
	    return $allusers_emails;
	}
	 
	 /** Function to get the all userid's and their fullnames **/
	 /*
	  * @abstract: mostly used for the users list in select box
	  * @return:
	  *	admin-logged in: all the users irrespective of roles or departments
	  *	anyother user-logged in: all the users in his department
	 */
	public function get_all_users_id_fullname($costcenter=false,$like = false,$page = false,$planid = false, $filterpage = false){
		global $DB, $CFG, $USER;
		$users = $DB->get_record('local_learningplan',array('id'=>$planid));
	     $us = $users->band;
		$array = explode(',',$us);
		$list = implode("','",$array);
		
		$sql="SELECT u.id, CONCAT(u.firstname, ' ',u.lastname) AS fullname
			FROM {user} as u WHERE u.id >1 AND u.deleted=0 AND u.suspended=0  ";
		
		if($planid!=0){
			$batch_users=$DB->get_fieldset_sql("SELECT userid FROM {local_learningplan_user} WHERE planid = {$planid} ");
		}
		array_push($batch_users, 1);
		$batch_userss = implode(',',$batch_users);
		if(!empty($batch_userss)){
			$sql .=' AND u.id not in(' . $batch_userss . ') ';
		}
		
		if($users->costcenter){
			$sql .=' AND u.open_costcenterid IN ('.$users->costcenter.') ';
		}else{
			//$sql .='';
		}
		if($users->department!=''){
			$sql .=' AND u.department IN ('.$users->department.') ';
		}else{
			$sql.=' AND u.department!="" ' ;
		}
		if($users->subdepartment!=''){
			$sql .=' AND u.open_subdepartment IN ('.$users->subdepartment.') ';
		}else{
			$sql.=' AND u.open_subdepartment!="" ';
		}
		if($users->band!=''){
			$sql .=" AND u.open_band IN('$list')";
		}else{
			$sql .=' AND u.open_band!="" ';
		}
		if(!$planid){
				if(is_siteadmin()){
				$sql .=" ";
				}else{
				$sql .=" and u.open_costcenterid=$costcenter ";
				}
		}
		
		
		if($like){
			$sql .= " AND (u.firstname LIKE '%%$like%%' OR u.lastname LIKE '%%$like%%')";
		}
		
		$totalusers = $DB->get_records_sql($sql);
		if($page){
			$page = $page-1;
			$length = $page*50;
			$sql .= " LIMIT $length, 50";
		}else{
			$sql .= " LIMIT 0,50";
		}
		$users = $DB->get_records_sql($sql);
		
		$fullnames = array();
		if($users){
		    foreach($users as $user){
			   $fullnames[] = ['id'=>$user->id,'filtername'=>$user->fullname];
		    }
		}
		
		$dataobject = new stdClass();
		$dataobject->total_count = count($totalusers);
		$dataobject->incomplete_results = false;
		$dataobject->items = $fullnames;
		
		return $dataobject;
	}
	 
	public function get_category_list($costcenter){
		global $DB, $CFG, $USER;
		//$systemcontext = context_system::instance();
		$systemcontext = (new \local_courses\lib\accesslib())::get_module_context();
		if (!is_siteadmin() && has_capability('local/costcenter:assign_multiple_departments_manage', $systemcontext)){

		$catid= $DB->get_field('local_costcenter','category',array('shortname'=>'ACD'));
		}elseif(!is_siteadmin() && has_capability('local/courses:enrol', $systemcontext)){
		/** For The Course Manager both the acd and other costcenter categories should come in Dropdown **/
		$cat1= $DB->get_field('local_costcenter','category',array('shortname'=>'ACD'));
		$cat2=$DB->get_field('local_costcenter','category',array('id'=>$costcenter));
		$catid=$cat1.','.$cat2;
		}else{
		$catid=$DB->get_field('local_costcenter','category',array('id'=>$costcenter));
		}
		 
		$sql="SELECT id,name  from {course_categories} where id IN ($catid) or parent IN ($catid)";
		$depts=$DB->get_records_sql_menu($sql);
		if($depts){
			foreach($depts as $key => $dept){
				$departments["$key"] = $dept;
			}
		}
		return $departments;
	}
	public function category_list(){
		global $DB, $CFG, $USER;
		$sql="SELECT id,name  from {course_categories} where 1=1";
		$depts=$DB->get_records_sql_menu($sql);
		if($depts){
			foreach($depts as $key => $dept){
		 		$departments["$key"] = $dept;
			}
		}
		return $departments;
	}
	public function get_costcenters(){
	    global $DB;
	    $departments = array();
	    $departments['-1'] = get_string('all');
	    $depts = $DB->get_records_sql_menu('SELECT id,fullname from {local_costcenter} where visible =1 and parentid IN(0,1)');
	    if($depts){
	        foreach($depts as $key => $dept){
	            $departments["$key"] = $dept;
	        }
	    }
	    return $departments;
	}
}