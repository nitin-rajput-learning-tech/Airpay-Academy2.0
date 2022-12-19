<?php
define('AJAX_SCRIPT',true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/local/search/lib.php');

global $CFG,$DB,$USER,$PAGE;
$PAGE->set_context(\local_costcenter\lib\accesslib::get_module_context());
$PAGE->set_url('/local/search/filterslist.php');

//new one


require_login();

$catid = optional_param('catid', 0, PARAM_TEXT);
$action = optional_param('action', '', PARAM_RAW);

if($catid && $action == 'itemslist'){
    $tagitem_data = get_itemlist($catid, 6, 0);
    $tagitems = $tagitem_data['itemslist'];
    echo json_encode($tagitems);
    exit;
}
$finallist = [];
$categoriesall = [];
$final_array['categoriesall'] = [];
// moduletype filters
$final_array['categoriesall'][] = get_itemlist('moduletype');
$final_array['categoriesall'][] = get_itemlist('learningtype');
$final_array['categoriesall'][] = get_itemlist('categories');
$final_array['categoriesall'][] = get_itemlist('level');
$final_array['categoriesall'][] = get_itemlist('skill');

$final = array();
$final['finallist'] = $final_array;
echo json_encode($final);

function get_itemlist($catid, $start = 0, $limit = 6){
	global $DB;
	switch($catid){
		case 'moduletype':
			$itemslist['learningpath'] = ['tagitemid' => 'moduletype_learningpath', 'tagitemname' => 'LP', 'tagitemshortname' => 'learningpath', 'coursecount' => local_search_get_coursecount_for_modules(['learningpath'])];
			$itemslist['ilt'] = ['tagitemid' => 'moduletype_classroom', 'tagitemname' => 'ILT', 'tagitemshortname' => 'classroom', 'coursecount' => local_search_get_coursecount_for_modules(['classroom'])];
            $itemslist['elearning'] = ['tagitemid' => 'moduletype_elearning', 'tagitemname' => 'E-Learning', 'tagitemshortname' => 'elearning', 'coursecount' => local_search_get_coursecount_for_modules(['elearning'])];
			$itemslist['program'] = ['tagitemid' => 'moduletype_program', 'tagitemname' => 'Program', 'tagitemshortname' => 'program', 'coursecount' => local_search_get_coursecount_for_modules(['program'])];
			ksort($itemslist);
            return ['catcode' => 'moduletype', 'tagcatname' => 'Module Type', 'itemslist' => $itemslist, 'showviewmore' => false];
		break;
		case 'learningtype':
			$itemslist = [];
			if($start == 0){
				$itemslist['learningpath'] = ['tagitemid' => 'learningtype_learningpath', 'tagitemname' => 'LP', 'tagitemshortname' => 'learningpath', 'coursecount' => local_search_get_coursecount_for_modules([LP => 'learningpath'])];
				$itemslist['ilt'] = ['tagitemid' => 'learningtype_classroom', 'tagitemname' => 'ILT', 'tagitemshortname' => 'classroom', 'coursecount' => local_search_get_coursecount_for_modules([ILT => 'classroom'])];
                $itemslist['elearning'] = ['tagitemid' => 'learningtype_elearning', 'tagitemname' => 'E-Learning', 'tagitemshortname' => 'elearning', 'coursecount' => local_search_get_coursecount_for_modules([ELE => 'elearning'])];
	            $itemslist['iltcourse'] = ['tagitemid' => 'learningtype_iltcourse', 'tagitemname' => 'ILT COURSE', 'tagitemshortname' => 'iltcourse', 'coursecount' => local_search_get_coursecount_for_modules([ICOURSE => 'iltcourse'])];
				$itemslist['mooc'] = ['tagitemid' => 'learningtype_mooc', 'tagitemname' => 'MOOC', 'tagitemshortname' => 'mooc', 'coursecount' => local_search_get_coursecount_for_modules([MOOC => 'mooc'])];
				$itemslist['learningpathcourse'] = ['tagitemid' => 'learningtype_learningpathcourse', 'tagitemname' => 'LP COURSE', 'tagitemshortname' => 'learningpathcourse', 'coursecount' => local_search_get_coursecount_for_modules([LPCOURSE => 'learningpathcourse'])];
			} else {
				$sql = "SELECT id, course_type, shortname FROM {local_course_types} WHERE id > 4 AND active = 1";
			 	$ctypes = $DB->get_records_sql($sql);
			    foreach($ctypes AS $customtype){
	        		$itemslist['custom_'.$customtype->shortname] = ['tagitemid' => 'learningtype_'.$customtype->shortname, 'tagitemname' => $customtype->course_type, 'tagitemshortname' => $customtype->shortname, 'coursecount' => local_search_get_coursecount_for_modules([$customtype->id => $customtype->shortname])];
	        	}
            }
        	ksort($itemslist);
            return ['catcode' => 'learningtype', 'tagcatname' => 'Learning Type', 'itemslist' => $itemslist, 'showviewmore' => true];
		break;

	   	case 'categories':
			if($start == 0){
				$categorySql = "SELECT id, name FROM {course_categories} WHERE visible = 1 ";
				$categories = $DB->get_records_sql_menu($categorySql, [], 0,7);
				if(count($categories) == 7){
					array_pop($categories);
					$showviewmore = true;
				}else{
					$showviewmore = false;
				}
			}else{
				$categorySql = "SELECT id, name FROM {course_categories} WHERE visible = 1 ";
				$categories = $DB->get_records_sql_menu($categorySql, [], 6,0);
			}
			$itemslist = [];
			foreach($categories AS $catid => $catname){
				$coursecount = local_search\output\allcourses::get_available_catalogtypes(['categories_'.$catid])['sumofallrecords'];
				$itemslist[] = ['tagitemid' => 'categories_'.$catid, 'tagitemname' => $catname, 'tagitemshortname' => $catname, 'coursecount' => $coursecount];
			}
			return ['catcode' => 'categories', 'tagcatname' => 'Category ', 'itemslist' => $itemslist, 'showviewmore' => $showviewmore];
		break;
		case 'level':
		  	list($itemslist, $showviewmore) = local_search_get_itemlist_level($start, $limit);
		  	return ['catcode' => 'level', 'tagcatname' => 'Level', 'itemslist' => $itemslist, 'showviewmore' => $showviewmore];
		  	break;

		case 'skill':
		  	list($itemslist, $showviewmore) = local_search_get_itemlist_skill($start, $limit);
		  	return ['catcode' => 'skill', 'tagcatname' => 'Skill Category', 'itemslist' => $itemslist, 'showviewmore' => $showviewmore];
	  	break;
     }
}
