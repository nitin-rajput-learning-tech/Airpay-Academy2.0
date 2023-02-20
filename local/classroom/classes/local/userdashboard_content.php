<?php
namespace local_classroom\local;
class userdashboard_content extends \block_userdashboard\content{
	public function userdashboard_menu_content(){
		$returndata = array();
		$returndata['id'] = 'classroom_courses';
		$returndata['order'] = 3;
		$returndata['pluginname'] = 'local_classroom';
		$returndata['tabname'] = 'inprogress';
		$returndata['status'] = 'inprogress';
		$returndata['class'] = 'userdashboard_menu_link';
		$returndata['iconclass'] = 'browse_classroom_icon mr-1';
		$returndata['label'] = get_string('classroomtrainings', 'block_userdashboard');
		$returndata['templatename'] = 'local_classroom/userdashboard_content';
		return $returndata;
	}
	public static function completed_classrooms($filter_text='', $offset, $limit) {
        global $DB, $USER;
        $sqlquery = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description ";

        $sql = " FROM {local_classroom} as lc   
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE  lc.status=4 and lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
        }
        $coursenames = $DB->get_records_sql($sqlquery.$sql, array(), $offset, $limit);
        return $coursenames;
    }
    public static function completed_classrooms_count($filter_text = ''){
        global $DB, $USER;
        $sqlcount = "SELECT COUNT(lc.id) ";
        $sql = " FROM {local_classroom} as lc   
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE  lc.status=4 and lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
        }
        $completedCount = $DB->count_records_sql($sqlcount . $sql);
        return $completedCount;
    }
    /**********End of the function********/

   
    public static function cancelled_classsroom($filter_text=''){
        global $DB,$USER;
        $sql = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description  FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status=3 AND lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
        }
        $cancelled_classsroom = $DB->get_records_sql($sql);
        return $cancelled_classsroom;
    }
    public static function cancelled_classrooms_count($filter_text){
        global $DB,$USER;
        $sql = "SELECT count(lc.id) FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status=3 AND lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
        }
        $cancelledCount = $DB->count_records_sql($sql);
        return $cancelledCount;
    }

    /**
     * [inprogress_classrooms description]
     * @return [type] [description]
     */
    public static function inprogress_classrooms($filter_text='', $offset, $limit) {
        global $DB, $USER;
        $sqlquery = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description ";
        $sql = " FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status=1 AND lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%' ";
        }
        $coursenames = $DB->get_records_sql($sqlquery.$sql, array(), $offset, $limit);
        return $coursenames;
    }
    public static function inprogress_classrooms_count($filter_text=''){
        global $DB, $USER;
        $sqlcount = "SELECT COUNT(lc.id) ";
        $sql = " FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status=1 AND lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%'";
        }
        $inprogressCount = $DB->count_records_sql($sqlcount . $sql);
        return $inprogressCount;
    }

    public static function gettotal_classrooms(){
        global $DB, $USER;
        $sql = "SELECT COUNT(lc.id) 
                FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status IN(1,4) AND lcu.userid={$USER->id} and lc.visible=1 ";
        $coursecount = $DB->count_records_sql($sql);
        return $coursecount;
        
    }
    //enrolled classrooms
     public static function enrolled_classrooms($filter_text='', $offset, $limit) {
        global $DB, $USER;
        $sqlquery = "SELECT lc.id,lc.name AS fullname,lc.startdate,lc.enddate,lc.description ";
        $sql = " FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status IN(1,4) AND lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%' ";
        }
        $enrolled_classrooms = $DB->get_records_sql($sqlquery.$sql, array(), $offset, $limit);
        return $enrolled_classrooms;
    }
    public static function enrolled_classrooms_count($filter_text='') {
        global $DB, $USER;
       $sql = "SELECT COUNT(lc.id) 
                FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lc.status IN(1,4) AND lcu.userid={$USER->id} and lc.visible=1 ";
        if(!empty($filter_text)){
            $sql .= " AND lc.name LIKE '%%{$filter_text}%%' ";
        }
        $enrolled_classrooms_count = $DB->count_records_sql($sql);
        return $enrolled_classrooms_count;
    }
    /*********end of the function******/

    



    /**
    * Returns url/path of the facetoface attachment if exists, else false
    *
    * @param int $iltid, facetoface id
    */
    public static function get_ilt_attachment($iltid){
        global $DB, $CFG;
        
        $fileitemid = $DB->get_field('local_classroom', 'classroomlogo', array('id'=>$iltid));
        $imgurl = false;
        if(!empty($fileitemid)){
            $sql = "SELECT * FROM {files} WHERE itemid = $fileitemid AND filename != '.' ORDER BY id DESC ";// LIMIT 1
            $filerecord = $DB->get_record_sql($sql);
        }
            if($filerecord!=''){
            $imgurl = file_encode_url($CFG->wwwroot."/pluginfile.php", '/' . $filerecord->contextid . '/' . $filerecord->component . '/' .$filerecord->filearea .'/'.$filerecord->itemid. $filerecord->filepath. $filerecord->filename);
            }
            if(empty($imgurl)){
                $dir = $CFG->wwwroot.'/local/costcenter/pix/course_images/image3.jpg';
                for($i=1; $i<=10; $i++) {
                    $image_name = $dir;
                    $imgurl = $image_name;
                    break;
                }
            }
        //}
        return $imgurl;
    }
}
