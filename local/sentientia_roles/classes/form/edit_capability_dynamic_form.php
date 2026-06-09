<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\form;

defined('MOODLE_INTERNAL') || die();

use core_form\dynamic_form;
use moodle_url;
use context;
use context_system;
use local_sentientia_roles\role_manager;

/**
 * Modal form for editing a single capability permission on a role.
 *
 * Server-side validation rejects unknown caps + unknown perms.
 * Persistence happens via {@see role_manager::update_capability()}
 * (NOT via direct $DB writes) so the audit log is always written.
 *
 * @package local_sentientia_roles
 */
class edit_capability_dynamic_form extends dynamic_form {

    protected function definition(): void {
        $mform = $this->_form;

        $roleid     = $this->optional_param('roleid', 0, PARAM_INT);
        $capability = $this->optional_param('capability', '', PARAM_RAW_TRIMMED);
        $current    = $this->optional_param('current', CAP_INHERIT, PARAM_INT);

        $mform->addElement('hidden', 'roleid', $roleid);
        $mform->setType('roleid', PARAM_INT);

        // Read-only display of role + capability so the admin sees what
        // they're about to change.
        if ($roleid > 0) {
            $role = role_manager::get_role($roleid);
            $mform->addElement('static', 'rolename',
                get_string('col_name', 'local_sentientia_roles'),
                '<strong>' . s($role['name']) . '</strong> '
                . '<code class="text-muted ml-2">' . s($role['shortname']) . '</code>');
        }

        $mform->addElement('hidden', 'capability', $capability);
        $mform->setType('capability', PARAM_RAW_TRIMMED);
        if ($capability !== '') {
            $mform->addElement('static', 'capability_label',
                get_string('form_capability', 'local_sentientia_roles'),
                '<code>' . s($capability) . '</code>');
        }

        // Permission select.
        $perms = [
            'inherit'  => get_string('cap_perm_inherit',  'local_sentientia_roles'),
            'allow'    => get_string('cap_perm_allow',    'local_sentientia_roles'),
            'prevent'  => get_string('cap_perm_prevent',  'local_sentientia_roles'),
            'prohibit' => get_string('cap_perm_prohibit', 'local_sentientia_roles'),
        ];
        $mform->addElement('select', 'permission',
            get_string('form_permission', 'local_sentientia_roles'), $perms);
        $mform->setDefault('permission', role_manager::permission_to_string((int) $current));
        $mform->addRule('permission', null, 'required', null, 'client');

        // Reason (optional).
        $mform->addElement('textarea', 'reason',
            get_string('form_reason', 'local_sentientia_roles'),
            ['rows' => 2, 'cols' => 60, 'maxlength' => 1024]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addHelpButton('reason', 'form_reason', 'local_sentientia_roles');
    }

    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_roles:manage', $this->get_context_for_dynamic_submission());
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        return role_manager::update_capability(
            (int) $data->roleid,
            (string) $data->capability,
            (string) $data->permission,
            (string) ($data->reason ?? '')
        );
    }

    public function set_data_for_dynamic_submission(): void {
        $roleid     = $this->optional_param('roleid', 0, PARAM_INT);
        $capability = $this->optional_param('capability', '', PARAM_RAW_TRIMMED);
        $current    = $this->optional_param('current', CAP_INHERIT, PARAM_INT);
        $this->set_data((object) [
            'roleid'     => $roleid,
            'capability' => $capability,
            'permission' => role_manager::permission_to_string((int) $current),
            'reason'     => '',
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $roleid = $this->optional_param('roleid', 0, PARAM_INT);
        return new moodle_url('/local/sentientia_roles/view.php',
            ['id' => $roleid, 'tab' => 'capabilities']);
    }
}
