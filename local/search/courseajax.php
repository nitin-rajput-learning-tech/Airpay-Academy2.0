<?php
define('AJAX_SCRIPT',true);
require_once(dirname(__FILE__) . '/../../config.php');
use local_classroom\classroom as classroom;
use local_classroom\program as program;

use local_search\output\searchlib;

//require_once('../../config.php');
global $CFG,$DB,$USER,$PAGE;
$PAGE->set_context(\local_costcenter\lib\accesslib::get_module_context());
$PAGE->set_url('/local/search/courseajax.php');

require_login();

$tab = optional_param('tab',0,PARAM_INT);
$page= optional_param('page',0, PARAM_INT);
$search= optional_param('search','', PARAM_RAW);
$category = optional_param('category',0,PARAM_INT);
$enrolltype = optional_param('enrolltype',0,PARAM_INT);
$sortid = optional_param('sortid',0, PARAM_RAW);
$selectedfilter = optional_param('selectedfilters',null, PARAM_RAW);

//new one
define('PERPAGE',15);


if($page>=1)
    $page = $page-1;
if(file_exists($CFG->dirroot . '/local/includes.php')){
    require_once($CFG->dirroot . '/local/includes.php');
    $includes = new user_course_details();
}

searchlib::$page = $page;
searchlib::$perpage = PERPAGE;
searchlib::$includesobj = $includes;
searchlib::$search = $search;
searchlib::$category = $category;
searchlib::$enrolltype = $enrolltype;
searchlib::$sortid = $sortid;

$startlimit= $page*PERPAGE;
$selectedfilter = json_decode($selectedfilter, true);

switch($tab){
    case 6: $pages = new \local_search\output\allcourses();
        echo json_encode($pages->main_toget_catalogtypes(PERPAGE, $selectedfilter));
    break;

} // end of switch statement