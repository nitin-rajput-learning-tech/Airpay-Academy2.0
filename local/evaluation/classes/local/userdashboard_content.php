<?php
namespace local_evaluation\local;
class userdashboard_content extends \block_userdashboard\content{
	public function userdashboard_menu_content(){
		$returndata = array();
		$returndata['id'] = 'evaluation_courses';
		$returndata['order'] = 6;
		$returndata['pluginname'] = 'local_evaluation';
		$returndata['tabname'] = 'inprogress';
		$returndata['status'] = 'inprogress';
		$returndata['class'] = 'userdashboard_menu_link';
		$returndata['iconclass'] = 'fa fa-clipboard';
		$returndata['label'] = get_string('feedbacks', 'block_userdashboard');
		$returndata['templatename'] = 'local_evaluation/userdashboard_content';
		return $returndata;
	}
	public static function inprogress_evaluations($filter_text='', $offset, $limit) {
        global $DB, $USER;
        // $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='a.open_path');
        $sqlquery = "SELECT a.*, eu.creatorid, eu.timemodified as joinedate";
        $sql =" FROM {local_evaluations} a , {local_evaluation_users} eu
            WHERE a.plugin = 'site' AND a.id = eu.evaluationid AND eu.userid = {$USER->id}
            AND instance = 0 AND a.visible = 1
            AND a.id NOT IN (SELECT evl.id from {local_evaluations} evl, {local_evaluation_completed} lec WHERE lec.evaluation = evl.id AND lec.userid = {$USER->id})
            AND a.evaluationmode LIKE 'SE' AND a.deleted != 1 "; 
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%{$filter_text}%%' ";
        }
        $sql .=" order by eu.timecreated DESC";
        $inprogressEvaluations = $DB->get_records_sql($sqlquery . $sql, array(), $offset, $limit);
        return $inprogressEvaluations;
    }
    /**********End of the function********/

    public static function inprogress_evaluations_count($filter_text=''){
    	global $USER,$DB;
        // $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    	$sql ="SELECT COUNT(a.id) FROM {local_evaluations} a , {local_evaluation_users} eu
            WHERE a.plugin = 'site' AND a.id = eu.evaluationid AND eu.userid = {$USER->id}
            AND instance = 0 AND a.visible = 1
            AND a.id NOT IN (SELECT evl.id from {local_evaluations} evl, {local_evaluation_completed} lec WHERE lec.evaluation = evl.id AND lec.userid = {$USER->id})
            AND a.evaluationmode LIKE 'SE' AND a.deleted != 1 "; 
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%{$filter_text}%%' ";
        }
        $inprogressEvalCount = $DB->count_records_sql($sql);
        return $inprogressEvalCount;
    }

    public static function completed_evaluations($filter_text='', $offset, $limit){
        global $DB,$USER;
        // $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        $sqlquery = "SELECT a.*, eu.timemodified as joinedate";
        $sql = " from {local_evaluations} a, {local_evaluation_completed} ec, {local_evaluation_users} eu where a.plugin = 'site' AND ec.evaluation = a.id AND ec.userid = {$USER->id} AND a.id = ec.evaluation AND eu.userid = {$USER->id} AND a.evaluationmode LIKE 'SE' AND a.deleted != 1  ";
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%$filter_text%%'";
        }
        $sql .=" order by ec.timemodified DESC";
        $completedEvaluations = $DB->get_records_sql($sqlquery . $sql, array(), $offset, $limit);
        return $completedEvaluations;
    
    }
    public static function completed_evaluations_count($filter_text=''){
    	global $USER, $DB;
        // $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    	$sql = "SELECT COUNT(a.id) 
    		FROM {local_evaluations} a 
    		JOIN {local_evaluation_completed} AS ec ON  ec.evaluation = a.id
    		WHERE a.plugin = 'site' AND ec.userid = {$USER->id} 
    		AND a.evaluationmode LIKE 'SE' AND a.deleted != 1  ";
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%$filter_text%%'";
        }
        $completedEvalCount = $DB->count_records_sql($sql);
        return $completedEvalCount;
    }

    //Enrolled Evaluation
    public static function enrolled_evaluations($filter_text='', $offset, $limit) {
        global $DB, $USER;
        // $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        $sqlquery = "SELECT a.*, eu.creatorid, eu.timemodified as joinedate";
        $sql =" FROM {local_evaluations} a , {local_evaluation_users} eu
            WHERE a.plugin = 'site' AND a.id = eu.evaluationid AND eu.userid = {$USER->id}
            AND instance = 0 AND a.visible = 1
            AND a.evaluationmode LIKE 'SE' AND a.deleted != 1 "; 
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%{$filter_text}%%' ";
        }
        $sql .=" order by eu.timecreated DESC";
        $enrolled_evaluations = $DB->get_records_sql($sqlquery . $sql, array(), $offset, $limit);
        return $enrolled_evaluations;
    }
    /**********End of the function********/

    public static function enrolled_evaluations_count($filter_text=''){
        global $USER,$DB;
        // $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        $sql ="SELECT COUNT(a.id) FROM {local_evaluations} a , {local_evaluation_users} eu
            WHERE a.plugin = 'site' AND a.id = eu.evaluationid AND eu.userid = {$USER->id}
            AND instance = 0 AND a.visible = 1
            AND a.evaluationmode LIKE 'SE' AND a.deleted != 1 "; 
        if(!empty($filter_text)){
            $sql .= " AND a.name LIKE '%%{$filter_text}%%' ";
        }
        // echo $sql;
        // exit;
        $enrolled_evaluations_count = $DB->count_records_sql($sql);
        
        return $enrolled_evaluations_count;
    }
}
