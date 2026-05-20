<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_evaluation\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * P1 #40 (2026-05-20) — modal form for "Bulk-assign by audience" on the
 * non-respondents admin page (P1 #38). Parallel-port of the classroom
 * P1 #13 + #14 bulk_enrol_audience_form. Submits to
 * `evaluation_audience_assigner::assign_by_filter` (P1 #39's back-end).
 *
 * Empty filters are intentionally blocked (validation requires at least
 * one criterion) — bulk-assigning EVERYONE would be both surprising and
 * a footgun. Admins who want everyone should use the "all users in this
 * tenant" cohort filter explicitly.
 *
 * @package local_airpay_evaluation
 */
class bulk_assign_audience_form extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $evaluationid = (int) $this->optional_param('evaluationid', 0, PARAM_INT);

        $mform->addElement('hidden', 'evaluationid', $evaluationid);
        $mform->setType('evaluationid', PARAM_INT);

        $mform->addElement('static', 'intro', '',
            '<p class="text-muted">'
            . get_string('bulk_assign_form_intro', 'local_airpay_evaluation')
            . '</p>');

        $mform->addElement('select', 'designation',
            get_string('audience_designation', 'local_airpay_evaluation'),
            ['' => get_string('audience_any', 'local_airpay_evaluation')],
            ['data-airpay-audience-filter' => 'designation']);
        $mform->setType('designation', PARAM_TEXT);

        $mform->addElement('select', 'region',
            get_string('audience_region', 'local_airpay_evaluation'),
            ['' => get_string('audience_any', 'local_airpay_evaluation')],
            ['data-airpay-audience-filter' => 'region']);
        $mform->setType('region', PARAM_TEXT);

        $mform->addElement('select', 'location',
            get_string('audience_location', 'local_airpay_evaluation'),
            ['' => get_string('audience_any', 'local_airpay_evaluation')],
            ['data-airpay-audience-filter' => 'location']);
        $mform->setType('location', PARAM_TEXT);

        $mform->addElement('select', 'employmenttype',
            get_string('audience_employmenttype', 'local_airpay_evaluation'),
            ['' => get_string('audience_any', 'local_airpay_evaluation')],
            ['data-airpay-audience-filter' => 'employmenttype']);
        $mform->setType('employmenttype', PARAM_TEXT);

        $mform->addElement('select', 'cohortid',
            get_string('audience_cohort', 'local_airpay_evaluation'),
            $this->get_cohort_options(),
            ['data-airpay-audience-filter' => 'cohortid']);
        $mform->setType('cohortid', PARAM_INT);

        $mform->addElement('static', 'preview', '',
            '<div data-airpay-audience-preview class="alert alert-light p-3 mt-3">'
            . '<strong data-airpay-audience-count>0</strong> '
            . get_string('audience_users_matched', 'local_airpay_evaluation')
            . '<div data-airpay-audience-sample class="small text-muted mt-2"></div>'
            . '</div>');

        $this->add_action_buttons(true,
            get_string('bulk_assign_button', 'local_airpay_evaluation'));
    }

    public function validation($data, $files) {
        $errors = [];
        $any = false;
        foreach (['designation', 'region', 'location', 'employmenttype'] as $k) {
            if (!empty($data[$k])) { $any = true; break; }
        }
        if (!$any && empty($data['cohortid'])) {
            $errors['designation'] = get_string('bulk_assign_pick_at_least_one',
                'local_airpay_evaluation');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        global $USER;
        $data = $this->get_data();
        $evaluationid = (int) $data->evaluationid;

        $filters = [
            'designation'    => (string) ($data->designation    ?? ''),
            'region'         => (string) ($data->region         ?? ''),
            'location'       => (string) ($data->location       ?? ''),
            'employmenttype' => (string) ($data->employmenttype ?? ''),
            'cohortid'       => (int)    ($data->cohortid       ?? 0),
        ];

        $result = \local_airpay_evaluation\evaluation_audience_assigner::assign_by_filter(
            $evaluationid, $filters, (int) $USER->id, null);

        $existing = $result['matched'] - $result['assigned'];

        return [
            'evaluationid' => $evaluationid,
            'matched'      => $result['matched'],
            'assigned'     => $result['assigned'],
            'capped'       => $result['capped'],
            'message'      => get_string('bulk_assign_result',
                'local_airpay_evaluation', (object) [
                    'assigned' => $result['assigned'],
                    'matched'  => $result['matched'],
                    'existing' => $existing,
                ]),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_evaluation:manage',
            $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $evaluationid = (int) $this->optional_param('evaluationid', 0, PARAM_INT);
        return new \moodle_url('/local/airpay_evaluation/non_respondents.php',
            ['id' => $evaluationid]);
    }

    private function get_cohort_options(): array {
        global $DB;
        $options = [0 => get_string('audience_any_cohort', 'local_airpay_evaluation')];
        $rows = $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name');
        foreach ($rows as $r) {
            $options[(int) $r->id] = format_string($r->name);
        }
        return $options;
    }
}
