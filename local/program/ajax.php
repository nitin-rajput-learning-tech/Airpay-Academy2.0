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
 * @subpackage local_program
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $CFG, $USER, $PAGE;
use local_program\program;

$action = required_param('action', PARAM_ACTION);
$programid = optional_param('programid', 0, PARAM_INT);
$levelid = optional_param('levelid', 0, PARAM_INT);
$bclcid = optional_param('bclcid', 0, PARAM_INT);
$draw = optional_param('draw', 1, PARAM_INT);
$start = optional_param('start', 0, PARAM_INT);
$length = optional_param('length', 10, PARAM_INT);
$search = optional_param_array('search', '', PARAM_RAW);
$tab = optional_param('tab', '', PARAM_RAW);
$programstatus = optional_param('programstatus', -1, PARAM_INT);
$programmodulehead = optional_param('programmodulehead', false, PARAM_BOOL);
$cat = optional_param('categoryname', '', PARAM_RAW);
$categorycontext = (new \local_program\lib\accesslib())::get_module_context();
$costcenterid = optional_param('costcenterid','', PARAM_RAW);
$departmentid = optional_param('departmentid', '', PARAM_RAW);
$subdepartmentid = optional_param('subdepartmentid', '', PARAM_RAW);
$l4department = optional_param('l4department', '', PARAM_RAW);
$l5department = optional_param('l5department', '', PARAM_RAW);
$program = optional_param('program', null, PARAM_RAW);
$status = optional_param('status', null, PARAM_RAW);
$view_type = optional_param('view_type', 'card', PARAM_TEXT);
$categories = optional_param('categories', '', PARAM_RAW);

require_login();
$PAGE->set_context($categorycontext);
$renderer = $PAGE->get_renderer('local_program');
try{
    switch ($action) {
        case 'viewprograms':
            $stable = new stdClass();
            $stable->thead = false;
            $stable->search = $search['value'];
            $stable->start = $start;
            $stable->length = $length;
            $stable->programstatus = $programstatus;
            $stable->costcenterid = $costcenterid;
            $stable->departmentid = $departmentid;
            $stable->subdepartmentid = $subdepartmentid;
            $stable->l4department = $l4department;
            $stable->l5department = $l5department;
            $stable->categories = $categories;
            $return = $renderer->viewprograms($stable,$program,$status,$view_type);
            
        break;

        case 'programsbystatus':
            $stable = new stdClass();
            $stable->thead = true;
            $stable->start = 0;
            $stable->length = -1;
            $stable->search = '';
            $stable->programstatus = $programstatus;
            $return = $renderer->viewprograms($stable);
        break;
        case 'viewprogramcourses':
            $return = $renderer->viewprogramcourses($programid);
        break;
        case 'viewprogramusers':
            $stable = new stdClass();
            $stable->search = $search['value'];
            $stable->start = $start;
            $stable->length = $length;
            $stable->programid = $programid;
            if ($programmodulehead) {
                $stable->thead = true;
            } else {
                $stable->thead = false;
            }
            $return = $renderer->viewprogramusers($stable);
        break;
        case 'manageprogramcategory':
        $rec = new stdClass();
        $rec->fullname = $cat;
        $rec->shortname = $cat;
        if ($rec->id) {
            $DB->update_record('local_program_categories', $rec);
        } else {
            $DB->insert_record('local_program_categories', $rec);
        }
        break;
        case 'programlastchildpopup':
            $stable = new stdClass();
            //$stable->search = $search['value'];
            $stable->start = $start;
            $stable->length = $length;
            if ($programmodulehead) {
                $stable->thead = true;
            } else {
                $stable->thead = false;
            }
            $return = $renderer->viewprogramlastchildpopup($programid, $stable);
        break;
        case 'viewprogramrequested_users_tab':
             $program = $DB->get_records('local_request_records', array('compname' => 'program','componentid' =>
                $programid));

            $output = $PAGE->get_renderer('local_request');
            $component = 'program';
            if ($program) {
                $return = $output->render_requestview(new local_request\output\requestview($program, $component));
            } else {
                $return = '<div class="alert alert-info">'.get_string('requestavail', 'local_program').'</div>';
            }
        break;
        case 'programlevelcourses':
             $return = $renderer->viewprogramcourses($programid, $levelid);
        break;
        case 'classroomlist':
        $courseid = required_param('courseid',  PARAM_INT);
        $program = new local_program\program();
        $data = $program->get_course_classrooms($courseid, $_REQUEST);
        echo json_encode($data);
        die();
        break;
        case 'deletecompletions':

            if($programid){

                $program = new \local_program\program();

                $program->delete_completion_data($programid, $levelid);
            }

        break;
    }

    echo json_encode($return);
}catch(Execption $e){
    throw new moodle_exception(get_string('programerror_in_fetching_data','local_program'));
}