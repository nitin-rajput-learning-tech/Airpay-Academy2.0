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

defined('MOODLE_INTERNAL') || die;

use has_user_permission;
use core_component;
use local_myteam\output\courseallocation_lib;

// require_once(dirname(__FILE__) . '/../config.php');
class courseallocation {
    public function courseallocation_view($value =false,$search = false) {
        global $CFG, $USER, $PAGE, $OUTPUT;
        $courseallocation_lib = new courseallocation_lib();
        $courseallocationdata = array();
        $courseallocationdata['existplugins'] = $this->courseallocation_action();
        
        $courseallocationdata['teamusers'] = $courseallocation_lib->get_team_myteam($search);
        if($value){
            return $OUTPUT->render_from_template('local_myteam/courseallocation', $courseallocationdata);
        }else{
            $data = array();
            $data[] = $courseallocationdata;
            return $data;
        }
        
        //return $courseallocationdata;
    }

    public function courseallocation_action(){
        $core_component = new core_component();
        $courses_exists = false;
        $course_plugin = $core_component::get_plugin_directory('local', 'courses');
        if(!empty($course_plugin)){
            $courses_exists = true;
        }
        $classroom_exists = false;
        $classroom_plugin = $core_component::get_plugin_directory('local', 'classroom');
        if(!empty($classroom_plugin)){
            $classroom_exists = true;
        }
        $program_exists = false;
        $program_plugin = $core_component::get_plugin_directory('local', 'program');
        if(!empty($program_plugin)){
            $program_exists = true;
        }
        
        $existplugins = array();
        $existplugins['coursesexist'] = $courses_exists;
        $existplugins['classroomexist'] = $classroom_exists;
        $existplugins['programexist'] = $program_exists;

        $data = array();
        $data[] = $existplugins;
        return $existplugins;
    }

    public function get_team_courses_view($user, $search = false){
        global $DB;
        $courseallocation_lib = new courseallocation_lib();
      
        $courses = $courseallocation_lib->get_team_courses($user, $search);
        if(!empty($courses)){
            $coursesdata = array();

            foreach($courses as $cid => $cname){
                $disattr = '';//'disabled';".$disable."
                $checked = '';//'checked';".$checked."
                $icons = '';
                $extra_class = '';

                $sql = "SELECT c.id FROM {user_enrolments} as ue
                            JOIN {enrol} as e ON e.id = ue.enrolid
                            JOIN {course} as c ON c.id = e.courseid
                            WHERE e.courseid = :courseid AND ue.userid = :userid AND e.enrol = :enrollment";
                $costcenterid = $DB->get_field('user', 'open_costcenterid', array('id' => $user));
                if($costcenterid > 0){
                    $sql .= " AND c.open_costcenterid = ".$costcenterid;
                }

                $enrolled = $DB->record_exists_sql($sql,  array('courseid' => $cid, 'userid' => $user, 'enrollment' => 'manual'));
                
                if($enrolled == true){
                    $checked = 'checked';
                    $disattr = 'disabled';
                }

                $returndata = array();
                $returndata['disattr'] = $disattr;
                $returndata['checked'] = $checked;
                $returndata['user'] = $user;
                $returndata['moduleid'] = $cid;
                $returndata['extraclass'] = $extra_class;
                $returndata['modulename'] = $cname;
                $returndata['icons'] = $icons;

                $coursesdata[] = $returndata;
                
            }
        }

        return $coursesdata;
    }
    
    public function get_team_classrooms_view($user, $search = false){
        global $DB;
        $courseallocation_lib = new courseallocation_lib();
       
        $classrooms = $courseallocation_lib->get_team_classrooms($user, $search);
        if(!empty($classrooms)){
            $classroomdata = array();
            foreach($classrooms as $classid => $classname){
                $disattr = '';//'disabled';".$disable."
                $checked = '';//'checked';".$checked."
                $icons = '';

                $sql = "SELECT cu.classroomid FROM {local_classroom_users} as cu
                            WHERE cu.classroomid = :classid and cu.userid = :userid";
                
                $enrolled = $DB->record_exists_sql($sql,  array('classid' => $classid, 'userid' => $user));

                $sql = "SELECT cu.classroomid FROM {local_classroom_waitlist} as cu
                            WHERE cu.classroomid = :classid and cu.userid = :userid and cu.enrolstatus=0";
                
                $waitingenrolled = $DB->record_exists_sql($sql,  array('classid' => $classid, 'userid' => $user));
                if($enrolled == true || $waitingenrolled==true){
                    $checked = 'checked';
                    $disattr = 'disabled';
                }

                $returndata = array();
                $returndata['disattr'] = $disattr;
                $returndata['checked'] = $checked;
                $returndata['user'] = $user;
                $returndata['moduleid'] = $classid;
                $returndata['extraclass'] = $extra_class;
                $returndata['modulename'] = $classname;
                $returndata['icons'] = $icons;

                $classroomdata[] = $returndata;
                
            }
        }
      
        return $classroomdata;
    }

    public function get_team_programs_view($user, $search = false){
        global $DB;
        $courseallocation_lib = new courseallocation_lib();
      
        $programs = $courseallocation_lib->get_team_programs($user, $search);
        if(!empty($programs)){
            $programdata = array();
            foreach($programs as $programid => $programname){
                $disattr = '';//'disabled';".$disable."
                $checked = '';//'checked';".$checked."
                $icons = '';
                $extra_class = '';

                $sql = "SELECT pu.programid FROM {local_program_users} as pu
                            WHERE pu.programid = :programid and pu.userid = :userid";
                
                $enrolled = $DB->record_exists_sql($sql, array('programid' => $programid, 'userid' => $user));
                if($enrolled == true){
                    $checked = 'checked';
                    $disattr = 'disabled';
                    $extra_class = 'checked_b4';
                }

                $returndata = array();
                $returndata['disattr'] = $disattr;
                $returndata['checked'] = $checked;
                $returndata['user'] = $user;
                $returndata['moduleid'] = $programid;
                $returndata['extraclass'] = $extra_class;
                $returndata['modulename'] = $programname;
                $returndata['icons'] = $icons;

                $programdata[] = $returndata;
            }
        }
        
        return $programdata;
    }

}