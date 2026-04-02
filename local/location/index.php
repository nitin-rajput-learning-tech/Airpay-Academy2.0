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
 * @subpackage local_location
 */



require_once(dirname(__FILE__) . '/../../config.php');
global $CFG,$PAGE;
require_once($CFG->dirroot . '/local/location/lib.php');
$categorycontext = (new \local_location\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);
$PAGE->set_url($CFG->wwwroot .'/local/location/index.php');
$PAGE->set_title(get_string('institute', 'local_location'));
$PAGE->set_heading(get_string('institute', 'local_location'));
$PAGE->navbar->ignore_active();
require_login();

$renderer = $PAGE->get_renderer('local_location');
$institute = new local_location\event\location();
$PAGE->requires->jquery();
$PAGE->requires->js('/local/location/js/delconfirm.js');
$PAGE->requires->js('/local/location/js/jquery.min.js',TRUE);
$PAGE->requires->js('/local/location/js/datatables.min.js', TRUE);
$PAGE->requires->css('/local/location/css/datatables.min.css');
$id = optional_param('id',0,PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$component = optional_param('component', 0, PARAM_RAW);
$components = optional_param('components', 0, PARAM_RAW);
$componentss = optional_param('componentss', 0, PARAM_RAW);
if($component){
  $PAGE->navbar->add(get_string($component, 'local_location'), new moodle_url('/local/'.$component.'/index.php'));
}elseif($components){
  $PAGE->navbar->add(get_string($components, 'local_location'), new moodle_url('/local/'.$components.'/index.php'));
}else if ($componentss){
  $PAGE->navbar->add(get_string($componentss, 'local_location'), new moodle_url('/local/'.$componentss.'/index.php'));
}
$PAGE->navbar->add( get_string('pluginname', 'local_location'));

echo $OUTPUT->header();

if ((has_capability('local/location:manageinstitute', $categorycontext) || has_capability('local/location:viewinstitute', $categorycontext))) {


if ((has_capability('local/location:manageinstitute', $categorycontext))|| has_capability('local/location:viewinstitute', $categorycontext)) {
  $PAGE->requires->js_call_amd('local_location/newinstitute', 'load', array());
echo "<ul class='course_extended_menu_list'>";
if ((has_capability('local/location:manageroom', $categorycontext) || has_capability('local/location:viewroom', $categorycontext))) {
   echo" <li>
        <div class = 'coursebackup course_extended_menu_itemcontainer' >
            <a href='".$CFG->wwwroot."/local/location/room.php' class='course_extended_menu_itemlink create_ilt' title='".get_string('room_title','local_location')."'><i class='icon fa fa-simplybuilt'></i></a>
        </div>
    </li>";
  }
  if(has_capability('local/location:manageinstitute', $categorycontext)){
		echo"<li>	
			<div class = 'coursebackup course_extended_menu_itemcontainer'>
				<a data-action='createinstitutemodal' data-value='0' class='course_extended_menu_itemlink' onclick ='(function(e){ require(\"local_location/newinstitute\").init({selector:\"createinstitutemodal\", contextid:$categorycontext->id, instituteid:$id}) })(event)' title='".get_string('createinstitute', 'local_location')."'><i class='icon fa fa-plus' aria-hidden='true'></i>
				</a>
			</div>
		</li>";
  }
	echo"</ul>";
}
if($delete){
  $institute->delete_institutes($id);
  redirect(new moodle_url('/local/location/index.php'));
}
$mform = new local_location\form\instituteform(null,array('id'=>$id));
$form_data = $institute->set_data_institute($id);
$mform->set_data($form_data);
if ($mform->is_cancelled()) {

}else if ($data = $mform->get_data()) {
  if($id > 0){
    $record->id = $data->id;
    $res = $institute->institute_update_instance($data);
  }else{
    $res = $institute->institute_insert_instance($data);
  }
  $returnurl = new moodle_url('/local/location/index.php');
  redirect($returnurl);
}
echo $renderer->display_institutes();
}else{
 echo get_string('no_permissions','local_location');
}
echo $OUTPUT->footer();



