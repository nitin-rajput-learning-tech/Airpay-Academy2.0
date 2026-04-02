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
 * @subpackage local_courses
 */

namespace local_courses\lib;

/**
 * get access lib functions
 */
class accesslib extends \local_costcenter\lib\accesslib{

    public static function course_costcenterpath($courseid = null) {

        global $DB;

        $costcenterpath=null;

        if($courseid != null && $courseid > 0){

            $costcenterpath=$DB->get_field('course','open_path',  array('id'=> $courseid));
        }

        return $costcenterpath;

    }
    public static function get_module_context($courseid = null){

        return parent::get_module_context(self::course_costcenterpath($courseid));

    }
    public static function get_costcenter_path_field_concatsql($columnname,$courseid = null, $datatype = NULL){

        return parent::get_costcenter_path_field_concatsql($columnname, self::course_costcenterpath($courseid));

    }
    public static function get_user_course_progress_percentage($courseid, $userid,
                              $enrolid = 0){

        global $CFG, $USER, $DB;



        $maincheckcontext=$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();

        // If viewing details of another user, then we must be able to view participants as well as profile of that user.


        if (((has_capability('local/courses:enrol',
                                $maincheckcontext,$userid)  || is_siteadmin())&&has_capability('local/courses:manage', $maincheckcontext,$userid))) {


            $context = \context_course::instance($courseid, IGNORE_MISSING);

            $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

            $totalusersssql = " SELECT COUNT(u.id)
                                    FROM {user} u
                                    WHERE u.confirmed = 1 AND u.deleted = 0 AND u.suspended = 0 $costcenterpathconcatsql";

            $totaluserscount =  $DB->count_records_sql($totalusersssql);

            $withcapability = 'local/courses:participate';
            $employeerole = $DB->get_field('role', 'id', array('shortname' => 'employee'));
            $params = array('courseid'=>$courseid, 'employeerole' => $employeerole);

            $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

            $enrolledusersssql = " SELECT COUNT(u.id) as ccount
                                FROM {course} c
                                JOIN {context} AS cot ON cot.instanceid = c.id AND cot.contextlevel = 50
                                JOIN {role_assignments} as ra ON ra.contextid = cot.id
                                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1
                                                AND u.deleted = 0 AND u.suspended = 0
                                WHERE c.id = :courseid AND ra.roleid = :employeerole $costcenterpathconcatsql";

            $enrolledusercount =  $DB->count_records_sql($enrolledusersssql, $params);


            $completedusersssql = " SELECT COUNT(u.id) as ccount
                                FROM {course} c
                                JOIN {context} AS cot ON cot.instanceid = c.id AND cot.contextlevel = 50
                                JOIN {role_assignments} as ra ON ra.contextid = cot.id
                                JOIN {user} u ON u.id = ra.userid AND u.confirmed = 1
                                                AND u.deleted = 0 AND u.suspended = 0
                                JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid
                                WHERE c.id = :courseid AND ra.roleid = :employeerole AND cc.timecompleted IS NOT NULL $costcenterpathconcatsql";

            $completedusercount = $DB->count_records_sql($completedusersssql,$params);
            if($totaluserscount > 0){
                $enrolledpercentage=($enrolledusercount / $totaluserscount) * 100;
            }
            
            if (!is_nan($enrolledpercentage)) {

                $enrolledpercentage = floor($enrolledpercentage);
            }else{
                $enrolledpercentage=0;
            }
            if($enrolledusercount > 0){

                $completedpercentage=($completedusercount / $enrolledusercount) * 100;
            }else{

                $completedpercentage=0;

            }

            if (!is_nan($completedpercentage)) {

                $completedpercentage= floor($completedpercentage);
            }else{
                $completedpercentage=0;
            }
            return array('totaluserscount'=>$totaluserscount,'enrolledusercount'=>$enrolledusercount,'completedusercount'=>$completedusercount,'enrolledpercentage'=>$enrolledpercentage,'completedpercentage'=>$completedpercentage,'courseprogresspercent'=>false);

        }else{

            $fullcourse = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $progress = \core_completion\progress::get_course_progress_percentage($fullcourse, $userid);
            $coursehasprogress = $progress !== null;
            $courseprogresspercent = $coursehasprogress ? $progress : 0;

            if (!is_nan($courseprogresspercent)) {

                $courseprogresspercent= floor($courseprogresspercent);
            }else{
                $courseprogresspercent=0;
            }

            return array('usercourseprogresspercent'=>$courseprogresspercent,'courseprogresspercent'=>true);
        }

    }
    public static function get_course_enrolled_sql($context, $withcapability = '', $groupid = 0, $onlyactive = false, $onlysuspended = false,$enrolid = 0) {

        // Use unique prefix just in case somebody makes some SQL magic with the result.
        static $i = 0;
        $i++;
        $prefix = 'eu' . $i . '_';

        $columnname=$prefix.'u.open_path';
        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname);

        $capjoin = get_enrolled_with_capabilities_join(
                $context, $prefix, $withcapability, $groupid, $onlyactive, $onlysuspended, $enrolid);

        $sql = "SELECT DISTINCT {$prefix}u.id
                  FROM {user} {$prefix}u
                $capjoin->joins
                 WHERE $capjoin->wheres $costcenterpathconcatsql";
        return array($sql, $capjoin->params);
    }
    public static function get_course_completed_sql($context,$enrolledsqlselect,$enrolledparams) {

        // Use unique prefix just in case somebody makes some SQL magic with the result.
        static $i = 0;
        $i++;
        $prefix = 'cu' . $i . '_';

         $sql = " SELECT DISTINCT {$prefix}cc.id
                            FROM {course_completions} as {$prefix}cc
                            WHERE {$prefix}cc.course = $context->instanceid AND {$prefix}cc.userid in ($enrolledsqlselect) AND {$prefix}cc.timecompleted IS NOT NULL ";


        return array($sql,$enrolledparams);
    }
}
