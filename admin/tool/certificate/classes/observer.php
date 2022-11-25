<?php
// This file is part of the tool_certificate plugin for Moodle - http://moodle.org/
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
 * Class observer for tool_certificate.
 *
 * @package    tool_certificate
 * @author     2020 Mikel Martín <mikel@moodle.com>
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Class tool_certificate_observer
 *
 * @package    tool_certificate
 * @author     2020 Mikel Martín <mikel@moodle.com>
 * @copyright  2020 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_certificate_observer
{
    /**
     * Course deleted observer
     *
     * @param \core\event\course_content_deleted $event
     */
    public static function on_course_content_deleted(\core\event\course_content_deleted $event): void {
        global $DB;

        $fs = get_file_storage();
        $issues = $DB->get_records('tool_certificate_issues', ['courseid' => $event->courseid]);
        foreach ($issues as $issue) {
            $fs->delete_area_files(context_system::instance()->id, 'tool_certificate', 'issues', $issue->id);
        }

        $DB->delete_records('tool_certificate_issues', ['courseid' => $event->courseid]);

    }
//    Mallikarjun added local_certificate abserver data
    /**
     * Triggered when course completed for a user
     *
     * @param \core\event\course_completed $event
     */
    public static function issue_course_certificate(\core\event\course_completed $event) {
        global $DB;

        $crs_certificate = $DB->get_field('course','open_certificateid',array('id'=>$event->courseid));
        
        if(!empty($crs_certificate)){
            self::issue_certificate($event->relateduserid, $event->courseid, 'course', $crs_certificate);
        }
    }


    /**
     * Triggered when classroom completed for a user
     *
     * @param \local_classroom\event\classroom_user_completed $event
     */
    public static function issue_classroom_certificate(\local_classroom\event\classroom_user_completed $event) {
        global $DB;

        $cl_certificate = $DB->get_field('local_classroom','certificateid',array('id'=>$event->objectid));
        
        if(!empty($cl_certificate)){
            self::issue_certificate($event->userid, $event->objectid, 'classroom', $cl_certificate);
        }
    }

    /**
     * Triggered when learningplan completed for a user
     *
     * @param \local_learningplan\event\learningplan_user_completed $event
     */
    public static function issue_learningplan_certificate(\local_learningplan\event\learningplan_user_completed $event) {
        global $DB;

        $lp_certificate = $DB->get_field('local_learningplan','certificateid',array('id'=>$event->objectid));
        
        if(!empty($lp_certificate)){
            self::issue_certificate($event->userid, $event->objectid, 'learningplan', $lp_certificate);
        }
    }


    /**
     * Triggered when program completed for a user
     *
     * @param \local_program\event\program_user_completed $event
     */
    public static function issue_program_certificate(\local_program\event\program_user_completed $event) {
        global $DB;

        $pr_certificate = $DB->get_field('local_program','certificateid',array('id'=>$event->objectid));
        
        if(!empty($pr_certificate)){
            self::issue_certificate($event->userid, $event->objectid, 'program', $pr_certificate);
        }
    }


    /**
     * Triggered when onlinetest completed for a user
     *
     * @param \local_onlinetests\event\onlinetest_completed $event
     */
    public static function issue_onlinetest_certificate(\local_onlinetests\event\onlinetest_completed $event) {
        global $DB;

        $ot_certificate=$DB->get_field('local_onlinetests','certificateid',array('id'=>$event->objectid));
        
        if(!empty($ot_certificate)){
            self::issue_certificate($event->userid, $event->objectid, 'onlinetest', $ot_certificate);
        }
    }

    private static function issue_certificate($userid, $moduleid, $moduletype, $certificateid){
        global $DB, $USER;
        try{
            $dataobj = new stdClass();

            $dataobj->userid = $userid;
            $dataobj->templateid = $certificateid;
            $dataobj->code = \tool_certificate\certificate::generate_code($dataobj->userid);
            $dataobj->moduletype = $moduletype;
            $dataobj->moduleid = $moduleid;
            $dataobj->emailed = 0;
            $dataobj->component = 'tool_certificate';
            $dataobj->courseid = 0;
            $dataobj->timecreated = time();
            $dataobj->usercreated = $USER->id;
            $dataobj->timemodified = time();
            $dataobj->usermodified = $USER->id;
        $dataobj->programid = $moduleid;
        $data['userfullname'] = fullname($DB->get_record('user', ['id' => $userid]));
        $dataobj->data = json_encode($data);
            
            $array = array('userid'=>$userid,'moduleid'=>$moduleid,
                            'moduletype'=>$moduletype);
            $exist_recordid = $DB->get_record('tool_certificate_issues',$array, 'id');
            if($exist_recordid){
                $dataobj->id = $exist_recordid->id;
                $DB->update_record('tool_certificate_issues',$dataobj);
            }else{
                $DB->insert_record('tool_certificate_issues',$dataobj);
            }
        }catch(exception $e){
            print_object($e);
        }
    }
}