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

/**
 * program View
 *
 * @package    local_program
 * @copyright  2018 Arun Kumar M <arun@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_program;
defined('MOODLE_INTERNAL') || die();
use context_system;
use stdClass;
use moodle_url;
use completion_completion;
use html_table;
use html_writer;
use core_component;
use \local_courses\action\insert as insert;

require_once($CFG->dirroot . '/local/program/lib.php');
if (file_exists($CFG->dirroot . '/local/lib.php')) {
  require_once($CFG->dirroot . '/local/lib.php');
}
require_once($CFG->dirroot . '/local/costcenter/lib.php');
//use \local_program\notifications_emails as programnotifications_emails;
// program
define('PROGRAM_NEW', 0);
define('PROGRAM_COMPLETED', 2);
// Types
define('PROGRAM', 1);

class program {
    /**
     * Manage program (Create or Update the program)
     * @method manage_program
     * @param  Object           $data Clasroom Data
     * @return Integer               program ID
     */
    public function manage_program($program) {
        global $DB, $USER;
        $program->shortname = $program->name;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
        if(!$program->selfenrol){
          $program->approvalreqd = 0;
        }
        if (empty($program->trainers)) {
            $program->trainers = null;
        }
        if (empty($program->capacity) || $program->capacity == 0) {
            $program->capacity = 0;
        }

        // added for OL-2104 for not saving and displaying the file
        file_save_draft_area_files($program->programlogo, $categorycontext->id, 'local_program', 'programlogo', $program->programlogo);
        $program->startdate = 0;
        $program->enddate = 0;
        $program->description = $program->cr_description['text'];

        try {
            if ($program->id > 0) {
                $program->timemodified = time();
                $program->usermodified = $USER->id;

                if($program->form_status == 1) {

                    if($program->map_certificate == 1){
                      $program->certificateid = $program->certificateid;
                    }else{
                      $program->certificateid = null;
                    }
                }


                 $open_path=$DB->get_field('local_program', 'open_path', array('id' => $program->id));
                list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$open_path);

                if($program->open_costcenterid !=$org){

                     local_costcenter_get_costcenter_path($program);

                }

                $DB->update_record('local_program', $program);
                // $this->program_set_events($program); // Added by sreenivas.
                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $program->id
                );
                // Trigger program updated event.

                $event = \local_program\event\program_updated::create($params);
                $event->add_record_snapshot('local_program', $program);
                $event->trigger();

                // Update program tags.
                // if (isset($program->tags)) {
                //     \local_tags_tag::set_item_tags('local_program', 'program', $program->id, $categorycontext, $program->tags, 0, $program->costcenter, $program->department);
                // }
            } else {
                $program->status = 0;
                $program->timecreated = time();
                $program->usercreated = $USER->id;
                local_costcenter_get_costcenter_path($program);

                $program->id = $DB->insert_record('local_program', $program);

                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $program->id
                );

                $event = \local_program\event\program_created::create($params);
                $event->add_record_snapshot('local_program', $program);
                $event->trigger();

                // Update program tags.
                // if (isset($program->tags)) {
                //     \local_tags_tag::set_item_tags('local_program', 'program', $program->id, $categorycontext, $program->tags, 0, $program->costcenter, $program->department);
                // }

                $program->shortname = 'program' . $program->id;
                $DB->update_record('local_program', $program);
                if ($program->id) {
                    $leveldata = new stdClass();
                    $leveldata->programid = $program->id;
                    $this->manage_program_stream_levels($leveldata, true);
                }
            }
            $program->totallevels = $DB->count_records('local_program_levels', array('programid' => $program->id));
            $DB->update_record('local_program', $program);
        } catch (dml_exception $ex) {
            print_error($ex);
        }
        return $program->id;
    }

    /**
    * This creates new events given as timeopen and closeopen by program.
    *
    * @global object
    * @param object $program
    * @return void
    */
   function program_set_events($program) {
        global $DB, $CFG, $USER;
        // Include calendar/lib.php.

        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($program->id);

        require_once($CFG->dirroot . '/calendar/lib.php');

        // evaluation start calendar events.
        $eventid = $DB->get_field('event', 'id',
               array('modulename' => '0', 'instance' => 0, 'plugin'=> 'local_program',
                'plugin_instance' => $program->id, 'eventtype' => 'open',
                'local_eventtype' => 'open'));

        if (isset($program->startdate) && $program->startdate > 0) {
           $event = new stdClass();
           $event->eventtype    = 'open';
           $event->categoryid   = $categorycontext->instanceid;
           $event->type         = empty($program->enddate) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
           $event->name         = $program->name;
           $event->description  = $program->name;
           $event->timestart    = $program->startdate;
           $event->timesort     = $program->startdate;
           $event->visible      = 1;
           $event->timeduration = 0;
           $event->plugin_instance = $program->id;
           $event->plugin = 'local_program';
           $event->local_eventtype    = 'open';
           $event->relateduserid    = $USER->id;
           if ($eventid) {
               // Calendar event exists so update it.
               $event->id = $eventid;
               $calendarevent = \calendar_event::load($event->id);
               $calendarevent->update($event);
           } else {
               // Event doesn't exist so create one.
               $event->courseid     = 1;
               $event->groupid      = 0;
               $event->userid       = 0;
               $event->modulename   = 0;
               $event->instance     = 0;
               $event->eventtype    = 'open';;
               \calendar_event::create($event);
           }
       } else if ($eventid) {
           // Calendar event is on longer needed.
           $calendarevent = \calendar_event::load($eventid);
           $calendarevent->delete();
       }

       // evaluation close calendar events.
       $eventid = $DB->get_field('event', 'id',
               array('modulename' => '0', 'instance' => 0, 'plugin' => 'local_program',
                'plugin_instance' => $program->id, 'eventtype' => 'close',
                'local_eventtype' => 'close'));

       if (isset($program->enddate) && $program->enddate > 0) {
           $event = new stdClass();
           $event->type         = CALENDAR_EVENT_TYPE_ACTION;
           $event->eventtype    = 'close';
           $event->categoryid   = $categorycontext->instanceid;
           $event->name         = $program->name;
           $event->description  = $program->name;
           $event->timestart    = $program->enddate;
           $event->timesort     = $program->enddate;
           $event->visible      = 1;
           $event->timeduration = 0;
           $event->plugin_instance = $program->id;
           $event->plugin = 'local_program';
           $event->local_eventtype    = 'close';
           $event->relateduserid    = $USER->id;
           if ($eventid) {
               // Calendar event exists so update it.
               $event->id = $eventid;
               $calendarevent = \calendar_event::load($event->id);
               $calendarevent->update($event);
           } else {
               // Event doesn't exist so create one.
               $event->courseid     = 1;
               $event->groupid      = 0;
               $event->userid       = 0;
               $event->modulename   = 0;
               $event->instance     = 0;
               \calendar_event::create($event);
           }
       } else if ($eventid) {
           // Calendar event is on longer needed.
           $calendarevent = \calendar_event::load($eventid);
           $calendarevent->delete();
       }
    }
    public function manage_program_level_completions($programid, $levelid, $courses = null,$validateddata = null) {
        global $DB, $USER;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($data->programid);
        if(is_null($courses)){
            $courses = $DB->get_records_menu('local_program_level_courses',
            array('programid' => $programid, 'levelid' => $levelid), '', 'id, courseid');
        }
        $bclcomptlcheck = $DB->record_exists('local_bcl_cmplt_criteria',
            array('programid' => $programid, 'levelid' => $levelid));
        if ($bclcomptlcheck) {
            $completions = $DB->get_record('local_bcl_cmplt_criteria',
                array('programid' => $programid, 'levelid' => $levelid));
        } else {
            $completions = new stdClass();
            $completions->programid = $programid;
            $completions->levelid = $levelid;
        }

        if (!empty($courses) && is_array($courses)) {
            $completions->courseids = implode(', ', array_values($courses));

        } else {
            $completions->courseids = null;
        }

        if(!isset($validateddata->coursetracking) || empty($validateddata->coursetracking)){
          $completions->coursetracking = 'AND';
        }else{
          $completions->coursetracking = $validateddata->coursetracking;
        }
        try {
            if ($completions->id > 0) {
                $completions->timemodified = time();
                $completions->usermodified = $USER->id;
                $DB->update_record('local_bcl_cmplt_criteria', $completions);
                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $completions->id,
                    'other' => array('programid' => $completions->programid,
                                             'levelid' => $completions->levelid)
                );
                $event = \local_program\event\program_completions_settings_updated::create($params);
                $event->add_record_snapshot('local_bcl_cmplt_criteria', $completions->programid);
                $event->trigger();
            } else {
                $completions->timecreated = time();
                $completions->usercreated = $USER->id;

                $completions->id = $DB->insert_record('local_bcl_cmplt_criteria', $completions);
                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $completions->id,
                    'other' => array('programid' => $completions->programid,
                                             'levelid' => $completions->levelid)
                );
                $event = \local_program\event\program_completions_settings_created::create($params);
                $event->add_record_snapshot('local_bcl_cmplt_criteria', $completions);
                $event->trigger();
            }
        } catch (dml_exception $ex) {
            print_error($ex);
        }
        return $completions->id;
    }
    public function program_completion_settings($data){
        global $DB, $USER;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($data->programid);
        try {
            $completions = $DB->get_record('local_bc_completion_criteria', array('programid' => $data->programid));
            if(empty($completions)){
                $completions = new \stdClass();
                $completions->programid = $data->programid;
            }
            $completions->levelids = implode(',', $data->levelids);
            $completions->leveltracking = $data->leveltracking;
            if ($completions->id > 0) {
                $completions->timemodified = time();
                $completions->usermodified = $USER->id;
                $DB->update_record('local_bc_completion_criteria', $completions);
                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $completions->id,
                    'other' => array('programid' => $completions->programid)
                );
                $event = \local_program\event\program_completions_settings_updated::create($params);
                $event->add_record_snapshot('local_bc_completion_criteria', $completions->programid);
                $event->trigger();
            } else {
                $completions->timecreated = time();
                $completions->usercreated = $USER->id;
                $completions->id = $DB->insert_record('local_bc_completion_criteria', $completions);
                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $completions->id,
                    'other' => array('programid' => $completions->programid)
                );
                $event = \local_program\event\program_completions_settings_created::create($params);
                $event->add_record_snapshot('local_bc_completion_criteria', $completions);
                $event->trigger();
            }
        } catch (dml_exception $ex) {
            print_error($ex);
        }
        return true;
    }
    /**
     * programs
     * @method programs
     * @param  Object     $stable Datatable fields
     * @return Array  programs and totalprogramcount
     */
    public function programs($stable, $request = false,$program = null,$status = null) {


        global $DB, $USER;
        $params = array();
        $programs = array();
        $programscount = 0;
        $concatsql = '';

        // if (isset($stable->programid) && $stable->programid > 0) {

        //     $categorycontext = (new \local_program\lib\accesslib())::get_module_context($stable->programid);
        // }else{

            $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

        // }

        if (!empty($stable->search)) {
            $fields = array("bc.name");
            // $fields = implode(" LIKE :search1 OR ", $fields);
            $fields .= " LIKE :search2 ";
            // $params['search1'] = '%' . $stable->search . '%';
            $params['search2'] = '%' . $stable->search . '%';
            $concatsql .= " AND ".$DB->sql_like('bc.name', ':search2', false);

        }

        if (!is_siteadmin() &&(has_capability('local/program:manageprogram', $categorycontext))) {

            if (has_capability('local/program:trainer_viewprogram', $categorycontext)) {
                $myprograms = $DB->get_records_menu('local_bc_course_sessions',
                    array('trainerid' => $USER->id), 'id', 'id, programid');
                if (!empty($myprograms)) {
                    $myprograms = implode(', ', $myprograms);
                    $concatsql .= " AND bc.id IN ( $myprograms ) and bc.visible=1";
                } else {
                    return compact('programs', 'programscount');
                }
            }else{

                $concatsql .= (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='bc.open_path');

            }
        } else if (!is_siteadmin()) {
            $myprograms = $DB->get_records_menu('local_program_users',
                array('userid' => $USER->id), 'id', 'id, programid');
            if (isset($stable->programid) && !empty($stable->programid)) {
                $userenrolstatus = $DB->record_exists('local_program_users',
                    array('programid' => $stable->programid, 'userid' => $USER->id));
                $status = $DB->get_field('local_program', 'status',
                    array('id' => $stable->programid));

                   $costcenterpathconcatsql = (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path');

                    $programsql = "SELECT lp.*
                                        FROM {local_program} AS lp WHERE lp.id = $stable->programid $costcenterpathconcatsql ";

                    $program_costcenter = $DB->get_record_sql($programsql);

                if ($userenrolstatus) {

                    if (!empty($myprograms)) {
                        $myprograms = implode(', ', $myprograms);
                        $concatsql .= " AND bc.id IN ( $myprograms ) and bc.visible=1";
                    } else {
                        return compact('programs', 'programscount');
                    }
                }else {

                    return compact('programs', 'programscount');

                }
            } else {
                if (!empty($myprograms)) {
                    $myprograms = implode(', ', $myprograms);
                    $concatsql .= " AND bc.id IN ( $myprograms ) and bc.visible=1";
                } else {
                    return compact('programs', 'programscount');
                }
            }
        }
        if (isset($stable->programid) && $stable->programid > 0) {
            $concatsql .= " AND bc.id = :programid";
            $params['programid'] = $stable->programid;
        }

        if($program != NULL && $program != 'null' && $program > 0) {

          if(is_array($program)){

            $program = implode(',', $program);

          }

          $concatsql .= " AND bc.id IN ($program) ";
        }
        if(!empty($stable->costcenterid)){

            $organizations = explode(',', $stable->costcenterid);
            $orgsql = [];
            foreach($organizations AS $organisation){
                $orgsql[] = " concat('/',bc.open_path,'/') LIKE :organisationparam_{$organisation}";
                $params["organisationparam_{$organisation}"] = '%'.$organisation.'%';
            }
            if(!empty($orgsql)){
                $concatsql .= " AND ( ".implode(' OR ', $orgsql)." ) ";
            }

        }
        if (!empty($stable->departmentid)) {
            $departments = explode(',', $stable->departmentid);
            $deptsql = [];
            foreach($departments AS $department){
                $deptsql[] = " concat('/',bc.open_path,'/') LIKE :departmentparam_{$department}";
                $params["departmentparam_{$department}"] = '%'.$department.'%';
            }
            if(!empty($deptsql)){
                $concatsql .= " AND ( ".implode(' OR ', $deptsql)." ) ";
            }
        }
        if (!empty($stable->subdepartmentid)) {
            $subdepartments = explode(',', $stable->subdepartmentid);
            $subdeptsql = [];
            foreach($subdepartments AS $subdepartment){
                $subdeptsql[] = " concat('/',bc.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
                $params["subdepartmentparam_{$subdepartment}"] = '%'.$subdepartment.'%';
            }
            if(!empty($subdeptsql)){
                $concatsql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
            }
        }
        if (!empty($stable->l4department)) {
            $subdepartments = explode(',', $stable->l4department);
            $subdeptsql = [];
            foreach($subdepartments AS $department4level){
                $subdeptsql[] = " concat('/',bc.open_path,'/') LIKE :department4levelparam_{$department4level}";
                $params["department4levelparam_{$department4level}"] = '%'.$department4level.'%';
            }
            if(!empty($subdeptsql)){
                $concatsql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
            }
        }
        if (!empty($stable->l5department)) {
            $subdepartments = explode(',', $stable->l5department);
            $subdeptsql = [];
            foreach($subdepartments AS $department5level){
                $subdeptsql[] = " concat('/',bc.open_path,'/') LIKE :department5levelparam_{$department5level}";
                $params["department5levelparam_{$department5level}"] = '%'.$department5level.'%';
            }
            if(!empty($subdeptsql)){
                $concatsql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
            }
        }
        if(!empty($stable->categories)){

            if(is_array($program)){

                $filtercategories = explode(',', $stable->categories);

            }else{

                $filtercategories = $stable->categories;
            }
            list($filtercategoriessql, $filtercategoriesparams) = $DB->get_in_or_equal($filtercategories, SQL_PARAMS_NAMED, 'categories', true, false);
            $params = array_merge($params, $filtercategoriesparams);
            $concatsql .= " AND bc.open_categoryid $filtercategoriessql";
        }
        if(!empty($status)){
          $filterstatus = explode(',',$status);
          if(!(in_array('active',$filterstatus) && in_array('inactive',$filterstatus))){
              if(in_array('active' ,$filterstatus)){
                  $concatsql .= " AND bc.visible = 1 ";
              }else if(in_array('inactive' ,$filterstatus)){
                  $concatsql .= " AND bc.visible = 0 ";
              }
          }
        }
        //end
        $countsql = "SELECT COUNT(bc.id) ";

        $fromsql = "SELECT bc.*, (SELECT COUNT(DISTINCT cu.userid)
                                  FROM {local_program_users} AS cu JOIN {user} As u ON cu.userid = u.id
                                  WHERE cu.programid = bc.id AND u.id > 2 AND u.suspended = 0 AND u.deleted = 0
                              ) AS enrolled_users, (SELECT COUNT(DISTINCT bu.userid)
                                  FROM {local_program_users} AS bu
                                  WHERE bu.programid = bc.id AND bu.completion_status = 1 AND bu.completiondate > 0
                              ) AS completed_users";


        $sql = " FROM {local_program} AS bc
                WHERE 1 = 1 ";
        $sql .= $concatsql;


        if (isset($stable->programid) && $stable->programid > 0) {

            $programs = $DB->get_record_sql($fromsql . $sql, $params);

        } else {
            try {

                $programscount = $DB->count_records_sql($countsql . $sql, $params);
                if ($stable->thead == false) {
                    $sql .= " ORDER BY bc.id DESC";

                    if ($request == true) {
                        $programs = $DB->get_record_sql($fromsql . $sql, $params, $stable->start, $stable->length);
                    } else {
                        $programs = $DB->get_records_sql($fromsql . $sql, $params, $stable->start, $stable->length);
                    }
                }
            } catch (dml_exception $ex) {
                $programscount = 0;
            }
        }
        if (isset($stable->programid) && $stable->programid > 0) {
            return $programs;
        } else {
            return compact('programs', 'programscount');
        }
    }
    /**
     * [program_add_assignusers description]
     * @method program_add_assignusers
     * @param  [type]                    $programid   [description]
     * @param  [type]                    $userstoassign [description]
     * @return [type]                                   [description]
     */
    public function program_add_assignusers($programid, $userstoassign) {
        global $DB, $USER,$CFG;
        if (file_exists($CFG->dirroot . '/local/lib.php')) {
            require_once($CFG->dirroot . '/local/lib.php');
        }
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        // require_once($CFG->dirroot . '/local/program/notifications_emails.php');
        // $emaillogs = new programnotifications_emails();
        $emaillogs = new \local_program\notification();
        $allow = true;
        $type = 'program_enrol';
        $dataobj = $programid;
        $fromuserid = $USER->id;
        if ($allow) {
           $progress       = 0;
           // $local_program = $DB->get_record_sql("SELECT * FROM {local_program} where id = $programid");
           $local_program = $DB->get_record('local_program', array('id' => $programid));
            foreach ($userstoassign as $key => $adduser) {

                    $progress++;

                    $programuser = new stdClass();
                    $programuser->programid = $programid;
                    $programuser->courseid = 0;
                    $programuser->userid = $adduser;
                    $programuser->supervisorid = 0;
                    $programuser->prefeedback = 0;
                    $programuser->postfeedback = 0;
                    $programuser->trainingfeedback = 0;
                    $programuser->confirmation = 0;
                    $programuser->completion_status = 0;
                    $programuser->completiondate = 0;
                    $programuser->usercreated = $USER->id;
                    $programuser->timecreated = time();
                    $programuser->usermodified = $USER->id;
                    $programuser->timemodified = time();
                    try {
                        $programuser->id = $DB->insert_record('local_program_users',
                            $programuser);

                        $params = array(
                            'context' => $categorycontext,
                            'objectid' => $programuser->id,
                            'relateduserid' => $programuser->id,
                            'other' => array('programid' => $programid)
                        );

                        $event = \local_program\event\program_users_enrol::create($params);
                        $event->add_record_snapshot('local_program_users', $programuser);
                        $event->trigger();

                        if ($local_program->status == 0) {
                            // $email_logs = $emaillogs->program_emaillogs($type, $dataobj, $programuser->userid, $fromuserid);
                            $touser = \core_user::get_user($programuser->userid);
                            $email_logs = $emaillogs->program_notification($type, $touser, $USER, $local_program);
                        }
                    } catch (dml_exception $ex) {
                        print_error($ex);
                    }

            }
            $program = new stdClass();
            $program->id = $programid;
            $program->totalusers = $DB->count_records('local_program_users',
                array('programid' => $programid));
            $DB->update_record('local_program', $program);

            $result              = new stdClass();
            $result->changecount = $progress;
            $result->program   = $local_program->name;
        }
        return $result;
    }
    /**
     * [program_remove_assignusers description]
     * @method program_remove_assignusers
     * @param  [type]                       $programid     [description]
     * @param  [type]                       $userstounassign [description]
     * @return [type]                                        [description]
     */
    public function program_remove_assignusers($programid, $userstounassign) {
        global $DB, $USER,$CFG;
        if (file_exists($CFG->dirroot . '/local/lib.php')) {
            require_once($CFG->dirroot . '/local/lib.php');
        }
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        // require_once($CFG->dirroot . '/local/program/notifications_emails.php');
        // $emaillogs = new programnotifications_emails();
        $emaillogs = new \local_program\notification();
        $programenrol = enrol_get_plugin('program');
        //$studentroleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $courses = $DB->get_records_menu('local_program_level_courses',
            array('programid' => $programid), 'id', 'id, courseid');
        $type = 'program_unenroll';
        $dataobj = $programid;
        $fromuserid = $USER->id;
        try {
          // $local_program = $DB->get_record_sql("SELECT * FROM {local_program} WHERE id = $programid");
          $local_program = $DB->get_record('local_program', array('id' => $programid));
            $progress= 0;
            foreach ($userstounassign as $key=>$removeuser) {
                    $progress++;
                    if ($local_program->status != 0) {
                        if (!empty($courses)) {
                            foreach ($courses as $course) {
                                if ($course > 0) {
                                    //$instance = $DB->get_record('enrol', array('courseid' => $course, 'enrol'=>'program'), '*', MUST_EXIST);
                                    //$programenrol->unenrol_user($instance, $removeuser, $instance->roleid, time());
                                    $unenrolprogramuser = $this->manage_bclevel_course_enrolments($course, $removeuser, $role = 'employee',$type = 'unenrol', $pluginname = 'program',$programid);
                                }
                            }
                        }
                    }
                $params = array(
                   'context' => $categorycontext,
                   'objectid' => $programid,
                   'relateduserid' => $removeuser,
                );

                $event = \local_program\event\program_users_unenrol::create($params);
                $event->add_record_snapshot('local_program_users', $programid);
                $event->trigger();
                $DB->delete_records('local_program_users',  array('programid' => $programid,
                    'userid' => $removeuser));
                if ($local_program->status == 0) {
                    // $email_logs = $emaillogs->program_emaillogs($type ,$dataobj, $removeuser, $fromuserid);
                    $touser = \core_user::get_user($removeuser);
                    $email_logs = $emaillogs->program_notification($type , $touser, $USER, $local_program);
                }
            }
            $program = new stdClass();
            $program->id = $programid;
            $program->totalusers = $DB->count_records('local_program_users',
                array('programid' => $programid));
            $DB->update_record('local_program', $program);

            $result              = new stdClass();
            $result->changecount = $progress;
            $result->program   = $local_program->name;
        } catch (dml_exception $ex) {
            print_error($ex);
        }
        return $result;
    }
    // OL-1042 Add Target Audience to programs//
    public function program_target_audience($program){
        global $DB;
        if ($program->id > 0) {            
            $program->open_group = !empty($program->open_group) ? implode(',', array_filter($program->open_group)) : NULL;
            if(!empty($program->open_group)) {
                $program->open_group = $program->open_group;
            } else {
                $program->open_group = NULL;
            }
            $program->open_hrmsrole    = (!empty($program->open_hrmsrole)) ? implode(',', array_filter($program->open_hrmsrole)) : null;
            if(!empty($program->open_hrmsrole)) {
                $program->open_hrmsrole = $program->open_hrmsrole;
            } else {
                $program->open_hrmsrole = NULL;
            }
            $program->open_designation = (!empty($program->open_designation)) ? implode(',', array_filter($program->open_designation)) : null;
            if(!empty($program->open_designation)) {
                $program->open_designation = $program->open_designation;
            } else {
                $program->open_designation = NULL;
            }
            $program->open_location    = (!empty($program->open_location)) ? implode(',', array_filter($program->open_location)) : null;
            if(!empty($program->open_location)) {
                $program->open_location = $program->open_location;
            } else {
                $program->open_location = NULL;
            }
             $open_path=$DB->get_field('local_program', 'open_path', array('id' => $program->id));
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$open_path);

            local_costcenter_get_costcenter_path($program);

            local_users_get_userprofile_datafields($program);
            $DB->update_record('local_program', $program);
        }
        return $program->id;
    }
    /**
     * [program_logo description]
     * @method program_logo
     * @param  integer        $programlogo [description]
     * @return [type]                        [description]
     */
    public function program_logo($programlogo = 0) {
        global $DB;
        $programlogourl = false;
        if ($programlogo > 0){
            $sql = "SELECT * FROM {files} WHERE itemid = $programlogo AND filename != '.'
            ORDER BY id DESC ";//LIMIT 1
            $programlogorecord = $DB->get_record_sql($sql);
        }
        if (!empty($programlogorecord)) {
          if ($programlogorecord->filearea == "programlogo") {
            $programlogourl = moodle_url::make_pluginfile_url($programlogorecord->contextid,
                $programlogorecord->component, $programlogorecord->filearea,
                $programlogorecord->itemid, $programlogorecord->filepath,
                $programlogorecord->filename);
          }
        }
        return $programlogourl;
    }
    /**
     * [manage_program_courses description]
     * @method manage_program_courses
     * @param  [type]                   $courses [description]
     * @return [type]                            [description]
     */
    public function manage_program_courses($courses) {
        global $DB, $USER;
        foreach ($courses->course as $course) {
            $programcourseexists = $DB->record_exists('local_program_level_courses',
                array('programid' => $courses->programid, 'levelid' => $courses->levelid, 'courseid' => $course));
            if (!empty($programcourseexists)) {
                continue;
            }
            $categorycontext = (new \local_program\lib\accesslib())::get_module_context($courses->programid);
            $programcourse = new stdClass();
            $programcourse->programid = $courses->programid;
            $programcourse->levelid = $courses->levelid;
            $programcourse->courseid = $course;
            $programcourse->timecreated = time();
            $programcourse->usercreated = $USER->id;
            $programcourse->id = $DB->insert_record('local_program_level_courses',
                $programcourse);
            $params = array(
                'context' => $categorycontext,
                'objectid' => $programcourse->id,
                'other' => array('programid' => $courses->programid,
                                 'levelid' => $courses->levelid)
            );

            $event = \local_program\event\bclevelcourse_created::create($params);
            $event->add_record_snapshot('local_program_level_courses', $programcourse);
            $event->trigger();

            $this->manage_program_level_completions($courses->programid, $courses->levelid);
        }
        $totalcourses = $DB->count_records('local_program_level_courses',
            array('programid' => $courses->programid, 'levelid' => $courses->levelid));
        $leveldata = new stdClass();
        $leveldata->id = $courses->levelid;
        $leveldata->programid = $courses->programid;
        $leveldata->totalcourses = $totalcourses;
        $leveldata->timemodified = time();
        $leveldata->usermodified = $USER->id;
        $DB->update_record('local_program_levels', $leveldata);
        $totalbccourses = $DB->count_records('local_program_level_courses',
            array('programid' => $courses->programid));
        $programdata = new stdClass();
        $programdata->id = $courses->programid;
        $programdata->totalcourses = $totalbccourses;
        $programdata->timemodified = time();
        $programdata->usermodified = $USER->id;
        $DB->update_record('local_program', $programdata);
        return true;
    }
    /**
     * [manage_program_course_enrolments description]
     * @method manage_program_course_enrolments
     * @param  [type]                             $cousre        [description]
     * @param  [type]                             $user          [description]
     * @param  string                             $roleshortname [description]
     * @param  string                             $type          [description]
     * @param  string                             $pluginname    [description]
     * @return [type]                                            [description]
     */
    public function manage_program_course_enrolments($cousre, $user, $roleshortname = 'employee',
        $type = 'enrol', $pluginname = 'program',$programid=null) {
        global $DB;
        return $this->manage_bclevel_course_enrolments($course, $user, $roleshortname,$type, $pluginname,$programid);
    }
    public function program_levels($programid) {
        global $DB, $USER;
        $programlevelssql = "SELECT bcl.id, bcl.level, bcl.position
                                FROM {local_program_levels} bcl
                                JOIN {local_program} bc ON bc.id = bcl.programid
                                WHERE bc.id = :programid";
        $programlevels = $DB->get_records_sql($programlevelssql, array('programid' => $programid));
        return $programlevels;
    }
    /**
     * [program_courses description]
     * @method program_courses
     * @param  [type]            $programid [description]
     * @return [type]                         [description]
     */
    public function program_level_courses($programid, $levelid, $userview = false) {
        global $DB, $USER;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        if ($levelid > 0) {
            $params = array();
            $programcourses = array();

            $programcoursesssql = "SELECT bclc.id AS bclevelcourseid, bclc.programid,
                                    bclc.levelid, c.fullname AS course, c.*
                                      FROM {local_program_level_courses} bclc
                                      JOIN {course} c ON c.id = bclc.courseid
                                     WHERE bclc.programid = :programid
                                     AND bclc.levelid = {$levelid} ";
            if ($userview && !is_siteadmin() && !has_capability('local/program:createprogram', $categorycontext)) {
                $programcoursesssql .= " ORDER BY bclevelcourseid ASC";
            }
            $programlevelcourses = $DB->get_records_sql($programcoursesssql,
                array('programid' => $programid));
        }
        return $programlevelcourses;
    }
    /**
     * [update_program_status description]
     * @method update_program_status
     * @param  [type]                  $programid     [description]
     * @param  [type]                  $programstatus [description]
     * @return [type]                                   [description]
     */
    public function update_program_status($programid, $programstatus) {
        global $DB, $USER;
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);
        $program = new stdClass();
        $program->id = $programid;
        $program->status = $programstatus;
        if($programstatus == program_COMPLETED) {
            $activeusers = $DB->count_records('local_program_users', array('programid' => $programid,
                'completion_status' => 1));
            $program->activeusers = $activeusers;
            $totalusers = $DB->count_records('local_program_users', array('programid' => $programid));
            $program->totalusers = $totalusers;
        }
        $program->usermodified = $USER->id;
        $program->timemodified = time();
        $program->completiondate = time();
        try {
            $DB->update_record('local_program', $program);
            $params = array(
                    'context' => $categorycontext,
                    'objectid' => $programid
                );
            $event  = \local_program\event\program_completed::create($params);
            $event->add_record_snapshot('local_program', $programid);
            $event->trigger();
           //  $params = array(
           //     'context' => $categorycontext,
           //     'objectid' => $program->id
           //  );

           // $event = \local_program\event\program_updated::create($params);
           // $event->add_record_snapshot('local_program', $program->id);
           // $event->trigger();
        } catch (dml_exception $ex) {
            print_error($ex);
        }
        return true;
    }
    /**
     * [programusers description]
     * @method programusers
     * @param  [type]         $programid [description]
     * @param  [type]         $stable      [description]
     * @return [type]                      [description]
     */
    public function programusers($programid, $stable) {
        global $DB, $USER;
        $params = array();
        $programusers = array();
        $concatsql = '';

        if (!empty($stable->search)) {
            $fields = array(
                0 => 'u.firstname',
                1 => 'u.lastname',
                2 => 'u.email',
                3 => 'u.idnumber'
            );
            $fields = implode(" LIKE '%" . $stable->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $stable->search . "%' ";
            $concatsql .= " AND ($fields) ";
        }
        $countsql = "SELECT COUNT(cu.id) ";
        $fromsql = "SELECT u.*, cu.completion_status ";
        $sql = " FROM {user} AS u
                 JOIN {local_program_users} AS cu ON cu.userid = u.id
                 JOIN {local_program} AS c ON c.id = cu.programid
                WHERE c.id = {$programid} AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND u.id > 2";
        $sql .= $concatsql;
        try {


            $programuserscount = $DB->count_records_sql($countsql. $sql, $params);
            if ($stable->thead == false) {
                $sql .= " ORDER BY id ASC";
                $programusers = $DB->get_records_sql($fromsql . $sql, $params, $stable->start, $stable->length);
            }
        } catch (dml_exception $ex) {
            $programuserscount = 0;
        }
        return compact('programusers', 'programuserscount');
    }
    /**
     * [program_completions description]
     * @method program_completions
     * @param  [type]                $programid [description]
     * @return [type]                             [description]
     */
    public function program_completions($programid){
        global $DB, $USER, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_role.php');

        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($programid);

        $programuserssql = "SELECT cu.*
                                FROM {user} AS u
                                JOIN {local_program_users} AS cu ON cu.userid = u.id
                                WHERE u.id > 2 AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0
                                AND cu.programid = {$programid} ";
        $programusers = $DB->get_records_sql($programuserssql);


        $programcoursessql = "SELECT c.*
                                  FROM {course} AS c
                                  JOIN {enrol} AS en on en.courseid=c.id
                                        and en.enrol LIKE 'program' and en.status = 0
                                  JOIN {local_program_level_courses} AS cc ON cc.courseid = c.id
                                 WHERE cc.programid = {$programid}";

        $programcourses = $DB->get_records_sql($programcoursessql);


        if (!empty($programusers)) {
            foreach ($programusers as $programuser) {
                $usercousrecompletionstatus =array();

                foreach($programcourses as $programcourse) {
                    $params = array(
                        'userid'    => $programuser->userid,
                        'course'    => $programcourse->id
                    );
                    $ccompletion = new completion_completion($params);

                    $ccompletionis_complete =  $ccompletion->is_complete();

                    if ($ccompletionis_complete) {
                        $usercousrecompletionstatus[]= true;
                    }
                }
                if (empty($program_completiondata) || ($program_completiondata->coursetracking == null)) {

                    if ((count($usercousrecompletionstatus)==count($programcourses))) {
                        $programuser->completion_status = 1;
                    } else {
                        $programuser->completion_status = 0;
                    }
                }else{
                    $programuser->completion_status = 0;

                    if ($program_completiondata->coursetracking=="AND") {
                        $programuser->completion_status = 0;
                    }

                    if($program_completiondata->coursetracking == "OR") {
                        $programuser->completion_status = 0;
                    }
                }

                $programuser->usermodified = $USER->id;
                $programuser->timemodified = time();
                $programuser->completiondate = time();
                $DB->update_record('local_program_users', $programuser);
                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $programuser->id
                );

                $event = \local_program\event\program_users_updated::create($params);
                $event->add_record_snapshot('local_program', $programid);
                $event->trigger();
            }
        }
        return true;
    }
    public function programcategories($formdata){
        global $DB;
        if ($formdata->id) {
            $DB->update_record('local_program_categories', $formdata);
        } else {
            $DB->insert_record('local_program_categories', $formdata);
        }
    }
    /**
     * [select_to_and_from_users description]
     * @param  [type]  $type       [description]
     * @param  integer $programid [description]
     * @param  [type]  $params     [description]
     * @param  integer $total      [description]
     * @param  integer $offset1    [description]
     * @param  integer $perpage    [description]
     * @param  integer $lastitem   [description]
     * @return [type]              [description]
     */
    public function select_to_and_from_users($type = null, $programid = 0, $params, $total = 0, $offset1 = -1, $perpage = -1, $lastitem = 0) {

        global $DB, $USER;
        $program = $DB->get_record('local_program', array('id' => $programid));

        $params['suspended'] = 0;
        $params['deleted'] = 0;

        if ($total == 0) {
            $sql = "SELECT u.id, concat(u.firstname,' ',u.lastname,' ','(',u.email,')',' ','(',u.open_employeeid,')') as fullname";
        } else {
            $sql = "SELECT count(u.id) as total";
        }
        $sql .= " FROM {user} AS u
                 WHERE  u.id > 2 AND u.suspended = :suspended
                                     AND u.deleted = :deleted ";
        if ($lastitem != 0) {
            $sql.=" AND u.id > $lastitem";
        }

        if(is_siteadmin()){

            $sql .= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path',$program->open_path,'lowerandsamepath');

        }else{

            $sql .= (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

        }

        $sql .= (new \local_users\lib\accesslib())::get_userprofilematch_concatsql($program);


        $sql .= " AND u.id <> $USER->id ";
        if (!empty($params['email'])) {
            $sql .= " AND u.id IN ({$params['email']})";
        }
        if (!empty($params['uname'])) {
            $sql .= " AND u.id IN ({$params['uname']})";
        }
        if (!empty($params['idnumber'])) {
            $sql .= " AND u.id IN ({$params['idnumber']})";
        }
        if(!empty($params['organization'])){
            $organizations = explode(',', $params['organization']);
            $orgsql = [];
            foreach($organizations AS $organisation){
                $orgsql[] = " concat('/',u.open_path,'/') LIKE :organisationparam_{$organisation}";
                $params["organisationparam_{$organisation}"] = '%/'.$organisation.'/%';
            }
            if(!empty($orgsql)){
                $sql .= " AND ( ".implode(' OR ', $orgsql)." ) ";
            }
        }
        if(!empty($params['department'])){
            $depts = explode(',', $params['department']);
            $deptsql = [];
            foreach($depts AS $dept){
                $deptsql[] = " concat('/',u.open_path,'/') LIKE :deptparam_{$dept}";
                $params["deptparam_{$dept}"] = '%/'.$dept.'/%';
            }
            if(!empty($deptsql)){
                $sql .= " AND ( ".implode(' OR ', $deptsql)." ) ";
            }
        }
        if(!empty($params['subdepartment'])){
            $subdepts = explode(',', $params['subdepartment']);
            $subdeptsql = [];
            foreach($subdepts AS $subdept){
                $subdeptsql[] = " concat('/',u.open_path,'/') LIKE :subdeptparam_{$subdept}";
                $params["subdeptparam_{$subdept}"] = '%/'.$subdept.'/%';
            }
            if(!empty($subdeptsql)){
                $sql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
            }
        }

        if(!empty($params['department4level'])){
            $depts4 = explode(',', $params['department4level']);
            $depts4sql = [];
            foreach($depts4 AS $dept4){
                $depts4sql[] = " concat('/',u.open_path,'/') LIKE :dept4param_{$dept4}";
                $params["dept4param_{$dept4}"] = '%/'.$dept4.'/%';
            }
            if(!empty($depts4sql)){
                $sql .= " AND ( ".implode(' OR ', $depts4sql)." ) ";
            }
        }

        if(!empty($params['department5level'])){
            $depts5 = explode(',', $params['department5level']);
            $depts5sql = [];
            foreach($depts5 AS $dept5){
                $depts5sql[] = " concat('/',u.open_path,'/') LIKE :dept5param_{$dept5}";
                $params["dept5param_{$dept5}"] = '%/'.$dept5.'/%';
            }
            if(!empty($depts5sql)){
                $sql .= " AND ( ".implode(' OR ', $depts5sql)." ) ";
            }
        }
        if(!empty($params['states'])){
            $states = explode(',', $params['states']);
            $statessql = [];
            foreach($states AS $state){
                $statessql[] = " concat('/',u.open_path,'/') LIKE :stateparam_{$state}";
                $params["stateparam_{$state}"] = '%/'.$state.'/%';
            }
            if(!empty($statessql)){
                $sql .= " AND ( ".implode(' OR ', $statessql)." ) ";
            }
        }
        if(!empty($params['district'])){
            $district = explode(',', $params['district']);
            $districtsql = [];
            foreach($district AS $dist){
                $districtsql[] = " concat('/',u.open_path,'/') LIKE :districtparam_{$dist}";
                $params["districtparam_{$dist}"] = '%/'.$dist.'/%';
            }
            if(!empty($districtsql)){
                $sql .= " AND ( ".implode(' OR ', $districtsql)." ) ";
            }
        }
        if(!empty($params['subdistrict'])){
            $subdistrict = explode(',', $params['subdistrict']);
            $subdistrictsql = [];
            foreach($subdistrict AS $subdist){
                $subdistrictsql[] = " concat('/',u.open_path,'/') LIKE :subdistrictparam_{$subdist}";
                $params["subdistrictparam_{$subdist}"] = '%/'.$subdist.'/%';
            }
            if(!empty($subdistrictsql)){
                $sql .= " AND ( ".implode(' OR ', $subdistrictsql)." ) ";
            }
        }
        if(!empty($params['village'])){
            $village = explode(',', $params['village']);
            $villagesql = [];
            foreach($village AS $vlg){
                $villagesql[] = " concat('/',u.open_path,'/') LIKE :villageparam_{$vlg}";
                $params["villageparam_{$vlg}"] = '%/'.$vlg.'/%';
            }
            if(!empty($villagesql)){
                $sql .= " AND ( ".implode(' OR ', $villagesql)." ) ";
            }
        }

        if (!empty($params['hrmsrole'])) {

            $hrmsroles = explode(',',$params['hrmsrole']);
            list($hrmsrolesql, $hrmsroleparams) = $DB->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole');
            $params = array_merge($params,$hrmsroleparams);            
            $sql .= " AND u.open_hrmsrole {$hrmsrolesql} ";
        }
        if (!empty($params['groups'])) {
            $sql .= " AND u.id IN (select cm.userid from {cohort_members} cm, {user} u where u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0 AND cm.cohortid IN ({$params['groups']}))";
        }

        if ($type == 'add') {
            $sql .= " AND u.id NOT IN (SELECT lcu.userid as userid
                                       FROM {local_program_users} AS lcu
                                       WHERE lcu.programid = $programid)";
        } else if ($type == 'remove') {
            $sql .= " AND u.id IN (SELECT lcu.userid as userid
                                       FROM {local_program_users} AS lcu
                                       WHERE lcu.programid = $programid)";
        }

        $sql .= " AND u.id NOT IN (SELECT lcu.trainerid as userid
                                       FROM {local_program_trainers} AS lcu
                                       WHERE lcu.programid = $programid)";

        $order = ' ORDER BY u.id ASC ';
        if ($perpage != -1) {
            // $order .= "LIMIT $perpage";
          $limitnumber = $perpage;
        }else{
          $limitnumber = 0;
        }

        if ($total == 0) {
            $availableusers = $DB->get_records_sql_menu($sql . $order, $params, 0, $limitnumber);
        } else {
            $availableusers = $DB->count_records_sql($sql, $params);
        }
        return $availableusers;
    }
    /**
     * [program_self_enrolment description]
     * @param  [type] $programid   [description]
     * @param  [type] $programuser [description]
     * @return [type]                [description]
     */
    public function program_self_enrolment($programid,$programuser){
        global $DB;
        $program_capacity_check=$this->program_capacity_check($programid);
        if (!$program_capacity_check) {
            $this->program_add_assignusers($programid,array($programuser));
            // $programcourses = $DB->get_records_menu('local_program_level_courses', array('programid' => $programid), 'id', 'id, courseid');
            // foreach($programcourses as $programcourse) {
            //    $this->manage_program_course_enrolments($programcourse, $programuser);
            // }
        }
    }

    public function program_capacity_check($programid) {
        global $DB;
        $return             = false;
        $programcapacity = $DB->get_field('local_program', 'capacity', array(
            'id' => $programid
        ));
        $enrolledusers     = $DB->count_records('local_program_users', array(
            'programid' => $programid
        ));
        if ($programcapacity <= $enrolledusers && !empty($programcapacity) && $programcapacity != 0) {
            $return = true;
        }
        return $return;
    }

    /**
     * [function to get user enrolled programs count]
     * @param  [INT] $userid [id of the user]
     * @return [INT]         [count of the programs enrolled]
     */
    public function enrol_get_users_programs_count($userid) {
        global $DB;
        $program_sql = "SELECT count(id)
                           FROM {local_program_users}
                          WHERE userid = :userid";
        $program_count = $DB->count_records_sql($program_sql, array('userid' => $userid));
        return $program_count;
    }
    /**
     * [function to get user enrolled programs ]
     * @param  [int] $userid [id of the user]
     * @return [object]         [programs object]
     */
    public function enrol_get_users_programs($userid) {
        global $DB;
        $program_sql = "SELECT lc.id, lc.name, lc.description
                           FROM {local_program} AS lc
                           JOIN {local_program_users} AS lcu ON lcu.programid = lc.id
                          WHERE userid = :userid";
        $programs = $DB->get_records_sql($program_sql, array('userid' => $userid));
        return $programs;
    }

    public function manage_program_stream_levels($level, $autocreate = false) {
        global $DB, $USER;

        $level->description = $level->level_description['text'];
        $position = $DB->count_records('local_program_levels', array('programid' => $level->programid));

        $categorycontext = (new \local_program\lib\accesslib())::get_module_context($level->programid);

        $level->position = $position;
        try {
            if ($level->id > 0) {
                $level->usermodified = $USER->id;
                $level->timemodified = time();
                $DB->update_record('local_program_levels', $level);
                $params = array(
                    'context' => $categorycontext,
                    'objectid' => $level->id,
                    'other' =>array('programid' => $level->programid)
                );

                $event = \local_program\event\level_updated::create($params);
                $event->add_record_snapshot('local_program_levels', $level);
                $event->trigger();
            } else {
                if ($autocreate) {
                    $records = array();
                    for ($i = 0; $i < 7; $i++) {
                        ${'record' . $i} = new stdClass();
                        ${'record' . $i}->programid = $level->programid;
                        ${'record' . $i}->level = 'Level ' . $i;
                        ${'record' . $i}->description = '';
                        ${'record' . $i}->position = $i;
                        ${'record' . $i}->usercreated = $USER->id;
                        ${'record' . $i}->timecreated = time();
                        $record = ${'record' . $i};
                        $levelid=$DB->insert_record('local_program_levels', $record);
                        $this->manage_program_level_completions($level->programid, $levelid, '', $formdata);
                    }
                    return true;
                } else {
                    $level->usercreated = $USER->id;
                    $level->timecreated = time();
                    $level->id = $DB->insert_record('local_program_levels', $level);
                    $this->manage_program_level_completions($level->programid, $level->id, '', $formdata);
                    $params = array(
                        'context' => $categorycontext,
                        'objectid' => $level->id,
                        'other' =>array('programid' => $level->programid)
                    );

                    $event = \local_program\event\level_created::create($params);
                    $event->add_record_snapshot('local_program_levels', $level);
                    $event->trigger();
                }
            }
        } catch (dml_exception $ex) {
            print_error($ex);
        }
        return $level->id;
    }
    /**
     * [unassign_courses_to_bclevel description]
     * @method unassign_courses_to_bclevel
     * @param  [type]                      $programid [description]
     * @param  [type]                      $levelid    [description]
     * @param  [type]                      $bclcid     [description]
     * @return [type]                                  [description]
     */
    public function unassign_courses_to_bclevel($programid, $levelid, $bclcid) {
        global $DB;
        $DB->delete_records('local_program_level_courses', array('id' => $bclcid,
            'levelid' => $levelid));

        $this->manage_program_level_completions($programid, $levelid);

        $totalcourses = $DB->count_records('local_program_level_courses',
            array('programid' => $programid, 'levelid' => $levelid));
        $leveldata = new stdClass();
        $leveldata->id = $levelid;
        $leveldata->programid = $programid;
        $leveldata->totalcourses = $totalcourses;
        $leveldata->timemodified = time();
        $leveldata->usermodified = $USER->id;
        $DB->update_record('local_program_levels', $leveldata);
        $totalbccourses = $DB->count_records('local_program_level_courses',
            array('programid' => $programid));
        $programdata = new stdClass();
        $programdata->id = $programid;
        $programdata->totalcourses = $totalbccourses;
        $programdata->timemodified = time();
        $programdata->usermodified = $USER->id;
        $DB->update_record('local_program', $programdata);

        return true;
    }
    /**
     * [manage_bclevel_course_enrolments description]
     * @method manage_bclevel_course_enrolments
     * @param  [type]                           $course     [description]
     * @param  [type]                           $user       [description]
     * @param  string                           $role       [description]
     * @param  string                           $type       [description]
     * @param  string                           $pluginname [description]
     * @return [type]                                       [description]
     */
    public function manage_bclevel_course_enrolments($course, $user, $role = 'employee',
        $type = 'enrol', $pluginname = 'program',$programid=null) {
        global $DB;

        $params =array(
            'courseid' => $course,
            'enrol' => $pluginname
        );
        if($programid !== null){
            $params['customint1']=$programid;
        }

        $enrolmethod = enrol_get_plugin($pluginname);

        $roleid      = $DB->get_field('role', 'id', array(
            'shortname' => $role
        ));


        if (!$DB->record_exists('enrol',$params)) {



            $courseobj = $DB->get_record('course', array('id' => $course));

            $fields = array('customint1'=>$programid,'roleid'=>$roleid);

            $enrolid = $enrolmethod->add_instance($courseobj,$fields);

        }else{

            $this->update_enrol_status($course,$programid,$status=ENROL_INSTANCE_ENABLED);
        }


        $instance    = $DB->get_record('enrol',$params , '*', MUST_EXIST);
        if (!empty($instance)) {
            if ($type == 'enrol') {
                $enrolmethod->enrol_user($instance, $user, $roleid, time());
            } else if ($type == 'unenrol') {
                $enrolmethod->unenrol_user($instance, $user, $roleid, time());
            }
        }
        return true;
    }
    public function update_enrol_status($course,$programid,$status){
        global $DB;
        $params =array('courseid' => $course, 'enrol' => 'program','customint1'=>$programid,'roleid'=>$DB->get_field('role','id',array('shortname' => 'employee')));
        $fields = array('customint1'=>$programid);
        $enrolid= $DB->get_field('enrol', 'id', $params);
        if(!empty($enrolid)){
            $arrayfields = array("id"=>$enrolid,"status"=>$status);
            $fields =array_merge($fields,$arrayfields);
            $update = $DB->update_record('enrol', $fields);
        }else{
            $update = false;
        }
        return  $update;
    }
    /**
     * [bclevel_completions description]
     * @method bclevel_completions
     * @param  [type]              $bclevel [description]
     * @param  [type]              $user    [description]
     * @return [type]                       [description]
     */
    public function bclevel_completions($bclevel, $user) {
        global $DB;
        $bccoursessql = "SELECT bclc.id as bclcid, bclc.programid, bclc.levelid, bclc.courseid
                         FROM {local_program_level_courses} bclc
                        WHERE bclc.levelid = :levelid ";
        $bccourses = $DB->get_records_sql($bccoursessql,
            array('levelid' => $bclevel->levelid, 'userid' => $user));

        if (!empty($bccourses)) {
            return true;
        }
        return false;
    }
    /**
     * [mycompletedlevels description]
     * @method mycompletedlevels
     * @param  [type]            $programid [description]
     * @param  [type]            $userid     [description]
     * @return [type]                        [description]
     */
    public function mycompletedlevels($programid, $userid) {
        global $DB;

        $mycompletedlevels = array();
        // $mycompletedlevelssql = "SELECT levelids
        //                            FROM {local_program_users}
        //                           WHERE programid = :programid AND userid = :userid ";
        $mycompletedlevelssql = "SELECT levelid FROM {local_bc_level_completions} WHERE programid = :programid AND userid = :userid AND completion_status = 1 ";
        $mycompletedlevelslist = $DB->get_fieldset_sql($mycompletedlevelssql,
            array('programid' => $programid, 'userid' => $userid));
        // if (!empty($mycompletedlevelslist)) {
        //     $mycompletedlevels = explode(',', $mycompletedlevelslist);
        // }
        return $mycompletedlevelslist;
    }
    /**
     * [mycompletedlevelcourses description]
     * @method mycompletedlevelcourses
     * @param  [type]                  $bclevel [description]
     * @return [type]                           [description]
     */
    public function mycompletedlevelcourses($bclevel) {
        global $DB, $USER;
        $programid = $bclevel->programid;
        $levelid = $bclevel->levelid;
        $courses = $DB->get_fieldset_select('local_program_level_courses', 'id', 'programid = :programid AND levelid = :levelid ORDER BY position ASC',
            array('programid' => $programid, 'levelid' => $levelid));
        $mycoursecomptllist = $DB->get_field('local_bc_level_completions', 'bclcids',
            array('programid' => $programid, 'levelid' => $levelid, 'userid' => $USER->id));
        $mycoursecomptl = explode(',', $mycoursecomptllist);
        return array($courses, $mycoursecomptl);
    }
    /**
     * [mynextlevelcourses description]
     * @method mynextlevelcourses
     * @param  [type]             $bclevel [description]
     * @return [type]                      [description]
     */
    public function mynextlevelcourses($bclevel){
        list($courses, $mycoursecomptl) = $this->mycompletedlevelcourses($bclevel);
        $notcmptlcourses = array_values(array_diff($courses, $mycoursecomptl));
        return $notcmptlcourses;
    }
    /**
     * [mycompletedveles description]
     * @method mycompletedveles
     * @param  [type]           $programid [description]
     * @return [type]                       [description]
     */
    public function mylevelsandcompletedlevels($programid) {

        global $DB, $USER;
        $levels = $DB->get_fieldset_select('local_program_levels', 'id', 'programid = :programid ORDER BY id ASC',
            array('programid' => $programid));
        // $mylevelcomptllist = $DB->get_field('local_program_users', 'levelids', array('programid' => $programid, 'userid' => $USER->id));
        $mycompletedlevelssql = "SELECT levelid FROM {local_bc_level_completions} WHERE programid = :programid AND userid = :userid AND completion_status = 1 ";
        $mylevelcomptl = $DB->get_fieldset_sql($mycompletedlevelssql,
            array('programid' => $programid, 'userid' => $USER->id));
        // $mylevelcomptl = explode(',', $mylevelcomptllist);
        return array($levels, $mylevelcomptl);

    }
    /**
     * [mynextlevels description]
     * @method mynextlevels
     * @param  [type]       $programid [description]
     * @return [type]                   [description]
     */
    public function mynextlevels($programid) {
        global $DB, $USER;
        list($levels, $mylevelcomptl) = $this->mylevelsandcompletedlevels($programid, $USER->id);
        $notcmptllevels = array_values(array_diff($levels, $mylevelcomptl));
        return $notcmptllevels;
    }
    public function levels_completion_status($programid){
      global $DB;
      $levels_completion_sql = "SELECT id FROM {local_program_users} WHERE programid = :programid AND (levelids != '' AND levelids IS NOT NULL) ";
      $status = $DB->get_field_sql($levels_completion_sql,  array('programid' => $programid));
      return !$status;
    }
    public function delete_completion_data($programid, $levelid = 0){
        global $DB, $USER;
        $params = array('programid' => $programid, 'completion_status' => 1);
        if($levelid > 0){
            $params['levelid'] = $levelid;
        }
        $levelcompletions = $DB->get_records('local_bc_level_completions', $params);
        foreach($levelcompletions as $levelcomp){
            // $dataobject = clone $levelcomp;
            // unset($dataobject->id);
            // $dataobject->timecreated = time();
            // $dataobject->usercreated = $USER->id;
            // $DB->insert_record('local_bc_level_comp_bk',  $dataobject);

            $levelcomp->completion_status = 0;
            $levelcomp->completiondate = 0;
            $levelcomp->usermodified = $USER->id;
            $levelcomp->timemodified = time();
            $DB->update_record('local_bc_level_completions',  $levelcomp);
        }
        $programcompletions = $DB->get_records('local_program_users', array('programid' => $programid, 'completion_status' => 1), '', 'id, programid,userid,completiondate,levelids');
        foreach($programcompletions AS $progcomp){
            // $dataobject = clone $progcomp;
            // unset($dataobject->id);
            // $dataobject->timecreated = time();
            // $dataobject->usercreated = $USER->id;
            // $DB->insert_record('local_bc_level_comp_bk',  $dataobject);

            $progcomp->completion_status = 0;
            $progcomp->completiondate = 0;
            $progcomp->usermodified = $USER->id;
            $progcomp->timemodified = time();
            $DB->update_record('local_program_users',  $progcomp);
        }
        return true;
    }
    public function get_classrooms_count($courseid){
        global $DB, $USER;
        $currenttime = time();
        $countSql = "SELECT count(lc.id)
            FROM {local_classroom_courses} AS lcc
            JOIN {local_classroom} AS lc On lcc.classroomid = lc.id WHERE lcc.courseid = :courseid AND lc.startdate > :currenttime1 AND lc.status in (1,3,4) ";

        $params=array('courseid' => $courseid, 'currenttime1' => $currenttime);

        $countSql.=$this->get_classroom_ta_query('lc');

        return $DB->count_records_sql($countSql,$params);
    }
    public function get_classrooms_content($courseid, $offset = 0, $limit = 10){
        global $DB, $USER;
        $currenttime = time();
        $courseSql = "SELECT lc.*, group_concat(concat(u.firstname,' ', u.lastname)) as trainers
            FROM {local_classroom_courses} AS lcc
            JOIN {local_classroom} AS lc On lcc.classroomid = lc.id
            LEFT JOIN {local_classroom_trainers} AS lct ON lct.classroomid = lc.id
            LEFT JOIN {user} AS u ON u.id = lct.trainerid
            WHERE lcc.courseid = :courseid AND lc.startdate > :currenttime1 AND lc.status in (1,3,4) ";
        $courseSql .= $this->get_classroom_ta_query('lc');
        $courseSql .= " GROUP BY lc.id ";

        return $DB->get_records_sql($courseSql, array('courseid' => $courseid, 'currenttime1' => $currenttime, 'currenttime2' => $currenttime), $offset, $limit);
    }
    public function get_classroom_ta_query($prefix = 'lc'){
        global $USER, $DB;

          $wheresql='';

          $usercostcenterpaths = $DB->get_records_menu('local_userdata', array('userid' => $USER->id), '', 'id, costcenterpath');
            $paths = [];
            foreach($usercostcenterpaths AS $userpath){
                $userpathinfo = $userpath;
                $paths[] = $userpathinfo.'/%';
                $paths[] = $userpathinfo;
                while ($userpathinfo = rtrim($userpathinfo,'0123456789')) {
                    $userpathinfo = rtrim($userpathinfo, '/');
                    if ($userpathinfo === '') {
                      break;
                    }
                    $paths[] = $userpathinfo;
                }
            }
            if(!empty($paths)){
                foreach($paths AS $path){
                    $pathsql[] = " lc.open_path LIKE '{$path}' ";
                }
                $wheresql .= " AND ( ".implode(' OR ', $pathsql).' ) ';
            }


            $params = array();

            $group_list = $DB->get_records_sql_menu("SELECT cm.id,cm.cohortid as groupid from {cohort_members} cm where cm.userid IN ({$USER->id})");

            if (!empty($group_list)){
                $groups_members = implode(',', $group_list);
                if(!empty($group_list)){
                    $grouquery = array();
                    foreach ($group_list as $key => $group) {
                        $grouquery[] = " CONCAT(',',lc.open_group,',') LIKE CONCAT('%,','{$group}',',%') ";
                    }
                    $groupqueeryparams =implode('OR',$grouquery);

                    $params[]= '('.$groupqueeryparams.')';
                }
            }

            if(count($params) > 0){
                $opengroup=implode('AND',$params);
            }else{
                $opengroup =  " 1 != 1 ";
            }

            $params = array();
            $params[]= " 1 = CASE WHEN lc.open_group is NOT NULL
                    THEN
                        CASE
                            WHEN $opengroup
                                THEN 1
                                ELSE 0
                        END
                    ELSE 1 END ";


            if(!empty($USER->open_designation) && $USER->open_designation != ""){

                $params[]= " 1 = CASE WHEN lc.open_designation IS NOT NULL
                            THEN
                                CASE
                                    WHEN CONCAT(',',lc.open_designation,',') LIKE CONCAT('%,','{$USER->open_designation}',',%')
                                        THEN 1
                                        ELSE 0
                                END
                            ELSE 1 END ";
            }


            if(!empty($params)){
                $finalparams = implode('AND',$params);
            }else{
                $finalparams= '1=1' ;
            }

            $wheresql .= " AND ($finalparams) ";

            return $wheresql;
    }
    public function get_course_classrooms($courseid, $requestData){
        $classrooms = $this->get_classrooms_content($courseid, $requestData['start'], $requestData['length']);
        $totalclassrooms = $this->get_classrooms_count($courseid);
        $data = [];
        $classroomsearch = new \local_classroom\output\search();
        foreach($classrooms AS $classroom){
            $info = [];
            $info[] = $classroom->name;
            if(!empty($classroom->trainers)){
                $classroom->trainers = explode(',', str_replace(',', '</li>,<li>', $classroom->trainers));
                $showless = $showmore = '';
                if(isset($classroom->trainers[2])){
                    $showless = '<span class="hidden show_less togglebutton">';
                    $showmore = '<a href = "javascript:void(0)"> '.get_string('showless', 'local_learningplan').'</a></span><a href = "javascript:void(0)" class="show_more togglebutton">'.get_string('showmore', 'local_learningplan').'</a>';
                    $classroom->trainers[2] = $showless.$classroom->trainers[2];
                }

                $info[] = '<ul class="trainerslist"><li>'.implode('', $classroom->trainers).'</li>'.$showmore.'</ul>';
            }else{
                $info[] = 'N/A';
            }
            $info[] = userdate($classroom->startdate);
            $info[] = userdate($classroom->enddate);

            $enrolled = $classroomsearch->get_the_enrollflag($classroom->id);
            if($enrolled){
                $info[] = \html_writer::link(new \moodle_url('/local/classroom/view.php', array('cid' => $classroom->id)), get_string('view'), array('class' => 'btn btn-primary'));
            }else{
                $info[] = $classroomsearch->get_enrollbtn($classroom);
            }
            $data[] = $info;
        }
        return [
            "sEcho" => intval($requestData['sEcho']),
            "iTotalRecords" => $totalclassrooms,
            "iTotalDisplayRecords" => $totalclassrooms,
            "aaData" => $data
        ];
    }
    public function program_completion_settings_info($programid) {
        global $DB, $USER;
        $programcompletiondata = $DB->get_record('local_bc_completion_criteria', array(
            'programid' => $programid
        ));
        $levelssql = "SELECT id,level FROM {local_program_levels}
                                            WHERE programid = $programid ";
        if (!empty($programcompletiondata) && $programcompletiondata->leveltracking == "OR" && $programcompletiondata->levelids != null) {
            $levelssql .= " AND id in ($programcompletiondata->levelids)";
        }
        $levels            = $DB->get_records_sql_menu($levelssql);
        $return           = "";
        $data = array();

        $level = '';

        if (!empty($levels)) {
            $level = implode(', ', $levels);
        }

        $list['programid'] = $programid;

        $list['levels'] = $levels;

        $leveltracking  = "";
        if ($programcompletiondata->leveltracking == "AND" && !empty($levels)) {
            $leveltracking = "_alllevels";
        }
        if ($programcompletiondata->leveltracking == "OR" && !empty($levels)) {
            $leveltracking = "_anylevels";
        }

        $list['tracking'] = get_string('program_completion_tab_info' . $leveltracking . '', 'local_program');
        $data[] = $list;

        return $data;
    }
    public function program_level_completion_settings_info($programid,$levelid) {
        global $DB, $USER;
        $levelcompletiondata = $DB->get_record('local_bcl_cmplt_criteria', array(
            'programid' => $programid,
            'levelid' => $levelid
        ));
        $levelcoursessql = "SELECT c.id,fullname
                                  FROM {course} AS c
                                  JOIN {local_program_level_courses} AS lc ON lc.courseid = c.id
                                 WHERE lc.programid = :programid AND lc.levelid = :levelid";
        $params = array();
        $params['programid'] = $programid;
        $params['levelid'] = $levelid;


        if (!empty($classroomcompletiondata) && $classroomcompletiondata->coursetracking == "OR" && $classroomcompletiondata->courseids != null) {
            $courseidsda = explode(',',$classroomcompletiondata->courseids);
            list($relatedcourseidsql, $relatedcourseidparams) = $DB->get_in_or_equal($courseidsda, SQL_PARAMS_NAMED, 'courseids');
            $params = array_merge($params,$relatedcourseidparams);
            $levelcoursessql .= " AND lc.courseid $relatedcourseidsql";
        }
        $levelcourses = $DB->get_records_sql_menu($levelcoursessql,$params);
        $return           = "";
        $data = array();

        $course = '';

        if (!empty($levelcourses)) {
            $course = implode(', ', $levelcourses);
        }

        $list['programid'] = $programid;

        $list['levelid'] = $levelid;

        $list['courses'] = $levelcourses;

        $coursetracking  = "";
        if ($levelcompletiondata->coursetracking == "AND" && !empty($levelcourses)) {
            $coursetracking = "_allcourses";
        }
        if ($levelcompletiondata->coursetracking == "OR" && !empty($levelcourses)) {
            $coursetracking = "_anycourses";
        }

        $list['tracking'] = get_string('program_level_completion_tab_info' . $coursetracking . '', 'local_program');
        $data[] = $list;

        return $data;
    }
}
