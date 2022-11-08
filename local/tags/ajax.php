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
 * Process ajax requests
 *
 * @author Sreenivas <sreenivasula@eabyas.in>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package local_tags
 */

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require(__DIR__.'/../../config.php');

$action = optional_param('action', '', PARAM_ALPHA);
$requestData = $_REQUEST;

if($requestData['action'] == 'gettagsdata'){
    $action = $requestData['action'];
}


$context =(new \local_tags\lib\accesslib())::get_module_context();
require_login();
$PAGE->set_context($context);
$return = false;
switch ($action) {
    case 'gettagsdata':
        global $OUTPUT, $USER;
        $userid = $requestData['params']['userid'];
        // get recent completed course
        $sql = "SELECT id, course from {course_completions} where timecompleted is not null and userid = ?
        order by id desc ";//limit 1
        $latestcompelted = $DB->get_record_sql($sql, [$userid]);

        if (!empty($latestcompelted)) {
            $completedtag = $DB->get_record("tag_instance", ['component'=>'local_courses', 'itemtype'=>'courses', 'itemid'=>$latestcompelted->course]);
            
        } else {
            // fetch highest enrolled course
            $sql = "SELECT  c.id as cid,c.fullname, COUNT( ra.id ) AS users FROM {role_assignments} AS ra
            JOIN {context} AS ctx ON ra.contextid = ctx.id
            JOIN {course} as c ON ctx.instanceid = c.id
            WHERE ra.roleid = 5 GROUP BY c.id, c.fullname order by users desc ";
            $record = $DB->get_record_sql($sql, null, IGNORE_MULTIPLE);

            $completedtag = $DB->get_record("tag_instance", ['component'=>'local_courses', 'itemtype'=>'courses', 'itemid'=>$record->cid]);       
        }

        if (empty($completedtag)) {
            return false;
        }
        // print_object($completedtag);
        // $relatedmodules = $DB->get_records("tag_instance", [ 'tagid'=>$completedtag->tagid]);
        $relatedmodules = $DB->get_records_sql("SELECT tg.id, tg.itemid, tg.component from {tag_instance} tg
            join {local_tags} t ON t.tagid = tg.tagid AND t.taginstanceid = tg.id 

            where t.open_costcenterid = :open_costcenterid AND t.open_departmentid = :open_departmentid AND tg.tagid = :tagid ", [ 'tagid'=>$completedtag->tagid,'open_costcenterid'=>$USER->open_costcenterid,'open_departmentid'=>$USER->open_departmentid]);
        foreach ($relatedmodules as $relatedmodule) {
            switch ($relatedmodule->component) {
                case 'local_evaluation':
                $data['name'] = $DB->get_field("local_evaluations", 'name', [ 'id'=>$relatedmodule->itemid]);
                $url = $CFG->wwwroot.'/local/evaluation/complete.php?id='.$relatedmodule->itemid.'';
                $url = $CFG->wwwroot.'/local/tags/index.php?id='.$completedtag->tagid.'#local_evaluation_evaluation';
                $data['link'] = html_writer::link($url, get_string('view'));
                $data['modtype'] = get_string('pluginname', 'local_evaluation');;
                break;
                case 'local_onlinetests':
                $data['name'] = $DB->get_field("local_onlinetests", 'name', [ 'quizid'=>$relatedmodule->itemid]);
                /*$url = $CFG->wwwroot.'/mod/quiz/view.php?id='.$relatedmodule->itemid.'';*/
                $url = $CFG->wwwroot.'/local/tags/index.php?id='.$completedtag->tagid.'#local_onlinetests_onlinetests';
                $data['link'] = html_writer::link($url, get_string('view'));
                $data['modtype'] = get_string('pluginname', 'local_onlinetests');
                break;
                case 'local_courses':
                $data['name'] = $DB->get_field("course", 'fullname', [ 'id'=>$relatedmodule->itemid]);
                /*$url = $CFG->wwwroot.'/course/view.php?id='.$relatedmodule->itemid.'';*/
                $url = $CFG->wwwroot.'/local/tags/index.php?id='.$completedtag->tagid.'#local_courses_courses';
                $data['link'] = html_writer::link($url, get_string('view'));
                $data['modtype'] = get_string('pluginname', 'local_courses');
                break;
                case 'local_forum':
                $data['name'] = $DB->get_field("local_forum", 'name', [ 'id'=>$relatedmodule->itemid]);
                /*$url = $CFG->wwwroot.'/local/forum/view.php?f='.$relatedmodule->itemid.'';*/
                $url = $CFG->wwwroot.'/local/tags/index.php?id='.$completedtag->tagid.'#local_forum_forum';
                $data['link'] = html_writer::link($url, get_string('view'));
                $data['modtype'] = get_string('pluginname', 'local_forum');
                break;
                case 'local_classroom':
                $data['name'] = $DB->get_field("local_classroom", 'name', [ 'id'=>$relatedmodule->itemid]);
                /*$url = $CFG->wwwroot.'/local/classroom/view.php?cid='.$relatedmodule->itemid.'';*/
                $url = $CFG->wwwroot.'/local/tags/index.php?id='.$completedtag->tagid.'#local_classroom_classroom';
                $data['link'] = html_writer::link($url, get_string('view'));
                $data['modtype'] = get_string('pluginname', 'local_classroom');
                break;
                case 'local_program':
                $data['name'] = $DB->get_field("local_program", 'name', [ 'id'=>$relatedmodule->itemid]);
                /*$url = $CFG->wwwroot.'/local/program/view.php?bcid='.$relatedmodule->itemid.'';*/
                $url = $CFG->wwwroot.'/local/tags/index.php?id='.$completedtag->tagid.'#local_program_program';
                $data['link'] = html_writer::link($url, get_string('view'));
                $data['modtype'] = get_string('pluginname', 'local_program');
                break;
                case 'local_certification':
                $data['name'] = $DB->get_field("local_certification", 'name', [ 'id'=>$relatedmodule->itemid]);
                /*$url = $CFG->wwwroot.'/local/certification/view.php?ctid='.$relatedmodule->itemid.'';*/
                $url = $CFG->wwwroot.'/local/tags/index.php?id='.$completedtag->tagid.'#local_certification_certification';
                $data['link'] = html_writer::link($url, get_string('view'));
                $data['modtype'] = get_string('pluginname', 'local_certification');
                break;
            }
            $popdata[] = $data;
        }

        $viewmore = 0;
        $tagcount = count($relatedmodules);
        if($tagcount >= 5){
            $viewmore = 1;
            $viewurl = $CFG->wwwroot.'/local/tags/index.php?id='.$completedtag->tagid.'';
            $viewmorelink = html_writer::link($viewurl, get_string('viewmore'));
        }
        $suggestedtags = implode(',', $name);
        /*$information = get_string('suggestedmodules', 'local_tags'). ':'. $suggestedtags;*/
        $info = array(
            'popdata' => $popdata,
            'viewmore' => $viewmore,
            'viewmorelink' => $viewmorelink,
            'tagcount' => $tagcount,
        );
        $information = $OUTPUT->render_from_template('local_tags/popup', $info);
        echo json_encode($information);
    break;
}
