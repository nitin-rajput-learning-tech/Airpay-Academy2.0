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

class decide_request_dynamic_form extends dynamic_form {

    protected function definition(): void {
        global $DB;
        $mform = $this->_form;
        $requestid = $this->optional_param('requestid', 0, PARAM_INT);
        $decision  = $this->optional_param('decision', 'approved', PARAM_ALPHAEXT);

        $mform->addElement('hidden', 'requestid', $requestid);
        $mform->setType('requestid', PARAM_INT);
        $mform->addElement('hidden', 'decision', $decision);
        $mform->setType('decision', PARAM_ALPHAEXT);

        if ($requestid > 0) {
            $r = $DB->get_record_sql("
                SELECT r.*, u.firstname, u.lastname, c.fullname AS coursename
                  FROM {local_airpay_mgr_requests} r
             LEFT JOIN {user}   u ON u.id = r.userid
             LEFT JOIN {course} c ON c.id = r.courseid
                 WHERE r.id = :id", ['id' => $requestid]);
            if ($r) {
                $verb = $decision === 'rejected' ? 'Reject' : 'Approve';
                $name = $r->firstname ? fullname((object) ['firstname' => $r->firstname, 'lastname' => $r->lastname]) : '—';
                $mform->addElement('static', 'summary', '',
                    '<div class="alert alert-light border">'
                    . '<strong>' . $verb . '</strong> request from <em>' . s($name) . '</em>'
                    . ' to enrol in <em>' . s(format_string((string) ($r->coursename ?? ''))) . '</em>'
                    . ($r->reason ? '<div class="mt-2 small text-muted">Reason: '
                        . s($r->reason) . '</div>' : '')
                    . '</div>');
            }
        }

        $mform->addElement('textarea', 'decision_reason', 'Note (optional)',
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('decision_reason', PARAM_TEXT);
    }

    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_manager:approve',
            $this->get_context_for_dynamic_submission());
    }

    public function process_dynamic_submission() {
        global $USER;
        $data = $this->get_data();
        return approval_manager::decide_request(
            (int) $data->requestid, (string) $data->decision,
            (string) ($data->decision_reason ?? ''), (int) $USER->id);
    }

    public function set_data_for_dynamic_submission(): void {
        $this->set_data((object) [
            'requestid' => $this->optional_param('requestid', 0, PARAM_INT),
            'decision'  => $this->optional_param('decision', 'approved', PARAM_ALPHAEXT),
            'decision_reason' => '',
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/airpay_manager/requests.php');
    }
}
