<?php
define('AJAX_SCRIPT',true);
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG, $DB, $USER;
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->dirroot.'/local/courses/lib.php');
// require_once($CFG->dirroot.'/local/courses/notifications_emails.php');
// use \local_courses\notificationemails as coursenotifications_emails;

$systemcontext = \local_costcenter\lib\accesslib::get_module_context();
$PAGE->set_context($systemcontext);
require_login();

$id  = required_param('id', PARAM_INT); // Course id
$enroll = optional_param('enrol','', PARAM_INT);
$course = $DB->get_record('course', array('id' => $id));
if($enroll){
$manual = enrol_get_plugin('self');
$type = 'course_enrol';
$dataobj = $id;
$fromuserid = 2;
    $sql = "select * from {enrol} where courseid=".$id." and enrol='self'";
	$instance = $DB->get_record_sql($sql);
    //$studentrole = $DB->get_record('role', array('shortname'=>'student'));
	if($instance){
       $test =  $manual->enrol_user($instance,$USER->id,$instance->roleid);
      //  $emaillogs = new coursenotifications_emails();
	     // $email_logs = $emaillogs->course_emaillogs($type,$dataobj,$USER->id,$fromuserid);
       $emaillogs = new \local_courses\notification();
       $notificationdata = $emaillogs->get_existing_notification($course, $type);
    if($notificationdata){
        $emaillogs->send_course_email($course, $USER, $type, $notificationdata);
    }
  }
  if(empty($test)){
  	$start_btn = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$id.'" class=""><button class="cat_btn viewmore_btn">'.get_string('start_now','local_search').'</button></a>';
      echo json_encode($start_btn);
  }
}
else{
	$renderer = $PAGE->get_renderer('local_search');
	echo $renderer->get_course_info($id);
}