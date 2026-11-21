<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

use local_sentientia_users\external_trainer\manager;

/**
 * The external-trainer invite form on /local/sentientia_users/external_trainers.php (ADR-036 D9).
 *
 * First name, last name, e-mail address and organisation. A cross-tenant caller (site admin or
 * local/sentientia_platform:crosstenant) also picks the tenant; anyone else invites into their own
 * tenant and sees no choice (manager::invite_path_for_caller() decides either way, the form only
 * offers what it will accept). No password field: the invite e-mail carries a set-password link.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_trainer_invite_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'firstname', get_string('firstname'), ['maxlength' => 100, 'size' => 30]);
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addRule('firstname', null, 'required', null, 'client');

        $mform->addElement('text', 'lastname', get_string('lastname'), ['maxlength' => 100, 'size' => 30]);
        $mform->setType('lastname', PARAM_NOTAGS);
        $mform->addRule('lastname', null, 'required', null, 'client');

        $mform->addElement('text', 'email', get_string('email'), ['maxlength' => 100, 'size' => 30]);
        $mform->setType('email', PARAM_RAW_TRIMMED);
        $mform->addRule('email', null, 'required', null, 'client');

        $mform->addElement('text', 'organisation', get_string('exttrainer_field_organisation', 'local_sentientia_users'),
            ['maxlength' => 255, 'size' => 40]);
        $mform->setType('organisation', PARAM_TEXT);
        $mform->addHelpButton('organisation', 'exttrainer_field_organisation', 'local_sentientia_users');

        // A cross-tenant caller always gets the choice, offered only the tenants where the flag is ON
        // (manager::tenant_options()). Keyed on the caller, not on the list being non-empty: with every
        // tenant switched off the select is empty and validation names the problem, instead of the field
        // vanishing and invite() failing after submit.
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            $tenants = manager::tenant_options();
            $mform->addElement('select', 'tenantpath', get_string('exttrainer_field_tenant', 'local_sentientia_users'),
                ['' => get_string('choosedots')] + $tenants);
            $mform->setType('tenantpath', PARAM_RAW_TRIMMED);
            $mform->addRule('tenantpath', null, 'required', null, 'client');
        }

        $this->add_action_buttons(false, get_string('exttrainer_invite_submit', 'local_sentientia_users'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim((string) ($data['firstname'] ?? '')) === '') {
            $errors['firstname'] = get_string('required');
        }
        if (trim((string) ($data['lastname'] ?? '')) === '') {
            $errors['lastname'] = get_string('required');
        }
        $problem = manager::email_problem((string) ($data['email'] ?? ''));
        if ($problem !== null) {
            $errors['email'] = get_string($problem, 'local_sentientia_users');
        }
        if (\local_sentientia_platform\tenant::is_cross_tenant()
                && !array_key_exists((string) ($data['tenantpath'] ?? ''), manager::tenant_options())) {
            $errors['tenantpath'] = get_string('exttrainer_error_tenant', 'local_sentientia_users');
        }
        return $errors;
    }
}
