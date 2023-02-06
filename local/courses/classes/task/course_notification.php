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

class course_notification extends \core\task\scheduled_task{
	public function get_name() {
        return get_string('taskcoursenotification', 'local_courses');
    }
    public function execute(){
    	global $DB, $CFG;
        require_once($CFG->dirroot.'/local/courses/includes.php');
    	$type = "course_notification";
    	$starttime = strtotime(date('d/m/Y', time()));
        $endtime = $starttime+86399;
    	$newcoursesql = "SELECT c.* FROM {course} AS c WHERE c.timecreated BETWEEN :startdate AND :enddate
            AND concat(',',c.open_identifiedas, ',') LIKE concat('%,',2,',%') ";
    	$newcourse = $DB->get_records_sql($newcoursesql, array('startdate' => $starttime, 'enddate'=> $endtime));
		$corecomponent = new \core_component();
		$costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
		$coursenotification = new \local_courses\notification();
    	foreach($newcourse AS $course){
    	   	$notification_sql = "SELECT * FROM {local_notification_info}
                WHERE notificationid=(SELECT id FROM {local_notification_type} WHERE shortname LIKE :type) AND active=1 ";
            $modulenotification_sql = "  AND concat(',',moduleid,',') LIKE concat('%,',:moduleid,',%') ";
            $globalnotification = " AND (moduleid IS NULL OR moduleid = 0) ";
            $modulenotificationparams = array('type' => $type, 'moduleid' => $course->id);
            $notificationparams = array('type' => $type);
            $userssql = "SELECT u.* FROM {user} AS u WHERE u.suspended=0 AND u.deleted=0 ";
            if($costcenterexist){
                $notification_sql .= " AND open_path LIKE :costcenterid ";
                $userssql .= " AND u.open_path LIKE :costcenterid";
                $params['costcenterid'] =$course->open_path;
                $notificationparams['costcenterid'] = $course->open_path;
                $modulenotificationparams['costcenterid'] =$course->open_path;
    			// if(!empty($course->open_departmentid)){
				// 	$userssql .= " AND u.open_departmentid=:departmentid";
    			// 	$params['departmentid'] = $course->open_departmentid;
    			// }
    		}
            $notification = $DB->get_record_sql($notification_sql.$modulenotification_sql, $modulenotificationparams);
            if(empty($notification)){
                $notification = $DB->get_record_sql($notification_sql.$globalnotification, $notificationparams);
            }
            if(!empty($notification)){
                $users = $DB->get_records_sql($userssql, $params);
                foreach($users AS $user){
                    $coursenotification->send_course_email($course, $user, $type, $notification);
                }
            }
    	}
    }
}
