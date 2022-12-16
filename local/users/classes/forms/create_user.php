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
        $id = $this->_customdata['id'];
        $editoroptions = $this->_customdata['editoroptions'];
        $filemanageroptions = $this->_customdata['filemanageroptions'];
        $admin = $this->_customdata['admin'];
        $open_positionid = $this->_customdata['open_positionid'];
        $open_domainid = $this->_customdata['open_domainid'];
        if ($form_status == 0) {
            // if (is_siteadmin($USER->id) || has_capability('local/users:manage', $categorycontext)) {
            //     $sql = "select id,fullname from {local_costcenter} where visible =
    		// 	 :visible and parentid=:parentid ";
            //     $costcenters = $DB->get_records_sql($sql, array('visible' => 1, 'parentid' => 0));
            // }

            // if (is_siteadmin($USER) || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
            //     $organizationlist = array(null => get_string('select_org', 'local_users'));
            //     foreach ($costcenters as $scl) {
            //         $organizationlist[$scl->id] = $scl->fullname;
            //     }
            //     $mform->addElement('select', 'open_costcenterid', get_string('organization', 'local_users'),
            //      $organizationlist);
            //     $mform->addRule('open_costcenterid', get_string('errororganization', 'local_users'), 'required',
            //      null, 'client');
            // } else if (has_capability('local/costcenter:manage_ownorganization', $categorycontext) || has_capability('local/costcenter:manage_owndepartments', $categorycontext)) {
            //     $user_dept = $DB->get_field('user', 'open_costcenterid', array('id' => $USER->id));
            //     $mform->addElement('hidden', 'open_costcenterid', null);
            //     $mform->setType('open_costcenterid', PARAM_ALPHANUM);
            //     $mform->setConstant('open_costcenterid', $user_dept);
            // }
            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, 'local_users', $categorycontext, $multiple = false);
            $count = count($costcenters);
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
            $mform->addElement('advcheckbox', 'createpassword', get_string('createpassword', 'auth'));
            $mform->disabledIf('createpassword', 'auth', 'in', $cannotchangepass);
            $mform->addElement('text', 'firstname', get_string('firstname', 'local_users'));
            $mform->addRule('firstname', get_string('errorfirstname', 'local_users'), 'required', null, 'client');
            $mform->setType('firstname', PARAM_RAW);

            $mform->addElement('text', 'lastname', get_string('lastname', 'local_users'));
            $mform->addRule('lastname', get_string('errorlastname', 'local_users'), 'required', null, 'client');
            $mform->setType('lastname', PARAM_RAW);

            $mform->addElement('text', 'email', get_string('email', 'local_users'));
            $mform->addRule('email', get_string('emailerror', 'local_users'), 'required', null, 'client');
            $mform->setType('email', PARAM_RAW);

            // if (is_siteadmin() || has_capability('local/costcenter:manage_ownorganization',
            //     $categorycontext) || has_capability('local/costcenter:
            // 	manage_multiorganizations', $categorycontext)) {
            //     $departmentslist = array(get_string('select_dept', 'local_users'));
            //     if ($id > 0) {
            //         $existing_costcenter = $DB->get_field('user', 'open_costcenterid', array('id' => $id));
            //     }
            //     if ($id > 0 && $existing_costcenter && !isset($this->_ajaxformdata['open_costcenterid'])) {
            //         $open_costcenterid = $existing_costcenter;
            //     } else {
            //         $open_costcenterid = $this->_ajaxformdata['open_costcenterid'];
            //     }
            //     if (!empty($open_costcenterid) && is_siteadmin()) {
            //         $departments = userlib::find_departments_list($open_costcenterid);
            //         foreach ($departments as $depart) {
            //             $departmentslist[$depart->id] = $depart->fullname;
            //         }
            //     } else if (!is_siteadmin() && has_capability('local/costcenter:view', $categorycontext)) {
            //         $departments = userlib::find_departments_list($USER->open_costcenterid);
            //         foreach ($departments as $depart) {
            //             $departmentslist[$depart->id] = $depart->fullname;
            //         }
            //     }
            //     $mform->addElement('select', 'open_departmentid', get_string('department', 'local_users'), $departmentslist);
            //     $mform->addHelpButton('open_departmentid', 'department', 'local_users');
            //     $mform->addRule('open_departmentid', get_string('departmentrequired', 'local_users'), 'required', null, 'client');
            // } else {
            //     $departmentid = $DB->get_field('user', 'open_departmentid', array('id' => $USER->id));
            //     $mform->addElement('hidden', 'open_departmentid');
            //     $mform->setType('open_departmentid', PARAM_INT);
            //     $mform->setConstant('open_departmentid', $departmentid);
            // }
            $mform->addElement('text', 'open_employeeid', get_string('serviceid', 'local_users'));
            $mform->addRule('open_employeeid',  get_string('employeeidrequired', 'local_users'),  'required',  '',  'client');
            $mform->addRule('open_employeeid',  get_string('open_employeeiderror', 'local_users'),
                'alphanumeric', 'extraruledata',  'client');

            $mform->setType('open_employeeid', PARAM_RAW);
            $open_costcenterid = $this->_customdata['org'] > 0 ?
                             $this->_customdata['org'] : $this->_ajaxformdata['open_costcenterid'];
            // if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $categorycontext)) {
            //     $reporting = userlib::find_supervisor_list($USER->open_costcenterid, $id);
            // } else if (!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $categorycontext)) {
            //     $reporting = userlib::find_dept_supervisor_list($USER->open_departmentid, $id);
            // } else if ($open_costcenterid > 0) {
            //     $reporting = userlib::find_supervisor_list($open_costcenterid, $id);
            // } else if ($id > 0) {
            //     $costcenterid = $DB->get_field('user', 'open_costcenterid',
            //      array('id' => $id));
            //     $reporting = userlib::find_supervisor_list($costcenterid, $id);
            // }
            $reporting = userlib::find_supervisor_list($id);
            $reportingmanger = array(null => get_string('select_reportingto', 'local_users'));
            foreach ($reporting as $report) {
                $reportingmanger[$report->id] = $report->username;
            }
            $select = $mform->addElement('select', 'open_supervisorid',
                    get_string('supervisor', 'local_users'),
                    $reportingmanger, array('id' => 'open_supervisorid'));
            $mform->setType('open_supervisorid', PARAM_RAW);
                // End of if($form_status = 0) condition.
        } else if ($form_status == 1) {
                    // Commented by sarath for removing subdepartments concept in Bizz LMS.
            $userrecord = $DB->get_record('user', array('id' => $id));
            $subdepartmentlist = array(null => get_string('select_subdept', 'local_users'));
            if (!empty($userrecord->open_departmentid)) {
                $subdepartments = userlib::find_subdepartments_list($userrecord->open_departmentid);
                foreach ($subdepartments as $subdepartment) {
                    $subdepartmentlist[$subdepartment->id] = $subdepartment->fullname;
                }
            }
            $mform->addElement('select', 'open_subdepartment', get_string('subdepartment', 'local_users'), $subdepartmentlist);
            $mform->addHelpButton('subdepartment', 'subdepartment', 'local_users');

            $mform->addElement('select', 'lang', get_string(
                'preferredlanguage', 'local_users'),
                get_string_manager()->get_list_of_translations());
            $mform->setDefault('lang', $CFG->lang);
            $mform->addElement('text', 'open_designation', get_string('designation', 'local_users'));
            $mform->setType('open_designation', PARAM_RAW);
            $mform->addHelpButton('open_designation', 'designation', 'local_users');
            $mform->addElement('text', 'open_hrmsrole', get_string('hrmrole', 'local_users'));
            $mform->setType('open_hrmsrole', PARAM_RAW);
            $mform->addHelpButton('open_hrmsrole', 'hrmrole', 'local_users');
            $mform->addElement('text', 'city', get_string('open_location', 'local_users'));
            $mform->setType('city', PARAM_RAW);
            $mform->addHelpButton('city', 'open_location', 'local_users');
            $mform->addElement('text', 'open_level', get_string('open_level', 'local_users'));
            $mform->setType('open_level', PARAM_RAW);
            $mform->addHelpButton('open_level', 'open_level', 'local_users');
            // OL-2167 jira issue.
            // End of if($form_status = 1) condition.
        } else if ($form_status == 2) {
            $mform->addElement('text', 'phone1', get_string('contactno', 'local_users'));
            $mform->addRule('phone1', get_string('numeric', 'local_users'), 'numeric', null, 'client');
            $mform->addRule('phone1', get_string('phoneminimum', 'local_users'), 'minlength', 10, 'client');
            $mform->addRule('phone1', get_string('phonemaximum', 'local_users'), 'maxlength', 15, 'client');
            $mform->setType('phone1', PARAM_RAW);
            $choices = get_string_manager()->get_list_of_countries();
            $choices = array('' => get_string('selectacountry').'...') + $choices;
            $mform->addElement('select', 'country', get_string('selectacountry'), $choices);
            if (empty($CFG->country)) {
                $mform->setDefault('country', $USER->country);
            } else {
                $mform->setDefault('country', \core_user::get_property_default('country'));
            }
            $mform->setAdvanced('country');
            if (isset($CFG->forcetimezone) && $CFG->forcetimezone != 99) {
                $choices = \core_date::get_list_of_timezones($CFG->forcetimezone);
                $mform->addElement('static', 'forcedtimezone', get_string('timezone'), $choices[$CFG->forcetimezone]);
                $mform->addElement('hidden', 'timezone');
                $mform->setType('timezone', \core_user::get_property_type('timezone'));
            } else {
                $userrecord = \core_user::get_user($id);
                $choices = \core_date::get_list_of_timezones($userrecord->timezone, true);
                $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
            }
            $mform->addElement('static', 'currentpicture', get_string('currentpicture'));
            $mform->addElement('checkbox', 'deletepicture', get_string('delete'));
            $mform->setDefault('deletepicture', 0);
            $mform->addElement('filepicker', 'imagefile',
                    get_string('newpicture'), null, array(
                    'accepted_types' => array('.jpg', '.jpeg', '.png')));

            $mform->addHelpButton('imagefile', 'newpicture');
        }
        // End of form status = 2 condition.
        $mform->addElement('hidden', 'id');
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
            if (!empty($data['email']) && !validate_email($data['email'])) {
                $errors['email'] = get_string('emailerror', 'local_users');
            }
            if (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $data['email'])) {
                $errors['email'] = get_string('invalidemail', 'local_users');
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
            if ($user = $DB->get_record('user', array(
                'open_employeeid' => $employeeid,
                'open_costcenterid' => $data['open_costcenterid']))) {
                if ($user->open_costcenterid ==
                    $data['open_costcenterid']) {
                    if (!isset($data['id']) ||
                    $user->id != $data['id']) {
                        $errors['open_employeeid'] = get_string('open_employeeidexist', 'local_users');
                    }
                }
            }
        }
        if ($form_status == 2) { // As these fields are in only form part 3(form_status=2).
            $phone = $data['phone1'];
            if ($phone) {
                if (!is_numeric($phone)) {
                    $errors['phone1'] = get_string('numeric', 'local_users');
                } else if (($phone < 999999999 || $phone > 10000000000) && $phone) {
                    $errors['phone1'] = get_string('phonenumvalidate', 'local_users');
                }
            }
        }
        return $errors;
    }
}

