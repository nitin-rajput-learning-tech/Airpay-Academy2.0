	<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
namespace local_program\event;
use stdClass;
defined('MOODLE_INTERNAL') or die;
class online_sessions_integration{

    public function online_sessions_type($session,$new_session,$type=1,$action){
        global $DB,$CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        // print_object("hi sir");exit;
        $get_config=get_config('local_program', 'program_onlinesession_type');

        if(!empty($get_config)){
            $instance_type=explode('_',$get_config);
            $visible = $DB->get_field('modules','visible',array('name'=>$instance_type[1]));
            if($visible){
                 $return=$this->program_module_integration($instance_type[1],$session,$new_session,$type);
            }


        }
    }
    /*For program if the Online session is a bigblueButton */
    public function program_module_integration($instance_type,$session,$new_session,$type){
        global $DB,$CFG,$USER;

        require_once($CFG->dirroot . '/course/modlib.php');

        $instance_type_timezone="$instance_type"._timezone;
        $instance_type_datetime="$instance_type"._datetime;

        if($instance_type!='webexactivity'){
            $online_module->name=$DB->get_field('local_program','name',array('id'=>$session->programid));
            if(empty($session->sessiontimezone)){
                $session->sessiontimezone='Asia/Kolkata';
            }
            $online_module->timezone=$session->sessiontimezone;
            $online_module->class_type=1;
            $online_module->$instance_type_timezone=$session->sessiontimezone;
            $online_module->class_timezone =$session->sessiontimezone;
            $online_module->timenow=time();
            $online_module->$instance_type_datetime=$session->timestart;
            $online_module->recording=1;
        }else{
            $online_module->type=1;
            $length = 9;
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?";
            $password = substr( str_shuffle( $chars ), 0, $length );
            $webex->password=$password;
            $webex->introformat=1;
            $webex->starttime=$session->timestart;
            $webex->endtime=$session->timefinish;
        }

        $online_module->modulename="$instance_type";
        $courseid=$DB->get_field('local_program','course',array('id'=>$session->programid));
        if($courseid==0){
           $courseid=1;
        }

        $online_module->course=$courseid;
        if(empty($session->duration)){
                $session->duration=0;
        }
        $online_module->duration=$session->duration;

        $online_module->groupinid=0;
        $online_module->module=$DB->get_field('modules','id',array('name'=>$instance_type));
        $online_module->section=1;
        $online_module->batchid=$session->programid;
        $online_module->sessionid=$new_session;
        $online_module->visible=1;
        if($instance_type=='bigbluebuttonbn'){
                $online_module->record=1;
                $online_module->add=$instance_type;

                $selections=array(
                                array('selectiontype'=>'all','selectionid'=>'all','role'=>'viewer')
                            );
                if(!empty($session->programid))
                      $get_trainers=$DB->get_records('local_bc_course_sessions',array('id'=>$session->id));

                if(!empty($get_trainers)){

                  foreach($get_trainers as $bbparticipants){
                        $row=array();
                        $row['selectiontype']='user';
                        $row['selectionid']=$bbparticipants->trainerid;
                        $row['role']='moderator';
                        $selections[]=$row;

                   }

                }

                $online_module->participants =json_encode($selections);
        }

       $courseid = $DB->get_record('course', array('id'=>$online_module->course), '*', MUST_EXIST);
     //print_object($online_module);
       $add_moduleinfo=add_moduleinfo($online_module, $courseid, $mform = null);

         if(isset( $add_moduleinfo->id)){
             $add_moduleinfo->id= $add_moduleinfo->id;
         }elseif(isset($add_moduleinfo->instance)){
             $add_moduleinfo->id= $add_moduleinfo->instance;
         }
        if($add_moduleinfo->id){
            $up_session=new stdClass();
            $up_session->id = $session->id;
            $up_session->moduletype = $instance_type;
            $up_session->moduleid = $add_moduleinfo->id;
            $up_session->timemodified = time();
            $up_session->usermodified = $USER->id;
            $DB->update_record('local_bc_course_sessions', $up_session);
        }

    }

}