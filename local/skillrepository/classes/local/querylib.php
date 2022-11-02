<?php
namespace local_skillrepository\local;
defined('MOODLE_INTERNAL') || die();
class querylib {
	private $db;

	private $user;

	public function __construct(){
		global $DB, $USER;
		$this->db = $DB;
		$this->user = $USER;
	}
	public function insert_update_level($formdata){
		if($formdata->id){
			$formdata->usermodified = $this->user->id;
			$formdata->timemodified = time();
			$this->db->update_record('local_course_levels', $formdata);
		}else{
			$formdata->usercreated = $this->user->id;
			$formdata->timecreated = time();
			$this->db->insert_record('local_course_levels', $formdata);
		}
	}

	public function get_table_contents($params){
		$params = (object)$params;


		$contentsql = "SELECT lcl.id,lcl.name,lcl.code, concat(u.firstname,' ', u.lastname) as username FROM {local_course_levels} AS lcl
			JOIN {user} AS u ON u.id=lcl.usercreated WHERE 1=1 ";

		if(!is_siteadmin()){
        	//For Organization head show only those levels created by them.
        	$costcenterid=$this->user->open_costcenterid;
			$contentsql .=" AND u.open_costcenterid=$costcenterid";
        }
		if($params->search){
			$contentsql .= " AND (lcl.name LIKE '%%{$params->search}%%' OR lcl.code LIKE '%%{$params->search}%%')";
		}
		$contentsql .=" ORDER BY lcl.id desc";
		if (isset($params->recordsperpage) && $params->perpage != '-1'){
            // $contentsql .= " LIMIT ".$params->recordsperpage .", ".$params->perpage;
        	$content = $this->db->get_records_sql($contentsql, array(), $params->recordsperpage, $params->perpage);
        }else{
        	$content = $this->db->get_records_sql($contentsql);
        }


        return $content;
	}

	public function get_total_levels_count($params){
		$params = (object)$params;
		$countsql = "SELECT count(id) FROM {local_course_levels} WHERE 1=1 ";
		if($params->search){
			$countsql .= " AND (name LIKE '%%{$params->search}%%' OR code LIKE '%%{$params->search}%%')";
		}
		$count = $this->db->count_records_sql($countsql);
		return $count;
	}
	public function delete_level($levelid){
		return $this->db->delete_records('local_course_levels',array('id' => $levelid));
	}
	public function can_delete_level($levelid){
		return true;
	}
	public function can_edit_level($levelid){
		return true;
	} 
}