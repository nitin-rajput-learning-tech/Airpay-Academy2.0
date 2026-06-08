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

namespace local_sentientia_learningpath\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit learning path dynamic form.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_path extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $pathid = (int) ($this->optional_param('pathid', 0, PARAM_INT));
        $iscreate = ($pathid === 0);

        $mform->addElement('hidden', 'pathid', $pathid);
        $mform->setType('pathid', PARAM_INT);

        // ── Basic info ────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_sentientia_learningpath'));

        $mform->addElement('text', 'name', get_string('name', 'local_sentientia_learningpath'),
            ['size' => 50, 'maxlength' => 254]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // P1 batch (2026-05-16) — swap raw textarea for the rich-text editor so
        // admins can embed images/links/formatted instructions. The editor
        // element returns an array [text, format]; we unpack it in process().
        $mform->addElement('editor', 'description_editor',
            get_string('description', 'local_sentientia_learningpath'),
            ['rows' => 10, 'cols' => 80],
            ['noclean' => true, 'subdirs' => 0, 'maxfiles' => 0,
             'enable_filemanagement' => false]);
        $mform->setType('description_editor', PARAM_RAW);

        // ── Organisation ──────────────────────────────────────────────
        $mform->addElement('header', 'hdr_org', get_string('heading_org', 'local_sentientia_learningpath'));

        $orgs = $this->get_org_options();
        $mform->addElement('select', 'costcenterid', get_string('organisation', 'local_sentientia_learningpath'), $orgs);
        $mform->setType('costcenterid', PARAM_INT);
        $mform->addHelpButton('costcenterid', 'organisation', 'local_sentientia_learningpath');

        // ── Compliance window (P1 batch 2026-05-16) ──────────────────
        // Both dates optional; together they bound when the path is
        // enrollable.
        $mform->addElement('header', 'hdr_window',
            get_string('heading_window', 'local_sentientia_learningpath'));

        $mform->addElement('date_selector', 'startdate',
            get_string('startdate', 'local_sentientia_learningpath'),
            ['optional' => true]);
        $mform->setType('startdate', PARAM_INT);
        $mform->addHelpButton('startdate', 'startdate', 'local_sentientia_learningpath');

        $mform->addElement('date_selector', 'enddate',
            get_string('enddate', 'local_sentientia_learningpath'),
            ['optional' => true]);
        $mform->setType('enddate', PARAM_INT);
        $mform->addHelpButton('enddate', 'enddate', 'local_sentientia_learningpath');

        // ── Status ────────────────────────────────────────────────────
        if (!$iscreate) {
            $mform->addElement('header', 'hdr_status', get_string('heading_status', 'local_sentientia_learningpath'));
            $statusoptions = [
                \local_sentientia_learningpath\path_manager::STATUS_ACTIVE   => get_string('status_active', 'local_sentientia_learningpath'),
                \local_sentientia_learningpath\path_manager::STATUS_ARCHIVED => get_string('status_archived', 'local_sentientia_learningpath'),
            ];
            $mform->addElement('select', 'status', get_string('status', 'local_sentientia_learningpath'), $statusoptions);
            $mform->setType('status', PARAM_INT);
        }
    }

    public function validation($data, $files) {
        $errors = [];

        // P1 batch (2026-05-16) — enddate must be on/after startdate when
        // both supplied. Either being 0 / empty means "no bound".
        $start = (int) ($data['startdate'] ?? 0);
        $end   = (int) ($data['enddate']   ?? 0);
        if ($start > 0 && $end > 0 && $end < $start) {
            $errors['enddate'] = get_string('enddate_before_start',
                'local_sentientia_learningpath');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $pathid = (int) $data->pathid;

        // P1 batch (2026-05-16) — editor element returns
        // ['text' => '...', 'format' => N]. Flatten into the columns the
        // path_manager expects.
        if (isset($data->description_editor) && is_array($data->description_editor)) {
            $data->description = (string) ($data->description_editor['text'] ?? '');
            $data->descriptionformat =
                (int) ($data->description_editor['format'] ?? FORMAT_HTML);
        }

        if ($pathid === 0) {
            $newid = \local_sentientia_learningpath\path_manager::create($data);
            return ['pathid' => $newid, 'message' => get_string('pathcreated', 'local_sentientia_learningpath')];
        } else {
            \local_sentientia_learningpath\path_manager::update($pathid, $data);
            return ['pathid' => $pathid, 'message' => get_string('pathupdated', 'local_sentientia_learningpath')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $pathid = (int) ($this->optional_param('pathid', 0, PARAM_INT));

        if ($pathid === 0) {
            $this->set_data((object) [
                'pathid' => 0,
                'description_editor' => [
                    'text'   => '',
                    'format' => FORMAT_HTML,
                ],
            ]);
            return;
        }

        $p = $DB->get_record('local_sentientia_learningpath', ['id' => $pathid], '*', MUST_EXIST);
        $this->set_data((object) [
            'pathid'       => $p->id,
            'name'         => $p->name,
            // P1 batch — repack the description into editor-element format.
            'description_editor' => [
                'text'   => (string) ($p->description ?? ''),
                'format' => (int) ($p->descriptionformat ?? FORMAT_HTML),
            ],
            'costcenterid' => $p->costcenterid ?? 0,
            'status'       => $p->status ?? 1,
            'startdate'    => (int) ($p->startdate ?? 0),
            'enddate'      => (int) ($p->enddate ?? 0),
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_learningpath/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        $pathid = (int) ($this->optional_param('pathid', 0, PARAM_INT));
        if ($pathid === 0) {
            require_capability('local/sentientia_learningpath:create', $context);
        } else {
            require_capability('local/sentientia_learningpath:update', $context);
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
