<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_programs\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * P1 #14 (2026-05-16) — modal form for "Bulk enrol by audience" on the
 * program Users tab. Mirrors the airpay_learningpath form (P1 #11) and
 * the airpay_classroom form (P1 #13).
 *
 * @package local_airpay_programs
 */
class bulk_enrol_audience_form extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $programid = (int) $this->optional_param('programid', 0, PARAM_INT);

        $mform->addElement('hidden', 'programid', $programid);
        $mform->setType('programid', PARAM_INT);

        $mform->addElement('static', 'intro', '',
            '<p class="text-muted">'
            . get_string('audience_form_intro', 'local_airpay_programs')
            . '</p>');

        $mform->addElement('select', 'designation',
            get_string('designation', 'local_airpay_programs'),
            ['' => get_string('audience_any', 'local_airpay_programs')],
            ['data-airpay-audience-filter' => 'designation']);
        $mform->setType('designation', PARAM_TEXT);

        $mform->addElement('select', 'region',
            get_string('region', 'local_airpay_programs'),
            ['' => get_string('audience_any', 'local_airpay_programs')],
            ['data-airpay-audience-filter' => 'region']);
        $mform->setType('region', PARAM_TEXT);

        $mform->addElement('select', 'location',
            get_string('location', 'local_airpay_programs'),
            ['' => get_string('audience_any', 'local_airpay_programs')],
            ['data-airpay-audience-filter' => 'location']);
        $mform->setType('location', PARAM_TEXT);

        $mform->addElement('select', 'employmenttype',
            get_string('employmenttype', 'local_airpay_programs'),
            ['' => get_string('audience_any', 'local_airpay_programs')],
            ['data-airpay-audience-filter' => 'employmenttype']);
        $mform->setType('employmenttype', PARAM_TEXT);

        $mform->addElement('select', 'cohortid',
            get_string('cohort', 'local_airpay_programs'),
            $this->get_cohort_options(),
            ['data-airpay-audience-filter' => 'cohortid']);
        $mform->setType('cohortid', PARAM_INT);

        $mform->addElement('static', 'preview', '',
            '<div data-airpay-audience-preview class="alert alert-light p-3 mt-3">'
            . '<strong data-airpay-audience-count>0</strong> '
            . get_string('audience_users_matched', 'local_airpay_programs')
            . '<div data-airpay-audience-sample class="small text-muted mt-2"></div>'
            . '</div>');

        $this->add_action_buttons(true,
            get_string('audience_enrol_button', 'local_airpay_programs'));
    }

    public function validation($data, $files) {
        $errors = [];
        $any = false;
        foreach (['designation', 'region', 'location', 'employmenttype'] as $k) {
            if (!empty($data[$k])) { $any = true; break; }
        }
        if (!$any && empty($data['cohortid'])) {
            $errors['designation'] = get_string('audience_pick_at_least_one',
                'local_airpay_programs');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        global $USER;
        $data = $this->get_data();
        $programid = (int) $data->programid;

        $filters = [
            'designation'    => (string) ($data->designation    ?? ''),
            'region'         => (string) ($data->region         ?? ''),
            'location'       => (string) ($data->location       ?? ''),
            'employmenttype' => (string) ($data->employmenttype ?? ''),
            'cohortid'       => (int)    ($data->cohortid       ?? 0),
        ];

        $result = \local_airpay_programs\program_audience_enroller::enrol_by_filter(
            $programid, $filters, (int) $USER->id);

        return [
            'programid' => $programid,
            'matched'   => $result['matched'],
            'enrolled'  => $result['enrolled'],
            'capped'    => $result['capped'],
            'message'   => sprintf(
                get_string('audience_enrol_result', 'local_airpay_programs'),
                $result['enrolled'], $result['matched']),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_programs:enrol',
            $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $programid = (int) $this->optional_param('programid', 0, PARAM_INT);
        return new \moodle_url('/local/airpay_programs/view.php',
            ['id' => $programid, 'tab' => 'users']);
    }

    private function get_cohort_options(): array {
        global $DB;
        $options = [0 => get_string('audience_any_cohort', 'local_airpay_programs')];
        $rows = $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name');
        foreach ($rows as $r) {
            $options[(int) $r->id] = format_string($r->name);
        }
        return $options;
    }
}
