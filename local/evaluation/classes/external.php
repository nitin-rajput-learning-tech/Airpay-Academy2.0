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
 * Feedback external API
 *
 * @package    local_evaluation
 * @category   external
 * @author     eabyas 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot.'/local/evaluation/lib.php');

use local_evaluation\external\evaluation_summary_exporter;
use local_evaluation\external\evaluation_completedtmp_exporter;
use local_evaluation\external\evaluation_item_exporter;
use local_evaluation\external\evaluation_valuetmp_exporter;
use local_evaluation\external\evaluation_value_exporter;
use local_evaluation\external\evaluation_completed_exporter;

/**
 * Feedback external functions
 *
 * @package    local_evaluation
 * @category   external
 * @copyright  eabyas 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class local_evaluation_external extends external_api {


    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_create_evaluation_form_parameters() {
        return new external_function_parameters(
            array(
                //'evalid' => new external_value(PARAM_INT, 'The evaluation id '),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array')
            )
        );
    }

    /**
     * Submit the create group form.
     *
     * @param int $contextid The context id for the course.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @return int new group id.
     */
    public static function submit_create_evaluation_form($contextid, $jsonformdata) {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/local/evaluation/evaluation_form.php');
        require_once($CFG->dirroot . '/local/evaluation/lib.php');

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_create_evaluation_form_parameters(),
                                            ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);

        $data = array();
        parse_str($serialiseddata, $data);

        $warnings = array();
        if ($data['instance'] == 0) {
            $instance = 0;
            $evaluationtype = 0;
            $plugin = 'site';
        } else {
            $instance = $data['instance'];
            $evaluationtype = $data['evaluationtype'];
            $plugin = $data['plugin'];
        }
        // The last param is the ajax submitted data.
        $params = array('id' => $data['id'], 'instance'=>$instance, 'plugin'=>$plugin);
        $mform = new evaluation_form(null, $params, 'post', '', null, true, $data);

        $validateddata = $mform->get_data();
        if ($validateddata) {
            if($validateddata->timeclose){
                $validateddata->timeclose = $validateddata->timeclose;
            }
            if ($validateddata->id > 0) {
                $evaluationid = evaluation_update_instance($validateddata);
            } else if ($validateddata->id < 0) {
                $validateddata->instance = $instance;
                $validateddata->plugin = $plugin;
                $validateddata->evaluationtype = ($evaluationtype) ? $evaluationtype: 0;
                $evaluationid = evaluation_add_instance($validateddata);
            }
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in submission');
        }

        return $evaluationid;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_create_evaluation_form_returns() {
      return new external_value(PARAM_INT, 'evaluation id');
    }

    /** Describes the parameters for delete_course webservice.
    * @return external_function_parameters
    */
    public static function evaluationview_parameters() {
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

    /**
    * lists all categories
    *
    * @param array $options
    * @param array $dataoptions
    * @param int $offset
    * @param int $limit
    * @param int $contextid
    * @param array $filterdata
    * @return array categories list.
    */
    public static function evaluationview($options, $dataoptions, $offset = 0, $limit = 0, $contextid, $filterdata) {
        global $DB, $PAGE;
        require_login();
        $PAGE->set_url('/local/evaluation/index.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
          self::evaluationview_parameters(),
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
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
         $stable->status = $decodedata->status;
        $records = get_listof_evalautions($stable, $filtervalues);
        $totalcount = $records['totalrecords'];
        $data = $records['records'];
        return [
            'totalcount' => $totalcount,
            'filterdata' => $filterdata,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
        ];
    }

    /**
    * Returns description of method result value
    * @return external_description
    */
    public static function evaluationview_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of challenges in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                    'has_evalcap' => new external_value(PARAM_BOOL, 'has_evalcap'),
                    'evalname' => new external_value(PARAM_RAW, 'evalname'),
                    'evalurl' => new external_value(PARAM_RAW, 'evalurl'),
                    'eval_name' => new external_value(PARAM_RAW, 'eval_name'),
                    'schedule' => new external_value(PARAM_RAW, 'schedule'),
                    'evaltype' => new external_value(PARAM_RAW, 'evaltype'),
                    'enrolled' => new external_value(PARAM_RAW, 'enrolled'),
                    'completed' => new external_value(PARAM_RAW, 'completed'),
                    'not_yetstarted' => new external_value(PARAM_RAW, 'not_yetstarted'),
                    'enrolledon' => new external_value(PARAM_RAW, 'enrolledon'),
                    'completedon' => new external_value(PARAM_RAW, 'completedon'),
                    'completedurl' => new external_value(PARAM_RAW, 'completedurl'),
                    'previewurl' => new external_value(PARAM_RAW, 'previewurl'),
                    'closed_feedback' => new external_value(PARAM_RAW, 'closed_feedback'),
                    'current_feedback' => new external_value(PARAM_RAW, 'current_feedback'),
                    'previewstring' => new external_value(PARAM_RAW, 'previewstring'),
                    'actions' => new external_value(PARAM_RAW, 'actions'),
                    'status' => new external_value(PARAM_BOOL, 'status  of feedback')
                    )
                )
            )
        ]);
    }

    /**
     * Describes the parameters for displayquestion webservice.
     * @return external_function_parameters
     */
    public static function displayquestion_parameters() {
        return new external_function_parameters(
            array(
                'itemid' => new external_value(PARAM_INT, 'item id'),
                'evalid' => new external_value(PARAM_INT, 'evalid'),
                'typ' => new external_value(PARAM_RAW, 'typ')
            )
        );
    }

    /**
     * displayquestion
     *
     * @param int $itemid
     * @param int $evalid
     * @param string $typ
     * @return array
     */
    public static function displayquestion($itemid, $evalid,$typ) {
        global $DB, $CFG,$PAGE;
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $PAGE->set_context($context);
        if ($itemid) {
            $item = $DB->get_record('local_evaluation_item', array('id' => $itemid), '*', MUST_EXIST);
            $typ = $item->typ;
        }
        $evaluation = $DB->get_record('local_evaluations', array('id'=>$evalid));
        $editurl = new moodle_url('/local/evaluation/eval_view.php', array('id' => $evaluation->id));
        // If the typ is pagebreak so the item will be saved directly.
        if ($typ === 'pagebreak') {
            require_sesskey();
            evaluation_create_pagebreak($evaluation->id);
            $link = html_writer::link($editurl, '<button  class="btn btn-primary">'.get_string('continue').'</button>', array('class'=>'pl-15'));
            $return = array(
            'formdata' => "<p>Pagebreak added</p>".$link
        );
        } else {
            if (!$typ || !file_exists($CFG->dirroot.'/local/evaluation/item/'.$typ.'/lib.php')) {
            print_error('typemissing', 'evaluation', $editurl->out(false));
        }
        require_once($CFG->dirroot.'/local/evaluation/item/'.$typ.'/lib.php');
        $itemobj = evaluation_get_item_class($typ);
        $itemobj->build_editform($item, $evaluation);
        $displayfrom = $itemobj->show_editform();
        $return = array(
            'formdata' => $displayfrom
        );
        }

        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function displayquestion_returns() {
        return new external_function_parameters(
            array(
                'formdata' => new external_value(PARAM_RAW, 'formdata ')
            )
        );
    }

    /**
     * Describes the parameters for displayquestion webservice.
     * @return external_function_parameters
     */
    public static function displaytemplate_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'templateid'),
                'templateid' => new external_value(PARAM_INT, 'templateid')
            )
        );
    }

    /**
     * displayquestion
     *
     * @param int $itemid
     * @param int $evalid
     * @param string $typ
     * @return array
     */
    public static function displaytemplate($id, $templateid) {
        global $DB, $CFG,$PAGE;
        require_once($CFG->dirroot.'/local/evaluation/use_templ_form.php');
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $PAGE->set_context($context);
        $evaluation = $DB->get_record('local_evaluations', array('id'=>$id));
        $evaluationstructure = new local_evaluation_structure($evaluation, $templateid);

        require_capability('local/evaluation:edititems', $context);
        $action  = $CFG->wwwroot.'/local/evaluation/use_templ.php';
        $mform = new local_evaluation_use_templ_form($action, array());
        $mform->set_data(array('id' => $id, 'templateid' => $templateid));

        $formdata = $mform->render();
        $form = new local_evaluation_complete_form(local_evaluation_complete_form::MODE_VIEW_TEMPLATE,
                $evaluationstructure, 'evaluation_preview_form', ['templateid' => $templateid,'evalid' => $id]);
        $formdata . '<h4>'.get_string('qsintemplate', 'local_evaluation').'</h4>';
        $formdata .= $form->render();

        $return = array(
            'formdata' => $formdata
        );

        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function displaytemplate_returns() {
        return new external_function_parameters(
            array(
                'formdata' => new external_value(PARAM_RAW, 'formdata ')
            )
        );
    }
    public static function addnew_question_parameters(){
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for gamification'),
                'id' => new external_value(PARAM_INT, 'The badge id for gamification'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
                'params' => new external_value(PARAM_RAW, 'optional parameter for default application'),

            )
        );
    }
    public static function addnew_question($contextid,$id, $jsonformdata, $params){
        global $PAGE, $CFG, $DB, $_SESSION;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::addnew_question_parameters(),
                                    ['contextid' => $contextid,'id' => $id, 'jsonformdata' => $jsonformdata, 'params' => $params]);
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
        $data = array();
        parse_str($serialiseddata, $data);
        $data = (object)$data;
        if($data->typ == 'label' || $data->typ == 'pagebreak'){
            $hasvaue = 0;
        }else if($data->typ == 'captcha'){
            if (empty($CFG->recaptchaprivatekey) OR empty($CFG->recaptchapublickey)) {
            $hasvaue = 0;
            }
            $hasvaue = 1;
        }else{
            $hasvaue = 1;
        }
        $data->hasvalue = $hasvaue;
            // print_object($data);
        if(file_exists($CFG->dirroot.'/local/evaluation/item/'.$data->typ.'/'.$data->typ.'_form.php')){
            require_once($CFG->dirroot.'/local/evaluation/item/'.$data->typ.'/'.$data->typ.'_form.php');
            require_once($CFG->dirroot.'/local/evaluation/item/'.$data->typ.'/lib.php');
            $eval_item_obj = evaluation_get_item_class($data->typ);
            $classname = 'evaluation_'.$data->typ.'_form';
            $thisform = new $classname('', array(), 'post', '', null, true, (array)$data);
            $position = $data->position;
            $data = $thisform->get_data();
            if(method_exists($eval_item_obj, 'set_ignoreempty')){
                $eval_item_obj->set_ignoreempty($data, $data->ignoreempty);
            }
            if(method_exists($eval_item_obj, 'set_hidenoselect')){
                $eval_item_obj->set_hidenoselect($data, $data->hidenoselect);
            }
            if($position)
                $data->position = $position;
            if($data->typ == 'label' && !empty($data->presentation_editor['text']))
               $data->presentation = $data->presentation_editor['text'];
        }
        if (isset($data->clone_item) AND $data->clone_item) {
            $data->id = ''; //to clone this item
            $data->position++;
        }

        if (!$data->id) {
            $data->id = $DB->insert_record('local_evaluation_item', $data);
        } else {
            $DB->update_record('local_evaluation_item', $data);
        }
        return $data->id;

    }
    public static function addnew_question_returns(){
        return new external_value(PARAM_INT, 'question id');
    }
    public static function evaluation_update_status_parameters(){
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for survey'),
                'id' => new external_value(PARAM_INT, 'The survey id for wellness'),
                'params' => new external_value(PARAM_RAW, 'optional parameter for default application'),
            )
        );
    }
    public static function evaluation_update_status($contextid, $id, $params){
        global $PAGE, $CFG, $DB, $USER;
        $params = self::validate_parameters(self::evaluation_update_status_parameters(),
                                    ['contextid' => $contextid,'id' => $id, 'params' => $params]);
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $evaluation = $DB->get_record('local_evaluations', array('id' => $id));
        $evaluation->visible = $evaluation->visible ? 0 : 1;
        $evaluation->usermodified = $USER->id;
        $evaluation->timemodified = time();
        $return = $DB->update_record('local_evaluations', $evaluation);
        return $return;
    }
    public static function evaluation_update_status_returns(){
        return new external_value(PARAM_BOOL, 'Status');
    }

    public static function evaluations_by_status_parameters() {
        return new external_function_parameters(
            array(
                'status' => new external_value(PARAM_RAW, 'Status'),
                'plugin' => new external_value(PARAM_RAW, 'plugin', VALUE_OPTIONAL, 'site'),
                'search' => new external_value(PARAM_RAW, 'Search', VALUE_OPTIONAL, ''),
                'page' => new external_value(PARAM_INT, 'Page', VALUE_OPTIONAL, 0),
                'perpage' => new external_value(PARAM_INT, 'Per Page', VALUE_OPTIONAL, 15),
            )
        );
    }

    public static function evaluations_by_status($status, $plugin = 'site', $search = '', $page = 0, $perpage = 15) {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::evaluations_by_status_parameters(),
                                    ['status' => $status, 'plugin' => $plugin, 'search' => $search,
                                        'page' => $page, 'perpage' => $perpage]);
        //validate parameter
        list($evaluations, $count) = \local_evaluation\evaluation::evaluations_by_status($status, true, $plugin, $search, $page, $perpage);
        return array('evaluations' => $evaluations, 'total' => $count);
    }

    public static function evaluations_by_status_returns() {
        return new external_single_structure(
            array(
                'evaluations' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Learning Path id'),
                            'name' => new external_value(PARAM_TEXT, 'Learning Path name'),
                            'type' => new external_value(PARAM_INT, 'type'),
                            'intro' => new external_value(PARAM_RAW, 'Description'),
                            'joinedate' => new external_value(PARAM_INT, 'Optional Courses Count'),
                            'timeopen' => new external_value(PARAM_INT, 'Mandatory'),
                            'timeclose' => new external_value(PARAM_INT, 'Optional Courses Count'),
                            'plugin' => new external_value(PARAM_RAW, 'Plugin'),
                            'completedon' => new external_value(PARAM_INT, 'Completed on')
                        )
                    )
                ),
                'total' => new external_value(PARAM_INT, 'Total')
            )
        );
    }

    /**
     * Describes the parameters for get_feedbacks_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_evaluations_parameters() {
        return new external_function_parameters (
            array(
                'id' => new external_value(PARAM_INT, 'Evaluation id'),
                'plugin' => new external_value(PARAM_RAW, 'Plugin', VALUE_OPTIONAL, 'site'),
                'instance' => new external_value(PARAM_BOOL, 'instance', VALUE_OPTIONAL, false)
            )
        );
    }

    /**
     * Returns a list of feedbacks in a provided list of courses.
     * If no list is provided all feedbacks that the user can view will be returned.
     *
     * @param array $courseids course ids
     * @return array of warnings and feedbacks
     * @since Moodle 3.3
     */
    public static function get_evaluations($id, $plugin = 'site', $instance = false) {
        global $PAGE;

        $warnings = array();
        $returnedfeedbacks = array();

        $params = array(
            'id' => $id,
            'plugin' => $plugin,
            'instance' => $instance
        );

        $params = self::validate_parameters(self::get_evaluations_parameters(), $params);

        $evaluations = array();
        // Ensure there are courseids to loop through.
        if (!empty($params['id'])) {
            list($evaluations, $count) = (new local_evaluation\evaluation)::evaluations_by_status('enrolled', true, $plugin, '', 0, 0, $id, $instance);
        }

        $result = array(
            'evaluations' => $evaluations,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_feedbacks_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_evaluations_returns() {
        return new external_single_structure(
            array(
                'evaluations' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Evaluation id'),
                            'name' => new external_value(PARAM_TEXT, 'Evaluation name'),
                            'type' => new external_value(PARAM_INT, 'type'),
                            'intro' => new external_value(PARAM_RAW, 'Description'),
                            'joinedate' => new external_value(PARAM_INT, 'Joined Date'),
                            'timeopen' => new external_value(PARAM_INT, 'Time Open'),
                            'timeclose' => new external_value(PARAM_INT, 'Time Close'),
                            'plugin' => new external_value(PARAM_RAW, 'Plugin'),
                            'anonymous' => new external_value(PARAM_INT, 'Whether the feedback is anonymous.'),
                            'autonumbering' => new external_value(PARAM_BOOL, 'Whether the feedback is anonymous.', VALUE_OPTIONAL, 1),
                            'completionsubmit' => new external_value(PARAM_BOOL, 'If this field is set to 1, then the activity will be automatically marked as complete on submission.', VALUE_OPTIONAL, 0),
                            'multiple_submit' => new external_value(PARAM_BOOL, 'If this field is set to 1, then the activity will be automatically marked as complete on submission.', VALUE_OPTIONAL, 1),
                            'page_after_submit' => new external_value(PARAM_RAW, 'Text to display after submission.', VALUE_OPTIONAL),
                            'publish_stats' => new external_value(PARAM_BOOL, 'Whether stats should be published.', VALUE_OPTIONAL, 0),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_evaluation_access_information.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_evaluation_access_information_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id.'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the feedback (for site feedbacks only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return access information for a given feedback.
     *
     * @param int $evaluationid feedback instance id
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and the access information
     * @since Moodle 3.3
     * @throws  moodle_exception
     */
    public static function get_evaluation_access_information($evaluationid, $courseid = 0) {
        global $PAGE;

        $params = array(
            'evaluationid' => $evaluationid,
            'courseid' => $courseid,
        );
        $params = self::validate_parameters(self::get_evaluation_access_information_parameters(), $params);

        $evaluation = self::validate_evaluation($params['evaluationid'],
            $params['courseid']);
        $evaluationcompletion = new local_evaluation_completion($evaluation);
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $result = array();
        // Capabilities first.
        $result['canviewanalysis'] = $evaluationcompletion->can_view_analysis();
        $result['cancomplete'] = $evaluationcompletion->can_complete();
        $result['cansubmit'] = $evaluationcompletion->can_submit();
        $result['candeletesubmissions'] = has_capability('local/evaluation:deletesubmissions', $context);
        $result['canviewreports'] = has_capability('local/evaluation:viewreports', $context);
        $result['canedititems'] = has_capability('local/evaluation:edititems', $context);

        // Status information.
        $result['isempty'] = $evaluationcompletion->is_empty();
        $result['isopen'] = $evaluationcompletion->is_open();
        $anycourse = ($course->id == SITEID);
        $result['isalreadysubmitted'] = $evaluationcompletion->is_already_submitted($anycourse);
        $result['isanonymous'] = $evaluationcompletion->is_anonymous();

        $result['warnings'] = [];
        return $result;
    }
    /**
     * Utility function for validating a feedback.
     *
     * @param int $evaluationid feedback instance id
     * @param int $courseid courseid course where user completes the feedback (for site feedbacks only)
     * @return array containing the feedback, feedback course, context, course module and the course where is being completed.
     * @throws moodle_exception
     * @since  Moodle 3.3
     */
    protected static function validate_evaluation($evaluationid, $courseid = 0) {
        global $DB, $USER;

        // Request and permission validation.
        $evaluation = $DB->get_record('local_evaluations', array('id' => $evaluationid), '*', MUST_EXIST);

        return $evaluation;
    }
    /**
     * Describes the get_evaluation_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_evaluation_access_information_returns() {
        return new external_single_structure(
            array(
                'canviewanalysis' => new external_value(PARAM_BOOL, 'Whether the user can view the analysis or not.'),
                'cancomplete' => new external_value(PARAM_BOOL, 'Whether the user can complete the feedback or not.'),
                'cansubmit' => new external_value(PARAM_BOOL, 'Whether the user can submit the feedback or not.'),
                'candeletesubmissions' => new external_value(PARAM_BOOL, 'Whether the user can delete submissions or not.'),
                'canviewreports' => new external_value(PARAM_BOOL, 'Whether the user can view the feedback reports or not.'),
                'canedititems' => new external_value(PARAM_BOOL, 'Whether the user can edit feedback items or not.'),
                'isempty' => new external_value(PARAM_BOOL, 'Whether the feedback has questions or not.'),
                'isopen' => new external_value(PARAM_BOOL, 'Whether the feedback has active access time restrictions or not.'),
                'isalreadysubmitted' => new external_value(PARAM_BOOL, 'Whether the feedback is already submitted or not.'),
                'isanonymous' => new external_value(PARAM_BOOL, 'Whether the feedback is anonymous or not.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_feedback.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function view_evaluation_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id'),
                'moduleviewed' => new external_value(PARAM_BOOL, 'If we need to mark the module as viewed for completion',
                    VALUE_DEFAULT, false),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the feedback (for site feedbacks only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $evaluationid feedback instance id
     * @param bool $moduleviewed If we need to mark the module as viewed for completion
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function view_evaluation($evaluationid, $moduleviewed = false, $courseid = 0) {

        $params = array('evaluationid' => $evaluationid, 'moduleviewed' => $moduleviewed, 'courseid' => $courseid);
        $params = self::validate_parameters(self::view_evaluation_parameters(), $params);
        $warnings = array();

        $evaluation = self::validate_evaluation($params['evaluationid'],
            $params['courseid']);
        $evaluationcompletion = new local_evaluation_completion($evaluation);

        // Trigger module viewed event.
        // $evaluationcompletion->trigger_module_viewed();
        if ($params['moduleviewed']) {
            if (!$evaluationcompletion->is_open()) {
                throw new moodle_exception('evaluation_is_not_open', 'feedback');
            }
            // Mark activity viewed for completion-tracking.
            // $evaluationcompletion->set_module_viewed();
        }

        $result = array(
            'status' => true,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the view_feedback return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function view_evaluation_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for launch_feedback.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function launch_evaluation_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id')
            )
        );
    }

    /**
     * Starts or continues a feedback submission
     *
     * @param array $evaluationid feedback instance id
     * @param int $courseid course where user completes a feedback (for site feedbacks only).
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function launch_evaluation($evaluationid) {
        global $PAGE;

        $params = array('evaluationid' => $evaluationid);
        $params = self::validate_parameters(self::launch_evaluation_parameters(), $params);
        $warnings = array();

        $evaluation = self::validate_evaluation($params['evaluationid']);
        // Check we can do a new submission (or continue an existing).
        $evaluationcompletion = self::validate_evaluation_access($evaluation);

        $gopage = $evaluationcompletion->get_resume_page();
        if ($gopage === null) {
            $gopage = -1; // Last page.
        }

        $result = array(
            'gopage' => $gopage,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the launch_feedback return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function launch_evaluation_returns() {
        return new external_single_structure(
            array(
                'gopage' => new external_value(PARAM_INT, 'The next page to go (-1 if we were already in the last page). 0 for first page.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_current_completed_tmp.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_current_completed_tmp_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id')
            )
        );
    }

    /**
     * Returns the temporary completion record for the current user.
     *
     * @param int $feedbackid feedback instance id
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_current_completed_tmp($evaluationid) {
        global $PAGE;

        $params = array('evaluationid' => $evaluationid);
        $params = self::validate_parameters(self::get_current_completed_tmp_parameters(), $params);
        $warnings = array();

        $feedback = self::validate_evaluation($params['evaluationid']);
        $feedbackcompletion = new local_evaluation_completion($feedback);

        if ($completed = $feedbackcompletion->get_current_completed_tmp()) {
            $exporter = new evaluation_completedtmp_exporter($completed);
            return array(
                'feedback' => $exporter->export($PAGE->get_renderer('core')),
                'warnings' => $warnings,
            );
        }
        throw new moodle_exception('not_started', 'feedback');
    }

    /**
     * Describes the get_current_completed_tmp return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_current_completed_tmp_returns() {
        return new external_single_structure(
            array(
                'feedback' => evaluation_completedtmp_exporter::get_read_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_items.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_items_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id')
            )
        );
    }

    /**
     * Returns the items (questions) in the given feedback.
     *
     * @param int $evaluationid feedback instance id
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and feedbacks
     * @since Moodle 3.3
     */
    public static function get_items($evaluationid) {
        global $PAGE;
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $params = array('evaluationid' => $evaluationid);
        $params = self::validate_parameters(self::get_items_parameters(), $params);
        $warnings = array();

        $feedback = self::validate_evaluation($params['evaluationid']);

        $feedbackstructure = new local_evaluation_completion($feedback);
        $returneditems = array();
        if ($items = $feedbackstructure->get_items()) {
            foreach ($items as $item) {
                $itemnumber = empty($item->itemnr) ? null : $item->itemnr;
                unset($item->itemnr);   // Added by the function, not part of the record.
                $exporter = new evaluation_item_exporter($item, array('context' => $context, 'itemnumber' => $itemnumber));
                $returneditems[] = $exporter->export($PAGE->get_renderer('core'));
            }
        }

        $result = array(
            'items' => $returneditems,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_items return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_items_returns() {
        return new external_single_structure(
            array(
                'items' => new external_multiple_structure(
                    evaluation_item_exporter::get_read_structure()
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_page_items.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_page_items_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id'),
                'page' => new external_value(PARAM_INT, 'The page to get starting by 0')
            )
        );
    }

    /**
     * Get a single feedback page items.
     *
     * @param int $evaluationid feedback instance id
     * @param int $page the page to get starting by 0
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function get_page_items($evaluationid, $page) {
        global $PAGE;
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $params = array('evaluationid' => $evaluationid, 'page' => $page);
        $params = self::validate_parameters(self::get_page_items_parameters(), $params);
        $warnings = array();

        $feedback = self::validate_evaluation($params['evaluationid']);

        $feedbackcompletion = new local_evaluation_completion($feedback);

        $page = $params['page'];
        $pages = $feedbackcompletion->get_pages();
        $pageitems = $pages[$page];
        $hasnextpage = $page < count($pages) - 1; // Until we complete this page we can not trust get_next_page().
        $hasprevpage = $page && ($feedbackcompletion->get_previous_page($page, false) !== null);

        $returneditems = array();
        foreach ($pageitems as $item) {
            $itemnumber = empty($item->itemnr) ? null : $item->itemnr;
            unset($item->itemnr);   // Added by the function, not part of the record.
            $exporter = new evaluation_item_exporter($item, array('context' => $context, 'itemnumber' => $itemnumber));
            $returneditems[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'items' => $returneditems,
            'hasprevpage' => $hasprevpage,
            'hasnextpage' => $hasnextpage,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_page_items return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_page_items_returns() {
        return new external_single_structure(
            array(
                'items' => new external_multiple_structure(
                    evaluation_item_exporter::get_read_structure()
                ),
                'hasprevpage' => new external_value(PARAM_BOOL, 'Whether is a previous page.'),
                'hasnextpage' => new external_value(PARAM_BOOL, 'Whether there are more pages.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for process_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function process_page_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id.'),
                'page' => new external_value(PARAM_INT, 'The page being processed.'),
                'responses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_NOTAGS, 'The response name (usually type[index]_id).'),
                            'value' => new external_value(PARAM_RAW, 'The response value.'),
                        )
                    ), 'The data to be processed.', VALUE_DEFAULT, array()
                ),
                'goprevious' => new external_value(PARAM_BOOL, 'Whether we want to jump to previous page.', VALUE_DEFAULT, false),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the feedback (for site feedbacks only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Process a jump between pages.
     *
     * @param array $evaluationid feedback instance id
     * @param array $page the page being processed
     * @param array $responses the responses to be processed
     * @param bool $goprevious whether we want to jump to previous page
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function process_page($evaluationid, $page, $responses = [], $goprevious = false) {
        global $USER, $SESSION;
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $params = array('evaluationid' => $evaluationid, 'page' => $page, 'responses' => $responses, 'goprevious' => $goprevious);
        $params = self::validate_parameters(self::process_page_parameters(), $params);
        $warnings = array();
        $siteaftersubmit = $completionpagecontents = '';

        $feedback = self::validate_evaluation($params['evaluationid']);
        // Check we can do a new submission (or continue an existing).
        $feedbackcompletion = self::validate_evaluation_access($feedback, true);

        // Create the $_POST object required by the feedback question engine.
        $_POST = array();
        foreach ($responses as $response) {
            // First check if we are handling array parameters.
            if (preg_match('/(.+)\[(.+)\]$/', $response['name'], $matches)) {
                $_POST[$matches[1]][$matches[2]] = $response['value'];
            } else {
                $_POST[$response['name']] = $response['value'];
            }
        }
        // Force fields.
        $_POST['id'] = $evaluationid;
        $_POST['courseid'] = $courseid;
        $_POST['gopage'] = $params['page'];
        $_POST['_qf__local_evaluation_complete_form'] = 1;

        // Determine where to go, backwards or forward.
        if (!$params['goprevious']) {
            $_POST['gonextpage'] = 1;   // Even if we are saving values we need this set.
            if ($feedbackcompletion->get_next_page($params['page'], false) === null) {
                $_POST['savevalues'] = 1;   // If there is no next page, it means we are finishing the feedback.
            }
        }

        // Ignore sesskey (deep in some APIs), the request is already validated.
        $USER->ignoresesskey = true;
        evaluation_init_evaluation_session();
        $SESSION->evaluation->is_started = true;

        $feedbackcompletion->process_page($params['page'], $params['goprevious']);
        $completed = $feedbackcompletion->just_completed();

        if ($completed) {
            $jumpto = 0;
            if ($feedback->page_after_submit) {
                $completionpagecontents = $feedbackcompletion->page_after_submit();
            }

            if ($feedback->site_after_submit) {
                $siteaftersubmit = evaluation_encode_target_url($feedback->site_after_submit);
            }
        } else {
            $jumpto = $feedbackcompletion->get_jumpto();
        }

        $result = array(
            'jumpto' => $jumpto,
            'completed' => $completed,
            'completionpagecontents' => $completionpagecontents,
            'siteaftersubmit' => $siteaftersubmit,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the process_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function process_page_returns() {
        return new external_single_structure(
            array(
                'jumpto' => new external_value(PARAM_INT, 'The page to jump to.'),
                'completed' => new external_value(PARAM_BOOL, 'If the user completed the feedback.'),
                'completionpagecontents' => new external_value(PARAM_RAW, 'The completion page contents.'),
                'siteaftersubmit' => new external_value(PARAM_RAW, 'The link (could be relative) to show after submit.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_analysis.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_analysis_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the feedback (for site feedbacks only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves the feedback analysis.
     *
     * @param array $evaluationid feedback instance id
     * @param int $groupid group id, 0 means that the function will determine the user group
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function get_analysis($evaluationid, $groupid = 0) {
        global $PAGE;
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $params = array('evaluationid' => $evaluationid, 'groupid' => $groupid);
        $params = self::validate_parameters(self::get_analysis_parameters(), $params);
        $warnings = $itemsdata = array();

        $evaluation = self::validate_evaluation($params['evaluationid']);

        // Check permissions.
        $feedbackstructure = new local_evaluation_structure($evaluation);
        if (!$feedbackstructure->can_view_analysis()) {
            throw new required_capability_exception($context, 'local/evaluation:viewanalysepage', 'nopermission', '');
        }

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            $groupid = 0;
        }

        // Summary data.
        $summary = new local_evaluation\output\summary($feedbackstructure, $groupid);
        $summarydata = $summary->export_for_template($PAGE->get_renderer('core'));

        $checkanonymously = true;
        if ($groupid > 0 AND $feedback->anonymous == EVALUATION_ANONYMOUS_YES) {
            $completedcount = $feedbackstructure->count_completed_responses($groupid);
            if ($completedcount < EVALUATION_MIN_ANONYMOUS_COUNT_IN_GROUP) {
                $checkanonymously = false;
            }
        }

        if ($checkanonymously) {
            // Get the items of the feedback.
            $items = $feedbackstructure->get_items(true);
            foreach ($items as $item) {
                $itemobj = evaluation_get_item_class($item->typ);
                $itemnumber = empty($item->itemnr) ? null : $item->itemnr;
                unset($item->itemnr);   // Added by the function, not part of the record.
                $exporter = new evaluation_item_exporter($item, array('context' => $context, 'itemnumber' => $itemnumber));

                $itemsdata[] = array(
                    'item' => $exporter->export($PAGE->get_renderer('core')),
                    'data' => $itemobj->get_analysed_for_external($item, $groupid),
                );
            }
        } else {
            $warnings[] = array(
                'item' => 'feedback',
                'itemid' => $feedback->id,
                'warningcode' => 'insufficientresponsesforthisgroup',
                'message' => s(get_string('insufficient_responses_for_this_group', 'feedback'))
            );
        }

        $result = array(
            'completedcount' => $summarydata->completedcount,
            'itemscount' => $summarydata->itemscount,
            'itemsdata' => $itemsdata,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_analysis return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_analysis_returns() {
        return new external_single_structure(
            array(
            'completedcount' => new external_value(PARAM_INT, 'Number of completed submissions.'),
            'itemscount' => new external_value(PARAM_INT, 'Number of items (questions).'),
            'itemsdata' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'item' => evaluation_item_exporter::get_read_structure(),
                        'data' => new external_multiple_structure(
                            new external_value(PARAM_RAW, 'The analysis data (can be json encoded)')
                        ),
                    )
                )
            ),
            'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_unfinished_responses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_unfinished_responses_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id.')
            )
        );
    }

    /**
     * Retrieves responses from the current unfinished attempt.
     *
     * @param array $evaluationid feedback instance id
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function get_unfinished_responses($evaluationid) {
        global $PAGE;

        $params = array('evaluationid' => $evaluationid);
        $params = self::validate_parameters(self::get_unfinished_responses_parameters(), $params);
        $warnings = $itemsdata = array();

        $feedback = self::validate_evaluation($params['evaluationid']);
        $feedbackcompletion = new local_evaluation_completion($feedback);

        $responses = array();
        $unfinished = $feedbackcompletion->get_unfinished_responses();
        foreach ($unfinished as $u) {
            $exporter = new evaluation_valuetmp_exporter($u);
            $responses[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'responses' => $responses,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_unfinished_responses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_unfinished_responses_returns() {
        return new external_single_structure(
            array(
            'responses' => new external_multiple_structure(
                evaluation_valuetmp_exporter::get_read_structure()
            ),
            'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_finished_responses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_finished_responses_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id.')
            )
        );
    }

    /**
     * Retrieves responses from the last finished attempt.
     *
     * @param array $evaluationid feedback instance id
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and the responses
     * @since Moodle 3.3
     */
    public static function get_finished_responses($evaluationid) {
        global $PAGE;

        $params = array('evaluationid' => $evaluationid);
        $params = self::validate_parameters(self::get_finished_responses_parameters(), $params);
        $warnings = $itemsdata = array();

        $feedback = self::validate_evaluation($params['evaluationid']);
        $feedbackcompletion = new local_evaluation_completion($feedback);

        $responses = array();
        // Load and get the responses from the last completed feedback.
        $feedbackcompletion->find_last_completed();
        $unfinished = $feedbackcompletion->get_finished_responses();
        foreach ($unfinished as $u) {
            $exporter = new evaluation_value_exporter($u);
            $responses[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'responses' => $responses,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_finished_responses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_finished_responses_returns() {
        return new external_single_structure(
            array(
            'responses' => new external_multiple_structure(
                evaluation_value_exporter::get_read_structure()
            ),
            'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_non_respondents.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_non_respondents_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group.',
                                                VALUE_DEFAULT, 0),
                'sort' => new external_value(PARAM_ALPHA, 'Sort param, must be firstname, lastname or lastaccess (default).',
                                                VALUE_DEFAULT, 'lastaccess'),
                'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'The number of records to return per page.', VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the feedback (for site feedbacks only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves a list of students who didn't submit the feedback.
     *
     * @param int $evaluationid feedback instance id
     * @param int $groupid Group id, 0 means that the function will determine the user group'
     * @param str $sort sort param, must be firstname, lastname or lastaccess (default)
     * @param int $page the page of records to return
     * @param int $perpage the number of records to return per page
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and users ids
     * @since Moodle 3.3
     */
    public static function get_non_respondents($evaluationid, $groupid = 0, $sort = 'lastaccess', $page = 0, $perpage = 0) {

        global $CFG;
        require_once($CFG->dirroot . '/local/evaluation/lib.php');
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $params = array('evaluationid' => $evaluationid, 'groupid' => $groupid, 'sort' => $sort, 'page' => $page,
            'perpage' => $perpage);
        $params = self::validate_parameters(self::get_non_respondents_parameters(), $params);
        $warnings = $nonrespondents = array();

        $feedback = self::validate_evaluation($params['evaluationid']);
        $feedbackcompletion = new local_evaluation_completion($feedback);
        $completioncourseid = 0;

        if ($feedback->anonymous != EVALUATION_ANONYMOUS_NO || $feedback->course == SITEID) {
            throw new moodle_exception('anonymous', 'local_evaluation');
        }

        // Check permissions.
        require_capability('local/evaluation:viewreports', $context);
        $groupid = 0;
        if ($params['sort'] !== 'firstname' && $params['sort'] !== 'lastname' && $params['sort'] !== 'lastaccess') {
            throw new invalid_parameter_exception('Invalid sort param, must be firstname, lastname or lastaccess.');
        }

        // Check if we are page filtering.
        if ($params['perpage'] == 0) {
            $page = $params['page'];
            $perpage = EVALUATION_DEFAULT_PAGE_COUNT;
        } else {
            $perpage = $params['perpage'];
            $page = $perpage * $params['page'];
        }
        $users = evaluation_get_incomplete_users($feedback, $groupid, $params['sort'], $page, $perpage, true);
        foreach ($users as $user) {
            $nonrespondents[] = [
                'courseid' => $completioncourseid,
                'userid'   => $user->id,
                'fullname' => fullname($user),
                'started'  => $user->feedbackstarted
            ];
        }

        $result = array(
            'users' => $nonrespondents,
            'total' => evaluation_count_incomplete_users($feedback, $groupid),
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_non_respondents return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_non_respondents_returns() {
        return new external_single_structure(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'userid' => new external_value(PARAM_INT, 'The user id'),
                            'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                            'started' => new external_value(PARAM_BOOL, 'If the user has started the attempt'),
                        )
                    )
                ),
                'total' => new external_value(PARAM_INT, 'Total number of non respondents'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_responses_analysis.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_responses_analysis_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
                'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'The number of records to return per page', VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the feedback (for site feedbacks only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return the feedback user responses.
     *
     * @param int $evaluationid feedback instance id
     * @param int $groupid Group id, 0 means that the function will determine the user group
     * @param int $page the page of records to return
     * @param int $perpage the number of records to return per page
     * @param int $courseid course where user completes the feedback (for site feedbacks only)
     * @return array of warnings and users attemps and responses
     * @throws moodle_exception
     * @since Moodle 3.3
     */
    public static function get_responses_analysis($evaluationid, $groupid = 0, $page = 0, $perpage = 0) {
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $params = array('evaluationid' => $evaluationid, 'groupid' => $groupid, 'page' => $page, 'perpage' => $perpage,);
        $params = self::validate_parameters(self::get_responses_analysis_parameters(), $params);
        $warnings = $itemsdata = array();

        $feedback = self::validate_evaluation($params['evaluationid']);

        // Check permissions.
        require_capability('local/evaluation:viewreports', $context);
        $groupid = 0;
        $feedbackstructure = new local_evaluation_structure($feedback);
        $responsestable = new local_evaluation_responses_table($feedbackstructure, $groupid);
        // Ensure responses number is correct prior returning them.
        $feedbackstructure->shuffle_anonym_responses();
        $anonresponsestable = new local_evaluation_responses_anon_table($feedbackstructure, $groupid);

        $result = array(
            'attempts'          => $responsestable->export_external_structure($params['page'], $params['perpage']),
            'totalattempts'     => $responsestable->get_total_responses_count(),
            'anonattempts'      => $anonresponsestable->export_external_structure($params['page'], $params['perpage']),
            'totalanonattempts' => $anonresponsestable->get_total_responses_count(),
            'warnings'       => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_responses_analysis return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_responses_analysis_returns() {
        $responsestructure = new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Response id'),
                    'name' => new external_value(PARAM_RAW, 'Response name'),
                    'printval' => new external_value(PARAM_RAW, 'Response ready for output'),
                    'rawval' => new external_value(PARAM_RAW, 'Response raw value'),
                )
            )
        );

        return new external_single_structure(
            array(
                'attempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Completed id'),
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'userid' => new external_value(PARAM_INT, 'User who responded'),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified for the response'),
                            'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                            'responses' => $responsestructure
                        )
                    )
                ),
                'totalattempts' => new external_value(PARAM_INT, 'Total responses count.'),
                'anonattempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Completed id'),
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'number' => new external_value(PARAM_INT, 'Response number'),
                            'responses' => $responsestructure
                        )
                    )
                ),
                'totalanonattempts' => new external_value(PARAM_INT, 'Total anonymous responses count.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_last_completed.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_last_completed_parameters() {
        return new external_function_parameters (
            array(
                'evaluationid' => new external_value(PARAM_INT, 'Feedback instance id')
            )
        );
    }

    /**
     * Retrieves the last completion record for the current user.
     *
     * @param int $evaluationid feedback instance id
     * @return array of warnings and the last completed record
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_last_completed($evaluationid) {
        global $PAGE;

        $params = array('evaluationid' => $evaluationid);
        $params = self::validate_parameters(self::get_last_completed_parameters(), $params);
        $warnings = array();

        $feedback = self::validate_evaluation($params['evaluationid']);
        $feedbackcompletion = new local_evaluation_completion($feedback);

        if ($feedbackcompletion->is_anonymous()) {
             throw new moodle_exception('anonymous', 'local_evaluation');
        }
        if ($completed = $feedbackcompletion->find_last_completed()) {
            $exporter = new evaluation_completed_exporter($completed);
            return array(
                'completed' => $exporter->export($PAGE->get_renderer('core')),
                'warnings' => $warnings,
            );
        }
        throw new moodle_exception('not_completed_yet', 'local_evaluation');
    }

    /**
     * Describes the get_last_completed return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_last_completed_returns() {
        return new external_single_structure(
            array(
                'completed' => evaluation_completed_exporter::get_read_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for validating access to feedback.
     *
     * @param  stdClass   $feedback feedback object
     * @param  stdClass   $course   course where user completes the feedback (for site feedbacks only)
     * @param  stdClass   $cm       course module
     * @param  stdClass   $context  context object
     * @throws moodle_exception
     * @return mod_feedback_completion feedback completion instance
     * @since  Moodle 3.3
     */
    protected static function validate_evaluation_access($feedback, $checksubmit = false) {
        $feedbackcompletion = new local_evaluation_completion($feedback, false, $feedback->id);
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        if (!$feedbackcompletion->can_complete()) {
            throw new required_capability_exception($context, 'local/evaluation:complete', 'nopermission', '');
        }

        if (!$feedbackcompletion->is_open()) {
            throw new moodle_exception('feedback_is_not_open', 'local_evaluation');
        }

        if ($feedbackcompletion->is_empty()) {
            throw new moodle_exception('no_items_available_yet', 'local_evaluation');
        }

        if ($checksubmit && !$feedbackcompletion->can_submit()) {
            throw new moodle_exception('this_feedback_is_already_submitted', 'local_evaluation');
        }
        return $feedbackcompletion;
    }

    public static function data_for_evaluations_parameters() {
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
    } // end of data_for_evaluation_courses_parameters.


    public static function data_for_evaluations($filter, $filter_text='', $filter_offset = 0, $filter_limit = 0) {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_evaluations_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
            'filter_offset' => $filter_offset,
            'filter_limit' => $filter_limit
        ));

        $PAGE->set_context((new \local_evaluation\lib\accesslib())::get_module_context());

        $renderable = new \local_evaluation\output\evaluation_courses($params['filter'],$params['filter_text'], $params['filter_offset'], $params['filter_limit']);
        $output = $PAGE->get_renderer('local_evaluation');

        $data= $renderable->export_for_template($output);

        return $data;
    } //end of data_for_evaluation_courses.


    public static function data_for_evaluations_returns() {


        return new external_single_structure(array (

            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'),
            'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'),
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
               'functionname' => new external_value(PARAM_TEXT, 'Function name'),
               'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
               'evaluationtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
               'enableflow' => new external_value(PARAM_BOOL, "flag for flow enabling", VALUE_DEFAULT, true),
               'moduledetails'=> new external_multiple_structure(
                new external_single_structure(
                    array(
                        'eval_name' => new external_value(PARAM_RAW, 'Name of the feedback'),
                        'name' => new external_value(PARAM_RAW, 'Display Name of the feedback'),
                        'dates' => new external_value(PARAM_RAW, 'Dates for feedback'),
                        'type' => new external_value(PARAM_TEXT, 'Type of feedback'),
                        'enrolledon' => new external_value(PARAM_TEXT, 'Date of enrollment'),
                        'completedon' => new external_value(PARAM_TEXT, 'Date of Completion'),
                        'actions' => new external_value(PARAM_RAW, 'Actions of feedback'),
                        'Yettostart' => new external_value(PARAM_RAW, 'Date of Completion'),
                        'index' => new external_value(PARAM_INT, 'Index of Card'),
                        'evaluation_url' => new external_value(PARAM_RAW, 'evaluation url'),
                        )
                    )
                ),
               //'sub_tab' => new external_value(PARAM_INT, 'inprogress = 0 & completed = 1'),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
               //'table_class' => new external_value(PARAM_TEXT, 'class for the table'),
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

    }  // end of the function data_for_evaluation_courses_returns
    public static function data_for_evaluations_paginated_parameters(){
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
    public static function data_for_evaluations_paginated($options, $dataoptions, $offset = 0, $limit = 0, $contextid, $filterdata){
        global $DB, $PAGE;
        require_login();
        $PAGE->set_url('/local/courses/userdashboard.php', array());
        $PAGE->set_context($contextid);

        $decodedoptions = (array)json_decode($options);
        $decodedfilter = (array)json_decode($filterdata);
        $filter = $decodedoptions['filter'];
        $filter_text = isset($decodedfilter['search_query']) ? $decodedfilter['search_query'] : '';
        $filter_offset = $offset;
        $filter_limit = $limit;
        $renderable = new \local_evaluation\output\evaluation_courses($filter, $filter_text, $filter_offset, $filter_limit);
        $output = $PAGE->get_renderer('local_evaluation');
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
    public static function data_for_evaluations_paginated_returns(){
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
                    'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'),
                    'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
                    'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
                    'functionname' => new external_value(PARAM_TEXT, 'Function name'),
                    'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
                    'evaluationtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
                    'enableflow' => new external_value(PARAM_BOOL, "flag for flow enabling", VALUE_DEFAULT, false),
                    'moduledetails'=> new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'eval_name' => new external_value(PARAM_RAW, 'Name of the feedback'),
                            'name' => new external_value(PARAM_RAW, 'Display Name of the feedback'),
                            'dates' => new external_value(PARAM_RAW, 'Dates for feedback'),
                            'type' => new external_value(PARAM_TEXT, 'Type of feedback'),
                            'enrolledon' => new external_value(PARAM_TEXT, 'Date of enrollment'),
                            'completedon' => new external_value(PARAM_TEXT, 'Date of Completion'),
                            'actions' => new external_value(PARAM_RAW, 'Actions of feedback'),
                            'Yettostart' => new external_value(PARAM_RAW, 'Date of Completion'),
                            'evaluation_url' => new external_value(PARAM_RAW,'evaluation url'),
                            )
                        )
                    ),
                    'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
                   'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
                   'index' => new external_value(PARAM_INT, 'number of courses count'),
                   'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
                   'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
                )
            )
        )
    ]);
    }
}
