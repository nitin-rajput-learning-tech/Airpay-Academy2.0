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
 * @subpackage local_skillrepository
 */
require_once(dirname(__FILE__) . '/../../config.php');
global $USER, $CFG, $PAGE, $OUTPUT, $DB;
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/local/skillrepository/lib.php');

$PAGE->requires->jquery();
$PAGE->requires->js('/local/skillrepository/js/jquery.dataTables.js',true);
$PAGE->requires->js('/local/skillrepository/js/skills_script.js',true); //For downloading csv
$PAGE->requires->js('/local/skillrepository/js/dataTables.buttons.min.js',true);
$PAGE->requires->js('/local/skillrepository/js/buttons.html5.min.js',true);
//$PAGE->requires->css('/local/skillrepository/css/jquery.dataTables.css');
$PAGE->requires->css('/local/skillrepository/css/buttons.dataTables.min.css');

$id = optional_param('id', 0, PARAM_INT);
$delete_id = optional_param('delete_id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$submitbutton = optional_param('submitbutton', '', PARAM_RAW);

require_login();
$PAGE->set_url('/local/skillrepository/index.php');
//$PAGE->set_context(context_system::instance());
$PAGE->set_context((new \local_skillrepository\lib\accesslib())::get_module_context());
$PAGE->set_pagelayout('standard');

$PAGE->set_title(get_string('pluginname', 'local_skillrepository'));
$PAGE->navbar->add(get_string('manage_skills', 'local_skillrepository'));

$PAGE->requires->js_call_amd('local_skillrepository/newrepository', 'load', array());
//$PAGE->requires->js_call_amd('local_skillrepository/newcategory', 'load', array());
//$systemcontext = context_system::instance();
$systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();

$id = 1; 

if (!has_capability('local/skillrepository:create_skill', (new \local_skillrepository\lib\accesslib())::get_module_context()) && !is_siteadmin()) {
	print_error('Sorry, You are not accessable to this page');
}
$renderer = $PAGE->get_renderer('local_skillrepository'); 
$repository = new local_skillrepository\event\insertrepository();
// if id exists, get curernt id details else create a new class

if($id > 0) {
	$toform = $repository->skillrepository_opertaions('local_skill', 'fetch-single', '', 'id', $id);  
	$description= isset($toform->description);
    $toform->description=array();
	$toform->description['text'] = isset($description);
} else {
	$fromform = new stdClass();
}



// skill repository form	        
$mform = new local_skillrepository\form\skill_repository_form(null, array('id'=>$id)); //create object for Skill Repository Form

if ($mform->is_cancelled()) {
	redirect('index.php');
} else if ($fromform = $mform->get_data()) {
	$fromform->description = $fromform->description['text'];
	if($fromform->id){
		$result = $repository->skillrepository_opertaions('local_skill', 'update', $fromform);
	} else{
		$result = $repository->skillrepository_opertaions('local_skill', 'insert', $fromform);	
	}	
	if($result)
		redirect($PAGE->url);
		
} else {
	if($id > 0) {
		$collapse = false;
		$description = array();
		
		$description['format'] = 1;
	//print_object($toform);
		$mform->set_data($toform);
	}elseif($submitbutton){
		$collapse = false;
	}else{
		$collapse = true;
	}
	
}


// Delete Skill Repository
/*$returnurl = new moodle_url('/local/skillrepository/index.php');
if ($delete) {
    $PAGE->url->param('delete', 1);
    if ($confirm and confirm_sesskey()) {
		$result = $repository->skillrepository_opertaions('local_skill', 'delete', '', 'id', $delete_id);
        redirect($returnurl);
    }
    $strheading = get_string('deleteskill', 'local_skillrepository');
    $PAGE->set_title($strheading);

    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);

        $yesurl = new moodle_url('/local/skillrepository/index.php', array('delete_id' => $delete_id, 'delete' => 1, 'confirm' => 1, 'sesskey' => sesskey()));
        $message = get_string('delconfirm', 'local_skillrepository');
        echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    
    echo $OUTPUT->footer();
    die;
}*/
$PAGE->set_heading(get_string('manage_skills', 'local_skillrepository'));
echo $OUTPUT->header();
echo $renderer->get_top_action_buttons_skills();
$skill = $repository->skillrepository_opertaions('local_skill', 'fetch-multiple','','','');
if(empty($skill)){
	$collapse = false;
}
// Display Skill Repository Table
//echo $renderer->display_table();
echo $renderer->manageskills_content();

echo $OUTPUT->footer();
