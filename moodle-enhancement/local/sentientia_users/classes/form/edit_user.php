<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_sentientia_users\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit user dynamic form.
 *
 * Same form handles both flows — userid=0 means "create new", userid>0 means
 * "edit existing". This reduces duplication and keeps validation rules consistent.
 *
 * Uses Moodle 5's core_form/dynamic_form pattern — no XML services, no AJAX
 * boilerplate. Loaded via core_form/modal_form AMD module.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_user extends \core_form\dynamic_form {

    /**
     * Build the form fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $userid = (int) ($this->optional_param('userid', 0, PARAM_INT));
        $iscreate = ($userid === 0);

        // Hidden userid (so submit knows which mode).
        $mform->addElement('hidden', 'userid', $userid);
        $mform->setType('userid', PARAM_INT);

        // ── Account section ───────────────────────────────────────────
        $mform->addElement('header', 'hdr_account', get_string('heading_account', 'local_sentientia_users'));

        // Username — only on create. Locked on edit (changing username is a separate workflow).
        if ($iscreate) {
            $mform->addElement('text', 'username', get_string('username', 'local_sentientia_users'),
                ['size' => 30, 'maxlength' => 100]);
            $mform->setType('username', PARAM_USERNAME);
            $mform->addRule('username', null, 'required', null, 'client');
            $mform->addHelpButton('username', 'username', 'local_sentientia_users');
        }

        $mform->addElement('text', 'email', get_string('email', 'local_sentientia_users'),
            ['size' => 40, 'maxlength' => 100]);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required', null, 'client');

        $mform->addElement('text', 'firstname', get_string('firstname', 'local_sentientia_users'),
            ['size' => 25, 'maxlength' => 100]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');

        $mform->addElement('text', 'lastname', get_string('lastname', 'local_sentientia_users'),
            ['size' => 25, 'maxlength' => 100]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');

        // Auth method — only on create.
        if ($iscreate) {
            $authchoices = [
                'manual' => 'Manual (admin sets password)',
                'oauth2' => 'SSO / OAuth2',
                'ldap'   => 'LDAP / Active Directory',
            ];
            $mform->addElement('select', 'auth', get_string('authmethod', 'local_sentientia_users'), $authchoices);
            $mform->setDefault('auth', 'manual');
        }

        // ── Personal Details ──────────────────────────────────────────
        $mform->addElement('header', 'hdr_personal', get_string('heading_personal', 'local_sentientia_users'));

        $mform->addElement('text', 'open_employeeid', get_string('employeeid', 'local_sentientia_users'),
            ['size' => 20, 'maxlength' => 50]);
        $mform->setType('open_employeeid', PARAM_TEXT);

        $mform->addElement('text', 'open_designation', get_string('designation', 'local_sentientia_users'),
            ['size' => 30, 'maxlength' => 100]);
        $mform->setType('open_designation', PARAM_TEXT);

        $mform->addElement('text', 'phone1', get_string('phone', 'local_sentientia_users'),
            ['size' => 20, 'maxlength' => 30]);
        $mform->setType('phone1', PARAM_TEXT);

        $mform->addElement('text', 'open_location', get_string('location', 'local_sentientia_users'),
            ['size' => 25, 'maxlength' => 100]);
        $mform->setType('open_location', PARAM_TEXT);

        // P1 batch (2026-05-16) — DOB + DOJ on admin edit form.
        //
        // Before this, admins could not fix HR errors in DOB/DOJ from the
        // Airpay UI — they had to drop into Moodle's core
        // `/user/editadvanced.php`, which is BizLMS-unaware and exposes
        // every other core field on the same page (a confusing detour).
        //
        // Both fields are optional (date_selector ?optional=true) and
        // empty input is stored as NULL by user_manager::apply_custom_fields()
        // so we don't end up with users born on 1970-01-01.
        $mform->addElement('date_selector', 'open_dateofbirth',
            get_string('open_dateofbirth', 'local_sentientia_users'),
            ['optional' => true, 'startyear' => 1940,
             'stopyear' => (int) date('Y')]);
        $mform->setType('open_dateofbirth', PARAM_INT);
        $mform->addHelpButton('open_dateofbirth', 'open_dateofbirth',
            'local_sentientia_users');

        $mform->addElement('date_selector', 'open_joindate',
            get_string('open_joindate', 'local_sentientia_users'),
            ['optional' => true, 'startyear' => 1990,
             'stopyear' => (int) date('Y') + 1]);
        $mform->setType('open_joindate', PARAM_INT);
        $mform->addHelpButton('open_joindate', 'open_joindate',
            'local_sentientia_users');

        // ── Organisation ──────────────────────────────────────────────
        $mform->addElement('header', 'hdr_org', get_string('heading_organisation', 'local_sentientia_users'));

        $orgs = $this->get_org_options();
        $mform->addElement('select', 'open_costcenterid', get_string('organisation', 'local_sentientia_users'), $orgs);
        $mform->setType('open_costcenterid', PARAM_INT);

        $mform->addElement('text', 'department', get_string('department', 'local_sentientia_users'),
            ['size' => 30, 'maxlength' => 100]);
        $mform->setType('department', PARAM_TEXT);

        // P1 batch (2026-05-16) — tenant-scoped reporting-manager autocomplete.
        // Previously this used core_user/form_user_selector which is NOT
        // tenant-aware; a Public-tenant admin could pick an Airpay-tenant
        // manager and silently break the org chart. The new selector calls
        // `local_sentientia_users_search_supervisors` and intersects scope with
        // both the caller's tenant AND (when editing) the subject's tenant.
        $mgr_options = [
            'multiple' => false,
            'ajax' => 'local_sentientia_users/supervisor_selector',
            'noselectionstring' => '— No supervisor —',
            'valuehtmlcallback' => function ($userid) {
                $user = \core_user::get_user($userid);
                if (!$user) {
                    return false;
                }
                return fullname($user) . ' (' . s($user->email) . ')';
            },
        ];
        $mform->addElement('autocomplete', 'open_supervisorid',
            get_string('supervisor', 'local_sentientia_users'), [], $mgr_options);
        $mform->setType('open_supervisorid', PARAM_INT);
        $mform->addHelpButton('open_supervisorid', 'supervisor',
            'local_sentientia_users');

        // ── Password section ──────────────────────────────────────────
        if ($iscreate) {
            $mform->addElement('header', 'hdr_password', get_string('heading_password', 'local_sentientia_users'));
            $mform->setExpanded('hdr_password');

            $mform->addElement('passwordunmask', 'password',
                get_string('password', 'local_sentientia_users'),
                ['size' => 30]);
            $mform->setType('password', PARAM_RAW);
            // Password required only when manual auth selected.
            $mform->disabledIf('password', 'auth', 'neq', 'manual');

            $mform->addElement('advcheckbox', 'emailwelcome',
                get_string('emailwelcome', 'local_sentientia_users'));
            $mform->setDefault('emailwelcome', 1);
        } else {
            // Edit mode — password reset (optional, blank = no change).
            $mform->addElement('header', 'hdr_password', get_string('heading_password', 'local_sentientia_users'));
            $mform->addElement('passwordunmask', 'newpassword',
                get_string('newpassword', 'local_sentientia_users'),
                ['size' => 30]);
            $mform->setType('newpassword', PARAM_RAW);
            $mform->addHelpButton('newpassword', 'newpassword', 'local_sentientia_users');
        }
    }

    /**
     * Custom validation.
     */
    public function validation($data, $files) {
        global $DB, $CFG;
        $errors = [];

        $userid = (int) ($data['userid'] ?? 0);
        $iscreate = ($userid === 0);

        // Email must be unique.
        if (!empty($data['email'])) {
            $sql = "email = :email AND deleted = 0";
            $params = ['email' => $data['email']];
            if (!$iscreate) {
                $sql .= " AND id != :uid";
                $params['uid'] = $userid;
            }
            if ($DB->record_exists_select('user', $sql, $params)) {
                $errors['email'] = get_string('emailtaken', 'local_sentientia_users');
            }
        }

        // Username must be unique (create only).
        if ($iscreate && !empty($data['username'])) {
            if ($DB->record_exists('user', [
                'username' => strtolower($data['username']),
                'mnethostid' => $CFG->mnet_localhost_id,
            ])) {
                $errors['username'] = get_string('usernametaken', 'local_sentientia_users');
            }
        }

        // Manual auth requires password on create.
        if ($iscreate && ($data['auth'] ?? 'manual') === 'manual' && empty($data['password'])) {
            $errors['password'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Process form submission.
     *
     * @return array  Response data sent back to JS (e.g. {userid: X, message: "..."})
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $userid = (int) $data->userid;

        if ($userid === 0) {
            // Create.
            $newid = \local_sentientia_users\user_manager::create($data);
            return [
                'userid' => $newid,
                'message' => get_string('usercreated', 'local_sentientia_users'),
            ];
        } else {
            // Update.
            \local_sentientia_users\user_manager::update($userid, $data);
            return [
                'userid' => $userid,
                'message' => get_string('userupdated', 'local_sentientia_users'),
            ];
        }
    }

    /**
     * Pre-fill form with existing user data.
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $userid = (int) ($this->optional_param('userid', 0, PARAM_INT));

        if ($userid === 0) {
            $this->set_data((object) ['userid' => 0]);
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

        // Resolve org id from open_path (open_costcenterid column does not
        // exist on production — open_path is canonical).
        $orgid = 0;
        if (!empty($user->open_path)) {
            $org = $DB->get_record('local_airpay_org', ['path' => $user->open_path], 'id');
            if ($org) {
                $orgid = (int) $org->id;
            }
        }

        $data = (object) [
            'userid' => $user->id,
            'email' => $user->email,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'open_employeeid' => $user->open_employeeid ?? '',
            'open_designation' => $user->open_designation ?? '',
            'phone1' => $user->phone1 ?? '',
            'open_location' => $user->open_location ?? '',
            // P1 batch (2026-05-16) — DOB + DOJ pre-fill from DB.
            'open_dateofbirth' => (int) ($user->open_dateofbirth ?? 0),
            'open_joindate'   => (int) ($user->open_joindate   ?? 0),
            'department' => $user->department ?? '',
            'open_costcenterid' => $orgid,
            'open_supervisorid' => $user->open_supervisorid ?? 0,
        ];

        $this->set_data($data);
    }

    /**
     * The page URL where this form is rendered (fallback for non-JS).
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_users/index.php');
    }

    /**
     * Capability check — must have edit (for update) or create (for create).
     */
    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        $userid = (int) ($this->optional_param('userid', 0, PARAM_INT));

        if ($userid === 0) {
            require_capability('local/sentientia_users:create', $context);
        } else {
            require_capability('local/sentientia_users:edit', $context);
        }
    }

    /**
     * Context for capability checks.
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Get organisation options for dropdown.
     */
    private function get_org_options(): array {
        global $DB;
        $orgs = $DB->get_records('local_airpay_org', ['visible' => 1],
            'depth ASC, fullname ASC', 'id, fullname, depth');

        $options = [0 => '— Select organisation —'];
        foreach ($orgs as $o) {
            // Indent by depth for visual hierarchy.
            $indent = str_repeat('— ', max(0, $o->depth - 1));
            $options[$o->id] = $indent . format_string($o->fullname);
        }
        return $options;
    }
}
