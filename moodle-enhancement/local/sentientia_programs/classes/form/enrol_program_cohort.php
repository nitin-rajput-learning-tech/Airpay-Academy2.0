<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Phase F.3 (2026-05-08) — modal form: mass-enrol all members of a
 * Moodle cohort into the program.
 *
 * @package    local_sentientia_programs
 */
class enrol_program_cohort extends \core_form\dynamic_form {

    protected function definition() {
        global $DB;
        $mform = $this->_form;
        $programid = (int) $this->optional_param('programid', 0, PARAM_INT);

        $mform->addElement('hidden', 'programid', $programid);
        $mform->setType('programid', PARAM_INT);

        // Cohort options — show name + member count. ADR-031: for a scoped
        // caller, only cohorts with members in their tenant, counting only
        // those members (the only ones process() will enrol); no tenant, no
        // cohorts. Until 2026-09-25 every cohort on the site was listed with
        // its full member count, and all of its members were enrolled.
        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_filter('u');
        $cohorts = $DB->get_records_sql(
            "SELECT c.id, c.name, c.idnumber,
                    (SELECT COUNT(*) FROM {cohort_members} cm
                       JOIN {user} u ON u.id = cm.userid
                      WHERE cm.cohortid = c.id AND $tnsql) AS member_count
               FROM {cohort} c
              WHERE c.visible = 1
           ORDER BY c.name ASC", $tnargs, 0, 500);
        if (!\local_sentientia_platform\tenant::is_cross_tenant()) {
            $cohorts = array_filter($cohorts, fn($c) => (int) $c->member_count > 0);
        }

        $options = [];
        foreach ($cohorts as $c) {
            $label = format_string($c->name);
            if (!empty($c->idnumber)) {
                $label .= ' [' . format_string($c->idnumber) . ']';
            }
            $label .= ' — ' . (int) $c->member_count . ' member(s)';
            $options[(int) $c->id] = $label;
        }

        if (empty($options)) {
            $mform->addElement('static', 'no_cohorts', '',
                '<p class="text-muted fst-italic">No cohorts found. '
                . 'Create cohorts under <em>Site administration → Users → '
                . 'Accounts → Cohorts</em> first.</p>');
            return;
        }

        $mform->addElement('select', 'cohortid', 'Cohort',
            $options, ['size' => min(15, max(5, count($options)))]);
        $mform->setType('cohortid', PARAM_INT);
        $mform->addRule('cohortid', null, 'required', null, 'client');

        $mform->addElement('static', 'hint', '',
            '<small class="text-muted">All current members of the chosen '
            . 'cohort will be enrolled. Already-enrolled members are skipped.</small>');
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $programid = (int) $data->programid;
        $cohortid = (int) ($data->cohortid ?? 0);

        // ADR-031: a scoped caller only enrols the cohort's members from their
        // own tenant ('' = cross-tenant = every member; null = no tenant).
        $scope = \local_sentientia_platform\tenant::scope_path();
        if ($scope === null) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        $result = \local_sentientia_programs\program_manager::enrol_cohort(
            $programid, $cohortid, $scope);

        return [
            'programid'        => $programid,
            'cohort_size'      => $result['cohort_size'],
            'newly_enrolled'   => $result['newly_enrolled'],
            'already_enrolled' => $result['already_enrolled'],
            'message'          => sprintf(
                'Cohort enrol: %d members → %d newly enrolled, %d already in.',
                $result['cohort_size'], $result['newly_enrolled'],
                $result['already_enrolled']),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        // Nothing to pre-fill.
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_programs:enrol',
            $this->get_context_for_dynamic_submission());
        // ADR-031: the program must be in the caller's tenant (this also
        // refuses a caller with no tenant, so scope_path() is non-null below).
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
}
