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
 * @subpackage local_classroom
 */
namespace local_classroom\local;
class user{
    public function user_profile_content($id,$return = false,$start =0,$limit=5){
        global $OUTPUT,$PAGE, $CFG;
        $returnobj = new \stdClass();
        $templateobj = new \stdClass();
        $returnobj->classroomexist = 1;
        $userclassrooms = $this->enrol_get_users_classroom($id,false,true,$start,$limit);
        $data = array();
        foreach($userclassrooms['data'] as $classroom){
            $classroomsarray = array();
            $classroomsarray['id'] = $classroom->id;
            $classroomsarray['name'] = $classroom->name;
            $classroomsummary = \local_costcenter\lib::strip_tags_custom($classroom->description);
            $classroomsummary = strlen($classroomsummary) > 140 ? substr($classroomsummary, 0, 140)."<span id='dots'>...</span><span id='more' style='display: none;'>".substr($classroomsummary, 140,strlen($classroomsummary)).'</span> <a onclick="myFunction()" id="myBtn">Read more</a>' : $classroomsummary;
            $classroomsarray['description'] = $classroomsummary;
            $classroomsarray['percentage'] = '';
            $classroomsarray['url'] = '';
            // $classroomsarray['module_img_url'] = ((new \local_classroom\classroom)->classroom_logo($classroom->module_logo));
            if($classroom->module_logo == 0){
                require_once($CFG->dirroot.'/local/includes.php');
                $includes = new \user_course_details();
                $classroomsarray['module_img_url'] = ($includes->get_classes_summary_files($classroom))->out();
            }else{
                // $classroomsarray['module_img_url'] = $return['background_logourl']->out();
                $classroomsarray['module_img_url'] = (new \local_classroom\classroom)->classroom_logo($classroom->module_logo);
            }
            $classroomsarray['module_img_url'] = is_object($classroomsarray['module_img_url']) ? $classroomsarray['module_img_url']->out() : $classroomsarray['module_img_url'] ;
          
          $data[] = $classroomsarray;
      }
        $returnobj->sequence = 1;
        $returnobj->divid = 'user_classrooms';
        $returnobj->string = get_string('classrooms', 'local_users');
        $returnobj->moduletype = 'classroom';
        $returnobj->targetID = 'display_classroom';
        $returnobj->userid = $id;
        $returnobj->count = $userclassrooms['count'];
        $returnobj->navdata = $data;
        return $returnobj;
    }
    public function user_team_content($user){
        global $OUTPUT;
        $classrooms = $this->get_team_member_classroom_status($user->id);
        $templatedata = array();
        $completed = $classrooms->completed;
        $total = $classrooms->total;
        $teamstatus = new \local_myteam\output\team_status_lib();
        $templatedata['elementcolor'] = $teamstatus->get_colorcode_tm_dashboard($completed,$total);
        $templatedata['completed'] = $completed;
        $templatedata['enrolled'] = $total;
        $templatedata['username'] = fullname($user);
        $templatedata['userid'] = $user->id;
        $templatedata['modulename'] = 'classroom';
        //return $OUTPUT->render_from_template('local_users/team_status_element', $templatedata);
        return (object) $templatedata;
    }
    public function get_team_member_classroom_status($userid){
        $return = new \stdClass();
        $return->inprogress = $this->classrooms_status_count($userid,'1');
        $return->completed = $this->classrooms_status_count($userid,'4');
        $return->total = $this->classrooms_status_count($userid,'1,4');
        return $return;

    }
    public function classrooms_status_count($userid,$status='') {
        global $DB;
        $sql = "SELECT count(lc.id) FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lc.id=lcu.classroomid
                WHERE lcu.userid = :userid ";
        $params = array();
        $params['userid'] = $userid;

        $statuses = explode(',',$status);

        list($statussql, $statusparams) = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED);
        $params = array_merge($params, $statusparams);

        $sql .= " AND lc.status $statussql";

        $coursecount = $DB->count_records_sql($sql,$params);
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
      $userclassrooms = $this->enrol_get_users_classroom($id,false,true,$start,$limit);
      $data = array();
      foreach($userclassrooms['data'] as $classroom){
          $classroomsarray = array();
          $classroomsarray['name'] = $classroom->name;
          $classroomsarray['code'] = $classroom->shortname;
          $classroomsarray['enrolldate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$classroom->enrolldate);
          if($classroom->status == 1){
            $status = 'Completed';
          }else{
            $status = 'Not Completed';
          }
          $classroomsarray['status'] = $status;
          if(!empty($classroom->completiondate)){
            $classroomsarray['completiondate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$classroom->completiondate);
          }else{
            $classroomsarray['completiondate'] = 'N/A';
          }

          $data[] = $classroomsarray;
      }
      $returnobj->navdata = $data;
      return $returnobj;
    }

     /**
     * [function to get_enrolled classroom data and count]
     * @param  [INT] $userid [id of the user]
     * @param  [BOOLEAN] $count [true or false]
     * @param  [BOOLEAN] $limityesorno [true or false]
     * @param  [INT] $start [start]
     * @return [INT] $limit [limit]
     */
    public function enrol_get_users_classroom($userid,$count = false,$limityesorno = false,$start =0,$limit=5) {
        global $DB;
        $ordersql = '';
        $countsql = "SELECT count(lc.id)";
        $selectsql = "SELECT lc.id,lc.name,lc.shortname,lc.description,lcu.timecreated as enrolldate,lcu.completion_status as status, lcu.completiondate, lc.classroomlogo AS module_logo ";

        $classroomsql = " FROM {local_classroom} AS lc 
                          JOIN {local_classroom_users} AS lcu ON lcu.classroomid = lc.id 
                          WHERE lcu.userid = :userid and lc.visible = 1";

        $params = array();
        $params['userid'] = $userid;
         $status = array(1,4);
         list($relatedestatussql, $relatedstatusparams) = $DB->get_in_or_equal($status, SQL_PARAMS_NAMED, 'status');
        $params = array_merge($params,$relatedstatusparams);
        $classroomsql .= " AND lc.status $relatedestatussql";

        $ordersql .= " ORDER BY lc.id DESC";

        if($limityesorno){
            $classrooms    = $DB->get_records_sql($selectsql.$classroomsql.$ordersql,$params ,$start,$limit);
        }else{
            $classrooms    = $DB->get_records_sql($selectsql.$classroomsql.$ordersql, $params);
        }
        $classroomscount    = $DB->count_records_sql($countsql.$classroomsql, $params);
        return array('count' => $classroomscount,'data' => $classrooms);
    }
    public function user_team_headers(){
      return array('classroom' => get_string('pluginname', 'local_classroom'));
    }
}
