<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_reports\form;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_reports\report_manager;

/**
 * Create / edit saved report definition (dynamic_form).
 *
 * @package    local_sentientia_reports
 */
class edit_report extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $reportid = (int) ($this->optional_param('reportid', 0, PARAM_INT));

        $mform->addElement('hidden', 'reportid', $reportid);
        $mform->setType('reportid', PARAM_INT);

        // ── Identity ──────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_sentientia_reports'));

        $mform->addElement('text', 'name', get_string('report_name', 'local_sentientia_reports'),
            ['size' => 50, 'maxlength' => 254, 'placeholder' => 'e.g. Monthly Compliance Snapshot']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description',
            get_string('description', 'local_sentientia_reports'),
            ['rows' => 3, 'cols' => 50, 'placeholder' => 'Optional. What does this report show?']);
        $mform->setType('description', PARAM_TEXT);

        // ── Report type ───────────────────────────────────────────────
        $mform->addElement('header', 'hdr_type', get_string('heading_type', 'local_sentientia_reports'));

        $mform->addElement('select', 'report_type',
            get_string('report_type', 'local_sentientia_reports'),
            report_manager::REPORT_TYPES);
        $mform->setType('report_type', PARAM_ALPHAEXT);
        $mform->setDefault('report_type', 'course_completion');
        $mform->addHelpButton('report_type', 'report_type', 'local_sentientia_reports');

        // ── Organisation scope ────────────────────────────────────────
        $mform->addElement('header', 'hdr_scope', get_string('heading_scope', 'local_sentientia_reports'));

        $orgs = $this->get_org_options();
        $mform->addElement('select', 'costcenterid',
            get_string('organisation', 'local_sentientia_reports'), $orgs);
        $mform->setType('costcenterid', PARAM_INT);
        $mform->addHelpButton('costcenterid', 'organisation', 'local_sentientia_reports');

        // ── Status ────────────────────────────────────────────────────
        $statusoptions = [
            report_manager::STATUS_ACTIVE   => get_string('status_active', 'local_sentientia_reports'),
            report_manager::STATUS_ARCHIVED => get_string('status_archived', 'local_sentientia_reports'),
        ];
        $mform->addElement('select', 'status',
            get_string('status', 'local_sentientia_reports'), $statusoptions);
        $mform->setType('status', PARAM_INT);
        $mform->setDefault('status', report_manager::STATUS_ACTIVE);
    }

    public function validation($data, $files) {
        $errors = [];
        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = get_string('name_required', 'local_sentientia_reports');
        }
        if (!array_key_exists($data['report_type'] ?? '', report_manager::REPORT_TYPES)) {
            $errors['report_type'] = get_string('invalid_report_type', 'local_sentientia_reports');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $reportid = (int) $data->reportid;

        if ($reportid === 0) {
            $newid = report_manager::create($data);
            return [
                'reportid' => $newid,
                'message'  => get_string('report_created', 'local_sentientia_reports'),
            ];
        } else {
            report_manager::update($reportid, $data);
            return [
                'reportid' => $reportid,
                'message'  => get_string('report_updated', 'local_sentientia_reports'),
            ];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $reportid = (int) ($this->optional_param('reportid', 0, PARAM_INT));

        if ($reportid === 0) {
            $this->set_data((object) [
                'reportid'    => 0,
                'report_type' => 'course_completion',
                'status'      => report_manager::STATUS_ACTIVE,
                'costcenterid' => 0,
            ]);
            return;
        }

        $r = $DB->get_record('local_sentientia_reports', ['id' => $reportid], '*', MUST_EXIST);
        $this->set_data((object) [
            'reportid'     => $r->id,
            'name'         => $r->name,
            'description'  => $r->description ?? '',
            'report_type'  => $r->report_type,
            'costcenterid' => $r->costcenterid ?? 0,
            'status'       => (int) $r->status,
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_reports/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_reports:manage', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Build hierarchical org dropdown using local_airpay_org.
     */
    private function get_org_options(): array {
        global $DB;
        $options = [0 => '— All organisations —'];
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_airpay_org')) {
            return $options;
        }
        $orgs = $DB->get_records('local_airpay_org', ['visible' => 1],
            'depth ASC, fullname ASC', 'id, fullname, depth');
        foreach ($orgs as $o) {
            $indent = str_repeat('— ', max(0, $o->depth - 1));
            $options[$o->id] = $indent . format_string($o->fullname);
        }
        return $options;
    }
}
