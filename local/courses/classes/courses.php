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
 * @subpackage local_courses
 */
namespace local_courses;
defined('MOODLE_INTERNAL') || die();
use context_system;

define('COURSE_NOT_ENROLLED', 0);
define('COURSE_ENROLLED', 1);
define('COURSE_ENROLMENT_REQUEST', 2);
define('COURSE_ENROLMENT_PENDING', 3);

class courses {

    public function enrol_status($enrol, $course, $userid = 0){
        global $DB, $USER;
        if ($course->approvalreqd == 1) {
            if ($enrol->enrolled == 0) {
                $componentid = $course->id;
                $component = 'elearning';
                $sql = "SELECT status FROM {local_request_records} WHERE componentid=:componentid AND compname LIKE :compname AND createdbyid = :createdbyid ORDER BY id desc ";
                $request = $DB->get_field_sql($sql, array('componentid' => $componentid, 'compname' => $component, 'createdbyid' => $USER->id));

                if ($request == 'PENDING') {
                    $return = COURSE_ENROLMENT_PENDING;
                } else {
                    $return = COURSE_ENROLMENT_REQUEST;
                }
            } else {
                $return = COURSE_ENROLLED;
            }
        } else {
            if ($enrol->enrolled == 0) {
                $return = COURSE_NOT_ENROLLED;
            } else {
                $return = COURSE_ENROLLED;
            }
        }

        return $return;
    }
    public static function can_access_course($courseid, $userid){
        global $DB, $CFG;
        $coursecontext = \context_course::instance($courseid, MUST_EXIST);
        if(!is_enrolled(\context_course::instance($COURSE->id))){
            if (!(is_siteadmin() OR has_capability('local/costcenter:manage_multiorganizations', $coursecontext))) {

                $user_costcenter =$DB->get_record('user',array('id'=>$userid),  $fields='id,open_costcenterid,open_departmentid');
                $course_costcenter =$DB->get_record('course',array('id'=>$courseid),  $fields='id,open_costcenterid,open_departmentid');
                if (has_capability('local/costcenter:manage_ownorganization',$coursecontext)) {
                    if (($user_costcenter->open_costcenterid != $course_costcenter->open_costcenterid)) {
                        $message = get_string('notyourorgcourse_msg','local_courses');
                        return ['status' => false, 'message' => $message];
                    }
                }elseif (has_capability('local/costcenter:manage_owndepartments',$categorycontext)) {
                    if (($user_costcenter->open_costcenterid != $course_costcenter->open_costcenterid) || ($user_costcenter->open_departmentid != $course_costcenter->open_departmentid)) {
                        $message = get_string('notyourdeptcourse_msg','local_courses');
                        return ['status' => false, 'message' => $message];
                    }
                }else{
                    return ['status' => false, 'message' => ''];
                }
            }
        }
        return ['status' => true, 'message' => ''];
    }
}
