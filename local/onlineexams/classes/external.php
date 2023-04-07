<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or localify
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
 * Courses external API
 *
 * @package    local_onlineexams
 * @category   external
 * @copyright  eAbyas <www.eabyas.in>
 */

defined('MOODLE_INTERNAL') || die;

use \local_onlineexams\form\custom_onlineexams_form as custom_onlineexam_form;
use \local_courses\action\insert as insert;
use \local_courses\local\general_lib as general_lib;
use \local_courses\form\custom_courseevidence_form as custom_courseevidence_form;


require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/courses/lib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
require_once($CFG->dirroot . '/local/onlineexams/lib.php');
require_once('../../config.php');
class local_onlineexams_external extends external_api
{

    /**
     * Describes the parameters for submit_create_course_form webservice.
     * @return external_function_parameters
     */
    public static function submit_create_onlineexams_form_parameters()
    {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                'form_status' => new external_value(PARAM_INT, 'Form position', 0),
                'id' => new external_value(PARAM_INT, 'Course id', 0),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create course form, encoded as a json array')
            )
        );
    }

    /**
     * Submit the create course form.
     *
     * @param int $contextid The context id for the course.
     * @param int $form_status form position.
     * @param int $id exam id -1 as default.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @return int new Exam id.
     */
    public static function submit_create_onlineexams_form($contextid, $form_status, $id, $jsonformdata)
    {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/local/courses/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(
            self::submit_create_onlineexams_form_parameters(),
            ['contextid' => $contextid, 'form_status' => $form_status,  'jsonformdata' => $jsonformdata]
        );

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $data = array();
        $serialiseddata = json_decode($params['jsonformdata']);
        if(is_object($serialiseddata)){
        $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $data);
        $warnings = array();
        if ($id) {
            $exam = get_course($id);
            $category = $DB->get_record('course_categories', array('id' => $exam->category), '*', MUST_EXIST);
        } else {
            $exam = null;
        }
        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->maxbytes, 'trusttext' => false, 'noclean' => true);
        $overviewfilesoptions = course_overviewfiles_options($exam);
        if (!empty($exam)) {
            // Add context for editor.
            $editoroptions['context'] = $coursecontext;
            $editoroptions['subdirs'] = file_area_contains_subdirs($coursecontext, 'course', 'summary', 0);
            $exam = file_prepare_standard_editor($exam, 'summary', $editoroptions, $coursecontext, 'course', 'summary', 0);
            if ($overviewfilesoptions) {
                file_prepare_standard_filemanager($exam, 'overviewfiles', $overviewfilesoptions, $coursecontext, 'course', 'overviewfiles', 0);
            }
            $get_coursedetails = $DB->get_record('course', array('id' => $exam->id));
        } else {
            // Editor should respect category context if course context is not set.
            $editoroptions['context'] = $catcontext;
            $editoroptions['subdirs'] = 0;
            $exam = file_prepare_standard_editor($exam, 'summary', $editoroptions, null, 'course', 'summary', null);
            if ($overviewfilesoptions) {
                file_prepare_standard_filemanager($exam, 'overviewfiles', $overviewfilesoptions, null, 'course', 'overviewfiles', 0);
            }
        }
        $params = array(
            'course' => $exam,
            'editoroptions' => $editoroptions,
            'returnto' => $returnto,
            'get_coursedetails' => $get_coursedetails,
            'form_status' => $form_status,
            'costcenterid' => $data->open_path,
            'courseid' => $data['id'],
        );
        // The last param is the ajax submitted data.
        $mform = new custom_onlineexam_form(null, $params, 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        if ($validateddata) {

            //quiz module ends here
            $formheaders = array_keys($mform->formstatus);
            $category_id = $data['category'];
            // print_r($quiz);
            $categorycontext = (new \local_courses\lib\accesslib())::get_module_context($exam->id);
            if (is_siteadmin()) {
                $departmentarr=(array) $data['open_departmentid'];
                $open_departmentid = implode(',', $departmentarr);
            } else {
                $open_departmentid = $data['open_departmentid'];
            }
            $subdepartmentarr=(array) $data['open_departmentid'];
            $open_subdepartment = implode(',', $subdepartmentarr);
            $open_departmentid = is_null($open_departmentid) ? 0  : $open_departmentid;
            $open_subdepartment = is_null($open_subdepartment) ? 0 : $open_subdepartment;
            if ($validateddata->id <= 0) {
                // $validateddata->open_identifiedas = $validateddata->identifiedtype;
                $validateddata->category = $category_id;
                $validateddata->open_departmentid = $open_departmentid;
                $validateddata->open_module = 'online_exams';
                $validateddata->format = 'singleactivity';
                $validateddata->open_subdepartment = $open_subdepartment;
                local_costcenter_get_costcenter_path($validateddata);

                if ($validateddata->open_path) {
                    $validateddata->category = $DB->get_field('local_costcenter', 'category', array('path' => $validateddata->open_path));
                }

                $validateddata->startdate = time();
                $validateddata->enddate = 0;
                $validateddata->open_coursetype = 1;
                if(isset($validateddata->concatshortname) && !empty($validateddata->concatshortname)){
                    $validateddata->shortname = $validateddata->concatshortname.'_'.$validateddata->shortname;
                }
                $examid = create_course($validateddata, $editoroptions);
               // Update course tags.
                if (isset($validateddata->tags)) {
                    $coursecontext = context_course::instance($examid->id, MUST_EXIST);
                    local_tags_tag::set_item_tags('local_courses', 'courses', $examid->id, $coursecontext, $validateddata->tags, 0, $data['open_path'], $validateddata->open_departmentid);
                }

                // if (class_exists('\block_trending_modules\lib')) {
                //     $trendingclass = new \block_trending_modules\lib();
                //     if (method_exists($trendingclass, 'trending_modules_crud')) {
                //         $trendingclass->trending_modules_crud($examid->id, 'local_courses');
                //     }
                // }
                $quiz= (object) $quiz;
                $quiz->course = $examid->id;
                $quiz->grademethod = $validateddata->grademethod;
                $quiz->grade = $validateddata->gradepass;
                $quiz->gradepass = $validateddata->gradepass;
                $quiz->name = $validateddata->fullname;

                if (!empty($validateddata->summary_editor['text']))
                    $quiz->introeditor['text'] = $validateddata->summary_editor['text'];
                else
                    $quiz->introeditor['text'] = $validateddata->fullname;

                $quiz->introeditor['format'] = $validateddata->summary_editor['format'];
                $quiz->completion= 2;
                $quiz->completionusegrade = 1;
                $quiz->completionpassgrade = 1;
                // $quiz->grade = $validateddata->maxgrade;

                $quiz = add_onlineexam_quiz($validateddata, $examid);
                add_moduleinfo($quiz, $examid);
                //$coursedata = $examid;
                // $enrol_status = $validateddata->selfenrol;
                // insert::add_enrol_method_tocourse($coursedata,$enrol_status);

            } elseif ($validateddata->id > 0) {
                $open_path = $DB->get_field('course', 'open_path', array('id' => $validateddata->id));
                list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $open_path);
                // $validateddata->open_identifiedas = $validateddata->identifiedtype;
                $validateddata->open_coursetype = 1;
                $coursedata = $DB->get_record('course', array('id' => $data['id']));
                if ($form_status == 0) {
                    $examid = (object) $examid;
                    $examid->id = $data['id'];
                    $validateddata->category = $category_id;


                    if ($validateddata->open_costcenterid != $org) {

                        local_costcenter_get_costcenter_path($validateddata);

                        if ($validateddata->open_path) {
                            $validateddata->category = $DB->get_field('local_costcenter', 'category', array('path' => $validateddata->open_path));
                        }
                    }
                    
                    update_course($validateddata, $editoroptions);

                    // purge appropriate caches in case fix_course_sortorder() did not change anything
                    cache_helper::purge_by_event('changesincourse');
                    cache_helper::purge_by_event('changesincoursecat');

                    //update Quizz
                    $quiz = update_onlineexam_quiz($validateddata, $data, $form_status);
                    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $data['id'], false, MUST_EXIST);
                    $quiz->coursemodule = $cm->id;
                    // $cm->grade = $validateddata->gradepass;
                    // $cm->gradepass = $validateddata->gradepass;
                    // $cm->grademethod = $validateddata->grademethod;
                    // $cm->attempts = $validateddata->grademethod;
                    // $cm->completion = 2;
                    // $cm->completionusegrade = 1;
                    // $cm->completionpassgrade = 1;
                    // print_r($quiz);
                    $updated = update_moduleinfo($cm, $quiz, $coursedata, null);
                    // print_r($updated);
                    //  insert::add_enrol_method_tocourse($coursedata, $coursedata->selfenrol);
                } else {

                    $data = (object)$data;
                    $examid = new stdClass();
                    $examid->id = $data->id;

                    update_course($data);
                    if ($form_status == 1) {
                        $quiz = update_onlineexam_quiz($validateddata, $data, $form_status);
                        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $data->id, false, MUST_EXIST);
                        $quiz->coursemodule = $cm->id;
                        // print_r($cm);
                        $updated = update_moduleinfo($cm, $quiz, $coursedata, null);
                        // print_r($updated);
                    }
                    if ($form_status == 2) {

                        local_costcenter_get_costcenter_path($data);

                        if ($data->open_path) {
                            $data->category = $DB->get_field('local_costcenter', 'category', array('path' => $data->open_path));
                        }
                    } else {
                        if ($validateddata->map_certificate == 1) {

                            $data->open_certificateid = $validateddata->open_certificateid;
                        } else {

                            $data->open_certificateid = null;
                        }
                    }

                    // purge appropriate caches in case fix_course_sortorder() did not change anything
                    cache_helper::purge_by_event('changesincourse');
                    cache_helper::purge_by_event('changesincoursecat');
                }
            }
            $next = $form_status + 1;
            $nextform = array_key_exists($next, $formheaders);
            if ($nextform !== false) {
                $form_status = $next;
                $error = false;
            } else {
                $form_status = -1;
                $error = true;
            }
            $enrolid = $DB->get_field('enrol', 'id', array('courseid' => $examid->id, 'enrol' => 'manual'));
            $existing_method = $DB->get_record('enrol', array('courseid' => $examid->id, 'enrol' => 'self'));
            $courseenrolid = $DB->get_field('course', 'selfenrol', array('id' => $examid->id));
            if ($courseenrolid == 1) {
                $existing_method->status = 0;
                $existing_method->customint6 = 1;
            } else {
                $existing_method->status = 1;
            }
            $DB->update_record('enrol', $existing_method);
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in submission');
        }
        $return = array(
            'courseid' => $examid->id,
            'enrolid' => $enrolid,
            'form_status' => $form_status
        );

        return $return;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_create_onlineexams_form_returns()
    {
        return new external_single_structure(array(
            'courseid' => new external_value(PARAM_INT, 'Exam id'),
            'enrolid' => new external_value(PARAM_INT, 'manual enrol id for the course'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }

    /** Describes the parameters for delete_course webservice.
     * @return external_function_parameters
     */
    public static function onlineexams_view_parameters()
    {
        return new external_function_parameters([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'offset' => new external_value(
                PARAM_INT,
                'Number of items to skip from the begging of the result set',
                VALUE_DEFAULT,
                0
            ),
            'limit' => new external_value(
                PARAM_INT,
                'Maximum number of results to return',
                VALUE_DEFAULT,
                0
            ),
            'contextid' => new external_value(PARAM_INT, 'contextid'),
            'filterdata' => new external_value(PARAM_RAW, 'filters applied'),
        ]);
    }

    /**
     * lists all courses
     *
     * @param array $options
     * @param array $dataoptions
     * @param int $offset
     * @param int $limit
     * @param int $contextid
     * @param array $filterdata
     * @return array courses list.
     */
    public static function onlineexams_view($options, $dataoptions, $offset = 0, $limit = 0, $contextid, $filterdata)
    {
        global $DB, $PAGE;
        require_login();
        $PAGE->set_url('/local/courses/courses.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::onlineexams_view_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        $offset = $params['offset'];
        $limit = $params['limit'];
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
        $data = get_listof_onlineexams($stable, $filtervalues,$options);
        $totalcount = $data['totalcourses'];
        return [
            'totalcount' => $totalcount,
            'length' => $totalcount,
            'filterdata' => $filterdata,
            'records' => $data,
            'options' => $options,
            'dataoptions' => $dataoptions,
        ];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */

    public static function onlineexams_view_returns()
    {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of challenges in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'total number of challenges in result set'),
            'length' => new external_value(PARAM_RAW, 'total number of challenges in result set'),
            'records' => new external_single_structure(
                array(
                    'hascourses' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'coursename' => new external_value(PARAM_RAW, 'coursename'),
                                'coursenameCut' => new external_value(PARAM_RAW, 'coursenameCut', VALUE_OPTIONAL),
                                'catname' => new external_value(PARAM_RAW, 'catname'),
                                'shortname' => new external_value(PARAM_RAW, 'shortname'),
                                'catnamestring' => new external_value(PARAM_RAW, 'catnamestring'),
                                'courseimage' => new external_value(PARAM_RAW, 'catnamestring'),
                                'enrolled_count' => new external_value(PARAM_INT, 'enrolled_count', VALUE_OPTIONAL),
                                'courseid' => new external_value(PARAM_INT, 'courseid'),
                                'completed_count' => new external_value(PARAM_INT, 'completed_count', VALUE_OPTIONAL),
                                'points' => new external_value(PARAM_INT, 'points', VALUE_OPTIONAL),
                                'coursetype' => new external_value(PARAM_RAW, 'coursetype', VALUE_OPTIONAL),
                                'onlineexamsummary' => new external_value(PARAM_RAW, 'onlineexamsummary', VALUE_OPTIONAL),
                                'courseurl' => new external_value(PARAM_RAW, 'courseurl', VALUE_OPTIONAL),
                                'enrollusers' => new external_value(PARAM_RAW, 'enrollusers', VALUE_OPTIONAL),
                                'editcourse' => new external_value(PARAM_RAW, 'editcourse', VALUE_OPTIONAL),
                                'update_status' => new external_value(PARAM_RAW, 'update_status', VALUE_OPTIONAL),
                                'course_class' => new external_value(PARAM_TEXT, 'course_status', VALUE_OPTIONAL),
                                'deleteaction' => new external_value(PARAM_RAW, 'designation', VALUE_OPTIONAL),
                                'grader' => new external_value(PARAM_RAW, 'grader', VALUE_OPTIONAL),
                                'activity' => new external_value(PARAM_RAW, 'activity', VALUE_OPTIONAL),
                                'requestlink' => new external_value(PARAM_RAW, 'requestlink', VALUE_OPTIONAL),
                                'skillname' => new external_value(PARAM_RAW, 'skillname', VALUE_OPTIONAL),
                                'attemptreport' => new external_value(PARAM_RAW, 'attemptreport', VALUE_OPTIONAL),
                                'analyticsreport' => new external_value(PARAM_RAW, 'analyticsreport', VALUE_OPTIONAL),
                                'importquestions' => new external_value(PARAM_RAW, 'importquestions', VALUE_OPTIONAL),
                                'ratings_value' => new external_value(PARAM_RAW, 'ratings_value', VALUE_OPTIONAL),
                                'ratingenable' => new external_value(PARAM_BOOL, 'ratingenable', VALUE_OPTIONAL),
                                'tagstring' => new external_value(PARAM_RAW, 'tagstring', VALUE_OPTIONAL),
                                'tagenable' => new external_value(PARAM_BOOL, 'tagenable', VALUE_OPTIONAL),
                                'report_view' => new external_value(PARAM_BOOL, 'report_view', VALUE_OPTIONAL),
                                'grade_view' => new external_value(PARAM_BOOL, 'grade_view', VALUE_OPTIONAL),
                                'delete' => new external_value(PARAM_BOOL, 'delete', VALUE_OPTIONAL),
                                'update' => new external_value(PARAM_BOOL, 'update', VALUE_OPTIONAL),
                                'enrol' => new external_value(PARAM_BOOL, 'enrol', VALUE_OPTIONAL),
                                'actions' => new external_value(PARAM_BOOL, 'actions', VALUE_OPTIONAL),
                                'examfromdate' => new external_value(PARAM_RAW, 'examfromdate', VALUE_OPTIONAL),
                                'examtodate' => new external_value(PARAM_RAW, 'examtodate', VALUE_OPTIONAL),
                                'passgrade' => new external_value(PARAM_RAW, 'passgrade', VALUE_OPTIONAL),
                                'maxgrade' => new external_value(PARAM_RAW, 'maxgrade', VALUE_OPTIONAL),
                                'fullonlineexamsummary' => new external_value(PARAM_RAW, 'fullonlineexamsummary', VALUE_OPTIONAL),                                

                            )
                        )
                    ),
                    //  'request_view' => new external_value(PARAM_INT, 'request_view', VALUE_OPTIONAL),

                    'nocourses' => new external_value(PARAM_BOOL, 'nocourses', VALUE_OPTIONAL),
                    'totalcourses' => new external_value(PARAM_INT, 'totalcourses', VALUE_OPTIONAL),
                    'length' => new external_value(PARAM_INT, 'length', VALUE_OPTIONAL),
                )
            )

        ]);
    }
    /** Describes the parameters for delete_course webservice.
     * @return external_function_parameters
     */
    public static function delete_onlineexams_parameters()
    {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
                'name' => new external_value(PARAM_RAW, 'name', false),
            )
        );
    }

    /**
     * Deletes course
     *
     * @param int $action
     * @param int $confirm
     * @param int $id course id
     * @param string $name
     * @return int new course id.
     */
    public static function delete_onlineexams($action, $id, $confirm, $name)
    {
        global $DB;
        try {
            if ($confirm) {
                $corcat = $DB->get_field('course', 'category', array('id' => $id));
                $category = $DB->get_record('course_categories', array('id' => $corcat));
                delete_course($id, false);
                // if (class_exists('\block_trending_modules\lib')) {
                //     $trendingclass = new \block_trending_modules\lib();
                //     if (method_exists($trendingclass, 'trending_modules_crud')) {
                //         $course_object = new stdClass();
                //         $course_object->id = $id;
                //         $course_object->module_type = 'local_courses';
                //         $course_object->delete_record = True;
                //         $trendingclass->trending_modules_crud($course_object, 'local_courses');
                //     }
                // }
                $category->coursecount = $category->coursecount - 1;
                $DB->update_record('course_categories', $category);
                $return = true;
            } else {
                $return = false;
            }
        } catch (dml_exception $ex) {
            print_error('deleteerror', 'local_classroom');
            $return = false;
        }
        return $return;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */

    public static function delete_onlineexams_returns()
    {
        return new external_value(PARAM_BOOL, 'return');
    }

    /* Describes the parameters for global_filters_form_option_selector webservice.
  * @return external_function_parameters
  */
    // public static function global_filters_form_option_selector_parameters()
    // {
    //     $query = new external_value(
    //         PARAM_RAW,
    //         'Query string'
    //     );
    //     $action = new external_value(
    //         PARAM_RAW,
    //         'Action for the classroom form selector'
    //     );
    //     $options = new external_value(
    //         PARAM_RAW,
    //         'Action for the classroom form selector'
    //     );
    //     $searchanywhere = new external_value(
    //         PARAM_BOOL,
    //         'find a match anywhere, or only at the beginning'
    //     );
    //     $page = new external_value(
    //         PARAM_INT,
    //         'Page number'
    //     );
    //     $perpage = new external_value(
    //         PARAM_INT,
    //         'Number per page'
    //     );
    //     return new external_function_parameters(array(
    //         'query' => $query,
    //         'action' => $action,
    //         'options' => $options,
    //         'searchanywhere' => $searchanywhere,
    //         'page' => $page,
    //         'perpage' => $perpage,
    //     ));
    // }
    public static function course_update_status_parameters()
    {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for survey'),
                'id' => new external_value(PARAM_INT, 'The survey id for wellness'),
                'params' => new external_value(PARAM_RAW, 'optional parameter for default application'),
            )
        );
    }
    public static function course_update_status($contextid, $id, $params)
    {
        global $DB;
        $params = self::validate_parameters(
            self::course_update_status_parameters(),
            ['contextid' => $contextid, 'id' => $id, 'params' => $params]
        );
        $context = (new \local_courses\lib\accesslib())::get_module_context($id);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $course = $DB->get_record('course', array('id' => $id), 'id, visible');
        $course->visible = $course->visible ? 0 : 1;
        $course->timemodified = time();
        $return = $DB->update_record('course', $course);

		$costcenterid = $DB->get_field('course','open_path',array('id' => $id));
        // if(class_exists('\block_trending_modules\lib')){
        //     $dataobject = new stdClass();
        //     $dataobject->update_status = True;
        //     $dataobject->id = $id;
        //     $dataobject->module_type = 'local_courses';
        //     $dataobject->module_visible = $course->visible;
		// 	$dataobject->costcenterid = $costcenterid;
        //     $class = (new \block_trending_modules\lib())->trending_modules_crud($dataobject, 'local_courses');
        // }

        return $return;
    }
    public static function course_update_status_returns()
    {
        return new external_value(PARAM_BOOL, 'Status');
    }
    public static function data_for_onlineexams_parameters()
    {
        $filter = new external_value(PARAM_TEXT, 'Filter text');
        $filter_text = new external_value(PARAM_TEXT, 'Filter name', VALUE_OPTIONAL);
        $filter_offset = new external_value(PARAM_INT, 'Offset value', VALUE_OPTIONAL);
        $filter_limit = new external_value(PARAM_INT, 'Limit value', VALUE_OPTIONAL);
        $params = array(
            'filter' => $filter,
            'filter_text' => $filter_text,
            'filter_offset' => $filter_offset,
            'filter_limit' => $filter_limit
        );
        return new external_function_parameters($params);
    }
    public static function data_for_onlineexams($filter, $filter_text = '', $filter_offset = 0, $filter_limit = 0)
    {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_onlineexams_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
            'filter_offset' => $filter_offset,
            'filter_limit' => $filter_limit
        ));
        $PAGE->set_context((new \local_onlineexams\lib\accesslib())::get_module_context());
        $renderable = new local_onlineexams\output\userdashboard($params['filter'], $params['filter_text'], $params['filter_offset'], $params['filter_limit']);
        $output = $PAGE->get_renderer('local_onlineexams');
        $data = $renderable->export_for_template($output);
        // print_r($data);
        return $data;
    }
    public static function data_for_onlineexams_returns()
    {
        $return  = new external_single_structure(array(
            'total' => new external_value(PARAM_INT, 'Number of enrolled onlineexams.', VALUE_OPTIONAL),
            'inprogresscount' =>  new external_value(PARAM_INT, 'Number of inprogress course count.'),
            'completedcount' =>  new external_value(PARAM_INT, 'Number of complete course count.'),
            'onlineexams_view_count' =>  new external_value(PARAM_INT, 'Number of onlineexams count.'),
            'enableslider' =>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available' =>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view' =>  new external_value(PARAM_TEXT, 'to add course count class'),
            'functionname' => new external_value(PARAM_TEXT, 'Function name'),
            'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
            'elearningtemplate' => new external_value(PARAM_INT, 'template name', VALUE_OPTIONAL),
            'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
            'enableflow' => new external_value(PARAM_BOOL, "flag for flow enabling", VALUE_DEFAULT, true),
            'moduledetails' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        // 'inprogress_coursename' => new external_value(PARAM_RAW, 'Course name'),
                        'lastaccessdate' => new external_value(PARAM_RAW, 'Last access Time'),
                        'course_image_url' => new external_value(PARAM_RAW, 'Course Image'),
                        'onlineexamsummary' => new external_value(PARAM_RAW, 'Course Summary'),
                        'fullonlineexamsummary' => new external_value(PARAM_RAW, 'full onlineexams Summary', VALUE_OPTIONAL),
                        'progress' => new external_value(PARAM_RAW, 'Course Progress'),
                        'progress_bar_width' => new external_value(PARAM_RAW, 'Course Progress bar width'),
                        'course_fullname' => new external_value(PARAM_RAW, 'Course Fullname'),
                        'course_fullname' => new external_value(PARAM_RAW, 'Course Fullname'),
                        'course_url' => new external_value(PARAM_RAW, 'Course Url'),
                        'inprogress_coursename_fullname' => new external_value(PARAM_RAW, 'Course Url'),
                        'rating_element' => new external_value(PARAM_RAW, 'Ratings'),
                        'element_tags' => new external_value(PARAM_RAW, 'Course Tags', VALUE_OPTIONAL),
                        // 'indexClass' => new external_value(PARAM_TEXT, 'Index Card Class'),
                        'index' => new external_value(PARAM_INT, 'Index of Card'),
                        'course_completedon' => new external_value(PARAM_RAW, 'course_completedon'),
                        'label_name' => new external_value(PARAM_RAW, 'course_completedon'),
                    )
                )
            ),
            'viewMoreCard' => new external_value(PARAM_BOOL, 'More info card to display', false),
            'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
            'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
            'index' => new external_value(PARAM_INT, 'number of onlineexams count'),
            'filter_text' => new external_value(PARAM_TEXT, 'filtertext content', VALUE_OPTIONAL),
            'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
            'templatename' => new external_value(PARAM_TEXT, 'Templatename for tab content'),
            'pluginname' => new external_value(PARAM_TEXT, 'Pluginname for tab content', VALUE_DEFAULT, 'local_onlineexams'),
            'tabname' => new external_value(PARAM_TEXT, 'Pluginname for tab content', VALUE_DEFAULT, 'local_onlineexams'),
            'status' => new external_value(PARAM_TEXT, 'Pluginname for tab content', VALUE_DEFAULT, 'local_onlineexams'),
            'enrolled_url' => new external_value(PARAM_URL, 'view_more_url for tab'), //added revathi
            'inprogress_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
            'completed_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
        ));
        return $return;
    }
    public static function data_for_onlineexams_paginated_parameters()
    {
        return new external_function_parameters([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'offset' => new external_value(
                PARAM_INT,
                'Number of items to skip from the begging of the result set',
                VALUE_DEFAULT,
                0
            ),
            'limit' => new external_value(
                PARAM_INT,
                'Maximum number of results to return',
                VALUE_DEFAULT,
                0
            ),
            'contextid' => new external_value(PARAM_INT, 'contextid'),
            'filterdata' => new external_value(PARAM_RAW, 'filters applied'),
        ]);
    }
    public static function data_for_onlineexams_paginated($options, $dataoptions, $offset = 0, $limit = 0, $contextid, $filterdata)
    {
        global $DB, $PAGE;
        require_login();
        $PAGE->set_url('/local/onlineexams/userdashboard.php', array());
        $PAGE->set_context($contextid);

        $decodedoptions = (array)json_decode($options);
        $decodedfilter = (array)json_decode($filterdata);
        $filter = $decodedoptions['filter'];
        $filter_text = isset($decodedfilter['search_query']) ? $decodedfilter['search_query'] : '';
        $filter_offset = $offset;
        $filter_limit = $limit;

        $renderable = new local_onlineexams\output\userdashboard($filter, $filter_text, $filter_offset, $filter_limit);
        $output = $PAGE->get_renderer('local_onlineexams');
        $data = $renderable->export_for_template($output);
        $totalcount = $renderable->onlineexamsViewCount;
        return [
            'totalcount' => $totalcount,
            'length' => $totalcount,
            'filterdata' => $filterdata,
            'records' => array($data),
            'options' => $options,
            'dataoptions' => $dataoptions,
        ];
    }
    public static function data_for_onlineexams_paginated_returns()
    {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of challenges in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'total' => new external_value(PARAM_INT, 'Number of enrolled onlineexams.', VALUE_OPTIONAL),
                        'inprogresscount' =>  new external_value(PARAM_INT, 'Number of inprogress course count.'),
                        'completedcount' =>  new external_value(PARAM_INT, 'Number of complete course count.'),
                        'onlineexams_view_count' =>  new external_value(PARAM_INT, 'Number of onlineexams count.'),

                        'inprogress_elearning_available' =>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
                        'course_count_view' =>  new external_value(PARAM_TEXT, 'to add course count class'),
                        'functionname' => new external_value(PARAM_TEXT, 'Function name'),
                        'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
                        'elearningtemplate' => new external_value(PARAM_INT, 'template name', VALUE_OPTIONAL),
                        'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
                        'enableflow' => new external_value(PARAM_BOOL, "flag for flow enabling", VALUE_DEFAULT, false),
                        'moduledetails' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    // 'inprogress_coursename' => new external_value(PARAM_RAW, 'Course name'),
                                    'lastaccessdate' => new external_value(PARAM_RAW, 'Last access Time'),
                                    'course_image_url' => new external_value(PARAM_RAW, 'Course Image'),
                                    'onlineexamsummary' => new external_value(PARAM_RAW, 'Course Summary'),
                                    'fullonlineexamsummary' => new external_value(PARAM_RAW, 'full onlineexams Summary', VALUE_OPTIONAL),
                                    'progress' => new external_value(PARAM_RAW, 'Course Progress'),
                                    'progress_bar_width' => new external_value(PARAM_RAW, 'Course Progress bar width'),
                                    'course_fullname' => new external_value(PARAM_RAW, 'Course Fullname'),
                                    'course_fullname' => new external_value(PARAM_RAW, 'Course Fullname'),
                                    'course_url' => new external_value(PARAM_RAW, 'Course Url'),
                                    'inprogress_coursename_fullname' => new external_value(PARAM_RAW, 'Course Url'),
                                    'rating_element' => new external_value(PARAM_RAW, 'Ratings'),
                                    'element_tags' => new external_value(PARAM_RAW, 'Course Tags', VALUE_OPTIONAL),
                                    // 'indexClass' => new external_value(PARAM_TEXT, 'Index Card Class'),
                                    'index' => new external_value(PARAM_INT, 'Index of Card'),
                                    'course_completedon' => new external_value(PARAM_RAW, 'course_completedon'),
                                    'label_name' => new external_value(PARAM_RAW, 'course_completedon'),
                                )
                            )
                        ),
                        // 'viewMoreCard' => new external_value(PARAM_BOOL, 'More info card to display', false),
                        'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
                        'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
                        'index' => new external_value(PARAM_INT, 'number of onlineexams count'),
                        'filter_text' => new external_value(PARAM_TEXT, 'filtertext content', VALUE_OPTIONAL),
                        'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
                        'templatename' => new external_value(PARAM_TEXT, 'Templatename for tab content'),
                        'pluginname' => new external_value(PARAM_TEXT, 'Pluginname for tab content', VALUE_DEFAULT, 'local_onlineexams'),
                        'tabname' => new external_value(PARAM_TEXT, 'Pluginname for tab content', VALUE_DEFAULT, 'local_onlineexams'),
                        'status' => new external_value(PARAM_TEXT, 'Pluginname for tab content', VALUE_DEFAULT, 'local_onlineexams'),
                    )
                )
            )
        ]);
    }
}
