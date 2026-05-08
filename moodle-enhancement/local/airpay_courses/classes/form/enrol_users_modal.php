<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase F.5 (2026-05-08) — modal form: enrol one or more users in a course.
// Replaces the deep-link to /enrol/users.php with an in-page modal.

namespace local_airpay_courses\form;

defined('MOODLE_INTERNAL') || die();

class enrol_users_modal extends \core_form\dynamic_form {

    protected function definition() {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/enrol/locallib.php');

        $mform = $this->_form;
        $courseid = (int) $this->optional_param('courseid', 0, PARAM_INT);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // Role dropdown — load from {role} (BizLMS uses 'employee').
        $roles = $DB->get_records('role', null, 'sortorder ASC',
            'id, shortname, name');
        $role_options = [];
        foreach ($roles as $r) {
            // Hide system + admin from picker.
            if (in_array($r->shortname, ['guest', 'frontpage', 'user',
                'administrator'], true)) continue;
            $label = format_string($r->name) ?: $r->shortname;
            $role_options[(int) $r->id] = $label . ' (' . $r->shortname . ')';
        }
        // Default = employee/student.
        $default_roleid = 0;
        foreach ($roles as $r) {
            if (in_array($r->shortname, ['employee', 'student'], true)) {
                $default_roleid = (int) $r->id;
                break;
            }
        }
        $mform->addElement('select', 'roleid', 'Role',
            $role_options);
        $mform->setType('roleid', PARAM_INT);
        if ($default_roleid > 0) {
            $mform->setDefault('roleid', $default_roleid);
        }

        // User picker — limit to non-enrolled users in the caller's tenant.
        $where = ['u.deleted = 0', 'u.suspended = 0', 'u.id > 2'];
        $params = ['cid' => $courseid];

        if (!is_siteadmin()) {
            $parts = explode('/', trim($USER->open_path ?? '', '/'));
            $top = isset($parts[0]) && ctype_digit($parts[0])
                ? (int) $parts[0] : 0;
            if ($top > 0) {
                $where[] = '(u.open_path = :ox OR u.open_path LIKE :op)';
                $params['ox'] = '/' . $top;
                $params['op'] = $DB->sql_like_escape('/' . $top . '/') . '%';
            }
        }

        // Already enrolled in this course?
        $already = $DB->get_fieldset_sql(
            "SELECT DISTINCT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid", ['cid' => $courseid]);
        if (!empty($already)) {
            [$insql, $inparams] = $DB->get_in_or_equal($already,
                SQL_PARAMS_NAMED, 'au', false);
            $where[] = "u.id $insql";
            $params = array_merge($params, $inparams);
        }
        $wheresql = implode(' AND ', $where);

        $cols = $DB->get_columns('user');
        $extra = '';
        if (isset($cols['open_employeeid'])) {
            $extra .= ', u.open_employeeid';
        }

        $users = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email{$extra}
               FROM {user} u
              WHERE $wheresql
           ORDER BY u.lastname ASC, u.firstname ASC",
            $params, 0, 2000);

        $opts = [];
        foreach ($users as $u) {
            $label = trim(($u->firstname ?? '') . ' ' . ($u->lastname ?? ''));
            if (!empty($u->open_employeeid ?? '')) {
                $label .= ' [' . format_string($u->open_employeeid) . ']';
            }
            $label .= ' — ' . $u->email;
            $opts[(int) $u->id] = $label;
        }

        $mform->addElement('select', 'userids', 'Users to enrol', $opts,
            ['multiple' => 'multiple',
             'size' => min(20, max(5, count($opts)))]);
        $mform->setType('userids', PARAM_INT);
        $mform->addRule('userids', null, 'required', null, 'client');
        $mform->addElement('static', 'hint', '',
            '<small class="text-muted">Hold Ctrl/Cmd to select multiple. '
            . 'Already-enrolled users do not appear in the list.</small>');

        if (empty($opts)) {
            $mform->addElement('static', 'no_users', '',
                '<p class="text-muted fst-italic">All eligible users in '
                . 'your tenant are already enrolled in this course.</p>');
        }
    }

    public function process_dynamic_submission() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/enrol/manual/locallib.php');

        $data = $this->get_data();
        $courseid = (int) $data->courseid;
        $roleid = (int) ($data->roleid ?? 0);
        $userids = is_array($data->userids ?? null)
            ? array_map('intval', $data->userids) : [];

        $instance = $DB->get_record('enrol',
            ['courseid' => $courseid, 'enrol' => 'manual', 'status' => 0]);
        if (!$instance) {
            throw new \moodle_exception(
                'No active manual enrolment method on this course.');
        }
        $manual = enrol_get_plugin('manual');

        $enrolled = 0; $skipped = 0;
        foreach ($userids as $uid) {
            if ($DB->record_exists('user_enrolments',
                ['enrolid' => $instance->id, 'userid' => $uid])) {
                $skipped++;
                continue;
            }
            $manual->enrol_user($instance, $uid, $roleid, 0, 0,
                ENROL_USER_ACTIVE);
            $enrolled++;
        }

        return [
            'courseid' => $courseid,
            'enrolled' => $enrolled,
            'skipped'  => $skipped,
            'message'  => "$enrolled enrolled, $skipped skipped.",
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        // Defaults set in definition().
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_courses:enrol',
            $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/airpay_courses/index.php');
    }
}
