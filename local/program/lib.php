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
defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot.'/user/selector/lib.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
use \local_program\form\program_form as program_form;
use local_program\local\querylib;
use local_program\program;
// use \local_program\notifications_emails as programnotifications_emails;

function local_program_pluginfile($course, $cm, $categorycontext, $filearea, $args, $forcedownload, array $options=array()) {
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'programlogo') {
        return false;
    }

    $itemid = array_shift($args);

    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/'.implode('/', $args).'/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($categorycontext->id, 'local_program', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_file($file, $filename, 0, $forcedownload, $options);
}

/**
 * Serve the new group form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function local_program_output_fragment_program_form($args) {
    global $CFG, $PAGE, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $renderer = $PAGE->get_renderer('local_program');
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;

    $mform = new program_form(null, array('id' => $args->id,
        'form_status' => $args->form_status), 'post', '', null, true, $formdata);
    $programdata = new stdClass();
    $programdata->id = $args->id;
    $programdata->form_status = $args->form_status;
    $mform->set_data($programdata);

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
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass);
    }
    $formstatusview = new \local_program\output\form_status($formstatus);
    $return .= $renderer->render($formstatusview);
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_program_output_fragment_session_form($args) {
    global $CFG, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;
    $formdata['bcid'] = $args->bcid;
    $formdata['levelid'] = $args->levelid;
    $formdata['bclcid'] = $args->bclcid;
    $mform = new \local_program\form\session_form(null, array('id' => $args->id,
        'bcid' => $args->bcid, 'levelid' => $args->levelid, 'bclcid' => $args->bclcid,
        'form_status' => $args->form_status), 'post', '', null, true, $formdata);
    if ($args->id > 0) {
        $sessiondata = $DB->get_record('local_bc_course_sessions', array('id' => $args->id));
        $sessiondata->form_status = $args->form_status;
        $sessiondata->cs_description['text'] = $sessiondata->description;
        if ($sessiondata->trainerid == 0) {
            $sessiondata->trainerid = null;
        }
        $mform->set_data($sessiondata);
    }

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
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_program_output_fragment_program_completion_form($args) {
    global $CFG, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;
    $formdata['bcid'] = $args->bcid;
    $mform = new \local_program\form\program_completion_form(null, array('id' => $args->id,
        'bcid' => $args->cid, 'form_status' => $args->form_status), 'post', '', null, true, $formdata);
    if ($args->id > 0) {
        $program_completiondata = $DB->get_record('local_program_completion', array('id' => $args->id));
        $program_completiondata->form_status = $args->form_status;

        if ($program_completiondata->sessionids == "NULL") {
            $program_completiondata->sessionids = null;
        }
        if ($program_completiondata->courseids == "NULL") {
            $program_completiondata->courseids = null;
        }

        $mform->set_data($program_completiondata);
    }

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
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
function local_program_output_fragment_course_form($args) {
    global $CFG, $PAGE, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $renderer = $PAGE->get_renderer('local_program');
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $formdata['bcid'] = $args->id;
    $formdata['levelid'] = $args->levelid;
    $mform = new programcourse_form(null, array('bcid' => $args->bcid, 'levelid' => $args->levelid,
        'form_status' => $args->form_status), 'post', '', null, true, $formdata);
    $programdata = new stdClass();
    $programdata->id = $args->id;
    $programdata->form_status = $args->form_status;
    $mform->set_data($programdata);

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
    $formstatus = new \local_program\output\form_status(array_values($mform->formstatus));
    $return .= $renderer->render($formstatus);
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}

class programcourse_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;
        $querieslib = new querylib();
        $mform = &$this->_form;
        $bcid = $this->_customdata['bcid'];
        $levelid = $this->_customdata['levelid'];
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

        //$mform->addElement('header', 'general', get_string('addcourses', 'local_program'));

        $mform->addElement('hidden', 'programid', $bcid);
        $mform->setType('programid', PARAM_INT);

        $mform->addElement('hidden', 'levelid', $levelid);
        $mform->setType('levelid', PARAM_INT);

        $courses = array();
        $course = $this->_ajaxformdata['course'];
        if (!empty($course)) {
            $course = implode(',', $course);
            $coursessql = "SELECT c.id, c.fullname
                        FROM {course} AS c
                        JOIN {enrol} AS en on en.courseid=c.id AND en.enrol='program' AND en.status=0
                        WHERE c.id IN ($course) AND c.visible = 1 
                        AND concat(',', c.open_identifiedas, ',') LIKE '%,5,%' 
                        AND c.id <> " . SITEID; //FIND_IN_SET(5, c.open_identifiedas)
            $courses = $DB->get_records_sql_menu($coursessql);
        } else if ($id > 0) {
            $coursessql = "SELECT c.id, c.fullname
                             FROM {course} AS c
                             JOIN {enrol} AS en on en.courseid=c.id and en.enrol='program' and en.status=0
                             JOIN {local_program_level_courses} AS cc ON cc.courseid = c.id
                            WHERE cc.programid = $bcid AND c.visible = 1 
                            AND concat(',', c.open_identifiedas, ',') LIKE '%,5,%' "; 
                            //FIND_IN_SET(5, c.open_identifiedas)
            $courses = $DB->get_records_sql_menu($coursessql);
        }

        $options = array(
            'ajax' => 'local_program/form-course-selector',
            'multiple' => true,
            'data-contextid' => $categorycontext->id,
        );
        $mform->addElement('autocomplete', 'course', get_string('course', 'local_program'), $courses,
            $options);
        $mform->addRule('course', null, 'required', null, 'client');

        $mform->disable_form_change_checker();
    }
}

/**
 * User selector subclass for the list of potential users on the assign roles page,
 * when we are assigning in a context below the course level. (CONTEXT_MODULE and
 * some CONTEXT_BLOCK).
 *
 * This returns only enrolled users in this context.
 */
class local_program_potential_users extends user_selector_base {
    protected $programid;
    protected $categorycontext;
    protected $courseid;
    /**
     * @param string $name control name
     * @param array $options should have two elements with keys groupid and courseid.
     */
    public function __construct($name, $options) {
        global $CFG;
        if (isset($options['context'])) {
            $this->context = $options['context'];
        } else {
            $this->context = context::instance_by_id($options['contextid']);
        }
        $options['accesscontext'] = $this->context;
        parent::__construct($name, $options);
        $this->programid = $options['programid'];
        $this->organization = $options['organization'];
        $this->department = $options['department'];
        $this->email = $options['email'];
        $this->idnumber = $options['idnumber'];
        $this->uname = $options['uname'];
        $this->searchanywhere = true;
        require_once($CFG->dirroot . '/group/lib.php');
    }

    protected function get_options() {
        global $CFG;
        $options = parent::get_options();
        $options['file'] = 'local/program/lib.php';
        $options['programid'] = $this->programid;
        $options['contextid'] = $this->context->id;
        return $options;
    }

    public function find_users($search) {
        global $DB;
        $params = array();
        $program = $DB->get_record('local_program', array('id' => $this->programid));
        if (empty($program)) {
            print_error('program not found!');
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
                        {$wherecondition}
                    AND u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                    AND u.deleted = :deleted
                        ";

        if(is_siteadmin()){

            $sql .= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path',$program->open_path,'lowerandsamepath');

        }else{

            $sql .= (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

        }

        if (!empty($this->email)) {
            $sql .= " AND u.id IN ({$this->email})";
        }
        if (!empty($this->uname)) {
            $sql .= " AND u.id IN ({$this->uname})";
        }
        if (!empty($this->department)) {
            $sql .= " AND u.open_departmentid IN ($this->department)";
        }
        if (!empty($this->idnumber)) {
            $sql .= " AND u.id IN ($this->idnumber)";
        }

        $options = array('contextid' => $this->context->id, 'programid' => $this->programid, 'email' => $this->email, 'uname' => $this->uname, 'department' => $this->department, 'idnumber' => $this->idnumber, 'organization' => $this->organization);
        $local_program_existing_users = new local_program_existing_users('removeselect', $options);
        $enrolleduerslist = $local_program_existing_users->find_users('', true);
        if (!empty($enrolleduerslist)) {
            $enrolleduers = implode(',', $enrolleduerslist);
            $sql .= " AND u.id NOT IN ($enrolleduers)";
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
            $groupname = get_string('potusersmatching', 'local_program', $search);
        } else {
            $groupname = get_string('potusers', 'local_program');
        }

        return array($groupname => $availableusers);
    }
}

/**
 * User selector subclass for the list of users who already have the role in
 * question on the assign roles page.
 */
class local_program_existing_users extends user_selector_base {
    protected $programid;
    protected $categorycontext;
    // protected $courseid;
    /**
     * @param string $name control name
     * @param array $options should have two elements with keys groupid and courseid.
     */
    public function __construct($name, $options) {
        global $CFG;
        $this->searchanywhere = true;
        if (isset($options['context'])) {
            $this->context = $options['context'];
        } else {
            $this->context = context::instance_by_id($options['contextid']);
        }
        $options['accesscontext'] = $this->context;
        parent::__construct($name, $options);
        $this->programid = $options['programid'];
        $this->organization = $options['organization'];
        $this->department = $options['department'];
        $this->email = $options['email'];
        $this->idnumber = $options['idnumber'];
        $this->uname = $options['uname'];
        require_once($CFG->dirroot . '/group/lib.php');
    }

    protected function get_options() {
        global $CFG;
        $options = parent::get_options();
        $options['file'] = 'local/program/lib.php';
        $options['programid'] = $this->programid;
        // $options['courseid'] = $this->courseid;
        $options['contextid'] = $this->context->id;
        return $options;
    }
    public function find_users($search, $idsreturn = false) {
        global $DB;

        list($wherecondition, $params) = $this->search_sql($search, 'u');

        $params['programid'] = $this->programid;
        $fields = "SELECT DISTINCT u.id, " . $this->required_fields_sql('u') ;
        $countfields = "SELECT COUNT(DISTINCT u.id) ";
        $params['confirmed'] = 1;
        $params['suspended'] = 0;
        $params['deleted'] = 0;
        $sql = " FROM {user} AS u
                JOIN {local_program_users} AS cu ON cu.userid = u.id
                 WHERE {$wherecondition}
                AND u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                    AND u.deleted = :deleted AND cu.programid = :programid";
        if (!empty($this->email)) {
            $sql.=" AND u.id IN ({$this->email})";
        }
       if (!empty($this->uname)) {
            $sql .=" AND u.id IN ({$this->uname})";
        }
        if (!empty($this->department)) {
            $sql .=" AND u.open_departmentid IN ($this->department)";
        }
        if (!empty($this->idnumber)) {
            $sql .=" AND u.id IN ($this->idnumber)";
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

    protected function this_con_group_name($search, $numusers) {
        if ($this->context->contextlevel == CONTEXT_SYSTEM) {
            // Special case in the System context.
            if ($search) {
                return get_string('extusersmatching', 'local_program', $search);
            } else {
                return get_string('extusers', 'local_program');
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

    protected function parent_con_group_name($search, $categorycontextid) {
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

function local_program_output_fragment_new_catform($args) {
    global $CFG, $DB;

    $args = (object) $args;
    $categorycontext = $args->context;
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
        $data = $DB->get_record('local_program_categories', array('id' => $categoryid));
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

    $mform = new local_program\form\catform(null, array('editoroptions' => $editoroptions), 'post', '', null, true, $formdata);

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
function program_filter($mform){
    global $DB,$USER;
    $stable = new stdClass();
    $stable->thead = false;
    $stable->start = 0;
    $stable->length = -1;
    $stable->search = '';
    $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

    $program_sql = "SELECT bc.id  FROM {local_program} AS bc ";

    $concatsql = '';
    if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {


            if(is_siteadmin()){

                $concatsql.= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='bc.open_path');

            }else{

                $concatsql.= (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='bc.open_path');

            }
        }
        $program_sql .= " WHERE 1 = 1 ";
        $program_sql .= $concatsql;
        $programids = $DB->get_fieldset_sql($program_sql);
        $componentid = implode(',', $programids);
        if (!empty($componentid)) {
            $courseslist = $DB->get_records_sql_menu("SELECT id, name FROM {local_program}
                WHERE id IN ($componentid)");
        } else {
            $courseslist = $DB->get_records_sql_menu("SELECT id, name FROM {local_program} ");
        }

    $select = $mform->addElement('autocomplete', 'program', '', $courseslist,
        array('placeholder' => get_string('program_name', 'local_program')));
    $mform->setType('program', PARAM_RAW);
    $select->setMultiple(true);
}
function get_user_program($userid) {
    global $DB;
    $sql = "SELECT lc.id, lc.name, lc.description
                FROM {local_program} AS lc
                JOIN {local_program_users} AS lcu ON lcu.programid = lc.id
                WHERE userid = :userid AND lc.status IN (1, 4)";
    $programs = $DB->get_records_sql($sql, array('userid' => $userid));
    return $programs;
}

class program_managelevel_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;
        $querieslib = new querylib();
        $mform = &$this->_form;
        $id = $this->_customdata['id'];
        $programid = $this->_customdata['programid'];
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

        //$mform->addElement('header', 'general', get_string('addcourses', 'local_program'));

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'programid', $programid);
        $mform->setType('programid', PARAM_INT);

        $mform->addElement('text', 'level', get_string('level', 'local_program'));
        $mform->addRule('level', null, 'required', null, 'client');

        $mform->addElement('editor', 'level_description', get_string('description', 'local_program'));
        $mform->setType('level_description', PARAM_RAW);
        // $mform->addRule('description', null, 'required', null, 'client');

        $mform->disable_form_change_checker();
    }
}

function local_program_output_fragment_program_managelevel_form($args) {
    global $CFG, $PAGE, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $renderer = $PAGE->get_renderer('local_program');
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;
    $formdata['programid'] = $args->programid;

    $mform = new program_managelevel_form(null, array('id' => $args->id,
        'programid' => $args->programid, 'form_status' => $args->form_status), 'post', '', null,
        true, $formdata);
    $bclevel = new stdClass();
    $bclevel->programid = $args->programid;
    if ($args->id > 0) {
        $bclevel = $DB->get_record('local_program_levels', array('id' => $args->id));
    }

    $bclevel->form_status = $args->form_status;
    $bclevel->level_description['text'] = $bclevel->description;
    $mform->set_data($bclevel);

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
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass);
    }
    $formstatusview = new \local_program\output\form_status($formstatus);
    $return .= $renderer->render($formstatusview);
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}


class program_managestream_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;
        $querieslib = new querylib();
        $mform = &$this->_form;
        $id = $this->_customdata['id'];
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1,1), false, 'local_program', $categorycontext, $multiple = false);

        $mform->addElement('text', 'stream', get_string('stream', 'local_program'));
        $mform->addRule('stream', null, 'required', null, 'client');

        $mform->addElement('editor', 'stream_description', get_string('description', 'local_program'));
        $mform->setType('stream_description', PARAM_RAW);

        $mform->disable_form_change_checker();
    }
}

function local_program_output_fragment_program_managestream_form($args) {
    global $CFG, $PAGE, $DB;
    $args = (object) $args;
    $categorycontext = $args->context;
    $return = '';
    $renderer = $PAGE->get_renderer('local_program');
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $formdata['id'] = $args->id;

    $mform = new program_managestream_form(null, array('id' => $args->id,
        'form_status' => $args->form_status), 'post', '', null,
        true, $formdata);
    $bcstream = new stdClass();
    if ($args->id > 0) {
        $bcstream = $DB->get_record('local_program_stream', array('id' => $args->id));
    }

    $bcstream->form_status = $args->form_status;
    $bcstream->stream_description['text'] = $bcstream->description;
    $mform->set_data($bcstream);

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
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass);
    }
    $formstatusview = new \local_program\output\form_status($formstatus);
    $return .= $renderer->render($formstatusview);
    $mform->display();
    $return .= ob_get_contents();
    ob_end_clean();

    return $return;
}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_program_leftmenunode(){
    $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
    $programnode = '';
    if(((has_capability('local/program:manageprogram', $categorycontext)) &&
        (!has_capability('local/program:trainer_viewprogram', $categorycontext))) ||
        (is_siteadmin())) {
        $programnode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_browseprograms', 'class'=>'pull-left user_nav_div browseprograms'));
            $programs_url = new moodle_url('/local/program/index.php');
            $program_icon = '<i class="fa fa-graduation-cap" aria-hidden="true"></i>';
            $programs = html_writer::link($programs_url, $program_icon.'<span class="user_navigation_link_text">'.get_string('browse_programs','local_program').'</span>',array('class'=>'user_navigation_link'));
            $programnode .= $programs;
        $programnode .= html_writer::end_tag('li');
    }

    //return array('10' => $programnode);
}
function local_program_quicklink_node(){
    global $CFG, $PAGE, $OUTPUT;
    $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
    $stable = new stdClass();
    if(has_capability('local/program:manageprogram', $categorycontext) || is_siteadmin()){
            
        // $stable->thead = false;
        // $stable->start = 0;
        // $stable->length = 1;
        // $stable->programstatus = -1;
        // $programs = (new program)->programs($stable);
        
        // $count_cr = $programs['programscount'];
        
        // $stable->programstatus = 1;
        // $programs = (new program)->programs($stable);
        
        // $count_activecr = $programs['programscount'];
        
        // $stable->programstatus = 3;
        // $programs = (new program)->programs($stable);
        
        // $count_cancelledcr = $programs['programscount'];
        
        // //local programs content
        $PAGE->requires->js_call_amd('local_program/ajaxforms', 'load');
        // $local_programs_content = $PAGE->requires->js_call_amd('local_program/ajaxforms', 'load');
        // $local_programs_content .= "<span class='anch_span'><span class='bootcamp_icon_wrap'></span></span>";
        // $local_programs_content .= "<div class='quick_navigation_detail'>
        //                                 <div class='span_str'>".get_string('manage_br_programs', 'local_program')."</div>";
        //     $local_programs_content .= "<span class='span_createlink'>";
        //     if(has_capability('local/program:createprogram', $categorycontext) || is_siteadmin()){
        //         $local_programs_content .= "<a href='javascript:void(0);' class='quick_nav_link goto_local_program' title='".get_string('create_program', 'local_program')."' onclick='(function(e){ require(\"local_program/ajaxforms\").init({contextid: ".$categorycontext->id.", component:\"local_program\", callback:\"program_form\", form_status:0, plugintype: \"local\", pluginname: \"program\", id:0, title: \"createprogram\" }) })(event)' >".get_string('create')."</a> | ";
        //     }
            
        //     $local_programs_content .="<a href='".$CFG->wwwroot."/local/program/index.php' class='viewlink' title= '".get_string('view_programs', 'local_program')." '>".get_string('view')."</a>
        //                                 </span>";
        // $local_programs_content .= "</div>";
        // $local_programs = '<div class="quick_nav_list manage_programs one_of_three_columns" >'.$local_programs_content.'</div>';

        $programs = array();
        $programs['node_header_string'] = get_string('manage_br_programs', 'local_program');
        $programs['pluginname'] = 'bootcamp';
        $programs['plugin_icon_class'] = 'fa fa-graduation-cap';
        if(has_capability('local/program:createprogram', $categorycontext) || is_siteadmin()){
            $programs['create'] = TRUE;
            $programs['create_element'] = html_writer::link('javascript:void(0)', get_string('create'), array('class' => 'quick_nav_link goto_local_program', 'title' => get_string('create_program', 'local_program'), 'onclick' => '(function(e){ require("local_program/ajaxforms").init({contextid: '.$categorycontext->id.', component:"local_program", callback:"program_form", form_status:0, plugintype: "local", pluginname: "program", id:0, title: "createprogram" }) })(event)'));
        }
        // if(has_capability('local/courses:view', $categorycontext) || has_capability('local/courses:manage', $categorycontext)){
        $programs['viewlink_url'] = $CFG->wwwroot.'/local/program/index.php';
        $programs['view'] = TRUE;
        $programs['viewlink_title'] = get_string('view_programs', 'local_program');
        // }
        $programs['space_count'] = 'one';
        $content = $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $programs);
    }
    
    return array('8' => $content);
}

/**
 * process the bootcamp_mass_enroll
 * @param csv_import_reader $cir  an import reader created by caller
 * @param Object $bootcamp  a bootcamp record from table mdl_local_bootcamp
 * @param Object $categorycontext  course context instance
 * @param Object $data    data from a moodleform
 * @return string  log of operations
 */
function program_mass_enroll($cir, $program, $categorycontext, $data) {
    global $CFG,$DB, $USER;
    require_once ($CFG->dirroot . '/group/lib.php');
    // require_once($CFG->dirroot . '/local/program/notifications_emails.php');
    // $emaillogs = new programnotifications_emails();
    $emaillogs = new \local_program\notification();
    // init csv import helper
    $useridfield = $data->firstcolumn;
    $cir->init();
    $enrollablecount = 0;
    while ($fields = $cir->next()) {
        $a = new stdClass();
        if (empty ($fields))
            continue;
        $fields[0]= str_replace('"', '', trim($fields[0]));
        /*First Condition To validate users*/
        $categorycontext = (new \local_program\lib\accesslib())::get_module_context();

        $sql="SELECT u.* from {user} u where u.deleted=0 and u.suspended=0 and u.$useridfield LIKE '{$fields[0]}' ";

        if(is_siteadmin()){

            $sql .= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path',$open_path=null,'lowerandsamepath');

        }else{

            $sql .= (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

        }

        if (!$user = $DB->get_record_sql($sql)) {
            $result .= '<div class="alert alert-error">'.get_string('im:user_unknown', 'local_courses', $fields[0] ). '</div>';
            continue;
        } else {
            // if (file_exists($CFG->dirroot . '/local/lib.php')) {
            //     require_once($CFG->dirroot . '/local/lib.php');
            // }
            $allow = true;
            $type = 'program_enrol';
            $dataobj = $program->id;
            $fromuserid = $USER->id;
            if ($allow) {
                // foreach ($userstoassign as $key => $adduser) {
                    if (true) {
                        $programuser = new stdClass();
                        $programuser->programid = $program->id;
                        $programuser->courseid = 0;
                        $programuser->userid = $user->id;
                        $programuser->supervisorid = 0;
                        $programuser->prefeedback = 0;
                        $programuser->postfeedback = 0;
                        $programuser->trainingfeedback = 0;
                        $programuser->confirmation = 0;
                        $programuser->attended_sessions = 0;
                        $programuser->hours = 0;
                        $programuser->completion_status = 0;
                        $programuser->completiondate = 0;
                        $programuser->usercreated = $USER->id;
                        $programuser->timecreated = time();
                        $programuser->usermodified = $USER->id;
                        $programuser->timemodified = time();
                        try {
                            $programuser->id = $DB->insert_record('local_program_users',
                            $programuser);
                            // $local_program = $DB->get_record_sql("SELECT * FROM {local_program} where id = $program->id");
                            $local_program = $DB->get_record('local_program', array('id' => $program->id));

                            $params = array(
                                'context' => $categorycontext,
                                'objectid' => $programuser->id,
                                'other' => array('programid' => $program->id)
                            );

                            $event = \local_program\event\program_users_enrol::create($params);
                            $event->add_record_snapshot('local_program_users', $programuser);
                            $event->trigger();

                            if ($local_program->status == 0) {
                                // $email_logs = $emaillogs->program_emaillogs($type, $dataobj, $programuser->userid, $fromuserid);
                                $touser = \core_user::get_user($programuser->userid);
                                $email_logs = $emaillogs->program_notification($type, $touser, $USER, $local_program);
                            }
                            $result .= '<div class="alert alert-success">'.get_string('im:enrolled_ok', 'local_courses', fullname($user)).'</div>';
                            $enrollablecount ++;
                        } catch (dml_exception $ex) {
                            print_error($ex);
                        }
                    } else {
                        break;
                    }
                // }
                $programid = $program->id;
                $program = new stdClass();
                $program->id = $programid;
                $program->totalusers = $DB->count_records('local_program_users',
                    array('programid' => $programid));
                $DB->update_record('local_program', $program);
            }
        }
    }
    $result .= '<br />';//exit;
    $result .= get_string('im:stats_i', 'local_program', $enrollablecount) . "";
    return $result;
}

/*
* Author Sarath
* return count of programs under selected costcenter
* @return  [type] int count of programs
*/
function costcenterwise_program_count($costcenter, $department = false, $subdepartment = false, $l4department=false, $l5department=false){
    global $USER, $DB,$CFG;
        $params = array();
        $params['costcenterpath'] = '%/'.$costcenter.'/%';

        $countprogramql = "SELECT count(id) FROM {local_program} WHERE concat('/',open_path,'/') LIKE :costcenterpath";

        if ($department) {
            $countprogramql .= "  AND concat('/',open_path,'/') LIKE :departmentpath  ";
            $params['departmentpath'] = '%/'.$department.'/%';
        }
        if ($subdepartment) {
            $countprogramql .= " AND concat('/',open_path,'/') LIKE :subdepartmentpath ";
            $params['subdepartmentpath'] = '%/'.$subdepartment.'/%';
        }
        if ($l4department) {
            $countprogramql .= " AND concat('/',open_path,'/') LIKE :l4departmentpath ";
            $params['l4departmentpath'] = '%/'.$l4department.'/%';
        }
        if ($l5department) {
            $countprogramql .= " AND concat('/',open_path,'/') LIKE :l5departmentpath ";
            $params['l5departmentpath'] = '%/'.$l5department.'/%';
        }

        $activesql = " AND visible = 1 ";
        $inactivesql = " AND visible = 0 ";

        $countprograms = $DB->count_records_sql($countprogramql, $params);
        $activeprograms = $DB->count_records_sql($countprogramql.$activesql, $params);
        $inactiveprograms = $DB->count_records_sql($countprogramql.$inactivesql, $params);
        if($countprograms >= 0){

            if($costcenter){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter;
            }
            if($department){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter.'&departmentid='.$department;
            }
            if($subdepartment){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
            }
            if($l4department){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
            }
            if($l5department){
                $viewprogramlink_url = $CFG->wwwroot.'/local/program/index.php?costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
            }
        }
        if($activeprograms >= 0){

            if($costcenter){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter;
            }
            if($department){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department;
            }
            if($subdepartment){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
            }
            if($l4department){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
            }
            if($l5department){
                $count_programactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=active&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
            }
        }
        if($inactiveprograms >= 0){

            if($costcenter){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter;
            }
            if($department){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department;
            }
            if($subdepartment){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment;
            }
            if($l4department){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department;
            }
            if($l5department){
                $count_programinactivelink_url = $CFG->wwwroot.'/local/program/index.php?status=inactive&costcenterid='.$costcenter.'&departmentid='.$department.'&subdepartmentid='.$subdepartment.'&l4department='.$l4department.'&l5department='.$l5department;
            }
        }
    return array('program_plugin_exist' => true,'allprogramcount' => $countprograms,'activeprogramcount' => $activeprograms,'inactiveprogramcount' => $inactiveprograms,'viewprogramlink_url'=>$viewprogramlink_url,'count_programactivelink_url' => $count_programactivelink_url,'count_programinactivelink_url' => $count_programinactivelink_url);
}

/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_program_list(){
    return 'Program';
}

/**
 * Returns programs tagged with a specified tag.
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
function local_program_get_tagged_programs($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0, $sort = '') {
    global $CFG, $PAGE;
    // prepare for display of tags related to evaluations
    $perpage = $exclusivemode ? 10 : 5;
    $displayoptions = array(
        'limit' => $perpage,
        'offset' => $page * $perpage,
        'viewmoreurl' => null,
    );
    $renderer = $PAGE->get_renderer('local_program');
    $totalcount = $renderer->tagged_programs($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, $count = 1, $sort);
    $content = $renderer->tagged_programs($tag->id, $exclusivemode, $ctx, $rec, $displayoptions, 0, $sort);
    $totalpages = ceil($totalcount / $perpage);
    if ($totalcount)
    return new local_tags\output\tagindex($tag, 'local_program', 'program', $content,
            $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    else
    return '';
}
/**
* todo sql query departmentwise
* @param  $categorycontext object
* @return array
**/
function orgdep_sql($categorycontext){
    global $DB, $USER;
    $sql = '';
    $params =array();

    $sql = (new \local_program\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');

    if(is_siteadmin()){

        $sql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path',$open_path=null,'lowerandsamepath');

    }elseif (has_capability('local/program:trainer_viewprogram', $categorycontext)){
        $myprograms = $DB->get_records_menu('local_program_trainers', array(
                    'trainerid' => $USER->id), 'id', 'id, programid');
        if (!empty($myprograms)) {
            list($relatedprogramsql, $params) = $DB->get_in_or_equal($myprograms, SQL_PARAMS_NAMED, 'myprograms');
            $sql = " AND c.id $relatedprogramsql";
        } else {
            return compact('sql', 'params');
        }
    }
    return compact('sql', 'params'); 
}

/**
* todo sql query departmentwise
* @param  $categorycontext object
* @return array
**/

function get_program_details($classid) { 
    global $USER, $DB, $PAGE;
    $categorycontext = (new \local_program\lib\accesslib())::get_module_context();
    $PAGE->requires->js_call_amd('local_program/program','load', array());
    $PAGE->requires->js_call_amd('local_request/requestconfirm','load', array());
    $details = array();
    $joinsql = " ";
    if(has_capability('local/program:manageprogram',$categorycontext)){
        $selectsql = "select c.*  ";
        $fromsql = " from  {local_program} c ";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_program' ";
        }
        $wheresql = " where c.id = ? ";

        $adminrecord = $DB->get_record_sql($selectsql.$fromsql.$joinsql.$wheresql, [$classid]);
        $details['manage'] = 1;
        $completedcount = $DB->count_records_sql("select count(cu.id) from {local_program_users} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.programid=? AND cu.completion_status=?", array($classid, 1));
        $enrolledcount = $DB->count_records_sql("select count(cu.id) from {local_program_users} cu, {user} u where u.id = cu.userid AND u.deleted = 0 AND u.suspended = 0 AND cu.programid=? ", array($classid));
        $sessioncount = $DB->count_records_sql("select count(cu.id) from {local_bc_course_sessions} cu, {local_program} c where c.id = cu.programid AND cu.programid=? ", array($classid));
        $details['completed'] = $completedcount;
        $details['enrolled'] = $enrolledcount;
        $details['noofsessions'] = $sessioncount;
    } else {
        $selectsql = "select cu.*, c.id as cid ";

        $fromsql = " from {local_program_users} cu 
        JOIN {local_program} c ON c.id = cu.programid ";
        if ($DB->get_manager()->table_exists('local_rating')) {
            $selectsql .= " , AVG(rating) as avg ";
            $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_program' ";
        }
        $wheresql = " where 1 = 1 AND cu.userid = ? AND c.id = ? ";

        $record = $DB->get_record_sql($selectsql.$fromsql.$joinsql.$wheresql, [$USER->id, $classid]);
        $sessioncount = $DB->count_records_sql("select count(cu.id) from {local_bc_course_sessions} cu, {local_program} c where c.id = cu.programid AND cu.programid=? ", array($classid));
        $classsql = "select c.* from {local_program} c where c.id = ?";
        $programinfo = $DB->get_record_sql($classsql, [$classid]);
        
        if ($programinfo->selfenrol == 1 && $programinfo->approvalreqd == 0) {
              $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('enroll','local_program'). ' title = ' .get_string('enroll','local_program'). ' onclick="(function(e){ require(\'local_program/program\').ManageprogramStatus({action:\'selfenrol\', id: '.$programinfo->id.', programid:'.$programinfo->id.',actionstatusmsg:\'program_self_enrolment\',programname:\''.$programinfo->name.'\'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>'.get_string('enroll','local_program').'</button></a>';
        } elseif ($programinfo->selfenrol == 1 && $programinfo->approvalreqd == 1) {
              $enrollmentbtn = '<a href="javascript:void(0);" class="cat_btn" alt = ' . get_string('requestforenroll','local_program'). ' title = ' .get_string('requestforenroll','local_program'). ' onclick="(function(e){ require(\'local_request/requestconfirm\').init({action:\'add\', componentid: '.$programinfo->id.', component:\'program\',componentname:\''.$programinfo->name.'\'}) })(event)" ><button class="cat_btn viewmore_btn"><i class="fa fa-pencil-square-o" aria-hidden="true"></i>'.get_string('requestforenroll','local_program').'</button></a>';
        }
        else {
            $enrollmentbtn ='-';
        }
        $details['manage'] = 0;
        $details['status'] = ($record->completion_status == 1) ? get_string('completed', 'local_onlinetests'):get_string('pending', 'local_onlinetests');
        $details['enrolled'] = ($record->timecreated) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->timecreated): $enrollmentbtn;
        $details['completed'] = ($record->completiondate) ? \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->completiondate): '-';
        $details['noofsessions'] = ($sessioncount) ? $sessioncount: '-' ;
        $details['attendance'] = $record->attended_sessions;
    }
    return $details;
}
function local_program_request_dependent_query($aliasname){
    $returnquery = " WHEN ({$aliasname}.compname LIKE 'program') THEN (SELECT name from {local_program} WHERE id = {$aliasname}.componentid) ";
    return $returnquery;
}
function check_programenrol_pluginstatus($value){
    global $DB ,$OUTPUT ,$CFG;
    $enabled_plugins = $DB->get_field('config', 'value', array('name' => 'enrol_plugins_enabled'));
    $enabled_plugins =  explode(',',$enabled_plugins);
    $enabled_plugins = in_array('program',$enabled_plugins);

if(!$enabled_plugins){

    if(is_siteadmin()){
        $url = $CFG->wwwroot.'/admin/settings.php?section=manageenrols';
        $enable = get_string('enableplugin','local_program',$url);
        echo $OUTPUT->notification($enable,'notifyerror');
    }
    else{
        $enable = get_string('manageplugincapability','local_program');
        echo $OUTPUT->notification($enable,'notifyerror');
     }
   }    
}
