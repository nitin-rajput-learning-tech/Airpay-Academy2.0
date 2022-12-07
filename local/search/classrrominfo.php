<?php
define('AJAX_SCRIPT',true);
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG, $DB, $USER;

$systemcontext = \local_costcenter\lib\accesslib::get_module_context();
$PAGE->set_context($systemcontext);
require_login();

$crid = optional_param('crid','', PARAM_INT);
$learningplanid = optional_param('learningplanid','', PARAM_INT);
$renderer = $PAGE->get_renderer('local_search');
if ($crid){
	echo $renderer->get_classroom_info($crid);
}
if ($learningplanid){
    echo $renderer->get_learningplan_info($learningplanid);
}
// }
?>






