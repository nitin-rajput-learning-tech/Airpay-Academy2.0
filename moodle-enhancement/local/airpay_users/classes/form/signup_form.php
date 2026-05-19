<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_users\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * W1-8 (2026-05-16) — Public-tenant signup form.
 *
 * Replaces BizLMS local_users/forms/registration_form. Captures first/last
 * name, email, password (with confirmation), country, language, and a hard
 * ToS-acceptance gate.
 *
 * @package local_airpay_users
 */
class signup_form extends \moodleform {

    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        $mform->addElement('header', 'signupheader',
            get_string('signup_heading', 'local_airpay_users'));

        // Identity block.
        $mform->addElement('text', 'firstname',
            get_string('firstname'), ['size' => 25, 'maxlength' => 100]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');

        $mform->addElement('text', 'lastname',
            get_string('lastname'), ['size' => 25, 'maxlength' => 100]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');

        $mform->addElement('text', 'email',
            get_string('email'), ['size' => 40, 'maxlength' => 100]);
        $mform->setType('email', PARAM_RAW_TRIMMED);  // PARAM_EMAIL strips chars; we validate via validate_email() in service
        $mform->addRule('email', null, 'required', null, 'client');

        // Password block.
        $mform->addElement('passwordunmask', 'password',
            get_string('password'), ['size' => 25, 'maxlength' => 50]);
        $mform->setType('password', PARAM_RAW);
        $mform->addRule('password', null, 'required', null, 'client');
        $mform->addHelpButton('password', 'signup_password', 'local_airpay_users');

        $mform->addElement('passwordunmask', 'password2',
            get_string('signup_password_confirm', 'local_airpay_users'),
            ['size' => 25, 'maxlength' => 50]);
        $mform->setType('password2', PARAM_RAW);
        $mform->addRule('password2', null, 'required', null, 'client');

        // Localisation block.
        $countries = get_string_manager()->get_list_of_countries();
        $mform->addElement('select', 'country',
            get_string('country'), $countries);
        $mform->setDefault('country', 'IN');

        $languages = get_string_manager()->get_list_of_translations();
        $mform->addElement('select', 'lang',
            get_string('language'), $languages);
        $mform->setDefault('lang', $CFG->lang ?? 'en');

        // ToS consent — hard gate per GDPR lawful-basis rules.
        $privacy_url = (new \moodle_url('/local/airpay_users/privacypolicy.php'))
            ->out(false);
        $tos_url = (new \moodle_url('/local/airpay_users/termscondition.php'))
            ->out(false);
        $tos_label = get_string('signup_tos_label', 'local_airpay_users', (object) [
            'tos_url'     => $tos_url,
            'privacy_url' => $privacy_url,
        ]);
        $mform->addElement('advcheckbox', 'agree_tos', '', $tos_label);
        $mform->setType('agree_tos', PARAM_INT);

        // Honeypot field — bots fill all visible fields. CSS-hidden via
        // form-row class; humans never see it.
        $mform->addElement('text', 'honeypot_url', '');
        $mform->setType('honeypot_url', PARAM_RAW_TRIMMED);
        $mform->addElement('html',
            '<style>.fitem_id_honeypot_url { display: none !important; }</style>');

        $this->add_action_buttons(true,
            get_string('signup_submit', 'local_airpay_users'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Honeypot trip → reject silently (well, with a generic message).
        if (!empty($data['honeypot_url'])) {
            $errors['email'] = get_string('signup_generic_error',
                'local_airpay_users');
            return $errors;
        }

        // Delegate to the service for real validation.
        $service_errors = \local_airpay_users\signup_service::validate((object) $data);
        return array_merge($errors, $service_errors);
    }
}
