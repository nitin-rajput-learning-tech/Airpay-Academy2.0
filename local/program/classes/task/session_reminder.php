<?php
namespace local_program\task;
class session_reminder extends \core\task\scheduled_task {
	public function get_name() {
        return get_string('tasksessionreminder', 'local_program');
    }
    public function execute(){
    	global $DB;
    	$notificationclass = new \local_program\notification();
		$emailtype = 'program_session_reminder';
		$fromuser = \core_user::get_support_user();
		$availiablenotifications = $this->module_program_due_notifications();
        $modules = array(0);
           foreach($availiablenotifications AS $notification){
        	$starttime = strtotime(date('d/m/Y', strtotime("+".$notification->reminderdays." day")));
        	$endtime = $starttime+86399;
        	$program_sql = "SELECT bss.*, bcs.name,lp.name as 'product_name',concat(u.firstname,' ',u.lastname) as 'user_name',bcs.timestart,bcs.timefinish
        		FROM {local_bc_course_sessions} AS bcs 
        		JOIN {local_bc_session_signups} AS bss ON bss.sessionid=bcs.id
        		JOIN {user} AS u ON bss.userid=u.id AND u.deleted = 0 AND u.suspended = 0
        		JOIN {local_program} AS lp ON lp.id=bcs.programid
        		WHERE bcs.programid IN (:moduleid) AND bcs.timestart BETWEEN :timestart AND :timeend ";
			$params = array('timestart' => $starttime, 'timeend' => $endtime, 'moduleid' => $notification->moduleid);
			$programusers = $DB->get_records_sql($program_sql, $params);
			foreach($programusers AS $sessionprogramdetails){
				$touser = \core_user::get_user($sessionprogramdetails->userid);
				$notificationclass->send_program_sessionreminder_notification($sessionprogramdetails, $touser, $fromuser, $emailtype, $notification);
			}
			$modules[] = $notification->moduleid;
        }


        ///Global
        $globalduenotifications = $this->global_due_notifications();
    	$moduleids = implode(',', $modules);
    	foreach($globalduenotifications AS $notification){
        	$starttime = strtotime(date('d/m/Y', strtotime("+".$notification->reminderdays." day")));
        	$endtime = $starttime+86399;
	  
        	$programsession_sql = "SELECT bss.*, bcs.name,lp.name as 'product_name',concat(u.firstname,' ',u.lastname) as 'user_name',bcs.timestart,bcs.timefinish
        		FROM {local_bc_course_sessions} AS bcs 
        		JOIN {local_bc_session_signups} AS bss ON bss.sessionid=bcs.id
        		JOIN {user} AS u ON bss.userid=u.id AND u.deleted = 0 AND u.suspended = 0
        		JOIN {local_program} AS lp ON lp.id=bcs.programid
        		WHERE bcs.programid IN (:moduleid) AND bcs.timestart BETWEEN :timestart AND :timeend AND u.open_costcenterid = :costcenterid ";
			$params = array('timestart' => $starttime, 'timeend' => $endtime, 'moduleid' => $moduleids, 'costcenterid' => $notification->costcenterid);
			$programsessionusers = $DB->get_records_sql($programsession_sql, $params);
			foreach($programsessionusers AS $programsessiondetails){
			
				$touser = \core_user::get_user($programsessiondetails->userid);
				$notificationclass->send_program_sessionreminder_notification($programsessiondetails, $touser, $fromuser, $emailtype, $notification);
			}
        }
    	
    }

    private function module_program_due_notifications(){
    	global $DB;
    	$modulenotification_sql = "SELECT lni.* FROM {local_notification_info} AS lni 
    		WHERE (lni.moduleid!=0 OR lni.moduleid IS NOT NULL) AND lni.notificationid=(SELECT id FROM {local_notification_type} WHERE shortname=:shortname)"; 
    	$notifications = $DB->get_records_sql($modulenotification_sql, array('shortname' => 'program_session_reminder'));

    	return $notifications;
    }

     private function global_due_notifications(){
    	global $DB;
    	$globalnotification_sql = "SELECT lni.* FROM {local_notification_info} AS lni 
    		WHERE (lni.moduleid=0 OR lni.moduleid IS NULL) AND lni.notificationid=(SELECT id FROM {local_notification_type} WHERE shortname=:shortname)"; 
    	$notifications = $DB->get_records_sql($globalnotification_sql, array('shortname' => 'program_session_reminder'));
    	return $notifications;
    }
}