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
 * @subpackage  my_event_calendar
 * @author eabyas  <info@eabyas.in>
**/
function check_event_access($event) {
	global $DB, $USER, $CFG;
	$context = context_system::instance();
	$dbman = $DB->get_manager();
	if($event->plugin){
		if (!$dbman->table_exists($event->plugin)) {
			$rowid = $event->plugin_instance;
			$table = $CFG->prefix.''.$event->plugin.'s';
		} else {
			if ($event->local_eventtype === "session_open" OR $event->local_eventtype === "session_close") {
	            $rowid = $event->plugin_itemid;
				if($event->plugin == 'local_program'){
	            	$table = $CFG->prefix.'local_bc_course_sessions';
	            }else{
	            	$table = $CFG->prefix.''.$event->plugin.'_sessions';
	            }
			} else {
            	$rowid = $event->plugin_instance;
            	$table = $CFG->prefix.''.$event->plugin;		
			}	
		}
	}else{
    	$rowid = $event->instance;
    	if($event->modulename){
    		$table = $CFG->prefix.''.$event->modulename;
    	}else{
    		$table = false;
    	}
    	$table = $CFG->prefix.''.$event->modulename;
    	$event->plugin = 'mod';
    }

	if($table){
		$itemsql = "SELECT * FROM {$table} WHERE id = ? ";
		$get_item = $DB->get_record_sql($itemsql, array($rowid));
	}

	$enrolled = false;
	$self_enrol = false;
	if ($get_item) {
		// check user is enrolled to event or not
		switch($event->plugin) {	
			case 'local_onlinetests':
				if (( is_siteadmin() || 
					has_capability('local/costcenter:manage_multiorganizations',$context) || 
					has_capability('local/costcenter:manage_ownorganization',$context)) || 
					$DB->record_exists_sql("SELECT ou.id FROM {local_onlinetest_users} ou, {local_onlinetests} o WHERE o.id = ou.onlinetestid AND ou.userid = {$USER->id} AND o.id = {$get_item->id} ") )
				$enrolled = true;
				else
				$enrolled = false;
				break;
			case 'local_evaluation':
				if (( is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations',$context) || has_capability('local/costcenter:manage_ownorganization',$context)) || 
					$DB->record_exists_sql("SELECT eu.id from {local_evaluation_users} AS eu  
						JOIN {local_evaluations} AS e ON e.id = eu.evaluationid 
						WHERE eu.userid = $USER->id AND e.id = $get_item->id AND e.evaluationmode LIKE 'SE' AND e.visible=1 ") || 
					$DB->record_exists_sql("SELECT eu.id from {local_evaluation_users} AS eu 
							JOIN {local_evaluations} AS e ON e.id=eu.evaluationid
							JOIN {user} AS u ON u.id=eu.userid
							WHERE u.open_supervisorid = $USER->id AND e.id = $get_item->id AND e.evaluationmode LIKE 'SP' AND e.visible=1 "))
				$enrolled = true;
				else
				$enrolled = false;
				break;
			case 'local_classroom':
				if ($event->local_eventtype === "session_open" OR $event->local_eventtype === "session_close") {
					$itemid = $get_item->classroomid;
					$training_or_session = 'session';
				} else {
					$itemid = $get_item->id;
					$training_or_session = 'training';
				}
				if ( ($DB->record_exists_sql("SELECT cu.id from {local_classroom_users} cu, {local_classroom} c where c.id = cu.classroomid AND cu.userid = {$USER->id} AND c.id = {$itemid}") && !has_capability('local/classroom:trainer_classroom', $context)) OR (( is_siteadmin() OR has_capability('local/costcenter:manage_multiorganizations',$context) OR (has_capability('local/costcenter:manage_ownorganization',$context))))) {					
					$enrolled = true;
				} else {
					if ($DB->record_exists_sql("SELECT ct.id from {local_classroom_trainers} ct, {local_classroom} c where c.id = ct.classroomid AND ct.trainerid = {$USER->id} AND c.id = {$itemid}") && has_capability('local/classroom:trainer_viewclassroom',$context))
					$enrolled = true;
					else
					$enrolled = false;
				}
				// get sesion / classroom info
				if ($training_or_session == 'session') {
					$training_info = new stdclass();
					
					if ($get_item->trainerid)
					$training_info->trianers = $DB->get_field_sql("SELECT concat(u.firstname,' ', u.lastname) as trainer FROM {user} u WHERE u.id = {$get_item->trainerid} " );
					else
					$training_info->trianers = get_string('notavailable','block_my_event_calendar');
					
					if ($get_item->roommid){
						$locations = $DB->get_records_sql_menu("SELECT concat(r.name, ' / ',  r.building, ' / ', r.address) AS location FROM {local_location_room} r WHERE r.id = {$get_item->roommid} " );
						$training_info->location = implode(', ', $locations);
					}
					else
					$training_info->location = get_string('notavailable','block_my_event_calendar');
					
					$training_info->type = ($get_item->onlinesession) ? get_string('online','block_my_event_calendar') : get_string('offline','block_my_event_calendar');
					$training_info->endtime = \local_costcenter\lib::get_userdate('M-d-Y H:i', $get_item->timefinish);
				} else {
					$training_info = new stdclass();
                    if ($trainers_list =$DB->get_records_sql_menu("SELECT u.id, concat( u.firstname, ' ', u.lastname) as trainers FROM {user} u WHERE u.id in (select trainerid from {local_classroom_trainers} WHERE classroomid ={$get_item->id} )"))
					$training_info->trianers = implode(', ',$trainers_list);
					else
					$training_info->trianers = get_string('notavailable','block_my_event_calendar');
					if ($get_item->instituteid){
						$locations = $DB->get_records_sql_menu("SELECT concat( fullname,' / ', address) AS location FROM {local_location_institutes} WHERE id = {$get_item->instituteid} " );
						$training_info->location = implode(', ', $locations);
					}
					else
						$training_info->location = get_string('notavailable','block_my_event_calendar');
					
					$training_info->type = get_string('classroom','block_my_event_calendar');
					$training_info->endtime =\local_costcenter\lib::get_userdate('M-d-Y H:i', $get_item->enddate);						
				}
				break;
			case 'local_program':
				if ($event->local_eventtype === "session_open" OR $event->local_eventtype === "session_close") {
					$itemid = $get_item->programid;
					$training_or_session = 'session';
				} else {
					$itemid = $get_item->id;
					$training_or_session = 'training';
				}
				if ( ($DB->record_exists_sql("SELECT pu.id from {local_program_users} pu, {local_program} p WHERE p.id = pu.programid AND pu.userid = $USER->id AND p.id = $itemid and p.visible=1") && !has_capability('local/program:trainer_viewprogram', context_system::instance())) OR (( is_siteadmin() OR has_capability('local/costcenter:manage_multiorganizations',$context) OR (has_capability('local/costcenter:manage_ownorganization',$context))))) {					
					$enrolled = true;
				} else {
					if ($DB->record_exists_sql("SELECT pu.id from {local_bc_course_sessions} pu, {local_program} p where p.id = pu.programid AND pu.trainerid = $USER->id AND p.id = $itemid and p.visible=1") && has_capability('local/program:trainer_viewprogram',$context))
					$enrolled = true;
					else
					$enrolled = false;
				}
				// get sesion / classroom info
				if ($training_or_session == 'session') {
					$training_info = new stdclass();
					
					if ($get_item->trainerid)
					$training_info->trianers = $DB->get_field_sql("SELECT concat(u.firstname,' ', u.lastname) as trainer from {user} u where u.id = $get_item->trainerid " );
					else
					$training_info->trianers = get_string('notavailable','block_my_event_calendar');
					
					if ($get_item->roommid){
						$locations = $DB->get_records_sql_menu("SELECT r.id, concat(r.name, ' / ',  r.building, ' / ', r.address) as location from {local_location_room} r where r.id = $get_item->roommid " );
						$training_info->location = implode(', ', $locations);
					}else
						$training_info->location = get_string('notavailable','block_my_event_calendar');
					
					$training_info->type = ($get_item->onlinesession) ? get_string('online','block_my_event_calendar') : get_string('classroom','block_my_event_calendar');
					$training_info->endtime = \local_costcenter\lib::get_userdate('M-d-Y H:i', $get_item->timefinish);
				} else {
					$training_info = new stdclass();
					if ($trainers_list = $DB->get_records_sql_menu("SELECT u.id, concat( u.firstname, ' ', u.lastname) as trainers FROM {user} u WHERE u.id IN (select trainerid from {local_program_trainers} where programid = {$get_item->id} )"))
					$training_info->trianers = implode(', ', $trainers_list);                    
                    else
					$training_info->trianers = get_string('notavailable','block_my_event_calendar');
                    
					if ($get_item->instituteid){
						$locations = $DB->get_records_sql_menu("SELECT concat( fullname,' / ', address) AS location FROM {local_location_institutes}  WHERE id = {$get_item->instituteid} " );
						$training_info->location = implode(', ', $locations);
					}
					else
					$training_info->location = get_string('notavailable','block_my_event_calendar');
					
					$training_info->type =get_string('classroom','block_my_event_calendar');
					$training_info->endtime =\local_costcenter\lib::get_userdate('M-d-Y H:i', $get_item->enddate);						
				}
				
				break;
			case 'local_certification':
				if ($event->local_eventtype === "session_open" OR $event->local_eventtype === "session_close") {
					$itemid = $get_item->certificationid;
					$training_or_session = 'session';
				} else {
					$itemid = $get_item->id;
					$training_or_session = 'training';
				}
				if ( ($DB->record_exists_sql("select cu.id from {local_certification_users} cu, {local_certification} c where c.id = cu.certificationid AND cu.userid = $USER->id AND c.id = $itemid")&& !has_capability('local/certification:trainer_certification', context_system::instance())) OR (( is_siteadmin() OR has_capability('local/costcenter:manage_multiorganizations',$context) OR (has_capability('local/costcenter:manage_ownorganization',$context))))) {
					
					
					$enrolled = true;
				} else {
					if ($DB->record_exists_sql("select ct.id from {local_certification_trainers} ct, {local_certification} c where c.id = ct.certificationid AND ct.trainerid = $USER->id AND c.id = $itemid") && has_capability('local/certification:trainer_viewcertification',$context))
					$enrolled = true;
					else
					$enrolled = false;
				}
				// get sesion / classroom info
				if ($training_or_session == 'session') {
					$training_info = new stdclass();
					
					if ($get_item->trainerid)
					$training_info->trianers = $DB->get_field_sql("select concat(u.firstname,' ', u.lastname) as trainer from {user} u where u.id = $get_item->trainerid " );
					else
					$training_info->trianers = get_string('notavailable','block_my_event_calendar');
					
					if ($get_item->roommid){
						$locations = $DB->get_records_sql_menu("SELECT concat(r.name, ' / ',  r.building, ' / ', r.address) as location from {local_location_room} r where r.id = {$get_item->roommid} " );
						$training_info->location = implode(', ', $locations);
					}
					else
						$training_info->location = get_string('notavailable','block_my_event_calendar');
					
					$training_info->type = ($get_item->onlinesession) ? get_string('online','block_my_event_calendar') : get_string('offline','block_my_event_calendar');
					$training_info->endtime = \local_costcenter\lib::get_userdate('M-d-Y H:i', $get_item->timefinish);
				} else {
					$training_info = new stdclass();
                    if ($trainers_list = $DB->get_records_sql_menu("SELECT concat( u.firstname, ' ', u.lastname) as trainers FROM {user} u where u.id in (select trainerid from {local_certification_trainers} where certificationid ={$get_item->id} )"))
						$training_info->trianers = implode(', ', $trainers_list);
					else
                    	$training_info->trianers = get_string('notavailable','block_my_event_calendar');
                    
					if ($get_item->instituteid){
						$locations = $DB->get_records_sql_menu("SELECT concat( fullname,' / ', address) as location from {local_location_institutes}  where id = {$get_item->instituteid} " );
						$training_info->location = implode(', ', $locations);
					}
					else
					$training_info->location = get_string('notavailable','block_my_event_calendar');
					
					$training_info->type = get_string('classroom','block_my_event_calendar');
					$training_info->endtime =\local_costcenter\lib::get_userdate('M-d-Y H:i', $get_item->enddate);						
				}
				break;
				//this is for all course module view in my calendar
				case 'mod':
					$training_info = new stdclass();
		            if ($DB->record_exists_sql("select ue.id from {user_enrolments} as ue JOIN {enrol} as enrol ON enrol.id = ue.enrolid where (enrol.enrol='manual' OR enrol.enrol = 'self') AND enrol.courseid = $event->courseid AND ue.userid = $USER->id")){
		            	$moduleid = $DB->get_field('modules','id',array('name' => $event->modulename));

		            	$coursemoduleid = $DB->get_field('course_modules','id',array('course' => $event->courseid,'instance' => $event->instance,'module' => $moduleid));

		            	$enrolled = true;
		            	$training_info->instance = $coursemoduleid;
		            }else{
		            	$enrolled = false;
		            }
				break;

				default:	
					$enrolled = false;
				break;
		}
		if (!$enrolled) { // check if the plugin allows self enrollment			
			if ($event->plugin ==='local_program' OR $event->plugin ==='local_classroom' OR $event->plugin ==='local_certification') {
				if ($get_item->costcenter == $USER->open_costcenterid) { // user can enroll self to the item
					if ($get_item->department > 0) {
						$explode_department = explode(',', $get_item->department);
						if (in_array($USER->open_departmentid, $explode_department)) {				
							$self_enrol = true;
						} else {
							$self_enrol = false;
						}
					} else {
						$self_enrol = true;
					}
				} else {
					$self_enrol = false;
				}
				if($self_enrol && !empty($get_item->open_group)){
					$self_enrol = in_array($USER->open_group, explode(',', $get_item->open_group)) ? true : false;
				}
				if($self_enrol && !empty($get_item->open_hrmsrole)){
					$self_enrol = in_array($USER->open_hrmsrole, explode(',', $get_item->open_hrmsrole)) ? true : false;
				}
				if($self_enrol && !empty($get_item->open_designation)){
					$self_enrol = in_array($USER->open_designation, explode(',', $get_item->open_designation)) ? true : false;
				}
				if($self_enrol && !empty($get_item->open_location)){
					$self_enrol = in_array($USER->open_location, explode(',', $get_item->open_location)) ? true : false;
				}
			}
		}
		$return = array('enrolled'=>$enrolled, 'self_enrol'=>$self_enrol, 'training_info'=>$training_info);
	} else {
		$return = array('enrolled'=>$enrolled, 'self_enrol'=>$self_enrol, 'training_info'=>$training_info);
	}
	
	return $return;
}

//user enrolled modules  switch case sql
function common_access_sql() {
    global $USER;
    $plugins = \block_my_event_calendar\calendarlib::event_calendar_plugin_details();
    $sql_array = array();
    if($plugins['onlinetest']){
		$sql_array[] = " when e.plugin = 'local_onlinetests' then (select o.id from {local_onlinetest_users} o JOIN {local_onlinetests} AS lo ON lo.id = o.onlinetestid where o.onlinetestid = e.plugin_instance AND userid = {$USER->id} AND lo.visible = 1 LIMIT 1) ";
	}
	if($plugins['feedback']){
		$sql_array[] = " when e.plugin = 'local_evaluation' then (select o.id from {local_evaluation_users} o JOIN {local_evaluations} AS le ON le.id = o.evaluationid where o.evaluationid = e.plugin_instance AND userid = {$USER->id} AND le.visible = 1 LIMIT 1) ";
	}
	if($plugins['classroom']){
		$sql_array[] = " when e.plugin = 'local_classroom' then (select lcu.id from {local_classroom_users} lcu JOIN {local_classroom} lc ON lc.id = lcu.classroomid where lcu.classroomid = e.plugin_instance AND lc.status = 1 AND lcu.userid = {$USER->id} LIMIT 1) ";
	}
	if($plugins['program']){
		$sql_array[] = " when e.plugin = 'local_program' then (select lpu.id from {local_program_users} lpu, {local_program} p where lpu.programid = e.plugin_instance AND lpu.userid = {$USER->id} and p.id = lpu.programid and p.visible=1 LIMIT 1) ";
	}
	if($plugins['certification']){
		$sql_array[] = " when e.plugin = 'local_certification' then (select lcu.id from {local_certification_users} lcu JOIN {local_certification} lc ON lc.id = lcu.certificationid where lcu.certificationid = e.plugin_instance AND lc.status = 1 AND lcu.userid = {$USER->id} LIMIT 1) ";
	}
	$sql_array[] = " when e.modulename IS NOT NULL then (select ue.id from {course} as c 
				JOIN {enrol} as enrol ON c.id = enrol.courseid
				JOIN {user_enrolments} as ue ON enrol.id = ue.enrolid 
				WHERE enrol.courseid = e.courseid AND ue.userid = $USER->id 
				 LIMIT 1) ";
	$final_concatsql = implode('', $sql_array);
	// $sql = " case
	// 		when e.plugin = 'local_onlinetests' then (select o.id from {local_onlinetest_users} o where o.onlinetestid = e.plugin_instance AND userid = $USER->id)
	// 		when e.plugin = 'local_evaluation' then (select o.id from {local_evaluation_users} o where o.evaluationid = e.plugin_instance AND userid = $USER->id)
	// 		when e.plugin = 'local_classroom' then (select lcu.id from {local_classroom_users} lcu JOIN {local_classroom} lc ON lc.id = lcu.classroomid where lcu.classroomid = e.plugin_instance AND lc.status = 1 AND lcu.userid = $USER->id)
	// 		when e.plugin = 'local_program' then (select lpu.id from {local_program_users} lpu, {local_program} p where lpu.programid = e.plugin_instance AND lpu.userid = $USER->id and p.id = lpu.programid and p.visible=1)
	// 		when e.plugin = 'local_certification' then (select lcu.id from {local_certification_users} lcu JOIN {local_certification} lc ON lc.id = lcu.certificationid where lcu.certificationid = e.plugin_instance AND lc.status = 1 AND lcu.userid = $USER->id )
	// 		when e.modulename IS NOT NULL then (select ue.id from {course} as c 
	// 						JOIN {enrol} as enrol ON c.id = enrol.courseid
	// 						JOIN {user_enrolments} as ue ON enrol.id = ue.enrolid where enrol.courseid = e.courseid AND c.open_identifiedas IN (3) AND ue.userid = $USER->id )
	// 	end ";
	$sql = " case $final_concatsql end > 0 ";
	// echo $sql; 
    return $sql;
}
