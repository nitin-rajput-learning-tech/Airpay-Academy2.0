<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Modal form: enrol one or more users into a classroom roster.
 *
 * Loaded via core_form/modalform from the classroom view's Users tab.
 * Dropdown shows users NOT already on the roster. Tenant-scoped: a
 * non-siteadmin caller only sees users in their org tree.
 *
 * Mirrors local_sentientia_learningpath\form\enrol_users_form (same UX
 * constraints — plain <select multiple> for headless test reliability).
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_classroom_users extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $classroomid = (int) $this->optional_param('classroomid', 0, PARAM_INT);

        $mform->addElement('hidden', 'classroomid', $classroomid);
        $mform->setType('classroomid', PARAM_INT);

        global $DB, $USER;

        // Already-on-roster set (excluded from dropdown).
        $already = [];
        if ($DB->get_manager()->table_exists('local_sentientia_classroom_users')) {
            $already = $DB->get_fieldset_select('local_sentientia_classroom_users',
                'userid', 'classroomid = :c', ['c' => $classroomid]);
        }

        $where = ['u.deleted = 0', 'u.suspended = 0', 'u.id > 2'];
        $params = [];

        // Tenant-scope: non-siteadmin only sees users in their tenant tree.
        if (!is_siteadmin()) {
            $parts = explode('/', trim($USER->open_path ?? '', '/'));
            $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
            if ($top > 0) {
                $where[] = '(u.open_path = :orgexact OR u.open_path LIKE :orgprefix)';
                $params['orgexact']  = '/' . $top;
                $params['orgprefix'] = $DB->sql_like_escape('/' . $top . '/') . '%';
            }
        }

        if (!empty($already)) {
            [$insql, $inparams] = $DB->get_in_or_equal($already, SQL_PARAMS_NAMED, 'aid', false);
            $where[] = "u.id $insql";
            $params = array_merge($params, $inparams);
        }
        $wheresql = implode(' AND ', $where);

        // Pull only the columns we need; defensive about open_employeeid.
        $cols = $DB->get_columns('user');
        $extra = '';
        if (isset($cols['open_employeeid'])) { $extra .= ', u.open_employeeid'; }

        $users = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email{$extra}
               FROM {user} u
              WHERE $wheresql
           ORDER BY u.lastname ASC, u.firstname ASC", $params, 0, 2000);

        $options = [];
        foreach ($users as $u) {
            $label = trim(($u->firstname ?? '') . ' ' . ($u->lastname ?? ''));
            if (!empty($u->open_employeeid ?? '')) {
                $label .= ' [' . format_string($u->open_employeeid) . ']';
            }
            $label .= ' — ' . $u->email;
            $options[(int) $u->id] = $label;
        }

        $mform->addElement('select', 'userids',
            get_string('enrol_users', 'local_sentientia_classroom'),
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
        $classroomid = (int) $data->classroomid;
        $userids = is_array($data->userids ?? null) ? array_map('intval', $data->userids) : [];

        $count = \local_sentientia_classroom\session_manager::enrol_users($classroomid, $userids);

        return [
            'classroomid' => $classroomid,
            'enrolled'    => $count,
            'message'     => get_string('users_enrolled_success', 'local_sentientia_classroom', $count),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        // Nothing to pre-fill.
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_classroom:update', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $classroomid = (int) $this->optional_param('classroomid', 0, PARAM_INT);
        return new \moodle_url('/local/sentientia_classroom/view.php',
            ['id' => $classroomid, 'tab' => 'users']);
    }
}
