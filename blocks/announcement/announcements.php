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
 * @subpackage blocks_announcement
 */
global $DB,$CFG, $USER, $OUTPUT, $PAGE;
require_once(dirname(__FILE__) . '/../../config.php');
// use \blocks_announcement\form\announcement_form as announcement_form;
require_once($CFG->dirroot . '/blocks/announcement/lib.php');
$id = optional_param('id', 0, PARAM_INT);
$visible = optional_param('visible', -1, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);
$collapse = optional_param('collapse', 0, PARAM_INT);
$courseid = 1;
require_login();

$PAGE->set_context((new \block_announcement\lib\accesslib())::get_module_context());
// $PAGE->set_pagelayout('admin');
$pageurl = new moodle_url('/blocks/announcement/announcements.php', array('courseid' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('pluginname', 'block_announcement'));
$PAGE->set_heading(get_string('pluginname', 'block_announcement'));
$PAGE->requires->jquery();
// $PAGE->requires->js('/blocks/announcement/js/jquery.dataTables.min.js',true);//*This js and css files for data grid of batches*//
$PAGE->requires->css('/blocks/announcement/css/jquery.dataTables.css');
$PAGE->navbar->add(get_string('pluginname', 'block_announcement'));
$systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
if(isguestuser($USER->id)){
   print_error('nopermission');
}
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->js_call_amd('block_announcement/announcement', 'DatatablesAnnounce', array());
$renderer = $PAGE->get_renderer('block_announcement');

if($id > 0 && $visible != -1){
	$dataobject=new stdClass();
	$dataobject->id=$id;
	$dataobject->visible=$visible;
	$DB->update_record('block_announcement', $dataobject);
	redirect($pageurl);
}

echo $OUTPUT->header();
 if($delete > 0){
     $announcement = $DB->get_record('block_announcement', array('id' => $delete));
     if($announcement){      
             if($DB->delete_records('block_announcement', array('id' => $delete))){
                 echo $OUTPUT->notification(get_string('announce_delete', 'block_announcement', $announcement->name), 'notifysuccess');
             }      
     }
 }
if(is_siteadmin($USER->id) || has_capability('block/announcement:manage_announcements', $systemcontext)){
    // $addnew = get_string('createnew', 'block_announcement').' +';
  $systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
$announcement = $DB->record_exists('block_announcement', array('courseid' => $courseid));
    
    // $back_url = new moodle_url('/my/', array());
    //  echo html_writer::link($back_url,
    //                         '<< Back', array('class'=>'pull-left', 'style'=>'font-size:18px;padding-right:10px;')); //
	
	if($announcement){
	echo "<ul class='course_extended_menu_list'>
			  <li>
					<div class='coursebackup'>   
						 <a class='course_extended_menu_itemlink' title='".get_string('create_announcement', 'block_announcement')."' data-action='announcementmodal' onclick ='(function(e){ require(\"block_announcement/announcement\").init({selector:\"announcementmodal\", contextid:$systemcontext->id, id:0}) })(event)'><i class='icon fa fa-plus' aria-hidden='true'></i>
							  </a>
					</div>
			  </li>
		 </ul>";
	}else{
		echo "<ul class='course_extended_menu_list'>
			  <li>
					<div class='coursebackup course_extended_menu_itemcontainer'>   
						 <a class='course_extended_menu_itemlink' title='".get_string('create_announcement', 'block_announcement')."' data-action='announcementmodal' onclick ='(function(e){ require(\"block_announcement/announcement\").init({selector:\"announcementmodal\", contextid:$systemcontext->id, id:0}) })(event)'><i class='icon fa fa-plus' aria-hidden='true'></i>
							  </a>
					</div>
			  </li>
		 </ul>";
	}
}
echo $renderer->announcements($courseid);
echo $OUTPUT->footer();
