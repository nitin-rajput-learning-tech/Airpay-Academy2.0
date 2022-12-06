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
$PAGE->set_url($CFG->wwwroot .'/local/location/room.php');
$PAGE->set_title(get_string('room', 'local_location'));
$PAGE->set_heading(get_string('room', 'local_location'));
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('pluginname', 'local_location'), new moodle_url('/local/location/index.php'));
$PAGE->navbar->add(get_string('rooms', 'local_location'));
$PAGE->requires->jquery();
$PAGE->requires->js('/local/location/js/delconfirm.js',TRUE);
$PAGE->requires->js('/local/location/js/jquery.min.js',TRUE);
$PAGE->requires->js('/local/location/js/datatables.min.js', TRUE);
$PAGE->requires->css('/local/location/css/datatables.min.css');
$room = new local_location\event\location();
$renderer = $PAGE->get_renderer('local_location');
$id = optional_param('id',0,PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
echo $OUTPUT->header();
if ((has_capability('local/location:manageinstitute', $categorycontext) || has_capability('local/location:viewinstitute', $categorycontext))) {
if ((has_capability('local/location:manageroom', $categorycontext) || has_capability('local/location:viewroom', $categorycontext))) {
if ((has_capability('local/location:manageroom', $categorycontext))) {
$PAGE->requires->js_call_amd('local_location/newroom', 'load', array());
echo "<ul class='course_extended_menu_list'>
          <li> 
              <div class = 'coursebackup course_extended_menu_itemcontainer'>
                <a data-action='createroommodal' data-value='0' class='course_extended_menu_itemlink' onclick ='(function(e){ require(\"local_location/newroom\").init({selector:\"createroommodal\", contextid:$categorycontext->id, roomid:$id}) })(event)' title='".get_string('createroom', 'local_location')."'><i class='icon fa fa-plus' aria-hidden='true'></i>
                </a>
              </div>
          </li>
      </ul>";
}

if($delete){
  $room->delete_rooms($id);
  redirect(new moodle_url('/local/location/room.php'));
}

$mform = new local_location\form\roomform(null,array('id'=>$id));
$form_data = $room->set_data_institute($id);
$mform->set_data($form_data);
if ($mform->is_cancelled()) {

}else if ($roomdata = $mform->get_data()) {
  if($id > 0){
    $record->id = $data->id;
    $res = $room->room_update_instance($roomdata);
  }else{
    $res = $room->room_insert_instance($roomdata);
  }
  $returnurl = new moodle_url('../../local/location/room.php');
  redirect($returnurl);
}

echo $renderer->display_rooms();
}else{
   echo get_string('no_permissions','local_location');
}
}else{
   echo get_string('no_permissions','local_location');
}
echo $OUTPUT->footer();



