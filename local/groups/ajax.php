<?php
require_once(dirname(__FILE__) . '/../../config.php');

global $DB, $PAGE,$CFG;
require_login();
$systemcontext = (new \local_groups\lib\accesslib())::get_module_context();
$PAGE->set_context($systemcontext);
$action = optional_param('action', 0, PARAM_INT);
$costcenter = optional_param('costcenter', 0, PARAM_INT);
$department = optional_param('department', 0, PARAM_INT);

$userlib = new local_users\functions\userlibfunctions();
switch ($action) {
	 case 'departmentlist':
	 	$departmentlist = $userlib->find_departments_list($costcenter);
	 	echo json_encode(['data' =>$departmentlist]);
	exit;
	break;
}
