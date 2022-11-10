<?php
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/local/ratings/lib.php');
global $USER,$DB,$CFG;

$courseid = $_REQUEST['courseid'];
$activityid = $_REQUEST['activityid'];
$itemid = $_REQUEST['itemid'];
$commentarea = $_REQUEST['commentarea'];
$comment = $_REQUEST['comment'];
if (! $course = $DB->get_record("course", array("id"=>$courseid))) {
//   print_error("Course ID not found");
    print_error(get_string('course_id_not_found', 'local_ratings'));
}
if($courseid==SITEID)
    $context = context_system::instance();	
else
    $context = context_course::instance($course->id);

$PAGE->set_context($context);
$comm = new stdClass;
$comm->courseid = $courseid;
$comm->activityid = $activityid;
$comm->itemid = $itemid;
$comm->commentarea = $commentarea;
$comm->userid = $USER->id;
$comm->comment = $comment;
$comm->time = time();
$string = str_replace(' ', '', $comment);
if($string!=''){
$comm->id = $DB->insert_record( 'local_comment', $comm );
echo get_existing_comments($courseid, $itemid, $comm).'!@'.$DB->count_records('local_comment', array('courseid'=>$courseid, 'activityid'=>$activityid, 'itemid'=>$itemid, 'commentarea'=>$commentarea));
}
?>


