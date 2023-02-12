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
 * @subpackage local_program
 */
defined('MOODLE_INTERNAL') || die();
global $PAGE, $OUTPUT;

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/local/program/lib.php');
use \local_program\program as program;
use \local_program\form\program_form as program_form;

class local_program_external extends external_api {

    public static function program_instance_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'ID', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id', false),
                'form_status' => new external_value(PARAM_INT, 'Form position', 0),
                'jsonformdata' => new external_value(PARAM_RAW, 'Submitted Form Data', false),
            )
        );
    }

    public static function program_instance($id, $categorycontextid, $form_status, $jsonformdata) {
        global $PAGE, $DB, $CFG, $USER;
        $categorycontext = context::instance_by_id($categorycontextid, MUST_EXIST);
        self::validate_context($categorycontext);
        $serialiseddata = json_decode($jsonformdata);
        $data = array();
        parse_str($serialiseddata, $data);

        $warnings = array();

        $program = new stdClass();

        // The last param is the ajax submitted data.
        $mform = new program_form(null, array('form_status' => $form_status), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        if ($validateddata) {
            // Do the action.
            if($form_status == 0)
                $programid = (new program)->manage_program($validateddata);
            else if ($form_status == 1)
                $programid = (new program)->program_target_audience($validateddata);

            if(class_exists('\block_trending_modules\lib')){
                $trendingclass = new \block_trending_modules\lib();
                if(method_exists($trendingclass, 'trending_modules_crud')){
                    $trendingclass->trending_modules_crud($programid, 'local_program');
                }
            }
        
            // if ($programid > 0) {
            //     $form_status = -1;
            //     $error = false;
            // } else {
            //     $form_status = -1;
            //     $error = true;
            // }
            $formheaders = array_keys($mform->formstatus);
            $next = $form_status + 1;
            $nextform = array_key_exists($next, $formheaders);
            if ($nextform !== false/*&& end($formheaders) !== $form_status*/) {
                $form_status = $next;
                $error = false;
            } else {
                $form_status = -1;
                $error = true;
            }
        } else {
            // Generate a warning.
            throw new moodle_exception('missingprogram', 'local_program');
        }
        $return = array(
            'id' => $programid,
            'form_status' => $form_status);
        return $return;

    }

    public static function program_instance_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'Context id for the framework'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }

    public static function delete_program_instance_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                 'programid' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
                'programname' => new external_value(PARAM_RAW, 'Action of the event', false),
            )
        );
    }

    public static function delete_program_instance($action, $id, $confirm,$programname) {
        global $DB;
        try {
            $categorycontext = (new \local_program\lib\accesslib())::get_module_context($id);
            $DB->delete_records('local_program_level_courses', array('programid' => $id));

            $DB->delete_records('local_bc_course_sessions', array('programid' => $id));

            $DB->delete_records('local_program_users', array('programid' => $id));
            $DB->delete_records('local_program_trainers', array('programid' => $id));
            $DB->delete_records('local_program_trainerfb', array('programid' => $id));

            // delete events in calendar
            $DB->delete_records('event', array('plugin_instance'=>$id, 'plugin'=>'local_program')); // added by sreenivas
            $params = array(
                    'context' => $categorycontext,
                    'objectid' =>$id
            );

            $event = \local_program\event\program_deleted::create($params);
            $event->add_record_snapshot('local_program', $id);
            $event->trigger();
            $DB->delete_records('local_program', array('id' => $id));
            if(class_exists('\block_trending_modules\lib')){
                $trendingclass = new \block_trending_modules\lib();
                if(method_exists($trendingclass, 'trending_modules_crud')){
                    $program_object = new stdClass();
                    $program_object->id = $id;
                    $program_object->module_type = 'local_program';
                    $program_object->delete_record = True;
                    $trendingclass->trending_modules_crud($program_object, 'local_program');
                }
            }
            $return = true;
        } catch (dml_exception $ex) {
            print_error('deleteerror', 'local_program');
            $return = false;
        }
        return $return;
    }
    public static function delete_program_instance_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }

    public static function program_course_selector_parameters() {
        $query = new external_value(
            PARAM_RAW,
            'Query string'
        );
        $includes = new external_value(
            PARAM_ALPHA,
            'What other contexts to fetch the frameworks from. (all, parents, self)',
            VALUE_DEFAULT,
            'parents'
        );
        return new external_function_parameters(array(
            'query' => $query,
            'context' => self::get_context_parameters(),
            'includes' => $includes
        ));
    }

    public static function program_course_selector($query, $categorycontext, $includes = 'parents') {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::program_course_selector_parameters(), array(
            'query' => $query,
            'context' => $categorycontext,
            'includes' => $includes
        ));
        $query = $params['query'];
        $includes = $params['includes'];
        $categorycontext = self::get_context_from_params($params['context']);

        self::validate_context($categorycontext);
        $courses = array();
        if ($query) {
            $queryparams = array();

            $concatsql= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');

            $cousresql = "SELECT c.id, c.fullname
                           FROM {course} AS c
                           JOIN {enrol} AS en on en.courseid = c.id AND en.enrol = 'program' and en.status = 0
                          WHERE c.visible = 1 AND concat(',',c.open_identifiedas,',') LIKE '%,5,%' AND c.fullname LIKE :query AND c.id <> " . SITEID . " {$concatsql}";
            $queryparams['query'] = "%$query%";
            $courses = $DB->get_records_sql($cousresql, $queryparams);
        }

        return array('courses' => $courses);
    }
    public static function program_course_selector_returns() {
        return new external_single_structure(array(
            'courses' => new external_multiple_structure(
                new external_single_structure(array(
                    'id' => new external_value(PARAM_INT, 'ID of the course'),
                    'fullname' => new external_value(PARAM_RAW, 'course fullname'),
                ))
            ),
        ));
    }
    public static function delete_session_instance_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                'programid' => new external_value(PARAM_INT, 'ID of the record', 0),
                'levelid' => new external_value(PARAM_INT, 'ID of the record', 0),
                'bclcid' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
            )
        );
    }

    public static function delete_session_instance($action, $id, $programid, $levelid, $bclcid, $confirm) {
        global $DB, $USER;
        try {
            if ($confirm) {
                $params = array(
                    'context' => $categorycontext,
                    'objectid' =>$id
                );

                $event = \local_program\event\session_deleted::create($params);
                $event->add_record_snapshot('local_bc_course_sessions', $id);
                $event->trigger();

                $DB->delete_records('local_bc_course_sessions', array('id' => $id));
                $return = true;
            } else {
                $return = false;
            }
        } catch (dml_exception $ex) {
            print_error('deleteerror', 'local_program');
            $return = false;
        }
        return $return;
    }

    public static function delete_session_instance_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }

    public static function program_form_option_selector_parameters() {
        $query = new external_value(
            PARAM_RAW,
            'Query string'
        );
        $action = new external_value(
            PARAM_RAW,
            'Action for the program form selector'
        );
        $options = new external_value(
            PARAM_RAW,
            'Action for the program form selector'
        );

        return new external_function_parameters(array(
            'query' => $query,
            'context' => self::get_context_parameters(),
            'action' => $action,
            'options' => $options
        ));
    }

    public static function program_form_option_selector($query, $categorycontext, $action, $options) {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::program_form_option_selector_parameters(), array(
            'query' => $query,
            'context' => $categorycontext,
            'action' => $action,
            'options' => $options
        ));
        $query = trim($params['query']);
        $action = $params['action'];
        $categorycontext = self::get_context_from_params($params['context']);
        $options = $params['options'];
        if (!empty($options)) {
            $formoptions = json_decode($options);
        }
        self::validate_context($categorycontext);
        if ($query && $action) {
            $querieslib = new \local_program\local\querylib();
            $return = array();

            switch($action) {
                case 'program_trainer_selector':
                    $parent = array();
                    if ($formoptions->parnetid > 0) {
                        $parent = array($formoptions->parnetid);
                    }
                    $return = $querieslib->get_user_department_trainerslist(true, $parent, array(),
                        $query);
                break;
                case 'program_institute_selector':
                    $service = array();
                    $service['programid'] = $formoptions->programid;
                    $service['query'] = $query;
                    $return = $querieslib->get_program_institutes($formoptions->institute_type, $service);
                break;
                case 'programsession_trainer_selector':
                    $parent = array();
                    if ($formoptions->parnetid > 0) {
                        $parent = array($formoptions->parnetid);
                    }
                    $return = $querieslib->get_user_department_trainerslist(true, $parent,
                        array(), $query);
                break;
                case 'program_completions_sessions_selector':
                    $sessions_sql = "SELECT id, name as fullname
                                        FROM {local_bc_course_sessions}
                                        WHERE programid = {$formoptions->programid}";
                    $return = $DB->get_records_sql($sessions_sql);
                break;
                case 'program_completions_courses_selector':
                    $courses_sql = "SELECT c.id, c.fullname FROM {course} as c JOIN {local_program_level_courses} as lcc on lcc.courseid=c.id where lcc.programid = {$formoptions->programid}";
                    $return = $DB->get_records_sql($courses_sql);
                break;
                case 'program_room_selector':
                    if (!empty($formoptions->instituteid)) {
                        $locationroomlistssql = "SELECT cr.id, cr.name AS fullname
                                           FROM {local_location_room} AS cr
                                           WHERE cr.visible = 1 AND cr.instituteid = {$formoptions->instituteid}";
                        $return = $DB->get_records_sql($locationroomlistssql);
                    } else {
                        $return = array();
                    }

                break;
            }
            return json_encode($return);
        }
    }
    public static function program_form_option_selector_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    public static function program_session_instance_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'ID', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id', false),
                'form_status' => new external_value(PARAM_INT, 'Form position', 0),
                'jsonformdata' => new external_value(PARAM_RAW, 'Submitted Form Data', false)
            )
        );
    }
    public static function program_session_instance($id, $categorycontextid, $form_status, $jsonformdata) {
        global $PAGE, $DB, $CFG, $USER;
        $categorycontext = context::instance_by_id($categorycontextid, MUST_EXIST);
        self::validate_context($categorycontext);
        $serialiseddata = json_decode($jsonformdata);
        $data = array();
        parse_str($serialiseddata, $data);

        $warnings = array();
        $program = new stdClass();

        // The last param is the ajax submitted data.
        $mform = new \local_program\form\session_form(null, array('id' => $data['id'],
            'bcid' => $data['programid'], 'levelid' => $data['levelid'],
            'bclcid' => $data['bclcid'], 'form_status' => $form_status), 'post', '', null,
             true, $data);
        $validateddata = $mform->get_data();
        if ($validateddata) {
            // Do the action.
            $sessionid = (new program)->manage_bc_courses_sessions($validateddata);
            if ($sessionid > 0) {
                $form_status = -2;
                $error = false;
            } else {
                $error = true;
            }
        } else {
            // Generate a warning.
            throw new moodle_exception('missingprogram', 'local_program');
        }
        $return = array(
            'id' => $sessionid,
            'form_status' => $form_status);
        return $return;
    }

    public static function program_session_instance_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'Context id for the framework'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }
    public static function program_completion_settings_instance_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'ID', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id', false),
                'form_status' => new external_value(PARAM_INT, 'Form position', 0),
                'jsonformdata' => new external_value(PARAM_RAW, 'Submitted Form Data', false),
            )
        );
    }

    public static function program_completion_settings_instance($id, $categorycontextid, $form_status, $jsonformdata) {
        global $PAGE, $DB, $CFG, $USER;
        $categorycontext = context::instance_by_id($categorycontextid, MUST_EXIST);
        self::validate_context($categorycontext);
        $serialiseddata = json_decode($jsonformdata);
        $data = array();
        parse_str($serialiseddata, $data);

        $warnings = array();
        $program = new stdClass();
        // The last param is the ajax submitted data.
        $mform = new \local_program\form\program_completion_form(null, array('id' => $data['id'],
            'bcid' => $data['programid'], 'form_status' => $form_status), 'post', '', null,
             true, $data);
        $validateddata = $mform->get_data();
        if ($validateddata) {
            // Do the action.
            $program_completionid = (new program)->manage_program_completions($validateddata);
            if ($program_completionid > 0) {
                $form_status = -2;
                $error = false;
            } else {
                $error = true;
            }
        } else {
            // Generate a warning.
            throw new moodle_exception('missingprogram', 'local_program');
        }
        $return = array(
            'id' => $program_completionid,
            'form_status' => $form_status);
        return $return;
    }

    public static function program_completion_settings_instance_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'Context id for the framework'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }
    public static function program_course_instance_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'ID', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id', false),
                'form_status' => new external_value(PARAM_INT, 'Form position', 0),
                'jsonformdata' => new external_value(PARAM_RAW, 'Submitted Form Data', false),
            )
        );
    }

    public static function program_course_instance($id, $categorycontextid, $form_status, $jsonformdata) {
        global $PAGE, $DB, $CFG, $USER;
        $categorycontext = context::instance_by_id($categorycontextid, MUST_EXIST);
        self::validate_context($categorycontext);
        $serialiseddata = json_decode($jsonformdata);
        $data = array();
        parse_str($serialiseddata, $data);

        $warnings = array();
        $program = new stdClass();

        // The last param is the ajax submitted data.
        $mform = new programcourse_form(null, array('bcid' => $data['programid'],
            'levelid' => $data['levelid'], 'form_status' => $form_status),
            'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        if ($validateddata) {
            // Do the action.
            $sessionid = (new program)->manage_program_courses($validateddata);
            if ($sessionid > 0) {
                $form_status = -2;
                $error = false;
            } else {
                $error = true;
            }
        } else {
            // Generate a warning.
            throw new moodle_exception('missingprogram', 'local_program');
        }
        $return = array(
            'id' => $sessionid,
            'form_status' => $form_status);
        return $return;
    }

    public static function program_course_instance_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'Context id for the framework'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }
    public static function delete_programcourse_instance_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                'programid' => new external_value(PARAM_INT, 'program ID', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
            )
        );
    }

    public static function delete_programcourse_instance($action, $id, $programid, $confirm) {
        global $DB;
        try {
            if ($confirm) {
                $course = $DB->get_field('local_program_level_courses', 'courseid', array('programid' => $programid, 'id' => $id));

                $program_completiondata =$DB->get_record_sql("SELECT id,courseids
                                        FROM {local_program_completion}
                                        WHERE programid = $programid");

                if ($program_completiondata->courseids != null) {
                    $program_courseids = explode(',', $program_completiondata->courseids);
                    $array_diff = array_diff($program_courseids, array($course));
                    if (!empty($array_diff)) {
                        $program_completiondata->courseids = implode(',', $array_diff);
                    } else {
                        $program_completiondata->courseids = "NULL";
                    }
                    $DB->update_record('local_program_completion', $program_completiondata);
                    $params = array(
                        'context' => $categorycontext,
                        'objectid' => $program_completiondata->id
                    );

                    $event = \local_program\event\program_completions_settings_updated::create($params);
                    $event->add_record_snapshot('local_program', $programid);
                    $event->trigger();
                }

                $programtrainers = $DB->get_records_menu('local_program_trainers',
                    array('programid' => $programid), 'trainerid', 'id, trainerid');
                if (!empty($programtrainers)) {
                    foreach ($programtrainers as $programtrainer) {
                        $unenrolprogramtrainer = (new program)->manage_program_course_enrolments($course, $programtrainer,
                            'editingteacher', 'unenrol');
                    }
                }
                $programusers = $DB->get_records_menu('local_program_users',
                    array('programid' => $programid), 'userid', 'id, userid');
                if (!empty($programusers)) {
                    foreach ($programusers as $programuser) {
                        $unenrolprogramuser = (new program)->manage_program_course_enrolments($course, $programuser,
                            'employee', 'unenrol');
                    }
                }
                $params = array(
                    'context' => $categorycontext,
                    'objectid' =>$id
                );

                $event = \local_program\event\program_courses_deleted::create($params);
                $event->add_record_snapshot('local_program_level_courses', $id);
                $event->trigger();
                $DB->delete_records('local_program_level_courses', array('id' => $id));
                $return = true;
            } else {
                $return = false;
            }
        } catch (dml_exception $ex) {
            print_error('deleteerror', 'local_program');
            $return = false;
        }
        return $return;
    }

    public static function delete_programcourse_instance_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }

    /*sree*/
    public static function submit_instituteform_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array')
            )
        );
    }

    /**
     * form submission of institute name and returns instance of this object
     *
     * @param int $categorycontextid
     * @param [string] $jsonformdata
     * @return institute form submits
     */
    public function submit_catform_form($categorycontextid, $jsonformdata){
        global $PAGE, $CFG;

        require_once($CFG->dirroot . '/local/program/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_instituteform_form_parameters(),
                                    ['contextid' => $categorycontextid, 'jsonformdata' => $jsonformdata]);
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($categorycontext);
        $serialiseddata = json_decode($params['jsonformdata']);
        // throw new moodle_exception('Error in creation');
        // die;
        $data = array();

        parse_str($serialiseddata, $data);
        $warnings = array();
         $mform = new local_program\form\catform(null, array(), 'post', '', null, true, $data);
        $category  = new local_program\event\category();
        $valdata = $mform->get_data();

        if ($valdata) {
            if ($valdata->id > 0) {
                $category->category_update_instance($valdata);
            } else {
                $category->category_insert_instance($valdata);
            }
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
    }


    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_catform_form_returns() {
        return new external_value(PARAM_INT, 'category id');
    }

    public static function manageprogramlevels_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id', true, 1),
                'form_status' => new external_value(PARAM_INT, 'Form position', false, 0),
                'jsonformdata' => new external_value(PARAM_RAW, 'Submitted Form Data', false),
            )
        );
    }

    public static function manageprogramlevels($categorycontextid, $form_status, $jsonformdata) {
        global $PAGE, $DB, $CFG, $USER;
        $categorycontext = context::instance_by_id($categorycontextid, MUST_EXIST);
        self::validate_context($categorycontext);
        $serialiseddata = json_decode($jsonformdata);
        $data = array();
        parse_str($serialiseddata, $data);

        $warnings = array();
        $program = new stdClass();

        // The last param is the ajax submitted data.
        $mform = new program_managelevel_form(null, array('id' => $data['id'],
            'programid' => $data['programid'],
            'form_status' => $form_status), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        if ($validateddata) {
            // Do the action.
            $stream = $DB->get_field('local_program', 'stream',
                array('id' => $validateddata->programid));
            $action = 'create';
            if ($validateddata->id > 0) {
                $action = 'update';
            }
            $sessionid = (new program)->manage_program_stream_levels($validateddata);
            if ($sessionid > 0) {
                $form_status = -2;
                $error = false;
            } else {
                $error = true;
            }
        } else {
            // Generate a warning.
            throw new moodle_exception('missingprogram', 'local_program');
        }
        $return = array(
            'id' => $sessionid,
            'form_status' => $form_status);
        return $return;
    }

    public static function manageprogramlevels_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'Context id for the framework'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }
    public static function bclevel_unassign_course_parameters(){
        return new external_function_parameters(
            array(
                'programid' => new external_value(PARAM_INT, 'ID of the program'),
                'levelid' => new external_value(PARAM_INT, 'ID of the program level'),
                'bclcid' => new external_value(PARAM_INT, 'ID of the program level course to be unassigned')
            )
        );
    }
    public static function bclevel_unassign_course($programid, $levelid, $bclcid){
        if ($programid > 0 && $bclcid > 0 && $levelid > 0) {
            $program = new program();
            $program->unassign_courses_to_bclevel($programid, $levelid, $bclcid);
            return true;
        } else {
            throw new moodle_exception('Error in unassigning of course');
            return false;
        }
    }
    public static function bclevel_unassign_course_returns(){
        return new external_value(PARAM_BOOL, 'return');
    }
    public static function bc_session_enrolments_parameters(){
        return new external_function_parameters(
            array(
                'programid' => new external_value(PARAM_INT, 'ID of the program', VALUE_REQUIRED),
                'levelid' => new external_value(PARAM_INT, 'ID of the program level', VALUE_REQUIRED),
                'bclcid' => new external_value(PARAM_INT, 'ID of the program level course to be unassigned', VALUE_REQUIRED),
                'sessionid' => new external_value(PARAM_INT, 'ID of the session', VALUE_REQUIRED),
                'signupid' => new external_value(PARAM_INT, 'ID of the session signup', false, 0),
                'enrol' => new external_value(PARAM_INT, 'enroment action status', VALUE_REQUIRED)
            )
        );
    }
    public static function bc_session_enrolments($programid, $levelid, $bclcid, $sessionid, $signupid, $enrol) {
        global $USER;
        $enroldata = new stdClass();
        $enroldata->programid = $programid;
        $enroldata->levelid = $levelid;
        $enroldata->bclcid = $bclcid;
        $enroldata->sessionid = $sessionid;
        $enroldata->signupid = $signupid;
        $enroldata->enrol = $enrol;
        $enroldata->userid = $USER->id;
        $return = (new program)->bc_session_enrolments($enroldata);
    }
    public static function bc_session_enrolments_returns(){
        return new external_value(PARAM_BOOL, 'return');
    }

    public static function delete_level_instance_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                'programid' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
            )
        );
    }

    public static function delete_level_instance($action, $id, $programid, $confirm) {
        global $DB,$USER;
        try {

            $DB->delete_records('local_bc_course_sessions', array('levelid' => $id));
            $DB->delete_records('local_program_level_courses', array('levelid' => $id));
            // delete events in calendar
            // $DB->delete_records('event', array('plugin_instance'=>$id, 'plugin'=>'local_program')); // added by sreenivas
            $params = array(
                    'context' => $categorycontext,
                    'objectid' =>$id
            );

            $event = \local_program\event\level_deleted::create($params);
            $event->add_record_snapshot('local_program_levels', $id);
            $event->trigger();
//            $levels = $DB->get_records_sql("SELECT lpl.id, lpl.position FROM {local_program_levels} AS lpl WHERE lpl.position > {$position} AND lpl.programid = {$programid} ");
//            if(count($levels) > 0){
//                foreach($levels AS $level){
//                    --$level->position;
//                    $DB->update_record('local_program_levels', $level);
//                }
//            }
            $DB->delete_records('local_program_levels', array('id' => $id));
            $return = true;
        } catch (dml_exception $ex) {
            print_error('deleteerror', 'local_program');
            $return = false;
        }
        return $return;
    }

    public static function delete_level_instance_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }
    public static function manageprogramstreams_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id', true, 1),
                'form_status' => new external_value(PARAM_INT, 'Form position', false, 0),
                'jsonformdata' => new external_value(PARAM_RAW, 'Submitted Form Data', false),
            )
        );
    }

    public static function manageprogramstreams($categorycontextid, $form_status, $jsonformdata) {
        global $PAGE, $DB, $CFG, $USER;
        $categorycontext = context::instance_by_id($categorycontextid, MUST_EXIST);
        self::validate_context($categorycontext);
        $serialiseddata = json_decode($jsonformdata);
        $data = array();
        parse_str($serialiseddata, $data);

        $warnings = array();
        $program = new stdClass();

        // The last param is the ajax submitted data.
        $mform = new program_managestream_form(null, array('id' => $data['id'],
            'form_status' => $form_status), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        if ($validateddata) {
            $action = 'create';
            if ($validateddata->id > 0) {
                $action = 'update';
            }
            $streamid = (new program)->manage_program_streams($validateddata);
            if ($streamid > 0) {
                $form_status = -2;
                $error = false;
            } else {
                $error = true;
            }
        } else {
            // Generate a warning.
            throw new moodle_exception('missingprogram', 'local_program');
        }
        $return = array(
            'id' => $streamid,
            'form_status' => $form_status);
        return $return;
    }

    public static function manageprogramstreams_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'Context id for the framework'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }

    public static function delete_stream_instance_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
            )
        );
    }

    public static function delete_stream_instance($action, $id, $confirm) {
        global $DB, $USER;
        try {
            if ($confirm) {
                $params = array(
                    'context' => $categorycontext,
                    'objectid' =>$id
                );

                $event = \local_program\event\program_stream_deleted::create($params);
                $event->add_record_snapshot('local_program_stream', $id);
                $event->trigger();
                
                $DB->delete_records('local_program_stream', array('id' => $id));
                $return = true;
            } else {
                $return = false;
            }
        } catch (dml_exception $ex) {
            print_error('deleteerror', 'local_program');
            $return = false;
        }
        return $return;
    }

    public static function delete_stream_instance_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }

    public static function manageprogramStatus_instance_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_RAW, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                 'programid' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
                'actionstatusmsg' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'programname' => new external_value(PARAM_RAW, 'Action of the event', false),
            )
        );
    }

    public static function manageprogramStatus_instance($action, $id, $confirm,$actionstatusmsg,$programname) {
        global $DB,$USER;
        try {
            if ($action === 'selfenrol') {
                $return = (new program)->program_self_enrolment($id,$USER->id, $selfenrol=1);          
            }else{
                $return = (new program)->program_status_action($id, $action);
            }
       
        } catch (dml_exception $ex) {
            print_error('deleteerror', 'local_program');
            $return = false;
        }
        return $return;
    }

    public static function manageprogramStatus_instance_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }
    public static function inactive_program_instance_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                 'programid' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
                'programname' => new external_value(PARAM_RAW, 'Action of the event', false),
            )
        );
    }

    public static function inactive_program_instance($action, $id, $confirm,$programname) {
        global $DB;
        try {
            $program=$DB->get_record('local_program',array('id'=>$id));
            $program->visible=0;
            $DB->update_record('local_program', $program);
            if(class_exists('\block_trending_modules\lib')){
                $dataobject = new stdClass();
                $dataobject->update_status = True;
                $dataobject->id = $id;
                $dataobject->module_type = 'local_program';
                $dataobject->module_visible = 0;
                $class = (new \block_trending_modules\lib())->trending_modules_crud($dataobject, 'local_program');
            }
            $params = array(
                    'context' => $categorycontext,
                    'objectid' =>$id
            );
            $event = \local_program\event\program_inactivated::create($params);
            $event->add_record_snapshot('local_program', $id);
            $event->trigger();
            $return = true;
        } catch (dml_exception $ex) {
            print_error('inactiveerror', 'local_program');
            $return = false;
        }
        return $return;
    }
    public static function inactive_program_instance_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }
    public static function active_program_instance_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                 'programid' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
                'programname' => new external_value(PARAM_RAW, 'Action of the event', false),
            )
        );
    }

    public static function active_program_instance($action, $id, $confirm,$programname) {
        global $DB;
        try {
            $program=$DB->get_record('local_program',array('id'=>$id));
            $program->visible=1;
            $DB->update_record('local_program', $program);
            if(class_exists('\block_trending_modules\lib')){
                $dataobject = new stdClass();
                $dataobject->update_status = True;
                $dataobject->id = $id;
                $dataobject->module_type = 'local_program';
                $dataobject->module_visible = 1;
                $class = (new \block_trending_modules\lib())->trending_modules_crud($dataobject, 'local_program');
            }
            $params = array(
                    'context' => $categorycontext,
                    'objectid' =>$id
            );
            $event = \local_program\event\program_activated::create($params);
            $event->add_record_snapshot('local_program', $id);
            $event->trigger();
            $return = true;
        } catch (dml_exception $ex) {
            print_error('inactiveerror', 'local_program');
            $return = false;
        }
        return $return;
    }
    public static function active_program_instance_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }
    public static function organization_streams_parameters() {
        return new external_function_parameters(
            array(
                'orgid' => new external_value(PARAM_INT, 'The id for the costcenter / organization'),
            )
        );
    }
    public static function organization_streams($orgid) {
        global $DB;

        $params = array();
        $program_streamsql = "SELECT lps.id,lps.stream FROM {local_program_stream} AS lps WHERE concat('/',lps.open_path,'/') LIKE :costcenter ";

        $params['costcenter'] = '%'.$orgid.'%';

        $data = $DB->get_records_sql_menu($program_streamsql, $params);

          return json_encode($data);
    }
    public static function organization_streams_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    public static function data_for_programs_parameters(){
        $filter = new external_value(PARAM_TEXT, 'Filter text');
        $filter_text = new external_value(PARAM_TEXT, 'Filter name',VALUE_OPTIONAL);
        $filter_offset = new external_value(PARAM_INT, 'Offset value',VALUE_OPTIONAL);
        $filter_limit = new external_value(PARAM_INT, 'Limit value',VALUE_OPTIONAL);
        $params = array(
            'filter' => $filter,
            'filter_text' => $filter_text,
            'filter_offset' => $filter_offset,
            'filter_limit' => $filter_limit
        );
        return new external_function_parameters($params);
    }
    public function data_for_programs($filter, $filter_text='', $filter_offset = 0, $filter_limit = 0){
        global $PAGE;

        $params = self::validate_parameters(self::data_for_programs_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
            'filter_offset' => $filter_offset,
            'filter_limit' => $filter_limit
        ));

        $PAGE->set_context($categorycontext);
        $renderable = new local_program\output\program_courses($params['filter'],$params['filter_text'], $params['filter_offset'], $params['filter_limit']);
        $output = $PAGE->get_renderer('block_userdashboard');

        $data= $renderable->export_for_template($output);

        return $data;
    }
    public function data_for_programs_returns(){
        return new external_single_structure(array (
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'),
            'program_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'), 
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
            'functionname' => new external_value(PARAM_TEXT, 'Function name'),
            'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
            'programtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
            'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
            'enableflow' => new external_value(PARAM_BOOL, "flag for flow enabling", VALUE_DEFAULT, true),
            'moduledetails' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'ProgramDescription' => new external_value(PARAM_RAW, 'Description of Program'),
                        'ProgramFullname' => new external_value(PARAM_RAW, 'Fullname of Program'),
                        'DisplayProgramFullname' => new external_value(PARAM_RAW, 'Displayed Program Fullname'),
                        'ProgramUrl' => new external_value(PARAM_RAW, 'Url for the Program'),
                        'ProgramIcon' => new external_value(PARAM_RAW, 'Icon for the program'),
                        'rating_element' => new external_value(PARAM_RAW, 'Rating Element for Program'),
                        'index' => new external_value(PARAM_INT, 'Index of Card'),
                    )
                )
            ),
            'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
            'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
            'index' => new external_value(PARAM_INT, 'number of courses count'),
            'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
            'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
            'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
            'viewMoreCard' => new external_value(PARAM_BOOL, 'More info card to display'),
            'enrolled_url' => new external_value(PARAM_URL, 'enrolled_url for tab'),//added revathi
            'inprogress_url' => new external_value(PARAM_URL, 'inprogress_url for tab'),
            'completed_url' => new external_value(PARAM_URL, 'completed_url for tab'),
        ));
    }
    public static function data_for_programs_paginated_parameters(){
        return new external_function_parameters([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                VALUE_DEFAULT, 0),
            'contextid' => new external_value(PARAM_INT, 'contextid'),
            'filterdata' => new external_value(PARAM_RAW, 'filters applied'),
        ]);
    }
    public static function data_for_programs_paginated($options, $dataoptions, $offset = 0, $limit = 0, $categorycontextid, $filterdata){
        global $DB, $PAGE;
        require_login();
        $PAGE->set_context($categorycontextid);

        $decodedoptions = (array)json_decode($options);
        $decodedfilter = (array)json_decode($filterdata);
        $PAGE->set_url('/local/program/userdashboard.php', array('tab' => $filter));
        $filter = $decodedoptions['filter'];
        $filter_text = isset($decodedfilter['search_query']) ? $decodedfilter['search_query'] : '';
        $filter_offset = $offset;
        $filter_limit = $limit;

        $PAGE->set_context($categorycontext);
        $renderable = new local_program\output\program_courses($filter, $filter_text, $filter_offset, $filter_limit);
        $output = $PAGE->get_renderer('local_program');

        $data = $renderable->export_for_template($output);
        $totalcount = $renderable->coursesViewCount;
        return [
            'totalcount' => $totalcount,
            'length' => $totalcount,
            'filterdata' => $filterdata,
            'records' => array($data),
            'options' => $options,
            'dataoptions' => $dataoptions,
        ];
    }
    public static function data_for_programs_paginated_returns(){
        return new external_single_structure([
        'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
        'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
        'totalcount' => new external_value(PARAM_INT, 'total number of challenges in result set'),
        'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        'records' => new external_multiple_structure(
                new external_single_structure(array (
                    'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),
                    'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),
                    'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'),
                    'program_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'), 
                    // 'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
                    'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
                    'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
                    'functionname' => new external_value(PARAM_TEXT, 'Function name'),
                    'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
                    'programtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
                    'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
                    'enableflow' => new external_value(PARAM_BOOL, "flag for flow enabling", VALUE_DEFAULT, false),
                    'moduledetails' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'ProgramDescription' => new external_value(PARAM_RAW, 'Description of Program'),
                                'ProgramFullname' => new external_value(PARAM_RAW, 'Fullname of Program'),
                                'DisplayProgramFullname' => new external_value(PARAM_RAW, 'Displayed Program Fullname'),
                                'ProgramUrl' => new external_value(PARAM_RAW, 'Url for the Program'),
                                'ProgramIcon' => new external_value(PARAM_RAW, 'Icon for the program'),
                                'rating_element' => new external_value(PARAM_RAW, 'Rating Element for Program')
                            )
                        )
                    ),
                    'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
                    'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
                    'index' => new external_value(PARAM_INT, 'number of courses count'),
                    'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
                    'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
                    // 'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
                )
            ) 
        )
    ]);
    }
    public static function unenrol_user_parameters(){
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context for the service'),
            'programid' => new external_value(PARAM_INT, 'Program id for the service'),
            'userid' => new external_value(PARAM_INT, 'Userid For the service')
        ]);
    }
    public static function unenrol_user($categorycontextid, $programid, $userid){
        $params = self::validate_parameters(self::unenrol_user_parameters(), array(
            'contextid' => $categorycontextid,
            'programid' => $programid,
            'userid' => $userid
        ));
        $programclass = new \local_program\program();
        $programclass->program_remove_assignusers($programid, [$userid]);
        return true;
    }
    public static function unenrol_user_returns(){
        return new external_value(PARAM_BOOL, 'return');
    }
}