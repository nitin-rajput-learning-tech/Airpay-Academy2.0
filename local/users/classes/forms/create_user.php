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

namespace local_users\forms;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
require_once($CFG->dirroot . '/local/users/lib.php');
use moodleform;
use context_system;
use costcenter;
use events;
use context_user;
use local_users\functions\userlibfunctions as userlib;

class create_user extends moodleform {
    public $formstatus;
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '',
     $attributes = null, $editable = true, $formdata = null) {

        $this->formstatus = array(
            'generaldetails' => get_string('generaldetails', 'local_users'),
            'otherdetails' => get_string('otherdetails', 'local_users'),
            'contactdetails' => get_string('contactdetails', 'local_users'),
            );
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }
    public function definition() {
        global $USER, $CFG, $DB, $PAGE;

        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

        $costcenter = new costcenter();
        $mform = $this->_form;
        $form_status = $this->_customdata['form_status'];
        // $id = $this->_customdata['id'];
        $id = $this->_customdata['id'] > 0 ? $this->_customdata['id'] : 0;
        $editoroptions = $this->_customdata['editoroptions'];
        $filemanageroptions = $this->_customdata['filemanageroptions'];
        $admin = $this->_customdata['admin'];
        $open_positionid = $this->_customdata['open_positionid'];
        $open_domainid = $this->_customdata['open_domainid'];
        // $this->_form->_attributes['id'] = 'create_user_form';
        // $this->_form->_attributes['class'] = 'create_user_form';
        if ($form_status == 0) {


            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,null, true, 'local_users', $categorycontext, $multiple = false);


            $count = count((array)$costcenters);
            $mform->addElement('hidden', 'count', $count);
            $mform->setType('count', PARAM_INT);
            $mform->addElement('text', 'username', get_string('username', 'local_users'));
            $mform->addRule('username', get_string('usernamerequired', 'local_users'), 'required', null, 'client');
            $mform->setType('username', PARAM_RAW);

            $auths = \core_component::get_plugin_list('auth');
            $enabled = get_string('pluginenabled', 'core_plugin');
            $disabled = get_string('plugindisabled', 'core_plugin');
            $authoptions = array();
            $cannotchangepass = array();
            $cannotchangeusername = array();
            foreach ($auths as $auth => $unused) {
                if ($auth == 'nologin') 
                    continue;
                    $authinst = get_auth_plugin($auth);
                

                if (!$authinst->is_internal()) {
                      $cannotchangeusername[] = $auth;
                }

                $passwordurl = $authinst->change_password_url();
                if (!($authinst->can_change_password() && empty($passwordurl))) {
                    if ($userid < 1 && $authinst->is_internal()) {
                          // This is unlikely but we can not create account without password.
                          // When plugin uses passwords, we need to set it initially at least.
                    } else {
                        $cannotchangepass[] = $auth;
                    }
                }
                if (is_enabled_auth($auth)) {

                    $authoptions[$auth] = get_string('pluginname', "auth_{$auth}");
                }
            }
            $mform->addElement('select', 'auth', get_string('authmethod', 'local_users'), $authoptions);
            $mform->addHelpButton('auth', 'chooseauthmethod', 'auth');

            $mform->addElement('passwordunmask', 'password', get_string('password'), 'size="20"');
            $mform->addHelpButton('password', 'newpassword');
            $mform->setType('password', PARAM_RAW);
            $mform->hideIf('password', 'createpassword', 'eq', 1);
            $mform->hideIf('password', 'auth', 'in', $cannotchangepass);
            $mform->addElement('advcheckbox', 'preference_auth_forcepasswordchange', get_string('forcepasswordchange'));
            $mform->hideIf('preference_auth_forcepasswordchange', 'auth', 'in', $cannotchangepass);
            $mform->addElement('advcheckbox', 'createpassword', get_string('createpassword', 'auth'));
            $mform->hideIf('createpassword', 'auth', 'in', $cannotchangepass);
            $mform->disabledIf('createpassword', 'auth', 'in', $cannotchangepass);
            $options = array(
                '0' => 'Select Prefix',
                '1' => 'Mr',
                '2' => 'Mrs',
                '3' => 'Ms'
            );
            $select = $mform->addElement('select', 'open_prefix', get_string('prefix','local_users'), $options);
            $mform->addElement('text', 'firstname', get_string('firstname', 'local_users'));
            $mform->addRule('firstname', get_string('errorfirstname', 'local_users'), 'required', null, 'client');
            $mform->setType('firstname', PARAM_RAW);

            $mform->addElement('text', 'lastname', get_string('lastname', 'local_users'));
            $mform->addRule('lastname', get_string('errorlastname', 'local_users'), 'required', null, 'client');
            $mform->setType('lastname', PARAM_RAW);

            $genderarray=array();
            $genderarray[] = $mform->createElement('radio', 'gender', '', get_string('male','local_users'), 0, $attributes);
            $genderarray[] = $mform->createElement('radio', 'gender', '', get_string('female','local_users'), 1, $attributes);
            $genderarray[] = $mform->createElement('radio', 'gender', '', get_string('other', 'local_users'), 2, $attributes);
            $mform->addGroup($genderarray, 'gendergr', get_string('gender', 'local_users'), array(' '), false);

            $mform->addElement('text', 'email', get_string('email', 'local_users'));
            $mform->addRule('email', get_string('emailerror', 'local_users'), 'required', null, 'client');
            $mform->setType('email', PARAM_RAW);


            $mform->addElement('text', 'open_employeeid', get_string('serviceid', 'local_users'));
            $mform->addRule('open_employeeid',  get_string('employeeidrequired', 'local_users'),  'required',  '',  'client');
            $mform->addRule('open_employeeid',  get_string('open_employeeiderror', 'local_users'),
                'alphanumeric', 'extraruledata',  'client');

            $mform->setType('open_employeeid', PARAM_RAW);
            $open_costcenterid = $this->_customdata['open_costcenterid'] > 0 ?
                             $this->_customdata['open_costcenterid'] : $this->_ajaxformdata['open_costcenterid'];


            $reportingmanger[0] =get_string('select_reportingto', 'local_users');
            if($id){
                $reportingmanger += $DB->get_records_sql_menu("SELECT id, concat(firstname,' ',lastname) FROM {user} WHERE id = (SELECT open_supervisorid FROM {user} WHERE id = :id) AND deleted = :deleted AND suspended = :suspended", ['id' => $id, 'deleted' => 0, 'suspended' => 0]);
            }else{
                $reportingmanger += $DB->get_records_sql_menu("SELECT id, concat(firstname,' ',lastname) FROM {user} WHERE  deleted = :deleted AND suspended = :suspended",['deleted' => 0, 'suspended' => 0]);
            }
            $supervisoroptions = array(
            'class' => 'supervisor_select',
            'id' => 'id_supervisor_select',
            'data-contextid' => $categorycontext->id,
            'data-parentclass' => 'open_costcenterid_select',
            'data-selectstring' => get_string('select_reportingto', 'local_users'),
            'data-options' => json_encode(array('id' => $id, 'parentid' => $open_costcenterid, 'enableallfield' => false)),
            'data-class' => 'supervisor_select',
            'multiple' => false,
            'ajax' => 'local_costcenter/form-options-selector',
            'data-action' => 'user_supervisor_selector',
            'noselectionstring'=>get_string('noselection', 'form')
            );
            $select = $mform->addElement('autocomplete', 'open_supervisorid',
                    get_string('supervisor', 'local_users'),
                    $reportingmanger, $supervisoroptions);
            $mform->setType('open_supervisorid', PARAM_RAW);
                // End of if($form_status = 0) condition.
        } else if ($form_status == 1) {

            // $mform->addElement('select', 'lang', get_string(
            //     'preferredlanguage', 'local_users'),
            //     get_string_manager()->get_list_of_translations());
            // $mform->setDefault('lang', $CFG->lang);

            $mform->addElement('text', 'open_designation', get_string('designation', 'local_users'));
            $mform->setType('open_designation', PARAM_RAW);
            $mform->addHelpButton('open_designation', 'designation', 'local_users');

             // local_users_get_userprofile_fields($mform, $this->_ajaxformdata, $this->_customdata, false, 'local_users', $categorycontext, $multiple = false);

            $mform->addElement('text', 'open_employmenttype', get_string('employmenttype', 'local_users'));
            $mform->setType('open_employmenttype', PARAM_RAW);
            $mform->addHelpButton('open_employmenttype', 'employmenttype', 'local_users');


            $mform->addElement('text', 'open_region', get_string('region', 'local_users'));
            $mform->setType('open_region', PARAM_RAW);
            $mform->addHelpButton('open_region', 'region', 'local_users');


            $mform->addElement('text', 'open_grade', get_string('grade', 'local_users'));
            $mform->setType('open_grade', PARAM_RAW);
            $mform->addHelpButton('open_grade', 'grade', 'local_users');


            $mform->addElement('date_selector', 'open_dateofbirth',get_string('dateofbirth','local_users'),
            array('optional' => true));
            $mform->addElement('date_selector', 'open_joindate',get_string('joiningdate','local_users'),
         array('optional' => true));
        } else if ($form_status == 2) {

            $mform->addElement('text', 'phone1', get_string('contactno', 'local_users'));
            $mform->addRule('phone1', get_string('numeric', 'local_users'), 'numeric', null, 'client');
            $mform->addRule('phone1', get_string('phoneminimum', 'local_users'), 'minlength', 10, 'client');
            $mform->addRule('phone1', get_string('phonemaximum', 'local_users'), 'maxlength', 10, 'client');
            $mform->setType('phone1', PARAM_RAW);


            $mform->addElement('hidden', 'timezone');
            $mform->setType('timezone', \core_user::get_property_type('timezone'));

            // if (isset($CFG->forcetimezone) && $CFG->forcetimezone != 99) {
            //     $choices = \core_date::get_list_of_timezones($CFG->forcetimezone);
            //     $mform->addElement('static', 'forcedtimezone', get_string('timezone'), $choices[$CFG->forcetimezone]);
            //     $mform->addElement('hidden', 'timezone');
            //     $mform->setType('timezone', \core_user::get_property_type('timezone'));
            // } else {
            //     $userrecord = \core_user::get_user($id);
            //     $choices = \core_date::get_list_of_timezones($userrecord->timezone, true);
            //     $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
            // }
            $mform->addElement('static', 'currentpicture', get_string('currentpicture'));
            $mform->addElement('checkbox', 'deletepicture', get_string('delete'));
            $mform->setDefault('deletepicture', 0);
            $mform->addElement('filepicker', 'imagefile',
                    get_string('newpicture'), null, array(
                    'accepted_types' => array('.jpg', '.jpeg', '.png')));

            $mform->addHelpButton('imagefile', 'newpicture');
        }
        // End of form status = 2 condition.
        // $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', 'id', $id, array('id' => 'userid'));
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id',  $id);
        $mform->addElement('hidden', 'form_status');
        $mform->setType('form_status', PARAM_INT);
        $mform->setDefault('form_status',  $form_status);
        $mform->disable_form_change_checker();

    }
    public function definition_after_data() {
        global $USER, $CFG, $DB, $OUTPUT;
        $mform = & $this->_form;
        $form_status = $this->_customdata['form_status'];
        if ($userid = $mform->getElementValue('id')) {
            $user = $DB->get_record('user', array('id' => $userid));
        } else {
            $user = false;
        }
        // Print picture.
        if (empty($USER->newadminuser)) {
            if ($user) {
                $context = context_user::instance($user->id, MUST_EXIST);
                $fs = get_file_storage();
                $hasuploadedpicture = ($fs->file_exists($context->id,
                    'user', 'icon', 0, '/', 'f2.png') || $fs->file_exists(
                    $context->id, 'user', 'icon', 0, '/', 'f2.jpg'));
                if (!empty($user->picture) && $hasuploadedpicture) {
                    $imagevalue = $OUTPUT->user_picture($user, array('courseid' => SITEID, 'size' => 64, 'link' => false));
                } else {
                    $imagevalue = get_string('none');
                }
            } else {
                $imagevalue = get_string('none');
            }
            if ($form_status == 2) {
                $imageelement = $mform->getElement('currentpicture');
                $imageelement->setValue($imagevalue);
            }
            if ($user && $mform->elementExists('deletepicture') && !$hasuploadedpicture) {
                $mform->removeElement('deletepicture');
            }
        }
    }

    public function validation($data, $files) {
        $errors = array();
        global $DB, $CFG, $USER;
        $sub_data = data_submitted();
        $errors = parent::validation($data, $files);
        $email = $data['email'];
        $employeeid = $data['open_employeeid'];
        $id = $data['id'];
        $uname = $data['username'];
        $form_status = $data['form_status'];
        if ($form_status == 0) {// As these fields are in only form part 1(form_status=0).
            $username = $data['username'];
            $firstname = $data['firstname'];
            $lastname = $data['lastname'];
            if (empty(trim($username))) {
                $errors['username'] = get_string('valusernamerequired', 'local_users');
            }
            if (empty(trim($firstname))) {
                $errors['firstname'] = get_string('valfirstnamerequired', 'local_users');
            }
            if (empty(trim($lastname))) {
                $errors['lastname'] = get_string('vallastnamerequired', 'local_users');
            }
            // OL72 issue department as mandatory.
            // $department = $data['open_departmentid'];
            // if (!$department) {
            //     $errors['open_departmentid'] = get_string('nodepartmenterror', 'local_users');
            // }
                // OL72 ends here.
            if (get_config('core', 'allowaccountssameemail') == 0) {
                if (!empty($data['email']) && ($user = $DB->get_record(
                    'user', array('email' => $data['email']),
                    '*', IGNORE_MULTIPLE))) {
                    if (empty($data['id']) || $user->id != $data['id']) {
                        $errors['email'] = get_string('emailexists', 'local_users');
                    }
                }
            }
            if(!empty($data['email'])){
                if (!validate_email($data['email'])) {
                    $errors['email'] = get_string('emailerror', 'local_users');
                }
                if (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $data['email'])) {
                    $errors['email'] = get_string('invalidemail', 'local_users');
                }
                if ($data['email'] != strtolower($data['email'])) {
                    $errors['email'] = get_string('onlylowercase', 'local_users');
                }
            }
            $auths = \core_component::get_plugin_list('auth');
            $cannotchangepass = [];
            foreach ($auths as $auth => $unused) {
                $authinst = get_auth_plugin($auth);
                $passwordurl = $authinst->change_password_url();
                if (!($authinst->can_change_password() && empty($passwordurl))) {
                    if ($authinst->is_internal()) {
                            // This is unlikely but we can not create account without password.
                            // when plugin uses passwords, we need to set it initially at least.
                    } else {
                        $cannotchangepass[] = $auth;
                    }
                }
            }
            if (!$data['createpassword']) {
                if (!empty($data['password']) && !in_array($data['auth'], $cannotchangepass)) {

                    $errmsg = ''; // Prevent eclipse warning.
                    if (!check_password_policy($data['password'], $errmsg)) {
                        $errors['password'] = $errmsg;
                    }
                } else if (empty($data['id']) &&
                    $data['createpassword'] != 1 && !in_array(
                    $data['auth'], $cannotchangepass) && empty(
                    $data['password'])) {
                    $errors['password'] = get_string('passwordrequired', 'local_users');
                }
            }
            if (strtolower($uname) != $uname) {
                $errors['username'] = get_string('lowercaseunamerequired', 'local_users');
            }
            if ($user = $DB->get_record('user', array('username' => $data['username']), '*', IGNORE_MULTIPLE)) {
                if (empty($data['id']) || $user->id != $data['id']) {
                    $errors['username'] = get_string('unameexists', 'local_users');
                }
            }
            $userempsql = "SELECT u.id, u.open_path FROM {user} AS u WHERE u.open_employeeid = :open_employeeid ";
            if ($user = $DB->get_record_sql($userempsql, array(
                'open_employeeid' => $employeeid))) {
                if (!isset($data['id']) ||
                    $user->id != $data['id']) {
                    $errors['open_employeeid'] = get_string('open_employeeidexist', 'local_users');
                }

            }
        }
        if ($form_status == 2) { // As these fields are in only form part 3(form_status=2).
            $phone = $data['phone1'];
            if ($phone) {
                if (!is_numeric($phone)) {
                    $errors['phone1'] = get_string('numeric', 'local_users');
                } else if ($phone < 1000000000 && $phone) {
                    $errors['phone1'] = get_string('phonenumvalidate', 'local_users');
                }
            }
        }
        return $errors;
    }
}

