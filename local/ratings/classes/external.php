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
 */
defined('MOODLE_INTERNAL') || die;
class local_ratings_external extends external_api{
	public static function get_specific_rating_info_parameters(){
		return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for rating', false),
                'itemid' => new external_value(PARAM_INT, 'itemid', 0),
                'ratearea' => new external_value(PARAM_TEXT, 'ratearea', false)
            )
        );
	}
	public static function get_specific_rating_info($contextid, $itemid, $ratearea){
		global $PAGE;
		$params = self::validate_parameters(
            self::get_specific_rating_info_parameters(),
            [
                'contextid' => $contextid,
                'itemid' => $itemid,
                'ratearea' => $ratearea
            ]
        );
		$PAGE->set_context(\context_system::instance());
        $lib = new \local_ratings\lib\ratinglib();
        $return = $lib->get_specific_rating_info($params['itemid'], $params['ratearea']);
        return array('rows' => $return);
	}
	public static function get_specific_rating_info_returns(){
		// return new external_value(PARAM_RAW, 'content');
        return new external_single_structure([
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'rateheader' => new external_value(PARAM_TEXT, 'Rating header'),
                    'bar_class' => new external_value(PARAM_TEXT, 'rating bar class'),
                    'ratedusers_count' => new external_value(PARAM_INT, 'Rated Users Count'),
                    'rating_perc' => new external_value(PARAM_RAW, 'percentage value of rating'),
                    'rating' => new external_value(PARAM_INT, 'Rating', VALUE_OPTIONAL),
                ])
            )
        ]);
	}
    public static function save_comment_parameters(){
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for rating', false),
                'itemid' => new external_value(PARAM_INT, 'itemid'),
                'commentarea' => new external_value(PARAM_TEXT, 'Comment Area'),
                'userid' => new external_value(PARAM_INT, 'Commenting User'),
                'comment' => new external_value(PARAM_RAW, 'Comment Area'),
            )
        );
    }
    public static function save_comment($contextid, $itemid, $commentarea, $userid, $comment){
        global $DB;
        $params = self::validate_parameters(
            self::save_comment_parameters(),
            [
                'contextid' => $contextid,
                'itemid' => $itemid,
                'commentarea' => $commentarea,
                'userid' => $userid,
                'comment' => $comment
            ]
        );
        // if(!empty(trim($params['commentarea']))){

        // }
        $commentid = $DB->get_field('local_comment', 'id', array('itemid' => $params['itemid'], 'commentarea' => $params['commentarea'] , 'userid' => $params['userid']));
        $comment_instance = new \stdClass();
        $comment_instance->comment = $params['comment'];
        if($commentid > 0){
            $comment_instance->id = $commentid;
            $comment_instance->timemodified = time();
            $status = $DB->update_record('local_comment', $comment_instance);
        }else{
            $comment_instance->itemid = $params['itemid'];
            $comment_instance->commentarea = $params['commentarea'];
            $comment_instance->userid = $params['userid'];
            $comment_instance->timecreated = time();
            $status = $DB->insert_record('local_comment', $comment_instance, false);
        }
        return array('success' => $status);
    }
    public static function save_comment_returns(){
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'status of comment')
            )
        );
    }
    public static function display_ratings_content_parameters(){
        return new external_function_parameters([
            'options' => new external_value(PARAM_RAW, 'options'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                VALUE_DEFAULT, 0),
            'contextid' => new external_value(PARAM_INT, 'contextid'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }
    public static function display_ratings_content(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata){

        $params = self::validate_parameters(
            self::display_ratings_content_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );

        $offset = $params['offset'];
        $limit = $params['limit'];
        $filtervalues = json_decode($filterdata);
        $filteroptions = json_decode($options);
        if(is_array($filtervalues)){
            $filtervalues = (object)$filtervalues;
        }
        // $filtervalues->parentid = $filteroptions->parentid;
        $defaults = new \stdClass();
        $defaults->thead = false;
        $defaults->start = $offset;
        $defaults->length = $limit;
        $decoded_dataoptions = json_decode($params['dataoptions']);
        $defaults->itemid = $decoded_dataoptions->itemid;
        $defaults->commentarea = $decoded_dataoptions->commentarea;
        // $rating_render = $PAGE->get_renderer('local_ratings');
        $ratings_lib = new \local_ratings\lib\ratinglib();
        $records = $ratings_lib->get_ratings_content($defaults, $filtervalues);
        $totalcount = $records['totalrecords'];
        $data = $records['records'];
        return [
            'totalcount' => $totalcount,
            'filterdata' => $filterdata,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
        ];

    }
    public static function display_ratings_content_returns(){
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of count in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'id of review'),
                                    'userpic' => new external_value(PARAM_RAW, 'url of userimage'),
                                    'userfullname' => new external_value(PARAM_RAW, 'User Fullname'),
                                    'rating' => new external_value(PARAM_TEXT, 'rating value'),
                                    'likestatus' => new external_value(PARAM_TEXT, 'Status of Like'),
                                    'comment' => new external_value(PARAM_RAW, 'Review posted')
                                )
                            )
                        )
        ]);
    }
    public static function set_module_rating_parameters(){
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for rating', false),
                'itemid' => new external_value(PARAM_INT, 'itemid'),
                'ratearea' => new external_value(PARAM_TEXT, 'Rating Area'),
                'userid' => new external_value(PARAM_INT, 'Rating User'),
                'rating' => new external_value(PARAM_INT, 'Rating value'),
            )
        );
    }
    public static function set_module_rating($contextid, $itemid, $ratearea, $userid, $rating){
        $params = self::validate_parameters(
            self::set_module_rating_parameters(),
            [
                'contextid' => $contextid,
                'itemid' => $itemid,
                'ratearea' => $ratearea,
                'userid' => $userid,
                'rating' => $rating
            ]
        );
        $rating_lib = new \local_rating\lib\ratinglib();
        $rating_lib->
        $rateid = $DB->get_field('local_rating', 'id', array('itemid' => $params['itemid'], 'ratearea' => $params['ratearea'] , 'userid' => $params['userid']));
        $rate_instance = new \stdClass();
        $rate_instance->rating = $params['rating'];
        if($rateid > 0){
            $rate_instance->id = $rateid;
            $rate_instance->timemodified = time();
            $status = $DB->update_record('local_rating', $rate_instance);
        }else{
            $rate_instance->itemid = $params['itemid'];
            $rate_instance->ratearea = $params['ratearea'];
            $rate_instance->userid = $params['userid'];
            $rate_instance->timecreated = time();
            $status = $DB->insert_record('local_rating', $rate_instance);
        }
        return $params['rating'];
    }
    public static function set_module_rating_returns(){
        return new external_value(PARAM_INT, 'Rating posted by the user');
    }
    public static function like_dislike_parameters(){
        return new external_function_parameters(
            array(
                'componentname' => new external_value(PARAM_TEXT, 'component name'),
                'componentid' => new external_value(PARAM_INT, 'component id'),
                'likestatus' => new external_value(PARAM_INT, 'Like status')
            )
        );
    }
    public static function like_dislike($componentname, $componentid, $likestatus){
        global $DB,$USER;
        $params = self::validate_parameters(
            self::like_dislike_parameters(),
            [
                'componentname' => $componentname,
                'componentid' => $componentid,
                'likestatus' => $likestatus
            ]
        );
        $data = new stdClass();
        $data->userid = $USER->id;
        $data->itemid = $componentid;
        $data->likearea = $componentname;
        if($existdata = $DB->get_record('local_like',array('userid' => $data->userid, 'likearea' => $componentname, 'itemid'=>$componentid))){
            $updatedata = new stdClass();
            $updatedata->id = $existdata->id;
            $updatedata->likestatus = $likestatus;
            $updatedata->timemodified = time();
            $result = $DB->update_record('local_like', $updatedata);
        }
        else{
            $data->timecreated = time();
            $data->timemodified = time();
            $data->likestatus = $likestatus;
            $result = $DB->insert_record('local_like', $data);
        }
            return array('status' => $result);
    }
    public static function like_dislike_returns(){
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_INT, 'status of comment')
            )
        );
    }
    public static function get_likedislike_parameters(){
        return new external_function_parameters(
            array(
                'componentname' => new external_value(PARAM_TEXT, 'component name'),
                'componentid' => new external_value(PARAM_INT, 'component id')
            )
        );
    }
    public static function get_likedislike($componentname, $componentid){
        global $DB,$USER;
        $params = self::validate_parameters(
            self::get_likedislike_parameters(),
            [
                'componentname' => $componentname,
                'componentid' => $componentid
            ]
        );
        $likes = $DB->count_records('local_like', array('likearea'=>$componentname, 'itemid' => $componentid, 'likestatus'=>'1'));
        $dislikes = $DB->count_records('local_like', array('likearea'=>$componentname, 'itemid' => $componentid, 'likestatus'=>'2'));
        return array('likes' => $likes, 'dislikes' => $dislikes);
    }
    public static function get_likedislike_returns(){
        return new external_single_structure(
            array(
                'likes' => new external_value(PARAM_INT, 'Likes Count'),
                'dislikes' => new external_value(PARAM_INT, 'Likes Count')
            )
        );
    }
    public static function submit_rating_parameters(){
        return new external_function_parameters(
            array(
                'componentname' => new external_value(PARAM_TEXT, 'component name'),
                'componentid' => new external_value(PARAM_INT, 'component id'),
                'ratingvalue' => new external_value(PARAM_INT, 'Rating value')
            )
        );
    }
    public static function submit_rating($componentname, $componentid, $ratingvalue){
        global $DB,$USER,$CFG;
        require_once($CFG->dirroot.'/local/ratings/lib.php');
        $params = self::validate_parameters(
            self::submit_rating_parameters(),
            [
                'componentname' => $componentname,
                'componentid' => $componentid,
                'ratingvalue' => $ratingvalue
            ]
        );

        $data = new stdClass();
        $data->userid = $USER->id;
        $data->itemid = $componentid;
        $data->ratearea = $componentname;
        if($existdata = $DB->get_record('local_rating',array('userid' => $data->userid, 'ratearea' => $componentname, 'itemid'=>$componentid))){
            $updatedata = new stdClass();
            $updatedata->id = $existdata->id;
            $updatedata->rating = $ratingvalue;
            $updatedata->timemodified = time();
            $result = $DB->update_record('local_rating', $updatedata);
        }
        else{
            $data->timecreated = time();
            $data->timemodified = time();
            $data->rating = $ratingvalue;
            $result = $DB->insert_record('local_rating', $data);
        }
        $numstars = $ratingvalue*2;
        $return_values = array();

        $avgratings = get_rating($componentid, $componentname);
        $starsobtained = $avgratings->avg/*/2*/;
        $res = "$starsobtained";
        $return_values = array();
        $return_values[] = $res;
        $return_values[] = $avgratings->count;
        $ratings_likes = $DB->get_record('local_ratings_likes', array('module_area' => $componentname, 'module_id' => $componentid));
        if($ratings_likes){
            $ratings_likes->module_rating = $res;
            $ratings_likes->module_rating_users = $avgratings->count;
            $ratings_likes->timemodified = time();
            $DB->update_record('local_ratings_likes', $ratings_likes);
        }else{
            $ratings_likes = new stdClass();
            $ratings_likes->module_id = $componentid;
            $ratings_likes->module_area = $componentname;
            $ratings_likes->module_rating = $res;
            $ratings_likes->module_rating_users = $avgratings->count;
            $ratings_likes->timecreated = time();
            $DB->insert_record('local_ratings_likes', $ratings_likes);
        }
        if(class_exists('\block_trending_modules\lib')){
            $dataobject = new stdClass();
            $dataobject->update_rating = True;
            $dataobject->id = $componentid;
            $dataobject->module_type = $componentname;
            $dataobject->average_rating = $res;
            $dataobject->rated_users = $avgratings->count;
            $class = (new \block_trending_modules\lib())->trending_modules_crud($dataobject, $componentname);
        }
        return array('status' => $result);
    }
    public static function submit_rating_returns(){
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_INT, 'Status')
            )
        );
    }
    public static function get_ratings_parameters(){
        return new external_function_parameters(
            array(
                'componentname' => new external_value(PARAM_TEXT, 'component name'),
                'componentid' => new external_value(PARAM_INT, 'component id'),
                'ratingvalue' => new external_value(PARAM_INT, 'Rating value')
            )
        );
    }
    public static function get_ratings($componentname, $componentid, $ratingvalue){
        global $DB,$USER,$CFG;
        require_once($CFG->dirroot.'/local/ratings/lib.php');
        $params = self::validate_parameters(
            self::get_ratings_parameters(),
            [
                'componentname' => $componentname,
                'componentid' => $componentid,
                'ratingvalue' => $ratingvalue
            ]
        );

        if(is_null($ratingvalue)){
            $modulerating = $DB->get_field('local_ratings_likes', 'module_rating', array('module_id' => $componentid, 'module_area' => $componentname));
        }else{
            $modulerating = $ratingvalue;
        }
        $avgratings = get_rating($componentid, $componentname);
        $avgrating = $avgratings->avg;
        return array('rating' => $modulerating, 'avgrating' => $avgrating);
    }
    public static function get_ratings_returns(){
        return new external_single_structure(
            array(
                'rating' => new external_value(PARAM_INT, 'rating'),
                'avgrating' => new external_value(PARAM_FLOAT, 'avgrating')
            )
        );
    }
    public static function get_reviews_parameters(){
        return new external_function_parameters(
            array(
                'componentname' => new external_value(PARAM_TEXT, 'component name'),
                'componentid' => new external_value(PARAM_INT, 'component id')
            )
        );
    }
    public static function get_reviews($componentname, $componentid){
        global $DB,$USER,$PAGE;
        $params = self::validate_parameters(
            self::get_reviews_parameters(),
            [
                'componentname' => $componentname,
                'componentid' => $componentid
            ]
        );
        $getreviews = $DB->get_records_sql("SELECT c.id as cid,c.itemid,c.commentarea,c.comment,c.timecreated,u.id,u.picture,u.firstname, u.lastname,u.firstnamephonetic,u.lastnamephonetic,
            u.middlename,u.alternatename,u.imagealt,u.email  FROM {local_comment} c JOIN {user} as u ON c.userid = u.id  WHERE c.itemid = $componentid AND c.commentarea = '$componentname'");
        $reviews = [];
        foreach ($getreviews as $review) {
            $list=array();
            $userinfo = array();
            $list['cid'] = $review->cid;
            $list['itemid'] = $review->itemid;
            $list['commentarea'] = $review->commentarea;
            $list['comment'] = $review->comment;
            $list['timecreated'] = $review->timecreated;
            $userinfo['id'] = $review->id;
            $userinfo['firstname'] = $review->firstname;
            $userinfo['lastname'] = $review->lastname;
            $userinfo['email'] = $review->email;
            $user_picture = new user_picture($review, array('link'=>false));
            $user_picture->size = 1;
            $user_picture =$user_picture->get_url($PAGE);
            $userpic = $user_picture->out();
            $userinfo['profilepic'] = $userpic;
            $list['userinfo'] = $userinfo;
            $reviews[]=$list;
        }

        return array('reviews' => $reviews);
    }
    public static function get_reviews_returns(){
        return new external_single_structure(
            array(
                'reviews' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'cid' =>  new external_value(PARAM_INT, 'Comment id'),
                            'itemid' =>  new external_value(PARAM_INT, 'Item id'),
                            'commentarea' =>  new external_value(PARAM_TEXT, 'Component name'),
                            'comment' =>  new external_value(PARAM_RAW, 'Comment'),
                            'timecreated' =>  new external_value(PARAM_RAW, 'Time created'),
                            'userinfo' =>
                                new external_single_structure(
                                    array(
                                        'id' =>  new external_value(PARAM_INT, 'User id'),
                                        'firstname' =>  new external_value(PARAM_TEXT, 'Posted By'),
                                        'lastname' =>  new external_value(PARAM_TEXT, 'Posted By'),
                                        'email' =>  new external_value(PARAM_RAW, 'Posted By'),
                                        'profilepic' => new external_value(PARAM_RAW, 'User Profile')
                                    )
                                )
                        )
                    )
                )
            )
        );
    }
}