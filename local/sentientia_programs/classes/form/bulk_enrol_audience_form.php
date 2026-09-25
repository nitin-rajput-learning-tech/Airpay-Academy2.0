<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * P1 #14 (2026-05-16) — modal form for "Bulk enrol by audience" on the
 * program Users tab. Mirrors the sentientia_learningpath form (P1 #11) and
 * the sentientia_classroom form (P1 #13).
 *
 * @package local_sentientia_programs
 */
class bulk_enrol_audience_form extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $programid = (int) $this->optional_param('programid', 0, PARAM_INT);

        $mform->addElement('hidden', 'programid', $programid);
        $mform->setType('programid', PARAM_INT);

        $mform->addElement('static', 'intro', '',
            '<p class="text-muted">'
            . get_string('audience_form_intro', 'local_sentientia_programs')
            . '</p>');

        $mform->addElement('select', 'designation',
            get_string('designation', 'local_sentientia_programs'),
            ['' => get_string('audience_any', 'local_sentientia_programs')],
            ['data-airpay-audience-filter' => 'designation']);
        $mform->setType('designation', PARAM_TEXT);

        $mform->addElement('select', 'region',
            get_string('region', 'local_sentientia_programs'),
            ['' => get_string('audience_any', 'local_sentientia_programs')],
            ['data-airpay-audience-filter' => 'region']);
        $mform->setType('region', PARAM_TEXT);

        $mform->addElement('select', 'location',
            get_string('location', 'local_sentientia_programs'),
            ['' => get_string('audience_any', 'local_sentientia_programs')],
            ['data-airpay-audience-filter' => 'location']);
        $mform->setType('location', PARAM_TEXT);

        $mform->addElement('select', 'employmenttype',
            get_string('employmenttype', 'local_sentientia_programs'),
            ['' => get_string('audience_any', 'local_sentientia_programs')],
            ['data-airpay-audience-filter' => 'employmenttype']);
        $mform->setType('employmenttype', PARAM_TEXT);

        $mform->addElement('select', 'cohortid',
            get_string('cohort', 'local_sentientia_programs'),
            $this->get_cohort_options(),
            ['data-airpay-audience-filter' => 'cohortid']);
        $mform->setType('cohortid', PARAM_INT);

        $mform->addElement('static', 'preview', '',
            '<div data-airpay-audience-preview class="alert alert-light p-3 mt-3">'
            . '<strong data-airpay-audience-count>0</strong> '
            . get_string('audience_users_matched', 'local_sentientia_programs')
            . '<div data-airpay-audience-sample class="small text-muted mt-2"></div>'
            . '</div>');

        $this->add_action_buttons(true,
            get_string('audience_enrol_button', 'local_sentientia_programs'));
    }

    public function validation($data, $files) {
        $errors = [];
        $any = false;
        foreach (['designation', 'region', 'location', 'employmenttype'] as $k) {
            if (!empty($data[$k])) { $any = true; break; }
        }
        if (!$any && empty($data['cohortid'])) {
            $errors['designation'] = get_string('audience_pick_at_least_one',
                'local_sentientia_programs');
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

        $result = \local_sentientia_programs\program_audience_enroller::enrol_by_filter(
            $programid, $filters, (int) $USER->id);

        return [
            'programid' => $programid,
            'matched'   => $result['matched'],
            'enrolled'  => $result['enrolled'],
            'capped'    => $result['capped'],
            'message'   => sprintf(
                get_string('audience_enrol_result', 'local_sentientia_programs'),
                $result['enrolled'], $result['matched']),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_programs:enrol',
            $this->get_context_for_dynamic_submission());
        // ADR-031: the program must be in the caller's tenant.
        \local_sentientia_programs\program_manager::require_program_access((int) $this->optional_param('programid', 0, PARAM_INT));
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $programid = (int) $this->optional_param('programid', 0, PARAM_INT);
        return new \moodle_url('/local/sentientia_programs/view.php',
            ['id' => $programid, 'tab' => 'users']);
    }

    private function get_cohort_options(): array {
        global $DB;
        $options = [0 => get_string('audience_any_cohort', 'local_sentientia_programs')];
        // ADR-031: a scoped caller sees only cohorts with members in their own
        // tenant (the audience itself is tenant-scoped anyway); no tenant, none.
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            $rows = $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name');
        } else {
            [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_filter('u');
            $rows = $DB->get_records_sql(
                "SELECT c.id, c.name
                   FROM {cohort} c
                  WHERE c.visible = 1
                    AND EXISTS (SELECT 1
                                  FROM {cohort_members} cm
                                  JOIN {user} u ON u.id = cm.userid
                                 WHERE cm.cohortid = c.id AND $tnsql)
               ORDER BY c.name ASC", $tnargs);
        }
        foreach ($rows as $r) {
            $options[(int) $r->id] = format_string($r->name);
        }
        return $options;
    }
}
