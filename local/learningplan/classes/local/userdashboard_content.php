<?php
namespace local_learningplan\local;
class userdashboard_content extends \block_userdashboard\content{
	public function userdashboard_menu_content(){
		$returndata = array();
		$returndata['id'] = 'learningplan_courses';
		$returndata['order'] = 2;
		$returndata['pluginname'] = 'local_learningplan';
		$returndata['tabname'] = 'inprogress';
		$returndata['status'] = 'inprogress';
		$returndata['class'] = 'userdashboard_menu_link';
		$returndata['iconclass'] = 'fa fa-map';
		$returndata['label'] = get_string('learningpaths', 'block_userdashboard');
		$returndata['templatename'] = 'local_learningplan/userdashboard_content';
		return $returndata;
	}
	public static function inprogress_lepnames($filter_text='', $offset, $limit) {
        global $DB, $USER;
        $sqlquery = "SELECT llp.id,llp.name as fullname, llp.description as description";
        $sql = " from {local_learningplan} llp JOIN {local_learningplan_user} as lla on llp.id=lla.planid where userid={$USER->id} and lla.completiondate is NULL and status is NULL and llp.visible=1";
        if(!empty($filter_text)){
            $sql .= " AND llp.name LIKE '%%$filter_text%%'";
        }
        $sql .= " ORDER BY lla.id desc";
        $inprogress = $DB->get_records_sql($sqlquery . $sql, array(), $offset, $limit);
        return $inprogress;
    }
    /****End of the function****/

    public static function inprogress_lepnames_count($filter_text=''){
    	global $DB, $USER;
    	$sqlcount = "SELECT COUNT(llp.id)";
    	$sql = " from {local_learningplan} llp JOIN {local_learningplan_user} as lla on llp.id=lla.planid where userid={$USER->id} and lla.completiondate is NULL and status is NULL and llp.visible=1";
        if(!empty($filter_text)){
            $sql .= " AND llp.name LIKE '%%$filter_text%%'";
        }
        $inprogressCount = $DB->count_records_sql($sqlcount.$sql);
        return $inprogressCount;
    }

    /******Function to the show the Completed LEP in the Classroom Training********/
     public static function completed_lepnames($filter_text='', $offset, $limit) {
        global $DB, $USER;
        $sqlquery = "SELECT llp.id,llp.name as fullname, llp.description as description";
        $sql = " FROM {local_learningplan} llp
            JOIN {local_learningplan_user} as lla on llp.id=lla.planid
            WHERE userid={$USER->id} and lla.completiondate is NOT NULL
            AND status=1 and llp.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND llp.name LIKE '%%{$filter_text}%%'";
        }
        $sql .= " ORDER BY lla.id desc";

        $completed = $DB->get_records_sql($sqlquery . $sql, array(), $offset, $limit);
        return $completed;
    }
    public static function completed_lepnames_count($filter_text=''){
    	global $DB, $USER;
    	$sqlcount = "SELECT COUNT(llp.id) ";
    	$sql = " FROM {local_learningplan} llp
            JOIN {local_learningplan_user} as lla on llp.id=lla.planid
            WHERE userid={$USER->id} and lla.completiondate is NOT NULL
            AND status=1 and llp.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND llp.name LIKE '%%{$filter_text}%%'";
        }
        $completedCount = $DB->count_records_sql($sqlcount.$sql);
        return $completedCount;
    }

    /******Function to the show the Enrolled LEP ********/
     public static function enrolled_lepnames($filter_text='', $offset, $limit) {
        global $DB, $USER;
        $sqlquery = "SELECT llp.id,llp.name as fullname, llp.description as description";
        $sql = " FROM {local_learningplan} llp
            JOIN {local_learningplan_user} as lla on llp.id=lla.planid
            WHERE userid={$USER->id} and llp.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND llp.name LIKE '%%{$filter_text}%%'";
        }
        $sql .= " ORDER BY lla.id desc";

        $enrolled = $DB->get_records_sql($sqlquery . $sql, array(), $offset, $limit);
        return $enrolled;
    }
    public static function enrolled_lepnames_count($filter_text=''){
        global $DB, $USER;
        $sqlcount = "SELECT COUNT(llp.id) ";
        $sql = " FROM {local_learningplan} llp
            JOIN {local_learningplan_user} as lla on llp.id=lla.planid
            WHERE userid={$USER->id} and llp.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND llp.name LIKE '%%{$filter_text}%%'";
        }
        $enrolledCount = $DB->count_records_sql($sqlcount.$sql);
        return $enrolledCount;
    }
}