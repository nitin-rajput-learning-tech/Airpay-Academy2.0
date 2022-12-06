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
 * local courses
 *
 * @package    local_courses
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_evaluation\local;

class user{

    /**
     * Description: User Course completion progress
     * @param  INT $courseid course id whose completed percentage to be fetched
     * @param  INT $userid   userid whose completed course prcentage to be fetched
     * @return INT           percentage of completion.
     */
    public function user_course_completion_progress($courseid, $userid) {
        global $DB, $USER, $CFG;
        if(empty($courseid) || empty($userid)){
            return false;
        }
        
        $sql="SELECT id from {course_completions} where course= ? and userid= ? and  timecompleted IS NOT NULL";
        $completionenabled=$DB->get_record_sql($sql, [$courseid, $userid]);
        if($completionenabled ==''){
        $total_activity_count = $this->total_course_activities($courseid);
        $completed_activity_count = $this->user_course_completed_activities($courseid, $userid);
            if($total_activity_count>0 && $completed_activity_count>0){
            	$course_completion_percent = $completed_activity_count/$total_activity_count*100;
            }
        }else{
            $course_completion_percent=100;
        }
        return $course_completion_percent;
    }

    /**
     * Description: User Course total Activities count
     * @param INT $courseid course id whose total activities count to be fetched
     * @return INT count of total activities
     */
    public function total_course_activities($courseid) {
        global $DB, $USER, $CFG;
        if(empty($courseid)){
            return false;
        }
        $sql="SELECT COUNT(ccc.id) as totalactivities FROM {course_modules} ccc WHERE ccc.course=?";
        $totalactivitycount = $DB->get_record_sql($sql, [$courseid]);
        $out = $totalactivitycount->totalactivities;
        return $out;
    }
    /**
     * Description: User Course Completed Activities count
     * @param  INT $courseid course id whose completed activities count to be fetched
     * @param  INT $userid   userid whose completed activities count to be fetched
     * @return INT           count of completed activities
     */
    public function user_course_completed_activities($courseid, $userid) {
        global $DB, $USER, $CFG;
        if(empty($courseid) || empty($userid)){
            return false;
        }
        $sql="SELECT count(cc.id) as completedact from {course_completion_criteria} ccc JOIN {course_modules_completion} cc ON cc.coursemoduleid = ccc.moduleinstance where ccc.course = ? and cc.userid= ? and cc.completionstate = 1 ";
        $completioncount = $DB->get_record_sql($sql, [$courseid, $userid]);
        $out = $completioncount->completedact;
        return $out;
    }
    public function user_team_content($user){
        global $OUTPUT;
        $feedbacks = $this->get_team_member_feedback_status($user->id, 'SP');
        $self_feedbacks = $this->get_team_member_feedback_status($user->id, 'SE');
        $templatedata = array();
        // $totalelearningcourses = $mandatorycourses->enrolled;
        $teamstatus = new \local_myteam\output\team_status_lib();
        $templatedata['supervisorevaluation']['elementcolor'] = $teamstatus->get_colorcode_tm_dashboard($feedbacks->completed, $feedbacks->enrolled);
        $templatedata['supervisorevaluation']['completed'] = $feedbacks->completed;
        $templatedata['supervisorevaluation']['enrolled'] = $feedbacks->enrolled;
        $templatedata['supervisorevaluation']['username'] = fullname($user);
        $templatedata['supervisorevaluation']['userid'] = $user->id;
        $templatedata['supervisorevaluation']['modulename'] = 'supervisorevaluation';

        $templatedata['evaluation']['elementcolor'] = $teamstatus->get_colorcode_tm_dashboard($self_feedbacks->completed, $self_feedbacks->enrolled);
        $templatedata['evaluation']['completed'] = $self_feedbacks->completed;
        $templatedata['evaluation']['enrolled'] = $self_feedbacks->enrolled;
        $templatedata['evaluation']['username'] = fullname($user);
        $templatedata['evaluation']['userid'] = $user->id;
        $templatedata['evaluation']['modulename'] = 'evaluation';
        //return $OUTPUT->render_from_template('local_users/team_status_element', $templatedata);
        return $templatedata;
    }
    public function get_team_member_feedback_status($userid, $evaluationmode){
        global $DB;
        // $inprogress = count($this->inprogress_coursenames($userid));
        // $completed = count($this->completed_coursenames($userid));
        $tcountsql = "SELECT count(leu.id) 
                      FROM {local_evaluation_users} AS leu 
                      JOIN {local_evaluations} AS le ON le.id=leu.evaluationid 
                      WHERE leu.userid=:userid AND le.instance = :instance
                      AND le.visible = :visible AND le.deleted = 0 AND le.evaluationmode = :evaluationmode ";

        $ccountsql = "SELECT count(lec.id) 
                      FROM {local_evaluation_completed} AS lec 
                      JOIN {local_evaluations} AS le ON le.id=lec.evaluation 
                      WHERE lec.userid=:userid AND le.instance = :instance
                      AND le.visible = :visible AND le.deleted = 0 AND le.evaluationmode = :evaluationmode ";

        $params = array('userid' => $userid, 'instance' => 0, 'visible' => 1, 'evaluationmode' => $evaluationmode);
        $enrolled = $DB->count_records_sql($tcountsql, $params); 
        $completed = $DB->count_records_sql($ccountsql, $params);
        $return = new \stdClass();
        $return->enrolled = $enrolled;
        $return->inprogress = $enrolled - $completed;
        $return->completed = $completed;
        return $return;
    }

    /**
     * [function to get_user modulecontent]
     * @param  [INT] $id [id of the user]
     * @param  [INT] $start [start]
     * @return [INT] $limit [limit]
     */
    public function supervisor_user_modulewise_content($id,$start =0,$limit=5){
      global $OUTPUT,$PAGE,$DB;
      $returnobj = new \stdClass();
      $userfeedbacks = $this->enrol_get_users_supervisor_evaluation($id, $start, $limit);
      $data = array();
      foreach($userfeedbacks['data'] as $feedback){
          $feedbackarray = array();
          $feedbackarray['name'] = $feedback->name;
          $feedbackarray['code'] = $feedback->type == 1 ? get_string('feedback', 'local_evaluation') : get_string('survey', 'local_evaluation');
          $feedbackarray['enrolldate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$feedback->timecreated);
          $feedbackarray['status'] = $feedback->completedid ? 'Completed' : 'Not Completed';
          if(!empty($feedback->completiontime)){
            $feedbackarray['completiondate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$feedback->completiontime);
          }else{
            $feedbackarray['completiondate'] = 'NA';
          }
          if($feedback->completedid){
            $url = new \moodle_url('/local/evaluation/show_entries.php', array('id' => $feedback->evaluationid, 'userid' => $feedback->userid, 'showcompleted' => $feedback->completedid, 'myteam' => 'view'));
            $feedbackarray['evaluation_button'] = \html_writer::link($url, get_string('viewresponse', 'local_evaluation'));
          }else{
            $url = new \moodle_url('/local/evaluation/complete.php', array('id' => $feedback->evaluationid, 'teamuserid' => $feedback->userid));
            $feedbackarray['evaluation_button'] = \html_writer::link($url, get_string('submit', 'local_evaluation'));
          }
          $data[] = $feedbackarray;
      }
      $returnobj->navdata = $data;
      return $returnobj;
    }
    public function user_modulewise_content($id,$start =0,$limit=5){
        global $OUTPUT,$PAGE,$DB;
      $returnobj = new \stdClass();
      $userfeedbacks = $this->enrol_get_users_evaluation($id, $start, $limit);
      $data = array();
      foreach($userfeedbacks['data'] as $feedback){
          $feedbackarray = array();
          $feedbackarray['name'] = $feedback->name;
          $feedbackarray['code'] = $feedback->type == 1 ? get_string('feedback', 'local_evaluation') : get_string('survey', 'local_evaluation');
          $feedbackarray['enrolldate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$feedback->timecreated);
          $feedbackarray['status'] = $feedback->completedid ? 'Completed' : 'Not Completed';
          if(!empty($feedback->completiontime)){
            $feedbackarray['completiondate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$feedback->completiontime);
          }else{
            $feedbackarray['completiondate'] = 'NA';
          }
          $data[] = $feedbackarray;
      }
      $returnobj->navdata = $data;
      return $returnobj;
    }
    public function enrol_get_users_evaluation($userid, $start = 0, $limit = 5){
        global $DB;
        $countsql = "SELECT count(leu.id) ";
        $feedbacks_sql = "SELECT leu.*, le.name, lec.id as completedid, lec.timemodified as completiontime ";
        $fromsql = " FROM {local_evaluation_users} AS leu 
                    JOIN {local_evaluations} AS le ON le.id = leu.evaluationid 
                    LEFT JOIN {local_evaluation_completed} AS lec ON lec.evaluation=le.id AND leu.userid=lec.userid
                    WHERE le.deleted = :deleted AND le.instance = :instance 
                    AND leu.userid = :userid AND le.visible =:visible AND le.evaluationmode = :evaluationmode  ";
        $params = array('deleted'=>0,'instance' => 0, 'userid' => $userid, 'visible' => 1, 'evaluationmode' => 'SE');

        $feedbacks = $DB->get_records_sql($feedbacks_sql.$fromsql, $params, $start, $limit);

        $feedbacks_count = $DB->count_records_sql($countsql.$fromsql, $params); 

        return array('data' => $feedbacks, 'count' => $feedbacks_count);
    }
    public function enrol_get_users_supervisor_evaluation($userid, $start = 0, $limit = 5){
        global $DB;
        $countsql = "SELECT count(leu.id) ";
        $feedbacks_sql = "SELECT leu.*, le.name, lec.id as completedid, lec.timemodified as completiontime ";
        $fromsql = " FROM {local_evaluation_users} AS leu 
                    JOIN {local_evaluations} AS le ON le.id = leu.evaluationid 
                    LEFT JOIN {local_evaluation_completed} AS lec ON lec.evaluation=le.id AND leu.userid=lec.userid
                    WHERE le.deleted = :deleted AND le.instance = :instance 
                    AND leu.userid = :userid AND le.visible =:visible AND le.evaluationmode = :evaluationmode  ";
        $params = array('deleted'=>0,'instance' => 0, 'userid' => $userid, 'visible' => 1, 'evaluationmode' => 'SP');

        $feedbacks = $DB->get_records_sql($feedbacks_sql.$fromsql, $params, $start, $limit);

        $feedbacks_count = $DB->count_records_sql($countsql.$fromsql, $params); 

        return array('data' => $feedbacks, 'count' => $feedbacks_count);
    }
    public function user_team_headers(){
        return array('supervisorevaluation' => get_string('supervisorfeedbacks', 'local_evaluation'), 
            'evaluation' => get_string('pluginname', 'local_evaluation'));
    }
}