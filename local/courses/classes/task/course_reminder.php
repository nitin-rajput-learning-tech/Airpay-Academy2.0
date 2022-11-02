<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or localify
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
 * Courses external API
 *
 * @package    local_courses
 * @category   external
 * @copyright  eAbyas <www.eabyas.in>
 */

namespace local_courses\task;
class course_reminder extends \core\task\scheduled_task {

	public function get_name() {
        return get_string('taskcoursereminder', 'local_courses');
    }

	public function execute(){
		global $DB;
        $type = "course_reminder";
        /*Getting the notification type id*/
        $find_type_id = $DB->get_field('local_notification_type','id',array('shortname'=>$type));
        $corecomponent = new \core_component();
		$costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        /*Getting the notification record to find the users*/
        $get_type_sql = "SELECT * FROM {local_notification_info} WHERE notificationid=:notificationid AND active=1 AND (moduleid IS NOT NULL OR moduleid = 0) ";
        $params = array('notificationid' => $find_type_id);
        $get_type_notifications = $DB->get_records_sql($get_type_sql, $params);
        $moduleids = array();
        foreach($get_type_notifications AS $notification){
            $moduleids[] = $notification->moduleid;
            $this->send_reminder_notification($notification, $type, $costcenterexist);   	
        }
        // $globalnotification_sql = "SELECT * FROM {local_notification_info} WHERE notificationid=:notificationid AND active=1 ";
        // $params = array('notificationid' => $find_type_id);
        // $global_notifications = $DB->get_records_sql($globalnotification_sql, $params);
        // $moduleids = implode(',', $moduleids);
        // foreach($global_notifications AS $notification){
        //     $this->send_global_reminder_notification($notification, $type, $moduleids, $costcenterexist);      
        // }
	}

    public function send_reminder_notification($notification, $type, $costcenterexist){
        global $DB;
        $day = $notification->reminderdays;
        // $day = $day+1;
        // $Today = \local_costcenter\lib::get_userdate("d/m/Y H:i");
        // $starttime = strtotime(Date('d/m/Y', strtotime("+".$day." days")));
        $starttime = strtotime(Date('d-m-Y', time()))-1;
        $endtime = $starttime+86400;
        $params = array();
        // $sql="SELECT e.id as enrolid,ue.*,c.id as courseid,c.fullname FROM {enrol} e 
        //     JOIN {user_enrolments} ue ON e.id = ue.enrolid 
        //     JOIN {course} c ON e.courseid = c.id 
        //     WHERE c.enddate BETWEEN :starttime AND :endtime";
        $sql = "SELECT  ue.*, e.id AS enrolid, c.id AS courseid, c.fullname 
            FROM {user_enrolments} ue  
            JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol IN ('manual', 'self') 
            JOIN {course} c ON e.courseid = c.id 
            LEFT JOIN {course_completions} AS cc ON cc.course=e.courseid AND cc.userid = ue.userid AND c.id = cc.course
            WHERE ue.timecreated+((c.open_coursecompletiondays-$day)*86400) BETWEEN :starttime AND :endtime AND c.id>1 AND cc.timecompleted IS NULL ";
        $params['starttime'] = $starttime;
        $params['endtime'] = $endtime;
        if($costcenterexist){
            $sql .= " AND c.open_costcenterid=:costcenterid ";
            $params['costcenterid'] = $notification->costcenterid;
        }
        $enrolcourses=$DB->get_records_sql($sql, $params);
        // $this->notification_to_user($enrolcourses, $notification, $type);
        $this->notification_to_end_user($enrolcourses, $notification, $type);
    }

    public function send_global_reminder_notification($notification, $type, $moduleids, $costcenterexist){
        global $DB;
        $day = $notification->reminderdays;
        $day = $day+1;
        // $Today = \local_costcenter\lib::get_userdate("d/m/Y H:i");
        // $starttime = strtotime(Date('d/m/Y', strtotime("+".$day." days")));
        $starttime = strtotime(Date('d-m-Y', time()))-1;
        $endtime = $starttime+86400;
        $params = array();
        // $sql="SELECT e.id as enrolid,ue.*,c.id as courseid,c.fullname FROM {enrol} e 
        //     JOIN {user_enrolments} ue ON e.id = ue.enrolid 
        //     JOIN {course} c ON e.courseid = c.id 
        //     WHERE c.enddate BETWEEN :starttime AND :endtime AND c.id NOT IN (:moduleids)";
        $sql = "SELECT  ue.*,e.id AS enrolid, c.id AS courseid, c.fullname 
            FROM {user_enrolments} AS ue  
            JOIN {enrol} AS e ON e.id = ue.enrolid AND e.enrol IN ('manual', 'self')
            LEFT JOIN {course_completions} AS cc ON cc.course=e.courseid
            JOIN {course} c ON e.courseid = c.id 
            WHERE ue.timestart+((c.open_coursecompletiondays-$day)*86400)
            BETWEEN :starttime AND :endtime AND concat(',',:moduleids,',') NOT LIKE CONCAT('%,',c.id,',%')  AND c.id>1 
            AND cc.timecompleted IS NULL ";
        $params['starttime'] = $starttime;
        $params['endtime'] = $endtime;
        $params['moduleids'] = $moduleids;
        if($costcenterexist){
            $sql .= " AND c.open_costcenterid=:costcenterid ";
            $params['costcenterid'] = $notification->costcenterid;
        }
        $enrolcourses=$DB->get_records_sql($sql, $params);
        // $this->notification_to_user($enrolcourses, $notification, $type);
        $this->notification_to_end_user($enrolcourses, $notification, $type);
    }

    public function notification_to_user($enrolcourses, $notification, $type){
        global $DB;
        foreach($enrolcourses as $enrolcourse){
            $sql="SELECT u.* from {user} AS u 
                JOIN {user_enrolments} AS ue ON ue.userid=u.id 
                where ue.enrolid =:enrolid";
            $enrolledusers=$DB->get_records_sql($sql, array('enrolid' => $enrolcourse->enrolid));
            $course = $DB->get_record('course', array('id' => $enrolcourse->courseid)); 
            /*Getting the users list to whom the notification should be sent*/
            foreach($enrolledusers as $user){
                $coursenotification = new \local_courses\notification();
                $coursenotification->send_course_email($course, $user, $type, $notification);
            }
        }
    }
    public function notification_to_end_user($enrolcourses, $notification, $type){
        // global $DB;
        $coursenotification = new \local_courses\notification();
        $courses = array();
        foreach($enrolcourses as $enrolcourse){
            $touser = \core_user::get_user($enrolcourse->userid);
            if(empty($courses[$enrolcourse->courseid])){
                $courses[$enrolcourse->courseid] = get_course($enrolcourse->courseid);
            }
            $course = $courses[$enrolcourse->courseid];
            $coursenotification->send_course_email($course, $touser, $type, $notification);
        }
    }
}