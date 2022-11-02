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
global $CFG, $DB;
$formPath = "$CFG->libdir/formslib.php";
require_once($formPath);
require_once('../lib/displayLists.php');

/** Navigation Bar **/
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('requestDisplay', 'block_request'), new moodle_url('/blocks/request/request_admin.php'));
$PAGE->navbar->add(get_string('approvecourse', 'block_request'));
require_login();

$PAGE->set_url('/blocks/request/admin/approve_course.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('pluginname', 'block_request'));
$PAGE->set_title(get_string('pluginname', 'block_request'));
echo $OUTPUT->header();

$context = context_system::instance();
if (has_capability('block/request:approverecord',$context)) {
} else {
  print_error(get_string('cannotapproverecord', 'block_request'));
}



?>



<script language="javascript" type="text/javascript">

function popitup(url) {
	newwindow=window.open(url,'name','height=600,width=400');
	if (window.focus) {newwindow.focus()}
	return false;
}


function goBack(){
	window.location ="../request_admin.php";
}

</script>
<style>
	tr:nth-child(odd)		{ background-color:#eee; }
	tr:nth-child(even)		{ background-color:#fff; }
 </style>
<?php


if(isset($_GET['id'])){
	$mid = required_param('id', PARAM_INT);
	$_SESSION['mid'] = $mid;
} else {

	$mid = $_SESSION['mid'];
}



class block_request_approve_course_form extends moodleform {

    function definition() {
    global $CFG, $currentSess, $mid, $USER, $DB;

 	$rec =  $DB->get_record('block_request_records', array('id'=>$mid));

	$mform =& $this->_form; // Don't forget the underscore!
	$mform->addElement('header', 'mainheader', '<span style="font-size:18px">'. get_string('requestadmin','block_request'). '</span>');

	// Page description text
	$mform->addElement('html', '<p></p>&nbsp;&nbsp;&nbsp;   <button type="button" ><img src="../icons/back.png"/>'.get_string('back','block_request').'</button><p></p>');

	$rec = $DB->get_recordset_select('block_request_records', 'id = ' . $mid);
   	$displayModHTML = block_request_display_admin_list($rec, false, false, false, '');

	$outputHTML = '<div>'.$displayModHTML.'</div>';

	$mform->addElement('html', $outputHTML);
	$mform->addElement('html', '<button type="button" onclick="window.location.href=\'approve_course_new.php\'">'.get_string('requestReview_ApproveRequest','block_request').'</button>');
	$mform->addElement('html', '<button type="button" onclick="return popitup(\'showcoursedetails.php?id='.$mid.'\')">'.get_string('requestReview_OpenDetails','block_request').'</button>');


	}
}


$mform = new block_request_approve_course_form();//name of the form you defined in file above.

if ($mform->is_cancelled()) {
}
else if ($fromform=$mform->get_data()) {
	
}
else {
	$mform->set_data($mform);
	$mform->display();
    echo $OUTPUT->footer();
}





?>
