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

$coursecontext = get_context_instance(CONTEXT_COURSE, $id);
$PAGE->set_context($coursecontext);
$PAGE->set_url('/local/search/coursedetails.php', array('id' =>$id));
require_login();
$PAGE->set_pagelayout('course');
$PAGE->requires->event_handler('#usernotcompleted_sessionprereq', 'click', 'M.util.show_confirm_dialog', array('message' => get_string('usernotcompleted_prereq', 'local_catalog'), 'callbacks' => array()));
local_search_include_search_js();
$course = $DB->get_record('course', array('id'=>$id));
if(!$course){
	print_error('invalidcourseid');
}

$PAGE->set_title($course->fullname);
//$PAGE->set_heading($course->fullname);
$userrolecontext = local_costcenter\lib\accesslib::get_module_context();
$catalogurl = new moodle_url('/local/search/allcourses.php', array());
if(!is_siteadmin() && array_search(0, local_costcenter\lib\accesslib::get_user_role_switch_path(), true)){
	$switchedrole = false;
	$PAGE->navbar->add(get_string('e_learning_courses','local_search'), $catalogurl);
}else{
	$switchedrole = true;
	if(has_capability('local/courses:manage', $userrolecontext) || is_siteadmin()){
		$managecourseurl = new moodle_url('/local/courses/courses.php');
	}else{
		$managecourseurl = new moodle_url('/my/dashboard.php');
	}
	$PAGE->navbar->add(get_string('manage_courses','local_courses'), $managecourseurl);

}

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

	echo '<div class="row  coursedet_row mb-4">
        <div class="col-md-9 coursedet_left">
            <h1 class="course_title">'.$Courseullnfame.'</h1>
            <div class="course_description">
               <p></p>
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
        <div class="col-md-3 coursedet_right">
        <div class="CourseDetils_container">
    	<div class="CourseDetils_content">
        <img class="img_summary img-responsive" src="'.$courseurl.'" alt="img" />
    	</div>
    	<div class="Course_content my-3">';

    	$managecoursecap = has_capability('local/courses:manage', $coursecontext);
        if($enrolled || is_siteadmin() || $managecoursecap){
        	echo '<div class="start_course mb-2">
		    		<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">
		                <button type="button" class="crs_content btn btn-lg btn-primary w-full ng-binding mb-2">
		                   Start Now
		                </button>
		            </a>
        		</div>';
        	echo '<div class="view_gradeslink"><a class="view_links btn btn-block mb-2" href="'.$CFG->wwwroot.'/grade/report/user/index.php?id='.$course->id.'">View Grades</a></div>';
        }else{
        	// $enrol = $DB->get_record('enrol', array('courseid'=>$id, 'enrol'=>'self'));
        	$coursesearchlib = new \local_courses\output\search();
        	if(!$switchedrole){
        		echo $coursesearchlib->get_enrollbutton(false,$course);
        	}
	  	  	   // echo '<div class="content_era_right">
			// 	<div class="enrol">
			// 		<form action="'.$CFG->wwwroot.'/enrol/index.php" method="post" id="mform1" class="mform" accept-charset="utf-8" autocomplete="off">
	        // 		<input type="hidden" value="'.$id.'" name="id">
	           //      <input name="instance" value="'.$enrol->id.'" type="hidden">
	           //      <input name="sesskey" value="'.sesskey().'" type="hidden">
	           //      <input name="_qf__'.$enrol->id.'_enrol_self_enrol_form" value="1" type="hidden">
	           //      <input name="mform_isexpanded_id_selfheader" value="1" type="hidden">
	           //      <input type="submit" id="id_submitbutton" class="crs_content btn btn-lg btn-primary w-full ng-binding mb-2" value="Enrol" name="submitbutton">
	           //      </form>
	           // 	</div>
	           // 	</div>';

		} 

     echo '<div class="coursebrieflist col-12 p-0 mt-2">';
        $credits = !empty($course->open_points) ? $course->open_points : "NA";
    	  echo'<ul class="crse_details">
				<li class="my-1 incentives__text">'.get_string('category', 'local_courses').': <b class="iteminfo">'.$course_category.'</b>
				</li>
				<li class="my-1 incentives__text">'.get_string('skill', 'local_courses').': <b class="iteminfo">'.$skill.'</b></li>
				<li class="my-1 incentives__text">'.get_string('open_levelcourse', 'local_courses').': <b class="iteminfo">'.$level.'</b></li>
				<li class="my-1 incentives__text">'.get_string('open_pointscourse', 'local_courses').': <b class="iteminfo">'.$credits.'</b>
				</li>
				</ul>
	            </div>
            	</div>
            </div>
        </div>
      </div>';
	echo '</div>';
	$renderer = $PAGE->get_renderer('local_search');
    echo '<div class="row">
				<div class="col-md-9 pr-0">
				<div id="coursedetails">
		            <ul>
		              <li><a href="#courseindex">Index</a></li>

		            </ul>
		            <div id="courseindex">'.$renderer->course_sections($course->id).'</div>

		        </div>
				</div>
				<div class="col-md-3"></div>
		  </div>';
//<li><a href="#courseilts">ILT Session</a></li><div id="courseilts">'.' $renderer->course_batchesinfo($course->id)'.'</div>
    echo html_writer::script('$("#coursedetails").tabs();');

echo $OUTPUT->footer();
