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
require_once("../../../config.php");
global $CFG; $DB;
$formPath = "$CFG->libdir/formslib.php";
require_once($formPath);
require_login();


/** Navigation Bar **/
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('requestDisplay', 'block_request'), new moodle_url('/blocks/request/request_admin.php'));
$PAGE->navbar->add(get_string('bulkdeny', 'block_request'));
$PAGE->set_url('/blocks/request/admin/bulk_deny.php');
$PAGE->set_context((new \local_request\lib\accesslib())::get_module_context());
$PAGE->set_heading(get_string('pluginname', 'block_request'));
$PAGE->set_title(get_string('pluginname', 'block_request'));
echo $OUTPUT->header();
?>


<SCRIPT LANGUAGE="JavaScript" SRC="http://code.jquery.com/jquery-1.6.min.js">
</SCRIPT>
<?php


$context =(new \local_request\lib\accesslib())::get_module_context();
if (has_capability('block/request:denyrecord',$context)) {
} else {
  print_error(get_string('cannotdenyrecord', 'block_request'));
}

if(isset($_GET['id'])){
	$mid = required_param('id', PARAM_INT);
	$_SESSION['mid'] = $mid;
} else {
	$mid = $_SESSION['mid'];
}


if(isset($_GET['mul'])){
	$_SESSION['mul'] = required_param('mul', PARAM_TEXT);
}

class block_request_bulk_deny extends moodleform {
 
    function definition() {
        global $CFG;
        global $currentSess;
		global $mid;
		global $USER;
		global $DB;
 	
        $currentRecord =  $DB->get_record('block_request_records', array('id'=>$currentSess));
        $mform =& $this->_form; // Don't forget the underscore! 
		 
        $mform->addElement('header', 'mainheader', get_string('denyrequest_Title','block_request'));

		// Page description text
		$mform->addElement('html', '<p></p>&nbsp;&nbsp;&nbsp;
					    <a href="../request_admin.php">< Back</a>
					    <p></p>
					    <center>'.get_string('denyrequest_Instructions','block_request').'.<p></p>&nbsp;</center><center>');
	
		// Comment box
		$mform->addElement('textarea', 'newcomment', '', 'wrap="virtual" rows="5" cols="50"');
		
		$buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('denyrequest_Btn','block_request'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

		$mform->addElement('html', '<p></p>&nbsp;</center>');

	}
}




   $mform = new block_request_bulk_deny();//name of the form you defined in file above.



   if ($mform->is_cancelled()){
        
	echo "<script>window.location='../request_admin.php';</script>";
			die;

  } else if ($fromform=$mform->get_data()){
		global $USER, $CFG, $DB;
		
		// Send Email to all concerned about the request deny.
		require_once('../request_email.php');
		
		
			$message = $_POST['newcomment'];
			$denyIds = explode(',',$_SESSION['mul']);
		    
			foreach ($denyIds as $cid) {
			
				// If the id isn't blank
				if ($cid != 'null') {
				
							$currentRecord =  $DB->get_record('block_request_records', array('id'=>$cid));
		
							$replaceValues = array();
						    $replaceValues['[course_code'] = $currentRecord->modcode;
						    $replaceValues['[course_name]'] = $currentRecord->modname;
						    $replaceValues['[e_key]'] = '';
						    $replaceValues['[full_link]'] = $CFG->wwwroot .'/blocks/request/comment.php?id=' . $cid;
						    $replaceValues['[loc]'] = '';
						    $replaceValues['[req_link]'] = $CFG->wwwroot .'/blocks/request/view_summary.php?id=' . $cid;
	    
						    
	    
						    // update the request record
							$newrec = new stdClass();
							$newrec->id = $cid;
							$newrec->status = 'REQUEST DENIED';
							$DB->update_record('block_request_records', $newrec); 
							
							// Add a comment to the module
							$userid = $USER->id;
							$newrec = new stdClass();
							$newrec->instanceid = $cid;
							$newrec->createdbyid = $userid;
							$newrec->message = $message;
							$newrec->dt = \local_costcenter\lib::get_userdate("d/m/Y H:i");	
							$DB->insert_record('block_request_comments', $newrec);
					
							block_request_send_deny_email_admin($message, $cid, $replaceValues);
								
							block_request_send_deny_email_user($message, $userid, $cid, $replaceValues);
							
							$_SESSION['mul'] = '';
							
				}
			
		
			}	
		

		echo "<script> window.location = '../request_admin.php';</script>";


  } else {
      
       $mform->focus();
	   $mform->set_data($mform);
	   $mform->display();
	   echo $OUTPUT->footer();
}





?>
