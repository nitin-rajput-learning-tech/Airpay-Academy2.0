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
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_search
 */

defined('MOODLE_INTERNAL') || die();
use local_search\output\allcourses as allcourses;

    /**
     * @param object $coursedetails
     */
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_search_leftmenunode(){
    $systemcontext = \local_costcenter\lib\accesslib::get_module_context();
    $catalognode = '';
    if(has_capability('local/search:viewcatalog',$systemcontext) || is_siteadmin()){
        $catalognode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_calalogue', 'class'=>'pull-left user_nav_div calalogue'));
            $catalog_url = new moodle_url('/local/search/allcourses.php');
            $catalog = html_writer::link($catalog_url, '<i class="fa fa-search" aria-hidden="true"></i><span class="user_navigation_link_text">'.get_string('pluginname','local_search').'</span>',array('class'=>'user_navigation_link'));
            $catalognode .= $catalog;
        $catalognode .= html_writer::end_tag('li');
    }

    return array('5' => $catalognode);
}

function local_search_get_coursecount_for_modules($moduletype){
	// global $DB;

	$response = allcourses::get_available_catalogtypes($moduletype);
    $sumofallrecords = $response['sumofallrecords'];
    return $sumofallrecords;
}
function local_search_get_coursecount_for_status($status){
    $response = allcourses::get_available_catalogtypes($status);
    $sumofallrecords = $response['sumofallrecords'];
    return $sumofallrecords;
}



function local_search_get_itemlist_grade($start = 0, $limit = 5){
	global $DB, $USER;
	$selectsql = "SELECT DISTINCT(open_grade), open_grade as value";
	$countsql = "SELECT count(DISTINCT(open_grade)) ";
	$sql = " FROM {user} AS u WHERE 1=1 AND suspended = 0 AND deleted = 0 AND open_grade != '' ";
	$params = [];
	$systemcontext = \local_costcenter\lib\accesslib::get_module_context();
	if(!(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) && $USER->open_costcenterid > 0){
		$sql .= " AND u.open_costcenterid = :open_costcenterid ";
		$params['open_costcenterid'] = $USER->open_costcenterid;
	}
	$grades = $DB->get_records_sql_menu($selectsql.$sql, $params, $start, $limit);

	$itemlist = [];
    foreach($grades AS $grade){
        $response = allcourses::get_available_catalogtypes(['grade_'.$grade]);
    	$sumofallrecords = $response['sumofallrecords'];
		$itemlist[] = ['tagitemid' => 'grade_'.$grade, 'tagitemname' => $grade, 'tagitemshortname' => $grade, 'coursecount' => $sumofallrecords];
	}
	$showviewmore = false;
	if($start == 0){
		$total_count = $DB->count_records_sql($countsql.$sql, $params);
		$showviewmore = $total_count > 6 ? true : false;
	}
	return [$itemlist, $showviewmore];
}

function local_search_get_itemlist_skill($start = 0, $limit = 5){
	global $DB, $USER;
	$selectsql = "SELECT id, name as value ";
	$countsql = "SELECT count(name) ";
	$sql .= " FROM {local_skill_categories} AS u WHERE 1=1";
	$params = [];
    $skill = $DB->get_records_sql_menu($selectsql.$sql, $params, $start, $limit);
	$itemlist = [];
	foreach($skill AS $skillid => $skillname){
		$response = allcourses::get_available_catalogtypes(['skill_'.$skillid]);
    	$sumofallrecords = $response['sumofallrecords'];
		$itemlist[] = ['tagitemid' => 'skill_'.$skillid, 'tagitemname' => $skillname, 'tagitemshortname' => $skillname, 'coursecount' => $sumofallrecords];
	}
	$showviewmore = false;
	if($start == 0){
		$total_count = $DB->count_records_sql($countsql.$sql, $params);
		$showviewmore = $total_count > 6 ? true : false;
	}
	return [$itemlist, $showviewmore];
}


function local_search_get_itemlist_level($start = 0, $limit = 5){
	global $DB, $USER;
	$selectsql = "SELECT id, name as value ";
	$countsql = "SELECT count(id) ";
	$sql .= " FROM {local_course_levels} AS u WHERE 1=1 ";
	$params = [];
    if(!is_siteadmin() && $USER->open_costcenterid){
        $sql .= " AND u.costcenterid = :costcenterid ";
        $params['costcenterid'] = $USER->open_costcenterid;
    }
    $courselevel = $DB->get_records_sql_menu($selectsql.$sql, $params, $start, $limit);
    $itemlist = [];
    foreach($courselevel AS $levelid => $levelname){
       $response = allcourses::get_available_catalogtypes(['level_'.$levelid]);
       $sumofallrecords = $response['sumofallrecords'];
       $itemlist[] = ['tagitemid' => 'level_'.$levelid,'tagitemname' => $levelname,'tagitemshortname' => $levelname, 'coursecount' => $sumofallrecords];
    }
    $showviewmore = false;
    if($start == 0){
    	$total_count = $DB->count_records_sql($countsql.$sql, $params);
    	$showviewmore = $total_count > 6 ? true : false;
    }
    return [$itemlist, $showviewmore];

}
function local_search_include_search_js(){
    $plugins = get_plugins_with_function('search_page_js');
    foreach($plugins AS $plugin){
        foreach($plugin as $function){
            $function();
        }
    }
}


