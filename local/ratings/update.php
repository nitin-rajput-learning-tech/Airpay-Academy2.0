<?php
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/local/ratings/lib.php');
global $USER,$DB,$CFG;

// $courseid = $_REQUEST['courseid'];
// $activityid = $_REQUEST['activityid'];
$itemid = $_REQUEST['itemid'];
$ratearea = $_REQUEST['ratearea'];
$rating = $_REQUEST['rating'];
$heading = $_REQUEST['heading'];
// if (! $course = $DB->get_record("course", array("id"=>$courseid))) {
//    print_error("Course ID not found");
// }
$rate = new stdClass;
// $rate->courseid = $courseid;
// $rate->activityid = $activityid;
$rate->itemid = $itemid;
$rate->ratearea = $ratearea;
$rate->userid = $USER->id;
$rate->rating = (int)(round($rating));
$rate->timecreated = time();
$rate->timemodified = time();
// $rate->moduleid = $courseid;
// print_object($rate);

if(!$existeddata = $DB->get_record('local_rating', array( 'itemid'=>$itemid, 'ratearea'=>$ratearea, 'userid'=>$USER->id))){
    $rate->id = $DB->insert_record( 'local_rating', $rate );

}
else{
	$updatedata = new stdClass();
	$updatedata->id = $existeddata->id;
	$updatedata->rating = $rate->rating;
	$updatedata->timemodified = time();
	$DB->update_record('local_rating', $updatedata);
}
$numstars = $rating*2;
$return_values = array();

$avgratings = get_rating($itemid, $ratearea);
$starsobtained = $avgratings->avg/*/2*/;
$res = "$starsobtained";
$return_values = array();
$return_values[] = $res;
$return_values[] = $avgratings->count;
$ratings_likes = $DB->get_record('local_ratings_likes', array('module_area' => $ratearea, 'module_id' => $itemid));
if($ratings_likes){
	$ratings_likes->module_rating = $res;
	$ratings_likes->module_rating_users = $avgratings->count;
	$ratings_likes->timemodified = time();
	$DB->update_record('local_ratings_likes', $ratings_likes);
}else{
	$ratings_likes = new stdClass();
	$ratings_likes->module_id = $itemid;
	$ratings_likes->module_area = $ratearea;
	$ratings_likes->module_rating = $res;
	$ratings_likes->module_rating_users = $avgratings->count;
	$ratings_likes->timecreated = time();
	$DB->insert_record('local_ratings_likes', $ratings_likes);
}
if(class_exists('\block_trending_modules\lib')){
	$dataobject = new stdClass();
	$dataobject->update_rating = True;
	$dataobject->id = $itemid;
	$dataobject->module_type = $ratearea;
	$dataobject->average_rating = $res;
	$dataobject->rated_users = $avgratings->count;
	$class = (new \block_trending_modules\lib())->trending_modules_crud($dataobject, $ratearea);
}
//$return_values[] = '(you) - '.userdate($rate->time);
// echo implode('!@', $return_values);
echo $starsobtained;