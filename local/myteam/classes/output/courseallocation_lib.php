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
 */

namespace local_myteam\output;
require_once($CFG->dirroot . '/local/classroom/lib.php');
use local_classroom\classroom;
use local_program\program;

defined('MOODLE_INTERNAL') || die;
class courseallocation_lib{

    public function get_team_myteam($search = false){
        global $DB, $USER, $OUTPUT;
        if($search){
            $condition = " AND CONCAT(u.firstname, ' ', u.lastname) LIKE '%$search%' ";
        } else {
            $condition = "";
        }
 //$concatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
        $departmentmyteamsql = "SELECT u.* FROM {user} as u
                                    WHERE u.open_supervisorid = $USER->id AND u.id != $USER->id
                                    AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND u.id > 2".$condition ;
        
    $departmentmyteam = $DB->get_records_sql($departmentmyteamsql);

        if(!empty($departmentmyteam)){
        	$return = '';
        	$myteamdata = array();
        	foreach ($departmentmyteam as $departmentuser) {
        		$row = array();
        		$row['id'] = $departmentuser->id;
        		$row['picture'] = '';
        		$row['fullname'] = fullname($departmentuser);
        		$myteamdata[] = $row;
            }
        }
        return $myteamdata;
    }

	public function get_team_courses($user, $search = false){
		global $DB, $USER,$CFG;

		if(empty($user)){
			return get_string('invaliduser');
		}
		if($search){
			$condition = " AND c.fullname LIKE '%$search%' ";
		}else{
			$condition = " ";
		}
		$usercostcenterpaths = $DB->get_records('local_userdata', array('userid' => $user));
		$paths = [];
        foreach($usercostcenterpaths AS $userpath){
            $userpathinfo = $userpath->costcenterpath;
            $paths[] = $userpathinfo.'%';
            while ($userpathinfo = rtrim($userpathinfo,'0123456789')) {
                $userpathinfo = rtrim($userpathinfo, '/');
                if ($userpathinfo === '') {
                  break;
                }
                $paths[] = $userpathinfo;
            }
        }
        $condition = '';
        if(!empty($paths)){
            foreach($paths AS $path){
                $pathsql[] = " c.open_path LIKE '{$path}' ";
            }
            $condition = " AND ( ".implode(' OR ', $pathsql).' ) ';
        }
		$courses_sql = "SELECT c.id, c.fullname 
										FROM {course} as c
										WHERE c.id <> 1 AND c.visible = 1 AND c.selfenrol = 1 ".$condition;//FIND_IN_SET(3, c.open_identifiedas)
		$courses = $DB->get_records_sql_menu($courses_sql);
		return $courses;
	}

	public function get_team_classrooms($user, $search = false){
		global $DB, $USER,$CFG;

		if(empty($user)){
			return get_string('invaliduser');
		}
		if($search){
			$condition = " AND c.name LIKE '%$search%' ";
		}else{
			$condition = " ";
		}
		$usercostcenterpaths = $DB->get_records('local_userdata', array('userid' => $user));
		$paths = [];
        foreach($usercostcenterpaths AS $userpath){
            $userpathinfo = $userpath->costcenterpath;
            $paths[] = $userpathinfo.'%';
            while ($userpathinfo = rtrim($userpathinfo,'0123456789')) {
                $userpathinfo = rtrim($userpathinfo, '/');
                if ($userpathinfo === '') {
                  break;
                }
                $paths[] = $userpathinfo;
            }
        }
        $condition = '';
        if(!empty($paths)){
            foreach($paths AS $path){
                $pathsql[] = " lc.open_path LIKE '{$path}' ";
            }
            $condition = " AND ( ".implode(' OR ', $pathsql).' ) ';
        }
		$classrooms_sql = "SELECT lc.id, lc.name
							FROM {local_classroom} as lc
							WHERE lc.visible = 1 AND lc.status = 1 ".$condition;
		$classrooms = $DB->get_records_sql_menu($classrooms_sql);

		return $classrooms;
	}
	public function get_team_programs($user, $search = false){
		global $DB, $USER;
			if($search){
				$condition = " AND p.name LIKE '%$search%' ";
			} else {
				$condition = "";
			}
			$open_path = $DB->get_field('user', 'open_path', array('id' => $user));
			if($open_path > 0){
				$condition .= " AND p.open_path = ".$open_path;
			}
			$programssql = "SELECT p.id, p.name FROM {local_program} as p
										WHERE p.visible = 1".$condition;
			
			$programs = $DB->get_records_sql_menu($programssql);
			return $programs;
	}

	public function courseallocation($learningtype, $allocatemyteam, $allocatecourses = array()){
		global $DB, $USER, $CFG;

		// $allocatecourses = explode(',', $allocatecourses);
		$data = array();
		switch ($learningtype) {
			case '1'://courses
				$return = array();
				$return['return_status'] ='';    
				if(!empty($allocatecourses)){
					require_once($CFG->dirroot . '/lib/enrollib.php');
					$manual = enrol_get_plugin('manual');
					$studentroleid = $DB->get_field('role', 'id', array('archetype' => 'student'));
					foreach($allocatecourses as $allocatecourse){
						// $sql = "SELECT FIND_IN_SET(3,open_identifiedas) FROM {course} WHERE id = $allocatecourse";
						$sql = "SELECT id FROM {course} WHERE id = $allocatecourse AND CONCAT(',',open_identifiedas,',') LIKE '%,3,%' ";
						$iselearning = $DB->get_record_sql($sql);
						$instance = $DB->get_record('enrol',
									 array('courseid' => $allocatecourse, 'enrol' => 'manual', 'roleid' => $studentroleid));

						if($instance){
							$user_enrollment_exists = $DB->record_exists('user_enrolments', array('enrolid' => $instance->id, 'userid' => $allocatemyteam));
							if(!$user_enrollment_exists){
									$out = $manual->enrol_user($instance, $allocatemyteam, $studentroleid);
									$notification = new \local_courses\notification();
									$type = 'course_enrol';
									$course = $DB->get_record('course', array('id' => $allocatecourse));
									$user = \core_user::get_user($allocatemyteam);
									$notificationdata = $notification->get_existing_notification($course, $type);
									if($notificationdata)
										$notification->send_course_email($course, $user, $type, $notificationdata);
									$return['enrolledornot'] = true;
							}else{
								$return['enrolledornot'] = false;
							}
						}else{
							$return['enrolledornot'] = false;
						}
						$data[] = $return;
					}
				}else{
					$return['enrolledornot'] = false;
					$data[] = $return;
				}

				return $data;

				break;
			case '2'://classrooms
				$return = array();
				$return['return_status'] ='';    
				if(!empty($allocatecourses)){
					$classroomclass = new classroom();
					foreach($allocatecourses as $allocatecourse){
						$instance = $DB->record_exists('local_classroom_users', array('classroomid' => $allocatecourse, 'userid' => $allocatemyteam));
						$sql = "SELECT cu.classroomid FROM {local_classroom_waitlist} as cu
                            WHERE cu.classroomid = :classid and cu.userid = :userid and cu.enrolstatus=0";
                
                		$waitingenrolled = $DB->record_exists_sql($sql,  array('classid' => $allocatecourse, 'userid' => $allocatemyteam));
						if(!$instance&&!$waitingenrolled){
							$out = $classroomclass->classroom_self_enrolment($allocatecourse, $allocatemyteam,$selfenrol=1,'myteam');
							$return_status='';
							     if($out > 0){
                                    $params=array();  
 									//$concatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
                                    $sql = "SELECT lw.sortorder as classroomwaitinglistno,c.name as classroom,concat(u.firstname,'',u.lastname) as username,
                                            (select GROUP_CONCAT(lcw.id) FROM {local_classroom_waitlist} as lcw where lcw.classroomid=lw.classroomid and lcw.enrolstatus=0) as active
                                            FROM {local_classroom_waitlist} as lw
                                            JOIN {local_classroom} AS c ON c.id = lw.classroomid
                                            JOIN {user} as u ON u.id=lw.userid
                                            where lw.id=:waitlistid ";        
                                    $params['waitlistid'] = $out;
                                    $stringobj=$DB->get_record_sql($sql, $params);
                                    $active=explode(',',$stringobj->active);
                                    $classroomwaitinglistno=array_search ($out, $active);
                                    $stringobj->classroomwaitinglistno=($classroomwaitinglistno+1) ? ($classroomwaitinglistno+1) : $stringobj->classroomwaitinglistno ;
                                    $return_status=get_string("otherclassroomwaitlistinfo",'local_classroom',$stringobj);
                            
				                }elseif($out == -1){
				                	$return_status=get_string("capacity_check",'local_classroom');
				                }
				            $return['return_status'] = $return_status;    
							$return['enrolledornot'] = true;
						}else{
							$return['enrolledornot'] = false;
						}
						$data[] = $return;
					}
				}else{
					$return['enrolledornot'] = false;
					$data[] = $return;
				}
				return $data;
				break;
			case '3'://programs
				$return = array();
				$return['return_status'] ='';    
				if(!empty($allocatecourses)){
					$programclass = new program();
					foreach($allocatecourses as $allocatecourse){
						$instance = $DB->record_exists('local_program_users', array('programid' => $allocatecourse, 'userid' => $allocatemyteam));
						if(!$instance){
							$out = $programclass->program_self_enrolment($allocatecourse, $allocatemyteam);
							$return['enrolledornot'] = true;
						}else{
							$return['enrolledornot'] = false;
						}
						$data[] = $return;
					}
					
				}else{
					$return['enrolledornot'] = false;
					$data[] = $return;
				}
				return $data;
				break;
			case '4'://learningplans
				$return = array();
				$return['return_status'] ='';    
				$return['enrolledornot'] = false;
				$data[] = $return;
				return $data;
				break;
		}
		$return['enrolledornot'] = false;
		$data[] = $return;
		return $data;
	}
}
