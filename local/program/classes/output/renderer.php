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
 * @package Bizlms 
 * @subpackage local_program
 */
namespace local_program\output;
require_once($CFG->dirroot . '/local/program/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
defined('MOODLE_INTERNAL') || die;

if (file_exists($CFG->dirroot . '/local/includes.php')) {
    require_once($CFG->dirroot . '/local/includes.php');
}

use html_table;
use html_writer;
use local_program\program;
use plugin_renderer_base;
use user_course_details;
use moodle_url;
use stdClass;
use single_button;
use tabobject;
use core_completion\progress;

class renderer extends plugin_renderer_base {
    /**
     * [render_program description]
     * @method render_program
     * @param  \local_program\output\program $page [description]
     * @return [type]                                  [description]
     */
    public function render_program(\local_program\output\program $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_program/program', $data);
    }
    /**
     * [render_form_status description]
     * @method render_form_status
     * @param  \local_program\output\form_status $page [description]
     * @return [type]                                    [description]
     */
    public function render_form_status(\local_program\output\form_status $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_program/form_status', $data);
    }
    /**
     * [render_session_attendance description]
     * @method render_session_attendance
     * @param  \local_program\output\session_attendance $page [description]
     * @return [type]                                           [description]
     */
    public function render_session_attendance(\local_program\output\session_attendance $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_program/session_attendance', $data);
    }
    /**
     * Display the program tabs
     * @return string The text to render
     */
    public function get_program_tabs($stable,$selectedprogram= null,$selectedstatus= null,$view_type= 'card') {

        global $CFG, $OUTPUT,$DB;

        $stable = clone $stable;
        $stable->thead = true;
        $stable->start = 0;
        $stable->length = -1;
        $stable->search = '';

        $programscontent = $this->viewprograms($stable,$selectedprogram,$selectedstatus,$view_type);
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

        $programtabslist = [
            'programslist' => $programscontent,
            'contextid' => $categorycontext->id,
            'plugintype' => 'local',
            'plugin_name' =>'program',
            'is_siteadmin' => ((has_capability('local/program:manageprogram',
            $categorycontext)) || is_siteadmin()) ? true : false,
            'creataprogram' => ((has_capability('local/program:manageprogram',
            $categorycontext) && has_capability('local/program:createprogram',
            $categorycontext)) || is_siteadmin()) ? true : false,
        ];
        if ((has_capability('local/location:manageinstitute', $categorycontext) || has_capability('local/location:viewinstitute', $categorycontext)) && (has_capability('local/program:manageprogram', $categorycontext))) {
            $programtabslist['location_url'] = $CFG->wwwroot . '/local/location/index.php?components=program';

        }
        if(is_siteadmin() || has_capability('local/request:approverecord',$categorycontext) ){
            $programtabslist['request_url'] = $CFG->wwwroot . '/local/request/index.php?component=program';
        }
        if(is_siteadmin() ||(
        has_capability('local/program:createprogram', $categorycontext)|| has_capability('local/program:updateprogram', $categorycontext)||has_capability('local/program:manageprogram', $categorycontext))){
            $sql = "SELECT id,name FROM {block_learnerscript} WHERE category = 'local_program'";
            $programreports = $DB->get_records_sql($sql);
           foreach ($programreports as $program) { 
            $programtabslist['reports'][] = ['programid' => $program->id, 'name' => $program->name];
        }
      }
        return $this->render_from_template('local_program/programtabs', $programtabslist);
    }
    /**
     * [viewprograms description]
     * @method viewprograms
     * @param  [type]         $stable [description]
     * @return [type]                 [description]
     */
    public function viewprograms($stable,$program = null,$status = null,$view_type='card') {

        global $OUTPUT, $CFG, $DB;

        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
        if (file_exists($CFG->dirroot . '/local/includes.php')) {
            require_once($CFG->dirroot . '/local/includes.php');
            $includes = new user_course_details();
        }
        if ($stable->thead) {
            $table_data = array();
            $programs = (new program)->programs($stable,$request = false,$program,$status);
            if ($programs['programscount'] > 0) {
                $table = new html_table();
                if($view_type == 'card'){
                  $table->head = array('', '','');
                  $table->id = 'viewprograms';
                }else{
                  $table->head = array(get_string('programname','local_program')/*,get_string('stream','local_program')*/, get_string('levels','local_program'), get_string('enrolled','local_program'),get_string('completed','local_program'), get_string('actions','local_program'));
                  $table->id = 'viewprograms_table';
                }
                    $table->data = $table_data;
                    $return = html_writer::table($table);
            } else {
                $return = "<div class='alert alert-info text-center'>" .
                        get_string('noprograms', 'local_program') . "</div>";
            }
        } else {
           
            $programs = (new program)->programs($stable,$request =false,$program,$status);
            $data = array();
            $table_data = array();
            $programchunks = array_chunk($programs['programs'], 3);
            foreach ($programchunks as $bc_data) {
                $row = [];
              foreach ($bc_data as $sdata) {
                    $programcontext = (new \local_program\lib\accesslib())::get_module_context($sdata->id);
                    $line = array();
                    $program = $sdata->name;
                    $programname = strlen($program) > 19 ? substr($program, 0, 19) . "..." : $program;
                    $description = \local_costcenter\lib::strip_tags_custom(html_entity_decode($sdata->description));

                    $isdescription = '';
                    if (empty($description)) {
                        $isdescription = false;
                    } else {
                        $isdescription = true;
                        if (strlen($description) > 130) {
                            $decsriptionCut = substr($description, 0, 130);
                            $decsriptionstring = \local_costcenter\lib::strip_tags_custom(html_entity_decode($decsriptionCut));
                        } else{
                            $decsriptionstring = "";
                        }
                    }

                    if ($sdata->stream) {
                        $stream =  $DB->get_field('local_program_stream', 'stream',
                            array('id' => $sdata->stream));
                    }
                    $streamname = strlen($stream) > 15 ? substr($stream, 0, 15) . ".." : $stream;
                    $level = $DB->count_records('local_program_levels',
                            array('programid' =>$sdata->id));
                    $line['program'] = addslashes($program);
                    $line['programname'] = $programname;
                    //$line['stream'] = $stream;
                    //$line['streamname'] = $streamname;
                    $line['totallevels'] = $level;
                    $line['programicon'] = $OUTPUT->image_url('program_icon', 'local_program');
                    $line['description'] =  \local_costcenter\lib::strip_tags_custom(html_entity_decode($sdata->description));
                    $line['descriptionstring'] = $decsriptionstring;
                    $line['isdescription'] = $isdescription;
                    $line['enrolled_users'] = $sdata->enrolled_users;
                    $line['completed_users'] = $sdata->completed_users;
                    $line['programid'] = $sdata->id;
                    $line['editicon'] = $OUTPUT->image_url('t/edit');
                    $line['deleteicon'] = $OUTPUT->image_url('t/delete');
                    $line['assignusersicon'] = $OUTPUT->image_url('t/assignroles');
                    $line['programcompletion'] = false;
                
                    $mouseovericon = false;
                    if ((has_capability('local/program:manageprogram', $programcontext) || is_siteadmin())) {
                        $line['action'] = true;
                    }
                    if ((has_capability('local/program:editprogram', $programcontext) || is_siteadmin())) {
                        $line['edit'] = true;
                        $mouseovericon = true;
                    }

                    if ((has_capability('local/program:deleteprogram', $programcontext) || is_siteadmin())) {
                        $count_records = $DB->get_records('local_bc_session_signups', array('programid'=>$sdata->id));
                        if(count($count_records) > 0) {
                            $line['cannotdelete'] = true;
                            $mouseovericon = true;
                        } else {
                            $line['delete'] = true;
                            $mouseovericon = true;
                        }
                    }

                    if (is_siteadmin() || (has_capability('local/program:inactiveprogram', $programcontext) || (has_capability('local/program:activeprogram', $programcontext)))) {
                         $line['hide_show'] = true;
                         $mouseovericon = true;
                    }
                    if ((has_capability('local/program:manageusers', $categorycontext) || is_siteadmin())) {
                        $line['assignusers'] = true;
                        $line['assignusersurl'] = new moodle_url("/local/program/enrollusers.php?bcid=" . $sdata->id . "");
                        $mouseovericon = true;
                    }
                    $completionstatus = $DB->get_field('local_program_users', 'completion_status', array('programid'=>$sdata->id, 'userid'=>$USER->id));
                    if($completionstatus == 1){
                        $line['programcompletionstatus'] = true;
                    } else {
                        $line['programcompletionstatus'] = false;

                    }
                    $line['programcompletion_id'] = $programcompletion_id;
                   
                    if($sdata->visible==1&&has_capability('local/program:inactiveprogram', $programcontext)){
                        $line['hide'] = true;
                    }elseif(has_capability('local/program:activeprogram', $programcontext)){
                        $line['show'] = true;
                    }
                    
                    $line['mouse_overicon'] = $mouseovericon;
                    $programnames = addslashes($programname);

                    if($line['action']){
                        if($line['cannotdelete']){
                          $actions = '<a href="javascript:void(0);" title = ' .get_string('delete','local_program'). ' onclick="(function(e){ require(\'local_program/program\').deleteConfirm({action:\'cannotdeleteprogram\', id: '.$sdata->id.',programid: '.$sdata->id.',programname:\''.$programnames.'\'}) })(event)" ><i class="fa fa-trash fa-fw" aria-hidden="true"></i></a>';
                         }
                    
                         if($line['delete']){
                           $actions = '<a href="javascript:void(0);" title = ' .get_string('delete','local_program'). ' onclick="(function(e){ require(\'local_program/program\').deleteConfirm({action:\'deleteprogram\', id: '.$sdata->id.',programid: '.$sdata->id.',programname:\''.$programnames.'\'}) })(event)" ><i class="fa fa-trash fa-fw" aria-hidden="true"></i></a>';
                        }

                       if($line['edit']){
                         $actions .=  '<a href="javascript:void(0);" title = ' .get_string('edit','local_program'). ' onclick="(function(e){ require(\'local_program/ajaxforms\').init({ contextid:1,component:\'local_program\',callback:\'program_form\',plugintype:\'local\',pluginname:\'program\', id: '.$sdata->id.', form_status:0 }) })(event)" ><i class="fa fa-pencil fa-fw" aria-hidden="true"></i></a>' ;
                       }
                       if($line['hide_show']){

                         if($line['hide']){
                             $actions .= '<a href="javascript:void(0);" title = ' .get_string('inactive','local_program'). ' onclick="(function(e){ require(\'local_program/program\').deleteConfirm({action:\'inactiveprogram\', id: '.$sdata->id.',programid: '.$sdata->id.',programname:\''.$programnames.'\'}) })(event)" ><i class="fa fa-eye fa-fw" aria-hidden="true"></i></a>'; 
                         }
                         if($line['show']){
                             $actions .= '<a href="javascript:void(0);" title = ' .get_string('active','local_program'). ' onclick="(function(e){ require(\'local_program/program\').deleteConfirm({action:\'activeprogram\', id: '.$sdata->id.',programid: '.$sdata->id.',programname:\''.$programnames.'\'}) })(event)" ><i class="fa fa-eye-slash" aria-hidden="true"></i></a>'; 
                         }
                       }
                    }
                     if($view_type == 'card'){
                      $row[] = $this->render_from_template('local_program/browseprogram', $line);

                    }else{
                      $row = [html_writer::tag('a', $programname, array('href' => $CFG->wwwroot. '/local/program/view.php?bcid='.$sdata->id)), $level , $sdata->enrolled_users,$sdata->completed_users, $actions];
                    }
                     $table_data[] = $row;

                }
               
                if (!isset($row[1])) {
                    $row[1] = '';
                    $row[2] = '';
                } else if (!isset($row[2])) {
                    $row[2] = '';
                }
                $data[] = $row;
              
            }
            if($view_type == 'card'){
            $return = array(
                "recordsTotal" => $programs['programscount'],
                "recordsFiltered" => $programs['programscount'],
                "data" => $data
            );

            }else{
                $return = array(
                "recordsTotal" => $programs['programscount'],
                "recordsFiltered" => $programs['programscount'],
                "data" => $table_data
            ); 
            
          }
          
        }
        return $return;
    }
    /**
     * [viewprogramsessions description]
     * @method viewprogramsessions
     * @param  [type]                $bclcid [description]
     * @param  [type]                $stable      [description]
     * @return [type]                             [description]
     */
    public function viewprogramsessions($bclcdata, $stable, $userview = false, $enrolmentpending = false,$tab=null) {
        global $OUTPUT, $CFG, $DB, $USER;
        $programid = $bclcdata->programid;
        $levelid = $bclcdata->levelid;
        $bclcid = $bclcdata->bclcid;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($bclcdata->programid);


        $program = $DB->get_record('local_program', ['id' => $bclcdata->programid]);

        $manage = true;

        if(!(is_siteadmin() || has_any_capability(['local/program:manageprogram'],$categorycontext))){
            $manage = false;
        }
        $programcompletionstatus = $DB->get_field('local_program_users', 'completion_status', array('programid' => $bclcdata->programid, 'userid' => $USER->id));
        if ($stable->thead) {
            $return = '';
            $lc_course = $DB->get_record('local_program_level_courses', array('id' => $bclcid));
            if (has_capability('local/program:createsession', $categorycontext) && (has_capability('local/program:manageprogram', $categorycontext)) && $manage) {
                $return .= '<ul class="course_extended_menu_list">
                                <li>
                                    <div class="createicon course_extended_menu_itemlink">
                                        <a class="create_session createpopicon" title="'.get_string('addsession', 'local_program').'" onclick="(function(e){ require(\'local_program/ajaxforms\').init({contextid:' . $categorycontext->id . ', component:\'local_program\', callback:\'session_form\', form_status:0, plugintype: \'local_program\', pluginname: \'session\', id:0, bcid: ' . $programid . ', levelid: '. $levelid .',  bclcid: ' . $bclcid . ', title: \'addsession\' }) })(event)">
                                            <i class="fa fa-plus icon" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </li>
                            </ul>';
            }
            $sessions = (new program)->programsessions($bclcdata, $stable, $userview,$tab);
            if ($sessions['sessionscount'] > 0) {
                $table = new html_table();
                $table->head = array(get_string('name'), get_string('startdatetime', 'local_program'));
                $table->head[] = get_string('enddatetime', 'local_program');
                $table->head[] = get_string('room', 'local_program');
                if ((has_capability('local/program:manageprogram', $categorycontext) || is_siteadmin() || has_capability('local/program:takesessionattendance', $categorycontext))) {
                    $table->head[] = get_string('status', 'local_program');
                    $table->head[] = get_string('attended_sessions_users', 'local_program');
                }
                $table->head[] = get_string('faculty', 'local_program');
                if ($userview) {
                    $table->head[] = get_string('seats', 'local_program');
                }

                if ((($userview && !$enrolmentpending) || ($userview && !$programcompletionstatus)) || (has_capability('local/program:manageprogram', $categorycontext) || is_siteadmin() || has_capability('local/program:takesessionattendance', $categorycontext))) {
                    $table->head[] = get_string('actions');
                }
                $table->id = 'viewprogramsessions';
                $table->attributes['data-bclcid'] = $bclcid;
                $return .= html_writer::table($table);
            } else {
                $return .= "<div class='mt-15 text-center alert alert-info w-full pull-left'>" . get_string('nosessions', 'local_program') . "</div>";
            }
        } else {
            $programuser = $DB->record_exists('local_program_users', array('programid' => $bclcdata->programid, 'userid' => $USER->id));
            $userview = false;
            if ($programuser && !is_siteadmin() && !has_capability('local/program:createprogram', $categorycontext)) {
                $bclevel = new stdClass();
                $bclevel->programid = $bclcdata->programid;
                $bclevel->levelid = $bclcdata->levelid;
                $notcmptlcourses = (new program)->mynextlevelcourses($bclevel);
                unset($notcmptlcourses[0]);
                $enrolmentpending = false;
                if (!empty($notcmptlcourses) && array_search($bclcid, $notcmptlcourses) !== false) {
                    $enrolmentpending = true;
                }

                $lastcompletiondate = $DB->get_field('local_bc_level_completions',
                    'completiondate', array('programid' => $bclcdata->programid,
                        'levelid' => $bclcdata->levelid, 'userid' => $USER->id));

                $mylastattendedsession = (new program)->myattendedsessions($bclcdata, true);
                if (empty($mylastattendedsession)) {
                    $mylastattendedsession = new stdClass();
                    $mylastattendedsession->timefinish = 0;
                }
                $userview = true;
            }
            $sessions = (new program)->programsessions($bclcdata, $stable, $userview, $tab);
            $data = array();
            $absentsessions = false;
            if ($programuser && !is_siteadmin() && !has_capability('local/program:createprogram', $categorycontext)) {
                $absentsessions = $DB->count_records('local_bc_session_signups',
                    array('programid' => $bclcdata->programid,
                        'levelid' => $bclcdata->levelid, 'bclcid' => $bclcdata->bclcid,
                        'userid' => $USER->id, 'completion_status' => 2));
                $presentsessions = $DB->count_records('local_bc_session_signups',
                    array('programid' => $bclcdata->programid,
                        'levelid' => $bclcdata->levelid, 'bclcid' => $bclcdata->bclcid,
                        'userid' => $USER->id, 'completion_status' => 1));
                $activesessions = $DB->count_records('local_bc_session_signups',
                    array('programid' => $bclcdata->programid,
                        'levelid' => $bclcdata->levelid, 'bclcid' => $bclcdata->bclcid,
                        'userid' => $USER->id, 'completion_status' => 0));
            }

            foreach ($sessions['sessions'] as $sdata) {
                $line = array();
                $line[] = $sdata->name;
                $line[] = '<i class="fa fa-calendar" aria-hidden="true"></i> ' .
                            \local_costcenter\lib::get_userdate("d/m/Y H:i", $sdata->timestart);
                $line[] =  '<i class="fa fa-calendar" aria-hidden="true"></i> ' .
                            \local_costcenter\lib::get_userdate("d/m/Y H:i", $sdata->timefinish);
                $link = get_string('pluginname', 'local_program');
                if ($sdata->onlinesession == 1) {
                    $moduleids = $DB->get_field('modules', 'id',
                        array('name' => $sdata->moduletype));
                    if ($moduleids) {
                        $moduleid = $DB->get_field('course_modules', 'id',
                                array('instance' => $sdata->moduleid, 'module' => $moduleids));
                        if ($moduleid) {
                            $link = html_writer::link($CFG->wwwroot . '/mod/' .$sdata->moduletype. '/view.php?id=' . $moduleid,
                                    get_string('join', 'local_program'),
                                    array('title' => get_string('join', 'local_program')));
                            if (!is_siteadmin() && !has_capability('local/program:manageprogram', $categorycontext)) {
                                $userenrolstatus = $DB->record_exists('local_program_users', array('programid' => $sdata->programid, 'userid' => $USER->id));
                                if (!$userenrolstatus) {
                                    $link = get_string('join', 'local_program');
                                }
                            }
                        }
                    }
                }
                $line[] = $sdata->room ? $sdata->room : 'N/A';

                $program_totalusers = $DB->count_records('local_bc_session_signups',
                    array('bclcid' => $bclcid, 'sessionid' => $sdata->id));
                if ((has_capability('local/program:manageprogram', $categorycontext) || is_siteadmin())) {
                    $attendedsessions_users = $DB->count_records('local_bc_session_signups',
                        array('bclcid' => $bclcid, 'sessionid' => $sdata->id,
                            'completion_status' => SESSION_PRESENT));
                }
                if (has_capability('local/program:manageprogram', $categorycontext) || has_capability('local/program:takesessionattendance', $categorycontext)) {
                    if ($sdata->timefinish <= time() && $sdata->attendance_status == 1) {
                        $line[] = get_string('completed', 'local_program');
                    } else {
                        $line[] = get_string('pending', 'local_program');
                    }
                } else {
                    // if ($sdata->timefinish <= time() && $attendance_status == 1) {
                    //     $line[] = get_string('completed', 'local_program');
                    // } else {
                    //     $line[] = get_string('pending', 'local_program');
                    // }
                }
                if ((has_capability('local/program:manageprogram', $categorycontext) || is_siteadmin())) {
                    $line[] = $sdata->activeusers . '/' . $sdata->totalusers;
                }
                if ($sdata->trainerid) {
                    $trainer = $DB->get_record('user', array('id' => $sdata->trainerid));
                    $line[] = $OUTPUT->user_picture($trainer, array('size' => 30)) . fullname($trainer);
                } else {
                    $line[] = "N/A";
                }
                if ($userview) {
                    $line[] = $sdata->signups . ' / ' . $sdata->maxcapacity;
                }
                $action = '';
                if ((has_capability('local/program:editsession', $categorycontext) || is_siteadmin())&&(has_capability('local/program:manageprogram', $categorycontext))) {
                    $action .= '<a href="javascript:void(0);" alt = ' . get_string('edit') . ' title = ' . get_string('edit') . ' onclick="(function(e){ require(\'local_program/ajaxforms\').init({contextid:1, component:\'local_program\', callback:\'session_form\', form_status:0, plugintype: \'local_program\', pluginname: \'session\', id: ' . $sdata->id . ', bcid: ' . $programid .', levelid: '. $levelid .', bclcid: '.$bclcid.', title: \'updatesession\'}) })(event)" ><img src="' . $OUTPUT->image_url('i/edit') . '" alt = ' . get_string('edit') . ' title = ' . get_string('edit') . ' class="icon"/></a>';
                }
                if ((has_capability('local/program:deletesession', $categorycontext)
                    || is_siteadmin())&&(has_capability('local/program:manageprogram', $categorycontext))) {
                    $count_records = $DB->get_records('local_bc_session_signups',
                        array('sessionid' => $sdata->id));
                    if (count($count_records) > 0) {
                         $action .= '<a href="javascript:void(0);" alt = ' . get_string('delete') . ' title = ' . get_string('delete') . ' onclick="(function(e){ require(\'local_program/program\').deleteConfirm({action:\'cannotdeletesession\', programid: ' . $sdata->programid . ', levelid: '.$levelid.', bclcid: '.$bclcid.', id: ' . $sdata->id . ' }) })(event)" ><img src="' . $OUTPUT->image_url('i/trash') . '" alt = ' . get_string('delete') . ' title = ' . get_string('delete') . ' class="icon"/></a>';
                    } else {
                         $action .= '<a href="javascript:void(0);" alt = ' . get_string('delete') . ' title = ' . get_string('delete') . ' onclick="(function(e){ require(\'local_program/program\').deleteConfirm({action:\'deletesession\', programid: ' . $sdata->programid . ', levelid: '.$levelid.', bclcid: ' . $bclcid . ', id: ' . $sdata->id . ' }) })(event)" ><img src="' . $OUTPUT->image_url('i/trash') . '" alt = ' . get_string('delete') . ' title = ' . get_string('delete') . ' class="icon"/></a>';
                    }
                }
                if(has_capability('local/program:manageusers', $categorycontext) || is_siteadmin()) {
                    $action .= '<a href="' . $CFG->wwwroot . '/local/program/session_enrolusers.php?bcid=' . $sdata->programid . '&levelid=' . $levelid . '&bclcid=' . $bclcid . '&sid=' . $sdata->id . '"><img src="' . $OUTPUT->image_url('t/groups') . '"  alt = "'.get_string('sessionenrolments', 'local_program').'"  title = "'.get_string('sessionenrolments', 'local_program').'" class="icon"/></a>';
                }
                if ((has_capability('local/program:takesessionattendance', $categorycontext) || is_siteadmin())&&(has_capability('local/program:manageprogram', $categorycontext))) {
                    $action .= '<a href="' . $CFG->wwwroot . '/local/program/attendance.php?bcid=' . $sdata->programid . '&sid=' . $sdata->id . '&action='.$tab.'&levelid='.$levelid.'&bclcid='.$bclcid.'" ><img src="' . $OUTPUT->image_url('t/assignroles') . '" alt = ' . get_string('attendace', 'local_program') . ' title = ' . get_string('attendace', 'local_program') . ' class="icon"/></a>';
                }
                if ((has_capability('local/program:editsession', $categorycontext) || has_capability('local/program:deletesession', $categorycontext) || has_capability('local/program:takesessionattendance', $categorycontext)) && (has_capability('local/program:manageprogram', $categorycontext))) {
                    $line[] = $action;
                } else {
                    if ((($userview && !$enrolmentpending) || ($userview && !$programcompletionstatus))) {
                        $sessiondate = date_create(date('d/m/Y', $sdata->timestart));
                        $currentdate = date_create(date('d/m/Y'));
                        $diff = date_diff( $sessiondate, $currentdate );
                        $sessionactionstatus = true;
                        if($sdata->sessiondays > 0){
                            if ($diff->days < $sdata->sessiondays) {
                                $sessionactionstatus = false;
                            }
                        }
                        // if ($diff->days < 3) {
                        //     $sessionactionstatus = false;
                        // }
                        if ($enrolmentpending) {
                            $line[] = 'Enrolment Not Yet Started.';
                        } else if (time() > $sdata->timefinish && $sdata->signupid == 0) {
                            $line[] = 'Closed';
                        } else if ($sdata->attendance_status && $sdata->completion_status == 2) {
                            $line[] = 'Absent';
                        } else if ($sdata->signupid > 0 && $sdata->completion_status == 1) {
                            $line[] = 'Present';
                        } else if (time() < $sdata->timestart && $sdata->signupid > 0 && $sdata->mysignupstatus > 0 && $presentsessions == 0 /*$sdata->attendance_status == 0 &&*/ && $sessionactionstatus && $programcompletionstatus == 0) {
                            $line[] = '<a href="javascript:void(0);" class="sessionenrol" data-programid='.$sdata->programid.' data-levelid='.$sdata->levelid.' data-bclcid='.$bclcid.' data-sessionid='.$sdata->id.' data-signupid='.$sdata->signupid.' data-enrol=3 >Cancel</a>';
                        } else if ($sdata->mysignupstatus > 0 && !$sdata->signupid /*&& $sdata->attendance_status == 0*/ && $presentsessions == 0 && $activesessions > 0 && $programcompletionstatus == 0 && ($sdata->timestart > $mylastattendedsession->timefinish) && $sessionactionstatus) {
                            $line[] = '<a href="javascript:void(0);" class="sessionenrol" data-programid='.$sdata->programid.' data-levelid='.$sdata->levelid.' data-bclcid='.$bclcid.' data-sessionid='.$sdata->id.' data-signupid=0 data-enrol=2 >Re Schedule</a>';
                        } else if ((($sdata->signups < $sdata->maxcapacity) && ($sdata->timestart > $mylastattendedsession->timefinish) && $sdata->signupid == 0 && $presentsessions == 0 && $programcompletionstatus == 0 && $sessionactionstatus) && ($sdata->mysignupstatus == 0 || $absentsessions)) {
                            $line[] = '<a href="javascript:void(0);" class="sessionenrol" data-programid='.$sdata->programid.' data-levelid='.$sdata->levelid.' data-bclcid='.$bclcid.' data-sessionid='.$sdata->id.'
                            data-signupid=0 data-enrol=1 >Enrol</a>';
                        } else if ($presentsessions > 0) {
                            $line[] = 'Already one session completed in this course.';
                        } else if ($sdata->signups == $sdata->maxcapacity && !$sdata->signupid){
                            $line[] = 'Max Seats filled.';
                        } else if ($sdata->signupid) {
                            $line[] = 'Enrolled';
                        } else {
                            $line[] = 'Enrollment Closed';
                        }
                    }
                }
                $data[] = $line;
            }
            $return = array(
                "recordsTotal" => $sessions['sessionscount'],
                "recordsFiltered" => $sessions['sessionscount'],
                "data" => $data
            );
        }
        return $return;
    }

    /**
     * [viewprogramsessionstabs description]
     * @method viewprogramsessions
     * @param  [type]                $bclcid [description]
     * @param  [type]                $stable      [description]
     * @return [type]                             [description]
     */
    public function viewprogramsessionstabs($bclcdata, $stable, $userview = false, $enrolmentpending = false, $tab) {
        global $CFG, $OUTPUT;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($bclcdata->programid);
        $toprow = array();
        if ($tab) {
            $toprow[] = new tabobject('upcomingsessions', new moodle_url("/local/program/sessions.php?action=upcomingsessions&bcid=$bclcdata->programid&levelid=$bclcdata->levelid&bclcid=$bclcdata->bclcid"), get_string('upcomingsessions', 'local_program'));
            $toprow[] = new tabobject('completedsessions', new moodle_url("/local/program/sessions.php?action=completedsessions&bcid=$bclcdata->programid&levelid=$bclcdata->levelid&bclcid=$bclcdata->bclcid"), get_string('completedsessions', 'local_program'));
            
        }
       echo $OUTPUT->tabtree($toprow, $tab);
    }
    
    public function viewprogramlevels($programid, $levelid) {
        global $OUTPUT, $CFG, $DB, $USER;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        $programs = $DB->get_records('local_program');
        foreach($programs AS $program)

         $manage = true;
         if(!(is_siteadmin() || has_any_capability(['local/program:manageprogram'],$categorycontext)) ){
            $manage = false;
         }
        $assign_courses = '';
        $bcuser = $DB->record_exists('local_program_users',
            array('programid' => $programid, 'userid' => $USER->id));
        $userview = $bcuser && !is_siteadmin() && !has_capability('local/program:createprogram', $categorycontext) ? true : false;

        $programlevels = (new program)->program_levels($programid);
        
        if(has_capability('local/program:addcourse', $categorycontext)){
            $addcourse = true;
        }

        if ($userview) {
            $mycompletedlevels = (new program)->mycompletedlevels($programid, $USER->id);
            $notcmptllevels = (new program)->mynextlevels($programid);
            if (!empty($notcmptllevels)) {
                $nextlevel = $notcmptllevels[0];
            } else {
                $nextlevel = 0;
            }
        }
        if (!empty($programlevels)) {
            //$prev_levelid = 0;
            $can_delete_levels = (new program)->levels_completion_status($programid);
            foreach ($programlevels as $k => $programlevel) {
                $activeclass = '';
                $disabled = '';
                $levelname = strlen($programlevel->level) > 11 ? substr($programlevel->level, 0, 11) ."<span class='levelrestrict'>..</span>": $programlevel->level;

                $programlevel->level = "<span title='".$programlevel->level."'>".$levelname."</span>";
                if ($programlevel->id == $levelid) {
                    $activeclass = 'active';
                }
                if ($userview && !is_siteadmin() && !has_capability('local/program:createprogram', $categorycontext) && array_search($programlevel->id, $mycompletedlevels) === false
                    && $nextlevel != $programlevel->id) {
                    $disabled = 'disabled';
                }
                $programlevel->mycompletionstatus = '';
                if ($userview && array_search($programlevel->id, $mycompletedlevels) !== false) {
                    $programlevel->mycompletionstatus = 'Completed';
                }

                $session = $DB->get_record('local_bc_session_signups', array('programid'=>$programid, 'levelid'=>$programlevel->id, 'completion_status'=>0));
                $programlevel->myinprogressstatus = '';
                if ($userview && array_search($programlevel->id, $mycompletedlevels) === false && !empty($session)) {
                    $programlevel->myinprogressstatus = 'Inprogress';
                }

                $programlevel->active = $activeclass;
                $programlevel->disabled = $disabled;
                $levelcount_records = $DB->get_records('local_bc_session_signups',
                array('programid' => $programid, 'levelid' => $programlevel->id));
                $candeletelevel = false;
                $editlevel = '';
                // $program_level_completetions = (new program)->levels_completion_status($programid);
                // if($prev_levelid){
                //     $level_completions = (new program)->levels_completion_status($programid, $prev_levelid) 
                // }
                if($can_delete_levels){
                    if ((count($levelcount_records) > 0 && has_capability('local/program:deletelevel',
                        $categorycontext))) {
                        $candeletelevel = false;
                    } else if (has_capability('local/program:deletelevel', $categorycontext)) {
                        $candeletelevel = true;
                    }
                }
                
                if(has_capability('local/program:createlevel',$categorycontext)){
                    isset($createlevel);
                }
                if(!(is_siteadmin() || has_capability('local/program:createlevel',$categorycontext))) {
                    ;
                    unset($createlevel);
                }


                if(has_capability('local/program:editlevel',$categorycontext)){
                    $editlevel = true;
                }
                if(!(is_siteadmin() || has_capability('local/program:editlevel',$categorycontext))) {
                    ;
                    $editlevel = false;
                }

                $programlevel->candeletelevel = $candeletelevel;
                $programlevels[$k] = $programlevel;
                //$prev_levelid = $k;
            }
        }

        $programlevelscontext = [
            'contextid' => $categorycontext->id,
            'programid' => $programid,
            //'cancreatelevel' => has_capability('local/program:createlevel', $categorycontext),
            'cancreatelevel' => isset($createlevel),
            'canviewlevel' => has_capability('local/program:viewlevel', $categorycontext),
            //'caneditlevel' => has_capability('local/program:editlevel', $categorycontext),
            'caneditlevel' => $editlevel,
            'canaddcourse' => has_capability('local/program:addcourse', $categorycontext),
            'caneditcourse' => has_capability('local/program:editcourse', $categorycontext),
            'canmanagecourse' => has_capability('local/program:managecourse', $categorycontext),
            'candeletelevel' => $candeletelevel,
            'cancreatesession' => (has_capability('local/program:createsession', $categorycontext) && $manage),
            'canenrolsession' => has_capability('local/program:enrolsession', $categorycontext) && !is_siteadmin(),
            'cfg' => $CFG,
            'levelid' => $levelid,
            'cantakeattendance' => has_capability('local/program:takesessionattendance',
                $categorycontext) && !is_siteadmin(),
            'programlevel' => $programlevel,
            'userview' => $userview,
            'programlevels' => array_values($programlevels),
            'levelcourses' => $this->viewprogramcourses($programid, $levelid)
        ];
        $return = $this->render_from_template('local_program/levelstab_content',
            $programlevelscontext);
        return $return;
    }
    /**
     * [viewprogramcourses description]
     * @method viewprogramcourses
     * @param  [type]               $programid [description]
     * @return [type]                            [description]
     */
    public function viewprogramcourses($programid, $levelid) {
        global $OUTPUT, $CFG, $DB, $USER;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        $programs = $DB->get_records('local_program');
        foreach($programs AS $program){

        $manage = true;
        
        if(!(is_siteadmin() || has_any_capability(['local/program:manageprogram'], $categorycontext))){
                $manage = false;
                
        }
        $assign_courses = '';
        $bcuser = $DB->record_exists('local_program_users',
            array('programid' => $programid, 'userid' => $USER->id));
        $userview = $bcuser && !is_siteadmin() && !has_capability('local/program:createprogram', $categorycontext) ? true : false;
        $bclevel = new stdClass();
        $bclevel->programid = $programid;
        $bclevel->levelid = $levelid;

        if ($userview) {
            $mycompletedlevels = (new program)->mycompletedlevels($programid, $USER->id);
            $notcmptllevels = (new program)->mynextlevels($programid);
            if (!empty($notcmptllevels)) {
                $nextlevel = $notcmptllevels[0];
            } else {
                $nextlevel = 0;
            }
        }
        $programlevelcourses =
            (new program)->program_level_courses($programid, $levelid, $userview);
        if ($userview) {
            $notcmptlcourses = (new program)->mynextlevelcourses($bclevel);
        }
        $programlevel = $DB->get_record('local_program_levels', array('programid' => $programid, 'id' => $levelid));

        $programlevel->mycompletionstatus = '';
        if ($userview && array_search($programlevel->id, $mycompletedlevels) !== false) {
            $programlevel->mycompletionstatus = 'Completed';
        }
        $session = $DB->get_record('local_bc_session_signups', array('programid'=>$programid, 'levelid'=>$programlevel->id, 'completion_status'=>0));
        $programlevel->myinprogressstatus = '';
        if ($userview && array_search($programlevel->id, $mycompletedlevels) === false && !empty($session)) {
            $programlevel->myinprogressstatus = 'Inprogress';
        }

        foreach ($programlevelcourses as $i => $bclevelcourse) {
            // print_object($bclevelcourse);exit;
            $bclevelcourses = array();
            $coursecontext = \context_course::instance($bclevelcourse->id);
           
            if(is_enrolled($coursecontext, $USER)){
                $courseurl = new \moodle_url('/course/view.php', array('id' => $bclevelcourse->id));
            }else{
                $courseurl = new \moodle_url('/local/program/sessions.php', array('bclcid' => $bclevelcourse->bclevelcourseid, 'levelid' => $bclevelcourse->levelid, 'bcid' => $bclevelcourse->programid));
            }
            $courselink = strlen($bclevelcourse->course) > 25 ? substr($bclevelcourse->course, 0, 25) . "..." : $bclevelcourse->course;
            $bclevelcourse->course = html_writer::link($courseurl, $courselink,
                    array('title' => $bclevelcourse->course));
            if ($userview) {
                $bclevelcourse->sessionenabled = true;
                if (array_search($bclevelcourse->bclevelcourseid, $notcmptlcourses) !== false) {
                    $bclevelcourse->sessionenabled = false;
                    $bclevelcourse->coursecompletionstatus = '';
                } else {
                    $bclevelcourse->coursecompletionstatus = 'Completed';
                }
            }

            $programlevelcourses[$i] = $bclevelcourse;
            $count_records = $DB->get_records('local_bc_session_signups',
                array('programid' => $programid, 'levelid' => $levelid,
                    'bclcid' => $bclevelcourse->bclevelcourseid));
            $canremovecourse = false;
            $cannotremovecourse = false;
            if (count($count_records) > 0 && has_capability('local/program:removecourse',
                $categorycontext)) {
                $canremovecourse = false;
                $cannotremovecourse = true;
            } else if (has_capability('local/program:removecourse', $categorycontext)) {
                $canremovecourse = true;
                $cannotremovecourse = false;
            }
            $bclevelcourse->canremovecourse = $canremovecourse;
            $bclevelcourse->cannotremovecourse = $cannotremovecourse;
            $programlevelcourses[$i] = $bclevelcourse;
        }
    }
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        
     
        $programcoursescontext = [
            'contextid' => $categorycontext->id,
            'programid' => $programid,
            'canaddcourse' => (has_capability('local/program:addcourse',$categorycontext) && $manage),
            'caneditcourse' => has_capability('local/program:editcourse', $categorycontext),
            'canmanagecourse' => has_capability('local/program:managecourse', $categorycontext),
            'cancreatesession' => (has_capability('local/program:createsession', $categorycontext) && $manage),
            'canenrolsession' => has_capability('local/program:enrolsession', $categorycontext) && !is_siteadmin(),
            'cfg' => $CFG,
            'levelid' => $levelid,
            'cantakeattendance' => has_capability('local/program:takesessionattendance',
                $categorycontext) && !is_siteadmin(),
            'programlevel' => $programlevel,
            'userview' => $userview,
            'programlevelcourses' => array_values($programlevelcourses)
        ];
        $return = $this->render_from_template('local_program/levelcoursescontent',
            $programcoursescontext);
        return $return;
    }
    /**
     * Display the program view
     * @return string The text to render
     */
    public function viewprogram($programid) {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        $stable = new stdClass();
        $stable->programid = $programid;
        $stable->thead = false;
        $stable->start = 0;
        $stable->length = 1;
        $program = (new program)->programs($stable);

        $manage = true;
        if(!(is_siteadmin() || has_any_capability(['local/program:manageprogram'], $categorycontext))){
                $manage = false;
        }

        if (empty($program)) {
            print_error("program Not Found!");
        }
        $includesfile = false;
        if (file_exists($CFG->dirroot . '/local/includes.php')) {
            $includesfile = true;
            require_once($CFG->dirroot . '/local/includes.php');
            $includes = new user_course_details();
        }
        
        if ($program->programlogo > 0) {
            $program->programlogoimg = (new program)->program_logo($program->programlogo);
            if ($program->programlogoimg == false) {
                if($includesfile){
                    $program->programlogoimg = $includes->get_classes_summary_files($program);
                }
            }
        } else {
            if($includesfile){
                    $program->programlogoimg = $includes->get_classes_summary_files($program);
                }
        }

        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$program->open_path);

        if($ctr){
            $department = $DB->get_field_sql('SELECT fullname FROM {local_costcenter} WHERE id IN('.$ctr.')');
            $programdepartment=$department ? $department : get_string('statusna');
        }else{
            $programdepartment =  get_string('all');
        }

         if($bu){
            $subdepartment = $DB->get_field_sql('SELECT fullname FROM {local_costcenter} WHERE id IN('.$bu.')');
            $programsubdepartment=$subdepartment ? $subdepartment : get_string('statusna');
        }else{
            $programsubdepartment =  get_string('all');
        }

        $program->stream = $DB->get_field('local_program_stream', 'stream', array('id' => $program->stream));

        $program->department = $programdepartment;

        $program->subdepartment = $programsubdepartment;
        
        $groups_sql = "SELECT mc.name FROM {cohort} AS mc 
            JOIN {local_groups} AS lg ON lg.cohortid = mc.id 
            WHERE ',{$program->open_group},' LIKE concat('%,',lg.id,',%') ";
        $program->open_group = $program->open_group ? implode(', ', $DB->get_fieldset_sql($groups_sql)): get_string('all');
        $program->open_group_str = strlen($program->open_group) > 15 ? substr($program->open_group, 0, 15).'...': $program->open_group;
        // $program->open_hrmsrole = $program->open_hrmsrole ? $program->open_hrmsrole : get_string('all');
        // $program->open_hrmsrole_str = strlen($program->open_hrmsrole) > 15 ? substr($program->open_hrmsrole, 0, 15).'...': $program->open_hrmsrole;
        $program->open_designation = $program->open_designation ? $program->open_designation : get_string('all'); 
        $program->open_designation_str = strlen($program->open_designation) > 15 ? substr($program->open_designation, 0, 15).'...': $program->open_designation;
        // $program->open_location = $program->open_location ? $program->open_location : get_string('all');
        // $program->open_location_str = strlen($program->open_location) > 15 ? substr($program->open_location, 0, 15).'...': $program->open_location;
        $return = "";
        $bulkenrollusers = '';
        $bulkenrollusersurl = '';

        $programcompletion = $user_tab = $course_tab = $session_tab = $action = $edit = $delete = $assignusers = $assignusersurl = false;
            $session_tab = false;
            $course_tab = true;
        if (has_capability('local/program:viewusers', $categorycontext)) {
            $user_tab = true;
        }
        if ((has_capability('local/program:manageprogram', $categorycontext) || is_siteadmin())) {
            $action = true;
        }

        
        if ((has_capability('local/program:programcompletion', $categorycontext) || is_siteadmin())) {
            $programcompletion = false;
        }
        if ((has_capability('local/program:editprogram', $categorycontext) || is_siteadmin())) {
            $edit = true;
        }

        $unenrolbutton = $this->get_self_unenrollment_button($programid, $program->name);
        if(!is_null($unenrolbutton)){
            $action = true;
        }
        $cannotdelete = true;
        $delete = false;
        if ((has_capability('local/program:deleteprogram', $categorycontext) || is_siteadmin())) {
            $count_records = $DB->get_records('local_bc_session_signups', array('programid' => $programid));
            if (count($count_records) > 0) {
                $cannotdelete = true;
                $delete  = false;
            } else {
                $cannotdelete = false;
                $delete = true;
            }
        }
       
        if ((has_capability('local/program:manageusers', $categorycontext) || is_siteadmin())) {
            $assignusers = true;
            $assignusersurl = new moodle_url("/local/program/enrollusers.php?bcid=" .
                $programid . "");
            $bulkenrollusers = true;
            $bulkenrollusersurl = new moodle_url("/local/program/mass_enroll.php?bcid=" .
                $programid . "");
        }
        $selfenrolmenttabcap = true;
        if (!has_capability('local/program:manageprogram', $categorycontext)) {
            $selfenrolmenttabcap = false;
        }
        if (!empty($program->description)) {
            $description = \local_costcenter\lib::strip_tags_custom(html_entity_decode($program->description));
        } else {
            $description = "";
        }
        $isdescription = '';
        if (empty($description)) {
            $isdescription = false;
            $decsriptionstring = "";
        } else {
            $isdescription = true;
            if (strlen($description) > 540) {
                $decsriptionCut = substr($description, 0, 540);
                $decsriptionstring = \local_costcenter\lib::strip_tags_custom(html_entity_decode($decsriptionCut));
            } else {
                $decsriptionstring = "";
            }
        }
        $bcuser = $DB->record_exists('local_program_users',
            array('programid' => $programid, 'userid' => $USER->id));
        $userview = $bcuser && !is_siteadmin() && !has_capability('local/program:createprogram', $categorycontext) ? true : false;

        if ($userview) {
            $mycompletedlevels = (new program)->mycompletedlevels($programid, $USER->id);
            $notcmptllevels = (new program)->mynextlevels($programid);
            if (!empty($notcmptllevels)) {
                $levelid = $notcmptllevels[0];
            } else {
                // $levelid = $DB->get_field_select('local_program_levels', 'id',
                // 'programid = :programid ORDER BY id ASC LIMIT 0, 1 ',
                // array('programid' => $programid));
                $levelid_sql = "SELECT id FROM {local_program_levels} WHERE programid = :programid ORDER BY id ASC ";
                $levelid = $DB->get_field_sql($levelid_sql, array('programid' => $programid));
            }
        } else {
            $levelid = $DB->get_field_select('local_program_levels', 'id',
            'programid = :programid ORDER BY id ASC ',
            array('programid' => $programid));// LIMIT 0, 1 
        }
        $completionstatus = $DB->get_field('local_program_users', 'completion_status', array('programid'=>$programid, 'userid'=>$USER->id));
        if($completionstatus == 1){
            $programcompletionstatus = true;
        } else {
            $programcompletionstatus = false;
        }
        $ratings_exist = \core_component::get_plugin_directory('local', 'ratings');
        if($ratings_exist){
            require_once($CFG->dirroot.'/local/ratings/lib.php');
            $display_ratings = display_rating($programid, 'local_program');
            $display_like = display_like_unlike($programid, 'local_program');
            $display_review = display_comment($programid, 'local_program');
            /*$PAGE->requires->jquery();
            $PAGE->requires->js('/local/ratings/js/jquery.rateyo.js');
            $PAGE->requires->js('/local/ratings/js/ratings.js');*/
        }else{
            $display_ratings = $display_like = $display_review = null;
        }
        $challenge_exist = \core_component::get_plugin_directory('local', 'challenge');
        if($challenge_exist){
            $enabled =  (int)get_config('', 'local_challenge_enable_challenge');
            if($enabled){
                $challenge_render = $PAGE->get_renderer('local_challenge');
                $element = $challenge_render->render_challenge_object('local_program', $programid);
                $challenge_element =  $element;
            }else{
                $challenge_element = false;
            }
        }else{
            $challenge_element = false;
        }
         if(!is_siteadmin()){
            $switchedrole = $USER->access['rsw']['/1'];
            if($switchedrole){
                $userrole = $DB->get_field('role', 'shortname', array('id' => $switchedrole));
            }else{
                $userrole = null;
            }
//            if(is_null($userrole) || $userrole == 'user'){
             if(is_null($userrole) || $userrole == 'employee'){
                    $core_component = new \core_component();
                    $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
                if($certificate_plugin_exist){
                    if(!empty($program->certificateid)){
                        $certificate_exists = true;
                        $sql = "SELECT id 
                                FROM {local_program_users}
                                WHERE programid = :programid AND userid = :userid
                                AND completion_status = 1 ";
                        $completed = $DB->record_exists_sql($sql, array('programid'=>$programid,'userid'=>$USER->id));
                   
                         if($completed){
                            $certificate_download = true;
                        }else{
                            $certificate_download = false;
                        }
//            Mallikarjun added to get tool certificate
            $gcertificateid = $DB->get_field('local_program', 'certificateid', array('id'=>$programid));
            $certificateid = $DB->get_field('tool_certificate_issues', 'code', array('moduleid'=>$programid,'userid'=>$USER->id,'moduletype'=>'program'));
//                        $certificateid = $program->certificateid;
                        // $certificate_download['moduletype'] = 'program';
                    }
                }
            }
        }
        $programcontext = [
            'program' => $program,
            'programid' => $programid,
            'action' => $action,
            'edit' => $edit,
            'programcompletion' => $programcompletion,
            'cannotdelete' => $cannotdelete,
            'delete' => $delete,
            'assignusers' => $assignusers,
            'assignusersurl' => $assignusersurl,
            'bulkenrollusers' => $bulkenrollusers,
            'bulkenrollusersurl' => $bulkenrollusersurl,
            'description' => $description,
            'descriptionstring' => $decsriptionstring,
            'isdescription' => $isdescription,
            'user_tab' => $user_tab,
            'course_tab' => $course_tab,
            'session_tab' => $session_tab,
            'programname' => $program->name,
            'cfg' => $CFG,
            'programcompletionstatus' => $programcompletionstatus,
            'cancreatelevel' => (has_capability('local/program:createlevel', $categorycontext) && $manage),
            'seats_image' => $OUTPUT->image_url('GraySeatNew', 'local_program'),
            'levelid' => $levelid,
            'programlevels' => $this->viewprogramlevels($programid, $levelid),
            'display_ratings' => $display_ratings,
            'display_like' => $display_like,
            'display_review' => $display_review,
            'challenge_element' => $challenge_element,
            'certificate_exists' => isset($certificate_exists),
            'certificate_download' => $certificate_download,
            'certificateid' => isset($certificateid),
            'unenrolbutton' => $unenrolbutton
             
        ];
        return $this->render_from_template('local_program/programContent', $programcontext);
    }
    public function get_self_unenrollment_button($programid, $programname){
        global $DB, $USER;
        $selfenrolled = $DB->record_exists('local_program_users', array('programid' => $programid, 'userid' => $USER->id, 'usercreated' => $USER->id));
        if(!$selfenrolled){
            return null;
        }
        $categorycontext =(new \local_program\lib\accesslib())::get_module_context($programid);
        $object = html_writer::link('javascript:void(0)', '<i class="icon fa fa-user-times" aria-hidden="true" aria-label="" title ="'.get_string('unenrol').'"></i>', array('class' => 'course_extended_menu_itemlink unenrolself_module', 'onclick' => '(function(e){ require(\'local_program/program\').unEnrolUser({programid: '.$programid.', userid:'.$USER->id.', programname:\''.$programname.'\', contextid:'.$categorycontext->id.'}) })(event)'));
        $container = html_writer::div($object, '', array('class' => 'course_extended_menu_itemcontainer text-xs-center'));
        $liTag = html_writer::tag('li', $container);
        return html_writer::tag('ul', $liTag, array('class' => 'course_extended_menu_list'));
    }
    /**
     * [viewprogramusers description]
     * @method viewprogramusers
     * @param  [type]             $programid [description]
     * @param  [type]             $stable      [description]
     * @return [type]                          [description]
     */
    public function viewprogramusers($stable) {
        global $OUTPUT, $CFG, $DB;
        $programid = $stable->programid;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        if (has_capability('local/program:manageusers', $categorycontext) && has_capability('local/program:manageprogram', $categorycontext)) {
            $url = new moodle_url('/local/program/enrollusers.php', array('bcid' => $programid));
            $assign_users ='<ul class="course_extended_menu_list">
                                <li>
                                    <div class="createicon course_extended_menu_itemlink"><a href="'.$url.'"><i class="icon fa fa-users fa-fw add_programcourse createpopicon cr_usericon" aria-hidden="true" title="'.get_string('assignusers', 'local_program').'"></i></a>
                                    </div>
                                </li>
                                <li>
                                    <div class="createicon course_extended_menu_itemlink"><a href="' . $CFG->wwwroot . '/local/program/users.php?download=1&amp;format=xls&amp;type=programwise&amp;bcid='.$programid.'&amp;search='.$search.'" target ="_blank"><i class="icon fa fa-download" aria-hidden="true" title="'.get_string('programdownloadreport', 'local_program').'"></i></a>
                                    </div>
                                </li>
                                <li>
                                    <div class="createicon course_extended_menu_itemlink"><a href="' . $CFG->wwwroot . '/local/program/users.php?download=1&amp;format=xls&amp;type=coursewise&amp;bcid='.$programid.'&amp;search='.$search.'" target ="_blank"><i class="icon fa fa-download" aria-hidden="true" title="'.get_string('coursedownloadreport', 'local_program').'"></i></a>
                                    </div>
                                </li>
                            </ul>';
        } else {
            $assign_users = "";
        }

        $core_component = new \core_component();
        $certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
        if($certificate_plugin_exist){
            $certid = $DB->get_field('local_program', 'certificateid', array('id'=>$programid));
        }else{
            $certid = false;
        }
        if ($stable->thead) {
            $programusers = (new program)->programusers($programid, $stable);
            if ($programusers['programuserscount'] > 0) {
                $table = new html_table();
                $head = array(get_string('employee', 'local_program'), get_string('employeeid', 'local_program'), get_string('email'),get_string('supervisor', 'local_users'),get_string('nooflevels', 'local_program'), get_string('status'));

                if($certid){
                    $head[] = get_string('certificate','local_program');
                }
                $table->head = $head;

                $table->id = 'viewprogramusers';
                $table->attributes['data-programid'] = $programid;
                $table->align = array('left', 'left', 'left', 'left', 'center', 'center');
                if($certid){
                    $table->align[] = 'center';
                }
                $return = $assign_users.html_writer::table($table);
            } else {
                $return = $assign_users."<div class='mt-15 text-center alert alert-info w-full pull-left'>" . get_string('noprogramusers', 'local_program') . "</div>";
            }
        } else {
            $programusers = (new program)->programusers($programid, $stable);
            $data = array();
            foreach ($programusers['programusers'] as $sdata) {
                $line = array();
                $line[] = '<div>
                                <span>' . $OUTPUT->user_picture($sdata) . ' ' . fullname($sdata) . '</span>
                            </div>';
                $line[] = '<span> <label for="employeeid">' . $sdata->open_employeeid . '</lable></span>';
                $line[] = '<span> <label for="email">' . $sdata->email . '</lable></span>';
                if(!empty($sdata->open_supervisorid)){
                    $supervisor = $DB->get_record('user', array('id' => $sdata->open_supervisorid),
                    'id,firstname,lastname');
                    $reportingto = $supervisor->firstname.' '.$supervisor->lastname;
                    $line[] = $reportingto;
                }else{
                    $line[] = '--';
                } 
                $total_levels = $DB->count_records('local_program_levels',  array('programid' => $programid));
                $completed_levels = $DB->count_records('local_bc_level_completions',  array('programid' => $programid, 'completion_status'=>1, 'userid'=>$sdata->id));
                $line[] = '<span> <label for="nooflevelscount">'.$completed_levels.'/'.$total_levels.'</lable></span>';
                $line[] = $sdata->completion_status == 1 ?'<span class="tag tag-success" title="Completed">&#10004;</span>' : '<span class="tag tag-danger" title="Not Completed">&#10006;</span>';

                $sql = "SELECT id, programid, userid, completion_status
                        FROM {local_program_users} 
                        WHERE programid = :programid AND userid = :userid 
                        AND completion_status != 0 ";

                $completed = $DB->record_exists_sql($sql, array('programid'=>$programid, 'userid'=>$sdata->id));
                if($certid){
                    $icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
                    if($completed){
//                        $array = array('ctid'=>$certid, 'mtype'=>'program','mid'=>$programid, 'uid'=>$sdata->id);
//                        $url = new moodle_url('/local/certificates/view.php', $array);
//                        mallikarjun added to download default certificate 
                        $certcode = $DB->get_field('tool_certificate_issues', 'code', array('moduleid'=>$programid,'userid'=>$sdata->id,'moduletype'=>'program'));
                        $array = array('code'=>$certcode);
                        $url = new moodle_url('../../admin/tool/certificate/view.php?', $array);
                        $downloadlink = html_writer::link($url, $icon, array('title'=>get_string('download_certificate','tool_certificate')));
                        
                    }else{
                        $url = 'javascript: void(0)';
                       // $icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
                        $downloadlink = html_writer::tag($url,get_string('notassigned','local_program'));
                    }
                    $line[] =  $downloadlink;
                }
                $data[] = $line;
            }
            $return = array(
                "recordsTotal" => $programusers['programuserscount'],
                "recordsFiltered" => $programusers['programuserscount'],
                "data" => $data,
            );
        }
        return $return;
    }
    /**
     * [viewprogramattendance description]
     * @method viewprogramattendance
     * @param  [type]                 $programid [description]
     * @param  integer                $sessionid  [description]
     * @return [type]                             [description]
     */
    public function viewprogramattendance($programid, $sessionid = 0) {
        global $PAGE, $OUTPUT, $DB;
        $program = new program();
        $attendees = $program->program_get_attendees($programid, $sessionid);
        $return = '';
        if (empty($attendees)) {
            $return .= "<div class='w-full pull-left text-center alert alert-info'>" . get_string('noprogramsessionusers', 'local_program') . "</div>";
        } else {
            $return .= '<form method="post" id="formattendance" action="' . $PAGE->url . '">';
            $return .= '<input type="hidden" name="action" value="attendance" />';
            $params = array();
            $params['programid'] = $programid;
            $sqlsessionconcat = '';
            if ($sessionid > 0) {
                $sqlsessionconcat = " AND id = :sessionid";
                $params['sessionid'] = $sessionid;
            }
            $sessions = $DB->get_fieldset_select('local_bc_course_sessions', 'id',
                'programid = :programid ' . $sqlsessionconcat, $params);
            foreach ($attendees as $attendee) {
                if (!$sessionid) {
                    $attendancestatuslist = $DB->get_records_sql('SELECT sessionid, id AS attendanceid, sessionid, status, userid FROM {local_bc_session_signups} WHERE programid = :programid AND userid = :userid', array('programid' => $programid, 'userid' => $attendee->id));
                }
                $list = array();
                $list[] = $OUTPUT->user_picture($attendee, array('size' => 30)) . fullname($attendee);
                foreach ($sessions as $session) {
                    if ($sessionid > 0) {
                        $attendanceid = $attendee->attendanceid;
                        $attendancestatus = $attendee->completion_status;
                    } else {
                        $attendanceid = isset($attendancestatuslist[$session]->attendanceid) && $attendancestatuslist[$session]->attendanceid > 0 ? $attendancestatuslist[$session]->attendanceid : 0;
                        $attendancestatus = isset($attendancestatuslist[$session]->status) && $attendancestatuslist[$session]->status > 0 ? $attendancestatuslist[$session]->status : 0;
                    }

                    $encodeddata = base64_encode(json_encode(array(
                            'programid' => $programid, 'sessionid' => $session,
                            'userid' => $attendee->id, 'attendanceid' => $attendanceid)));
                    $radio = '<input type="hidden" value="' . $encodeddata . '"
                    name="attendeedata[]">';

                    $check_exist = $DB->get_field('local_bc_session_signups', 'id',
                        array('sessionid' => $session, 'userid' => $attendee->id));
                    if ($check_exist) {
                        $checked = '';
                    } else {
                        $checked = 'checked';
                    }

                    if ($attendancestatus == 2) {
                        $checked = '';
                        $status = $sessionid > 0 ? "Absent" : "A";
                        $status = '<span class="tag tag-danger">'.$status.'</span>';
                    } else if ($attendancestatus == 1) {
                        $status = $sessionid > 0 ? "Present" : "P";
                        $checked = 'checked';
                        $status = '<span class="tag tag-success">'.$status.'</span>';
                    } else {
                        $status = $sessionid > 0 ? "Not yet given" : "NY";
                        $status = '<span class="tag tag-warning">'.$status.'</span>';
                    }
                    $radio .= '<input type="checkbox" name="status[' . $encodeddata .']"
                         ' . $checked  .' class="checksingle'.$session.'">';
                    if ($sessionid > 0) {
                        $list[] = $status;
                    }
                    $list[] = $radio;
                }
                $data[] = $list;
            }
            $table = new html_table();
            $script = "";
            if ($sessionid > 0) {
                $table->head = array('Employee', 'Status', 'Attendance<p><input type=checkbox name=checkAll id=checkAll'.$sessionid.'> Select All</p>');
                $script .= html_writer::script("
                        $('#checkAll$sessionid').change(function () {
                                $('.checksingle$sessionid').prop('checked', $(this).prop('checked'));
                         });
                     ");
            } else {
                $table->head[] = 'Employee';
                foreach ($sessions as $session) {
                    $table->head[] = 'Session ' . $session.'<p><input type=checkbox name=checkAll id=checkAll'.$session.'> Select All</p>';
                    $script .= html_writer::script("
                        $('#checkAll$session').change(function () {
                                $('.checksingle$session').prop('checked', $(this).prop('checked'));
                         });
                     ");
                }
            }
            $table->data = $data;
            $return .= html_writer::table($table);
            $return .= '<input type="submit" name="submit" value="Submit">';
            $return .= '<input type="submit" name="reset" value="Reset Selected">';
            $return .= '</form>';
            $return .= "<div id='result'></div>" . $script;
        }
        return $return;
    }

    public function viewprogramlastchildpopup($programid){
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        $stable = new stdClass();
        $stable->programid = $programid;
        $stable->thead = false;
        $stable->start = 0;
        $stable->length = 1;
        $program = (new program)->programs($stable);
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        $program_status = $DB->get_field('local_program', 'status', array('id' => $programid));
        if (!has_capability('local/program:view_newprogramtab', $categorycontext) && $program_status== 0) {
            print_error("You don't have permissions to view this page.");
        } else if (!has_capability('local/program:view_holdprogramtab', $categorycontext) &&
            $program_status == 2) {
            print_error("You don't have permissions to view this page.");
        }
        if (empty($program)) {
            print_error("program Not Found!");
        }
        $includesfile = false;
        if (file_exists($CFG->dirroot . '/local/includes.php')) {
            $includesfile = true;
            require_once($CFG->dirroot . '/local/includes.php');
            $includes = new user_course_details();
        }

        if ($program->programlogo > 0) {
            $program->programlogoimg = (new program)->program_logo($program->programlogo);
            if ($program->programlogoimg == false) {
                if($includesfile){
                    $program->programlogoimg = $includes->get_classes_summary_files($sdata);
                }
            }
        } else {
            if($includesfile){
                $program->programlogoimg = $includes->get_classes_summary_files($program);
            }
        }

        $return = "";
        $program->userenrolmentcap = (has_capability('local/program:manageusers', $categorycontext)
            && has_capability('local/program:manageprogram', $categorycontext)
            && $program->status == 0) ? true : false;

        $stable = new stdClass();
        $stable->thead = true;
        $stable->start = 0;
        $stable->length = -1;
        $stable->search = '';


        $allocatedseats = $DB->count_records('local_program_users',
            array('programid' => $programid)) ;
        $coursesummary = \local_costcenter\lib::strip_tags_custom($course->summary,
                    array('overflowdiv' => false, 'noclean' => false, 'para' => false));
        $description = \local_costcenter\lib::strip_tags_custom(html_entity_decode($program->description));
        $isdescription = '';
        if (empty($description)) {
           $isdescription = false;
        } else {
            $isdescription = true;
            if (strlen($description) > 250) {
                $decsriptionCut = substr($description, 0, 250);
                $decsriptionstring = \local_costcenter\lib::strip_tags_custom(html_entity_decode($decsriptionCut));
            } else {
                $decsriptionstring = "";
            }
        }

        $programcontext = [
            'program' => $program,
            'programid' => $programid,
            'allocatedseats' => $allocatedseats,
            'description' => $description,
            'descriptionstring' => $decsriptionstring,
            'isdescription' => $isdescription,
            'contextid' => $categorycontext->id,
            'cfg' => $CFG,
            'linkpath' => "$CFG->wwwroot/local/program/view.php?bcid=$programid"
        ];
        return $this->render_from_template('local_program/programview', $programcontext);
    }
    /**
     * [view_program_sessions description]
     * @method view_program_sessions
     * @param  [type]                 $bclcid [description]
     * @param  [type]                 $stable [description]
     * @return [type]                         [description]
     */
    public function view_program_sessions($bclcid, $stable) {
        global $OUTPUT, $CFG, $DB, $USER;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($bclcid->programid);
        if ($stable->thead) {
            $return = '';
            $sessions = (new program)->programsessions($bclcid, $stable);
            if ($sessions['sessionscount'] > 0) {
                $table = new html_table();
                if ((has_capability('local/program:manageprogram', $categorycontext) || is_siteadmin())) {
                    $table->head = array(get_string('name'), get_string('date'));
                    $table->head[] = get_string('type', 'local_program');
                    $table->head[] = get_string('room', 'local_program');
                    $table->head[] = get_string('status', 'local_program');
                    $table->head[] = get_string('trainer', 'local_program');
                } else {
                    $table->head = array(get_string('name'), get_string('date'));
                    $table->head[] = get_string('type', 'local_program');
                    $table->head[] = get_string('room', 'local_program');
                    $table->head[] = get_string('status', 'local_program');
                    $table->head[] = get_string('trainer', 'local_program');
                }

                $table->id = 'viewbcsessions';
                $table->attributes['data-bclcid'] = $bclcid;
                $return .= html_writer::table($table);
            } else {
                $return .= "<div class='mt-15 alert alert-info w-full pull-left text-center'>" .
                get_string('nosessions', 'local_program') . "</div>";
            }
        } else {
            $sessions = (new program)->programsessions($bclcid, $stable);
            $data = array();
            foreach ($sessions['sessions'] as $sdata) {
                $line = array();
                $line[] = $sdata->name;
                $line[] = \local_costcenter\lib::get_userdate("d/m/Y H:i", $sdata->timestart) . ' to ' .
                                    \local_costcenter\lib::get_userdate("d/m/Y H:i", $sdata->timefinish);

                $link = get_string('pluginname', 'local_program');
                if ($sdata->onlinesession == 1) {
                    $moduleids = $DB->get_field('modules', 'id',
                        array('name' => $sdata->moduletype));
                    if ($moduleids) {
                        $moduleid = $DB->get_field('course_modules', 'id',
                            array('instance' => $sdata->moduleid, 'module' => $moduleids));
                        if ($moduleid) {
                            $link = html_writer::link($CFG->wwwroot . '/mod/' . $sdata->moduletype. '/view.php?id=' . $moduleid,
                                get_string('join', 'local_program'),
                                array('title' => get_string('join', 'local_program')));
                            if (!has_capability('local/program:manageprogram', $categorycontext)) {
                                $userenrolstatus = $DB->record_exists('local_program_users', array('programid' => $programid, 'userid' => $USER->id));
                                if (!$userenrolstatus) {
                                    $link = get_string('join', 'local_program');
                                }
                            }
                        }
                    }
                }
                $line[] = $link;
                $line[] = $sdata->room ? $sdata->room : 'N/A';
                if ($sdata->timefinish <= time() && $sdata->attendance_status == 1) {
                    $line[] = get_string('completed', 'local_program');
                } else {
                    $line[] = get_string('pending', 'local_program');
                }
                $trainer = $DB->get_record('user', array('id' => $sdata->trainerid));
                $line[] =  fullname($trainer);
                $data[] = $line;
            }
            $return = $data;
        }
        return $return;
    }
    public function viewprogramstreams($stable) {
        global $OUTPUT, $CFG, $DB;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
        $return = '';
        if (is_siteadmin() || has_capability('local/program:manageprogram', $categorycontext)) {
            $return .= '<ul class="course_extended_menu_list">
                            <li>
                                <div class="course_extended_menu_itemcontainer">
                                    <a class="course_extended_menu_itemlink create_ilt" title=" '. get_String("create_streams", "local_program") .'" onclick="(function(e){ require(\'local_program/ajaxforms\').init({contextid:'. $categorycontext->id .', component:\'local_program\', callback:\'program_managestream_form\', form_status:0, plugintype: \'local_program\', pluginname: \'stream\', id:0, title: \'createstream\' }) })(event)">
                                        <i class="icon fa fa-columns" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </li>
                        </ul>';
            }
        if ($stable->thead) {
            $programstreams = (new program)->programstreams($stable);
            if ($programstreams['programstreamscount'] > 0) {
                $table = new html_table();
                $table->head = array(get_String('streams','local_program'), get_String('description','local_program'), get_String('actions','local_program'));
                $table->id = 'viewprogramstreams';
                $return .= html_writer::table($table);
            } else {
                $return .= "<div class='alert alert-info text-center'>" .
                        get_string('noprogramstreams', 'local_program') . "</div>";
            }
        } else {
            $programstreams = (new program)->programstreams($stable);
            $data = array();
            foreach ($programstreams['programstreams'] as $programstream) {
                $row = [];
                $row[] = $programstream->stream;
                  if(strlen($programstream->description) >380){
                    $programstream->description = substr($programstream->description, 0,380).'...';
                    }
                $row[] = $programstream->description? $programstream->description: 'N/A';
                $action = '';
                if (is_siteadmin() || (has_capability('local/program:manageprogram', $categorycontext))) {
                    $action .= '<a href="javascript:void(0);" alt = ' . get_string('edit') . ' title = ' . get_string('edit') . ' onclick="(function(e){ require(\'local_program/ajaxforms\').init({contextid:1, component:\'local_program\', callback:\'program_managestream_form\', form_status:0, plugintype:\'local\', pluginname:\'program_stream\', id: ' . $programstream->id . ', title: \'updatesession\'}) })(event)" ><img src="' . $OUTPUT->image_url('t/editinline') . '" alt = ' . get_string('edit') . ' title = ' . get_string('edit') . ' class="icon"/></a>';
                }
                if (is_siteadmin() || (has_capability('local/program:manageprogram', $categorycontext))) {
                    $pr_exists = $DB->record_exists('local_program',array('stream'=>$programstream->id));
                    if($pr_exists){
                        $action .= $OUTPUT->help_icon('cannotdeleteprogram', 'local_program');
                    }else{
                        $action .= '<a href="javascript:void(0);" alt = ' . get_string('delete') . ' title = ' . get_string('delete') . ' onclick="(function(e){ require(\'local_program/program\').deleteConfirm({action:\'deletestream\', id: ' . $programstream->id . ' }) })(event)" ><img src="' . $OUTPUT->image_url('i/trash') . '" alt = '. get_string('delete') . ' title = ' . get_string('delete') . ' class="icon"/></a>';
                    }
                }
                $row[] = $action;//'Edit, Delete';
                $data[] = $row;
            }
            $return = array(
                "recordsTotal" => $programstreams['programstreamscount'],
                "recordsFiltered" => $programstreams['programstreamscount'],
                "data" => $data
            );
        }
        return $return;
    }
      public function programview_check($programid){
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        $stable = new stdClass();
        $stable->programid = $programid;
        $stable->thead = false;
        $stable->start = 0;
        $stable->length = 1;
        $program = (new program)->programs($stable);

        $program_status = $DB->get_field('local_program', 'status', array('id' => $programid));
        if (empty($program)) {
            print_error("program Not Found!");
        }

        return $program;
    }

    /**
     * Renders html to print list of program tagged with particular tag
     *
     * @param int $tagid id of the tag
     * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
     *             are displayed on the page and the per-page limit may be bigger
     * @param int $fromctx context id where the link was displayed, may be used by callbacks
     *            to display items in the same context first
     * @param int $ctx context id where to search for records
     * @param bool $rec search in subcontexts as well
     * @param array $displayoptions
     * @return string empty string if no courses are marked with this tag or rendered list of courses
     */
  public function tagged_programs($tagid, $exclusivemode, $ctx, $rec, $displayoptions, $count = 0, $sort='') {
    global $CFG, $DB, $USER;
    $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
    if ($count > 0)
    $sql =" select count(c.id) from {local_program} c ";
    else
    $sql =" select c.* from {local_program} c ";

    $where = " where c.id IN (SELECT t.itemid FROM {tag_instance} t WHERE t.tagid = :tagid AND t.itemtype = :itemtype AND t.component = :component)";
    $joinsql = $groupby = $orderby = '';
    if (!empty($sort)) {
      switch($sort) {
        case 'highrate':
        if ($DB->get_manager()->table_exists('local_rating')) {
          $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_program' ";
          $groupby .= " group by c.id ";
          $orderby .= " order by AVG(rating) desc ";
        }        
        break;
        case 'lowrate':  
        if ($DB->get_manager()->table_exists('local_rating')) {  
          $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_program' ";
          $groupby .= " group by c.id ";
          $orderby .= " order by AVG(rating) asc ";
        }
        break;
        case 'latest':
        $orderby .= " order by c.timecreated desc ";
        break;
        case 'oldest':
        $orderby .= " order by c.timecreated asc ";
        break;
        default:
        $orderby .= " order by c.timecreated desc ";
        break;
        }
    }
    $whereparams = array();
    $conditionalwhere = '';
    if (!is_siteadmin()) {
        $wherearray = orgdep_sql($categorycontext); // get records department wise
        $whereparams = $wherearray['params'];
        $conditionalwhere = $wherearray['sql'];
    }    

    $tagparams = array('tagid' => $tagid, 'itemtype' => 'program', 'component' => 'local_program');
    $params = array_merge($tagparams, $whereparams);
    if ($count > 0) {
      $records = $DB->count_records_sql($sql.$where.$conditionalwhere, $params);
      return $records;
    } else {
      $records = $DB->get_records_sql($sql.$joinsql.$where.$conditionalwhere.$groupby.$orderby, $params);
    }
    $tagfeed = new \local_tags\output\tagfeed(array(), 'programs');
    $img = $this->output->pix_icon('i/course', '');
    foreach ($records as $key => $value) {
      $url = $CFG->wwwroot.'/local/program/view.php?bcid='.$value->id.'';
      $imgwithlink = html_writer::link($url, $img);
      $modulename = html_writer::link($url, $value->name);
      $testdetails = get_program_details($value->id);
      $details = '';
      $details = $this->render_from_template('local_program/tagview', $testdetails);
      $tagfeed->add($imgwithlink, $modulename, $details);
    }
    return $this->output->render_from_template('local_tags/tagfeed', $tagfeed->export_for_template($this->output));
    }
    public function get_userdashboard_program($tab, $filter = false,$view_type='card'){
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

        $templateName = 'local_program/userdashboard_paginated';
        $cardClass = 'col-md-6 col-12';
        $perpage = 6;
        if($view_type=='table'){
            $templateName = 'local_program/userdashboard_paginated_catalog_list';
            $cardClass = 'tableformat';
            $perpage = 20;
        } 
        $options = array('targetID' => 'dashboard_program', 'perPage' => $perpage, 'cardClass' => $cardClass, 'viewType' => $view_type);
        $options['methodName']='local_program_userdashboard_content_paginated';
        $options['templateName']= $templateName;
        $options['filter'] = $tab;
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $categorycontext->id));
        $context = [
            'targetID' => 'dashboard_program',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];
        if($filter){
           return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }
}
