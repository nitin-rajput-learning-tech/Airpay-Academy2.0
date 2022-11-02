<?php
 define('AJAX_SCRIPT', true);
// @error_reporting(E_ALL | E_STRICT); // NOT FOR PRODUCTION SERVERS!
//  @ini_set('display_errors', '1');    // NOT FOR PRODUCTION SERVERS!

require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $PAGE,$CFG,$USER;
require_login();
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$componentid = required_param('componentid',PARAM_INT);
$component = required_param('component',PARAM_TEXT);
$id = optional_param('id', 0, PARAM_INT);
$action = required_param('action', PARAM_TEXT);

use local_request\api\requestapi;

if($component && $componentid && $action){
  switch($action){
    case 'add' :  
      echo $newrequestid = requestapi:: create($component, $componentid);
      break;
    case 'approve':
     if($component=='Classroom'){
      $updatedid =requestapi::approve($id);
      echo json_encode($updatedid);
     }else{
      echo $updatedid =requestapi::approve($id);
     }
      break;
    case 'deny':  
      echo $updatedid =requestapi::deny($id); 
      break;
    case 'delete':  
      echo $updatedid =requestapi::delete($id); 
      break;                   
  }
  //echo $newrequestid; 
} else {
  echo 0;
} 

