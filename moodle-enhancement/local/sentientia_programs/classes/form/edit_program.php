<?php
// This file is part of Moodle - http://moodle.org/
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit certification program dynamic form.
 *
 * @package    local_sentientia_programs
 */
class edit_program extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $programid = (int) ($this->optional_param('programid', 0, PARAM_INT));
        $iscreate = ($programid === 0);

        $mform->addElement('hidden', 'programid', $programid);
        $mform->setType('programid', PARAM_INT);

        // ── Basic info ────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_sentientia_programs'));

        $mform->addElement('text', 'name', get_string('name', 'local_sentientia_programs'),
            ['size' => 50, 'maxlength' => 254]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // P1 #9 (2026-05-16) — swap textarea for rich-text editor (mirrors
        // sentientia_learningpath W2 #2 commit 8df39b36f).
        $mform->addElement('editor', 'description_editor',
            get_string('description', 'local_sentientia_programs'),
            ['rows' => 10, 'cols' => 80],
            ['noclean' => true, 'subdirs' => 0, 'maxfiles' => 0,
             'enable_filemanagement' => false]);
        $mform->setType('description_editor', PARAM_RAW);

        // ── Organisation ──────────────────────────────────────────────
        $mform->addElement('header', 'hdr_org', get_string('heading_org', 'local_sentientia_programs'));

        $orgs = $this->get_org_options();
        $mform->addElement('select', 'costcenterid', get_string('organisation', 'local_sentientia_programs'), $orgs);
        $mform->setType('costcenterid', PARAM_INT);

        // ── Completion rules ──────────────────────────────────────────
        $mform->addElement('header', 'hdr_completion', get_string('heading_completion', 'local_sentientia_programs'));

        $completion_options = [
            1 => get_string('completion_all_levels', 'local_sentientia_programs'),
            0 => get_string('completion_any_level', 'local_sentientia_programs'),
        ];
        $mform->addElement('select', 'completion_required',
            get_string('completion_rule', 'local_sentientia_programs'), $completion_options);
        $mform->setType('completion_required', PARAM_INT);
        $mform->setDefault('completion_required', 1);

        // ── Enrolment window (P1 #9 2026-05-16) ───────────────────────
        $mform->addElement('header', 'hdr_window',
            get_string('heading_window', 'local_sentientia_programs'));
        $mform->addElement('date_selector', 'startdate',
            get_string('startdate', 'local_sentientia_programs'),
            ['optional' => true]);
        $mform->setType('startdate', PARAM_INT);
        $mform->addHelpButton('startdate', 'startdate', 'local_sentientia_programs');

        $mform->addElement('date_selector', 'enddate',
            get_string('enddate', 'local_sentientia_programs'),
            ['optional' => true]);
        $mform->setType('enddate', PARAM_INT);
        $mform->addHelpButton('enddate', 'enddate', 'local_sentientia_programs');

        // ── Status ────────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_status', get_string('heading_status', 'local_sentientia_programs'));

        $statusoptions = [
            \local_sentientia_programs\program_manager::STATUS_DRAFT    => get_string('status_draft', 'local_sentientia_programs'),
            \local_sentientia_programs\program_manager::STATUS_ACTIVE   => get_string('status_active', 'local_sentientia_programs'),
            \local_sentientia_programs\program_manager::STATUS_ARCHIVED => get_string('status_archived', 'local_sentientia_programs'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'local_sentientia_programs'), $statusoptions);
        $mform->setType('status', PARAM_INT);
        $mform->setDefault('status', \local_sentientia_programs\program_manager::STATUS_DRAFT);
    }

    public function validation($data, $files) {
        $errors = [];
        // P1 #9 (2026-05-16) — enrolment-window validation.
        $start = (int) ($data['startdate'] ?? 0);
        $end   = (int) ($data['enddate']   ?? 0);
        if ($start > 0 && $end > 0 && $end < $start) {
            $errors['enddate'] = get_string('enddate_before_start',
                'local_sentientia_programs');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $programid = (int) $data->programid;

        // P1 #9 (2026-05-16) — unpack the editor element into description +
        // descriptionformat columns.
        if (isset($data->description_editor) && is_array($data->description_editor)) {
            $data->description = (string) ($data->description_editor['text'] ?? '');
            $data->descriptionformat =
                (int) ($data->description_editor['format'] ?? FORMAT_HTML);
        }

        if ($programid === 0) {
            $newid = \local_sentientia_programs\program_manager::create($data);
            return ['programid' => $newid, 'message' => get_string('programcreated', 'local_sentientia_programs')];
        } else {
            \local_sentientia_programs\program_manager::update($programid, $data);
            return ['programid' => $programid, 'message' => get_string('programupdated', 'local_sentientia_programs')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $programid = (int) ($this->optional_param('programid', 0, PARAM_INT));

        if ($programid === 0) {
            $this->set_data((object) [
                'programid' => 0,
                'description_editor' => [
                    'text'   => '',
                    'format' => FORMAT_HTML,
                ],
            ]);
            return;
        }

        $p = $DB->get_record('local_sentientia_programs', ['id' => $programid], '*', MUST_EXIST);
        $this->set_data((object) [
            'programid'           => $p->id,
            'name'                => $p->name,
            // P1 #9 — repack description into editor format.
            'description_editor'  => [
                'text'   => (string) ($p->description ?? ''),
                'format' => (int) ($p->descriptionformat ?? FORMAT_HTML),
            ],
            'costcenterid'        => $p->costcenterid ?? 0,
            'completion_required' => $p->completion_required ?? 1,
            'status'              => $p->status ?? 0,
            'startdate'           => (int) ($p->startdate ?? 0),
            'enddate'             => (int) ($p->enddate ?? 0),
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_programs/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        $programid = (int) ($this->optional_param('programid', 0, PARAM_INT));
        if ($programid === 0) {
            require_capability('local/sentientia_programs:create', $context);
        } else {
            require_capability('local/sentientia_programs:update', $context);
        }
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    private function get_org_options(): array {
        global $DB;
        $orgs = $DB->get_records('local_sentientia_org', ['visible' => 1],
            'depth ASC, fullname ASC', 'id, fullname, depth');
        $options = [0 => '— No specific organisation —'];
        foreach ($orgs as $o) {
            $indent = str_repeat('— ', max(0, $o->depth - 1));
            $options[$o->id] = $indent . format_string($o->fullname);
        }
        return $options;
    }
}
