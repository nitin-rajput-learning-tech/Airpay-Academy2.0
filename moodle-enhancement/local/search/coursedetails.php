<?php
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG,$USER, $DB, $PAGE, $OUTPUT;

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('local_classroom/classroom', 'load');
$PAGE->requires->js_call_amd('local_search/courseinfo', 'load');
$PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');
require_once $CFG->libdir.'/gradelib.php';
// local/search/lib.php — only load if BizLMS search plugin exists.
$searchlib = $CFG->dirroot . '/local/search/lib.php';
if (file_exists($searchlib)) { require_once($searchlib); }
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->dirroot.'/grade/report/user/lib.php';
// local/includes.php — BizLMS bootstrap, load only if present.
$includeslib = $CFG->dirroot . '/local/includes.php';
if (file_exists($includeslib)) { require_once($includeslib); }

$id  = required_param('id', PARAM_INT); // Course id

$coursecontext = context_course::instance($id);
$PAGE->set_context($coursecontext);
$PAGE->set_url('/local/search/coursedetails.php', array('id' =>$id));
$PAGE->requires->event_handler('#usernotcompleted_sessionprereq', 'click', 'M.util.show_confirm_dialog', array('message' => get_string('usernotcompleted_prereq', 'local_search'), 'callbacks' => array()));
local_search_include_search_js();
$course = get_course($id);
if($USER->open_costcenterid != $course->open_costcenterid){
	redirect($CFG->wwwroot.'/local/airpay_catalog/index.php');
}
if(!$course){
	throw new moodle_exception('invalidcourseid');
}

$PAGE->set_title($course->fullname);
$userrolecontext = \local_airpay_org\accesslib::get_module_context();
$catalogurl = new moodle_url('/local/search/allcourses.php', array());
if(!is_siteadmin() && (empty(\local_airpay_org\accesslib::get_user_role_switch_path()) || in_array(0, \local_airpay_org\accesslib::get_user_role_switch_path(), true))){
	$switchedrole = false;
	$PAGE->navbar->add(get_string('pluginname','local_search'), $catalogurl);
}else{
	$switchedrole = true;
	if(has_capability('local/courses:manage', $userrolecontext) || is_siteadmin()){
		$managecourseurl = new moodle_url('/local/courses/courses.php');
	}else{
		$managecourseurl = new moodle_url('/my/dashboard.php');
	}
	$PAGE->navbar->add(get_string('manage_courses','local_courses'), $managecourseurl);

}
$employeerole = $DB->get_field('role', 'id', array('shortname' => 'employee'));
$params = array('courseid'=>$course->id, 'employeerole' => $employeerole);
$enrolledusersssql = " SELECT COUNT(u.id) as ccount
                                FROM {course} c
                                JOIN {context} AS cot ON cot.instanceid = c.id AND cot.contextlevel = 50
                                JOIN {role_assignments} as ra ON ra.contextid = cot.id
                                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1
                                                AND u.deleted = 0 AND u.suspended = 0
                                WHERE c.id = :courseid AND ra.roleid = :employeerole";
$enrolled_count =  $DB->count_records_sql($enrolledusersssql, $params);
$costcenterpathconcatsql = \local_airpay_org\accesslib::get_costcenter_path_field_concatsql($columnname='path',$costcenterpath=null,$datatype='lowerandsamepath');

$completedusersssql = " SELECT COUNT(u.id) as ccount
                                FROM {course} c
                                JOIN {context} AS cot ON cot.instanceid = c.id AND cot.contextlevel = 50
                                JOIN {role_assignments} as ra ON ra.contextid = cot.id
                                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1
                                                AND u.deleted = 0 AND u.suspended = 0
                                JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid
                                WHERE c.id = :courseid AND ra.roleid = :employeerole AND cc.timecompleted IS NOT NULL $costcenterpathconcatsql";

$completed_count = $DB->count_records_sql($completedusersssql,$params);
$PAGE->navbar->add($course->fullname);

// ═══ MODERN COURSE DETAIL VIEW (Airpay LXP) ═══
// Renders the modern Mustache template if the airpayux theme is active.
// Falls through to legacy BizLMS rendering below if not.
if ($PAGE->theme->name === 'airpayux') {
    // Build context for the modern template.
    $catinfo = \local_airpay_catalog\category_manager::get_with_parent((int)($course->open_categoryid ?? 0));
    $mod_categoryname = !empty($catinfo->full_path) ? format_string($catinfo->full_path) : 'Uncategorized';

    $mod_level = $DB->get_field('local_course_levels', 'name', ['id' => $course->open_level]);
    $mod_skill = $DB->get_field('local_skill', 'name', ['id' => $course->open_skill]);

    $includes_mod = new user_course_details();
    $mod_imageurl = $includes_mod->course_summary_files($course);

    // Check enrollment status.
    $mod_enrolled = $DB->record_exists('role_assignments', [
        'contextid' => $coursecontext->id, 'userid' => $USER->id, 'roleid' => 5]);

    // Get course progress if enrolled.
    $mod_progress = 0;
    if ($mod_enrolled) {
        $p = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
        $mod_progress = $p !== null ? round($p) : 0;
    }

    // Check self-enrollment availability.
    $mod_can_selfenrol = false;
    $enrolinstances = enrol_get_instances($course->id, true);
    foreach ($enrolinstances as $instance) {
        if ($instance->enrol === 'self') { $mod_can_selfenrol = true; break; }
    }

    // Build module list from course sections with completion status.
    $mod_modules = [];
    $mod_total_activities = 0;
    $modinfo = get_fast_modinfo($course);
    $completioninfo = $mod_enrolled ? new \completion_info($course) : null;
    foreach ($modinfo->get_section_info_all() as $section) {
        if ($section->section == 0) continue;
        $activities = [];
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];
                if (!$mod->visible) continue;
                $iconmap = ['scorm' => 'fa-play-circle', 'quiz' => 'fa-question-circle',
                    'assign' => 'fa-pencil-square-o', 'forum' => 'fa-comments',
                    'page' => 'fa-file-text', 'url' => 'fa-external-link',
                    'resource' => 'fa-file-o', 'label' => 'fa-tag'];
                // Get completion state for enrolled users.
                $status = 'not_started';
                if ($mod_enrolled && $completioninfo) {
                    $cmdata = $completioninfo->get_data($mod, false, $USER->id);
                    if ($cmdata->completionstate == COMPLETION_COMPLETE ||
                        $cmdata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $status = 'completed';
                    } elseif ($cmdata->completionstate == COMPLETION_INCOMPLETE) {
                        $status = 'in_progress';
                    }
                }
                $activities[] = [
                    'name'         => format_string($mod->name),
                    'icon'         => $iconmap[$mod->modname] ?? 'fa-circle-o',
                    'is_complete'  => ($status === 'completed'),
                    'is_progress'  => ($status === 'in_progress'),
                    'is_locked'    => (!$mod_enrolled),
                ];
                $mod_total_activities++;
            }
        }
        if (!empty($activities)) {
            $sectionname = get_section_name($course, $section);
            $mod_modules[] = [
                'name' => $sectionname ?: 'Section ' . $section->section,
                'activity_count' => count($activities),
                'activities' => $activities,
            ];
        }
    }

    // Completion rate as social proof.
    $mod_completion_rate = ($enrolled_count > 0)
        ? round(($completed_count / $enrolled_count) * 100) : 0;

    // Related courses (same category, limit 4, exclude current).
    $mod_related = [];
    if (!empty($course->open_categoryid)) {
        $related_sql = "SELECT c.id, c.fullname, c.shortname
                          FROM {course} c
                         WHERE c.open_categoryid = :catid AND c.id != :thisid
                           AND c.visible = 1 AND c.id > 1
                      ORDER BY c.timecreated DESC";
        $related_recs = $DB->get_records_sql($related_sql,
            ['catid' => $course->open_categoryid, 'thisid' => $course->id], 0, 4);
        foreach ($related_recs as $rc) {
            $mod_related[] = [
                'fullname'  => format_string($rc->fullname),
                'shortname' => format_string($rc->shortname),
                'detailurl' => (new moodle_url('/local/search/coursedetails.php', ['id' => $rc->id]))->out(false),
            ];
        }
    }

    // Share URLs.
    $mod_shareurl = (new moodle_url('/local/search/coursedetails.php', ['id' => $course->id]))->out(false);
    $mod_sharetext = urlencode($course->fullname . ' - Airpay Academy: ' . $mod_shareurl);

    $mod_context = [
        'coursename'      => format_string($course->fullname),
        'description'     => format_text($course->summary, FORMAT_HTML),
        'imageurl'        => $mod_imageurl ?: '',
        'categoryname'    => $mod_categoryname,
        'categoryid'      => $course->open_categoryid ?? 0,
        'catalogurl'      => (new moodle_url('/local/airpay_catalog/index.php'))->out(false),
        'level'           => $mod_level ?: '',
        'has_level'       => !empty($mod_level),
        'skill'           => $mod_skill ?: '',
        'has_skill'       => !empty($mod_skill),
        'type'            => $course->format === 'singleactivity' ? 'SCORM' : 'Multi-module',
        'enrolled_count'  => $enrolled_count,
        'completed_count' => $completed_count,
        'is_enrolled'     => $mod_enrolled,
        'has_progress'    => ($mod_progress > 0),
        'progress'        => $mod_progress,
        'can_selfenrol'   => $mod_can_selfenrol,
        'viewurl'         => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'enrolurl'        => (new moodle_url('/enrol/index.php', ['id' => $course->id]))->out(false),
        'modules'          => $mod_modules,
        'has_modules'      => !empty($mod_modules),
        'total_activities' => $mod_total_activities,
        'total_sections'   => count($mod_modules),
        'completion_rate'  => $mod_completion_rate,
        'has_certificate'  => $DB->get_manager()->table_exists('customcert') && $DB->record_exists('customcert', ['course' => $course->id]),
        'related_courses'  => $mod_related,
        'has_related'      => !empty($mod_related),
        'shareurl'         => $mod_shareurl,
        'sharetext'        => $mod_sharetext,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_search/coursedetail_modern', $mod_context);
    echo $OUTPUT->footer();
    die();
}

// ═══ LEGACY BIZLMS RENDERING (unchanged below) ═══
echo $OUTPUT->header();
echo '<div class="content_era_left">';

	$legacy_catinfo = \local_airpay_catalog\category_manager::get_with_parent((int)($course->open_categoryid ?? 0));
	$categoryname = !empty($legacy_catinfo->full_path) ? $legacy_catinfo->full_path : 'N/A';
	$open_level = $DB->get_field('local_course_levels', 'name', array('id' => $course->open_level));
	$level = $open_level ? $open_level : 'NA';
	$open_skill = $DB->get_field('local_skill', 'name', array('id' => $course->open_skill));
	$skill = $open_skill ? $open_skill : 'NA';

	// if(is_null($course->open_grade) || $course->open_grade == '' || $course->open_grade == -1){
	// 	$course_grade = get_string('all');
	// }else{
	// 	$course_grade = $course->open_grade;
	// }
	$Courseullnfame = $course->fullname;
  	$includes = new user_course_details();
	$courseurl = $includes->course_summary_files($course);
	$managecoursecap = has_capability('local/courses:manage', $coursecontext);
	echo '<div class=" coursedet_left">
		<div class="cousedet_topcontent">
		
        <div class="img_summary row m-0" style="background-image:url('.$courseurl.')" alt="img" />
    	
    	<div class="col-md-8 CourseDetails_content d-flex flex-column justify-content-end"> 
            <h3 class="course_title">'.$Courseullnfame.'</h3>

         <div class="row mt-4 pb-2">';
		 if($managecoursecap)
			{
             echo 	'<div class="col-md-3 user_completion d-flex">
                        <div class="user_icon mr-2"></div>
                        <div class="completion_details d-flex">
                            <span class="details_content text-nowrap">Users Completion :</span>
                            <span class="enroll_number">'.$completed_count.'</span>
                        </div>
                    </div>
                    <div class="user_enrollment col-md-3 d-flex">
                        <div class="enroll_icon mr-2"></div>
                        <div class="enroll_details d-flex">
                            <span class="details_content text-nowrap">Enrollments :</span>
                            <span class="enroll_number">'.$enrolled_count.'</span>
                        </div>
                    </div>
					<div class=" skill_level_details col-md-3 d-flex">
                        <div class="skill_icon mr-2"></div>
                        <div class="skill_details d-flex">
                            <span class="details_content text-nowrap">Skill :</span>
                            <span class="skill_level">'.$skill.'</span>
                        </div>
                    </div>
					<div class=" skill_level_details col-md-3 d-flex">
						<div class="skill_icon mr-2"></div>
						<div class="skill_details d-flex">
							<span class="details_content text-nowrap">Skill Level :</span>
							<span class="skill_level">'.$open_level.'</span>
					</div>
					</div>
                    
					
                    </div>';
				}
            echo '<div class="pull-right">';
			
   

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
        //   $renderer = $PAGE->get_renderer('local_search');
        //  echo '
		// 		<div id="coursedetails" class="mt-1">
		//             <ul>
		//               <li><a href="#courseindex">Index</a></li>

		//             </ul>
		//             <div id="courseindex">'.$renderer->course_sections($course->id).'</div>

		//         </div>
		// 		';
	echo $course->summary;
    echo html_writer::script('$("#coursedetails").tabs();');   

        echo '</div>
        <div class="col-md-3 coursedet_right">
        <div class="CourseDetils_container">
    	
    	<div class="Course_content p-0">';

    	$managecoursecap = has_capability('local/courses:manage', $coursecontext);
    	if (!isloggedin()) {
    		echo '<div class="start_course p-2">
	    		<a href="'.$CFG->wwwroot.'/login/index.php">
	                <button type="button" class="crs_content btn btn-lg btn-primary w-full ng-binding">
	                   Login
	                </button>
	            </a>
    		</div>';
    	} else if($enrolled || is_siteadmin() || $managecoursecap){
        	echo '<div class="start_course p-2">
		    		<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">
		                <button type="button" class="crs_content btn btn-lg btn-primary w-full ng-binding">
		                   Start Now
		                </button>
		            </a>
        		</div>';
        	//echo '<div class="view_gradeslink px-2"><a class="view_links btn btn-block" href="'.$CFG->wwwroot.'/grade/report/user/index.php?id='.$course->id.'">View Grades</a></div>';
        }else{
        	// $enrol = $DB->get_record('enrol', array('courseid'=>$id, 'enrol'=>'self'));
        	$coursesearchlib = new \local_courses\output\search();
        	if(!$switchedrole){
        		$enroll = is_enrolled($coursecontext, $USER->id, '', true);
        		echo $coursesearchlib->get_enrollbutton($enroll,$course);
        		if($course->price_status == 1){
					echo $coursesearchlib->get_add_to_cart_button($enroll,$course);
        		}
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
		$certificate=$course->open_certificateid ? 'YES' :'NO';
		$coursetype=$DB->get_field('local_course_types', 'name', array('id'=>$course->open_identifiedas));
		$coursetype = $coursetype ? $coursetype : 'NA';
     	echo '<div class="coursebrieflist col-12 p-0 mt-2">';
        $credits = !empty($course->open_points) ? $course->open_points : "NA";
        echo '<div class="crs_detail_head">
        <p>Course Information</p>
        </div>';
    	  echo'<ul class="crse_details">';
	    	   	if($course->price_status == 1 && $course->courseprice > 0 ){
	    	   		echo'<li class="my-1 incentives__text d-flex align-items-center">
						<div class="category_type d-flex align-items-center">
							<span class="tag_icon coursedetailsicon"><i class="fa fa-tags faicon" aria-hidden="true"></i></span>
							<span>'.get_string('price', 'local_courses').'</span>
						</div>
						<b class="iteminfo ml-2">'.$course->courseprice.'</b>
					</li>';
	    	   	}
			echo'<li class="my-1 incentives__text d-flex align-items-center">
					<div class="category_type d-flex align-items-center">
						<span class="career_icon"></span>
						<span>'.get_string('category', 'local_courses').'</span>
					</div>
					<b class="iteminfo ml-2">'.$categoryname.'</b>
				</li>
				<li class="my-1 incentives__text d-flex align-items-center">
					<div class="category_type d-flex align-items-center">
						<span class="category_icon"></span>
						<span>'.get_string('type', 'local_courses').'</span>
					</div>  
					<b class="iteminfo ml-2">'.$coursetype.'</b>
				</li>
				<li class="my-1 incentives__text d-flex align-items-center">
					<div class="category_type d-flex align-items-center">
						<span class="level_icon"></span>
						<span>'.get_string('coursecompday_atsearch', 'local_search').'</span>
					</div>
					<b class="iteminfo ml-2">'.$course->open_coursecompletiondays.'</b>
				</li>
				<li class="my-1 incentives__text d-flex align-items-center">
					<div class="category_type d-flex align-items-center">
						<span class="grade_icon"></span>
						<span>'.get_string('certificate', 'local_courses').'</span>
					</div>
					
						<b class="iteminfo ml-2">'.$certificate.'</b>
				</li>
				</ul>
	            </div>
            	</div>
            </div>
        </div>
      </div>';
	echo '</div>';
	
    

echo $OUTPUT->footer();
