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
 * @subpackage local_classroom
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->libdir . '/formslib.php');
define('classroom',2);
use \local_classroom\form\classroom_form as classroom_form;
use local_classroom\local\querylib;
use local_classroom\classroom;

function local_classroom_pluginfile($course, $cm, $categorycontext, $filearea, $args, $forcedownload, array $options = array())
{
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'classroomlogo') {
        return false;
    }

    $itemid = array_shift($args);

    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($categorycontext->id, 'local_classroom', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_file($file, $filename, null, 0, false, 0);
}

/**
 * Serve the new group form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_classroom_output_fragment_classroom_form($args)
{
    global $CFG, $PAGE, $DB;
    $args = (object) $args;
    $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
    $return = '';
    $renderer = $PAGE->get_renderer('local_classroom');
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $data = $DB->get_record('local_classroom', ['id' => $args->id]);
    $formdata['id'] = $args->id;
    $customdata = array(
        'id' => $args->id,
        'form_status' => $args->form_status
    );
    if($args->id){
        $customdata['open_path'] = $data->open_path;
    }
    // $customdata['open_path'] = '/1/3/4/16/17';
    local_costcenter_set_costcenter_path($customdata);
    local_users_set_userprofile_datafields($customdata,$data);
    $mform = new classroom_form(null, $customdata, 'post', '', null, true, $formdata);
    $classroomdata = new stdClass();
    $classroomdata->id = $args->id;
    $classroomdata->form_status = $args->form_status;
    $mform->set_data($classroomdata);
    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    $formheaders = array_keys($mform->formstatus);
    $nextform = array_key_exists($args->form_status, $formheaders);
    if ($nextform === false) {
        return false;
    }
    ob_start();
    $formstatus = array();
    foreach (array_values($mform->formstatus) as $k => $mformstatus) {
        $activeclass = $k == $args->form_status ? 'active' : '';
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass, 'form-status' => $k);
    }
    $formstatusview = new \local_classroom\output\form_status($formstatus);
    $return .= $renderer->render($formstatusview);
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();
    return $return;
}
/**
 * [classroom_evaluation_types description]
 * @method classroom_evaluation_types
 * @return [type]                     [description]
 */
function classroom_evaluationtypes($evaluationid = 0, $instance = 0, $from = "view", $id = -1)
{
    global $DB;
    $classroomevaluationtypes = array(
        1 => 'Training feedback',
        2 => 'Trainer feedback'
    );
    $trainer_exist = $DB->record_exists('local_classroom_trainers', array('classroomid' => $instance));
    if (!$trainer_exist && $from == 'form') {
        unset($classroomevaluationtypes[2]);
    }
    $exist_cl_fd = $DB->count_records('local_evaluations', array('instance' => $instance, 'plugin' => 'classroom'));
    if ($exist_cl_fd == 0 && $from == 'form') {
        $return = $classroomevaluationtypes;
    } elseif ($id > 0 && $from == 'form') {
        $evaluationtype = $DB->get_field('local_evaluations', 'evaluationtype', array('id' => $id, 'plugin' => 'classroom'));
        $return = array($evaluationtype => $classroomevaluationtypes[$evaluationtype]);
    } elseif ($from == 'form') {

        $exist = $DB->record_exists('local_classroom', array('id' => $instance, 'trainingfeedbackid' => $evaluationid));
        $exist_id = $DB->get_field('local_classroom', 'trainingfeedbackid', array('id' => $instance));
        $exist_with_tr_fd = $DB->count_records_sql("SELECT count(id) as total FROM {local_classroom_trainers} where classroomid= :classroomid AND feedback_id>0", array('classroomid' => $instance));
        $exist_with_tr = $DB->count_records('local_classroom_trainers', array('classroomid' => $instance));
        if ($exist_id == 0) {
            $exist_id = -1;
        }

        if (($exist && $evaluationid > 0) || (!$exist && $evaluationid < 0 && $exist_id == $evaluationid && ($exist_with_tr_fd == $exist_with_tr))) {
            unset($classroomevaluationtypes[2]);
        } elseif (($exist_with_tr_fd == 0) || ($exist_with_tr_fd != $exist_with_tr) || ($exist_with_tr_fd == $exist_with_tr)) {
            unset($classroomevaluationtypes[1]);
        }

        $return = $classroomevaluationtypes;
    } else {
        $return = $classroomevaluationtypes;
    }

    return $return;
}
function classroom_manage_evaluations($evaluation, $add_update_instance)
{
    global $DB, $USER;
    $pluginevaluationtypes = classroom_evaluationtypes();
    if ($add_update_instance == 'update') {
        $params = array(
            'classroomid' => $evaluation->instance,
            'evaluationid' => $evaluation->id, 'timemodified' => time(),
            'usermodified' => $USER->id
        );
    }
    switch ($pluginevaluationtypes[$evaluation->evaluationtype]) {
        case 'Trainer feedback':

            $sql = 'UPDATE {local_classroom_trainers} SET timemodified = :timemodified,
                    usermodified = :usermodified WHERE feedback_id = :evaluationid AND classroomid = :classroomid';

            if ($add_update_instance == 'add') {

                $sql = 'UPDATE {local_classroom_trainers} SET
                            feedback_id = :evaluationid, timemodified = :timemodified,
                            usermodified = :usermodified WHERE id=:id AND classroomid = :classroomid
                            AND feedback_id = 0';

                $classroomtrainerssql = "SELECT ct.id,ct.classroomid FROM {user} AS u JOIN {local_classroom_trainers} AS ct ON ct.trainerid = u.id
                    WHERE ct.classroomid = :classroomid AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND u.id > 2 and ct.feedback_id = 0";
                $params = array();
                $params['classroomid'] = $evaluation->instance;

                $classroomtrainers = $DB->get_records_sql($classroomtrainerssql, $params);

                foreach ($classroomtrainers as $classroomtrainer) {

                    $evaluationid = $DB->insert_record("local_evaluations", $evaluation);
                    $evaluation->id = $evaluationid;

                    $params = array(
                        'context' => (new \local_classroom\lib\accesslib())::get_module_context($classroomtrainer->classroomid),
                        'objectid' => $evaluationid
                    );

                    $event = \local_classroom\event\classroom_feedbacks_created::create($params);
                    $event->add_record_snapshot('local_classroom', $evaluation->instance);
                    $event->trigger();

                    $params = array(
                        'classroomid' => $classroomtrainer->classroomid,
                        'evaluationid' => $evaluation->id, 'timemodified' => time(),
                        'usermodified' => $USER->id, 'id' => $classroomtrainer->id
                    );
                    classroom_evaluations_add_remove_users($classroomtrainer->classroomid, $evaluation->id, 'feedback_to_users');
                    $return = $DB->execute($sql, $params);
                }
            } else {
                $return = $DB->execute($sql, $params);
                $params = array(
                    'context' => (new \local_classroom\lib\accesslib())::get_module_context($evaluation->instance),
                    'objectid' => $evaluation->id
                );
                $event = \local_classroom\event\classroom_feedbacks_updated::create($params);
                $event->add_record_snapshot('local_classroom', $evaluation->instance);
                $event->trigger();
            }
            break;
        case 'Training feedback':

            $sql = 'UPDATE {local_classroom} SET
                trainingfeedbackid = :evaluationid, timemodified = :timemodified,
                usermodified = :usermodified WHERE id = :classroomid AND
                trainingfeedbackid = 0';

            if ($add_update_instance == 'add') {
                $evaluationid = $DB->insert_record("local_evaluations", $evaluation);
                $evaluation->id = $evaluationid;
                $params = array(
                    'context' => (new \local_classroom\lib\accesslib())::get_module_context($evaluation->instance),
                    'objectid' => $evaluationid
                );

                $event = \local_classroom\event\classroom_feedbacks_created::create($params);
                $event->add_record_snapshot('local_classroom', $evaluation->instance);
                $event->trigger();
                $params = array(
                    'classroomid' => $evaluation->instance,
                    'evaluationid' => $evaluation->id, 'timemodified' => time(),
                    'usermodified' => $USER->id
                );
                classroom_evaluations_add_remove_users($evaluation->instance, $evaluation->id, 'feedback_to_users');
                $return = $DB->execute($sql, $params);
            } else {
                $return = $DB->execute($sql, $params);
                $params = array(
                    'context' => (new \local_classroom\lib\accesslib())::get_module_context($evaluation->instance),
                    'objectid' => $evaluation->id
                );
                $event = \local_classroom\event\classroom_feedbacks_updated::create($params);
                $event->add_record_snapshot('local_classroom', $evaluation->instance);
                $event->trigger();
            }

            break;
        default:
            $return = false;
            break;
    }
    return $return;
}
function classroom_evaluations_add_remove_users($classroomid, $evaluationid = 0, $type, $add_update_user = 0, $action = 'add')
{
    global $DB, $USER;
    switch ($type) {
        case 'feedback_to_users':

            $submitted = new stdClass();
            $submitted->timemodified = time();
            $submitted->timecreated = time();
            $submitted->evaluationid = $evaluationid;
            $submitted->creatorid = $USER->id;

            $fromsql = "SELECT cu.id,cu.userid FROM {local_classroom_users} as cu
                    WHERE cu.classroomid= :classroomid ";

            $classroomusers = $DB->get_records_sql($fromsql, array('classroomid' => $classroomid));

            foreach ($classroomusers as $classroomuser) {
                $submitted->userid = $classroomuser->userid;

                $exist = $DB->record_exists('local_evaluation_users', array('userid' => $classroomuser->userid, 'evaluationid' => $evaluationid));
                if (empty($exist)) {
                    $insert = $DB->insert_record('local_evaluation_users', $submitted);
                }
            }

            break;
        case 'users_to_feedback':
            $submitted = new stdClass();
            $submitted->timemodified = time();
            $submitted->timecreated = time();
            $submitted->creatorid = $USER->id;
            $submitted->userid = $add_update_user;
            $fromsql = "SELECT e.id
                 FROM {local_evaluations} AS e
                WHERE e.plugin = 'classroom' AND e.instance = :classroomid ";
            $classroomevaluations = $DB->get_records_sql($fromsql, array('classroomid' => $classroomid));

            foreach ($classroomevaluations as $classroomevaluation) {
                $submitted->evaluationid = $classroomevaluation->id;

                $exist = $DB->record_exists('local_evaluation_users', array('userid' => $add_update_user, 'evaluationid' => $classroomevaluation->id));
                if (empty($exist)) {
                    if ($action == 'add') {
                        $insert = $DB->insert_record('local_evaluation_users', $submitted);
                    }
                } elseif ($exist) {
                    if ($action == 'update') {
                        $fromsql = "SELECT ec.id
                                        FROM {local_evaluation_completed} AS ec
                                       WHERE ec.evaluation = :evalid AND ec.userid = :userid";
                        $evaluationcompletions = $DB->get_records_sql($fromsql, array('evalid' => $classroomevaluation->id, 'userid' => $add_update_user));

                        foreach ($evaluationcompletions as $evaluationcomple) {
                            $DB->delete_records('local_evaluation_value', array('completed' => $evaluationcomple->id));
                            $DB->delete_records('local_evaluation_completed', array('id' => $evaluationcomple->id));
                        }
                        $DB->delete_records('local_evaluation_users',  array('evaluationid' => $classroomevaluation->id, 'userid' => $add_update_user));
                    }
                }
            }

            break;
    }
}
function classroom_evaluation_completed($evaluationid, $userid, $type)
{
    global $CFG, $DB, $USER;
    $pluginevaluationtypes = classroom_evaluationtypes();
    $evaluation = $DB->get_record_sql("SELECT id,instance,evaluationtype
                                     FROM {local_evaluations}
                                     WHERE id = :evalid AND plugin='classroom'", array('evalid' => $evaluationid));

    if ($evaluation) {

        switch ($pluginevaluationtypes[$evaluation->evaluationtype]) {
            case 'Trainer feedback':

                $local_classroom_trainers = $DB->get_record_sql("SELECT id,trainerid
                                                        FROM {local_classroom_trainers}
                                                        WHERE classroomid = :classroomid  AND feedback_id= :evalid", array('classroomid' => $evaluation->instance, 'evalid' => $evaluation->id));
                if ($type == 'add') {

                    $params = (object)array(
                        'clrm_trainer_id' => $local_classroom_trainers->id, 'classroomid' => $evaluation->instance,
                        'trainerid' => $local_classroom_trainers->trainerid, 'userid' => $userid, 'score' => 1,
                        'timecreated' => time(), 'usercreated' => $USER->id
                    );


                    $return = $DB->insert_record('local_classroom_trainerfb', $params);
                } elseif ($type == 'update') {

                    $DB->delete_records('local_classroom_trainerfb', array('clrm_trainer_id' => $local_classroom_trainers->id));
                    $return = $DB->execute('UPDATE {local_classroom_trainers} SET
                    feedback_id = 0,feedback_score=0, timemodified =' . time() . ',
                    usermodified = ' . $USER->id . ' WHERE id = :id', array('id' => $local_classroom_trainers->id));

                    $params = array(
                        'context' => (new \local_classroom\lib\accesslib())::get_module_context($evaluation->instance),
                        'objectid' => $evaluationid
                    );
                    $event = \local_classroom\event\classroom_feedbacks_deleted::create($params);
                    $event->add_record_snapshot('local_classroom', $evaluation->instance);
                    $event->trigger();
                }

                break;
            case 'Training feedback':

                $params = array(
                    'classroomid' => $evaluation->instance,
                    'timemodified' => time(), 'usermodified' => $USER->id
                );

                if ($type == 'add') {
                    $params['trainingfeedback'] = 1;
                    $params['userid'] = $userid;
                    $sqluserid = "userid=:userid";
                } elseif ($type == 'update') {
                    $params['trainingfeedback'] = 0;
                    $sqluserid = "1=1";
                    $return = $DB->execute('UPDATE {local_classroom} SET
                    trainingfeedbackid = 0,training_feedback_score=0, timemodified =' . time() . ',
                    usermodified = ' . $USER->id . ' WHERE id = :id', array('id' => $evaluation->instance));
                    $paramslog = array(
                        'context' => (new \local_classroom\lib\accesslib())::get_module_context($evaluation->instance),
                        'objectid' => $evaluation->id
                    );
                    $event = \local_classroom\event\classroom_feedbacks_deleted::create($paramslog);
                    $event->add_record_snapshot('local_classroom', $evaluation->instance);
                    $event->trigger();
                }

                $sql = 'UPDATE {local_classroom_users} SET
                    trainingfeedback = :trainingfeedback, timemodified = :timemodified,
                    usermodified = :usermodified WHERE ' . $sqluserid . ' AND classroomid = :classroomid';
                $return = $DB->execute($sql, $params);


                break;
        }
    }
}
function local_classroom_output_fragment_session_form($args)
{
    global $CFG, $DB;
    $args = (object) $args;
    $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
    $return = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;
    $formdata['cid'] = $args->cid;
    $mform = new \local_classroom\form\session_form(
        null,
        array(
            'id' => $args->id,
            'cid' => $args->cid, 'form_status' => $args->form_status
        ),
        'post',
        '',
        null,
        true,
        $formdata
    );
    if ($args->id > 0) {
        $sessiondata = $DB->get_record('local_classroom_sessions', array('id' => $args->id));
        $sessiondata->form_status = $args->form_status;
        $sessiondata->cs_description['text'] = $sessiondata->description;
        if ($sessiondata->trainerid == 0) {
            $sessiondata->trainerid = NULL;
        }
        $mform->set_data($sessiondata);
    }

    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    if($mform->formstatus){
    $formheaders = array_keys($mform->formstatus);
    }
    if($args->form_status){
    $nextform = array_key_exists($args->form_status, $formheaders);
    }
    if ($nextform === false) {
        return false;
    }
    ob_start();
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_classroom_output_fragment_classroom_completion_form($args)
{
    global $CFG, $DB;
    $args = (object) $args;
    $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
    $return = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;
    $formdata['cid'] = $args->cid;
    $mform = new \local_classroom\form\classroom_completion_form(
        null,
        array(
            'id' => $args->id,
            'cid' => $args->cid, 'form_status' => $args->form_status
        ),
        'post',
        '',
        null,
        true,
        $formdata
    );
    if ($args->id > 0) {
        $classroom_completiondata = $DB->get_record('local_classroom_completion', array('id' => $args->id));
        $classroom_completiondata->form_status = $args->form_status;


        if ($classroom_completiondata->sessionids == "NULL") {
            $classroom_completiondata->sessionids = null;
        }
        if ($classroom_completiondata->courseids == "NULL") {
            $classroom_completiondata->courseids = null;
        }

        $mform->set_data($classroom_completiondata);
    }

    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    if($mform->formstatus){
    $formheaders = array_keys($mform->formstatus);
    }
    if($args->form_status){
    $nextform = array_key_exists($args->form_status, $formheaders);
    }
    if ($nextform === false) {
        return false;
    }
    ob_start();
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_classroom_output_fragment_course_form($args)
{
    global $CFG, $PAGE, $DB;
    $args = (object) $args;
    $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
    $return = '';
    $renderer = $PAGE->get_renderer('local_classroom');
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $formdata);
    }
    $formdata['cid'] = $args->id;

    $mform = new classroomcourse_form(null, array(
        'cid' => $args->cid,
        'form_status' => $args->form_status
    ), 'post', '', null, true, $formdata);
    $classroomdata = new stdClass();
    $classroomdata->id = $args->id;
    $classroomdata->form_status = $args->form_status;
    $mform->set_data($classroomdata);

    if (!empty((array) $serialiseddata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    if($mform->formstatus){
    $formheaders = array_keys($mform->formstatus);
    }
    if($args->form_status){
    $nextform = array_key_exists($args->form_status, $formheaders);
    }
    if ($nextform === false) {
        return false;
    }
    ob_start();
    $formstatus = new \local_classroom\output\form_status(array_values((array)$mform->formstatus));
    $return .= $renderer->render($formstatus);
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}

class classroomcourse_form extends moodleform
{

    public function definition()
    {
        global $CFG, $DB, $USER;
        $querieslib = new querylib();
        $mform = &$this->_form;
        $cid = $this->_customdata['cid'];
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($cid);

        $mform->addElement('hidden', 'classroomid', $cid);
        $mform->setType('classroomid', PARAM_INT);

        $courses = array();
        $params = array();
        $course = $this->_ajaxformdata['course'];
        if (!empty($course)) {
            // $course = implode(',', $course);
            $coursessql = "SELECT c.id, c.fullname
                              FROM {course} AS c
                             WHERE c.visible = 1  AND c.id <> " . SITEID;

            list($csql, $courseparam) = $DB->get_in_or_equal($course, SQL_PARAMS_NAMED);
            $coursessql .= " AND c.id $csql ";
            $params = $params + $courseparam;
            $courses = $DB->get_records_sql_menu($coursessql, $params);
        } else if ($cid > 0) {
            $coursessql = "SELECT c.id, c.fullname
                              FROM {course} AS c

                              JOIN {local_classroom_courses} AS cc ON cc.courseid = c.id
                             WHERE cc.classroomid = :classroomid AND c.visible = 1 ";
            $courses = $DB->get_records_sql_menu($coursessql, array('classroomid' => $cid));
        } 
        $options = array(
            'ajax' => 'local_classroom/form-course-selector',
            'multiple' => false,
            'data-contextid' => $categorycontext->id,
            'data-classroomid' => $cid,
        );
        $mform->addElement('autocomplete', 'course', get_string('course', 'local_classroom'), $courses, $options);
        $mform->addRule('course', null, 'required', null, 'client');

        $mform->disable_form_change_checker();
    }
    public function validation($data, $files) {
        global $CFG, $DB, $USER;
        $errors = parent::validation($data, $files);   
		if (empty($data['course'])){
                $errors['course'] = get_string('no_courses_assigned', 'local_classroom');
            
        }
        return $errors;
    }
}

/**
 * User selector subclass for the list of potential users on the assign roles page,
 * when we are assigning in a context below the course level. (CONTEXT_MODULE and
 * some CONTEXT_BLOCK).
 *
 * This returns only enrolled users in this context.
 */
class local_classroom_potential_users extends user_selector_base
{
    protected $classroomid;
    protected $categorycontext;
    protected $courseid;
    /**
     * @param string $name control name
     * @param array $options should have two elements with keys groupid and courseid.
     */
    public function __construct($name, $options)
    {
        global $CFG;
        if (isset($options['context'])) {
            $this->context = $options['context'];
        } else {
            $this->context = context::instance_by_id($options['contextid']);
        }
        $options['accesscontext'] = $this->context;
        parent::__construct($name, $options);
        $this->classroomid = $options['classroomid'];
        $this->organization = $options['organization'];
        $this->department = $options['department'];
        $this->email = $options['email'];
        $this->idnumber = $options['idnumber'];
        $this->uname = $options['uname'];
        $this->searchanywhere = true;
        require_once($CFG->dirroot . '/group/lib.php');
    }

    protected function get_options()
    {
        global $CFG;
        $options = parent::get_options();
        $options['file'] = 'local/classroom/lib.php';
        $options['classroomid'] = $this->classroomid;
        // $options['courseid'] = $this->courseid;
        $options['contextid'] = $this->context->id;
        return $options;
    }

    public function find_users($search)
    {
        global $DB;
        $params = array();
        $classroom = $DB->get_record('local_classroom', array('id' => $this->classroomid));
        if (empty($classroom)) {
            print_error('classroom not found!');
        }

        // Now we have to go to the database.
        list($wherecondition, $params) = $this->search_sql($search, 'u');

        if ($wherecondition) {
            $wherecondition = ' AND ' . $wherecondition;
        }

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(u.id)';
        $params['confirmed'] = 1;
        $params['suspended'] = 0;
        $params['deleted'] = 0;

        $sql   = " FROM {user} AS u
                  WHERE 1 = 1
                        $wherecondition
                    AND u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                    AND u.deleted = :deleted
                        ";

        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($this->classroomid);

        if ((has_capability('local/classroom:manageclassroom', $categorycontext)) && (!is_siteadmin())) {
            $sql .= (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        }


        if (!empty($this->email)) {
            $email = explode(',', $this->email);
            list($emailsql, $emailparam) = $DB->get_in_or_equal($email, SQL_PARAMS_NAMED);
            $sql .= " AND u.id $emailsql";
            $params = $params + $emailparam;
        }
        if (!empty($this->uname)) {
            $uname = explode(',', $this->uname);
            list($unamesql, $unameparam) = $DB->get_in_or_equal($uname, SQL_PARAMS_NAMED);
            $sql .= " AND u.id $unamesql";
            $params = $params + $unameparam;
        }
        // if (!empty($this->department)) {
        //     $department = explode(',', $this->department);
        //     list($departmentsql, $departmentparam) = $DB->get_in_or_equal($department, SQL_PARAMS_NAMED);
        //     $sql .= " AND u.open_departmentid $departmentsql";
        //     $params = $params + $departmentparam;
        // }
        if (!empty($this->idnumber)) {
            $idnumber = explode(',', $this->idnumber);
            list($idnumbersql, $idnumberparam) = $DB->get_in_or_equal($idnumber, SQL_PARAMS_NAMED);
            $sql .= " AND u.id $idnumbersql";
            $params = $params + $idnumberparam;
        }

        $options = array('contextid' => $this->context->id, 'classroomid' => $this->classroomid, 'email' => $this->email, 'uname' => $this->uname, 'department' => $this->department, 'idnumber' => $this->idnumber, 'organization' => $this->organization);
        $local_classroom_existing_users = new local_classroom_existing_users('removeselect', $options);
        $enrolleduerslist = $local_classroom_existing_users->find_users('', true);
        if (!empty($enrolleduerslist)) {
            // $enrolleduers = implode(',', $enrolleduerslist);
            list($enrolleduerslistsql, $enrolleduerslistparam) = $DB->get_in_or_equal($enrolleduerslist, SQL_PARAMS_NAMED, 'param', false);
            $sql .= " AND u.id $enrolleduerslistsql";
            $params = $params + $enrolleduerslistparam;
        }

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        // Check to see if there are too many to show sensibly.
        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }
        // If not, show them.
        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }

        if ($search) {
            $groupname = get_string('potusersmatching', 'local_classroom', $search);
        } else {
            $groupname = get_string('potusers', 'local_classroom');
        }

        return array($groupname => $availableusers);
    }
}

/**
 * User selector subclass for the list of users who already have the role in
 * question on the assign roles page.
 */
class local_classroom_existing_users extends user_selector_base
{
    protected $classroomid;
    protected $categorycontext;
    // protected $courseid;
    /**
     * @param string $name control name
     * @param array $options should have two elements with keys groupid and courseid.
     */
    public function __construct($name, $options)
    {
        global $CFG;
        $this->searchanywhere = true;
        if (isset($options['context'])) {
            $this->context = $options['context'];
        } else {
            $this->context = context::instance_by_id($options['contextid']);
        }
        $options['accesscontext'] = $this->context;
        parent::__construct($name, $options);
        $this->classroomid = $options['classroomid'];
        $this->organization = $options['organization'];
        $this->department = $options['department'];
        $this->email = $options['email'];
        $this->idnumber = $options['idnumber'];
        $this->uname = $options['uname'];
        require_once($CFG->dirroot . '/group/lib.php');
    }

    protected function get_options()
    {
        global $CFG;
        $options = parent::get_options();
        $options['file'] = 'local/classroom/lib.php';
        $options['classroomid'] = $this->classroomid;
        // $options['courseid'] = $this->courseid;
        $options['contextid'] = $this->context->id;
        return $options;
    }
    public function find_users($search, $idsreturn = false)
    {
        global $DB;

        list($wherecondition, $params) = $this->search_sql($search, 'u');

        $params['classroomid'] = $this->classroomid;
        $fields = "SELECT DISTINCT u.id, " . $this->required_fields_sql('u');
        $countfields = "SELECT COUNT(DISTINCT u.id) ";
        $params['confirmed'] = 1;
        $params['suspended'] = 0;
        $params['deleted'] = 0;
        $sql = " FROM {user} AS u
                JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                 WHERE $wherecondition
                AND u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                    AND u.deleted = :deleted AND cu.classroomid = :classroomid";

        if (!empty($this->email)) {
            $email = explode(',', $this->email);
            list($emailsql, $emailparam) = $DB->get_in_or_equal($email, SQL_PARAMS_NAMED);
            $sql .= " AND u.id $emailsql";
            $params = $params + $emailparam;
        }
        if (!empty($this->uname)) {
            $uname = explode(',', $this->uname);
            list($unamesql, $unameparam) = $DB->get_in_or_equal($uname, SQL_PARAMS_NAMED);
            $sql .= " AND u.id $unamesql";
            $params = $params + $unameparam;
        }
        if (!empty($this->department)) {
            $department = explode(',', $this->department);
            list($departmentsql, $departmentparam) = $DB->get_in_or_equal($department, SQL_PARAMS_NAMED);
            $sql .= " AND u.open_departmentid $departmentsql";
            $params = $params + $departmentparam;
        }
        if (!empty($this->idnumber)) {
            $idnumber = explode(',', $this->idnumber);
            list($idnumbersql, $idnumberparam) = $DB->get_in_or_equal($idnumber, SQL_PARAMS_NAMED);
            $sql .= " AND u.id $idnumbersql";
            $params = $params + $idnumberparam;
        }
        if (!$this->is_validating()) {
            $existinguserscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($existinguserscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $existinguserscount);
            }
        }
        if ($idsreturn) {
            $categorycontextusers = $DB->get_records_sql_menu('SELECT DISTINCT u.id, u.id as userid ' . $sql, $params);
            return $categorycontextusers;
        } else {
            $order = " ORDER BY u.id DESC";
            $categorycontextusers = $DB->get_records_sql($fields . $sql . $order, $params);
        }

        // No users at all.
        if (empty($categorycontextusers)) {
            return array();
        }

        if ($search) {
            $groupname = get_string('enrolledusersmatching', 'enrol', $search);
        } else {
            $groupname = get_string('enrolledusers', 'enrol');
        }
        return array($groupname => $categorycontextusers);
    }

    protected function this_con_group_name($search, $numusers)
    {
        if ($this->context->contextlevel == CONTEXT_SYSTEM) {
            // Special case in the System context.
            if ($search) {
                return get_string('extusersmatching', 'local_classroom', $search);
            } else {
                return get_string('extusers', 'local_classroom');
            }
        }
        $categorycontexttype = context_helper::get_level_name($this->context->contextlevel);
        if ($search) {
            $a = new stdClass;
            $a->search = $search;
            $a->contexttype = $categorycontexttype;
            if ($numusers) {
                return get_string('usersinthisxmatching', 'core_role', $a);
            } else {
                return get_string('noneinthisxmatching', 'core_role', $a);
            }
        } else {
            if ($numusers) {
                return get_string('usersinthisx', 'core_role', $categorycontexttype);
            } else {
                return get_string('noneinthisx', 'core_role', $categorycontexttype);
            }
        }
    }

    protected function parent_con_group_name($search, $categorycontextid)
    {
        $categorycontext = context::instance_by_id($categorycontextid);
        $categorycontextname = $categorycontext->get_context_name(true, true);
        if ($search) {
            $a = new stdClass;
            $a->contextname = $categorycontextname;
            $a->search = $search;
            return get_string('usersfrommatching', 'core_role', $a);
        } else {
            return get_string('usersfrom', 'core_role', $categorycontextname);
        }
    }
}

function local_classroom_output_fragment_new_catform($args)
{
    global $CFG, $DB;

    $args = (object) $args;
    $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
    $categoryid = $args->categoryid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    if ($args->categoryid > 0) {
        $heading = 'Update category';
        $collapse = false;
        $data = $DB->get_record('local_classroom_categories', array('id' => $categoryid));
    }
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $categorycontext,
        'noclean' => true,
        'subdirs' => false,
    ];
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $categorycontext, 'group', 'description', null);

    $mform = new local_classroom\form\catform(null, array('editoroptions' => $editoroptions), 'post', '', null, true, $formdata);

    $mform->set_data($data);

    if (!empty($formdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
function classroom_filter($mform)
{
    global $DB, $USER;
    $stable = new stdClass();
    $stable->thead = false;
    $stable->start = 0;
    $stable->length = -1;
    $stable->search = '';
    $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
    $sql = "SELECT id, name FROM {local_classroom} WHERE id > 1";
    if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {
        // $classrooms = (new classroom)->classrooms($stable,true);
        $classroom_sql = "SELECT c.id FROM {local_classroom} AS c ";
        $concatsql = '';
        if ((has_capability('local/classroom:manageclassroom', $categorycontext)) && !(is_siteadmin())) {
            $concatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
            }
        $classroom_sql .= " WHERE 1 = 1 ";
        $classroom_sql .= $concatsql;
        $classroomids = $DB->get_fieldset_sql($classroom_sql);
        $componentid = implode(',', $classroomids);
        if (!empty($componentid)) {
            $params = array();
            $sql = "SELECT id, name FROM {local_classroom} ";
            $componentids = explode(',', $componentid);
            list($componentsql, $componentparam) = $DB->get_in_or_equal($componentids, SQL_PARAMS_NAMED);
            $sql .= " WHERE id $componentsql";
            $params = $params + $componentparam;

            $courseslist = $DB->get_records_sql_menu($sql, $params);
        } else {
            $courseslist = $DB->get_records_sql_menu("SELECT id, name FROM {local_classroom} ");
        }
    }
    $select = $mform->addElement('autocomplete', 'classroom', '', $courseslist, array('placeholder' => get_string('classroom_name', 'local_classroom')));
    $mform->setType('classroom', PARAM_RAW);
    $select->setMultiple(true);
}
function get_user_classroom($userid)
{
    global $DB;
    $sql = "SELECT lc.id,lc.name,lc.description 
                FROM {local_classroom} AS lc 
                JOIN {local_classroom_users} AS lcu ON lcu.classroomid = lc.id 
                WHERE userid = :userid ";
    $params = array();
    $params['userid'] = $userid;

    list($statussql, $statusparam) = $DB->get_in_or_equal(array(1, 4), SQL_PARAMS_NAMED);
    $sql .= " AND lc.status $statussql";
    $params = $params + $statusparam;

    $classrooms = $DB->get_records_sql($sql, $params);
    return $classrooms;
}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_classroom_leftmenunode()
{
    $categorycontext =  (new \local_classroom\lib\accesslib())::get_module_context();
    $classroomnode = '';
    if (((has_capability('local/classroom:manageclassroom', $categorycontext)) && (!has_capability('local/classroom:trainer_viewclassroom', $categorycontext))) || (is_siteadmin())) {
        $classroomnode .= html_writer::start_tag('li', array('id' => 'id_leftmenu_browseclassrooms', 'class' => 'pull-left user_nav_div browseclassrooms'));
        $classrooms_url = new moodle_url('/local/classroom/index.php');
        // $classrooms_icon = '<span class="classroom_icon_wrap"></span>';
        $classrooms_icon = '<i class="fa fa-desktop" aria-hidden="true"></i>';
        $classrooms = html_writer::link($classrooms_url, $classrooms_icon . '<span class="user_navigation_link_text">' . get_string('manage_classrooms', 'local_classroom') . '</span>', array('class' => 'user_navigation_link'));
        $classroomnode .= $classrooms;
        $classroomnode .= html_writer::end_tag('li');
    }

    return array('8' => $classroomnode);
}
function local_classroom_quicklink_node()
{
    global $CFG, $PAGE, $OUTPUT;
    $categorycontext =  (new \local_classroom\lib\accesslib())::get_module_context();
    $stable = new stdClass();
    if (has_capability('local/classroom:manageclassroom', $categorycontext) || is_siteadmin()) {
        $stable->thead = false;
        $stable->start = 0;
        $stable->length = 1;
        $stable->classroomstatus = -1;
        $local_classroom = '';
        $classrooms = (new classroom)->classrooms($stable);

        $count_cr = $classrooms['classroomscount'];

        $stable->classroomstatus = 1;
        $classrooms = (new classroom)->classrooms($stable);

        $count_activecr = $classrooms['classroomscount'];

        $stable->classroomstatus = 4;
        $classrooms = (new classroom)->classrooms($stable);

        $count_completedclassroom = $classrooms['classroomscount'];

        if ($count_activecr == 0 || $count_cr == 0) {
            $percentage = 0;
        } else {
            $percentage = round(($count_activecr / $count_cr) * 100);
            $percentage = (int)$percentage;
        }


        //local classrooms content
        $PAGE->requires->js_call_amd('local_classroom/ajaxforms', 'load');

        $data = array();
        $local_classrooms = '';
       // $data['percentage'] = $percentage;
        $data['pluginname'] = 'classroom';
        $data['node_header_string'] = get_string('manage_br_classrooms', 'local_classroom');
        $data['plugin_icon_class'] = 'fa fa-desktop';
        $data['createclassroom'] = false;
        $data['contextid'] = $categorycontext->id;
        $data['displaystats'] = TRUE;

        if (has_capability('local/classroom:createclassroom', $categorycontext) || is_siteadmin()) {
            $data['create'] = true;
            $data['create_element'] = html_writer::link('javascript:void(0)', get_string('create'), array('class' => 'quick_nav_link goto_local_classroom', 'title' => get_string('create_classroom', 'local_classroom'), 'onclick' => '(function(e){ require("local_classroom/ajaxforms").init({contextid:1, component:"local_classroom", callback:"classroom_form", form_status:0, plugintype: "local", pluginname: "classroom", id:0 }) })(event)'));
        }
        $data['viewlink_url'] = $CFG->wwwroot . '/local/classroom/index.php';
        $data['view'] = TRUE;
        // $data['root'] = $CFG->wwwroot;
        $data['count_total'] = $count_cr;
        $data['count_active'] = $count_activecr;
        $data['inactive_string'] = get_string('completed', 'local_users');
        $data['count_inactive'] = $count_completedclassroom;
        $data['space_count'] = 'two';
        $local_classrooms .= $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $data);
    }

    return array('4' => $local_classrooms);
}

/*
* Author Sarath
* return count of classrooms under selected costcenter
* @return  [type] int count of classrooms
*/
function costcenterwise_classroom_count($costcenter, $department = false, $subdepartment = false, $l4department=false, $l5department=false)
{
    global $USER, $DB;
    $newsql = $activesql = $cancelledsql = $completedsql = $holdsql = '';
    $params = array();
    $params['costcenterpath'] = '%/'.$costcenter.'/%';
    $sql = "SELECT count(id) FROM {local_classroom} WHERE concat('/',open_path,'/') LIKE :costcenterpath";

    if ($department) {
        $sql .= "  AND concat('/',open_path,'/') LIKE :departmentpath  ";
        $params['departmentpath'] = '%/'.$department.'/%';
    }
    if ($subdepartment) {
        $sql .= " AND concat('/',open_path,'/') LIKE :subdepartmentpath ";
        $params['subdepartmentpath'] = '%/'.$subdepartment.'/%';
    }
    if ($l4department) {
        $sql .= " AND concat('/',open_path,'/') LIKE :l4departmentpath ";
        $params['l4departmentpath'] = '%/'.$l4department.'/%';
    }
    if ($l5department) {
        $sql .= " AND concat('/',open_path,'/') LIKE :l5departmentpath ";
        $params['l5departmentpath'] = '%/'.$l5department.'/%';
    }
    $count = $DB->count_records_sql($sql, $params);

    $newsql .= " AND status = 0 ";
    $activesql .= " AND status = 1 ";
    $cancelledsql .= " AND status = 3 ";
    $completedsql .= " AND status = 4 ";

    $holdsql .= " AND status = 2 ";

    $newclassroomscount = $DB->count_records_sql($sql . $newsql, $params);

    $activeclassroomscount = $DB->count_records_sql($sql . $activesql, $params);
    $cancelledclassroomscount = $DB->count_records_sql($sql . $cancelledsql, $params);
    $completedclassroomscount = $DB->count_records_sql($sql . $completedsql, $params);

    $holdclassroomscount = $DB->count_records_sql($sql . $holdsql, $params);

    return array('classroom_plugin_exist' => true, 'allclassroomcount' => $count, 'newclassroomcount' => $newclassroomscount, 'activeclassroomcount' => $activeclassroomscount, 'cancelledclassroomcount' => $cancelledclassroomscount, 'completedclassroomcount' => $completedclassroomscount, 'holdclassroomscount' => $holdclassroomscount);
}

/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_classroom_list()
{
    return 'Classroom';
}

/**
 * Returns classrooms tagged with a specified tag.
 *
 * @param local_tags_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \local_tags\output\tagindex
 */
function local_classroom_get_tagged_classrooms($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0, $sort = '')
{
    global $CFG, $PAGE;
    // prepare for display of tags related to evaluations
    $perpage = $exclusivemode ? 10 : 5;
    $displayoptions = array(
        'limit' => $perpage,
        'offset' => $page * $perpage,
        'viewmoreurl' => null,
    );
    $renderer = $PAGE->get_renderer('local_classroom');
    $totalcount = $renderer->tagged_classrooms($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, $count = 1, $sort);
    $content = $renderer->tagged_classrooms($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, 0, $sort);
    $totalpages = ceil($totalcount / $perpage);
    if ($totalcount)
        return new local_tags\output\tagindex(
            $tag,
            'local_classroom',
            'classroom',
            $content,
            $exclusivemode,
            $fromctx,
            $ctx,
            $rec,
            $page,
            $totalpages
        );
    else
        return '';
}
/**
 * todo sql query departmentwise
 * @param  $categorycontext object
 * @return array
 **/
function org_dep_sql($categorycontext)
{
    global $DB, $USER;
    $sql = '';
    $params = array();
    if (has_capability('local/classroom:manageclassroom', $categorycontext)) {
        $sql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
    } elseif (has_capability('local/classroom:trainer_viewclassroom', $categorycontext)) {
        $myclassrooms = $DB->get_records_menu('local_classroom_trainers', array(
            'trainerid' => $USER->id
        ), 'id', 'id, classroomid');
        if (!empty($myclassrooms)) {
            list($relatedclassromsql, $params) = $DB->get_in_or_equal($myclassrooms, SQL_PARAMS_NAMED, 'myclassrooms');
            $sql = " AND c.id $relatedclassromsql";
        } else {
            return compact('sql', 'params');
        }
    } else {
        $sql .= (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        // target audience
        $gparams = array();
        $group_list = $DB->get_records_sql_menu("select cm.id,cm.cohortid as groupid from {cohort_members} cm where cm.userid IN ({$USER->id})");
        if (!empty($group_list)) {
            $groups_members = implode(',', $group_list);
            if (!empty($group_list)) {
                $grouquery = array();
                foreach ($group_list as $key => $group) {
                    $grouquery[] = " CONCAT(',',c.open_group,',') LIKE CONCAT('%,',$group,',%') ";
                }
                $groupqueeryparams = implode('OR', $grouquery);
                $gparams[] = '(' . $groupqueeryparams . ')';
            }
        }
       
        if (!empty($gparams))
            $opengroup = implode('AND', $gparams);
        else
            $opengroup = '1 != 1';
        $fparams = array();
        $fparams[] = " 1 = CASE WHEN (c.open_group!='-1' AND c.open_group <> '')
                THEN
                  CASE WHEN $opengroup
                    THEN 1
                    ELSE 0 END 
                ELSE 1 END ";       
      
        if (!empty($USER->open_designation) && $USER->open_designation != "") {
            $designationlike = "'%,$USER->open_designation,%'";
        } else {
            $designationlike = "''";
        }
        $fparams[] = " 1 = CASE WHEN c.open_designation IS NOT NULL
            THEN 
              CASE WHEN CONCAT(',',c.open_designation,',') LIKE {$designationlike}
                THEN 1
                ELSE 0 END
            ELSE 1 END  ";


        if (!empty($params)) {
            $finalparams = implode('AND', $fparams);
        } else {
            $finalparams = '1=1';
        }

        $sql .= " AND ($finalparams OR ( c.open_designation IS NULL  AND c.open_group IS NULL ) ) AND c.status in (1,3,4) ";
    }
    return compact('sql', 'params');
}

/**
 * todo sql query departmentwise
 * @param  $categorycontext object
 * @return array
 **/

function get_classroom_details($classid)
{
    global $USER, $DB, $PAGE;
    $categorycontext =  (new \local_classroom\lib\accesslib())::get_module_context();
    $PAGE->requires->js_call_amd('local_classroom/classroom', 'load', array());
    $PAGE->requires->js_call_amd('local_request/requestconfirm', 'load', array());
    $details = array();
    // $time = \local_costcenter\lib::get_userdate("d/m/Y H:i");
    $time = time();
    $joinsql = '';
    if (
        is_siteadmin() or has_capability('local/costcenter:manage_ownorganization', $categorycontext) or
        has_capability('local/costcenter:manage_owndepartments', $categorycontext) or has_capability('local/classroom:trainer_viewclassroom', $categorycontext)
    ) {
        $selectsql = "select c.*  ";
        $fromsql = " from  {local_certification} c ";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_certification' ";
        }
        $wheresql = " where c.id = ? ";

        $adminrecord = $DB->get_record_sql($selectsql . $fromsql . $joinsql . $wheresql, [$classid]);
        $details['manage'] = 1;
        $completedcount = $DB->count_records_sql("select count(cu.id) from {local_classroom_users} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.classroomid=? AND cu.completion_status=?", array($classid, 1));
        $enrolledcount = $DB->count_records_sql("select count(cu.id) from {local_classroom_users} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.classroomid=? ", array($classid));
        $sessioncount = $DB->count_records_sql("select count(cu.id) from {local_classroom_sessions} cu, {local_classroom} c where c.id = cu.classroomid AND cu.classroomid=? ", array($classid));
        $details['completed'] = $completedcount;
        $details['enrolled'] = $enrolledcount;
        $details['noofsessions'] = $sessioncount;
        $details['startdate'] = ($adminrecord->startdate) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $adminrecord->startdate) : '-';
        $details['enddate'] = ($adminrecord->enddate) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $adminrecord->enddate) : '-';
    } else {
        $selectsql = "select cu.*, c.id as cid, c.startdate, c.enddate ";

        $fromsql = " from {local_classroom_users} cu 
        JOIN {local_classroom} c ON c.id = cu.classroomid ";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_classroom' ";
        }
        $wheresql = " where 1 = 1 AND cu.userid = ? AND c.id = ? ";

        $record = $DB->get_record_sql($selectsql . $fromsql . $joinsql . $wheresql, [$USER->id, $classid], IGNORE_MULTIPLE);


        $sessioncount = $DB->count_records_sql("select count(cu.id) from {local_classroom_sessions} cu, {local_classroom} c where c.id = cu.classroomid AND cu.classroomid=? ", array($classid));
        $details['manage'] = 0;
        $details['status'] = ($record->completion_status == 1) ? get_string('completed', 'local_onlinetests') : get_string('pending', 'local_onlinetests');

        $classsql = "select c.* from {local_classroom} c where c.id = ?";
        $classroominfo = $DB->get_record_sql($classsql, [$classid]);
        $totalsetas = $classroominfo->capacity;
        $filledseats = $DB->count_records('local_classroom_users', ['classroomid' => $classid]);
        if ($classroominfo->status == 1 && $classroominfo->nomination_startdate <= $time || $time >= $classroominfo->nomination_enddate && $filledseats < $totalsetas) {
            if ($classroominfo->approvalreqd == 0) {
                $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('enroll', 'local_classroom') . ' title = ' . get_string('enroll', 'local_classroom') . ' onclick="(function(e){ require(\'local_classroom/classroom\').ManageclassroomStatus({action:\'selfenrol\', id: ' . $classroominfo->id . ', classroomid:' . $classroominfo->id . ',actionstatusmsg:\'classroom_self_enrolment\',classroomname:\'' . $classroominfo->name . '\'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>' . get_string('enroll', 'local_classroom') . '</button></a>';
            } else {
                $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('requestforenroll', 'local_classroom') . ' title = ' . get_string('enroll', 'local_classroom') . ' onclick="(function(e){ require(\'local_request/requestconfirm\').init({action:\'add\', componentid: ' . $classroominfo->id . ', component:\'classroom\',componentname:\'' . $classroominfo->name . '\'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>' . get_string('requestforenroll', 'local_classroom') . '</button></a>';
            }
        } else {
            $enrollmentbtn = '-';
        }

        $details['enrolled'] = ($record->timecreated) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->timecreated) : $enrollmentbtn;
        $details['completed'] = ($record->completiondate) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->completiondate) : '-';
        $details['noofsessions'] = ($sessioncount) ? $sessioncount : '-';
        $details['attendance'] = $record->attended_sessions;
        $details['startdate'] = ($record->startdate) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->startdate) : '-';
        $details['enddate'] = ($record->enddate) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->enddate) : '-';
    }
    return $details;
}
function local_classroom_request_dependent_query($aliasname)
{
    $returnquery = " WHEN ({$aliasname}.compname LIKE 'classroom') THEN (SELECT name from {local_classroom} WHERE id = {$aliasname}.componentid) ";
    return $returnquery;
}
function check_classroomenrol_pluginstatus($value)
{
    global $DB, $OUTPUT, $CFG;
    $enabled_plugins = $DB->get_field('config', 'value', array('name' => 'enrol_plugins_enabled'));
    $enabled_plugins =  explode(',', $enabled_plugins);
    $enabled_plugins = in_array('classroom', $enabled_plugins);

    if (!$enabled_plugins) {

        if (is_siteadmin()) {
            $url = $CFG->wwwroot . '/admin/settings.php?section=manageenrols';
            $enable = get_string('enableplugin', 'local_classroom', $url);
            echo $OUTPUT->notification($enable, 'notifyerror');
        } else {
            $enable = get_string('manageplugincapability', 'local_classroom');
            echo $OUTPUT->notification($enable, 'notifyerror');
        }
    }
}
function local_classroom_search_page_js(){
    global $PAGE;
    $PAGE->requires->js_call_amd('local_classroom/classroom','load', array());
}
function local_classroom_search_page_filter_element(&$filterelements){
    global $CFG;
    if(file_exists($CFG->dirroot.'/local/search/lib.php')){
        require_once($CFG->dirroot.'/local/search/lib.php');
        $filterelements['ilt'] = ['code' => 'classroom', 'name' => 'Classrooms', 'tagitemshortname' => 'classroom', 'count' => local_search_get_coursecount_for_modules([['type' => 'moduletype', 'values' => ['classroom']]])];
    }
}
function local_classroom_enabled_search(){
    return ['pluginname' => 'local_classroom', 'templatename' => 'local_classroom/searchpagecontent', 'type' => classroom];
}
function  local_classroom_applicable_filters_for_search_page(&$filterapplicable){
    $filterapplicable[classroom] = [/*'learningtype',*/ 'status', 'categories'/*, 'level', 'skill'*/];
}

