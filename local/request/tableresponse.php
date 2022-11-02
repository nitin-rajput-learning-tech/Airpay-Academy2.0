<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_request
 */

 //define('AJAX_SCRIPT', true);
//@error_reporting(E_ALL | E_STRICT); // NOT FOR PRODUCTION SERVERS!
// @ini_set('display_errors', '1');    // NOT FOR PRODUCTION SERVERS!

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

		case 'add' :  echo $newrequestid = requestapi:: create($component, $componentid);
                  break;
    case 'approve':  echo $updatedid =requestapi::approve($id);
                       break;
    case 'deny':  echo $updatedid =requestapi::deny($id); 
                       break;

    case 'delete':  echo $updatedid =requestapi::delete($id); 
                       break;                   
    }
    //echo $newrequestid; 
 }
 else {

  	echo 0;
  } 

