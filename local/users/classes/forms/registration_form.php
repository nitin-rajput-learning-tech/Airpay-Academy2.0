<?php
namespace local_users\forms;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');
use moodleform;
use core_user;
use local_users\functions\userlibfunctions as userlib;

class registration_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
        $policy = get_config('local_users','privacypolicy');
        $termscondition = get_config('local_users','termscondition');
 
        $title = get_string('registrationtitle', 'local_users');
        $mform = $this->_form; // Don't forget the underscore! 
        $mform->addElement('html', '<div class="card-title">
                        <h3 class="signup-title text-center p-3">'.$title.'</h3>
                       
                    </div> ');
        $mform->addElement('html','<div class="signup_form"> <div class="row">
            <div class="col-md-6 px-5">');

        $mform->addElement('text', 'firstname', get_string('firstname', 'local_users'));
        $mform->addRule('firstname', get_string('errorfirstname', 'local_users'), 'required', null, 'client');
        $mform->setType('firstname', PARAM_RAW);

        $mform->addElement('text', 'lastname', get_string('lastname', 'local_users'));
        $mform->addRule('lastname', get_string('errorlastname', 'local_users'), 'required', null, 'client');
        $mform->setType('lastname', PARAM_RAW);

        $mform->addElement('text', 'username', get_string('username', 'local_users'));
        $mform->addRule('username', get_string('usernamerequired', 'local_users'), 'required', null, 'client');
        $mform->setType('username', PARAM_RAW);

        $mform->addElement('passwordunmask', 'password', get_string('password'), 'size="20"');
        $mform->setType('password', PARAM_RAW);

        $mform->addElement('text', 'email', get_string('email', 'local_users'));
        $mform->addRule('email', get_string('erroremail', 'local_users'), 'required', null, 'client');
        $mform->setType('email', PARAM_RAW);

        $mform->addElement('text', 'phone1', get_string('contactno', 'local_users'));
        $mform->addRule('phone1', get_string('numeric', 'local_users'), 'numeric', null, 'client');
        $mform->addRule('phone1', get_string('phoneminimum', 'local_users'), 'minlength', 10, 'client');
        $mform->addRule('phone1', get_string('phonemaximum', 'local_users'), 'maxlength', 10, 'client');
        $mform->addRule('phone1', get_string('errorphoneno', 'local_users'), 'required', null, 'client');
        $mform->setType('phone1', PARAM_RAW);

        $choices = get_string_manager()->get_list_of_countries();
        $choices = array('' => get_string('selectacountry') . '...') + $choices;
        $mform->addElement('select', 'country', get_string('selectacountry'), $choices);
        if (!empty($CFG->country)) {
            $mform->setDefault('country', core_user::get_property_default('country'));
        }
        $mform->addRule('country', get_string('countryrequired', 'local_users'), 'required', null, 'client');

        $mform->addElement('date_selector', 'open_dateofbirth',get_string('dateofbirth','local_users'));
        $mform->addRule('open_dateofbirth', get_string('dateofbirthrequired', 'local_users'), 'required', null, 'client');

        $mform->addElement('html','</div>
            <div class="col-md-6 px-5">');

        $genderarray=array();
        $genderarray[] = $mform->createElement('radio', 'gender', '', get_string('male','local_users'), 0, $attributes);
        $genderarray[] = $mform->createElement('radio', 'gender', '', get_string('female','local_users'), 1, $attributes);
        $genderarray[] = $mform->createElement('radio', 'gender', '', get_string('other', 'local_users'), 2, $attributes);
        $mform->addGroup($genderarray, 'gender', get_string('gender', 'local_users'), array(' '), false);
        // $mform->addElement('text', 'gender', get_string('gender', 'local_users'));


        $translations = get_string_manager()->get_list_of_translations();
        $mform->addElement('select', 'lang', get_string('preferredlanguage'), $translations);

        $mform->addElement('text', 'open_educationlevel', get_string('education_level', 'local_users'));

        $mform->addElement('text', 'open_fieldwork', get_string('fieldwork', 'local_users'));

        $mform->addElement('text', 'open_jobtitle', get_string('jobtitle', 'local_users'));

        $mform->addElement('text', 'open_company', get_string('companyname', 'local_users'));

        if($policy){
            $policystring=get_string('privacypolicy', 'local_users');
            $policytext=get_string('policystring', 'local_users');
             $policy = $policytext.'<a target="_blank" href='.$policy.'>'.$policystring.'</a>';          
            
            $mform->addElement('checkbox', 'open_privacypolicy', $policy);

            $mform->addRule('open_privacypolicy', get_string('privacypolicyrequired', 'local_users'), 'required', null, 'client');

        }else
        {
            $mform->addElement('html', '<a>');            
            $mform->addElement('checkbox', 'open_privacypolicy', get_string('policystring', 'local_users').get_string('privacypolicy','local_users'));
            $mform->addElement('html', '</a>');            
            $mform->addRule('open_privacypolicy', get_string('privacypolicyrequired', 'local_users'), 'required', null, 'client');

        }

        if($termscondition)
        {
            $termsstring=get_string('termscondition', 'local_users');
            $termstext=get_string('termsconditionstring', 'local_users');
            $termscondition = $termstext.'<a target="_blank" href='.$termscondition.'>'.$termsstring.'</a>';          
            $mform->addElement('checkbox', 'open_termscondition',$termscondition);
            
            $mform->addRule('open_termscondition', get_string('termsconditionrequired', 'local_users'), 'required', null, 'client');
        }else
        {
            $mform->addElement('html', '<a>');            
            $mform->addElement('checkbox', 'open_termscondition', get_string('termsconditionstring', 'local_users').get_string('termscondition','local_users'));
            $mform->addElement('html', '</a>');            
            $mform->addRule('open_termscondition', get_string('termsconditionrequired', 'local_users'), 'required', null, 'client');

        }

        $this->add_action_buttons($cancel = false,get_string('submit', 'local_users'));

        $mform->addElement('html','</div></div> </div>
            ');

    }
    //Custom validation should be added here
   public function validation($data, $files) {
        $errors = array();
        global $DB, $CFG;
        $uname = $data['username'];
            $email = $DB->get_record('user', array('email' =>$data['email']));

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
            if($data['email'] == $email->email && $data['email'] != "")
            {
                $errors['email'] = get_string('emailexists', 'local_users');
            }

            if (strtolower($uname) != $uname) {
                $errors['username'] = get_string('lowercaseunamerequired', 'local_users');
            }
            if ($user = $DB->get_record('user', array('username' => $data['username']), '*', IGNORE_MULTIPLE)) {
                if (empty($data['id']) || $user->id != $data['id']) {
                    $errors['username'] = get_string('unameexists', 'local_users');
                }
            }

            $phone = $data['phone1'];
            if ($phone) {
                if (!is_numeric($phone)) {
                    $errors['phone1'] = get_string('numeric', 'local_users');
                } else if ($phone < 1000000000 && $phone) {
                    $errors['phone1'] = get_string('phonenumvalidate', 'local_users');
                }
            }

            if($data['country'] == 0)
            {
                $errors['country'] = get_string('countryrequired', 'local_users');
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

        return $errors;
    }
}
