<?php
// This file is part of Moodle - http://moodle.org/
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_programs\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit certification program dynamic form.
 *
 * @package    local_airpay_programs
 */
class edit_program extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $programid = (int) ($this->optional_param('programid', 0, PARAM_INT));
        $iscreate = ($programid === 0);

        $mform->addElement('hidden', 'programid', $programid);
        $mform->setType('programid', PARAM_INT);

        // ── Basic info ────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_airpay_programs'));

        $mform->addElement('text', 'name', get_string('name', 'local_airpay_programs'),
            ['size' => 50, 'maxlength' => 254]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description', 'local_airpay_programs'),
            ['rows' => 5, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        // ── Organisation ──────────────────────────────────────────────
        $mform->addElement('header', 'hdr_org', get_string('heading_org', 'local_airpay_programs'));

        $orgs = $this->get_org_options();
        $mform->addElement('select', 'costcenterid', get_string('organisation', 'local_airpay_programs'), $orgs);
        $mform->setType('costcenterid', PARAM_INT);

        // ── Completion rules ──────────────────────────────────────────
        $mform->addElement('header', 'hdr_completion', get_string('heading_completion', 'local_airpay_programs'));

        $completion_options = [
            1 => get_string('completion_all_levels', 'local_airpay_programs'),
            0 => get_string('completion_any_level', 'local_airpay_programs'),
        ];
        $mform->addElement('select', 'completion_required',
            get_string('completion_rule', 'local_airpay_programs'), $completion_options);
        $mform->setType('completion_required', PARAM_INT);
        $mform->setDefault('completion_required', 1);

        // ── Status ────────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_status', get_string('heading_status', 'local_airpay_programs'));

        $statusoptions = [
            \local_airpay_programs\program_manager::STATUS_DRAFT    => get_string('status_draft', 'local_airpay_programs'),
            \local_airpay_programs\program_manager::STATUS_ACTIVE   => get_string('status_active', 'local_airpay_programs'),
            \local_airpay_programs\program_manager::STATUS_ARCHIVED => get_string('status_archived', 'local_airpay_programs'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'local_airpay_programs'), $statusoptions);
        $mform->setType('status', PARAM_INT);
        $mform->setDefault('status', \local_airpay_programs\program_manager::STATUS_DRAFT);
    }

    public function validation($data, $files) {
        return [];
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $programid = (int) $data->programid;

        if ($programid === 0) {
            $newid = \local_airpay_programs\program_manager::create($data);
            return ['programid' => $newid, 'message' => get_string('programcreated', 'local_airpay_programs')];
        } else {
            \local_airpay_programs\program_manager::update($programid, $data);
            return ['programid' => $programid, 'message' => get_string('programupdated', 'local_airpay_programs')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $programid = (int) ($this->optional_param('programid', 0, PARAM_INT));

        if ($programid === 0) {
            $this->set_data((object) ['programid' => 0]);
            return;
        }

        $p = $DB->get_record('local_airpay_programs', ['id' => $programid], '*', MUST_EXIST);
        $this->set_data((object) [
            'programid'           => $p->id,
            'name'                => $p->name,
            'description'         => $p->description ?? '',
            'costcenterid'        => $p->costcenterid ?? 0,
            'completion_required' => $p->completion_required ?? 1,
            'status'              => $p->status ?? 0,
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/airpay_programs/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        $programid = (int) ($this->optional_param('programid', 0, PARAM_INT));
        if ($programid === 0) {
            require_capability('local/airpay_programs:create', $context);
        } else {
            require_capability('local/airpay_programs:update', $context);
        }
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    private function get_org_options(): array {
        global $DB;
        $orgs = $DB->get_records('local_airpay_org', ['visible' => 1],
            'depth ASC, fullname ASC', 'id, fullname, depth');
        $options = [0 => '— No specific organisation —'];
        foreach ($orgs as $o) {
            $indent = str_repeat('— ', max(0, $o->depth - 1));
            $options[$o->id] = $indent . format_string($o->fullname);
        }
        return $options;
    }
}
