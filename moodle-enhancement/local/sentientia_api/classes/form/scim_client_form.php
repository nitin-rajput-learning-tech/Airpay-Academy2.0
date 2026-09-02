<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_sentientia_api\scim\client;

/**
 * Add-client form for the SCIM admin page (ADR-030 Wave B).
 *
 * @package local_sentientia_api
 */
class scim_client_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('scim_client_name', 'local_sentientia_api'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'costcenterid', get_string('scim_client_tenant', 'local_sentientia_api'), ['size' => 8]);
        $mform->setType('costcenterid', PARAM_INT);
        $mform->setDefault('costcenterid', 0);
        $mform->addHelpButton('costcenterid', 'scim_client_tenant', 'local_sentientia_api');

        $auths = array_combine(client::ALLOWED_AUTH, client::ALLOWED_AUTH);
        $mform->addElement('select', 'auth', get_string('scim_client_auth', 'local_sentientia_api'), $auths);
        $mform->setDefault('auth', 'oauth2');
        $mform->addHelpButton('auth', 'scim_client_auth', 'local_sentientia_api');

        $mform->addElement('text', 'ratelimit', get_string('scim_client_ratelimit', 'local_sentientia_api'), ['size' => 8]);
        $mform->setType('ratelimit', PARAM_INT);
        $mform->setDefault('ratelimit', 0);
        $mform->addHelpButton('ratelimit', 'scim_client_ratelimit', 'local_sentientia_api');

        $mform->addElement('advcheckbox', 'enabled', get_string('scim_client_enabled', 'local_sentientia_api'));
        $mform->setDefault('enabled', 1);

        $this->add_action_buttons(false, get_string('scim_client_add', 'local_sentientia_api'));
    }
}
