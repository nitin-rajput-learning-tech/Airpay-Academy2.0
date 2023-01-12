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
 * @subpackage local_notifications
 */


require_once(dirname(__FILE__) . '/../../config.php');
global $CFG, $USER, $PAGE, $OUTPUT;
$id = optional_param('id', 0, PARAM_INT);
$component = optional_param('component', null, PARAM_RAW);
require_once($CFG->dirroot . '/local/courses/filters_form.php');
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$sitecontext =(new \local_notifications\lib\accesslib())::get_module_context();
require_login();
$PAGE->set_url('/local/notifications/index.php', array());
$PAGE->set_context($sitecontext);
$PAGE->set_title(get_string('pluginname', 'local_notifications'));
$PAGE->set_heading(get_string('pluginname', 'local_notifications'));
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/local/notifications/css/jquery.dataTables.min.css', true);
$renderer = $PAGE->get_renderer('local_notifications');
require_capability('local/notifications:manage', $sitecontext);

echo $OUTPUT->header();
if($deleteid && $confirm && confirm_sesskey()){	
	$result = $DB->delete_records("local_notification_info", array('id'=>$deleteid));
	if($result){
		redirect($CFG->wwwroot.'/local/notifications/index.php');
	}
}
$PAGE->requires->js_call_amd('local_notifications/notifications', 'load');
// $PAGE->requires->js_call_amd('local_notifications/notifications', 'init', array('[data-action=createnotificationmodal]', $sitecontext->id, $id));
echo "<ul class='course_extended_menu_list'>
         <li>
        <div class='coursebackup course_extended_menu_itemcontainer'>
                <a href=$CFG->wwwroot/local/notifications/email_status.php title='".get_string('email_status', 'local_notifications')."' class='course_extended_menu_itemlink'>
                    <i class='icon fa fa-envelope-square'></i>
                </a>
            </div>
       </li>
        <li>
        <div class='coursebackup course_extended_menu_itemcontainer'>
                        <a id='extended_menu_createusers' title='".get_string('createnotification', 'local_notifications')."' class='course_extended_menu_itemlink' data-action='createnotificationmodal' onclick ='(function(e){ require(\"local_notifications/notifications\").init({selector:\"createnotificationmodal\", context:$sitecontext->id, id:$id, form_status:0}) })(event)' ><i class='icon fa fa-bell-o createicon' aria-hidden='true'></i><!-- <i class='fa fa-plus createiconchild' aria-hidden='true'></i> --></a>
        </div>
        </li>
        
    </ul>";
$PAGE->requires->js_call_amd('local_notifications/custom', 'init');
$PAGE->requires->js_call_amd('local_notifications/custom', 'notificationDatatable', array(array('id' => $id, 'context' => $sitecontext)));
$notifications = new \local_notifications\output\notifications();

$collapse = true;
$show = '';


// if ($mform->is_cancelled()) {
//     redirect($CFG->wwwroot . '/local/notifications/index.php');
// }else{
//     $collapse = true;
//     $show = '';
//     $list=null;
// }


//passing options and dataoptions in filter
    $filterparams = $renderer->managenotifications_content(true);

    //for filtering users we are providing form
    $mform = new filters_form(null, array('filterlist'=>array('notifications'), 'filterparams' => $filterparams));
    // $mform = users_filters_form($filterparams);
    if($mform->is_cancelled()){
        redirect('index.php');
    }
    echo '<a class="btn-link btn-sm" title="'.get_string('filter').'" href="javascript:void(0);" data-toggle="collapse" data-target="#local_notifications-filter_collapse" aria-expanded="false" aria-controls="local_notifications-filter_collapse">
        <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
      </a>';
    echo  '<div class="collapse '.$show.'" id="local_notifications-filter_collapse">
                <div id="filters_form" class="card card-body p-2">';
                    $mform->display();
    echo        '</div>
            </div>';




//echo $renderer->render($notifications);

echo $renderer->managenotifications_content();

echo $OUTPUT->footer();
