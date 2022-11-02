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
 * @subpackage local_costcenter
 */


global $USER, $DB,$PAGE,$CFG, $OUTPUT;

require_once('../../config.php');

$depth = required_param('depth', PARAM_INT);
$systemcontext = context_system::instance();
require_login();

$PAGE->set_pagelayout('standard');
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/costcenter/costcentersettings.php');
$PAGE->set_heading($SITE->fullname);

if($depth == 1){
    $title = get_string('orgconfig', 'local_costcenter');
    $module = get_string('organisations','local_costcenter');
}else{
    $title = get_string('deptconfig', 'local_costcenter');
    $module = get_string('costcenter','local_costcenter');
}

$PAGE->set_title($title);
$PAGE->navbar->ignore_active();
$PAGE->navbar->add($title,new moodle_url('/local/costcenter/costcentersettings.php'));

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$data_submitted = data_submitted();
if(!empty($data_submitted)){
    $submitted_datas = $data_submitted->module;
    foreach($submitted_datas as $moduleid => $submitted_data){
        $insert_record = new stdClass();
        $update_record = $DB->get_record_sql("SELECT * FROM {local_costcenter} WHERE id = ? ", [$moduleid]);
        if ($update_record) {
            $insert_record = new stdClass();
            $insert_record->id = $update_record->id;
            $submitted_data[] = $update_record->id;
            $insert_record->multipleorg = implode(',', $submitted_data);
            $insert_record->timemodified = time();
            $DB->update_record('local_costcenter', $insert_record);
        } else {
            $insert_record->moduleid=$moduleid;
            $insert_record->multipleorg = implode(',', $submitted_data);
            $insert_record->timecreated = time();
            $insert_record->usermodified = $USER->id;
            $DB->insert_record('local_costcenter', $insert_record);
        }
    }
}
if($depth == 1){
    $costcenters="SELECT id,fullname from {local_costcenter} where depth=1";
}elseif($depth == 2){
    $costcenters="SELECT id,fullname from {local_costcenter} where depth=2";  
} else {
    print_error('invalid depth');
}

$costcenters_list=$DB->get_records_sql($costcenters);
$count=count($costcenters_list);

$table = new html_table();
$data=array();
foreach($costcenters_list as $module_lists){
    $list=array(); 
    $i = 2;
    $costcenters_list_check=array();
    foreach($costcenters_list as $costcenters_lists){
        $checkbox = $DB->get_field_sql("SELECT multipleorg FROM {local_costcenter} WHERE CONCAT(',',multipleorg,',') LIKE CONCAT('%,',{$costcenters_lists->id},',%') AND  id = $module_lists->id ");//FIND_IN_SET($costcenters_lists->id, multipleorg )
        $checkbox = explode(',', $checkbox);
        if(in_array($costcenters_lists->id, $checkbox) || $module_lists->id == $costcenters_lists->id){
            $disabled = '';
            if($module_lists->id == $costcenters_lists->id) {
                $disabled = 'disabled';
            }
            $costcenters_list_check[$i]='<input type="checkbox" name="module['.$module_lists->id.'][]" value="'.$costcenters_lists->id.'" checked '.$disabled.'>';
        }else{
            $costcenters_list_check[$i]='<input type="checkbox" name="module['.$module_lists->id.'][]" value="'.$costcenters_lists->id.'">';
        }
         $i++;
    }
    $mod[]= $module_lists->fullname;
    $list[]= $module_lists->fullname;
    $data[]=$list+$costcenters_list_check;
}
$table->head = array_merge(array($module),$mod);
$table->data = $data;
echo '<form method="post">'.html_writer::table($table).'<input type="submit" value="Submit"></form>';

echo $OUTPUT->footer();