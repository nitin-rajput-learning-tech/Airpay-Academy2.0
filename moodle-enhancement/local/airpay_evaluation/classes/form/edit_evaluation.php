<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_evaluation\form;

defined('MOODLE_INTERNAL') || die();

use local_airpay_evaluation\evaluation_manager;

/**
 * Create / edit evaluation form dynamic form.
 *
 * @package    local_airpay_evaluation
 */
class edit_evaluation extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $evaluationid = (int) ($this->optional_param('evaluationid', 0, PARAM_INT));

        $mform->addElement('hidden', 'evaluationid', $evaluationid);
        $mform->setType('evaluationid', PARAM_INT);

        // ── Identity ──────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_airpay_evaluation'));

        $mform->addElement('text', 'name', get_string('eval_name', 'local_airpay_evaluation'),
            ['size' => 50, 'maxlength' => 254, 'placeholder' => 'e.g. POSH Training Feedback']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description', 'local_airpay_evaluation'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        // ── Kirkpatrick framework ─────────────────────────────────────
        $mform->addElement('header', 'hdr_kirkpatrick', get_string('heading_kirkpatrick', 'local_airpay_evaluation'));

        $mform->addElement('select', 'kirkpatrick_level',
            get_string('kirkpatrick_level', 'local_airpay_evaluation'),
            evaluation_manager::KIRKPATRICK_LEVELS);
        $mform->setType('kirkpatrick_level', PARAM_INT);
        $mform->setDefault('kirkpatrick_level', 1);
        $mform->addHelpButton('kirkpatrick_level', 'kirkpatrick_level', 'local_airpay_evaluation');

        // ── Trigger ───────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_trigger', get_string('heading_trigger', 'local_airpay_evaluation'));

        $mform->addElement('select', 'trigger_event',
            get_string('trigger_event', 'local_airpay_evaluation'),
            evaluation_manager::TRIGGER_EVENTS);
        $mform->setType('trigger_event', PARAM_ALPHA);
        $mform->setDefault('trigger_event', 'manual');

        $mform->addElement('text', 'days_after',
            get_string('days_after', 'local_airpay_evaluation'),
            ['size' => 5]);
        $mform->setType('days_after', PARAM_INT);
        $mform->setDefault('days_after', 0);
        $mform->addHelpButton('days_after', 'days_after', 'local_airpay_evaluation');
        $mform->disabledIf('days_after', 'trigger_event', 'eq', 'manual');

        // ── Privacy ───────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_privacy', get_string('heading_privacy', 'local_airpay_evaluation'));

        $mform->addElement('advcheckbox', 'anonymous',
            get_string('anonymous', 'local_airpay_evaluation'));
        $mform->addHelpButton('anonymous', 'anonymous', 'local_airpay_evaluation');

        // ── Organisation ──────────────────────────────────────────────
        $orgs = $this->get_org_options();
        $mform->addElement('select', 'costcenterid',
            get_string('organisation', 'local_airpay_evaluation'), $orgs);
        $mform->setType('costcenterid', PARAM_INT);

        // ── Status ────────────────────────────────────────────────────
        $statusoptions = [
            evaluation_manager::STATUS_DRAFT    => get_string('status_draft', 'local_airpay_evaluation'),
            evaluation_manager::STATUS_ACTIVE   => get_string('status_active', 'local_airpay_evaluation'),
            evaluation_manager::STATUS_ARCHIVED => get_string('status_archived', 'local_airpay_evaluation'),
        ];
        $mform->addElement('select', 'status',
            get_string('status', 'local_airpay_evaluation'), $statusoptions);
        $mform->setType('status', PARAM_INT);
        $mform->setDefault('status', evaluation_manager::STATUS_DRAFT);
    }

    public function validation($data, $files) {
        $errors = [];
        if (isset($data['days_after']) && $data['days_after'] < 0) {
            $errors['days_after'] = get_string('days_after_invalid', 'local_airpay_evaluation');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $evaluationid = (int) $data->evaluationid;

        if ($evaluationid === 0) {
            $newid = evaluation_manager::create($data);
            return ['evaluationid' => $newid, 'message' => get_string('evaluationcreated', 'local_airpay_evaluation')];
        } else {
            evaluation_manager::update($evaluationid, $data);
            return ['evaluationid' => $evaluationid, 'message' => get_string('evaluationupdated', 'local_airpay_evaluation')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $evaluationid = (int) ($this->optional_param('evaluationid', 0, PARAM_INT));

        if ($evaluationid === 0) {
            $this->set_data((object) ['evaluationid' => 0]);
            return;
        }

        $e = $DB->get_record('local_airpay_evaluation', ['id' => $evaluationid], '*', MUST_EXIST);
        $this->set_data((object) [
            'evaluationid'      => $e->id,
            'name'              => $e->name,
            'description'       => $e->description ?? '',
            'kirkpatrick_level' => $e->kirkpatrick_level ?? 1,
            'trigger_event'     => $e->trigger_event ?? 'manual',
            'days_after'        => $e->days_after ?? 0,
            'costcenterid'      => $e->costcenterid ?? 0,
            'status'            => $e->status ?? 0,
            'anonymous'         => $e->anonymous ?? 0,
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/airpay_evaluation/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_evaluation:manage', $this->get_context_for_dynamic_submission());
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
