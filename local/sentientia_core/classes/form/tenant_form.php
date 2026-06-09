<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Add / edit a Sentientia tenant (ADR-021 Wave 4 registry admin UI).
 *
 * Expects $customdata['customers'] = [customerid => name] for the owner dropdown.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_form extends \moodleform {

    /**
     * Form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $customers = $this->_customdata['customers'] ?? [];

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'savetenant');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('text', 'rootid', get_string('field_rootid', 'local_sentientia_core'),
            ['size' => 12]);
        $mform->setType('rootid', PARAM_INT);
        $mform->addRule('rootid', null, 'required', null, 'client');
        $mform->addHelpButton('rootid', 'field_rootid', 'local_sentientia_core');

        $mform->addElement('select', 'customerid', get_string('field_customer', 'local_sentientia_core'),
            $customers);
        $mform->addRule('customerid', null, 'required', null, 'client');

        $mform->addElement('text', 'name', get_string('field_tenantname', 'local_sentientia_core'),
            ['maxlength' => 255, 'size' => 48]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('field_idnumber', 'local_sentientia_core'),
            ['maxlength' => 255, 'size' => 32]);
        $mform->setType('idnumber', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('idnumber', 'field_idnumber', 'local_sentientia_core');

        $mform->addElement('select', 'status', get_string('field_status', 'local_sentientia_core'),
            customer_form::status_options());
        $mform->setDefault('status', 'active');

        $this->add_action_buttons(true, get_string('addtenant', 'local_sentientia_core'));
    }

    /**
     * Server-side validation: rootid must be a positive integer and unique.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);

        $rootid = (int) $data['rootid'];
        if ($rootid <= 0) {
            $errors['rootid'] = get_string('err_rootid_positive', 'local_sentientia_core');
        } else {
            $select = 'rootid = :r';
            $params = ['r' => $rootid];
            if (!empty($data['id'])) {
                $select .= ' AND id <> :id';
                $params['id'] = $data['id'];
            }
            if ($DB->record_exists_select('local_sentientia_tenant', $select, $params)) {
                $errors['rootid'] = get_string('err_rootid_taken', 'local_sentientia_core');
            }
        }
        return $errors;
    }
}
