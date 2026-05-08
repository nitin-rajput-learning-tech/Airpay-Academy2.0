<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_manager\form;

defined('MOODLE_INTERNAL') || die();

use core_form\dynamic_form;
use moodle_url;
use context;
use context_system;
use local_airpay_manager\approval_manager;

class create_allocation_dynamic_form extends dynamic_form {

    protected function definition(): void {
        global $DB, $USER;
        $mform = $this->_form;

        // Build direct-report option list. On stock Moodle (no
        // open_supervisorid) this'll be empty; admins can still allocate
        // because we skip the gate when no reports exist.
        $reportids = approval_manager::direct_report_ids((int) $USER->id);
        $useropts = [0 => 'Select a direct report...'];
        if (!empty($reportids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($reportids, SQL_PARAMS_NAMED, 'uid');
            $users = $DB->get_records_sql(
                "SELECT id, firstname, lastname, email
                   FROM {user} WHERE id $insql AND deleted = 0
               ORDER BY lastname ASC, firstname ASC", $inparams);
            foreach ($users as $u) {
                $useropts[$u->id] = fullname($u) . ' <' . s($u->email) . '>';
            }
        } else {
            // Fallback: allow selecting any non-suspended, non-deleted user.
            $allusers = $DB->get_records_sql(
                "SELECT id, firstname, lastname, email
                   FROM {user}
                  WHERE deleted = 0 AND suspended = 0 AND id > 2
               ORDER BY lastname ASC, firstname ASC
                 LIMIT 200");
            foreach ($allusers as $u) {
                $useropts[$u->id] = fullname($u) . ' <' . s($u->email) . '>';
            }
        }

        $mform->addElement('select', 'userid', 'Direct report', $useropts);
        $mform->addRule('userid', null, 'required', null, 'client');

        // Course list — visible courses only.
        $courses = $DB->get_records_select('course',
            'visible = 1 AND id > 1', null,
            'fullname ASC', 'id, fullname, shortname', 0, 200);
        $courseopts = [0 => 'Select a course...'];
        foreach ($courses as $c) {
            $courseopts[$c->id] = format_string($c->fullname) . ' (' . s($c->shortname) . ')';
        }
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
        require_capability('local/airpay_manager:allocate',
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
        return new moodle_url('/local/airpay_manager/allocations.php');
    }
}
