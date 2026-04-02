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

namespace local_courses\local;

class user{

    public function user_profile_content($userid,$return = false,$start =0,$limit =5){
        global $OUTPUT, $CFG;
        $returnobj = new \stdClass();
        $returnobj->coursesexist = 1;
        $records = $this->enrol_get_users_courses($userid,false,true,$start,$limit);
        $courses = $records['data'];

        $data = array();
        foreach ($courses as $course) {
            $coursesarray = array();
            $coursesarray["id"] = $course->id;
            $coursesarray["name"] = $course->fullname;
            $url = new \moodle_url('/course/view.php', array('id' => $course->id));
            $urllink = $url->out();
            $coursesarray["url"] = $urllink;
            $coursesummary = $course->summary;
            $coursesummary = \local_costcenter\lib::strip_tags_custom($coursesummary);
            $summarystring = strlen($coursesummary) > 120 ? clean_text(substr($coursesummary, 0, 120))."..." : $coursesummary;
            $coursesarray["description"] = $summarystring;
            $number=$this->user_course_completion_progress($course->id,$userid);
            $coursesarray["percentage"] = (is_number($number)) ?round($number) : 0;

            require_once($CFG->dirroot.'/local/includes.php');
            $includes = new \user_course_details();
            $course_record = get_course($course->id);
            $background_logourl= ($includes->course_summary_files($course_record));
            if(is_a($background_logourl, 'moodle_url')){
                $coursesarray['module_img_url'] = $background_logourl->out();
            }else{
                $coursesarray['module_img_url'] = $background_logourl;
            }
            $data[] = $coursesarray;
        }

        $returnobj->sequence = 0;
        $returnobj->count = $records['count'];
        $returnobj->divid = 'user_courses';
        $returnobj->moduletype = 'courses';
        $returnobj->targetID = 'display_classroom';
        $returnobj->userid = $userid;
        $returnobj->string = get_string('courses', 'local_users');
        $returnobj->navdata = $data;
        return $returnobj;
    }

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
        $course_completion_percent = '';
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
        $courses = $this->get_team_member_course_status($user->id);
        $templatedata = array();
        // $totalelearningcourses = $mandatorycourses->enrolled;
        $teamstatus = new \local_myteam\output\team_status_lib();
        $templatedata['elementcolor'] = $teamstatus->get_colorcode_tm_dashboard($courses->completed,$courses->enrolled);
        $templatedata['completed'] = $courses->completed;
        $templatedata['enrolled'] = $courses->enrolled;
        $templatedata['username'] = fullname($user);
        $templatedata['userid'] = $user->id;
        $templatedata['modulename'] = 'courses';
        //return $OUTPUT->render_from_template('local_users/team_status_element', $templatedata);
        return (object) $templatedata;
    }
    public function get_team_member_course_status($userid ,$optional = false, $mandatory = false,$totalcourses = false){
        global $DB;
        $inprogress = count($this->inprogress_coursenames($userid));
        $completed = count($this->completed_coursenames($userid));
        $return = new \stdClass();
        $return->enrolled = $inprogress+$completed;
        $return->inprogress = $inprogress;
        $return->completed = $completed;
        return $return;

    }
    public function inprogress_coursenames($userid) {
        global $DB;
        $params = array();
        $couresparams = array();
        $sql = "SELECT DISTINCT(course.id),ue.userid, course.fullname, course.shortname as code, course.summary,ue.timecreated as enrolldate
                    FROM {course} AS course
                    JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol IN('self','manual','auto','fee')
                    JOIN {user_enrolments} AS ue ON e.id = ue.enrolid
                    WHERE ue.userid = :userid AND course.id > 1 AND (course.open_coursetype = 0 OR course.open_coursetype IS NULL) ";

        $params['userid'] = $userid;

        $completed_courses = self::completed_coursenames($userid);
        if(!empty($completed_courses)){
            $complted_id = array();
            foreach($completed_courses as $complted_course){
                $completed_id[] = $complted_course->id;
            }
            $completed_ids = implode(',', $completed_id);
            list($couressql, $couresparams) = $DB->get_in_or_equal($completed_id, SQL_PARAMS_NAMED, 'param', false, false);
            $sql .= " AND course.id $couressql";
        }
        $paramsarray = array_merge($params,$couresparams);
        $inprogress_courses = $DB->get_records_sql($sql, $paramsarray);
        return $inprogress_courses;
    }

    public function completed_coursenames($userid) {
        global $DB;
        $sql = "SELECT distinct(cc.id) as completionid,c.id,c.fullname,c.shortname as code,c.summary,ue.timecreated as enrolldate,cc.timecompleted as completedate
            FROM {course_completions} AS cc
            JOIN {course} AS c ON c.id = cc.course
            JOIN {enrol} AS e ON c.id = e.courseid AND e.enrol IN('self','manual','auto','fee')
            JOIN {user_enrolments} AS ue ON e.id = ue.enrolid AND ue.userid = cc.userid
            WHERE cc.timecompleted is not NULL AND c.visible=1 AND c.id>1 AND cc.userid = ? AND (c.open_coursetype = 0 OR c.open_coursetype IS NULL) 
            ";

        $coursenames = $DB->get_records_sql($sql, [$userid]);
        return $coursenames;
    }

    public function enrol_get_users_courses($userid, $count =false, $limityesno = false, $start = 0, $limit = 5, $source = false) {
        global $DB;
        $countsql = "SELECT count(DISTINCT(course.id)) ";
        $coursessql = "SELECT course.id, course.fullname as name,course.shortname as code, course.summary,ue.timecreated as enrolldate , cc.timecompleted AS completiondate ";

        // $fromsql = "FROM {course} AS course
        //             JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol IN('self','manual','auto','fee')
        //             JOIN {user_enrolments} ue ON e.id = ue.enrolid
        //             LEFT JOIN {course_completions} AS cc ON cc.course = course.id AND cc.userid = {$userid}
        //             WHERE ue.userid = ? AND CONCAT(',',course.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND course.id>1 ";

        $fromsql = " FROM {course} course
                    JOIN {enrol} e ON e.courseid = course.id AND
                                (e.enrol = 'manual' OR e.enrol = 'self')
                    JOIN {user_enrolments} ue ON ue.enrolid = e.id
                    JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1
                                    AND u.deleted = 0 AND u.suspended = 0
                    JOIN {local_costcenter} lc ON concat('/',u.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1
                    JOIN {role_assignments} as ra ON ra.userid = u.id
                    JOIN {context} AS cxt ON cxt.id=ra.contextid AND cxt.contextlevel = 50 AND cxt.instanceid=course.id
                    JOIN {role} as r ON r.id = ra.roleid AND r.shortname = 'employee'
                    LEFT JOIN {course_completions} as cc ON cc.course = course.id AND u.id = cc.userid
                    WHERE course.id > 1 AND ue.userid = ? AND (course.open_coursetype = 0 OR course.open_coursetype IS NULL) AND course.visible=1 ";
        // if ($source) {
        //     $fromsql .= " AND course.open_securecourse != 1 ";
        // }
        $ordersql = " ORDER BY ue.id DESC ";
        if ($limityesno)
            $records = $DB->get_records_sql($coursessql.$fromsql.$ordersql, [$userid], $start, $limit);
        else
        $records = $DB->get_records_sql($coursessql.$fromsql.$ordersql, [$userid]);
        foreach($records as $key=>$value){
        $statusid = $DB->get_field_sql("SELECT cc.id FROM {course_completions} cc JOIN {user} u ON cc.userid = u.id WHERE cc.course =:courseid AND cc.userid =:userid AND cc.timecompleted > 0 AND cc.timecompleted is not null AND u.suspended = 0 AND u.deleted = 0 ",array('userid' => $userid ,'courseid' => $value->id ));
        if($statusid){
          $status = 'Completed';
        }else{
          $status = 'Not Completed';
        }
        $value->status = $status;
        if(!empty($value->completiondate)){
            $value->completiondate = \local_costcenter\lib::get_userdate('d/m/Y H:i',$value->completiondate);
        }else{
            $value->completiondate = 'NA';
        }
        $value->enrolldate = \local_costcenter\lib::get_userdate('d/m/Y H:i',$value->enrolldate);

    }
        $total = $DB->count_records_sql($countsql.$fromsql, [$userid]);
        return array('data'=>$records, 'count'=>$total);
    }

    /**
     * [function to get_user modulecontent]
     * @param  [INT] $id [id of the user]
     * @param  [INT] $start [start]
     * @return [INT] $limit [limit]
     */
    public function user_modulewise_content($id,$start =0,$limit=5){
      global $OUTPUT,$PAGE,$DB;
      $returnobj = new \stdClass();
      $usercourses = enrol_get_users_courses($id,false,null,null);
      $data = array();
      foreach($usercourses as $course){
          $coursessarray = array();
          $coursessarray['name'] = $course->fullname;
          $coursessarray['code'] = $course->shortname;
          $coursessarray['enrolldate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$course->enrolldate);
          $statusid = $DB->get_field_sql("SELECT cc.id FROM {course_completions} cc JOIN {user} u ON cc.userid = u.id WHERE cc.course =:courseid AND cc.userid =:userid AND cc.timecompleted > 0 AND cc.timecompleted is not null AND u.suspended = 0 AND u.deleted = 0 ",array('userid' => $id ,'courseid' => $course->id ));

          if($statusid){
            $status = 'Completed';
          }else{
            $status = 'Not Completed';
          }
          $coursessarray['status'] = $status;
          if(!empty($course->completiondate)){
            $coursessarray['completiondate'] = \local_costcenter\lib::get_userdate('d/m/Y H:i',$course->completiondate);
          }else{
            $coursessarray['completiondate'] = 'NA';
          }
          $data[] = $coursessarray;
      }
      $returnobj->navdata = $data;
      return $returnobj;
    }
    public function user_team_headers(){
        return array('courses' => get_string('pluginname', 'local_courses'));
    }
}
