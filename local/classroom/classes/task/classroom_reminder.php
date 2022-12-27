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
namespace local_classroom\task;
class classroom_reminder extends \core\task\scheduled_task{
	public function get_name() {
        return get_string('taskclassroomreminder', 'local_classroom');
    }
	public function execute(){
		global $DB;
        $type = "classroom_reminder";
        /*Getting the notification type id*/
        $find_type_id = $DB->get_field('local_notification_type','id',array('shortname'=>$type));
        $corecomponent = new \core_component();
		$costcenterexist = $corecomponent::get_plugin_directory('local','costcenter');
        /*Getting the notification record to find the users*/
        $get_type_sql = "SELECT * FROM {local_notification_info} WHERE notificationid=:notificationid AND active=1 AND (moduleid IS NOT NULL OR moduleid <> 0) ";
        $params = array('notificationid' => $find_type_id);
        $get_type_notifications = $DB->get_records_sql($get_type_sql, $params);
        $moduleids = array(0);
        foreach($get_type_notifications AS $notification){
            $moduleids[] = $notification->moduleid;
            $this->send_reminder_notification($notification, $type, $costcenterexist);
        }
        $globalnotification_sql = "SELECT * FROM {local_notification_info} WHERE notificationid=:notificationid AND active=1 AND (moduleid IS  NULL OR moduleid = 0)";
        $params = array('notificationid' => $find_type_id);
        $global_notifications = $DB->get_records_sql($globalnotification_sql, $params);
        $moduleids = implode(',', $moduleids);
        foreach($global_notifications AS $notification){
            $this->send_global_reminder_notification($notification, $type, $moduleids, $costcenterexist);
        }
	}
	 public function send_reminder_notification($notification, $type, $costcenterexist){
        global $DB;
		$classroom_notification = new \local_classroom\notification();
		$fromuser = \core_user::get_support_user();
        $day = $notification->reminderdays;
        // $Today = \local_costcenter\lib::get_userdate("d/m/Y H:i");
        $starttime = strtotime(Date('d/m/Y', strtotime("+".$day." days")));
        $endtime = $starttime+86399;
        $params = array();
        $sql = "SELECT lcu.id as user_enrolid, lc.*, lcu.userid FROM {local_classroom} AS lc
    			JOIN {local_classroom_users} AS lcu ON lcu.classroomid=lc.id
    			WHERE lc.startdate BETWEEN :starttime AND :endtime ";
        $params['starttime'] = $starttime;
        $params['endtime'] = $endtime;
        if($costcenterexist){
            $sql .= " AND concat('/',lc.open_path,'/') LIKE :costcenterid ";
            $params['costcenterid'] = $notification->costcenterid;
        }
		// echo $sql;
		// print_r($params);
        $enrolclassrooms=$DB->get_records_sql($sql, $params);
		// echo "indusers";
		// print_r($enrolclassrooms);
		foreach($enrolclassrooms AS $classroomcontent){
				$touser = \core_user::get_user($classroomcontent->userid);
				$classroominstance = $classroomcontent;
				$classroom_notification->send_classroom_notification($classroominstance, $touser, $fromuser, $type, $notification);
		}
        //$this->notification_to_user($enrolclassrooms, $notification, $type);

		$params = array();
        $sql ="SELECT lct.id as trainer_enrolid, lc.*, lct.trainerid as userid FROM {local_classroom} AS lc
    			JOIN {local_classroom_trainers} AS lct ON lct.classroomid=lc.id
    			WHERE lc.startdate BETWEEN :starttime AND :endtime ";
        $params['starttime'] = $starttime;
        $params['endtime'] = $endtime;
        if($costcenterexist){
            $sql .= " AND concat('/',lc.open_path,'/') LIKE :costcenterid ";
            $params['costcenterid'] = $notification->costcenterid;
        }
		// 	echo $sql;
		// print_r($params);
        $enrolclassrooms=$DB->get_records_sql($sql, $params);
		// echo "indtrainer";
		// 		print_r($enrolclassrooms);

		foreach($enrolclassrooms AS $classroomcontent){
				$touser = \core_user::get_user($classroomcontent->userid);
				$classroominstance = $classroomcontent;
				$classroom_notification->send_classroom_notification($classroominstance, $touser, $fromuser, $type, $notification);
		}
        //$this->notification_to_user($enrolclassrooms, $notification, $type);
    }

    public function send_global_reminder_notification($notification, $type, $moduleids, $costcenterexist){
		global $DB;
		$classroom_notification = new \local_classroom\notification();
		$fromuser = \core_user::get_support_user();
        $day = $notification->reminderdays;
        // $Today = \local_costcenter\lib::get_userdate("d/m/Y H:i");
        $starttime = strtotime(Date('d/m/Y', strtotime("+".$day." days")));
        $endtime = $starttime+86399;
        $params = array();
        $sql = "SELECT lcu.id as user_enrolid, lc.*, lcu.userid FROM {local_classroom} AS lc
    			JOIN {local_classroom_users} AS lcu ON lcu.classroomid=lc.id
    			WHERE lc.startdate BETWEEN :starttime AND :endtime ";
        if(trim($moduleids) != ''){
            $sql .= " AND lc.id NOT IN ($moduleids) ";
        }
        $params['starttime'] = $starttime;
        $params['endtime'] = $endtime;
        // $params['moduleids'] = $moduleids;
        if($costcenterexist){
            $sql .= " AND concat('/',lc.open_path,'/') LIKE :costcenterid ";
            $params['costcenterid'] = $notification->costcenterid;
        }
		// 	echo $sql;
		// print_r($params);
        $enrolclassrooms=$DB->get_records_sql($sql, $params);
		// echo "globalusers";
		// print_r($enrolclassrooms);
		foreach($enrolclassrooms AS $classroomcontent){
				$touser = \core_user::get_user($classroomcontent->userid);
				$classroominstance = $classroomcontent;
				$classroom_notification->send_classroom_notification($classroominstance, $touser, $fromuser, $type, $notification);
		}
        //$this->notification_to_user($enrolclassrooms, $notification, $type);

		$params = array();
        $sql ="SELECT lct.id as trainer_enrolid, lc.*, lct.trainerid as userid FROM {local_classroom} AS lc
    			JOIN {local_classroom_trainers} AS lct ON lct.classroomid=lc.id
    			WHERE lc.startdate BETWEEN :starttime AND :endtime  AND lc.id NOT IN ($moduleids)";
        $params['starttime'] = $starttime;
        $params['endtime'] = $endtime;
        // $params['moduleids'] = $moduleids;
        if($costcenterexist){
            $sql .= " AND concat('/',lc.open_path,'/') LIKE :costcenterid ";
            $params['costcenterid'] = $notification->costcenterid;
        }
		// 	echo $sql;
		// print_r($params);
        $enrolclassrooms=$DB->get_records_sql($sql, $params);
		// echo "globaltrainers";
		// print_r($enrolclassrooms);
		foreach($enrolclassrooms AS $classroomcontent){
				$touser = \core_user::get_user($classroomcontent->userid);
				$classroominstance = $classroomcontent;
				$classroom_notification->send_classroom_notification($classroominstance, $touser, $fromuser, $type, $notification);
		}
        //$this->notification_to_user($enrolclassrooms, $notification, $type);
    }
}
