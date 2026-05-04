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

namespace local_airpay_learningpath\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit learning path dynamic form.
 *
 * @package    local_airpay_learningpath
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
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_airpay_learningpath'));

        $mform->addElement('text', 'name', get_string('name', 'local_airpay_learningpath'),
            ['size' => 50, 'maxlength' => 254]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description', 'local_airpay_learningpath'),
            ['rows' => 5, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        // ── Organisation ──────────────────────────────────────────────
        $mform->addElement('header', 'hdr_org', get_string('heading_org', 'local_airpay_learningpath'));

        $orgs = $this->get_org_options();
        $mform->addElement('select', 'costcenterid', get_string('organisation', 'local_airpay_learningpath'), $orgs);
        $mform->setType('costcenterid', PARAM_INT);
        $mform->addHelpButton('costcenterid', 'organisation', 'local_airpay_learningpath');

        // ── Status ────────────────────────────────────────────────────
        if (!$iscreate) {
            $mform->addElement('header', 'hdr_status', get_string('heading_status', 'local_airpay_learningpath'));
            $statusoptions = [
                \local_airpay_learningpath\path_manager::STATUS_ACTIVE   => get_string('status_active', 'local_airpay_learningpath'),
                \local_airpay_learningpath\path_manager::STATUS_ARCHIVED => get_string('status_archived', 'local_airpay_learningpath'),
            ];
            $mform->addElement('select', 'status', get_string('status', 'local_airpay_learningpath'), $statusoptions);
            $mform->setType('status', PARAM_INT);
        }
    }

    public function validation($data, $files) {
        return [];
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $pathid = (int) $data->pathid;

        if ($pathid === 0) {
            $newid = \local_airpay_learningpath\path_manager::create($data);
            return ['pathid' => $newid, 'message' => get_string('pathcreated', 'local_airpay_learningpath')];
        } else {
            \local_airpay_learningpath\path_manager::update($pathid, $data);
            return ['pathid' => $pathid, 'message' => get_string('pathupdated', 'local_airpay_learningpath')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $pathid = (int) ($this->optional_param('pathid', 0, PARAM_INT));

        if ($pathid === 0) {
            $this->set_data((object) ['pathid' => 0]);
            return;
        }

        $p = $DB->get_record('local_airpay_learningpath', ['id' => $pathid], '*', MUST_EXIST);
        $this->set_data((object) [
            'pathid'       => $p->id,
            'name'         => $p->name,
            'description'  => $p->description ?? '',
            'costcenterid' => $p->costcenterid ?? 0,
            'status'       => $p->status ?? 1,
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/airpay_learningpath/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        $pathid = (int) ($this->optional_param('pathid', 0, PARAM_INT));
        if ($pathid === 0) {
            require_capability('local/airpay_learningpath:create', $context);
        } else {
            require_capability('local/airpay_learningpath:update', $context);
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
