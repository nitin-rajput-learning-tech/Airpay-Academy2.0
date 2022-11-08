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
 * @subpackage local_request
 */
namespace local_request\export;
defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use context_system;
use context_course;
use context_user;



/**
 * Class containing data for course competencies page
 *
 * @copyright  2018 hemalatha c arun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class requestview {




    public static function exporter($requestlist,$tab){
      global $DB, $USER;

      $outputhtml = '';
        $data = new stdClass();
        $usercontext = context_user::instance($USER->id);
        foreach ($requestlist as  $request) {

            $onerow['status']=  $request->status;

            if($request->status =='APPROVED'){
              $onerow['approvestatus'] =1;
            }
            else{
               $onerow['approvestatus'] =0;
            }

            if($request->status =='REJECTED'){
              $onerow['rejectstatus'] =1;
            }
            else{
               $onerow['rejectstatus'] =0;
            }
            // if($request->compname == 'elearning'){
            //     $request->compname = 'E-Learning';
            // }
            $onerow['compname'] = get_string($request->compname,'local_request');
            $onerow['requestedby'] =$request->createdbyid;
            $onerow['requesteddate'] = \local_costcenter\lib::get_userdate("d/m/Y ",$request->timecreated);
            $onerow['componentid'] = $request->componentid;
            $onerow['id'] = $request->id;
            if($request->createdbyid){
              $user =$DB->get_record('user', array('id'=>$request->createdbyid));
              $onerow['requesteduser'] = fullname($user);
            }
            $onerow['responder'] = (new self)->get_responder_name($request->responder);
            if($request->respondeddate){
              $onerow['respondeddate'] = \local_costcenter\lib::get_userdate("d/m/Y ",$request->respondeddate);
            }
            else
              $onerow['respondeddate']='-------';

              $onerow['componentname'] = (new self)->get_component_name($request->compname, $request->componentid);

            $record['request'] = $onerow;
           $data->requests[]= $record;
        } // end of foreach
       // $data->requests = $data->requests;
        $data->tab =  $tab;
        $data->capability=  (new self)->get_capabilitycheck_list();
        return $data;


    } // exporter end of function


    private function get_responder_name($responder){
      global $DB;
      if($responder){
         $responderinfo=$DB->get_record('user', array('id'=>$responder));
         $name = $responderinfo->firstname.' '.$responderinfo->lastname;
      }
      else{

         $name = "-------";
      }

      return $name;

    } // end of get_responder_name

    private function get_component_name($component, $componentid){
      global $DB;

      $componentname='-------';

      if($componentid){

      switch($component){
      case 'classroom' :
                        $componentname = $DB->get_field('local_classroom', 'name', array('id'=>$componentid));
                         break;

      case 'program' :    $componentname = $DB->get_field('local_program', 'name', array('id'                  =>$componentid));
                         break;

      case 'learningplan' : $componentname = $DB->get_field('local_learningplan', 'name', array('id'=>$componentid));
                         break;


      case 'elearning' :   $componentname = $DB->get_field('course', 'fullname', array('id'=>$componentid));
                         break;

      case 'certification' :   $componentname = $DB->get_field('local_certification', 'name', array('id'=>$componentid));
                         break;

     } // end of switch statement

    }

      return $componentname;

    } // end of get_component_name


    public  function get_capabilitycheck_list(){
        global $USER;
        $usercontext =(new \local_request\lib\accesslib())::get_module_context();
        $viewrecord_capability=0;
            if(has_capability('local/request:viewrecord',$usercontext)){
              $viewrecord_capability=1;
            }


            $approve_capability=0;
            if(has_capability('local/request:approverecord',$usercontext)){
              $approve_capability=1;
            }

            $deny_capability=0;
            if(has_capability('local/request:denyrecord',$usercontext)){
              $deny_capability=1;
            }

            $addrecord_capability=0;
            if(has_capability('local/request:addrecord',$usercontext)){
              $addrecord_capability=1;
            }

            $deleterecord_capability=0;
            if(has_capability('local/request:deleterecord',$usercontext)){
              $deleterecord_capability=1;
            }

            $addcomment_capability=0;
            if(has_capability('local/request:addcomment',$usercontext)){
              $addcomment_capability=1;
            }

        return $list= array('viewrecord_capability'=>$viewrecord_capability,
                            'approve_capability'=> $approve_capability,
                            'deny_capability'=>  $deny_capability,
                            'addrecord_capability'=>$addrecord_capability,
                            'deleterecord_capability'=>$deleterecord_capability,
                             'addcomment_capability' =>$addcomment_capability );
    } // end of function




} // end of class






