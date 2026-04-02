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
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/blocks/my_event_calendar/lib.php');
global $CFG, $DB, $PAGE, $USER,$OUTPUT;
$systemcontext = context_system::instance();

$cal_startdate = optional_param('start',0,PARAM_RAW);
$cal_endtdate = optional_param('end',0,PARAM_RAW);
$text = optional_param('text','',PARAM_RAW);
$PAGE->set_context($systemcontext);


$time = time();
if(!$cal_startdate || !$cal_endtdate){
	$cal_startdate = \local_costcenter\lib::get_userdate('Y-m-d',$time);
	$cal_endtdate = \local_costcenter\lib::get_userdate('Y-m-d',$time);
	// $cal_startdate = date('Y-m-d',$time);
	// $cal_endtdate = date('Y-m-d',$time);
}

// $startdate = strtotime($cal_startdate);
// $enddate = strtotime($cal_endtdate);
$startdatearr = explode('-', $cal_startdate);
$enddatearr = explode('-', $cal_endtdate);
$startdate = make_timestamp($startdatearr[0], $startdatearr[1], $startdatearr[2]);
$enddate = make_timestamp($enddatearr[0], $enddatearr[1], $enddatearr[2]);

// $startdate = mktime(0 ,0 , 0, $startdatearr[1], $startdatearr[2], $startdatearr[0]);
// $enddate = mktime(0, 0, 0, $enddatearr[1], $enddatearr[2], $enddatearr[0]);

// echo \local_costcenter\lib::get_userdate('Y-m-d H:i',$enddate);

if ($startdate == $enddate) {
	// $modified_start = $cal_startdate;
	// $nextday = \local_costcenter\lib::get_userdate('d m Y',strtotime($modified_start . "+1 days"));
	$enddate = $startdate+86399;
	// $enddate = strtotime($nextday);
	// echo \local_costcenter\lib::get_userdate('Y-m-d H:i',$startdate);
	// echo \local_costcenter\lib::get_userdate('Y-m-d H:i',$enddate);
}
	//sql for all events based on logged in user
	$common_access_sql = common_access_sql();
	$local_events_sql = "SELECT * FROM {event} AS e WHERE 
						timestart >= :timestart AND timestart <= :timeend 
						AND $common_access_sql ";
	$local_events = $DB->get_records_sql($local_events_sql, array('timestart'=>$startdate,'timeend'=>$enddate));
// echo $local_events_sql;
// print_r(array('timestart'=>$startdate,'timeend'=>$enddate));
	$context = context_system::instance();
// print_r($local_events);die;
$eventsarray = array();
foreach ($local_events as $local_event) {
	$can_access = true;

	// check event access to user
	$can_access = check_event_access($local_event);
	if ($can_access['enrolled']) {
		$local_eventstartdate = \local_costcenter\lib::get_userdate('M-d-Y H:i', $local_event->timestart);
		if (( is_siteadmin() OR has_capability('local/costcenter:manage_multiorganizations',$context) OR has_capability('local/costcenter:manage_ownorganization',$context) )) {
			$can_access['self_enrol'] = false;
			$can_access['enrolled'] = true;
		}
		if($can_access['training_info']->instance){
			$local_event->plugin_instance = $local_event->plugin_itemid = $can_access['training_info']->instance;
			$local_event->local_eventtype = $local_event->eventtype;
			$local_event->plugin = 'mod';
		}


		switch ($local_event->plugin) {
			case 'local_evaluation':
                $pluginurl = $CFG->wwwroot.'/local/evaluation/eval_view.php?id='.$local_event->plugin_instance;
                $popup = false;
            break;
            case 'local_classroom':
                $pluginurl = 'javascript:void(0)';
                $popup = true;
                $instancename = $DB->get_field('local_classroom','name',array('id' => $local_event->plugin_instance));
            break;
            case 'local_program':
                 $pluginurl = 'javascript:void(0)';
                 $popup = true;
                 $instancename = $DB->get_field('local_program','name',array('id' => $local_event->plugin_instance));
            break;
            case 'local_certification':
                $pluginurl = 'javascript:void(0)';
                $popup = true;
                $instancename = $DB->get_field('local_certification','name',array('id' => $local_event->plugin_instance));
            break;
            case 'local_onlinetests':
                $pluginurl = $CFG->wwwroot.'/mod/quiz/view.php?id='.$local_event->plugin_itemid;
                $popup = false;
            break;

            case 'mod':
                $pluginurl = $CFG->wwwroot.'/'.$local_event->plugin.'/'.$local_event->modulename.'/view.php?id='.$local_event->plugin_instance;
                $popup = false;
            break;
            case 'user':
                $pluginurl = 'javascript:void(0)';
                $popup = true;
            break;

            default:
                $pluginurl = $CFG->wwwroot.'/';
                $popup = false;
		}

		if ($local_event->eventtype === "open" || $local_event->eventtype === "session_open" ) {
			$string = get_string('openson','block_my_event_calendar');
		}else if($local_event->eventtype === "close" || $local_event->eventtype === "session_close"){

			$string = 'closes on';
			continue;
			$string = get_string('closeson','block_my_event_calendar');
		}else{
			$string = get_string('available','block_my_event_calendar');
		}

		if($instancename){
			$sumstring = $instancename.' '.$local_event->name.' '.$string.' '.$local_eventstartdate;
		}else{
			$sumstring = $local_event->name.' '.$string.' '.$local_eventstartdate;
		}
		
		if(strlen($sumstring) > 25){
			$smallstring = substr($sumstring, 0, 25);
			$eventsname = $smallstring.'...';
		}else{
			$eventsname = $sumstring;
		}
		if ($local_event->$can_access['training_info']->endtime > 0) {
			$eventenddate = true;
		}else{
			$eventenddate = false;
		}
		$events = array(
			'id'=>$local_event->id,
			'instance'=>$local_event->plugin_instance,
			'itemid'=>$local_event->plugin_itemid,
			'local_eventname'=>$local_event->name,
			'local_eventstartdate' =>$local_eventstartdate,
			'local_eventenddate' =>$can_access['training_info']->endtime,
			'start' => \local_costcenter\lib::get_userdate('Y m d', $local_event->timestart),
			//'start' => date('d m Y', $local_event->timestart),
			'modulename' => $local_event->modulename,
			'eventtype' => $local_event->local_eventtype,
			'plugin' => $local_event->plugin,		
			'capability'=>1,
			'enrolled'=>$can_access['enrolled'],
			'self_enrol'=>$can_access['self_enrol'],
			'location'=>$can_access['training_info']->location,
			'training_type'=>$can_access['training_info']->type,
			'trainer'=>$can_access['training_info']->trianers,
			'string'=>$string,
			'pluginurl'=>$pluginurl,
			'eventenddate' => $eventenddate,
			'popup' => $popup,
			'eventfullname' => $eventsname,
		);

		$eventsarray[] = $events;
	} else {
		continue;
	}
} 

//for date display
if($startdate){
	// $selecteddate = \local_costcenter\lib::get_userdate('j M (D)',$startdate );
	$selecteddate = date('j-M-D',$startdate );
	$dates = explode('-', $selecteddate);
	$dates[1] = get_string(strtolower($dates[1]), 'block_my_event_calendar');
	$dates[2] = '('.get_string(strtolower($dates[2]), 'block_my_event_calendar').')';
	$selecteddate = implode(' ', $dates);

}

$content = $OUTPUT->render_from_template('block_my_event_calendar/myevents', array('events' => $eventsarray,'selecteddate' => $selecteddate));
if($text == 'default'){
	//this is for day color based on that moduletypes
	echo $response = json_encode($eventsarray);
}else{
	//events are dipalying with the selected date
	echo $response = json_encode($content);
}
