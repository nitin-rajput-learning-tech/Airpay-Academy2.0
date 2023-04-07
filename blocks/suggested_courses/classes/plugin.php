<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This courselister is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This courselister is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this courselister.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Suggested Courses list block plugin helper
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms
 * @subpackage block_suggested_courses
 */

namespace block_suggested_courses;

use dml_exception;

defined('MOODLE_INTERNAL') || die;

/**
 * Class plugin
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms
 * @subpackage block_suggested_courses
 */
abstract class plugin {
    /** @var string */
    const COMPONENT = 'block_suggested_courses';

    
    /** @var int */
    const SUGGESTEDCOURSES = 1;

    public static function get_suggestedcourses($stable,$filtervalues,$data_object) {
        global $DB, $USER, $CFG;
        $systemcontext = \context_system::instance();
        $params=array();

        $interested_skill_ids = $DB->get_record('local_interested_skills',array('usercreated'=>$USER->id), 'interested_skill_ids', $strictness=IGNORE_MISSING);

        if ($interested_skill_ids) {
            $intskills = $interested_skill_ids->interested_skill_ids;

            $coursetypessql = "SELECT c.id, c.fullname, c.visible, c.summary, c.open_identifiedas, c.selfenrol, c.open_skill, ue.userid as userid
                FROM {course} AS c
                JOIN {user_enrolments} as ue ON ue.userid = $USER->id
                JOIN {enrol} as en ON en.id = ue.enrolid AND c.id = en.courseid
                WHERE c.open_skill IN ($intskills) AND c.visible = 1 AND c.selfenrol = 1";  

            $coursecountsql = $DB->get_records_sql($coursetypessql);
            $var = array();
            foreach ($coursecountsql as $key) {
                $var[$key->id] = $key->id;
            }
            $imp_val = implode(',', $var);
            if ($imp_val == '' || null) {
                $imp_val = 0;
            }
            $params = [];
            $sql = "SELECT c.id, c.fullname, c.visible, c.summary, c.open_identifiedas, c.selfenrol, c.open_skill
                FROM {course} AS c
                WHERE c.open_skill IN ($intskills) AND c.visible = 1 AND c.selfenrol = 1 AND c.id NOT IN ($imp_val)";

            if(!empty($data_object->search_query)){
                $sql .= " AND c.fullname LIKE '%$data_object->search_query%'";
            }
            $suggestedcourses = $DB->get_records_sql($sql, $params, $stable->start, $stable->length);
        } else {
            $allcoursecount = 0;
        }
        try {
         if($coursecount === ''){
                $coursecount = $DB->get_records_sql($sql, $params);   
                $allcoursecount=count($coursecount);
             }
        } catch (dml_exception $ex) {
            $allcoursecount = 0;
        }
        // print_r($suggestedcourses);die;
        return compact('suggestedcourses', 'allcoursecount');
    }
}
