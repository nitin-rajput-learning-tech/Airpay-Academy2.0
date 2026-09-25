<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_manager\form;

defined('MOODLE_INTERNAL') || die();

use core_form\dynamic_form;
use moodle_url;
use context;
use context_system;
use local_sentientia_manager\approval_manager;

class create_allocation_dynamic_form extends dynamic_form {

    protected function definition(): void {
        global $USER;
        $mform = $this->_form;

        // ADR-031: the manager's direct reports inside their own tenant (only a
        // cross-tenant manager with no reports falls back to any active user),
        // and only courses in that tenant. approval_manager::create_allocation()
        // enforces the same bounds on submit.
        $useropts = [0 => 'Select a direct report...']
            + approval_manager::allocatable_user_options((int) $USER->id);

        $mform->addElement('select', 'userid', 'Direct report', $useropts);
        $mform->addRule('userid', null, 'required', null, 'client');

        // Course list — visible courses only, in the manager's tenant.
        $courseopts = [0 => 'Select a course...']
            + approval_manager::allocatable_course_options((int) $USER->id);
        $mform->addElement('select', 'courseid', 'Course', $courseopts);
        $mform->addRule('courseid', null, 'required', null, 'client');

        $mform->addElement('date_selector', 'due_date', 'Due date',
            ['optional' => true]);

        $mform->addElement('textarea', 'note', 'Note (optional)',
            ['rows' => 2, 'cols' => 60]);
        $mform->setType('note', PARAM_TEXT);
    }

    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_manager:allocate',
            $this->get_context_for_dynamic_submission());
    }

    public function process_dynamic_submission() {
        global $USER;
        $data = $this->get_data();
        $id = approval_manager::create_allocation(
            (int) $USER->id,
            (int) $data->userid,
            (int) $data->courseid,
            !empty($data->due_date) ? (int) $data->due_date : null,
            (string) ($data->note ?? ''));
        return ['id' => $id];
    }

    public function set_data_for_dynamic_submission(): void {
        $this->set_data((object) [
            'userid' => 0, 'courseid' => 0, 'due_date' => 0, 'note' => '',
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/sentientia_manager/allocations.php');
    }
}
