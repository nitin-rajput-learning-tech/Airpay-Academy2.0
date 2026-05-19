<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_users\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * W1-6 (2026-05-16) — moodleform for uploading a 24-column HRMS CSV.
 *
 * Renders a filepicker + a Help block listing all standard columns and which
 * are mandatory. Submission is handled by the parent bulk_hrms.php page.
 *
 * @package local_airpay_users
 */
class bulk_hrms_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'csvfile',
            get_string('hrms_csvfile', 'local_airpay_users'),
            null, [
                'accepted_types' => ['.csv'],
                'maxfiles'       => 1,
            ]);
        $mform->addRule('csvfile', null, 'required', null, 'client');

        $mandatory = implode(', ', \local_airpay_users\hrms_importer::MANDATORY_COLUMNS);
        $standard  = implode(', ', \local_airpay_users\hrms_importer::STANDARD_COLUMNS);
        $help_html = '<div class="alert alert-info small">'
            . '<strong>HRMS 24-column format</strong> (Darwinbox / SAP style).'
            . '<br><strong>Mandatory columns:</strong> <code>' . s($mandatory) . '</code>'
            . '<br><strong>All recognised columns:</strong> <code>' . s($standard) . '</code>'
            . '<br>Column order is flexible — header row is read by name.'
            . ' Unknown columns are ignored.'
            . ' Existing users (matching email, username OR employee_code) are <em>updated</em>;'
            . ' new users are <em>inserted</em>.'
            . ' <code>reportingmanager_empid</code> is resolved in a second pass against'
            . ' <code>open_employeeid</code> in the same tenant — unresolved managers'
            . ' generate warnings, not errors.'
            . '</div>';
        $mform->addElement('static', 'csvhelp', '', $help_html);

        $this->add_action_buttons(true,
            get_string('hrms_runimport', 'local_airpay_users'));
    }

    /**
     * Server-side validation. We can only validate the form-level state here
     * (CSV content goes through the importer's own validation pass on submit).
     */
    public function validation($data, $files) {
        $errors = [];
        if (empty($files['csvfile']) && empty($data['csvfile'])) {
            $errors['csvfile'] = get_string('required');
        }
        return $errors;
    }
}
