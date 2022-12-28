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
 * List the tool provided 
 *
 * @package   define role hr and ast functions
 * @subpackage  local
 * @author  2016 hameed@eabyas.in
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class user_course_details {
		
	function total_course_activities($courseid) {
        global $DB, $USER, $CFG;
        if(empty($courseid)){
            return false;
        }
		$sql="SELECT COUNT(ccc.id) as totalactivities FROM {course_modules} ccc WHERE ccc.course={$courseid}";
		$total_activitycount = $DB->get_record_sql($sql);
		$out = $total_activitycount->totalactivities;
        return $out;
    }
	
	function user_course_completed_activities($courseid, $userid) {
        global $DB, $USER, $CFG;
        if(empty($courseid) || empty($userid)){
            return false;
        }
		// $sql="SELECT count(cc.id) as completedact from {course_completion_criteria} ccc JOIN {course_modules_completion} cc ON cc.coursemoduleid = ccc.moduleinstance where ccc.course={$courseid} and cc.userid={$userid} and cc.completionstate=1";
		$sql= "SELECT  COUNT( cm.id ) as completedact from {course_modules} as cm JOIN {course_modules_completion} as cmc ON cmc.coursemoduleid = cm.id WHERE cm.course=$courseid AND cmc.userid = $userid";
		$completioncount = $DB->get_record_sql($sql);
		$out = $completioncount->completedact;
        return $out;
    }
	
	function user_course_completion_progress($courseid, $userid) {
        global $DB, $USER, $CFG;

        if(empty($courseid) || empty($userid)){
            return false;
        }
        $course = get_course($courseid);
        $info = new completion_info($course);
		// $is_completed = $info->is_course_complete($USER->id);
		if (!$info->is_enabled()) {
            return null;
        }

        if (!$info->is_tracked_user($userid)) {
            return null;
        }
        // Before we check how many modules have been completed see if the course has.
        if ($info->is_course_complete($userid)) {
            return 100;
        }
		$modules = $info->get_activities();
        $count = count($modules);
        if (!$count) {
            return null;
        }

        // Get the number of modules that have been completed.
        $completed = 0;
        foreach ($modules as $module) {
            $data = $info->get_data($module, true, $userid);
            $completed += $data->completionstate == COMPLETION_INCOMPLETE ? 0 : 1;
        }
        $course_completion_percent=($completed / $count) * 100;

        return $course_completion_percent;

		
		// $sql="select id from {course_completions} where course=$courseid and userid=$userid and timecompleted IS NOT NULL";
		// //echo $sql;
		// $condition=$DB->get_record_sql($sql);
		
		// if(empty($condition)){
		// $total_activity_count = $this->total_course_activities($courseid);
		// $completed_activity_count = $this->user_course_completed_activities($courseid, $userid);
  //       if($total_activity_count>0 && $completed_activity_count>0){
		// 	$totalactivities = $total_activity_count-1;
		// 	if($totalactivities > 0){
	 //           $course_completion_percent = $completed_activity_count/($totalactivities)*100;
		// 	}else{
		// 		$course_completion_percent = 0;
		// 	}
		// }
		// }else{
		// 	$course_completion_percent=100;
		// }
		// return $course_completion_percent;
    }
	
	function course_summary_files($courserecord){
        global $DB, $CFG, $OUTPUT;
        if ($courserecord instanceof stdClass) {
           // require_once($CFG->libdir . '/coursecatlib.php');
            $courserecord = new core_course_list_element($courserecord);
        }
        
        // set default course image
        //$url = $OUTPUT->pix_url('/course_images/courseimg', 'local_costcenter');
        foreach ($courserecord->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            if($isimage){
				$url = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $file->get_contextid() . '/' .
					$file->get_component() . '/' .$file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
			}else{
				$url = $OUTPUT->image_url('courseimg', 'local_courses');//send_file_not_found();
			}
        }
		if(empty($url)){
			$url = $OUTPUT->image_url('courseimg', 'local_courses');//send_file_not_found();
		}
        return $url;
    }
    
    function get_classes_summary_files($classrecord){
    		global $OUTPUT;
			$url = $OUTPUT->image_url('classviewnew', 'local_classroom');
        return $url;
    }
	
	// function for insert training_at as -1, if select All, else insert as selected options
	// @param -- $training_locations array, list of selected locations
	// @param -- $organizationids , comma seperated organisation values
	function get_training_at_values($training_locations, $organizationids){
		global $DB, $USER;
		
		$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
		$training_at = null;
		
		if(sizeof($training_locations)>0){
			$selected_locationscount = count($training_locations);
			$sql = "SELECT u.id, location
					FROM {local_userdata} ud
					JOIN {user} u ON u.id = ud.userid
					WHERE u.deleted = 0 AND u.suspended = 0 AND ud.location IS NOT NULL
					AND ud.location != '' ";
			if($organizationids){
				$sql .= " AND ud.costcenterid IN ($organizationids) ";
			}else{
					// following for OH, TC role users
				if(!is_siteadmin() && (has_capability('local/costcenter:view', $systemcontext) || has_capability('mod/facetoface:addinstance', $systemcontext))){
					$userdept = $DB->get_field('local_userdata', 'costcenterid', array('userid'=>$USER->id));
					if($userdept){
						$sql .= " and ud.costcenterid = $userdept ";
					}
				}
				
			}
			$sql .= " GROUP BY ud.location";
			$totallocations = $DB->get_records_sql_menu($sql);
			$totallocationscount = count($totallocations);
			if($selected_locationscount == $totallocationscount){
				$training_at = -1;
			}else{
				$training_at = implode(',', $training_locations); 
			}
		}
		
		return $training_at;
	}
	
	// function for insert band as -1, if select All, else insert as selected options
	// @param -- $selectedbands array, list of selected locations
	// @param -- $organizationids , comma seperated organisation values
	function get_band_values($selectedbands, $organizationids){
		global $DB, $USER;
		
		$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
		$band = null;
		
		if(sizeof($selectedbands)>0){
			$selected_bandscount = count($selectedbands);
			
			$sql = "SELECT u.id, ud.band
					FROM {local_userdata} ud
					JOIN {user} u ON u.id = ud.userid
					WHERE u.deleted = 0 AND u.suspended = 0 AND ud.band IS NOT NULL
					AND ud.band != '' ";
					
			if($organizationids){
				$sql .= " AND ud.costcenterid IN ($organizationids) ";
			}else{
				// following for OH, TC role users
				if(!is_siteadmin() && (has_capability('local/costcenter:view', $systemcontext) || has_capability('mod/facetoface:addinstance', $systemcontext))){
					$userdept = $DB->get_field('local_userdata', 'costcenterid', array('userid'=>$USER->id));
					if($userdept){
						$sql .= " and ud.costcenterid = $userdept ";
					}
				}
			}
			$sql .= " GROUP BY ud.band";
			
			$totalbands = $DB->get_records_sql_menu($sql);
			$totalbandscount = count($totalbands);
			
			if($selected_bandscount == $totalbandscount){
				$band = -1;
			}else{
				$band = implode(',', $selectedbands); 
			}
		}
		
		return $band;
	}
}

/*For the security checking up if viewing course of users is acceptable or not acceptable.
 Initially I am checking it for the course view part and including this on view page of course

*/

class has_user_permission{
          
          
    public function access_courses_permission($courseid){
         global $DB, $USER;
        
         //------userid and courseid validation-------------------    
         if (empty($courseid)) {
             return false;               
         }  //---------end validation------------------
	   if(is_siteadmin() || $courseid==1)
	       return true;
	   
  //      $check_course_acc=$DB->get_field('course','open_costcenterid',array('id'=>$courseid));
	 //   $course_costcenter=$DB->get_field('local_costcenter','shortname',array('id'=>$check_course_acc));
	 
	 // if($course_costcenter =='ACD')
		//   return true;
	 
  //          $usercostcenter=$DB->get_field('user','open_costcenterid',array('id'=>$USER->id));
              
		 
		//  if(!empty($usercostcenter)){
			 
		// 	if($check_course_acc == $usercostcenter)
		// 	      return true;
		//         else
  //                 return false;
		 
		//  }
		 
		 
		 return true;

           		 
        
    } // end of username_andcostcenter_validation function
    
      public function access_activity_permission($moduleid){
         global $DB, $USER;
        
         //------userid and courseid validation-------------------    
         if (empty($moduleid)) {
             return false;               
         }  //---------end validation------------------
	   if(is_siteadmin())
		   return true;
           
       $sql= "SELECT lc.costcenterid from {course_modules} cm join {local_coursedetails} lc on lc.courseid=cm.course where cm.id={$moduleid}";
	  
	   $check_course_acc=$DB->get_field_sql($sql); 
	
	   $course_costcenter=$DB->get_field('local_costcenter','shortname',array('id'=>$check_course_acc));
           if($course_costcenter=='ACD')
               return true;
		 
		    $usercostcenter=$DB->get_field('local_userdata','costcenterid',array('userid'=>$USER->id));
		 
		 if(!empty($usercostcenter)){
                     
			 if($usercostcenter == $check_course_acc)
				 return true;
		     else 
                return false;				 
		 
		 }
		 
		 
		 

           		 
        
    } // end of username_andcostcenter_validation function
	
	 public function access_user_permission($userid){
         global $DB, $USER;
		 
        $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
         //------userid and courseid validation-------------------    
         if (empty($userid)) {
             return false;               
         }  //---------end validation------------------
	   if(is_siteadmin() || has_capability('local/costcenter:assign_multiple_departments_manage', $systemcontext))
		   return true;
           
        $check_course_acc=$DB->get_field('user','open_costcenterid',array('id'=>$userid)); 
	    $usercostcenter=$DB->get_field('user','open_costcenterid',array('id'=>$USER->id));
		 
		 if(!empty($usercostcenter) && !empty($check_course_acc) ){
                     
			 if($usercostcenter == $check_course_acc)
				 return true;
		     else 
                return false;				 
		 
		 }
		 else 
			 return false;
		 
		 
		 

           		 
        
    } // end of username_andcostcenter_validation function
     public function history($userid,$curr_tab){
		
		global $CFG, $DB, $OUTPUT;
		//	print_object($curr_tab);
		$courses_active = '';
		$users_active = '';
		$bulk_users_active = '';
		$request_users='';
		if($curr_tab == 'course'){
			$courses_active = ' active ';
		}elseif($curr_tab == 'activehistory'){
			$active_active = ' active ';
		}elseif($curr_tab == 'request_user'){
			$request_users= ' active';
		}
		
		//$total_enroled_users=$DB->get_record_sql('SELECT count(llu.userid) as data  FROM {local_learningplan_user} as llu JOIN {user} as u ON u.id=llu.userid WHERE llu.planid='.$id.' AND u.deleted!=1');
		//$total_requested_users=$DB->count_records('local_learningplan_approval',array('planid'=>$id));
		//$total_assigned_course=$DB->count_records('local_learningplan_courses',array('planid'=>$id));
		$return = '';
		$tabs = '<ul class="nav nav-tabs nav-justified">
						<li class="'.$courses_active.'">
							<a data-toggle="tab" href="#plan_courses">
								<span><img src="'.$OUTPUT->pix_url('i/course').'" title="'.get_string('active_history','local_certification').'" /></span>
								'.get_string('course_history','local_certification').'</a>
						</li>
						<li class="'.$active_active.'">
							<a data-toggle="tab" href="#plan_users">
								<span><img src="'.$OUTPUT->pix_url('i/users').'" title="'.get_string('assignusers','local_certification').'" /></span>
								'.get_string('active_history','local_certification').'<span class="badge">'. $total_enroled_users->data .'</span></a>
						</li>
						
						
					  </ul>';
		$tabs .= '<div class="tab-content">';
		$tabs .= $this->course_history($curr_tab);
		$tabs .= $this->active_history($curr_tab);
		////$tabs .= $this->learningplans_bulk_users_tab_content($id, $curr_tab,$condition);
		//$tabs .= $this->learningplans_request_users_tab_content($id, $curr_tab,$condition);
		$tabs .= '</div>';
		$return .= $tabs;
		return $return;
	}
	public function course_history($curr_tab){
		//print_object($curr_tab);
			global $CFG, $DB, $OUTPUT;
			$active_courses = ' ';
		if($curr_tab == 'course'){
			$active_courses = ' in active';
		}
			$sql = "select * from {local_course_history} ";
			$coursehistory = $DB->get_records_sql($sql);
			$return='';
			$return.= html_writer::start_tag('div', array('id' => 'plan_courses','class'=>'tab-pane fade'.$active_courses.''));
			//$return .='<div id="plan_courses" class="tab-pane fade '.$active_courses.'">';
			$table = new html_table();
			
			$table->head = array( 'Course ID' ,'Moodle CourseID','User ID','Moodle UserID', 'Enrolled Date','Enrolled By','Assigned','Course','CategoryID',
			'Certificate Term','Utm Source','Utm Medium','Utm Campaign','User Name','Employee Code','Email','Department',
			'Sub Department','Subsub Department');
			$data=array();
			foreach($coursehistory as $coursehistorylist){
			$list=array();
			$list[]= $coursehistorylist->courseid;
			$list[]= $coursehistorylist->moodlecourseid;
			$list[]= $coursehistorylist->userid;
			$list[]= $coursehistorylist->moodleuserid;
			$list[]= \local_costcenter\lib::get_userdate("d/m/Y H:i",$coursehistorylist->enrolleddate);
			$list[]= $coursehistorylist->enrolledby;
			$list[]= $coursehistorylist->assigned;
			$list[]= $coursehistorylist->coursename;
			$list[]= $coursehistorylist->categoryid;
			$list[]= $coursehistorylist->certificateterm;
			$list[]= $coursehistorylist->utmsource;
			$list[]= $coursehistorylist->utmmedium;
			$list[]= $coursehistorylist->utmcampaign;
			$list[]= $coursehistorylist->username;
			$list[]= $coursehistorylist->employeecode;
			$list[]= $coursehistorylist->email;
			$list[]= $coursehistorylist->department;
			$list[]= $coursehistorylist->subdepartment;
			$list[]= $coursehistorylist->subsubdepartment;
			
			$data[]=$list;
			}
			$table->data=$data;
			$return.= html_writer::table($table);
			$return.=html_writer::end_tag('div');
			return $return;
		
	}
	public function active_history($curr_tab){
	$table = new html_table();
	$active_courses = ' ';
		if($curr_tab == 'activehistory'){
			$active_courses = ' in active';
		}
     $return='';
	 $return.= html_writer::start_tag('div', array('id' => 'plan_users','class'=>'tab-pane fade'.$active_courses.''));
			//$return .='<div id="plan_users" class="tab-pane fade '.$active_courses.'">';
     $table->head = array( 'Employee Name' ,'Employee Code','Employee Email','Group', 'Course Name','Enrolled on','Due Date','Course CompletedDate',
                          'Complition %','Time Spent','Activity Name','Lesson Score');
     $data=array();
     foreach($activityhistory as $activityhistorylist){
     $list=array();
     $list[]= $activityhistorylist->empname;
     $list[]= $activityhistorylist->employeecode;
     $list[]= $activityhistorylist->employeeemail;
     $list[]= $activityhistorylist->group;
     $list[]= $activityhistorylist->coursename;
     $list[]= \local_costcenter\lib::get_userdate("d/m/Y H:i",$activityhistorylist->enrolledon);
     $list[]= \local_costcenter\lib::get_userdate("d/m/Y H:i",$activityhistorylist->duedate);
     $list[]= \local_costcenter\lib::get_userdate("d/m/Y H:i",$activityhistorylist->coursecompleteddate);
     $list[]= $activityhistorylist->completionpercentage;
     $list[]= gmdate('H:i', $activityhistorylist->timespent);
     $list[]= $activityhistorylist->activityname;
     $list[]= $activityhistorylist->lessonscore;
     
     
     $data[]=$list;
}
$table->data=$data;
$return .=html_writer::table($table);
$return.=html_writer::end_tag('div');
return $return;
	}
	function mod_facetoface_prerequest_cheking($classroomid,$employee){
   //print_object($employee);exit;
    global $USER, $DB;
     $output = false;
     $exists=array();
     $classroomcourse=$DB->get_record_sql("SELECT courseid FROM {local_classroom_courses} 
     												WHERE classroomid=$classroomid");
    $coursedetails = $DB->get_field('course','id' ,array('id'=>$classroomcourse->courseid));
    if($coursedetails !=null){
        $coursedetail=$coursedetails;
        foreach($coursedetail as $course_completions){
	        $sql ="select id from {course_completions} where userid= $employee and course in ($course_completions) and timecompleted !=''"; 
			$exist=$DB->record_exists_sql($sql);
	            if(empty($exist)){
	                $exist=0;
	                $exists[]=$exist;
	            }else{
	                $exists[]=$exist;
	            }
        }
    }
 
	 if(in_array(0, $exists)){
		 $output=false;
	 }else{
	     $output=true;
	 }
    return $output;
}
function tocheckcapacity($batchid,$capacity,$userid){
global $DB,$USER;	
	$flag=0;
	
        //if($loggedinuser_supervisor){
            $facetofacecapacity=$capacity;
            $enrolledusercount=$DB->count_records('local_facetoface_users',array('f2fid'=>$batchid));
           

            $enrolledusercount+count($userid);
//print_object($enrolledusercount);
		   //print_object($enrolledusercount);
            if($facetofacecapacity>0){
				
                if(($enrolledusercount+count($userid)) > $facetofacecapacity){
					
                    //display confirmation message
					//echo $OUTPUT->notification( 'Seats are filled, you dont have permission to enroll more than the capacity ', 'notifysuccess');
					$flag=1;
					  
					
		  }
       }
	 
return $flag;
	}
}
/**
* Returns url/path of the facetoface attachment if exists, else false
*
* @param int $iltid, facetoface id
*/
function get_ilt_attachment($iltid){
    global $DB, $CFG;
    
    $fileitemid = $DB->get_field('local_classroom', 'classroomlogo', array('id'=>$iltid));
    $imgurl = false;
    if(!empty($fileitemid)){
        $sql = "SELECT * FROM {files} WHERE itemid = $fileitemid AND filename != '.' ORDER BY id DESC LIMIT 1";
        $filerecord = $DB->get_record_sql($sql);
    }
    	if($filerecord!=''){
        $imgurl = file_encode_url($CFG->wwwroot."/pluginfile.php", '/' . $filerecord->contextid . '/' . $filerecord->component . '/' .$filerecord->filearea .'/'.$filerecord->itemid. $filerecord->filepath. $filerecord->filename);
        }
        if(empty($imgurl)){
            $dir = $CFG->wwwroot.'/local/costcenter/pix/course_images/image3.jpg';
			for($i=1; $i<=10; $i++) {
				$image_name = $dir;
				$imgurl = $image_name;
				break;
			}
        }
    //}
    return $imgurl;
}
