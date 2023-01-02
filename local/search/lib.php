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

	$response = (new allcourses())->get_available_catalogtypes($moduletype);
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

function local_search_get_itemlist_skill($start = 0, $limit = 6){
	global $DB, $USER;
	$selectsql = "SELECT id, name as value ";
	$countsql = "SELECT count(name) ";
	$sql .= " FROM {local_skill_categories} AS u WHERE 1=1";
	$params = [];
    $skill = $DB->get_records_sql_menu($selectsql.$sql, $params, $start, $limit);
    if($start == 0){
        if(count($skill) == $limit){
            array_pop($skill);
            $showviewmore = true;
        }else{
            $showviewmore = false;
        }
    }
	$itemlist = [];
	foreach($skill AS $skillid => $skillname){
		$response = allcourses::get_available_catalogtypes([['type' => 'skill', 'values' => [$skillid]]]);
    	$sumofallrecords = $response['sumofallrecords'];
		$itemlist[] = ['code' => $skillid, 'name' => $skillname, 'tagitemshortname' => $skillname, 'count' => $sumofallrecords];
	}
	return [$itemlist, $showviewmore];
}


function local_search_get_itemlist_level($start = 0, $limit = 6){
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
    if($start == 0){
        if(count($courselevel) == $limit){
            array_pop($courselevel);
            $showviewmore = true;
        }else{
            $showviewmore = false;
        }
    }
    $itemlist = [];
    foreach($courselevel AS $levelid => $levelname){
       $response = allcourses::get_available_catalogtypes([['type' => 'level', 'values' => [$levelid]]]);
       $sumofallrecords = $response['sumofallrecords'];
       $itemlist[] = ['code' => $levelid,'name' => $levelname,'tagitemshortname' => $levelname, 'count' => $sumofallrecords];
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
function local_search_get_enabled_searchplugin_info(){
    $plugins = get_plugins_with_function('enabled_search');
    $pluginsinfo = [];
    foreach($plugins AS  $plugin_type => $plugin){
        foreach($plugin as $pluginname => $function){
            $pluginsinfo[] = $function();
        }
    }
    return $pluginsinfo;
}
function local_search_get_filters(){
    $filter_array = [];
    $filter_array[] = local_search_get_filter_itemlist('moduletype',0, 0);
    $filter_array[] = local_search_get_filter_itemlist('status',0, 0);
    $filter_array[] = local_search_get_filter_itemlist('learningtype',0, 0);
    $filter_array[] = local_search_get_filter_itemlist('categories',0, 0);
    $filter_array[] = local_search_get_filter_itemlist('level',0, 0);
    $filter_array[] = local_search_get_filter_itemlist('skill',0, 0);
    return $filter_array;
}
function local_search_get_filter_itemlist($catid, $start = 0, $limit = 7){
    global $DB;
    switch($catid){
        case 'moduletype':
            $itemslist = [];
            $filterplugins = get_plugins_with_function('search_page_filter_element');

            foreach($filterplugins AS $filterelements){
                foreach($filterelements AS $filterelement){
                    $filterelement($itemslist);
                }
            }
            return ['type' => 'moduletype', 'name' => 'Module Type', 'options' => $itemslist, 'showviewmore' => false];
        break;
        case 'status':
            $itemslist[] = ['code' => 'notenrolled', 'name' => 'Not Enrolled', 'tagitemshortname' => 'notenrolled_modules', 'count' => local_search_get_coursecount_for_status([['type' => 'status', 'values' => ['notenrolled']]])];
            $itemslist[] = ['code' => 'enrolled', 'name' => 'Enrolled', 'tagitemshortname' => 'enrolled_modules', 'count' => local_search_get_coursecount_for_status([['type' => 'status', 'values' => ['enrolled']]])];
            $itemslist[] = ['code' => 'completed', 'name' => 'Completed', 'tagitemshortname' => 'completed_modules', 'count' => local_search_get_coursecount_for_status([['type' => 'status', 'values' => ['completed']]])];
            return ['type' => 'status', 'name' => 'Status', 'options' => $itemslist, 'showviewmore' => false];
        break;
        case 'learningtype':
            $itemslist = [];
            $sql = "SELECT id, name, shortname FROM {local_course_types} WHERE active = 1 ";
            $ctypes = $DB->get_records_sql($sql, [], $start, $limit);
            if($start == 0){
                if(count($ctypes) == $limit){
                    array_pop($ctypes);
                    $showviewmore = true;
                }else{
                    $showviewmore = false;
                }
            }
            foreach($ctypes AS $customtype){
                $itemslist[] = ['code' => $customtype->id, 'name' => $customtype->name, 'tagitemshortname' => 'learningtype_'.$customtype->shortname, 'count' => local_search_get_coursecount_for_modules([['type' => 'learningtype', 'values' => [$customtype->id]]])];
            }
            ksort($itemslist);
            return ['type' => 'learningtype', 'name' => 'Learning Type', 'options' => $itemslist, 'showviewmore' => $showviewmore];
        break;
        case 'categories':
            $categorySql = "SELECT id, fullname FROM {local_custom_category} WHERE 1 = 1 ";
            $categories = $DB->get_records_sql_menu($categorySql, [], $start, $limit);
            if($start == 0){
                if(count($categories) == 7){
                    array_pop($categories);
                    $showviewmore = true;
                }else{
                    $showviewmore = false;
                }
            }
            $itemslist = [];
            foreach($categories AS $catid => $catname){
                $coursecount = local_search\output\allcourses::get_available_catalogtypes([['type' => 'categories', 'values' => [$catid]]])['sumofallrecords'];
                $itemslist[] = ['code' => $catid, 'name' => $catname, 'tagitemshortname' => $catname, 'count' => $coursecount];
            }
            return ['type' => 'categories', 'name' => 'Category ', 'options' => $itemslist, 'showviewmore' => $showviewmore];
        break;
        case 'level':
            list($itemslist, $showviewmore) = local_search_get_itemlist_level($start, $limit);
            return ['type' => 'level', 'name' => 'Level', 'options' => $itemslist, 'showviewmore' => $showviewmore];
            break;

        case 'skill':
            list($itemslist, $showviewmore) = local_search_get_itemlist_skill($start, $limit);
            return ['type' => 'skill', 'name' => 'Skill Category', 'options' => $itemslist, 'showviewmore' => $showviewmore];
        break;
     }
}


