<?php
namespace local_users\forms;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');
use moodleform;
use core_user;
use local_users\functions\userlibfunctions as userlib;

class registration_form extends moodleform {
    public function definition() {
        global $CFG;
        $policy = get_config('local_users', 'privacypolicy');
        $termscondition = get_config('local_users', 'termscondition');

        $title = get_string('registrationtitle', 'local_users');
        $mform = $this->_form;

        // Single-column simplified layout.
        $mform->addElement('html', '<div class="signup_form signup_form--simple">');
        $mform->addElement('html', '<h3 class="signup-title text-center mb-3">' . $title . '</h3>');
        $mform->addElement('html', '<p class="text-center text-muted mb-4" style="font-size:0.85rem;">Your email will be used as your login username.</p>');

        // First Name.
        $mform->addElement('text', 'firstname', get_string('firstname', 'local_users'));
        $mform->addRule('firstname', get_string('errorfirstname', 'local_users'), 'required', null, 'client');
        $mform->setType('firstname', PARAM_RAW);

        // Last Name.
        $mform->addElement('text', 'lastname', get_string('lastname', 'local_users'));
        $mform->addRule('lastname', get_string('errorlastname', 'local_users'), 'required', null, 'client');
        $mform->setType('lastname', PARAM_RAW);

        // Email (also used as username).
        $mform->addElement('text', 'email', get_string('email', 'local_users'));
        $mform->addRule('email', get_string('erroremail', 'local_users'), 'required', null, 'client');
        $mform->setType('email', PARAM_RAW);

        // Password.
        $mform->addElement('passwordunmask', 'password', get_string('password'), 'size="20"');
        $mform->setType('password', PARAM_RAW);

        // Mobile Number.
        $mform->addElement('text', 'phone1', get_string('contactno', 'local_users'));
        $mform->addRule('phone1', get_string('numeric', 'local_users'), 'numeric', null, 'client');
        $mform->addRule('phone1', get_string('phoneminimum', 'local_users'), 'minlength', 10, 'client');
        $mform->addRule('phone1', get_string('phonemaximum', 'local_users'), 'maxlength', 10, 'client');
        $mform->addRule('phone1', get_string('errorphoneno', 'local_users'), 'required', null, 'client');
        $mform->setType('phone1', PARAM_RAW);

        // Privacy Policy checkbox.
        if ($policy) {
            $policystring = get_string('privacypolicy', 'local_users');
            $policytext = get_string('policystring', 'local_users');
            $policylabel = $policytext . '<a target="_blank" href=' . $policy . '>' . $policystring . '</a>';
            $mform->addElement('checkbox', 'open_privacypolicy', $policylabel);
            $mform->addRule('open_privacypolicy', get_string('privacypolicyrequired', 'local_users'), 'required', null, 'client');
        } else {
            $mform->addElement('checkbox', 'open_privacypolicy', get_string('policystring', 'local_users') . get_string('privacypolicy', 'local_users'));
            $mform->addRule('open_privacypolicy', get_string('privacypolicyrequired', 'local_users'), 'required', null, 'client');
        }

        // Terms & Conditions checkbox.
        if ($termscondition) {
            $termsstring = get_string('termscondition', 'local_users');
            $termstext = get_string('termsconditionstring', 'local_users');
            $termslabel = $termstext . '<a target="_blank" href=' . $termscondition . '>' . $termsstring . '</a>';
            $mform->addElement('checkbox', 'open_termscondition', $termslabel);
            $mform->addRule('open_termscondition', get_string('termsconditionrequired', 'local_users'), 'required', null, 'client');
        } else {
            $mform->addElement('checkbox', 'open_termscondition', get_string('termsconditionstring', 'local_users') . get_string('termscondition', 'local_users'));
            $mform->addRule('open_termscondition', get_string('termsconditionrequired', 'local_users'), 'required', null, 'client');
        }

        $this->add_action_buttons($cancel = false, get_string('submit', 'local_users'));

        $mform->addElement('html', '</div>');
    }

    public function validation($data, $files) {
        $errors = array();
        global $DB, $CFG;

        // Email validation.
        $email = $DB->get_record('user', array('email' => $data['email']));
        if (!empty($data['email'])) {
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
        if (!empty($data['email']) && !empty($email->email) && $data['email'] == $email->email) {
            $errors['email'] = get_string('emailexists', 'local_users');
        }

        // Check if email-as-username already exists (edge case: old user has this email as their username).
        $email_as_username = strtolower(trim($data['email']));
        if (!empty($email_as_username)) {
            $existing = $DB->get_record('user', array('username' => $email_as_username), '*', IGNORE_MULTIPLE);
            if ($existing && (empty($data['id']) || $existing->id != $data['id'])) {
                $errors['email'] = get_string('emailexists', 'local_users');
            }
        }

        // Phone validation.
        $phone = $data['phone1'];
        if ($phone) {
            if (!is_numeric($phone)) {
                $errors['phone1'] = get_string('numeric', 'local_users');
            } else if ($phone < 1000000000 && $phone) {
                $errors['phone1'] = get_string('phonenumvalidate', 'local_users');
            }
        }

        // Password policy validation.
        $auths = \core_component::get_plugin_list('auth');
        $cannotchangepass = [];
        foreach ($auths as $auth => $unused) {
            $authinst = get_auth_plugin($auth);
            $passwordurl = $authinst->change_password_url();
            if (!($authinst->can_change_password() && empty($passwordurl))) {
                if (!$authinst->is_internal()) {
                    $cannotchangepass[] = $auth;
                }
            }
        }
        if (empty($data['createpassword'])) {
            if (!empty($data['password']) && !in_array($data['auth'] ?? 'manual', $cannotchangepass)) {
                $errmsg = '';
                if (!check_password_policy($data['password'], $errmsg)) {
                    $errors['password'] = $errmsg;
                }
            } else if (empty($data['id']) && empty($data['password'])) {
                $errors['password'] = get_string('passwordrequired', 'local_users');
            }
        }

        return $errors;
    }
}
