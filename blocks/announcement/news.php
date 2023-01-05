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
use \blocks_announcement\form\announcement_form as announcement_form;
require_once($CFG->dirroot . '/blocks/announcement/lib.php');
$delete = optional_param('delete', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$courseid = 1;
require_login();
//$coursecontext = context_course::instance($courseid);
$systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$pageurl = new moodle_url('/blocks/announcement/news.php',array('id'=>$id));
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('pluginname', 'block_announcement'));
$PAGE->navbar->add(get_string('pluginname', 'block_announcement'));
if(isguestuser($USER->id)){
   print_error('nopermission');
}
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('block_announcement');
    $announcements_sql = $DB->get_record_sql("SELECT id,courseid,usermodified,name,description,attachment FROM {block_announcement} WHERE id = $id");
                $data = '';
                $course = $DB->get_record('course', array('id' => $announcements_sql->courseid, 'visible' => 1));
                //if(!$course){
                //    continue;
                //}
                $user = $DB->get_record('user', array('id' => $announcements_sql->usermodified, 'confirmed' => 1, 'deleted' => 0, 'suspended' => 0));
                //if(!$user){
                //    continue;
                //}
                $data .= html_writer::tag('h3', $announcements_sql->name, array('class' => 'createnews'));
                $url = new moodle_url('/my/', array());
                $out = html_writer::link($url, '<< Back', array());
                $data .= html_writer::div($out, 'delnews pull-right text-right mt-10 mb-10  p-10 mr-20 clear');
                if($announcements_sql->attachment){
                    $file =$DB->get_record_sql("SELECT * FROM {files} WHERE itemid = $announcements_sql->attachment and filename!='.' and component = 'block_announcement' and filearea = 'announcement'");
                    $filedata = get_file_storage();
                    $files = $filedata->get_area_files($file->contextid, 'block_announcement', 'announcement',$file->itemid, 'id', false);
                    $download_link = "<div  style='float:right !important' title='".get_string('noattachement','block_announcement')."'> - </div>";
                    if(!empty($files)){
                        $url = array(); 
                        foreach ($files as $file) {                       
                            $url[] = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $file->get_contextid() . '/' . 'block_announcement' . '/' . 'announcement' .'/'.$file->get_itemid(). $file->get_filepath() . $file->get_filename(), !$isimage);
                        }
                        $download_link = "<div style='float:right !important'><a href=".$url[0]." download title='".get_string('attachment','block_announcement')."'><i class='fa fa-download'></i></a></div>";
                    }
                    $data .= $download_link;

                }else{
                    $data .= "<div class='col-md-1 col-sm-1 col-1  pull-left p-0 text-center pull-right mt-10' title='".get_string('noattachement','block_announcement')."'> - </div>";
                }
                   $data .= html_writer::div(($announcements_sql->description), 'addnews')."</br>";

  
                
                $return = '<input type="submit" id="submit_news"  value="Back" />';
                
            echo $data;
echo $OUTPUT->footer();

