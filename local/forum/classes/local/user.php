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
 * local forum
 *
 * @package    local_forum
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace local_forum\local;

class user{

    public function user_profile_content($userid,$return = false,$start =0,$limit =5){
        global $OUTPUT, $CFG;
        $returnobj = new \stdClass();
        $returnobj->forumexist = 1;
        $records = $this->enrol_get_users_forum($userid,false,true,$start,$limit);
        $forum = $records['data'];

        $data = array();
        foreach ($forum as $forum) {
            $forumarray = array();
            $forumarray["id"] = $forum->id;
            $forumarray["name"] = $forum->fullname;
            $url = new \moodle_url('/mod/quiz/view.php', array('id' => $forum->id));
            $urllink = $url->out();
            $forumarray["url"] = $urllink;
            $forumummary = $forum->summary;
            $forumummary = \local_costcenter\lib::strip_tags_custom($forumummary);
            $summarystring = strlen($forumummary) > 120 ? clean_text(substr($forumummary, 0, 120))."..." : $forumummary;
            $forumarray["description"] = $summarystring;
            $forumarray["percentage"] = round($this->user_forum_completion_progress($forum->id,$userid));

            require_once($CFG->dirroot.'/local/includes.php');
            $includes = new \user_course_details();
            $forum_record = get_course($forum->id);
            $background_logourl= ($includes->course_summary_files($forum_record));
            if(is_a($background_logourl, 'moodle_url')){
                $forumarray['module_img_url'] = $background_logourl->out();
            }else{
                $forumarray['module_img_url'] = $background_logourl;
            }
            $data[] = $forumarray;
        }

        $returnobj->sequence = 5;
        $returnobj->count = $records['count'];
        $returnobj->divid = 'user_forum';
        $returnobj->moduletype = 'forum';
        $returnobj->targetID = 'display_classroom';
        $returnobj->userid = $userid;
        $returnobj->string = get_string('forum', 'local_users');
        $returnobj->navdata = $data;
        return $returnobj;
    }

    /**
     * Description: User forum completion progress
     * @param  INT $forumid forum id whose completed percentage to be fetched
     * @param  INT $userid   userid whose completed forum prcentage to be fetched
     * @return INT           percentage of completion.
     */
    public function user_forum_completion_progress($forumid, $userid) {
        global $DB, $USER, $CFG;
        if(empty($forumid) || empty($userid)){
            return false;
        }

        $sql="SELECT id from {course_completions} where course= ? and userid= ? and  timecompleted IS NOT NULL";
        $completionenabled=$DB->get_record_sql($sql, [$forumid, $userid]);
        $forum_completion_percent = '';
       
        return 0;
    }

 
   

   
    public function inprogress_forumnames($userid) {
        global $DB;
        $params = array();
        $couresparams = array();
        $sql = "SELECT DISTINCT(course.id),ue.userid, course.fullname, course.shortname as code, course.summary,ue.timecreated as enrolldate
                    FROM {course} AS course
                    JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol IN('self','manual','auto')
                    JOIN {user_enrolments} AS ue ON e.id = ue.enrolid
                    WHERE ue.userid = :userid  AND course.id > 1 AND course.open_coursetype = 1";

        $params['userid'] = $userid;

        $completed_forum = self::completed_forumnames($userid);
        if(!empty($completed_forum)){
            $complted_id = array();
            foreach($completed_forum as $complted_forum){
                $completed_id[] = $complted_forum->id;
            }
            $completed_ids = implode(',', $completed_id);
            list($couressql, $couresparams) = $DB->get_in_or_equal($completed_id, SQL_PARAMS_NAMED, 'param', false, false);
            $sql .= " AND course.id $couressql";
        }

        $paramsarray = array_merge($params,$couresparams);
        $inprogress_forum = $DB->get_records_sql($sql, $paramsarray);
        return $inprogress_forum;
    }

    public function completed_forumnames($userid) {
        global $DB;
        $sql = "SELECT distinct(cc.id) as completionid,c.id,c.fullname,c.shortname as code,c.summary,ue.timecreated as enrolldate,cc.timecompleted as completedate
            FROM {course_completions} AS cc
            JOIN {course} AS c ON c.id = cc.course
            JOIN {enrol} AS e ON c.id = e.courseid AND e.enrol IN('self','manual','auto')
            JOIN {user_enrolments} AS ue ON e.id = ue.enrolid AND ue.userid = cc.userid
            WHERE cc.timecompleted is not NULL AND c.visible=1 AND c.id>1 AND cc.userid = ? AND c.open_coursetype = 1
            ";

        $forumnames = $DB->get_records_sql($sql, [$userid]);
        return $forumnames;
    }

    public function enrol_get_users_forum($userid, $count =false, $limityesno = false, $start = 0, $limit = 5, $source = false) {
        global $DB;
        $countsql = "SELECT count(DISTINCT(course.id)) ";
        $forumsql = "SELECT course.id, course.fullname,course.shortname, course.summary,ue.timecreated as enrolldate , cc.timecompleted AS completiondate ";

        // $fromsql = "FROM {course} AS course
        //             JOIN {enrol} AS e ON course.id = e.courseid AND e.enrol IN('self','manual','auto')
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
                    WHERE course.id > 1 AND ue.userid = ? AND course.open_coursetype = 1";
        // if ($source) {
        //     $fromsql .= " AND course.open_securecourse != 1 ";
        // }
        $ordersql = " ORDER BY ue.id DESC ";
        if ($limityesno)
            $records = $DB->get_records_sql($forumsql.$fromsql.$ordersql, [$userid], $start, $limit);
        else
        $records = $DB->get_records_sql($forumsql.$fromsql.$ordersql, [$userid]);

        $total = $DB->count_records_sql($countsql.$fromsql, [$userid]);

        return array('data'=>$records, 'count'=>$total);
    }

   
}
