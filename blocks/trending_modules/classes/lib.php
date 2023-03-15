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
 * @package   Bizlms
 * @subpackage  trending_modules
 * @author eabyas  <info@eabyas.in>
**/
namespace block_trending_modules;
class lib  extends \block_trending_modules\querylib {
	// $moduleicon = array()
	public function trending_modules_crud($dataobject, $pluginname){
		if(!is_object($dataobject)){
			$dataobject = $this->make_trending_instance_object($dataobject, $pluginname);
		}
		$instance = new \stdClass();
		$instance->module_id = $dataobject->id;
		$instance->module_type = $dataobject->module_type;

		$instancedata = $this->db->get_record('block_trending_modules', (array)$instance);
		$instance->id = $instancedata->id; 
		if($dataobject->delete_record){
			$this->db->delete_records('block_trending_modules', array('id' => $instance->id));
			return;
		}
		$dataobject->name ? $instance->module_name = $dataobject->name : False;
		
		if($instance->id){
			isset($dataobject->open_path) ? $instance->open_path = $dataobject->open_path : $instancedata->open_path;
			isset($dataobject->module_tags) ? $instance->module_tags = $dataobject->module_tags : $instance->module_tags = $instancedata->module_tags;

			isset($dataobject->module_description) ? $instance->module_description = $dataobject->module_description : $instance->module_description = $instancedata->module_description;

			isset($dataobject->module_startdate) ? $instance->module_startdate = $dataobject->module_startdate : $instance->module_startdate = $instancedata->module_startdate;
			
			isset($dataobject->module_enddate) ? $instance->module_enddate = $dataobject->module_enddate : $instance->module_enddate = $instancedata->module_enddate;
			
			isset($dataobject->module_visible) ? $instance->module_visible = $dataobject->module_visible : $instance->module_visible = $instancedata->module_visible;

			isset($dataobject->module_status) ? $instance->module_status = $dataobject->module_status : $instance->module_status = $instancedata->module_status;
			
			isset($dataobject->module_imagelogo) ? $instance->module_imagelogo = $dataobject->module_imagelogo : $instance->module_imagelogo = $instancedata->module_imagelogo;
			
			isset($dataobject->open_group) ? $instance->open_group = $dataobject->open_group :  $instance->open_group = $instancedata->open_group;
			
			isset($dataobject->open_location) ? $instance->open_location = $dataobject->open_location : $instance->open_location = $instancedata->open_location;
			
			isset($dataobject->open_hrmsrole) ? $instance->open_hrmsrole = $dataobject->open_hrmsrole : $instance->open_hrmsrole = $instancedata->open_hrmsrole;
			
			isset($dataobject->open_designation) ? $instance->open_designation = $dataobject->open_designation: $instance->open_designation = $instancedata->open_designation;
			
			if($dataobject->update_rating){
				$instance->module_rating = $dataobject->average_rating;
				$instance->rated_users = $dataobject->rated_users;
			}
			
			if($dataobject->update_likes){
				$instance->module_likes = $dataobject->likes;
				$instance->liked_users = $dataobject->liked_users;
			}

			isset($dataobject->update_status) ? $dataobject->module_visible = $dataobject->visible : $dataobject->module_visible = $instancedata->module_visible;

			if($dataobject->update_enrollments){
				switch($pluginname){
					case 'local_courses':
						$sql = "SELECT count(ue.id) FROM {user_enrolments} AS ue 
							JOIN {enrol} AS e ON ue.enrolid = e.id AND e.enrol IN ('self', 'manual', 'auto') 
							WHERE e.courseid = :id ";
					break;
					case 'local_classroom':
					case 'local_certification':
					case 'local_program':
					case 'local_learningplan':
						$table = $pluginname == 'local_learningplan' ? $pluginname.'_user' :  $pluginname.'_users';
						$field = $pluginname == 'local_learningplan' ? 'planid' : explode('_',$pluginname)[1].'id';
						$sql = "SELECT count(id) FROM {{$table}} WHERE $field = :id ";
					break;
				}
				$dataobject->enrolled_users = $this->db->get_field_sql($sql, array('id' => $dataobject->id));
			}

			if($dataobject->update_completions){
				switch($pluginname){
					case 'local_courses':
						$sql = "SELECT count(cc.id) FROM {user_enrolments} AS ue 
							JOIN {enrol} AS e ON ue.enrolid = e.id AND e.enrol IN ('self', 'manual', 'auto')
							JOIN {course_completions} AS cc ON cc.userid = ue.userid AND cc.course = e.courseid
							WHERE e.courseid = :id AND cc.timecompleted IS NOT NULL ";
					break;
					case 'local_classroom':
					case 'local_certification':
					case 'local_program':
					case 'local_learningplan':
						$table = $pluginname == 'local_learningplan' ? $pluginname.'_user' :  $pluginname.'_users';
						$field = $pluginname == 'local_learningplan' ? 'planid' : explode('_',$pluginname)[1].'id';
						$sql = "SELECT count(id) FROM {{$table}} WHERE $field = :id AND completiondate IS NOT NULL AND completiondate > 0 ";
					break;
				}
				$dataobject->completed_users = $this->db->get_field_sql($sql, array('id' => $dataobject->id));
			}			

			$instance->timemodified = time();
			// print_object($instance);
			$this->db->update_record('block_trending_modules', $instance);
		}else{

			isset($dataobject->open_path) ? $instance->open_path = $dataobject->open_path : False;

			$dataobject->module_description ? $instance->module_description = $dataobject->module_description : $instance->module_description = NULL;

			$dataobject->module_startdate ? $instance->module_startdate = $dataobject->module_startdate : $instance->module_startdate = 0;
			
			$dataobject->module_enddate ? $instance->module_enddate = $dataobject->module_enddate : $instance->module_enddate = 0;
			
			$dataobject->module_tags ? $instance->module_tags = $dataobject->module_tags : $instance->module_tags = NULL;
			
			$dataobject->module_visible ? $instance->module_visible = $dataobject->module_visible : $instance->module_visible = 0;

			$dataobject->module_status ? $instance->module_status = $dataobject->module_status : $instance->module_status = 0;
			
			$dataobject->module_imagelogo ? $instance->module_imagelogo = $dataobject->module_imagelogo : $instance->module_imagelogo = 0;
			
			$dataobject->open_group ? $instance->open_group = $dataobject->open_group :  $instance->open_group = 0;
			
			$dataobject->open_location ? $instance->open_location = $dataobject->open_location : $instance->open_location = NULL;
			
			$dataobject->open_hrmsrole ? $instance->open_hrmsrole = $dataobject->open_hrmsrole : $instance->open_hrmsrole = NULL;
			
			$dataobject->open_designation ? $instance->open_designation = $dataobject->open_designation: $instance->open_designation = NULL;
			
			$instance->timecreated = time();
			
			$this->db->insert_record('block_trending_modules', $instance);
		}
	}
	public function user_trending_modules($args){
		global $PAGE;
		$search = $args->search ? $args->search : NULL;
		$moduletype = $args->filtervalues->module_type ? $args->filtervalues->module_type : NULL;
		$trending_data = $this->get_trending_modules_query($args->config, $search, false, $moduletype, $moduletype, $args->filtervalues);
		$sql = $trending_data['sql'];
		$ordersql = $trending_data['ordersql'];
		$params = $trending_data['params'];
		$records = $this->db->get_records_sql($sql.$ordersql, $params, $args->limitfrom, $args->limitnum);
		$data = array();
		$renderer = $PAGE->get_renderer('local_ratings');
		$thisrender = $PAGE->get_renderer('block_trending_modules');
		foreach($records AS $record){
			$meathodname = $record->module_type.'_content';
			$thisdata = $this->$meathodname($record);
			$thisdata['ratings_content'] = $renderer->render_ratings_data($record->module_type, $record->module_id, $record->module_rating, $args->rateWidth);
			$icontag = \html_writer::tag('i', '', array('class' => 'fa fa-th suggestion_icon', 'title' => get_string('suggestions', 'block_trending_modules')));
			if(!isset($args->filtervalues->show_suggestions) || $args->filtervalues->show_suggestions > 0){
				$thisdata['suggestions_btn'] = $thisrender->get_trending_modules_button($record->module_type, $record->module_id, $icontag, $record->module_tags);
			}
			// $thisdata['background_logourl'] = $thisdata['background_logourl']->out();
			$data[] = $thisdata;
		}
		return $data;
	}
	public function get_total_modules_count($args){
		$search = $args->search ? $args->search : NULL;
		$moduletype = $args->filtervalues->module_type ? $args->filtervalues->module_type : NULL;
		$trending_data = $this->get_trending_modules_query($args->config, $search, True, $moduletype, $moduletype, $args->filtervalues);
		$sql = $trending_data['sql'];
		$ordersql = $trending_data['ordersql'];
		$params = $trending_data['params'];
		// print_object($sql.$ordersql);
		// print_object($params);
		$count = $this->db->count_records_sql($sql, $params);
		return $count; 
	}
    private function local_classroom_content($module){
    	global $CFG,$USER,$DB;
    	$return = array();
		$sql = "SELECT cu.id FROM {local_classroom_users} AS cu 
							WHERE cu.classroomid = $module->module_id AND cu.userid = $USER->id";
		$classroom_enrolled=$DB->get_records_sql($sql);
		if(!$classroom_enrolled){
    	$description = strip_tags($module->module_description);
    	$return['description'] = strlen($description) > 50 ? substr($description, 0, 50).'...' : $description; 
    	$return['description_title'] = $description;
    	$return['modulename'] = strlen($module->module_name) > 13 ? substr($module->module_name, 0, 13).'...': $module->module_name;
    	$return['modulename_title'] = $module->module_name;

    	// added for popup content
    	$return['functionname'] = 'classroominfo';
    	$return['selector'] = 'classroom'.$module->module_id;
    	$return['moduleidentifier'] = 'crid';
    	$return['moduleid'] = $module->module_id;
    	$return['modulelink'] = $CFG->wwwroot . '/local/classroom/view.php?cid='.$module->module_id;
    	$return['background_logourl'] = ((new \local_classroom\classroom)->classroom_logo($module->module_imagelogo));
    	if($return['background_logourl'] == 0){
            require_once($CFG->dirroot.'/local/includes.php');
            $includes = new \user_course_details();
            $return['background_logourl'] = ($includes->get_classes_summary_files($module))->out();
        }else{
        	$return['background_logourl'] = $return['background_logourl']->out();
        }
	}
    	return $return;
    }
    private function local_courses_content($module){
    	global $CFG,$USER;
    	$return = array();
		$isenrolled = is_enrolled(\context_course::instance($module->module_id, $USER->id, '', true));
    	if(!$isenrolled){
		$description = strip_tags($module->module_description);
    	$return['description'] = strlen($description) > 50 ? substr($description, 0, 50).'...' : $description; 
    	$return['description_title'] = $description;
    	$return['modulename'] = strlen($module->module_name) > 13 ? substr($module->module_name, 0, 13).'...': $module->module_name;
    	$return['modulename_title'] = $module->module_name;

    	// added for popup content
    	$return['functionname'] = 'init';
    	$return['selector'] = 'courseinfo'.$module->module_id;
    	$return['moduleidentifier'] = 'courseid';
    	$return['moduleid'] = $module->module_id;
    	$return['modulelink'] = $CFG->wwwroot . '/local/search/coursedetails.php?id='.$module->module_id;
    	require_once($CFG->dirroot.'/local/includes.php');
    	$includes = new \user_course_details();
		$course_record = get_course($module->module_id);
		$background_logourl= ($includes->course_summary_files($course_record));
		if(is_a($background_logourl, 'moodle_url')){
			$return['background_logourl'] = $background_logourl->out();
		}else{
			$return['background_logourl'] = $background_logourl;
		}
		}
    	return $return;
    }
    private function local_certification_content($module){
    	global $CFG;
    	$return = array();
    	$description = strip_tags($module->module_description);
    	$return['description'] = strlen($description) > 50 ? substr($description, 0, 50).'...' : $description; 
    	$return['description_title'] = $description;
    	$return['modulename'] = strlen($module->module_name) > 13 ? substr($module->module_name, 0, 13).'...': $module->module_name;
    	$return['modulename_title'] = $module->module_name;

    	// added for popup content
    	$return['functionname'] = 'certificationinfo';
    	$return['selector'] = 'certification'.$module->module_id;
    	$return['moduleidentifier'] = 'certificationid';
    	$return['moduleid'] = $module->module_id;

    	$return['background_logourl'] = ((new \local_certification\certification)->certification_logo($module->module_imagelogo));
    	if($return['background_logourl'] == 0){
            require_once($CFG->dirroot.'/local/includes.php');
            $includes = new \user_course_details();
            $return['background_logourl'] = ($includes->get_classes_summary_files($module))->out();
        }else{
        	$return['background_logourl'] = $return['background_logourl']->out();
        }
    	return $return;
    }
    private function local_program_content($module){
    	global $CFG,$DB,$USER;
    	$return = array();
		$sql = "SELECT pu.id FROM {local_program_users} AS pu 
							WHERE pu.programid = $module->module_id AND pu.userid = $USER->id";
		$program_enrolled=$DB->get_records_sql($sql);
		if(!$program_enrolled){
    	$description = strip_tags($module->module_description);
    	$return['description'] = strlen($description) > 50 ? substr($description, 0, 50).'...' : $description; 
    	$return['description_title'] = $description;
    	$return['modulename'] = strlen($module->module_name) > 13 ? substr($module->module_name, 0, 13).'...': $module->module_name;
    	$return['modulename_title'] = $module->module_name;

    	// added for popup content
    	$return['functionname'] = 'programinfo';
    	$return['selector'] = 'programinfo'.$module->module_id;
    	$return['moduleidentifier'] = 'programid';
    	$return['moduleid'] = $module->module_id;
    	$return['modulelink'] = $CFG->wwwroot . '/local/program/view.php?bcid='.$module->module_id;
    	$background_logourl = ((new \local_program\program)->program_logo($module->module_imagelogo));
    	if($background_logourl == 0){
            require_once($CFG->dirroot.'/local/includes.php');
            $includes = new \user_course_details();
            $return['background_logourl'] = ($includes->get_classes_summary_files($module))->out();
        }else{
        	if(is_a($background_logourl, 'moodle_url'))
        	$return['background_logourl'] = $background_logourl->out();
        }
	}
    	return $return;
    }
    private function local_learningplan_content($module){
    	global $CFG,$DB,$USER;
    	$return = array();
		$sql = "SELECT lu.id FROM {local_learningplan_user} AS lu 
							WHERE lu.planid = $module->module_id AND lu.userid = $USER->id";
		$plan_enrolled=$DB->get_records_sql($sql);
		if(!$plan_enrolled){
    	$description = strip_tags($module->module_description);
    	$return['description'] = strlen($description) > 50 ? substr($description, 0, 50).'...' : $description; 
    	$return['description_title'] = $description;
    	$return['modulename'] = strlen($module->module_name) > 13 ? substr($module->module_name, 0, 13).'...': $module->module_name;
    	$return['modulename_title'] = $module->module_name;

    	// added for popup content
    	$return['functionname'] = 'learningplaninfo';
    	$return['selector'] = 'learningplan'.$module->module_id;
    	$return['moduleidentifier'] = 'learningplanid';
    	$return['moduleid'] = $module->module_id;
    	$return['modulelink'] = $CFG->wwwroot . '/local/learningplan/lpathinfo.php?id='.$module->module_id;
    	$learningplan_lib = new \local_learningplan\lib\lib();
    	$return['background_logourl'] = $learningplan_lib->get_learningplansummaryfile($module->module_id);
		}
		return $return;
    }
    public function get_existing_moduleinfo($module_type, $functionname){
    	$classname = "\\$module_type\\local\\general_lib";
    	if(class_exists($classname)){
    		$classobject = new $classname;
    		if(method_exists($classobject, $functionname)){
    			return $classobject;
    		}
    	}
    }
}
