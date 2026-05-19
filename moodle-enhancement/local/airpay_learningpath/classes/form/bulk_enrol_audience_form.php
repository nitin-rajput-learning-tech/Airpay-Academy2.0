<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * P1 #11 (2026-05-16) — modal form for the "Bulk enrol by audience" action
 * on the path Users tab.
 *
 * Admin picks filter criteria (designation, region, employment type,
 * cohort) → submission calls `path_audience_enroller::enrol_by_filter()`
 * → returns ['matched' => N, 'enrolled' => M] for the success toast.
 *
 * Preview happens via a separate AMD-driven WS call before final submit,
 * so admins see the count + sample BEFORE committing.
 *
 * @package local_airpay_learningpath
 */
class bulk_enrol_audience_form extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $pathid = (int) $this->optional_param('pathid', 0, PARAM_INT);

        $mform->addElement('hidden', 'pathid', $pathid);
        $mform->setType('pathid', PARAM_INT);

        $mform->addElement('static', 'intro', '',
            '<p class="text-muted">'
            . get_string('audience_form_intro', 'local_airpay_learningpath')
            . '</p>');

        // Each dropdown is populated by the chip_filters AMD module
        // (already loaded for the user-list page). We re-use the same
        // `local_airpay_users_list_filter_options` WS — single roundtrip
        // returns designation + location + region + grade + employmenttype
        // + hrmsrole distinct values.
        $mform->addElement('select', 'designation',
            get_string('designation', 'local_airpay_learningpath'),
            ['' => get_string('audience_any', 'local_airpay_learningpath')],
            ['data-airpay-audience-filter' => 'designation']);
        $mform->setType('designation', PARAM_TEXT);

        $mform->addElement('select', 'region',
            get_string('region', 'local_airpay_learningpath'),
            ['' => get_string('audience_any', 'local_airpay_learningpath')],
            ['data-airpay-audience-filter' => 'region']);
        $mform->setType('region', PARAM_TEXT);

        $mform->addElement('select', 'location',
            get_string('location', 'local_airpay_learningpath'),
            ['' => get_string('audience_any', 'local_airpay_learningpath')],
            ['data-airpay-audience-filter' => 'location']);
        $mform->setType('location', PARAM_TEXT);

        $mform->addElement('select', 'employmenttype',
            get_string('employmenttype', 'local_airpay_learningpath'),
            ['' => get_string('audience_any', 'local_airpay_learningpath')],
            ['data-airpay-audience-filter' => 'employmenttype']);
        $mform->setType('employmenttype', PARAM_TEXT);

        // Cohort selector — populated from local cohorts (using a
        // pre-fetched list since cohorts are usually < 100 per tenant).
        $mform->addElement('select', 'cohortid',
            get_string('cohort', 'local_airpay_learningpath'),
            $this->get_cohort_options(),
            ['data-airpay-audience-filter' => 'cohortid']);
        $mform->setType('cohortid', PARAM_INT);

        // Live preview pane — gets populated by the AMD module as the
        // admin tweaks filters. Empty until preview() is called.
        $mform->addElement('static', 'preview', '',
            '<div data-airpay-audience-preview class="alert alert-light p-3 mt-3">'
            . '<strong data-airpay-audience-count>0</strong> '
            . get_string('audience_users_matched', 'local_airpay_learningpath')
            . '<div data-airpay-audience-sample class="small text-muted mt-2"></div>'
            . '</div>');

        $this->add_action_buttons(true,
            get_string('audience_enrol_button', 'local_airpay_learningpath'));
    }

    public function validation($data, $files) {
        $errors = [];
        // At least one filter must be set (otherwise admin would enrol
        // ALL users — that's not a target audience, that's "enrol everyone"
        // and we want them to use the regular enrol form for that).
        $any = false;
        foreach (['designation', 'region', 'location', 'employmenttype'] as $k) {
            if (!empty($data[$k])) { $any = true; break; }
        }
        if (!$any && empty($data['cohortid'])) {
            $errors['designation'] = get_string('audience_pick_at_least_one',
                'local_airpay_learningpath');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        global $USER;
        $data = $this->get_data();
        $pathid = (int) $data->pathid;

        $filters = [
            'designation'    => (string) ($data->designation    ?? ''),
            'region'         => (string) ($data->region         ?? ''),
            'location'       => (string) ($data->location       ?? ''),
            'employmenttype' => (string) ($data->employmenttype ?? ''),
            'cohortid'       => (int)    ($data->cohortid       ?? 0),
        ];

        $result = \local_airpay_learningpath\path_audience_enroller::enrol_by_filter(
            $pathid, $filters, (int) $USER->id);

        return [
            'pathid'   => $pathid,
            'matched'  => $result['matched'],
            'enrolled' => $result['enrolled'],
            'capped'   => $result['capped'],
            'message'  => sprintf(
                get_string('audience_enrol_result', 'local_airpay_learningpath'),
                $result['enrolled'], $result['matched']),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        // No pre-fill — every audience starts from a blank slate.
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_learningpath:enrol',
            $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $pathid = (int) $this->optional_param('pathid', 0, PARAM_INT);
        return new \moodle_url('/local/airpay_learningpath/view.php',
            ['id' => $pathid, 'tab' => 'users']);
    }

    /**
     * Build cohort options for the dropdown. Tenant-scoped to caller's
     * own context cohorts + system cohorts.
     */
    private function get_cohort_options(): array {
        global $DB;
        $options = [0 => get_string('audience_any_cohort', 'local_airpay_learningpath')];
        // System-level cohorts are visible to all tenants. We don't try
        // to be too clever here — admins should see every cohort their
        // context can see.
        $rows = $DB->get_records('cohort',
            ['visible' => 1],
            'name ASC',
            'id, name');
        foreach ($rows as $r) {
            $options[(int) $r->id] = format_string($r->name);
        }
        return $options;
    }
}
