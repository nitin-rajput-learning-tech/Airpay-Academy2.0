<?php

/* @function display_like_unlike
 *  @function returns option to like or unlike.
 */
function display_like_unlike($itemid, $likearea){
    global $DB, $CFG, $USER, $PAGE, $OUTPUT;
    
    
    $ask_rating_data = ask_for_rating($itemid, $likearea, $heading=NULL, 0);
    if(!$ask_rating_data['enroll']){
        $params = array('class' => 'like_unlike disable_pointer', 'style'=>'pointer-events:none');
    }else{
        $params = array('class' => 'like_unlike');
    }
    $output = html_writer::start_tag('div', $params);
    
    
    $output .= html_writer::start_tag('div', array('class'=>'d-flex align-items-center','id'=>'contents_'.$itemid, 'style'=>'float: left; clear:both;font-size:16px;'));
    $likestyle = "";
    $unlikestyle = "";
    $mylike_unlike = $DB->get_record('local_like',array('userid' => $USER->id, 'itemid' => $itemid, 'likearea' => $likearea));

    if($mylike_unlike){
        if($mylike_unlike->likestatus==1){
            $likestyle = "style='color:#0769ad'";
        }
        else if($mylike_unlike->likestatus==2){
            $unlikestyle = "style='color:#0769ad'";
        }
    }
    //Like button----------
    
    $likeicon = "<i class='fa fa-thumbs-up' $likestyle> </i>";
    $likeEnable = html_writer::start_span('thubmbsup',array('title'=>get_string('like','local_ratings'), 'title'=>get_string('like','local_ratings'), 'onclick'=>"(function(e){ require('local_ratings/ratings').updatevalues({ action: 0 , itemid: ".$itemid.", likearea : '".$likearea."'}) })(event)"));
    $likeEnable .= $likeicon;
    $likeEnable .= html_writer::end_span();

    $likeparams = array('id'=>'label_like_'.$itemid, 'style'=>'float: left; padding: 0 4px 0 0;cursor:pointer;');

	$output .= html_writer::tag('div', $likeEnable, $likeparams);
    //Like count-------------------
    $likecount = $DB->count_records('local_like', array('likearea'=>$likearea, 'itemid'=>$itemid, 'likestatus'=>1));
    $output .= '<span style="float: left;" class="count_likearea_'.$itemid.'">'.$likecount.'</span>';
    
    
    
    //Unlike button----------
    $unlikeicon = "<i class='fa fa-thumbs-down' $unlikestyle> </i>";

    $unlikeEnable = html_writer::div($unlikeicon,'thubmbsdown', array('src'=>$CFG->wwwroot.'/local/ratings/pix/unlikeN.png', 'title'=>get_string('dislike','local_ratings'), 'style'=>'cursor: pointer;', 'onclick'=>"(function(e){ require('local_ratings/ratings').updatevalues({ action: 1 , itemid: ".$itemid.", likearea : '".$likearea."'}) })(event)"));
    $unlikeDisable = html_writer::empty_tag('img', array('src'=>$CFG->wwwroot.'/local/ratings/pix/unlike_disableN.png', 'title'=>get_string('you_disliked_it','local_ratings')));
    $unlike = html_writer::empty_tag('img', array('src'=>$CFG->wwwroot.'/local/ratings/pix/unlike.png'));
    $unlikeparams = array('id'=>'label_unlike_'.$itemid, 'style'=>'float: left; padding: 0 4px 0 15px;cursor:pointer;');

	$output .= html_writer::tag('div', $unlikeEnable, $unlikeparams);
    
    //Dislike count---------------------
    $unlikecount = $DB->count_records('local_like', array('likearea'=>$likearea, 'itemid'=>$itemid, 'likestatus'=>2));
    $output .= '<span style="float: left;" class="count_unlikearea_'.$itemid.'">'.$unlikecount.'</span>';

    $output .= html_writer::end_tag('div'); //End of #contents_$item
    
    
    
    $output .= html_writer::end_tag('div'); //End of .like_unlike
    // $output .= display_comment($itemid, $likearea);
    return $output;
}

/*
 * @function  display_rating
 * @todo function calculates over all rating for course
 * @returns rating image for course
*/
function display_rating($itemid, $ratearea) {
    global $CFG,$DB, $USER, $PAGE, $OUTPUT;
    $PAGE->requires->js_call_amd('local_ratings/ratings', 'trigger');
    $avgratings = get_rating($itemid, $ratearea);
	$existrating = $DB->get_field('local_rating','rating',array('itemid' => $itemid, 'ratearea' => $ratearea, 'userid'=> $USER->id));
    $ratings = $existrating ? $existrating :0;
    $currentuserrating = $DB->get_record('local_rating', array('itemid'=>$itemid, 'ratearea'=>$ratearea, 'userid'=>$USER->id));
    $ask_rating_data = ask_for_rating($itemid, $ratearea, $heading=NULL, isset($currentuserrating->rating));
    
    $res = '<div class="radiostars mt-10">';

    if($ask_rating_data['enroll']){
        $res .= html_writer::div('', '', array('id' => 'userradiostars', 'data-stars' => $ratings, 'data-userid' => $USER->id, 'data-ratearea' => $ratearea, 'data-itemid' => $itemid));

        $options = ['max' => 5,
                'rgbOn' => "#efce2e",
                'rgbOff' => "#9c9b97",
                'rgbSelection' => "#efce2e",
                'indicator' => "fa-star",
                'fontsize' => "18px"
            ];
        $PAGE->requires->js_call_amd('local_ratings/ratings', 'init', array("#userradiostars", json_encode($options)));
        $displayrating = $ratings;
    }else{
        // $res = display_averagerating($itemid, $ratearea, $tooltipdata);
        $renderer = $PAGE->get_renderer('local_ratings');
        $displayrating = $DB->get_field('local_ratings_likes', 'module_rating', array('module_area' => $ratearea, 'module_id' => $itemid));
        $displayrating = !empty($displayrating) ? round($displayrating, 2) : 0;
        $res .= $renderer->render_ratings_data($ratearea, $itemid, $displayrating);
        // echo $res; 
        // print_object($res);exit;
    }
    $res .= '</div>'; //End of .overall_ratings
    $res .= '<div class="overall_users mt-10">';
    $res .= "<div class='mytooltip'>
            <div class='mytooltiptext'><span class='rating_tooltip' data-itemid=$itemid data-ratearea='$ratearea'></span>
            </div>
        </div>";
    $res .="<i class='fa fa-star rating_star overall_ratings_$itemid'>$displayrating</i>&nbsp;";
    $res .= "<span>(<font class='totalcount_$itemid'>$avgratings->count</font> users)</span>";
    $res .= '</div>'; //End of .overall_users

    
    // $res .= '<div class="togglerating togglerating_'.$itemid.'">';
    // if(!isloggedin() || isguestuser() ){
	   // $res .= "<div>You need to login to rate this $ratearea</div>";
    // } else {
	   //  $res .= $ask_rating_data['html'];
    // }
    // echo $res;
    // $res .= '</div>'; //End of .radiostars
    return $res;
}

function display_averagerating($itemid, $ratearea, $tooltipdata) {
    
    $avgratings = get_rating($itemid, $ratearea);

    $averagerating = $avgratings->avg/*/2*/;

    $id = "userradiostars_{$ratearea}_{$itemid}";

    $res = '<div class="radiostars mt-10" style="width:60%; float:left">';
    $res .= "<div id='$id' class='disable_pointer' style='pointer-events:none' disable=disabled></div>";
    $res .= html_writer::script("
            $( document ).ready(function() { 
                $('#".$id."').rateYo({
                  rating : $averagerating,
                  numStars: 5,
                  precision: 1,
                  minValue: 1,
                  maxValue: 5,
                  // spacing   : '5px',
                  multiColor: {
                    'startColor': '#ffc107', //RED
                    'endColor': '#ffc107', //RED
                    // 'endColor'  : '#00FF00'  //GREEN
                  },
                  starWidth: '18px'
                });
            });
        ");
    
    $res .= '</div>'; //End of .overall_ratings
    $res .= '<div class="overall_users mt-10" style="width:40%; float:left">';
    $res .= "<div class='mytooltip'>
                <div class='mytooltiptext'><span class='rating_tooltip' data-itemid=$itemid data-ratearea='$ratearea'></span>
                </div>
            </div>";
    $res .="<i class='fa fa-heart overall_ratings_$itemid' style='color: #0f77d5; font-size:18px;' >$averagerating</i>";
    $res .= " (<font class='totalcount_$itemid'>$avgratings->count</font> users)";
    $res .= '</div>'; //End of .overall_users
    
    return $res;
}

/*
 * @function  ask_for_rating
 * @todo function displays the empty stars to give the rating
 * @returns rating star images 
*/
function ask_for_rating($itemid, $ratearea, $heading ,$currentuserrating=0) {
    global $DB, $USER, $CFG, $OUTPUT;
    $result = html_writer::start_tag('div', array('class' => 'comment_'.$itemid, 'style'=>'padding: 5px;'));
    $user = $DB->get_record('user', array('id'=>$USER->id));
    $result .= html_writer::start_tag('div', array('class'=>'comment_commentarea'));
    $result .= '<div class="example_'.$itemid.'">';
    if ((isloggedin() && !isguestuser())) {
    	$disable = '';
    	// $title = "title='Click on the star to rate this $ratearea'";
    	$enroll = true;
    	// if($courseid!==SITEID){
    	//     $context = get_context_instance(CONTEXT_COURSE, $courseid);
    	//     if(!is_enrolled($context, $USER->id) && !is_siteadmin() && !has_capability('local/feedback:view', $context)) {
    	// 	$enroll = false;
    	// 	$disable = 'disabled="disabled"';
    	// 	$title = 'title="You need to enroll to the '.$ratearea.' to give a rating"';
    	//     }
    	// }
    	// if(!$enroll){
    	//     $result .= '<div>You need to enroll to the '.$ratearea.' to give a rating</div>';
    	// }
        // $enroll = true;
        // $disable = '';
        // $title = 'title="You need to enroll to the '.$ratearea.' to give a rating"';
        $participate_info = can_participate($itemid, $ratearea);

        $result = $participate_info['result'];
        $attribute = $participate_info['attribute'];
        $enroll = $participate_info['enroll'];
    }
    $result .= '</div>';
    $result .= html_writer::end_tag('div'); //End of .comment_commentarea
    $result .= html_writer::end_tag('div'); //End of .comment_$itemid
    return array('html' => $result, 'attribute' => $attribute , 'enroll' => $enroll);
}
function can_participate($itemid, $area){
    global $USER, $DB;
    $enroll =  true;
    $disable = '';
    $result = '';
    switch($area){
        case 'local_courses':
            $context = get_context_instance(CONTEXT_COURSE, $itemid);
            if(!is_enrolled($context, $USER->id)) {
                $enroll = false;
            }
        break;
        case 'local_classroom':
            if(!$DB->record_exists('local_classroom_users', array('classroomid' => $itemid, 'userid' => $USER->id))){
                $enroll = false;
            }
        break;
        case 'local_program':
            if(!$DB->record_exists('local_program_users', array('programid' => $itemid, 'userid' => $USER->id))){
                $enroll = false;
            }
        break;
        case 'local_learningplan':
            if(!$DB->record_exists('local_learningplan_user', array('planid' => $itemid, 'userid' => $USER->id))){
                $enroll = false;
            }
        break;
        case 'local_certification':
            if(!$DB->record_exists('local_certification_users', array('certificationid' => $itemid, 'userid' => $USER->id))){
                $enroll = false;
            }
        break;
    }
    if(!$enroll){
        $disable = 'disabled="disabled"';
        $result .= '<div>'.get_string('youneedtoenrol', 'local_ratings', $area).'</div>';
    }
    return array('result' => $result, 'attribute' => $disable , 'enroll' => $enroll);
}
/*
 * @function  get_rating
 * @todo caluclates rating
 * @return average rating as numeric value
*/
function get_rating($itemid, $ratearea) {
    global $CFG, $DB, $USER;
    $sql = "SELECT SUM(rating) AS sum, count(userid) AS count
    FROM {local_rating} WHERE ratearea LIKE '{$ratearea}' AND itemid = {$itemid} ";
	
    $avgrec = $DB->get_record_sql($sql);
    // $avgrec->avg = $avgrec->avg * 2;  // Double it for half star scores.
    // $avgrec->avg = $avgrec->avg * 2;  // Double it for half star scores.
    // //// Now round it up or down.
    if(!empty($avgrec) && $avgrec->count > 0){
        $avgrec->avg = round($avgrec->sum/$avgrec->count, 2);
    }else{
        $avgrec->avg = 0;
    }
    return $avgrec;
}
/*
 * @function  get_existing_rates
 * @function takes existing rated record
 * @return list of ratings given by users
*/
function get_existing_rates($record) { //For now we are not using this function
    global $DB, $USER, $CFG, $OUTPUT;
    $result = '';
    $result .= html_writer::start_tag('div', array('class' => 'comment_'.$record->itemid.'_'.$record->id, 'style'=>'padding: 5px;'));
    $user = $DB->get_record('user', array('id'=>$record->userid));

    $result .= html_writer::start_tag('div', array('class'=>'comment_commentarea'));
    $result .= '<img src="'.$CFG->wwwroot.'/local/ratings/pix/star'.($record->rating*2).'.png" />';
    $result .= html_writer::end_tag('div'); //End of .comment_commentarea
    $result .= html_writer::end_tag('div'); //End of .comment_$itemid_$existing_comment->id
    return $result;
}
function display_comment($itemid, $commentarea, $viewmore = true, $userid = null){
    global $DB, $USER, $PAGE;
    if(!get_config('local_ratings','review_enable')){
        return;
    }
    $PAGE->requires->js_call_amd('local_ratings/ratings', 'load');

    if(is_null($userid)){
        $userid = $USER->id;
    }
    $participate_info = can_participate($itemid, $commentarea);
    if($participate_info['enroll']){
        $usercomment = $DB->get_field('local_comment', 'comment', array('userid' => $userid, 'itemid' => $itemid, 'commentarea' => $commentarea));
        // $dialogdiv = html_writer::div('', '', array('id' => 'post_comment_'.$commentarea.'_'.$itemid));
        $displaycomment = strlen($usercomment) > 50 ? clean_text(substr($usercomment, 0, 50)).'...': $usercomment;
        $mycomment_content = "<div class='comment_information'>
                <span class='comment_label'>".get_string('my_review','local_ratings')."</span> 
                <span class='comment_colon'>:</span> 
                <span id = 'comment_value_{$commentarea}_{$itemid}' class='comment_value' title = '{$usercomment}'> {$displaycomment}
                <span>
            </div>";
        $return = html_writer::link('javascript:void(0)', get_string('writereview', 'local_ratings'), array('data-comment' => $usercomment, 'class' => 'mr-3', 'id' => 'post_comment_'.$commentarea.'_'.$itemid, 'onclick'=>'(function(e){
                                                            require("local_ratings/ratings").comment_item({userid: '.$USER->id.', itemid: ' . $itemid . ', commentarea: \'' . $commentarea . '\'}) })(event)'));
        
    }else{
        $mycomment_content = '';
        $return = $participate_info['return'];
    }
    if($viewmore){
        $return .= html_writer::link(new \moodle_url('/local/ratings/reviews.php',  array('itemid' => $itemid, 'commentarea' => $commentarea)), get_string('view_all_reviews','local_ratings'), array('class' => 'pull-right ml-1'));
    }
    return html_writer::div(/*$dialogdiv.*/$mycomment_content.$return, 'pull-left parentdiv');
}
/**
 code commented as comments are not used now.
 */
// /*
//  * @function  display_comment_area
//  * @function provides the option to give the comment
// */
// function display_comment_area($courseid, $activityid, $itemid, $commentarea, $page='') {
//     global $CFG,$USER,$DB;
//     $result = html_writer::start_tag('div', array('class' => 'mycomment'));    
//     $params = array('courseid'=>$courseid, 'activityid'=>$activityid, 'itemid'=>$itemid, 'commentarea'=>$commentarea);
//     $existing_comments = $DB->get_records('local_comment', $params, 'time DESC');
//     $count_comments = $DB->count_records('local_comment', $params);
//     if($page=="course_era")
//     $result .= html_writer::tag('span', 'Comments', array('id'=>'anchorclass_'.$itemid));
//     else 
//     $result .= html_writer::tag('a', 'Comments', array('id'=>'anchorclass_'.$itemid, "href" => "javascript:void(0)", "onClick"=>"fnViewAllComments($itemid)"));
//     if($commentarea!=='forum'){
// 	$result .= '&nbsp;(<font class="commentcount_'.$itemid.'">'.$count_comments.'</font>)';
//     }    
//     // used to make comment area visible by default   
//     if($page=="course_era")
//     $result .= html_writer::start_tag('div', array('class' => 'coursecomment', 'id' => 'comment_list_'.$itemid,'style'=>'width:95% !important; position:relative'));
//     else
//     $result .= html_writer::start_tag('div', array('class' => 'coursecomment', 'id' => 'comment_list_'.$itemid, 'style'=>'display: none;'));
    
//     $closeIcon = html_writer::empty_tag('img', array('src'=>$CFG->wwwroot.'/local/ratings/pix/icon_close_popup.gif', 'title'=>'Close'));
//     if($page!=="course_era")
//     $result .= html_writer::tag('div', $closeIcon, array('style'=>'float: right; cursor: pointer;margin-top: -2%;', 'class'=>'closeicon'.$itemid));
//     if(isloggedin() && !isguestuser() ){
// 	$enroll = true;
// 	if($courseid!==SITEID){
// 	    $context = get_context_instance(CONTEXT_COURSE, $courseid);
// 	    if(!is_enrolled($context, $USER->id) && !is_siteadmin() && !has_capability('local/feedback:view', $context)) {
// 		$enroll = false;
// 	    }
// 	}
// 	if(!$enroll){
// 	    $result .= '<div>You need to enroll to the '.$commentarea.' to comment.</div>';
// 	}else{
// 	    $result .= html_writer::start_tag('textarea', array('name'=>'commentarea', 'id'=>'mycomment_'.$itemid, 'rows'=>'2', 'cols'=>'50'));
// 	    $result .= html_writer::end_tag('textarea');
// 	    $button = html_writer::empty_tag('img', array('src'=>$CFG->wwwroot.'/local/ratings/pix/acm-submit2165.gif'));
// 	    //$button = html_writer::tag('button', 'Comment', array());
// 	    $result .= html_writer::tag('a', '&nbsp;'.$button, array('class'=>'commentclick_'.$itemid, "href" => "javascript:void(0)", "onClick"=>"fnComment($courseid, $activityid, $itemid, \"$commentarea\", \"$CFG->wwwroot\")", 'style'=>'font-size: 12px;'));
// 	}
//     }
//     $result .= html_writer::start_tag('div', array('class' => 'comment_'.$itemid));
//     $result .= html_writer::end_tag('div'); // End of .comment_$itemid
//     $i=1;
//     foreach($existing_comments as $existing_comment){
// 	if($i > 3){
// 	    $result .= html_writer::start_tag('div', array('class' => 'viewallcomments'.$itemid, 'style'=>'display: none;'));
// 	}
// 	$result .= get_existing_comments($courseid, $itemid, $existing_comment);
// 	if($i > 3){
// 	    $result .= html_writer::end_tag('div'); // End of .comment_$itemid
// 	}
// 	$i++;
//     }
//     if($count_comments > 3){
// 	//  only applied to storywall plugin
// 	if($page=="course_era")
//             $result .= html_writer::tag('a', 'View All', array("href" => "javascript:void(0)", 'class'=>'viewall'.$itemid , 'style'=>'font-size: 12px;',  "onClick"=>"fnstorywallComments($itemid)" ));
// 	else if($commentarea=='course')
//             $result .= html_writer::tag('a', 'View All', array("href" => $CFG->wwwroot.'/local/course_era.php?id='.$courseid, 'style'=>'font-size: 12px;'));
//         else
//             $result .= html_writer::tag('a', 'View All', array("href" => "javascript:void(0)", 'style'=>'font-size: 12px;','class'=>'viewall'.$itemid));

//     }
//     $result .= html_writer::end_tag('div'); // End of .comment_list
//     $result .= html_writer::end_tag('div'); // End of .mycomment
//     return $result;
// }

// /*
//  * @function  get_existing_comments
//  * @return list of comments given by users
// */
// function get_existing_comments($courseid, $itemid, $existing_comment) {
//     global $DB, $USER, $CFG, $OUTPUT;
//     $result = '';
//     $result .= html_writer::start_tag('div', array('class' => 'comment_'.$itemid.'_'.$existing_comment->id, 'style'=>'padding: 5px; margin-top: 10px;'));
//     $result .= html_writer::start_tag('div', array('class'=>'comment_picture', 'style'=>'padding: 5px;'));
//     $user = $DB->get_record('user', array('id'=>$existing_comment->userid));
//     $result .= $OUTPUT->user_picture($user, array('courseid' => $courseid, 'size'=>32));
//     $result .= html_writer::end_tag('div'); //End of .comment_picture
    
//     $result .= html_writer::start_tag('div', array('class'=>'comment_time'));
//     $result .= ' <a href="'.$CFG->wwwroot.'/user/profile.php?id='.$user->id.'&courseid='.$courseid.'">' . fullname($user) .'</a> - '.userdate($existing_comment->time);
//     if($USER->id==$existing_comment->userid){
// 	$deleteIcon = html_writer::empty_tag('img', array('src'=>$CFG->wwwroot.'/pix/t/delete.png', 'style'=>'margin-left: 20px;'));
// 	$result .= html_writer::tag('a', $deleteIcon, array("href" => "javascript:void(0)", "onclick"=>"DeleteComment($existing_comment->id, $courseid, $existing_comment->activityid, $itemid, \"$existing_comment->commentarea\", \"$CFG->wwwroot\")"));
//     }
//     $result .= html_writer::end_tag('div'); //End of .comment_time
    
//     $result .= html_writer::start_tag('div', array('class'=>'comment_commentarea', 'style'=>'width: 98%;'));
//     $result .= $existing_comment->comment;
//     $result .= html_writer::end_tag('div'); //End of .comment_commentarea
//     $result .= html_writer::end_tag('div'); //End of .comment_$itemid_$existing_comment->id
//     return $result;
// }
