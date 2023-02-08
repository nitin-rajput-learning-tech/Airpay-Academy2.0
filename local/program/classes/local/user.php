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
namespace local_program\local;
use local_program\program AS programlib ;
class user{
	public function user_profile_content($userid, $return = false,$start =0,$limit=0){
        global $OUTPUT, $CFG;
        $returnobj = new \stdClass();
        $returnobj->programexist = 1;
        $user_programs = $this->enrol_get_users_program($userid,false,true,$start,$limit);
        $data = array();
        foreach($user_programs['data'] as $program){
            $programsarray = array();
            $programsarray['id'] = $program->id;
            $programsarray['name'] = $program->name;
            $programssummary = \local_costcenter\lib::strip_tags_custom($program->description);
            $programssummary = strlen($programssummary) > 140 ? substr($programssummary, 0, 140)."..." : $programssummary;
            $programsarray['description'] = $programssummary;
            $programsarray['percentage'] = '';
            $programsarray['url'] = '';
            $background_logourl = ((new \local_program\program)->program_logo($program->module_logo));
            if($background_logourl == 0){
              require_once($CFG->dirroot.'/local/includes.php');
              $includes = new \user_course_details();
              $programsarray['module_img_url'] = ($includes->get_classes_summary_files($program))->out();
            }else{
              if(is_a($background_logourl, 'moodle_url'))
                $programsarray['module_img_url'] = $background_logourl->out();
            }
            $data[] = $programsarray;
        }

        $returnobj->sequence = 4;
        $returnobj->count = $user_programs['count'];
        $returnobj->divid = 'user_programs';
        $returnobj->moduletype = 'program';
        $returnobj->userid = $userid;
        $returnobj->string = get_string('programs', 'local_users');
        
        $returnobj->navdata = $data;
        return $returnobj;
    }
    public function user_team_content($user){
        global $OUTPUT;
        $programcourses = $this->get_team_member_program_status($user->id);
        $completed = $programcourses->completed;
        $total = $programcourses->total;
        $teamstatus = new \local_myteam\output\team_status_lib();
        $templatedata = array();
        $templatedata['elementcolor'] = $teamstatus->get_colorcode_tm_dashboard($completed,$total);
        $templatedata['completed'] = $completed;
        $templatedata['enrolled'] = $total;
        $templatedata['username'] = fullname($user);
        $templatedata['userid'] = $user->id;
        $templatedata['modulename'] = 'program';
        // return $OUTPUT->render_from_template('local_users/team_status_element', $templatedata);
        return (object) $templatedata;
    }
    public function get_team_member_program_status($userid){
        $return = new \stdClass();
        $return->inprogress = $this->programs_status_count($userid,'0');
        $return->completed = $this->programs_status_count($userid,'1');
        $return->total = $this->programs_status_count($userid,'0,1');
        return $return;
    }

    public function programs_status_count($userid,$status='') {
        global $DB;
        $sql = "SELECT count(lpro.id) FROM {local_program} AS lpro 
                JOIN {local_program_users} AS lprou ON lpro.id=lprou.programid
                WHERE lprou.completion_status IN ({$status}) AND lprou.userid={$userid} ";
        $coursecount = $DB->count_records_sql($sql);
        return $coursecount;
    }

    /**
     * [function to get_user modulecontent]
     * @param  [INT] $id [id of the user]
     * @param  [INT] $start [start]
     * @return [INT] $limit [limit]
     */
    public function user_modulewise_content($id,$start =0,$limit=5){
      global $OUTPUT,$PAGE;
      $returnobj = new \stdClass();
      $userprograms = $this->enrol_get_users_program($id,false,true,$start,$limit);
      $data = array();
      foreach($userprograms['data'] as $program){
          $programssarray = array();
          $programssarray['name'] = $program->name;
          $programssarray['code'] = $program->shortname;
          $programssarray['enrolldate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$program->enrolldate);
          if($program->status == 1){
            $programssarray['status'] = 'Completed';
            $programssarray['completiondate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$program->completiontime);
          }else{
            $programssarray['status'] = 'Not Completed';
            $programssarray['completiondate'] = 'NA';
          }

          $data[] = $programssarray;
      }
      $returnobj->navdata = $data;
      return $returnobj;
    }

    /**
     * [function to get_enrolled program data and count]
     * @param  [INT] $userid [id of the user]
     * @param  [BOOLEAN] $count [true or false]
     * @param  [BOOLEAN] $limityesorno [true or false]
     * @param  [INT] $start [start]
     * @return [INT] $limit [limit]
     */
    public function enrol_get_users_program($userid,$count = false,$limityesorno = false,$start =0,$limit=5) {
        global $DB;
        $countsql = "SELECT count(lcu.id) ";
        $selectsql = "SELECT lcu.id, lc.name, lc.description,lcu.timecreated as enrolldate,
                      lcu.completion_status as status, lcu.timemodified as completiontime, lc.programlogo AS module_logo ";
        $fromsql = " FROM {local_program} AS lc
                      JOIN {local_program_users} AS lcu ON lcu.programid = lc.id
                      WHERE lcu.userid = :userid";
        $params = array();
        $params['userid'] = $userid;

        if($limityesorno){
            $programs = $DB->get_records_sql($selectsql.$fromsql,$params,$start,$limit);
        }else{
            $programs = $DB->get_records_sql($selectsql.$fromsql,$params);
        }
        $programscount = $DB->count_records_sql($countsql.$fromsql,$params);

        return array('count' => $programscount,'data' => $programs);
    }
    public function user_team_headers(){
        return array('program' => get_string('pluginname', 'local_program'));
    }
}