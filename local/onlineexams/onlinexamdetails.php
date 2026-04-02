<?php
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG,$USER, $DB, $PAGE, $OUTPUT;

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('local_classroom/classroom', 'load');
$PAGE->requires->js_call_amd('local_search/courseinfo', 'load');

require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->dirroot.'/local/search/lib.php';
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->dirroot.'/grade/report/user/lib.php';
require_once($CFG->dirroot.'/local/includes.php');

$id  = required_param('id', PARAM_INT); // Course id

$coursecontext = context_course::instance($id);
$PAGE->set_context($coursecontext);
$PAGE->set_url('/local/search/coursedetails.php', array('id' =>$id));
require_login();
// $PAGE->set_pagelayout('course');
$PAGE->requires->event_handler('#usernotcompleted_sessionprereq', 'click', 'M.util.show_confirm_dialog', array('message' => get_string('usernotcompleted_prereq', 'local_catalog'), 'callbacks' => array()));
local_search_include_search_js();
$course = $DB->get_record('course', array('id'=>$id));
if(!$course){
	throw new moodle_exception('invalidcourseid');
}

$PAGE->set_title($course->fullname);
//$PAGE->set_heading($course->fullname);
$userrolecontext = local_costcenter\lib\accesslib::get_module_context();
	$switchedrole = true;
	if(has_capability('local/onlineexams:manage', $userrolecontext) || is_siteadmin()){
		$managecourseurl = new moodle_url('/local/onlineexams/index.php');
	}else{
		$managecourseurl = new moodle_url('/my/dashboard.php');
	}
	$PAGE->navbar->add(get_string('manage_onlineexams','local_onlineexams'), $managecourseurl);



$PAGE->navbar->add($course->fullname);
echo $OUTPUT->header();
echo '<div class="content_era_left">';

	$course_category = $DB->get_field('local_custom_category', 'fullname', array('id'=>$course->open_categoryid));
	$course_category = $course_category ? $course_category : 'NA';
	// $open_level = $DB->get_field('local_course_levels', 'name', array('id' => $course->open_level));
	// $level = $open_level ? $open_level : 'NA';
	// $open_skill = $DB->get_field('local_skill', 'name', array('id' => $course->open_skill));
	// $skill = $open_skill ? $open_skill : 'NA';

	// if(is_null($course->open_grade) || $course->open_grade == '' || $course->open_grade == -1){
	// 	$course_grade = get_string('all');
	// }else{
	// 	$course_grade = $course->open_grade;
	// }
	$Courseullnfame = $course->fullname;
  	$includes = new user_course_details();
	$courseurl = $includes->course_summary_files($course);

	echo '<div class=" coursedet_left">
		<div class="cousedet_topcontent">
		
        <div class="img_summary row m-0" style="background-image:url('.$courseurl.')" alt="img" />
    	
    	<div class="col-md-8 CourseDetails_content d-flex flex-column justify-content-end"> 
            <h3 class="course_title">'.$Courseullnfame.'</h3>

         <div class="row mt-4 pb-2">
              	<div class="col-md-4 user_completion d-flex">
                        <div class="user_icon mr-2"></div>
                        <div class="completion_details d-flex">
                            <span class="details_content text-nowrap">Users Completion :</span>
                            <span class="enroll_number"></span>
                        </div>
                    </div>
                    <div class="user_enrollment col-md-4 d-flex">
                        <div class="enroll_icon mr-2"></div>
                        <div class="enroll_details d-flex">
                            <span class="details_content text-nowrap">Enrollments :</span>
                            <span class="enroll_number"></span>
                        </div>
                    </div>
                    <div class=" skill_level_details col-md-4 d-flex">
                        <div class="skill_icon mr-2"></div>
                        <div class="skill_details d-flex">
                            <span class="details_content text-nowrap">Skill Level :</span>
                            <span class="skill_level"></span>
                        </div>
                    </div>
                    </div>
                            
            <div class="pull-right">';

   

    if(isloggedin()){
		$role = $DB->get_record('role_assignments', array('contextid'=>$coursecontext->id, 'userid'=>$USER->id));
		$is_teacher = $is_student = false;
		if($role){
			if($role->roleid==5){
				$is_student = true;
			} else if($role->roleid==3 || $role->roleid==4){
				$is_teacher = true;
			}
		}
	}
	
	$course_options = array();
	$enrolled = $DB->get_records('role_assignments', array('contextid'=>$coursecontext->id, 'userid'=>$USER->id));
	$enrolcount = $DB->count_records('role_assignments', array('contextid'=>$coursecontext->id, 'roleid' => 5)); 
		       
    $share = '<span class="addthis_toolbox addthis_default_style "
                                addthis:url="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'" >
                                <a class="addthis_button_facebook" addthis:title="'.$course->fullname.'"></a>
                                <a class="addthis_button_twitter" addthis:title="'.$course->fullname.'"></a>
                                <a class="addthis_button_linkedin" addthis:title="'.$course->fullname.'"></a>
			    <a class="addthis_button_compact" addthis:title="'.$course->fullname.'"></a>
              </span>';
	$course_options[] = $share;

	echo html_writer::tag('div', implode(' | ', $course_options), array('class'=>'course_options'));

      	echo '</div>
        </div>
        </div>
        </div>
        <div class="row coursedet_row mt-3">
        <div class="col-md-9 course_desc">
	        <div class="desc_head"><h5>Description</h5></div>
	        <div class="course_description">
               <p></p>
            </div>';
            $renderer = $PAGE->get_renderer('local_search');
         echo '
				<div id="coursedetails" class="mt-1">
		            <ul>
		              <li><a href="#courseindex">Index</a></li>

		            </ul>
		            <div id="courseindex">'.$renderer->course_sections($course->id).'</div>

		        </div>
				';
//<li><a href="#courseilts">ILT Session</a></li><div id="courseilts">'.' $renderer->course_batchesinfo($course->id)'.'</div>
    echo html_writer::script('$("#coursedetails").tabs();');   

        echo '</div>
        <div class="col-md-3 coursedet_right">
        <div class="CourseDetils_container">
    	
    	<div class="Course_content p-0">';

    	$managecoursecap = has_capability('local/onlineexams:manage', $coursecontext);
        if($enrolled || is_siteadmin() || $managecoursecap){
        	echo '<div class="start_course p-2">
		    		<a href="'.$CFG->wwwroot.'/mod/quiz/view.php?id='.$course->id.'">
		                <button type="button" class="crs_content btn btn-lg btn-primary w-full ng-binding">
		                   Start Now
		                </button>
		            </a>
        		</div>';
        	echo '<div class="view_gradeslink px-2"><a class="view_links btn btn-block" href="'.$CFG->wwwroot.'/grade/report/user/index.php?id='.$course->id.'">View Grades</a></div>';
        }

     echo '<div class="coursebrieflist col-12 p-0 mt-2">';
        $credits = !empty($course->open_points) ? $course->open_points : "NA";
        echo '<div class="crs_detail_head">
        <p>Category</p>
        </div>';
    	  echo'<ul class="crse_details">
				<li class="my-1 incentives__text d-flex align-items-center">
					<div class="category_type d-flex align-items-center">
						<span class="career_icon"></span>
						<span>Career Track</span>
					</div>
					<b class="iteminfo ml-2">'.$course_category.'</b>
				</li>
				<li class="my-1 incentives__text d-flex align-items-center">
					<div class="category_type d-flex align-items-center">
						<span class="category_icon"></span>
						<span>'.get_string('category', 'local_courses').'</span>
					</div>  
					<b class="iteminfo ml-2">'.$course_category.'</b>
				</li>
				<li class="my-1 incentives__text d-flex align-items-center">
					<div class="category_type d-flex align-items-center">
						<span class="level_icon"></span>
						<span>Level</span>
					</div>
					<b class="iteminfo ml-2">'.$course_category.'</b>
				</li>
				<li class="my-1 incentives__text d-flex align-items-center">
					<div class="category_type d-flex align-items-center">
						<span class="grade_icon"></span>
						<span>Grade</span>
					</div>
						<b class="iteminfo ml-2">'.$course_category.'</b>
				</li>
				<li class="my-1 incentives__text d-flex align-items-center">
					<div class="category_type d-flex align-items-center">
						<span class="cp_icon"></span>
						<span>Course Provider</span>
					</div>
					<b class="iteminfo ml-2">'.$course_category.'</b>
				</li>
				</ul>
	            </div>
            	</div>
            </div>
        </div>
      </div>';
	echo '</div>';
	
    

echo $OUTPUT->footer();
