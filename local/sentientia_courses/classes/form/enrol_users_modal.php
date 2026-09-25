<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase F.5 (2026-05-08) — modal form: enrol one or more users in a course.
// Replaces the deep-link to /enrol/users.php with an in-page modal.

namespace local_sentientia_courses\form;

defined('MOODLE_INTERNAL') || die();

class enrol_users_modal extends \core_form\dynamic_form {

    protected function definition() {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/enrol/locallib.php');

        $mform = $this->_form;
        $courseid = (int) $this->optional_param('courseid', 0, PARAM_INT);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // ADR-031: the caller's tenant (null = cross-tenant) and the roles
        // they may give in this course - course_manager::enrol_role_choices(),
        // the same list the enrol CSV checks: for a scoped caller only roles
        // they may assign here, never manager / coursecreator / a site-level
        // role, and learner roles only in a course their tenant does not own.
        // check_access_for_dynamic_submission() has already refused a course
        // outside the caller's scope.
        $root = \local_sentientia_courses\course_manager::enrol_scope_root();
        $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
        $roles = $course
            ? \local_sentientia_courses\course_manager::enrol_role_choices($course, $root)
            : [];

        // Role dropdown (BizLMS uses 'employee').
        $role_options = [];
        foreach ($roles as $r) {
            $label = format_string($r->name) ?: $r->shortname;
            $role_options[(int) $r->id] = $label . ' (' . $r->shortname . ')';
        }
        // Default = employee/student, among the roles actually offered.
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

        // ADR-031: path_filter() is 1=1 for a cross-tenant caller and 1=0 for
        // one with no tenant. The inline version added no clause at all when
        // the caller's open_path did not resolve, which listed (and let the
        // caller enrol) up to 2000 users from every tenant.
        [$tusql, $tuargs] = \local_sentientia_platform\tenant::path_filter('u');
        $where[] = $tusql;
        $params = array_merge($params, $tuargs);

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

        // ADR-031: second line of defence behind the picker's options - the
        // course and EVERY submitted user must be in the caller's scope, and
        // the role must be one the caller may give in this course.
        $root = \local_sentientia_courses\course_manager::require_enrol_scope($courseid, $userids);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $choices = \local_sentientia_courses\course_manager::enrol_role_choices($course, $root);
        if (!array_key_exists($roleid, $choices)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }

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
        require_capability('local/sentientia_courses:enrol',
            $this->get_context_for_dynamic_submission());
        // ADR-031: runs before definition() on load AND on submit. Refuses a
        // scoped caller with no tenant (invalidtenant) and a course outside
        // the caller's tenant (error_outoftenant); cross-tenant callers pass.
        $courseid = (int) $this->optional_param('courseid', 0, PARAM_INT);
        \local_sentientia_courses\course_manager::require_enrol_scope($courseid);
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_courses/index.php');
    }
}
