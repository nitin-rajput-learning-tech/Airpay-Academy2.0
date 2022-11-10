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
 * @subpackage  local_ratings
 * @author eabyas  <info@eabyas.in>
**/
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $USER, $CFG;
 // $activityid = $_REQUEST['activity'];
 $itemid = $_REQUEST['item'];
 $likearea = $_REQUEST['likearea'];
$action = $_REQUEST['action'];
// $courseid = $_REQUEST['course'];
// $moduleid = $_REQUEST['moduleid'];

$l = (isset($action) && $action) ? 2 : 1 ;
$data = new stdClass();
$data->userid = $USER->id;
// $data->activityid = $activityid;
$data->itemid = $itemid;
// $data->courseid = $courseid;
$data->likearea = $likearea;
// $data->moduleid = $moduleid;
$data->likestatus = $l;
if($existdata = $DB->get_record('local_like',array('userid' => $data->userid, 'likearea' => $data->likearea, 'itemid'=>$data->itemid))){
	$updatedata = new stdClass();
	$updatedata->id = $existdata->id;
	$updatedata->likestatus = $data->likestatus;
	$updatedata->timemodified = time();
	$result = $DB->update_record('local_like', $updatedata);
}
else{
	$data->timecreated = time();
	$data->timemodified = time();
	$result = $DB->insert_record('local_like', $data);
}
$return = new stdClass();
$return->like = $DB->count_records('local_like', array('likearea'=>$likearea, 'itemid'=>$itemid, 'likestatus'=>'1'));
$return->dislike = $DB->count_records('local_like', array('likearea'=>$likearea, 'itemid'=>$itemid, 'likestatus'=>'2'));
$ratings_likes = $DB->get_record('local_ratings_likes', array('module_area' => $likearea, 'module_id' => $itemid));
if($ratings_likes){
	$ratings_likes->module_like = $return->like;
	$ratings_likes->module_like_users = $return->like + $return->dislike;
	$ratings_likes->timemodified = time();
	$DB->update_record('local_ratings_likes', $ratings_likes);
}else{
	$ratings_likes = new stdClass();
	$ratings_likes->module_id = $itemid;
	$ratings_likes->module_area = $likearea;
	$ratings_likes->module_like = $return->like;
	$ratings_likes->module_like_users = $return->like + $return->dislike;
	$ratings_likes->timecreated = time();
	$DB->insert_record('local_ratings_likes', $ratings_likes);

}
if(class_exists('\block_trending_modules\lib')){
	$dataobject = new stdClass();
	$dataobject->update_likes = True;
	$dataobject->id = $itemid;
	$dataobject->module_type = $likearea;
	$dataobject->likes = $return->like;
	$dataobject->liked_users = $return->like + $return->dislike;
	$class = (new \block_trending_modules\lib())->trending_modules_crud($dataobject, $ratearea);
}
echo json_encode($return);
