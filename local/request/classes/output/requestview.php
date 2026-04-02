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
namespace local_request\output;
defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use context_system;
use context_course;
use context_user;

use local_request\export\requestview as requestexporter;



/**
 * Class containing data for course competencies page
 *
 * @copyright  2018 hemalatha c arun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class requestview implements renderable, templatable {

    /** @var int $courseid Course id for this page. */
    protected $pendinglist = null;


    protected $requestlist =null;


    /**
     * Construct this renderable.
     * @param int $courseid The course record for this page.
     */
    public function __construct($list=null, $component=null,$sorting=false,$tab=false,$componentid=false) {
        global $USER, $DB;
        // if(empty($list)){
            $systemcontext = (new \local_request\lib\accesslib())::get_module_context();

            if(has_capability('local/request:viewrecord',$systemcontext) && !has_capability('local/request:approverecord',$systemcontext)){
                $this->requestlist = $DB->get_records('local_request_records', array('createdbyid' =>$USER->id));

            }
            else{
                if(is_siteadmin() || (has_capability('local/request:viewrecord',$systemcontext) && has_capability('local/request:approverecord',$systemcontext))){
                    $this->requestlist = $this->get_specific_costcenter_requests($component,$sorting,$componentid);
                }
            }
          $this->tab = $tab;
        return $this;
    }// end of constructors


   public function get_specific_costcenter_requests($component=null, $sorting=false, $componentid=false){
      global $USER, $DB;
        $requestlist = array();
        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
        $systemcontext = (new \local_request\lib\accesslib())::get_module_context();
        $fields = " req.id, req.createdbyid, req.compname, req.compcode, req.compkey, req.componentid, req.status, req.responder, req.respondeddate, req.usermodified, req.timecreated, req.timemodified ";
        if(is_siteadmin()){
           $sql = "SELECT $fields FROM {local_request_records} AS req 
           JOIN {user} as u ON u.id=req.createdbyid
           WHERE 1=1";
        }else{
              $sql = "SELECT req.* FROM {local_request_records} AS req
                  JOIN {user} AS u ON u.id=req.createdbyid
                  WHERE 1=1 $costcenterpathconcatsql";
                        }
        if($sql){
          $sql .= " AND u.deleted = 0 AND u.suspended = 0 ";
          if($component){
            if(is_array($component)){
              $listid =  "'".implode("','", $component)."'";

            }else{
              $listid = "'".$component."'";
            }
            $sql .=" and req.compname IN ($listid) ";
          }
          if($componentid){
            $sql .=" and req.componentid=$componentid ";

          }
            $sql .= " ORDER by req.timemodified DESC";
          $requestlist = $DB->get_records_sql($sql);
        }

        return $requestlist;
      } // end of  get_specific_costcenter_requests
    public function get_requestdetails($stable,$filtervalues){
      
        global $DB, $PAGE,$USER,$CFG,$OUTPUT;
        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);           
        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path',$org);
        $params=array();

        $systemcontext = (new \local_request\lib\accesslib())::get_module_context();
        $plugins = get_plugin_list('local');
        $subquery = array();
        foreach($plugins AS $plugin){
            if(file_exists($plugin.'/lib.php')){
                require_once($plugin.'/lib.php');
                $functionname = 'local_'.end(explode('/', $plugin)).'_request_dependent_query';
                if(function_exists($functionname)){
                    $subquery[] = $functionname($aliasname = 'req');
                }
            }
        }
        if(!empty($subquery)){
            $dependencyquery = " CASE ".implode(' ', $subquery)." ELSE NULL END ";
            $wheredependency_sql = " AND {$dependencyquery} IS NOT NULL ";
        }else{
            $dependencyquery = " ";
            $wheredependency_sql = " ";
        }
        $requestsql = " SELECT req.id, req.createdbyid, req.compname, req.compcode, req.compkey, req.componentid, req.status, req.responder, req.respondeddate, req.usermodified, req.timecreated, req.timemodified, {$dependencyquery} AS actualcomponentname ";
        $countsql = " SELECT count(req.id) ";
        
        if(is_siteadmin()){
           $sql = " FROM {local_request_records} AS req 
            JOIN {user} AS u ON u.id=req.createdbyid 
            WHERE 1=1";
        }else{
           
            $sql = " FROM {local_request_records} AS req
                JOIN {user} AS u ON u.id=req.createdbyid
                WHERE 1=1 $costcenterpathconcatsql ";
        }
        $sql .= " AND u.deleted = 0 AND u.suspended = 0 {$wheredependency_sql} ";

        $sitecontext =(new \local_request\lib\accesslib())::get_module_context();
        if(!is_siteadmin() && !has_capability('local/request:approverecord', $sitecontext)){
           $sql .= " AND u.id = $USER->id ";
        }

        if($filtervalues->courseid>1){
            $sql .= " AND req.componentid={$filtervalues->courseid} ";
        }
       
        if($sql){
          if($filtervalues->courses){
            if(is_array($filtervalues->courses)){
              $listid =  /*"'".*/implode("','", $filtervalues->courses)/*."'"*/;

            }else{
              $listid = /*"'".*/$filtervalues->courses/*."'"*/;
            }
            $sql .=" and req.componentid IN ($listid) and req.compname LIKE 'elearning'";
          }
         if($filtervalues->classroom){
            if(is_array($filtervalues->classroom)){
              $listid =  /*"'".*/implode("','", $filtervalues->classroom)/*."'"*/;

            }else{
              $listid = /*"'".*/$filtervalues->classroom/*."'"*/;
            }
            $sql .=" and req.componentid IN ($listid) and req.compname LIKE 'classroom'";
          }
          if($filtervalues->learningplan){
            if(is_array($filtervalues->learningplan)){
              $listid =  /*"'".*/implode("','", $filtervalues->learningplan)/*."'"*/;

            }else{
              $listid = /*"'".*/$filtervalues->learningplan/*."'"*/;
            }
            $sql .=" and req.componentid IN ($listid) and req.compname LIKE 'learningplan'";
          }
        if($filtervalues->users){
            if(is_array($filtervalues->users)){
                $userids =  /*"'".*/implode("','", $filtervalues->users)/*."'"*/;
            }else{
                $userids = /*"'".*/$filtervalues->users/*."'"*/;
            }
            $sql .=" and req.createdbyid IN ($userids) ";
        }
        if(isset($filtervalues->program) && !empty($filtervalues->program)){
            $programids = $filtervalues->program;

            $sql .= " and req.componentid IN ($programids) and req.compname LIKE 'program' ";
        }
        if($filtervalues->status){
          $componentlist = explode(',',$filtervalues->status);
          list($componentlistsql, $componentlistparams) = $DB->get_in_or_equal($componentlist, SQL_PARAMS_NAMED, 'status');
            $params = array_merge($params,$componentlistparams);

            $sql .=" and req.status $componentlistsql ";
        }
        if($filtervalues->request){
            $componentlist = explode(',',$filtervalues->request);

            list($componentlistsql, $componentlistparams) = $DB->get_in_or_equal($componentlist, SQL_PARAMS_NAMED, 'componentlist');
            $params = array_merge($params,$componentlistparams);

            $sql .=" and req.compname $componentlistsql ";
        }
        $requestcount = $DB->count_records_sql($countsql.$sql,$params);
        $sql .= "ORDER BY CASE WHEN (req.status LIKE 'PENDING') THEN 1 
                WHEN (req.status LIKE 'REJECTED') THEN 2
                WHEN (req.status LIKE 'APPROVED') THEN 3
                ELSE 4 END ASC , req.id DESC ";
        $requestlist = $DB->get_records_sql($requestsql.$sql,$params, $stable->start, $stable->length);

        $record = array();
        foreach ($requestlist as  $request) {
            $onerow = array();
            $onerow['status']=  $request->status;
            $onerow['responded'] = 0;
            if($request->status =='APPROVED'){
                $onerow['approvestatus'] =1;
                $onerow['responded'] = 1;

            }
            else{
               $onerow['approvestatus'] =0;
            }

            if($request->status =='REJECTED'){
                $onerow['rejectstatus'] =1;
                $onerow['responded'] = 1;
                
            }
            else{
                $onerow['rejectstatus'] =0;
            }
            if($request->status =='PENDING'){
                $onerow['enablebutton'] = 1;
            }else{
                $onerow['enablebutton'] = 1;
            }
            $onerow['compname'] = get_string($request->compname,'local_request');
            $onerow['requestedby'] =$request->createdbyid;

            $reqesteddate = $request->timecreated;
            
            
            $onerow['requesteddate'] = \local_costcenter\lib::get_userdate("d/m/Y H:i",$reqesteddate);
            $onerow['componentid'] = $request->componentid;
            $onerow['id'] = $request->id;
            if($request->createdbyid){
              $user =$DB->get_record('user', array('id'=>$request->createdbyid));
              $onerow['requesteduser'] = fullname($user);
            }
            if($request->responder){
                $responderinfo=$DB->get_record('user', array('id'=>$request->responder));
                $name = $responderinfo->firstname.' '.$responderinfo->lastname;
            }else{
                $name = '-------';
            }

            $onerow['responder'] = $name;
            $responddate = $request->respondeddate;
            if($responddate)
                $onerow['respondeddate'] = \local_costcenter\lib::get_userdate("d/m/Y H:i",$responddate);
            else
                $onerow['respondeddate']='-------';
            $componentid = $request->componentid;

            $presentdate = time();

              $checkdate = $responddate ? $responddate : time(); 
              $diff = $checkdate - $reqesteddate;
              $days = (int)($diff/(60*60*24));
              $onerow['daysdone'] = $days;


            $favcolor = "red";

            $deleted = False;
            $pluginshelper = array('elearning' => array('componenticonclass' => 'fa fa-book',
                                                'customimage_required' => False),
                            'classroom' => array('componenticonclass' => 'classroom_icon',
                                                'customimage_required' => True),
                            'certification' => array('componenticonclass' => 'fa fa-graduation-cap',
                                                'customimage_required' => False),
                            'learningplan' => array('componenticonclass' => 'fa fa-map',
                                                'customimage_required' => False),
                            'program' => array('componenticonclass' => 'program_icon',
                                                'customimage_required' => True)
                        );
            $onerow['componenticonclass'] = $pluginshelper[$request->compname]['componenticonclass'];
            $onerow['customimage_required'] = $pluginshelper[$request->compname]['customimage_required'];
            $onerow['componentname'] = strlen($request->actualcomponentname) > 20 ? clean_text(substr($request->actualcomponentname, 0,20)).'...' : $request->actualcomponentname;
            if($deleted){
                $onerow['responded'] = 1;
            }

            $record[] = $onerow;
        }
        $approve_capability=0;
        if(has_capability('local/request:approverecord',$systemcontext)){
          $approve_capability=1;
        }

        $deny_capability=0;
        if(has_capability('local/request:denyrecord',$systemcontext)){
          $deny_capability=1;
        }
          return array('deny_capability' => $deny_capability,'approve_capability' => $approve_capability, 'record' => $record, 'requestcount' => $requestcount);
        }


    }
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Renderer base.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $DB, $USER;

        $data = requestexporter::exporter($this->requestlist,$this->tab);

        return $data;

    }


} // end of class
