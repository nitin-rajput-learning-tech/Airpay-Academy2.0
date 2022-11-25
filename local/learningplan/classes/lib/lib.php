<?php
namespace local_learningplan\lib;
use context_module;
use file_encode_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use context_system;
use html_writer;
use html_table;
use moodle_url;
// use \local_learningplan\notifications_emails as learningplannotifications_emails;
class lib {
    function __construct(){
        global $DB, $CFG, $OUTPUT,  $USER, $PAGE;
        $this->db=$DB;
        $this->user=$USER;
    }
    
    function create_learning_plan($data){
		global $DB, $USER;
		$systemcontext = (new \local_users\lib\accesslib())::get_module_context();
		$data->description = $data->description['text'];
        $data->usercreated =  $this->user->id;
		$data->timecreated = time();
		$data->visible = 1;
		 $learningplan->department=-1;
		if($data->summaryfile){
			
			file_save_draft_area_files($data->summaryfile, $systemcontext->id, 'local_learningplan', 'summaryfile', $data->summaryfile);
		}
		if ((!is_siteadmin() && (((! has_capability('local/costcenter:manage_multiorganizations', $systemcontext))) &&(!has_capability('local/costcenter:manage_owndepartments',$systemcontext))))) {
			$data->department=-1;
		}elseif (is_siteadmin() || ((! has_capability('local/costcenter:manage_multiorganizations', $systemcontext))&&(has_capability('local/costcenter:manage_owndepartments',$systemcontext)))) {
			$data->department=$USER->open_departmentid;
		}
		$return = $this->db->insert_record('local_learningplan', $data);
		// Update evaluation tags.
	    if (isset($data->tags)) {
	        \local_tags_tag::set_item_tags('local_learningplan', 'learningplan', $return, $systemcontext, $data->tags, 0, $data->costcenter, $data->department);
	    }
		return $return;
    }
	
	function update_learning_plan($data){
		global $DB;
		if($data->description){
			$data->description = $data->description['text'];
		}
		if($data->form_status==1){

		  $data->open_group =(!empty($data->open_group)) ? implode(',',array_filter($data->open_group)) :NULL;
		    if(!empty($data->open_group)) {
                $data->open_group = $data->open_group;
            } else {
                $data->open_group = NULL;
            }
          $data->open_hrmsrole =(!empty($data->open_hrmsrole)) ? implode(',',array_filter($data->open_hrmsrole)) :NULL;
          	if(!empty($data->open_hrmsrole)) {
                $data->open_hrmsrole = $data->open_hrmsrole;
            } else {
                $data->open_hrmsrole = NULL;
            }
          $data->open_designation =(!empty($data->open_designation)) ? implode(',',array_filter($data->open_designation)) :NULL;
          	if(!empty($data->open_designation)) {
                $data->open_designation = $data->open_designation;
            } else {
                $data->open_designation = NULL;
            }
          $data->open_location =(!empty($data->open_location)) ? implode(',',array_filter($data->open_location)) :NULL;
          	if(!empty($data->open_location)) {
                $data->open_location = $data->open_location;
            } else {
                $data->open_location = NULL;
            }
          	if(in_array(-1, $data->department) || $data->department == -1){
          		$data->department = -1;
          	}else{
	            if(is_array ($data->department)){            
	                $data->department = !empty($data->department) ? implode(',', $data->department) : -1;
	            }else{
	                 $data->department = !empty($data->department) ? $data->department : -1;
	            }
	        }
            if(in_array(-1, $data->subdepartment) || $data->subdepartment == -1){
            	$data->subdepartment = -1;
            }else{
	            if(is_array ($data->subdepartment)){            
	                $data->subdepartment = !empty($data->subdepartment) ? implode(',', $data->subdepartment) : -1;
	            }else{
	                 $data->subdepartment = !empty($data->subdepartment) ? $data->subdepartment : -1;
	            }
	        }
		}else{
			if($data->map_certificate == 1 || !empty($existinfsecr->certificateid) ){
				$data->certificateid = $data->certificateid;
			}else{
				$data->certificateid = null;
			}
		}
		$data->usermodified =  $this->user->id;
		$data->timemodified = time();
		$existingsummaryfile = $this->db->get_field('local_learningplan', 'summaryfile', array('id' => $data->id));
		if($data->summaryfile){
			$systemcontext = (new \local_users\lib\accesslib())::get_module_context();
			file_save_draft_area_files($data->summaryfile, $systemcontext->id, 'local_learningplan', 'summaryfile', $data->summaryfile);
		}
		if(!empty($data->id)){
			$return = $this->db->update_record('local_learningplan', $data);
		}
		// Update evaluation tags.
	    if (isset($data->tags)) {
	        \local_tags_tag::set_item_tags('local_learningplan', 'learningplan', $data->id, (new \local_users\lib\accesslib())::get_module_context(), $data->tags, 0, $data->costcenter, $data->department);
	    }
		return $data->id;
    }

    /**
     * [get_enrollable_users_to_learningplan description]
     * @param  [int] $planid [learningpath id]
     * @return [object]         [object of id's of users]
     */
    public function get_enrollable_users_to_learningplan($planid){
    	global $DB,$USER;
		
		if(!is_siteadmin()){
			$siteadmin_sql=" AND u.suspended =0
								 AND u.deleted =0  AND u.open_costcenterid = $USER->open_costcenterid ";
		}else{
			$siteadmin_sql="";
		}
		
    	$plan_info = $DB->get_record('local_learningplan',array('id' => $planid));

	    $sql = "SELECT u.id FROM {user} AS u WHERE u.id > 2 {$siteadmin_sql} AND u.id not in ($USER->id) ";

	         

		if($plan_info->department !== null && $plan_info->department !== '-1'&& $plan_info->department !== 0){
				$sql.= ' AND u.open_departmentid IN('.$plan_info->department.')';
		}
		if($plan_info->subdepartment !== null && $plan_info->subdepartment !== '-1'&& $plan_info->subdepartment !== 0){
			$sql.= ' AND u.open_subdepartment IN('.$plan_info->subdepartment.')';	
		}
    // OL-1042 Add Target Audience to Classrooms//
	   $params = array();
            if(!empty($plan_info->open_group)){
                $group_list = $DB->get_records_sql_menu("SELECT cm.id, cm.userid from {cohort_members} cm, {user} u where u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0 AND cm.cohortid IN ({$plan_info->open_group})");
                 
                 $groups_members = implode(',', $group_list);
                 if (!empty($groups_members))
                 $sql .=" AND u.id IN ({$groups_members})";
                 else
                 $sql .=" AND u.id =0";
                 
            }                         
            if(!empty($plan_info->open_hrmsrole)){
				 // $implode_result=implode("\",\"",explode(',',$plan_info->open_hrmsrole));
        		$params['hrmsrole_like'] = ','.$plan_info->open_hrmsrole.',';
            	$sql .= " AND :hrmsrole_like LIKE CONCAT('%,',u.open_hrmsrole,',%')";
            }
            if(!empty($plan_info->open_designation)){
				// $implode_result=implode("\",\"",explode(',',$plan_info->open_designation));
				$params['designation_like'] = $plan_info->open_designation;
    //             $sql .= " AND u.open_designation IN(\"{$implode_result}\")";
            	   $sql .= " AND :designation_like LIKE CONCAT('%,',u.open_designation,',%')";
            }
            if(!empty($plan_info->open_location)){
				// $implode_result=implode("\",\"",explode(',',$plan_info->open_location));
				$params['location_like'] = ','.$plan_info->open_location.',';
                $sql .= " AND :location_like LIKE CONCAT('%,', u.city, ',%')";
            }
        // OL-1042 Add Target Audience to Classrooms//
		$sql .= " AND u.id NOT IN (SELECT userid FROM {local_learningplan_user} WHERE planid=$planid)";
	    $users_info = $DB->get_records_sql($sql,$params);

	    return $users_info;
    }

    public function get_enrollable_users_count_to_learningplan($planid){
    	global $DB,$USER;
		if(!is_siteadmin()){
			$siteadmin_sql=" AND u.suspended =0
							AND u.deleted =0  AND u.open_costcenterid = $USER->open_costcenterid ";
		}else{
			$siteadmin_sql="";
		}
    	$plan_info = $DB->get_record('local_learningplan',array('id' => $planid));

	    $sql = "SELECT count(u.id) FROM {user} AS u 
	    		WHERE u.id > 2 $siteadmin_sql AND u.id not in ($USER->id) ";

	    $params = array();
		if($plan_info->department !== null && $plan_info->department !== '-1'&& $plan_info->department !== 0 && !empty($plan_info->department)){
			$params['dept'] = '%,'.$plan_info->department.',%';
			$sql.= " AND :dept LIKE CONCAT('%,',u.open_departmentid,',%') ";
		}
		if($plan_info->subdepartment !== null && $plan_info->subdepartment !== '-1'&& $plan_info->subdepartment !== 0){	
			$params['subdept'] = '%,'.$plan_info->subdepartment.',%';
			$sql.= " AND :subdept LIKE CONCAT('%,',u.open_subdepartment,',%') ";	
		}
		
         // OL-1042 Add Target Audience to Classrooms//
	   	
        if(!empty($plan_info->open_group)){
            $group_list = $DB->get_records_sql_menu("select cm.id, cm.userid 
            									from {cohort_members} cm, {user} u 
            									where u.id = cm.userid AND u.deleted = 0 
            									AND u.suspended = 0 AND cm.cohortid IN 
            									({$plan_info->open_group})");
             
            $groups_members = implode(',', $group_list);
            if (!empty($groups_members))
             	$sql .=" AND u.id IN ({$groups_members})";
            else
             	$sql .=" AND u.id =0";
             
        }                         
        if(!empty($plan_info->open_hrmsrole)){
        	$params['hrmsrole_like'] = ','.$plan_info->open_hrmsrole.',';
        	$sql .= " AND :hrmsrole_like LIKE CONCAT('%,',u.open_hrmsrole,',%')  ";
        }
        if(!empty($plan_info->open_designation)){
        	$params['designation_like'] = ','.$plan_info->open_designation.',';
            $sql .= " AND :designation_like LIKE CONCAT('%,',u.open_designation,',%') ";	
        }	
        if(!empty($plan_info->open_location)){
            $params['location_like'] = ','.$plan_info->open_location.',';
            $sql .= " AND :location_like LIKE CONCAT('%,',u.city,',%') ";
        }
        // OL-1042 Add Target Audience to Classrooms//
	
	    // $existing_user_sql = " SELECT id,userid FROM {local_learningplan_user} WHERE planid=:planid";
	    // $existing_users = $DB->get_records_sql_menu($existing_user_sql,array('planid' => $planid));
	    // $existing_userids = implode(',',$existing_users);
	    // if(!empty($existing_userids)){
	    	$sql .= " AND u.id NOT IN (SELECT userid FROM {local_learningplan_user} WHERE planid=$planid ) ";
	    //}
	    $users_info = $DB->count_records_sql($sql,$params);

	    return $users_info;
    }



    /**
     * for activating or deactivating a learningplan.
     * @param  [int] $id [id of the learning plan]
     * @return [bool] true
     */
    function togglelearningplan($id){
    	$visible = $this->db->get_field('local_learningplan', 'visible', array('id' => $id));
    	if($visible){
    		$this->db->execute("UPDATE {local_learningplan} SET visible = 0 WHERE id = $id");
    		$status = 0;
    	}else{
    		$this->db->execute("UPDATE {local_learningplan} SET visible = 1 WHERE id = $id");
    		$status = 1;
    	}
    	if(class_exists('\block_trending_modules\lib')){
            $dataobject = new stdClass();
            $dataobject->update_status = True;
            $dataobject->id = $id;
            $dataobject->module_type = 'local_learningplan';
            $dataobject->module_visible = $status;
            $class = (new \block_trending_modules\lib())->trending_modules_crud($dataobject, 'local_learningplan');
        }
    	return true;
    }
	
	function delete_learning_plan($id){
		
		if($id > 0){
			$this->db->delete_records('local_learningplan', array('id' => $id));
			$this->db->delete_records('local_learningplan_user', array('planid' => $id));
			$this->db->delete_records('local_learningplan_courses', array('planid' => $id));

			$params = array(
                'context' => (new \local_users\lib\accesslib())::get_module_context(),
                'objectid' => $id,
                'userid' => $USER->id,
                'relateduserid' => $USER->id,
                'other' => array('userid' => $userid, 'learningplanid' => $id)
            );
            $event = \local_learningplan\event\learningplan_deleted::create($params);
            $event->add_record_snapshot('local_learningplan', $id);
            $event->trigger();
		}
	}
	
	function learningplan_courses_list($id){
		global $DB,$USER;
		$systemcontext = (new \local_users\lib\accesslib())::get_module_context();
		
		if(is_siteadmin() /*|| has_capability('local/costcenter:manage_multiorganizations', $systemcontext)|| has_capability('local/costcenter:assign_multiple_departments_manage', $systemcontext)*/){
			$costcenterid = $DB->get_field('local_learningplan', 'costcenter', array('id' => $id));
			$sql = "SELECT c.id as id, c.fullname FROM {course} as c
					WHERE c.id > 1 AND c.visible = 1  "; //FIND_IN_SET(4,c.open_identifiedas)
			if($costcenterid){		
					$sql.=" AND c.open_costcenterid=$costcenterid";
			}
			$courses = $DB->get_records_sql_menu($sql);
		}else{
			$course_sql = "SELECT c.id as id, c.fullname
							FROM {course} as c
							WHERE c.id > 1 AND c.visible = 1  "; //and FIND_IN_SET(4,c.open_identifiedas)
			
				   $course_sql.=" AND 
							c.open_costcenterid=$USER->open_costcenterid";
				  if (!has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
				  		$course_sql.=" AND 
							c.open_departmentid=$USER->open_departmentid";
				  }
			$courses = $DB->get_records_sql_menu($course_sql);
		}
		return $courses;
	}
	// AND concat(',',c.open_identifiedas,',') LIKE '%,4,%'
	function assign_courses_to_learningplan($data){
		
		$this->db->insert_record('local_learningplan_courses', $data);
		return 'courses added to learningplan';
	}
	
	function delete_courses_to_learningplan($data){
		
		
		$get=$this->db->get_records('local_learningplan_courses',array('planid'=>$data->planid));
		$this->db->delete_records('local_learningplan_courses', array('id' => $data->id, 'planid' => $data->planid, 'courseid' => $data->courseid));
		$get_coures=$this->db->get_records('local_learningplan_courses',array('planid'=>$data->planid));
		$i=0;
		foreach($get_coures as $get){
			
			$data = new stdClass();
			$data->id=$get->id;
			$data->planid = $get->planid;
			$data->courseid = $get->courseid;
			$data->nextsetoperator=$get->nextsetoperator;
			$data->timecreated = time();
			$data->usercreated =  $this->user->id;
			$data->timemodified = 0;
			$data->usermodified = 0;
			$data->sortorder=$i;
            
		
			$this->db->update_record('local_learningplan_courses', $data);
			$i++;
		}	
	}

	function unassign_delete_courses_to_learningplans($courseid,$planid){
		$course_record = $this->db->get_record('local_learningplan_courses', array('planid' => $planid, 'id' => $courseid));
		if(!empty($course_record)){
			/*If record found then we start for delete the course*/
			$delete_data = new stdClass();
			$delete_data->id = $course_record->id;
			$delete_data->planid = $planid;
			$delete_data->courseid = $course_record->courseid;
			$delete_record = $this->delete_courses_to_learningplan($delete_data);
		}
	}
	function unassign_delete_users_to_learningplans($userid,$planid){
		$user_record = $this->db->get_record('local_learningplan_user', array('planid' => $planid, 'userid' => $userid));
		if(!empty($user_record)){
			/*If record found then we start for delete the course*/
			$delete_data = new stdClass();
			$delete_data->id = $user_record->id;
			$delete_data->planid = $planid;
			$delete_data->userid = $user_record->userid;
			$delete_record = $this->delete_users_to_learningplan($delete_data);
		}
	}
	
	function get_learningplan_assigned_courses($planid){
		global $DB;
		if($planid){
		$sql = "SELECT c.*,lc.sortorder,lc.id as lepid,lc.nextsetoperator as next
					FROM {local_learningplan_courses} lc
					JOIN {course} c ON c.id = lc.courseid
					WHERE lc.planid = {$planid} ORDER BY lc.sortorder ASC" ;
		$courses = $DB->get_records_sql($sql);
		
		}else{
			$courses=false;
		}
		return $courses;
	}
	
	function assign_users_to_learningplan($data){
		global $DB, $CFG, $USER;
		if(file_exists($CFG->dirroot.'/local/lib.php')){
			require_once($CFG->dirroot.'/local/lib.php');
		}
		// require_once($CFG->dirroot.'/local/learningplan/notifications_emails.php');
		$check = $this->db->get_records('local_learningplan_user',array('userid'=>$data->userid,'planid'=>$data->planid));
		$type = 'learningplan_enrol';
      	$dataobj = $data->planid;
      	$fromuserid = 2;
   	if(!$check){
	    $data->timemodified = time();
	    $data->timecreated = time();
		$user=$this->db->insert_record('local_learningplan_user', $data);
		// $emaillogs = new learningplannotifications_emails();
  //     	$email_logs = $emaillogs->learningplan_emaillogs($type,$dataobj,$data->userid,$fromuserid);
		$notification = new \local_learningplan\notification();
		$touser = \core_user::get_user($data->userid);
		$fromuser = \core_user::get_user(2);
		$learningplaninstance = $DB->get_record('local_learningplan', array('id' => $data->planid));
		$email_logs = $notification->learningplan_notification($type, $touser, $fromuser, $learningplaninstance);
		if($user){
			$approvalid=$this->db->get_record('local_learningplan_approval',array('planid'=>$data->planid,'userid'=>$data->userid));
			if($approvalid){
			$facetofaceinfo=$this->db->get_record('local_learningplan_approval', array('id'=>$approvalid->id));
			$facetofaceinfo->approvestatus=1;           
			$facetofaceinfo->approvedby = $this->user->id;          
			$facetofaceinfo->timemodified = time();
			$facetofaceinfo->usermodified =  $this->user->id;
			$check=$this->db->update_record('local_learningplan_approval', $facetofaceinfo);
			}
		    
		}
	}
		return 'courses added to learningplan';
	    
	}
	
	function delete_users_to_learningplan($data){
		    if($data->id){
				
			$this->db->delete_records('local_learningplan_user', array('id' => $data->id, 'planid' => $data->planid, 'userid' => $data->userid));
		}else{
			
			$id=$this->db->delete_records('local_learningplan_user',array('planid' => $data->planid, 'userid' => $data->userid));
			$this->db->delete_records('local_learningplan_user', array('id' => $id, 'planid' => $data->planid, 'userid' => $data->userid));
		}
		$approval=$this->db->get_record('local_learningplan_approval',array('planid'=>$data->planid,'userid'=>$data->userid));
		if($approval){
			
	    $approvalid= $approval->id;
        $facetofaceinfo=$this->db->get_record('local_learningplan_approval', array('id'=>$approvalid));
        $facetofaceinfo->approvestatus=2;           
        $facetofaceinfo->approvedby = $this->user->id;          
        $facetofaceinfo->timemodified = time();
        $facetofaceinfo->usermodified =  $this->user->id;
        $facetofaceinfo->reject_msg =$submitted_data->text;
		
		$dat=$this->db->update_record('local_learningplan_approval', $facetofaceinfo);
		}
		return 'Users deleted from learningplan';
	}
	
	function get_learningplan_assigned_users($planid,$requestData){
	    global $DB;
		$sql = "SELECT u.*,lu.completiondate,lu.status,lu.timecreated
					FROM {local_learningplan_user} lu
					JOIN {user} u ON u.id = lu.userid
					WHERE lu.planid = ".$planid." AND u.deleted=0 ";
		$param = [];
	     if ( $requestData['search']['value'] != "" )
			{	
			// $likesql = $this->db->sql_like('(concat(u.firstname," ",u.lastname) ', ':search',  false);
			// 	$sql .= " and {$likesql} ";
				$fields = array(/*"u.firstname", "u.lastname", */"concat(u.firstname, ' ',u.lastname)");
	            $likesql = array();
	            $i = 0;
	            foreach ($fields as $field) {
	                $i++;
	                $likesql[] = $this->db->sql_like($field, ":queryparam$i", false);
	                $param["queryparam$i"] = "%{$requestData['search']['value']}%";
	            }
	            if(!empty($likesql)){
	                $sql .= " AND ( ".implode(" OR ", $likesql)." ) ";
	            }
				// $param['search'] = "%{$requestData['search']['value']}%";
			}
			$sql .= " AND u.suspended=0 ";
	      ////added by sharath
	      $sql .= " ORDER BY lu.id DESC ";
	      //ended here by sharath

	  		if ( isset( $requestData['start'] ) && $requestData['length'] != '-1' && isset($limit) == 0 )
	      {
	         // $sql .="  LIMIT ".$requestData['start'] .", ".$requestData['length'];
			$users = $this->db->get_records_sql($sql, $param, $requestData['start'], $requestData['length']);
	      }else{
	      	$users = $this->db->get_records_sql($sql, $param);
	      }
		
		return $users;
	}
	function notification_for_user_enrol($users,$data){
		
		
		 $type="learningplan_enrol";
         $get_ilt=$this->db->get_record('local_notification_type',array('shortname'=>$type));
		       foreach($users as $to_userid){
                    $users=implode(',',$to_userid);
				   	$from = $this->db->get_record('user', array('id'=> $this->user->id));
					$data_infor=$this->db->get_record('local_learningplan',array('id'=>$data->planid));
					if($data_infor->learning_type==1){
						$type='core courses';
					}else{
						$type='elective courses';
					}
					$coursename=$this->db->get_records_menu('local_learningplan_courses',array('planid'=>$data->planid),'id','id,courseid');
					if($coursename){
						$course= implode(',',$coursename);
						$sql="SELECT id,fullname from {course} where id IN ($course)";
						$coursename=$this->db->get_records_sql_menu($sql);
						$course_names=array();
						foreach($coursename as $course){
							$course_names[]="<span>$course</span><br/>";
						}
						$course_names1=implode('',$course_names);
					}else{
						$course_names1 = 'Not Assigned';
					}
					$department=$this->db->get_field('local_costcenter','fullname',array('id'=>$data_infor->costcenter));
					 if($department==''){
                    $department="[ilt_department]";
                    }
					$sql="SELECT id, concat(firstname,' ', lastname) as fullname  from {user} where id={$data_infor->usercreated} ";   
					$creator=$this->db->get_record_sql($sql);
					$dataobj= new stdClass();
					$dataobj->lep_name=$data_infor->name;
					$dataobj->lep_course=$course_names1;
					$dataobj->course_code=$data_infor->shortname;
					$dataobj->lep_startdate= \local_costcenter\lib::get_userdate("d/m/Y H:i",$data_infor->startdate);
					$dataobj->lep_enddate= \local_costcenter\lib::get_userdate("d/m/Y H:i",$data_infor->enddate);
					$dataobj->lep_creator=$creator->fullname;
					$dataobj->lep_type=$type;
					$dataobj->lep_enroluser_username="[lep_enroluser_username]";
					$dataobj->lep_enroluseremail="[lep_enroluseremail]";
					$url = new moodle_url($CFG->wwwroot.'/local/learningplan/view.php',array('id'=>$data->planid,'couid'=>$data->planid));
                    $dataobj->lep_link = html_writer::link($url, $data_infor->name, array());
					$touserid=$to_userid;
					$fromuserid=2;
					$notifications_lib = new notifications();
					$emailtype='learningplan_enrol';
					$planid=$data->planid;			
					$notifications_lib->send_email_notification($emailtype, $dataobj, $touserid, $fromuserid,$batchid=0,$planid);
				 }
			  return true;
	}
	function get_previous_course_status($planid, $sortorder,$courseid){

		$sql = "SELECT lc.courseid 
				FROM {local_learningplan_courses} as lc
				JOIN {course} c ON c.id = lc.courseid
				WHERE lc.planid = :planid and lc.nextsetoperator='and' 
				ORDER BY lc.sortorder ASC";

		$records = $this->db->get_fieldset_sql($sql, array('planid' => $planid));
		if(count($records) > 0){
			$array_search = array_search($courseid,$records);
			if($array_search){
				$coursecompleted=$this->get_completed_lep_users($records[$array_search-1],$planid);
				if($coursecompleted){
					return true;
				}else{ 
					return false;
				}
			}else{
				return true;
			}
		}else{
			return true;
		}
	}
	function get_completed_lep_users($courseid,$planid){
		$sql="SELECT * from {course_completions} where course=$courseid and userid= {$this->user->id} and timecompleted is not NULL ";
		$get_course=$this->db->get_record_sql($sql);
		return $get_course;
	}
	public function check_courses_assigned_target_audience($user,$planid){
	
		   $users=$this->db->get_record('user',array('id'=> $this->user->id));
			 $us=$users->open_band;
			 $array=explode(',',$us);
			 $list=implode("','",$array);
			/*********changed IN to Find_in_set in query for issues 1258********/
			$sql = "SELECT ud.* FROM {local_learningplan} AS ud WHERE
			ud.id={$planid} AND (1 = CASE WHEN ud.costcenter IS NOT NULL 
				THEN WHEN CONCAT(',',ud.costcenter,',') LIKE CONCAT('%,',{$users->open_costcenterid},',%')
					THEN 1
					ELSE 0 END
				ELSE 1 END)
			AND (1 = CASE WHEN ud.department IS NOT NULL 
				THEN WHEN CONCAT(',',ud.department,',') LIKE CONCAT('%,',{$users->open_departmentid},',%') 
					THEN 1
					ELSE 0 END
				ELSE 1 END)
			AND (1 = CASE WHEN ud.subdepartment IS NOT NULL 
				THEN WHEN CONCAT(',',ud.department,',') LIKE CONCAT('%,',{$users->open_subdepartment},',%') 
					THEN 1
					ELSE 0 END
				ELSE 1 END)";
			// FIND_IN_SET('.$users->open_costcenterid.',ud.costcenter)
			// FIND_IN_SET('.$users->open_departmentid.',ud.department)
			// FIND_IN_SET('.$users->open_subdepartment.', ud.subdepartment)
			$learning_plans = $this->db->get_records_sql($sql);
			
			if($learning_plans){
				return true;
			}else{
				return false;
			}
	}
	public function to_enrol_users_check_completion($planid,$users){
		$sql = "SELECT llc.*,cc.* 
				FROM {local_learningplan_courses} AS llc
				JOIN {course} c ON llc.courseid = c.id
				JOIN {course_completions} AS cc ON 	cc.course = llc.courseid
				WHERE llc.planid = ".$planid." AND cc.userid = $users AND cc.timecompleted!='NULL' 
				ORDER BY llc.id DESC LIMIT 1";
		$check=$this->db->get_record_sql($sql);
		if($check){
			$sort=$check->sortorder+1;	
			$sql="SELECT llc.* 
				FROM {local_learningplan_courses} llc
				JOIN {course} c ON llc.courseid = c.id
				WHERE llc.planid = ".$planid." and llc.sortorder = $sort ";
			$record = $this->db->get_record_sql($sql);
			
			if($record){
				$enrol_manual = enrol_get_plugin('learningplan');
				$sql = "SELECT * 
						FROM {enrol} 
						WHERE courseid = ".$record->courseid." and enrol='learningplan'";
					
				$instance=$this->db->get_record_sql($sql);
				if($instance){ 
					$roleid=$instance->roleid;
					$timestart=0;
					$timeend=0;
					$enrol_manual->enrol_user($instance, $users, $roleid, $timestart, $timeend);
				}
			}
		}
	}
	public function complete_the_lep($planid,$user){
			if($planid){
				$sql="SELECT llc.courseid as id, llc.courseid 
						FROM {local_learningplan_courses} as llc 
						JOIN {course} as c ON llc.courseid = c.id
						JOIN {local_learningplan_user} as llu ON llc.planid = llu.planid 
						WHERE llc.planid=$planid AND llc.nextsetoperator='and' 
						AND llu.userid = $user ";
			$courses=$this->db->get_records_sql_menu($sql);
			$check=array();
			$completed=array();
			$optional_completed=array();
			if($courses){
				foreach($courses as $course){
					$sql="SELECT id 
						FROM {course_completions} 
						WHERE course={$course} AND userid= $user 
						AND timecompleted IS NOT NULL";
					$check = $this->db->get_record_sql($sql);
					if($check){
						$completed[]=1;
					}else{
						$completed[]=0;
					}			
				}
			}else{
				$sql="SELECT llc.courseid as id, llc.courseid 
						FROM {local_learningplan_courses} as llc 
						JOIN {course} as c ON llc.courseid = c.id
						JOIN {local_learningplan_user} as llu on llc.planid=llu.planid 
						WHERE llc.planid = $planid AND llu.userid=$user ";
				$courses = $this->db->get_records_sql_menu($sql);
				foreach($courses as $course){
					$sql="SELECT id 
							FROM {course_completions} 
							WHERE course=:course AND userid=:user 
							AND timecompleted IS NOT NULL";
					$check=$this->db->get_record_sql($sql, array('course' => (int)$course, 'user' => (int) $user));
					if($check){
						$optional_completed[]=1;
					}else{
						$optional_completed[]=0;
					}		
				}
			}
			if($completed){
				if (in_array("0", $completed)){
				}else{
					$date=time();
					$sql="SELECT * 
							FROM {local_learningplan_user} 
							WHERE planid=$planid AND userid=$user";
					$learnigplanuser=$this->db->get_record_sql($sql);
				
					if($learnigplanuser){
						$condition=$this->db->get_field('local_learningplan_user','id',array('id'=>$learnigplanuser->id,'status'=>1));
						if(empty($condition)){
							$sql="UPDATE {local_learningplan_user} SET completiondate='$date' WHERE id=".$learnigplanuser->id."";
							$data=$this->db->execute($sql);
						
							$sql_1="UPDATE {local_learningplan_user} SET status='1' WHERE id=".$learnigplanuser->id."";
							$data_1=$this->db->execute($sql_1); 
						
							$emailtype="learningplan_completion";
							$status="Completed";
							$params = array(
			                    'context' => (new \local_users\lib\accesslib())::get_module_context(),
			                    'objectid' => $planid,
			                    'courseid' => 1,
			                    'userid' => $user,
			                    'relateduserid' => $user,
			                );
			                $event = \local_learningplan\event\learningplan_user_completed::create($params);
			                $event->add_record_snapshot('local_learningplan', $planid);
			                $event->trigger();
							// $this->to_send_request_notification($id,$emailtype,$status,$planid);
							$learningplaninstance=$this->db->get_record('local_learningplan',array('id'=>$learnigplanuser->planid));
							$touser = \core_user::get_user($user);
							$fromuser = \core_user::get_user(2);
							$learningplan_notification = new \local_learningplan\notification();
							$learningplan_notification->learningplan_notification($emailtype, $touser, $fromuser, $learningplaninstance);
						}
					}
				}
			}
			if($optional_completed){
				if (in_array("1", $optional_completed)){
					$date=time();
					$sql="SELECT * 
						FROM {local_learningplan_user} 
						WHERE planid=$planid and userid=$user";
					$learnigplanuser = $this->db->get_record_sql($sql);
					
					if($learnigplanuser){
						$condition=$this->db->get_field('local_learningplan_user','id',array('id'=>$learnigplanuser->id,'status'=>1));
					
						if(empty($condition)){
							$sql="UPDATE {local_learningplan_user} 
									SET completiondate='$date' 
									WHERE id=".$learnigplanuser->id."";
							$data=$this->db->execute($sql);
							
							$sql_1="UPDATE {local_learningplan_user} SET status='1' WHERE id=".$learnigplanuser->id."";
							$data_1=$this->db->execute($sql_1); 
							$emailtype="learningplan_completion";
							$status="Completed";
							$params = array(
			                    'context' => (new \local_users\lib\accesslib())::get_module_context(),
			                    'objectid' => $planid,
			                    'courseid' => 1,
			                    'userid' => $user,
			                    'relateduserid' => $user,
			                );
			                $event = \local_learningplan\event\learningplan_user_completed::create($params);
			                $event->add_record_snapshot('local_learningplan', $planid);
			                $event->trigger();
							// $this->to_send_request_notification($id,$emailtype,$status,$planid); $touser, $USER, $learningplaninstance
							$learningplaninstance=$this->db->get_record('local_learningplan',array('id'=>$learnigplanuser->planid));
							$touser = \core_user::get_user($user);
							$fromuser = \core_user::get_user(2);
							$learningplan_notification = new \local_learningplan\notification();
							$learningplan_notification->learningplan_notification($emailtype, $touser, $fromuser, $learningplaninstance);
						}
					}
				}
			}
		}
	}
public function to_enrol_users($planid,$userid,$course_enrol){
	    $sql="SELECT * from {local_learningplan_courses} where planid=$planid and courseid=$course_enrol";
		$record=$this->db->get_record_sql($sql);
					
		foreach($record as $single){
			
			$enrol_manual = enrol_get_plugin('learningplan');
			$sql="SELECT * from {enrol} where courseid=".$course_enrol." and enrol='learningplan'";
			$instance=$this->db->get_record_sql($sql);
			if($instance){		
			$roleid=$instance->roleid;
			$timestart=$this->db->get_field('course','startdate',array('id'=>$course_enrol));
			$timeend=0;
			$enrol_manual->enrol_user($instance, $userid, $roleid, $timestart, $timeend);
			}else{
			 echo "Please contact the admin and enrol the course";	
			}
		}
		$plan_url = new \moodle_url('/course/view.php', array('id' => $course_enrol));
        redirect($plan_url);	
}
public function to_send_request_notification($data,$emailtype,$status,$planid){
	 global $DB, $CFG;
	   	$from = "2";
		$data_infor=$this->db->get_record('local_learningplan',array('id'=>$data->planid));
		$completion_date=$this->db->get_field('local_learningplan_user','completiondate',array('userid'=>$data->userid,'planid'=>$data->planid));
		
		$coursename=$this->db->get_records_menu('local_learningplan_courses',array('planid'=>$data->planid),'id','id,courseid');
		if($coursename){
			$course= implode(',',$coursename);
			$sql="SELECT id,fullname from {course} where id IN ($course)";
			$coursename=$this->db->get_records_sql_menu($sql);
			$course_names=array();
			foreach($coursename as $course){
			$course_names[]="<span>$course</span><br/>";
			}
			$course_names1=implode('',$course_names);
		}else{
			$course_names1="course still not assigned";
		}
		if($data_infor->learning_type==1){
			$type='core courses';
		}else{
			$type='elective courses';
		}
		$department=$this->db->get_field('local_costcenter','fullname',array('id'=>$data_infor->costcenter));
		 if($department==''){
	    }
		$sql="SELECT id, concat(firstname,' ', lastname) as fullname  from {user} where id=$data_infor->usercreated";   
		$creator=$this->db->get_record_sql($sql);
	   
		
		$dataobj= new stdClass();
		$dataobj->lep_name=$data_infor->name;
		$dataobj->lep_course=$course_names1;
		$dataobj->course_code=$data_infor->shortname;
		$dataobj->lep_startdate= \local_costcenter\lib::get_userdate("d/m/Y H:i",$data_infor->startdate);
		$dataobj->lep_enddate= \local_costcenter\lib::get_userdate("d/m/Y H:i",$data_infor->enddate);
		
		$dataobj->lep_enroluser_username=$this->db->get_field('user','username',array('id'=>$data->userid));
		$dataobj->lep_enroluseremail=$this->db->get_field('user','email',array('id'=>$data->userid));
		$dataobj->lep_status=$status;
		$dataobj->lep_creator=$creator->fullname;
		if($emailtype=='learningplan_enrol' || $emailtype=='lep_nomination' || $emailtype=='learningplan_completion' || $emailtype=='lep_approvaled'){
		$url = new moodle_url($CFG->wwwroot.'/local/learningplan/view.php',array('id'=>$data->planid,'couid'=>$data->planid));
	    $dataobj->lep_link = html_writer::link($url, $data_infor->name, array());
		}
		if($emailtype=='lep_approval_request' || $emailtype=='lep_rejected'){
		$url = new moodle_url($CFG->wwwroot.'/local/learningplan/view.php',array('id'=>$data->planid));
	    $dataobj->lep_link = html_writer::link($url, $data_infor->name, array());
		
		$reject=$this->db->get_field('local_learningplan_approval','reject_msg',array('planid'=>$data->planid,'userid'=>$data->userid));
		$dataobj->lep_rejectmsg=$reject;
		}
}
	
	/**
     * Returns url/path of the learningplan summaryfile if exists, else false
     *
	 * @param int $lpanid, local_learningplan id
     */
	function get_learningplansummaryfile($lpanid){
		global $CFG, $DB;
       
		
		$imgurl = false;
		
        $fileitemid = $DB->get_field('local_learningplan', 'summaryfile', array('id'=>$lpanid));
		
		if(!empty($fileitemid)){
 			$sql = "SELECT * FROM {files} WHERE itemid = $fileitemid AND filename != '.' ORDER BY id DESC ";
			$filerecord = $DB->get_record_sql($sql);
		}	
			if($filerecord!=''){
				
			$imgurl = $CFG->wwwroot."/pluginfile.php/" . $filerecord->contextid . '/' . $filerecord->component . '/' .$filerecord->filearea .'/'.$filerecord->itemid. $filerecord->filepath. $filerecord->filename;
			}
			if(empty($imgurl)){
			
			$dir = $CFG->wwwroot.'/local/costcenter/pix/course_images/image3.jpg';
			for($i=1; $i<=10; $i++) {
				$image_name = $dir;
				$imgurl = $image_name;
				break;
			}
		}
		
		return $imgurl;
	}
	
	/**
     * Returns function for get learnigplan courses count
     *
	 * @param int $planid, local_learningplan id
	 * @param text $mandatory optional, and/or
     */
	function learningplancourses_count($planid, $mandatory = null){
		global $DB;
		$sql = "SELECT COUNT(lc.id)
					FROM {local_learningplan_courses} lc
					JOIN {course} c ON c.id = lc.courseid
					WHERE lc.planid = ".$planid." " ;
					
		if($mandatory == 'and'){
			$sql .= "AND lc.nextsetoperator = 'and' ";
		}elseif($mandatory == 'or'){
			$sql .= "AND lc.nextsetoperator = 'or' ";
		}
		
		$coursescount = $DB->count_records_sql($sql);
		return $coursescount;
	}

	//$data is object for need to take module type and instnce of the module added by sharath
    function modal_lpcourse_enrol($new_plan_courses,$planid){
		global $USER;
		//below two lines also commented by sharath because it is not using in this function

		//$existing_plan_courses_record = $this->db->get_records('local_learningplan_courses', array('planid'=> $planid));
	    //$existing_plan_timecreated = $this->db->get_record('local_learningplan_courses', array('planid'=> $planid));
	   if(!empty($new_plan_courses)){ 
	      	foreach($new_plan_courses as $plan_course){
		        $i=0;
		        $data = new stdClass();
		        $data->planid = $planid;
		        $data->courseid = $plan_course;
		        // $data->instance = $plan_course;
		        // $data->moduletype = $submitdata['moduletype'];
		        $data->nextsetoperator='or';
		        $data->timecreated = time();
		        $data->usercreated = $USER->id;
		        $data->timemodified = 0;
		        $data->usermodified = 0;
		         /**Check The sort order max and insert next value**/
		        $sql="select  MAX(sortorder) as sort from {local_learningplan_courses} where planid=$planid";
		        $last_order=$this->db->get_record_sql($sql);
		       // var_dump($sql);exit;
		                
		        if($last_order->sort>=0 && $last_order->sort!=''){
		            $i=$last_order->sort+1;
		            $data->sortorder=$i;
		        }else{       
		            $data->sortorder=$i;
		            $i++;     
		        }
		        $create_record = $this->assign_courses_to_learningplan($data);


		        // print_r($create_record);
	      	}
		}
	}
	/**
	 * function to get the count to learningplans of a specific user 
	 * @param  [INT] $userid [user id for whom the count of learning plan is required]
	 * @return [INT]         [count of user enrolled learningplan]
	 */
	function enrol_get_users_learningplan_count($userid){
		global $DB;
		$learningplan_sql = "SELECT count(id) FROM {local_learningplan_user} WHERE userid = :userid";
		$learningplan_count = $DB->count_records_sql($learningplan_sql, array('userid' => $userid));
		return $learningplan_count;
	}
	function enrol_get_users_learningplans($userid){
		global $DB;
		$learningplan_sql = "SELECT lp.id,lp.name,lp.description FROM {local_learningplan} AS lp
							JOIN {local_learningplan_user} AS lpu ON lp.id = lpu.planid
							WHERE lpu.userid = :userid";
		$learningplans = $DB->get_records_sql($learningplan_sql, array('userid' => $userid));
		return $learningplans;

	}
}
