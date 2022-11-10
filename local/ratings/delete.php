<?php
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/local/ratings/lib.php');
global $USER,$DB,$CFG;

$id = $_REQUEST['id'];
$courseid = $_REQUEST['courseid'];
$activityid = $_REQUEST['activityid'];
$itemid = $_REQUEST['itemid'];
$commentarea = $_REQUEST['commentarea'];
$DB->delete_records('local_comment', array('id'=>$id));
echo $DB->count_records('local_comment', array('courseid'=>$courseid, 'activityid'=>$activityid, 'itemid'=>$itemid, 'commentarea'=>$commentarea));
?>


