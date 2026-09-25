<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Modal form: enrol one or more users into a learning path.
 *
 * Loaded via core_form/modalform from the path detail page's Users tab.
 * The dropdown only shows users NOT already enrolled. Tenant-scoped: a
 * non-siteadmin caller only sees users in their org tree.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_users_form extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $pathid = (int) $this->optional_param('pathid', 0, PARAM_INT);

        $mform->addElement('hidden', 'pathid', $pathid);
        $mform->setType('pathid', PARAM_INT);

        global $DB, $USER;

        // Already-enrolled set (excluded from dropdown).
        $already = $DB->get_fieldset_select('local_sentientia_learningpath_users',
            'userid', 'pathid = :p', ['p' => $pathid]);

        $where = ['u.deleted = 0', 'u.suspended = 0', 'u.id > 2'];
        $params = [];

        // Tenant scope (ADR-031): cross-tenant callers see everyone, a scoped
        // caller their own tenant, and a caller with NO tenant nobody. The
        // hand-rolled check this replaces skipped the filter when the
        // caller's tenant did not resolve, listing every tenant's users.
        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_filter('u');
        $where[] = $tnsql;
        $params = array_merge($params, $tnargs);

        if (!empty($already)) {
            [$insql, $inparams] = $DB->get_in_or_equal($already, SQL_PARAMS_NAMED, 'aid', false);
            $where[] = "u.id $insql";
            $params = array_merge($params, $inparams);
        }
        $wheresql = implode(' AND ', $where);

        // Cap at 2000 — that's enough for any tenant. Above that we'd want a
        // CSV-upload variant (separate feature; not built yet).
        $users = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email, u.open_employeeid
               FROM {user} u
              WHERE $wheresql
           ORDER BY u.lastname ASC, u.firstname ASC", $params, 0, 2000);

        $options = [];
        foreach ($users as $u) {
            $label = trim($u->firstname . ' ' . $u->lastname);
            if (!empty($u->open_employeeid)) {
                $label .= ' [' . format_string($u->open_employeeid) . ']';
            }
            $label .= ' — ' . $u->email;
            $options[(int) $u->id] = $label;
        }

        // Multi-select (see same note in assign_courses_form). Plain <select
        // multiple> is bulletproof + headless-test-friendly.
        $mform->addElement('select', 'userids',
            get_string('enrol_users', 'local_sentientia_learningpath'),
            $options,
            ['multiple' => 'multiple', 'size' => min(20, max(5, count($options)))]);
        $mform->setType('userids', PARAM_INT);
        $mform->addRule('userids', null, 'required', null, 'client');
        $mform->addElement('static', 'hint', '',
            '<small class="text-muted">Hold Ctrl (or Cmd on Mac) and click to select multiple users.</small>');

        if (empty($options)) {
            $mform->addElement('static', 'no_options',
                '',
                '<p class="text-muted fst-italic">All eligible users in your tenant are already enrolled.</p>');
        }
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $pathid = (int) $data->pathid;
        $userids = is_array($data->userids) ? array_map('intval', $data->userids) : [];
        // ADR-031: every user enrolled must be in the caller's tenant.
        \local_sentientia_learningpath\path_manager::require_users_in_scope($userids);

        $count = \local_sentientia_learningpath\path_manager::enrol_users($pathid, $userids);

        return [
            'pathid'   => $pathid,
            'enrolled' => $count,
            'message'  => $count . ' user' . ($count === 1 ? '' : 's') . ' enrolled.',
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        // Nothing to pre-fill.
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_learningpath:enrol', $this->get_context_for_dynamic_submission());
        // ADR-031: the path must be in the caller's tenant.
        \local_sentientia_learningpath\path_manager::require_path_tenant((int) $this->optional_param('pathid', 0, PARAM_INT));
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $pathid = (int) $this->optional_param('pathid', 0, PARAM_INT);
        return new \moodle_url('/local/sentientia_learningpath/view.php',
            ['id' => $pathid, 'tab' => 'users']);
    }
}
