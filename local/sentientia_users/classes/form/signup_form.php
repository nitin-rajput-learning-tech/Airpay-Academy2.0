<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * W1-8 (2026-05-16) — Public-tenant signup form.
 *
 * Replaces BizLMS local_users/forms/registration_form. Captures first/last
 * name, email, password (with confirmation), country, language, and a hard
 * ToS-acceptance gate.
 *
 * @package local_sentientia_users
 */
class signup_form extends \moodleform {

    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        $mform->addElement('header', 'signupheader',
            get_string('signup_heading', 'local_sentientia_users'));

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
        $mform->addHelpButton('password', 'signup_password', 'local_sentientia_users');

        $mform->addElement('passwordunmask', 'password2',
            get_string('signup_password_confirm', 'local_sentientia_users'),
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
        $privacy_url = (new \moodle_url('/local/sentientia_users/privacypolicy.php'))
            ->out(false);
        $tos_url = (new \moodle_url('/local/sentientia_users/termscondition.php'))
            ->out(false);
        $tos_label = get_string('signup_tos_label', 'local_sentientia_users', (object) [
            'tos_url'     => $tos_url,
            'privacy_url' => $privacy_url,
        ]);
        $mform->addElement('advcheckbox', 'agree_tos', '', $tos_label);
        $mform->setType('agree_tos', PARAM_INT);

        // Honeypot field — bots fill all visible fields. CSS-hidden so
        // humans never see it. Moodle wraps each mform element in a
        // container whose ID is "fitem_" + the element id — here
        // "fitem_id_honeypot_url" (an ID, NOT a class). The original
        // ".fitem_id_honeypot_url" class selector matched nothing, so the
        // honeypot rendered as a visible empty field between the ToS
        // checkbox and the buttons. Target the ID instead.
        $mform->addElement('text', 'honeypot_url', '');
        $mform->setType('honeypot_url', PARAM_RAW_TRIMMED);
        $mform->addElement('html',
            '<style>#fitem_id_honeypot_url { display: none !important; }</style>');

        // P1 #59 (2026-05-20) — defense-in-depth: Google reCAPTCHA v2,
        // shown only when the site admin has configured the keys in
        // Site administration > Security > Site policies.
        // The honeypot stays as the first line of defence; reCAPTCHA
        // is the second, gated on admin opt-in so this form works
        // on dev environments without internet access too.
        if (signup_form::recaptcha_configured()) {
            $mform->addElement('recaptcha', 'recaptcha_element',
                get_string('security_question', 'auth'));
            $mform->addHelpButton('recaptcha_element', 'recaptcha', 'auth');
        }

        $this->add_action_buttons(true,
            get_string('signup_submit', 'local_sentientia_users'));
    }

    /**
     * Returns true when the site has reCAPTCHA v2 keys configured.
     * P1 #59 (2026-05-20) — extracted so the validation method can
     * call the same gate without duplicating the empty() check.
     */
    public static function recaptcha_configured(): bool {
        global $CFG;
        return !empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Honeypot trip → reject silently (well, with a generic message).
        if (!empty($data['honeypot_url'])) {
            $errors['email'] = get_string('signup_generic_error',
                'local_sentientia_users');
            return $errors;
        }

        // P1 #59 — reCAPTCHA challenge verification, mirroring Moodle's
        // own auth/email/signup_form.php logic. The form element rendered
        // by the 'recaptcha' MoodleQuickForm element auto-injects the
        // challenge + response fields under the recaptcha_element name.
        if (signup_form::recaptcha_configured()) {
            $recaptcha_element = $this->_form->getElement('recaptcha_element');
            if (!empty($this->_form->_submitValues['g-recaptcha-response'])) {
                $response = $this->_form->_submitValues['g-recaptcha-response'];
                if (!$recaptcha_element->verify($response)) {
                    $errors['recaptcha_element'] =
                        get_string('incorrectpleasetryagain', 'auth');
                }
            } else {
                $errors['recaptcha_element'] =
                    get_string('missingrecaptchachallengefield');
            }
        }

        // Delegate to the service for real validation.
        $service_errors = \local_sentientia_users\signup_service::validate((object) $data);
        return array_merge($errors, $service_errors);
    }
}
