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
    $tagitem_data = local_search_get_filter_itemlist($catid, 6, 0);
    $tagitems = $tagitem_data['options'];
    echo json_encode($tagitems);
    exit;
}
$finallist = [];
$categoriesall = [];
$final_array['categoriesall'] = [];
// moduletype filters
$final_array['categoriesall'][] = local_search_get_filter_itemlist('moduletype');
$final_array['categoriesall'][] = local_search_get_filter_itemlist('status');
$final_array['categoriesall'][] = local_search_get_filter_itemlist('learningtype');
$categories = local_search_get_filter_itemlist('categories',0, 0);
if(count($categories['options']) > 0){
    $final_array['categoriesall'][] = $categories;
}
$levels = local_search_get_filter_itemlist('level',0, 0);
if(count($levels['options']) > 0){
    $final_array['categoriesall'][] = $levels;
}
$skills = local_search_get_filter_itemlist('skill',0, 0);
if(count($skills['options']) > 0){
    $final_array['categoriesall'][] = $skills;
}
$final = array();
$final['finallist'] = $final_array;
echo json_encode($final);