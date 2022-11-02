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
require_once("$CFG->libdir/formslib.php");
require_login();
/** Navigation Bar **/
$PAGE->set_context(context_system::instance());
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('requestDisplay', 'block_request'), new moodle_url('/blocks/request/request_admin.php'));
$PAGE->navbar->add(get_string('bulkapprove', 'block_request'));
$PAGE->set_url('/blocks/request/admin/bulk_approve.php');

$PAGE->set_title(get_string('pluginname', 'block_request'));


$context = context_system::instance();
if (has_capability('block/request:approverecord',$context)) {
} else {
  print_error(get_string('cannotapproverecord', 'block_request'));
}


if (isset($_GET['mul'])) {
	$_SESSION['mul'] = required_param('mul', PARAM_TEXT);
}

class block_request_bulk_approve_form extends moodleform {
 
    function definition() {
        global $CFG;
        global $currentSess;
		global $USER;
		global $DB;
 	
        $currentRecord =  $DB->get_record('block_request_records', array('id'=>$currentSess));
        $mform =& $this->_form; // Don't forget the underscore! 
		 
        $mform->addElement('header', 'mainheader', get_string('approvingcourses','block_request'));

		// Page description text
		$mform->addElement('html', '<p></p>&nbsp;&nbsp;&nbsp;
					    	<a href="../request_admin.php">< Back</a>');

		$mform->addElement('html', '<p></p><center>'.get_string('approvingcourses', 'block_request').'</center>');
		
		
		global $USER, $CFG, $DB;
		
		// Send Email to all concerned about the request deny.
		require_once('../lib/course_lib.php');
		
		$denyIds = explode(',',$_SESSION['mul']);
		    
			foreach ($denyIds as $cid) {
			
				// If the id isn't blank
				if ($cid != 'null') {
				
						$mid = block_request_create_new_course_by_record_id($cid, true);
									
				}
			
		
			}	
	
		$_SESSION['mul'] = '';
		echo "<script> window.location = '../request_admin.php';</script>";
		

	}
}




   $mform = new block_request_bulk_approve_form();//name of the form you defined in file above.



   if ($mform->is_cancelled()) {
        
	echo "<script>window.location='../request_admin.php';</script>";
	die;

  } else if ($fromform=$mform->get_data()) {
	


  } else {
	    $mform->display();

}





?>
