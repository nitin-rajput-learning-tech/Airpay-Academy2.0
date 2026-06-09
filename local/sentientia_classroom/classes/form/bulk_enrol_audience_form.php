<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * P1 #13 + #14 (2026-05-16) — modal form for "Bulk enrol by audience" on
 * the classroom Users tab. Mirrors the sentientia_learningpath form
 * (P1 #11).
 *
 * @package local_sentientia_classroom
 */
class bulk_enrol_audience_form extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $classroomid = (int) $this->optional_param('classroomid', 0, PARAM_INT);

        $mform->addElement('hidden', 'classroomid', $classroomid);
        $mform->setType('classroomid', PARAM_INT);

        $mform->addElement('static', 'intro', '',
            '<p class="text-muted">'
            . get_string('audience_form_intro', 'local_sentientia_classroom')
            . '</p>');

        $mform->addElement('select', 'designation',
            get_string('designation', 'local_sentientia_classroom'),
            ['' => get_string('audience_any', 'local_sentientia_classroom')],
            ['data-airpay-audience-filter' => 'designation']);
        $mform->setType('designation', PARAM_TEXT);

        $mform->addElement('select', 'region',
            get_string('region', 'local_sentientia_classroom'),
            ['' => get_string('audience_any', 'local_sentientia_classroom')],
            ['data-airpay-audience-filter' => 'region']);
        $mform->setType('region', PARAM_TEXT);

        $mform->addElement('select', 'location',
            get_string('location', 'local_sentientia_classroom'),
            ['' => get_string('audience_any', 'local_sentientia_classroom')],
            ['data-airpay-audience-filter' => 'location']);
        $mform->setType('location', PARAM_TEXT);

        $mform->addElement('select', 'employmenttype',
            get_string('employmenttype', 'local_sentientia_classroom'),
            ['' => get_string('audience_any', 'local_sentientia_classroom')],
            ['data-airpay-audience-filter' => 'employmenttype']);
        $mform->setType('employmenttype', PARAM_TEXT);

        $mform->addElement('select', 'cohortid',
            get_string('cohort', 'local_sentientia_classroom'),
            $this->get_cohort_options(),
            ['data-airpay-audience-filter' => 'cohortid']);
        $mform->setType('cohortid', PARAM_INT);

        $mform->addElement('static', 'preview', '',
            '<div data-airpay-audience-preview class="alert alert-light p-3 mt-3">'
            . '<strong data-airpay-audience-count>0</strong> '
            . get_string('audience_users_matched', 'local_sentientia_classroom')
            . '<div data-airpay-audience-sample class="small text-muted mt-2"></div>'
            . '</div>');

        $this->add_action_buttons(true,
            get_string('audience_enrol_button', 'local_sentientia_classroom'));
    }

    public function validation($data, $files) {
        $errors = [];
        $any = false;
        foreach (['designation', 'region', 'location', 'employmenttype'] as $k) {
            if (!empty($data[$k])) { $any = true; break; }
        }
        if (!$any && empty($data['cohortid'])) {
            $errors['designation'] = get_string('audience_pick_at_least_one',
                'local_sentientia_classroom');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        global $USER;
        $data = $this->get_data();
        $classroomid = (int) $data->classroomid;

        $filters = [
            'designation'    => (string) ($data->designation    ?? ''),
            'region'         => (string) ($data->region         ?? ''),
            'location'       => (string) ($data->location       ?? ''),
            'employmenttype' => (string) ($data->employmenttype ?? ''),
            'cohortid'       => (int)    ($data->cohortid       ?? 0),
        ];

        $result = \local_sentientia_classroom\classroom_audience_enroller::enrol_by_filter(
            $classroomid, $filters, (int) $USER->id);

        return [
            'classroomid' => $classroomid,
            'matched'     => $result['matched'],
            'enrolled'    => $result['enrolled'],
            'capped'      => $result['capped'],
            'message'     => sprintf(
                get_string('audience_enrol_result', 'local_sentientia_classroom'),
                $result['enrolled'], $result['matched']),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_classroom:enrol',
            $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $classroomid = (int) $this->optional_param('classroomid', 0, PARAM_INT);
        return new \moodle_url('/local/sentientia_classroom/view.php',
            ['id' => $classroomid, 'tab' => 'users']);
    }

    private function get_cohort_options(): array {
        global $DB;
        $options = [0 => get_string('audience_any_cohort', 'local_sentientia_classroom')];
        $rows = $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name');
        foreach ($rows as $r) {
            $options[(int) $r->id] = format_string($r->name);
        }
        return $options;
    }
}
