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
namespace block_announcement\output;
require_once($CFG->dirroot . '/blocks/announcement/lib.php');
defined('MOODLE_INTERNAL') || die;

use context_system;
use html_table;
use html_table_cell;
use html_writer;
use plugin_renderer_base;
use moodle_url;
use stdClass;
use single_button;

class renderer extends plugin_renderer_base {
    
    public function announcements($courseid, $limit = 0){
        global $DB,$CFG, $USER, $OUTPUT, $PAGE;
        $systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
        $announcement_lib = new \block_announcement\local\lib();
        /*$announcements = $announcement_lib->announcements($courseid, $limit, false);*/
        $announcements = $announcement_lib->get_announcement_details($courseid, $limit, false);
        $no_redirect_url = 'javascript:void(0)';
        $data = [];
        $open_path = explode('/',$USER->open_path);
        if(array_key_exists('1',$open_path))
        $open_costcenterid = $open_path[1];

        if(!empty($announcements)){
            $data = array();
            foreach($announcements as $announce){
                $row = array();
                $buttons = [];
                $user_name = $DB->get_field_sql("SELECT CONCAT(firstname,' ',lastname) as fullname FROM {user} WHERE id=:id AND confirmed=:confirmed AND deleted=:deleted AND suspended=:suspended ", array('id' => $announce->usermodified, 'confirmed' => 1, 'deleted' => 0, 'suspended' => 0));                                     
                if(!$user_name){
                    continue;
                }
                $link = html_writer::div("<a href='".new moodle_url('/blocks/announcement/news.php',array('id'=>$announce->id))."'>".'...'.get_string('viewmore', 'block_announcement')."</a>");
                if($announce->name > substr(($announce->name),0,35)){
                    
                    $row[] = substr($announce->name,0,35).' '.$link;
                }else{
                    $row[] = $announce->name;
                }
                if($announce->description > substr(($announce->description),0,50)){
                    $row[] = substr(strip_tags($announce->description),0,50).$link;
                }else{
                    $row[] = strip_tags($announce->description);
                }

                $row[] = $user_name;
                        
                $row[] = ($announce->timemodified) ? \local_costcenter\lib::get_userdate('d m Y h:i', $announce->timemodified) : '-';
                if($announce->attachment){
                    $file =$DB->get_record_sql("SELECT * FROM {files} WHERE itemid = $announce->attachment and filename!='.' and component = 'block_announcement' and filearea = 'announcement'");
                    $filedata = get_file_storage();
                    $files = $filedata->get_area_files($file->contextid, 'block_announcement', 'announcement',$file->itemid, 'id', false);
                    $download_link = '-';
                    if(!empty($files)){
                        $url = array(); 
                        foreach ($files as $file) {            
                            $isimage = $file->is_valid_image();            
                            $url[] = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $file->get_contextid() . '/' . 'block_announcement' . '/' . 'announcement' .'/'.$file->get_itemid(). $file->get_filepath() . $file->get_filename(), !$isimage);
                        }
                        // $img= "<img id= '' src = '$url[0]'  height='70' width='70'/><br>";
                        // $download_link = \html_writer::link($url[0],)
                        $download_link = "<a href=".$url[0]." download title='".get_string('attachment','block_announcement')."'><i class='fa fa-download'></i></a>";
                    }
                    $row[] = $download_link;

                }else{
                    $row[] = "<p title='".get_string('noattachement','block_announcement')."'> - </p>";
                }
                if(isloggedin() && is_siteadmin($USER->id)){
                    $display_buttons = true;
                }else if(has_capability('block/announcement:manage_announcements',$systemcontext) && $announce->costcenterid==$open_costcenterid && $announce->usermodified==$USER->id){
                    $display_buttons = true;
                }else{
                    $display_buttons = false;
                }
                if($display_buttons){
                    if(!empty($announce->visible)){
                        $visible_value = 0;
                        $status = 'Hide';                       
                        $show_hide_iconimg = html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('t/hide'), 'title' => get_string('hide', 'block_announcement'),'alt' => get_string('hide'), 'class'=>'icon'));
                    }else{
                        $visible_value = 1;
                        $status = 'Show';
                        $show_hide_iconimg = html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('t/show'),'title' => get_string('show', 'block_announcement') ,'alt' => get_string('show'), 'class'=>'icon'));
                    }
                    $buttons[] = html_writer::link($no_redirect_url, $show_hide_iconimg, array('onclick' => '(function(e){ require(\'block_announcement/announcement\').statusConfirm({selector:\'change_status_announcement_modal\', contextid:'.$systemcontext->id.', id:'.$announce->id.', visible:'.$visible_value.',status:"'.$status.'",name:"'.$announce->name.'"}) })(event)'));
                    $buttons[] = html_writer::link($no_redirect_url,
                        html_writer::empty_tag('img', array('src'=>$OUTPUT->image_url('i/edit'), 'title'=>get_string('edit', 'block_announcement'), 'class'=>'icon','onclick'=> '(function(e){ require(\'block_announcement/announcement\').init({selector:\'announcementmodal\', contextid:'.$systemcontext->id.', id:'.$announce->id.'}) })(event)')));
                    $buttons[] = html_writer::link($no_redirect_url, html_writer::empty_tag('img', array('src'=>$OUTPUT->image_url('t/delete'), 'alt'=>'delete', 'class'=>'icon', 'id' => 'deleteannounce_'.$announce->id)), array('title'=>get_string('delete'), 'onclick' => '(function(e){ require(\'block_announcement/announcement\').deleteConfirm({selector:\'delete_announcement_modal\', contextid:'.$systemcontext->id.', id:'.$announce->id.', name:"'.$announce->name.'"}) })(event)'));
                    $row[] = implode(' ', $buttons);
                }else{
                    $row[] = '-';
                }

                $data[] = $row;
            }
            $table = new html_table();
            $table->id = 'table_block_announcement';
            $table->head = array(get_string('subject', 'block_announcement'),
                                 get_string('description'),
                                 get_string('postedby', 'block_announcement'),
                                 get_string('postedon_head', 'block_announcement'),
                                 get_string('attachment', 'block_announcement'),
                                 get_string('actions')
                                 );
            $table->data = $data;
            // $table->size = array('20%', '30%', '10%', '15%', '10%', '10%');
            $table->align = array('left', 'left', 'left', 'left', 'center', 'center');
            $out = html_writer::table($table);
            return $out;
        }else{
            return '<div class="w-full pull-left mt-15 alert alert-info text-center">'.get_string('no_announcements', 'block_announcement').'</div>';
        }
    }

    
    public function announcements_view($courseid,$limit = 0){
        global $DB, $COURSE, $USER, $OUTPUT, $PAGE, $CFG;
        $announcement_lib = new \block_announcement\local\lib();
        $allannouncements = $announcement_lib->announcements($courseid,$limit,true);
        // print_r($allannouncements);die();
        $content = '';
        $return = '';
        // $systemcontext = context_system::instance();
        $systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
        if(is_siteadmin($USER->id) || has_capability('block/announcement:manage_announcements', $systemcontext)){
            
            $return .= html_writer::div("<span><a href='".new moodle_url('/blocks/announcement/announcements.php', array('collapse'=>0))."'>".get_string('manageanno', 'block_announcement')."</a></span>",'cratenew');
        }
        if(!empty($allannouncements)){
            $lib = array();
            $rowdata = '';
            foreach($allannouncements as $announce){
               // print_object($announce)
                $user_name = $DB->get_field_sql("SELECT concat(firstname,' ',lastname) as fullname FROM {user} WHERE id=:id AND confirmed=:confirmed AND deleted=:deleted AND suspended=:suspended", array('id' => $announce->usermodified, 'confirmed' => 1, 'deleted' => 0, 'suspended' => 0));
                $template = '';
    
                $url = new moodle_url('/blocks/announcement/news.php',array('id'=>$announce->id));
                if($announce->name > substr(($announce->name),0,12)){
                    $name = substr($announce->name,0,20).' '.'...';
                }else{
                    $name = $announce->name;
                }
                $More = '<div class="col-md-9 col-sm-9 col-9 pull-left announcement_right"><div class = "info"><div class="annoncement_name" title="'.$announce->name.'">'.html_writer::link($url,$name,array()).'</div>'.
                 '<div class="annonun_created">'.get_string('by',  'block_announcement').' '.$user_name.'</div></div></div>'.
                 "\n";

                if($announce->startdate){
                    $template .= '<div class="col-md-2 col-sm-2 col-2 pull-left p-0 text-center announcedate_container"><span class="announcedate_content"><span class="d-block announceday">'.\local_costcenter\lib::get_userdate("d", $announce->startdate).'</span>
                        <span class="announcemy">'.\local_costcenter\lib::get_userdate("M 'y", $announce->startdate).'</span>
                        </span></div>';
                }else{
                    $template .= '<div class="col-md-2 col-sm-2 col-2 pull-left p-0 text-center announcedate_container"><span class="announcedate_content"><span class="d-block announceday">'.\local_costcenter\lib::get_userdate("d", $announce->timemodified).'</span>
                        <span class="announcemy">'.\local_costcenter\lib::get_userdate("M 'y", $announce->timemodified).'</span>
                        </span></div>';
                }
                $template .= $More;
                if($announce->attachment){
                    $file =$DB->get_record_sql("SELECT * FROM {files} WHERE itemid = $announce->attachment and filename!='.' and component = 'block_announcement' and filearea = 'announcement'");
                    $filedata = get_file_storage();
                    $files = $filedata->get_area_files(isset($file->contextid), 'block_announcement', 'announcement',isset($file->itemid), 'id', false);
                    $download_link = "<div class='col-md-1 col-sm-1 pull-left p-0 text-center mt-10' title='".get_string('noattachement','block_announcement')."'> - </div>";
                    if(!empty($files)){
                        $url = array(); 
                        foreach ($files as $file) {                       
                            $url[] = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $file->get_contextid() . '/' . 'block_announcement' . '/' . 'announcement' .'/'.$file->get_itemid(). $file->get_filepath() . $file->get_filename(), !$isimage);
                        }
                        $download_link = "<div class='col-md-1 col-sm-1 col-1 pull-left p-0 text-center mt-10'><a href=".$url[0]." download title='".get_string('attachment','block_announcement')."'><i class='fa fa-download'></i></a></div>";
                    }
                    $template .= $download_link;

                }else{
                    $template .= "<div class='col-md-1 col-sm-1 col-1  pull-left p-0 text-center pull-right mt-10' title='".get_string('noattachement','block_announcement')."'> - </div>";
                }
                $readycell = new html_table_cell();
                $readycell->text = $template;
                $readycell->attributes['class'] = 'activeannouncements col-md-12 col-sm-12 col-12 pull-left p-0';
                $rowdata .= html_writer::div( $template, 'activeannouncements col-md-12 col-sm-12 col-12 pull-left p-0');

            }
            $content .= html_writer::div($rowdata, 'fullannouncement');
            $return .= html_writer::div($content, 'totalannouncements');
          // echo count($allannouncements);

            //get total count
            $allannouncemetstotal = $announcement_lib->announcements_count($courseid,$limit,true);
            
            if($allannouncemetstotal > 2){

            /*if(count($allannouncements) > 3){ */           
                $return .= html_writer::div("<span><a href='".new moodle_url('/blocks/announcement/announcements.php', array('collapse'=>1))."'>".get_string('viewmore', 'block_announcement')."</a></span>",'technicalsupport');
            }    
        }else{
            
            $return .= '<div class="w-full pull-left mt-15 alert alert-info text-center">'.get_string('no_announcements', 'block_announcement').'</div>';
            
            
        }
        
        return $return;
    }
}





