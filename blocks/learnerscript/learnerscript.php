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

/** Learner Script
 * A Moodle block for creating LearnerScript Reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2017
 */
require_once("../../config.php");
global $DB;
        /*$examsinformation = get_config('block_learnerscript', 'examsinformation');
        if (!empty($examsinformation)) {*/
            $sql = "SELECT DISTINCT ue.id, c.id AS examid, c.fullname AS examname, ue.userid AS userid, CONCAT(u.firstname, ' ', u.lastname) AS username, lcc1.id AS vendorid, lcc1.vendorname, ue.timecreated AS enroldate, cc.timecompleted AS completiondate, ue.completiondate AS deadline, u.open_path AS user_costcenterid, u.open_departmentid AS user_departmentid, c.open_costcenterid AS costcenterid, c.open_departmentid AS departmentid, c.timecreated AS timecreated, ra.timemodified AS usermodified, c.open_contentvendor AS open_contentvendor, c.open_subdepartment AS subdepartment, u.open_subdepartment AS user_subdepartment, (SELECT cfd.intvalue
                        FROM {customfield_data} cfd  
                        JOIN {customfield_field} cff ON cff.id = cfd.fieldid AND cff.name = 'EOL' 
                        WHERE cfd.instanceid = c.id) AS upcomingeol, (SELECT UNIX_TIMESTAMP(DATE_ADD(FROM_UNIXTIME(cfd1.timemodified) , interval cfd1.charvalue month))
                        FROM {customfield_data} cfd1
                        JOIN {customfield_field} cff1 ON cff1.id = cfd1.fieldid AND cff1.name = 'Valid for (months)'
                        WHERE cfd1.charvalue != '' AND DATE_ADD(FROM_UNIXTIME(cfd1.timemodified) , interval cfd1.charvalue month) BETWEEN CURDATE()
                        AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND cfd1.instanceid = c.id) AS upcomingexpiry, ue.id AS refid
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON e.id = ue.enrolid 
                    JOIN {role_assignments} ra ON ra.userid = ue.userid
                    JOIN {context} ct ON ct.id = ra.contextid
                    JOIN {role} rl ON rl.id = ra.roleid AND rl.shortname IN ('employee','student')
                    JOIN {user} u ON u.id = ue.userid AND u.confirmed = 1 AND u.deleted = 0 
                    JOIN {course} c ON c.id = e.courseid AND c.id = ct.instanceid 
                    LEFT JOIN {local_courses_venderslist} lcc1 ON lcc1.id = c.open_vendor
                    JOIN {local_courses_learningformat} clf ON clf.id = c.open_learningformat AND clf.name = 'Exam' 
                    LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
                     WHERE 1 = 1 AND ct.contextlevel = 50 AND (ue.timecreated BETWEEN 1669372800 AND 1669372865 OR
                    u.timemodified BETWEEN 1669372800 AND 1669372865 OR ue.timemodified BETWEEN 1669372800 AND 1669372865 OR c.timemodified BETWEEN 1669372800 AND 1669372865 OR cc.timecompleted BETWEEN 1669372800 AND 1669372865 OR ra.timemodified BETWEEN 1669372800 AND 1669372865 OR lcc1.timemodified BETWEEN 1669372800 AND 1669372865 OR e.timemodified BETWEEN 1669372800 AND 1669372865) ";
            $examrecords = $DB->get_recordset_sql($sql, array());
            foreach ($examrecords as $examrecord) {
                $record = new stdClass;
                $record->examid = $examrecord->examid;
                $record->examname = $examrecord->examname;
                $record->vendorid = !empty($examrecord->vendorid) ? $examrecord->vendorid : 0;
                $record->vendorname = !empty($examrecord->vendorname) ? $examrecord->vendorname : '';
                $record->userid = $examrecord->userid;
                $record->username = $examrecord->username;
                $record->enroldate = $examrecord->enroldate;
                $record->completiondate = !empty($examrecord->completiondate) ? $examrecord->completiondate : 0;
                $record->deadline = !empty($examrecord->deadline) ? $examrecord->deadline : 0;
                $record->user_costcenterid = (!empty($examrecord->user_costcenterid) && ($examrecord->user_costcenterid > 0)) ? $examrecord->user_costcenterid : 0;
                $record->user_departmentid = (!empty($examrecord->user_departmentid) && ($examrecord->user_departmentid > 0)) ? $examrecord->user_departmentid : 0;
                $record->costcenterid = (!empty($examrecord->costcenterid) && ($examrecord->costcenterid > 0)) ? $examrecord->costcenterid : 0;
                $record->departmentid = (!empty($examrecord->departmentid) && ($examrecord->departmentid > 0) ) ? $examrecord->departmentid : 0;
                $record->timecreated = !empty($examrecord->timecreated) ? $examrecord->timecreated : 0;
                $record->usermodified = !empty($examrecord->usermodified) ? $examrecord->usermodified : 0;
                $record->open_contentvendor = !empty($examrecord->open_contentvendor) ? $examrecord->open_contentvendor : 0;
                
                $record->subdepartment = (!empty($examrecord->subdepartment) && ($examrecord->subdepartment > 0))? $examrecord->subdepartment : 0;
                $record->user_subdepartment = (!empty($examrecord->user_subdepartment) && ($examrecord->user_subdepartment > 0)) ? $examrecord->user_subdepartment: 0;

                $record->upcomingeol = !empty($examrecord->upcomingeol) ? $examrecord->upcomingeol : 0; 
                $record->upcomingexpiry = !empty($examrecord->upcomingexpiry) ? $examrecord->upcomingexpiry : 0; 
                $record->refid = !empty($examrecord->refid) ? ($examrecord->refid) : 0 ;
                $records1 = $DB->get_field('block_ls_exams', 'id', 
                            array('examid' => $record->examid,
                                'vendorid' => $record->vendorid,
                                    'userid' => $record->userid,
                                    'refid' => $record->refid));
                if (!empty($records1)) {
                   $record->id = $records1; 
                   $record->examid = $record->examid; 
                   $record->examname = $record->examname;
                   $record->vendorid = $record->vendorid;
                   $record->vendorname = $record->vendorname;
                   $record->userid = $record->userid;
                   $record->username = $record->username;
                   $record->enroldate = $record->enroldate;
                   $record->completiondate = $record->completiondate;
                   $record->deadline = $record->deadline;
                   $record->user_costcenterid = $record->user_costcenterid;
                   $record->user_departmentid = $record->user_departmentid;
                   $record->costcenterid = $record->costcenterid;
                   $record->departmentid = $record->departmentid;
                   $record->timecreated = $record->timecreated;
                   $record->usermodified = $record->usermodified;
                   $record->open_contentvendor = $record->open_contentvendor;
                   $record->subdepartment = $record->subdepartment;
                   $record->user_subdepartment = $record->user_subdepartment;
                   $record->upcomingeol = $record->upcomingeol;
                   $record->upcomingexpiry = $record->upcomingexpiry;
                   $record->refid = $record->refid;
                   $DB->update_record('block_ls_exams', $record);
                } else {
                    $DB->insert_record('block_ls_exams',  $record);
                }
            } 
            set_config('examsinformation', time(), 'block_learnerscript');
            print_r('Completed updating exams data');
       /* }*/
