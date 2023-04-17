<?php
namespace local_program\local;
class userdashboard_content extends \block_userdashboard\content{
	public function userdashboard_menu_content(){
		$returndata = array();
		$returndata['id'] = 'xseed';
		$returndata['order'] = 4;
		$returndata['pluginname'] = 'local_program';
		$returndata['tabname'] = 'inprogress';
		$returndata['status'] = 'inprogress';
		$returndata['class'] = 'userdashboard_menu_link';
		$returndata['iconclass'] = 'fa fa-graduation-cap';
		$returndata['label'] = get_string('program', 'block_userdashboard');
		$returndata['templatename'] = 'local_program/userdashboard_content';
		return $returndata;
	}
	public static function inprogress_programs($filter_text='', $offset, $limit) {
        global $DB, $USER;
        $sql = "SELECT bc.id, bc.name AS fullname, bc.shortname, bc.description
                  FROM {local_program} AS bc
                  JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                 WHERE bcu.userid = $USER->id AND bcu.programid NOT IN (SELECT programid
                        FROM {local_program_users} WHERE completion_status = 1 AND completiondate > 0
                            AND userid = {$USER->id} ) and bc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
        }
        $inprogress_bootcamps = $DB->get_records_sql($sql, array(), $offset, $limit);
        return $inprogress_bootcamps;
    }
    public static function inprogress_programs_count($filter_text=''){
        global $DB, $USER;
        $sql = "SELECT count(bc.id)
                FROM {local_program} AS bc
                JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                WHERE bcu.userid = $USER->id AND bc.visible=1 
                AND bcu.programid NOT IN (SELECT programid 
                    FROM {local_program_users} 
                    WHERE completion_status = 1 AND completiondate > 0 
                    AND userid = {$USER->id} ) ";
        if(!empty($filter_text)){
            $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
        }
        $programCount = $DB->count_records_sql($sql);
        return $programCount;
    }
    public static function completed_programs($filter_text='', $offset, $limit) {
        global $DB, $USER;
        $sql = "SELECT bc.id, bc.name AS fullname, bc.shortname, bc.description
                  FROM {local_program} as bc
                  JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                 WHERE bcu.completion_status = 1 AND bcu.completiondate > 0
                        AND bcu.userid = {$USER->id} and bc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
        }
        $completed_bootcamps = $DB->get_records_sql($sql, array(), $offset, $limit);
        return $completed_bootcamps;
    }
    public static function completed_programs_count($filter_text=''){
        global $DB, $USER;
        $sql = "SELECT COUNT(bc.id)
                FROM {local_program} as bc
                JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                WHERE bcu.completion_status = 1 AND bcu.completiondate > 0 
                AND bcu.userid = {$USER->id} AND bc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
        }
        $completedCount = $DB->count_records_sql($sql); 
        return $completedCount;
    }

    public static function gettotal_bootcamps(){
            global $DB, $USER;
            $sql = "SELECT bc.id,bc.name AS fullname, bc.description  FROM {local_program} AS bc
                    JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                    WHERE bc.status IN(1,4) AND bcu.userid={$USER->id} and bc.visible=1 ";
            $coursenames = $DB->get_records_sql($sql);
            return count($coursenames);
    }
    //enrolled programs
    public static function enrolled_programs($filter_text='', $offset, $limit) {
        global $DB, $USER;
        $sql = "SELECT bc.id, bc.name AS fullname, bc.shortname, bc.description
                  FROM {local_program} as bc
                  JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                 WHERE bcu.userid = {$USER->id} and bc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
        }
        $enrolled_programs = $DB->get_records_sql($sql, array(), $offset, $limit);
        return $enrolled_programs;
    }
    public static function enrolled_programs_count($filter_text=''){
        global $DB, $USER;
        $sql = "SELECT COUNT(bc.id)
                FROM {local_program} as bc
                JOIN {local_program_users} AS bcu ON bc.id = bcu.programid
                WHERE bcu.userid = {$USER->id} AND bc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND bc.name LIKE '%%{$filter_text}%%'";
        }
        $enrolled_programs_count = $DB->count_records_sql($sql); 
        return $enrolled_programs_count;
    }
}
