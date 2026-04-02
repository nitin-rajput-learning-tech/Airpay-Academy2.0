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
namespace local_evaluation\task;
class evaluation_due extends \core\task\scheduled_task{
	public function get_name() {
        return get_string('taskevaluationdue', 'local_evaluation');
    }
    public function execute(){
    	global $DB;
    	$notificationclass = new \local_evaluation\notification();
    	$emailtype = 'feedback_due';
    	$fromuser = \core_user::get_support_user();
        $availiablenotifications = $this->module_evaluation_due_notifications();
        $modules = array();
        foreach($availiablenotifications AS $notification){
        	$starttime = strtotime(date('d-m-Y', strtotime("+".$notification->reminderdays." day")));
        	$endtime = $starttime+86399;
	    	$evaluationsql = "SELECT eu.*,u.id as user_id,e.id as evaluationid
				FROM {local_evaluation_users} eu
				JOIN {local_evaluations} e ON e.id = eu.evaluationid
				JOIN {user} u ON eu.userid=u.id AND u.deleted = 0 AND u.suspended = 0
				WHERE eu.status !=1 AND e.id NOT IN (SELECT uec.evaluation 
														FROM {local_evaluation_completed} AS uec 
														WHERE uec.userid=u.id AND uec.evaluation=e.id) 
				AND e.timeopen !=0 AND e.timeclose !=0 AND e.timeopen BETWEEN :timestart AND :timeend AND e.id IN (:moduleid) ";
			$params = array('timestart' => $starttime, 'timeend' => $endtime, 'moduleid' => $notification->moduleid);
			$evaluationusers = $DB->get_records_sql($evaluationsql, $params);
			foreach($evaluationusers AS $user){
				$evaluationinstance = $DB->get_record('local_evaluations', array('id' => $user->evaluationid));
				$touser = \core_user::get_user($user->user_id);
				$notificationclass->send_evaluation_notification($evaluationinstance, $touser, $fromuser, $emailtype, $notification);
			}
			$modules[] = $notification->moduleid;
        }
        $globalduenotifications = $this->global_due_notifications();
        $moduleids = implode(',', $modules);
        foreach($globalduenotifications AS $notification){
        	$starttime = strtotime(date('d-m-Y', strtotime("+".$notification->reminderdays." day")));
        	$endtime = $starttime+86399;
			$costcenterid=explode('/',$notification->open_path)[1];
	    	$evaluationsql = "SELECT eu.*,u.id as user_id,e.id as evaluationid
				FROM {local_evaluation_users} eu
				JOIN {local_evaluations} e ON e.id = eu.evaluationid
				JOIN {user} u ON eu.userid=u.id AND u.deleted = 0 AND u.suspended = 0
				WHERE eu.status !=1 AND e.id NOT IN (SELECT uec.evaluation 
														FROM {local_evaluation_completed} AS uec 
														WHERE uec.userid=u.id AND uec.evaluation=e.id) 
				AND e.timeopen !=0 AND e.timeclose !=0 AND e.timeopen BETWEEN :timestart AND :timeend AND e.id NOT IN (:moduleid) AND e.costcenterid = :costcenterid ";
			$params = array('timestart' => $starttime, 'timeend' => $endtime, 'moduleid' => $moduleids, 'costcenterid' => $costcenterid);
			$evaluationusers = $DB->get_records_sql($evaluationsql, $params);
			foreach($evaluationusers AS $user){
				$evaluationinstance = $DB->get_record('local_evaluations', array('id' => $user->evaluationid));
				$touser = \core_user::get_user($user->user_id);
				$notificationclass->send_evaluation_notification($evaluationinstance, $touser, $fromuser, $emailtype, $notification);
			}
        }
    }
    private function global_due_notifications(){
    	global $DB;
    	$globalnotification_sql = "SELECT lni.* FROM {local_notification_info} AS lni 
    		WHERE (lni.moduleid=0 OR lni.moduleid IS NULL) AND lni.notificationid=(SELECT id FROM {local_notification_type} WHERE shortname=:shortname)"; 
    	$notifications = $DB->get_records_sql($globalnotification_sql, array('shortname' => 'feedback_due'));
    	return $notifications;
    }
    private function module_evaluation_due_notifications(){
    	global $DB;
    	$modulenotification_sql = "SELECT lni.* FROM {local_notification_info} AS lni 
    		WHERE (lni.moduleid!=0 OR lni.moduleid IS NOT NULL) AND lni.notificationid=(SELECT id FROM {local_notification_type} WHERE shortname=:shortname)"; 
		$notifications = $DB->get_records_sql($modulenotification_sql, array('shortname' => 'feedback_due'));
    	return $notifications;
    }
}
