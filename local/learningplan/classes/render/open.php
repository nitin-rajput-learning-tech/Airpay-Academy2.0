<?php
namespace local_learningplan\render;

class open{
    private $userid;
    
    function __construct(){
        global $DB, $CFG, $OUTPUT, $USER, $PAGE;
        $this->db=$DB;
        $this->userid=$USER;
    }
    
    public static function userdetails(){
		 global $DB, $CFG, $OUTPUT, $USER, $PAGE;
        $sql="SELECT u.id AS userid,u.open_costcenterid,u.open_group,u.open_departmentid,
                u.open_subdepartment,c.fullname AS costcentername 
                FROM {user} AS u 
                JOIN {local_costcenter} c ON c.id=u.open_costcenterid
                WHERE u.id=$USER->id";
	    $userinfo=$DB->get_record_sql($sql);
        return $userinfo;
    }
    
    static public function departments($department_id){
        global $DB;
        $plan_departments = $DB->get_records_sql('SELECT id, fullname FROM {local_costcenter} WHERE id IN('.$department_id.')');
        return $plan_departments;
    }
}
?>