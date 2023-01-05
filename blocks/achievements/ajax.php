<?php
define('AJAX_SCRIPT', true);
require_once('../../config.php');
global $CFG, $DB, $USER, $PAGE;

$PAGE->requires->jquery();
$page = required_param('page', PARAM_INT);
$syscontext = (new \local_costcenter\lib\accesslib())::get_module_context();
$PAGE->set_context($syscontext);
$output = $PAGE->get_renderer('block_achievements'); 
require_login();
switch($page){
    case 1:
	    echo json_encode($output->display_achievements(1));
    break;
    case 2:
		echo json_encode($output->display_achievements(2));	
    break;
	case 3:
	  echo json_encode($output->display_achievements(3));	
    break;
}