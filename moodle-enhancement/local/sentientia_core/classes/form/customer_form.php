<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Add / edit a Sentientia customer (ADR-021 Wave 4 registry admin UI).
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customer_form extends \moodleform {

    /**
     * Form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'savecustomer');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('text', 'name', get_string('field_customername', 'local_sentientia_core'),
            ['maxlength' => 255, 'size' => 48]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('field_shortname', 'local_sentientia_core'),
            ['maxlength' => 100, 'size' => 24]);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addHelpButton('shortname', 'field_shortname', 'local_sentientia_core');

        $mform->addElement('select', 'status', get_string('field_status', 'local_sentientia_core'),
            self::status_options());
        $mform->setDefault('status', 'active');

        $this->add_action_buttons(true, get_string('addcustomer', 'local_sentientia_core'));
    }

    /**
     * Server-side validation: enforce shortname uniqueness.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);

        $select = 'shortname = :sn';
        $params = ['sn' => $data['shortname']];
        if (!empty($data['id'])) {
            $select .= ' AND id <> :id';
            $params['id'] = $data['id'];
        }
        if ($DB->record_exists_select('local_sentientia_customer', $select, $params)) {
            $errors['shortname'] = get_string('err_shortname_taken', 'local_sentientia_core');
        }
        return $errors;
    }

    /**
     * The status dropdown options.
     *
     * @return array
     */
    public static function status_options(): array {
        return [
            'active'    => get_string('status_active', 'local_sentientia_core'),
            'suspended' => get_string('status_suspended', 'local_sentientia_core'),
            'archived'  => get_string('status_archived', 'local_sentientia_core'),
        ];
    }
}
