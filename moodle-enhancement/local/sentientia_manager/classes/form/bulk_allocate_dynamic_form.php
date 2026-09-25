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

/**
 * Bulk allocate one course to N direct reports.
 */
class bulk_allocate_dynamic_form extends dynamic_form {

    protected function definition(): void {
        global $USER;
        $mform = $this->_form;

        // Multi-select user picker. ADR-031: the manager's direct reports inside
        // their own tenant; only a cross-tenant manager with no reports falls
        // back to any active user. bulk_allocate() enforces the same per row.
        $useropts = approval_manager::allocatable_user_options((int) $USER->id);

        $select = $mform->addElement('select', 'userids', 'Direct reports', $useropts);
        $select->setMultiple(true);
        $mform->addRule('userids', null, 'required', null, 'client');

        // Course picker — visible courses in the manager's tenant.
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
        $userids = is_array($data->userids) ? array_map('intval', $data->userids) : [];
        $result = approval_manager::bulk_allocate(
            (int) $USER->id, $userids, (int) $data->courseid,
            !empty($data->due_date) ? (int) $data->due_date : null,
            (string) ($data->note ?? ''));
        return [
            'succeeded_count' => count($result['succeeded']),
            'skipped_count'   => count($result['skipped']),
            'failed_count'    => count($result['failed']),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        $this->set_data((object) [
            'userids' => [], 'courseid' => 0, 'due_date' => 0, 'note' => '',
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/sentientia_manager/allocations.php');
    }
}
