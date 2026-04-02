<?php
require_once('../../config.php');

// $costcenterpath="/159";
// echo $costcenterpath;
// $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='g.open_path',$costcenterpath);

//echo $costcenterpathconcatsql;
// require_once $CFG->libdir.'/gradelib.php';
// require_once $CFG->dirroot.'/grade/lib.php';
// require_once $CFG->dirroot.'/grade/report/overview/lib.php';
// $context = context_course::instance(66);
// $systemcontext = context_system::instance();
//  $personalcontext = context_user::instance(134);
//  $course = $DB->get_record('course', array('id' => 65));
// $access = grade_report_overview::check_access($systemcontext, $context, $personalcontext, $course, 134);
// require_once($CFG->dirroot . '/local/costcenter/lib.php');

//  $costcenterdepth=local_costcenter_get_fields();

//         $depth=count($costcenterdepth);
//         var_dump($depth);
// var_dump($USER->useraccess['currentroleinfo']['depth']);
echo $OUTPUT->header();
//  $url = new moodle_url('/local/costcenter/test.php', array('sesskey'=>sesskey()));
// $costcenterpath = (new \local_costcenter\lib\accesslib())::get_user_role_switch_select_option($url);

// echo $costcenterpath;

$open_path = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'c.open_path');
echo $open_path;
echo $OUTPUT->footer();
die;