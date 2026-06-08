<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\form;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_evaluation\evaluation_manager;

/**
 * Create / edit evaluation form dynamic form.
 *
 * @package    local_sentientia_evaluation
 */
class edit_evaluation extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $evaluationid = (int) ($this->optional_param('evaluationid', 0, PARAM_INT));

        $mform->addElement('hidden', 'evaluationid', $evaluationid);
        $mform->setType('evaluationid', PARAM_INT);

        // ── Identity ──────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_sentientia_evaluation'));

        $mform->addElement('text', 'name', get_string('eval_name', 'local_sentientia_evaluation'),
            ['size' => 50, 'maxlength' => 254, 'placeholder' => 'e.g. POSH Training Feedback']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description', 'local_sentientia_evaluation'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        // ── Kirkpatrick framework ─────────────────────────────────────
        $mform->addElement('header', 'hdr_kirkpatrick', get_string('heading_kirkpatrick', 'local_sentientia_evaluation'));

        $mform->addElement('select', 'kirkpatrick_level',
            get_string('kirkpatrick_level', 'local_sentientia_evaluation'),
            evaluation_manager::KIRKPATRICK_LEVELS);
        $mform->setType('kirkpatrick_level', PARAM_INT);
        $mform->setDefault('kirkpatrick_level', 1);
        $mform->addHelpButton('kirkpatrick_level', 'kirkpatrick_level', 'local_sentientia_evaluation');

        // ── Trigger ───────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_trigger', get_string('heading_trigger', 'local_sentientia_evaluation'));

        $mform->addElement('select', 'trigger_event',
            get_string('trigger_event', 'local_sentientia_evaluation'),
            evaluation_manager::TRIGGER_EVENTS);
        $mform->setType('trigger_event', PARAM_ALPHA);
        $mform->setDefault('trigger_event', 'manual');

        $mform->addElement('text', 'days_after',
            get_string('days_after', 'local_sentientia_evaluation'),
            ['size' => 5]);
        $mform->setType('days_after', PARAM_INT);
        $mform->setDefault('days_after', 0);
        $mform->addHelpButton('days_after', 'days_after', 'local_sentientia_evaluation');
        $mform->disabledIf('days_after', 'trigger_event', 'eq', 'manual');

        // ── Privacy ───────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_privacy', get_string('heading_privacy', 'local_sentientia_evaluation'));

        $mform->addElement('advcheckbox', 'anonymous',
            get_string('anonymous', 'local_sentientia_evaluation'));
        $mform->addHelpButton('anonymous', 'anonymous', 'local_sentientia_evaluation');

        // ── P1 #17 (2026-05-16) — availability window + pulse mode ────
        // Time-bounded availability (timeopen/timeclose) closes audit
        // item #15. Multiple-submit (multiple_submit) closes audit
        // item #14 — both from parity-audit-2026-05-15/sentientia_evaluation.md.
        $mform->addElement('header', 'hdr_window',
            get_string('heading_window', 'local_sentientia_evaluation'));

        // `date_time_selector` + `optional=true` produces a checkbox-gated
        // datetime; ticked = "use this date", unticked = "no constraint
        // (stored as 0)". Matches the Moodle convention used by core
        // course.startdate / forum.cutoffdate.
        $mform->addElement('date_time_selector', 'timeopen',
            get_string('timeopen', 'local_sentientia_evaluation'),
            ['optional' => true]);
        $mform->addHelpButton('timeopen', 'timeopen', 'local_sentientia_evaluation');

        $mform->addElement('date_time_selector', 'timeclose',
            get_string('timeclose', 'local_sentientia_evaluation'),
            ['optional' => true]);
        $mform->addHelpButton('timeclose', 'timeclose', 'local_sentientia_evaluation');

        $mform->addElement('advcheckbox', 'multiple_submit',
            get_string('multiple_submit', 'local_sentientia_evaluation'));
        $mform->addHelpButton('multiple_submit', 'multiple_submit', 'local_sentientia_evaluation');

        // ── P1 #19 (2026-05-16) — Notifications ───────────────────────
        // Closes audit item #17 from
        // parity-audit-2026-05-15/sentientia_evaluation.md.
        $mform->addElement('header', 'hdr_notifications',
            get_string('heading_notifications', 'local_sentientia_evaluation'));

        $mform->addElement('advcheckbox', 'notify_admin_on_response',
            get_string('notify_admin_on_response', 'local_sentientia_evaluation'));
        $mform->addHelpButton('notify_admin_on_response',
            'notify_admin_on_response', 'local_sentientia_evaluation');

        // ── Organisation ──────────────────────────────────────────────
        $orgs = $this->get_org_options();
        $mform->addElement('select', 'costcenterid',
            get_string('organisation', 'local_sentientia_evaluation'), $orgs);
        $mform->setType('costcenterid', PARAM_INT);

        // ── Status ────────────────────────────────────────────────────
        $statusoptions = [
            evaluation_manager::STATUS_DRAFT    => get_string('status_draft', 'local_sentientia_evaluation'),
            evaluation_manager::STATUS_ACTIVE   => get_string('status_active', 'local_sentientia_evaluation'),
            evaluation_manager::STATUS_ARCHIVED => get_string('status_archived', 'local_sentientia_evaluation'),
        ];
        $mform->addElement('select', 'status',
            get_string('status', 'local_sentientia_evaluation'), $statusoptions);
        $mform->setType('status', PARAM_INT);
        $mform->setDefault('status', evaluation_manager::STATUS_DRAFT);
    }

    public function validation($data, $files) {
        $errors = [];
        if (isset($data['days_after']) && $data['days_after'] < 0) {
            $errors['days_after'] = get_string('days_after_invalid', 'local_sentientia_evaluation');
        }

        // P1 #17 — sanity-check the window. The date_time_selector with
        // optional=true posts the timestamp as `0` when unticked, so we
        // only validate when BOTH ends are set.
        $open  = (int) ($data['timeopen']  ?? 0);
        $close = (int) ($data['timeclose'] ?? 0);
        if ($open > 0 && $close > 0 && $close < $open) {
            $errors['timeclose'] = get_string('eval_window_inverted',
                'local_sentientia_evaluation');
        }

        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $evaluationid = (int) $data->evaluationid;

        if ($evaluationid === 0) {
            $newid = evaluation_manager::create($data);
            return ['evaluationid' => $newid, 'message' => get_string('evaluationcreated', 'local_sentientia_evaluation')];
        } else {
            evaluation_manager::update($evaluationid, $data);
            return ['evaluationid' => $evaluationid, 'message' => get_string('evaluationupdated', 'local_sentientia_evaluation')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $evaluationid = (int) ($this->optional_param('evaluationid', 0, PARAM_INT));

        if ($evaluationid === 0) {
            $this->set_data((object) ['evaluationid' => 0]);
            return;
        }

        $e = $DB->get_record('local_sentientia_evaluation', ['id' => $evaluationid], '*', MUST_EXIST);
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
            // P1 #17 — pre-fill window + pulse fields. 0 sentinels render
            // as unticked optional date_time_selector + unchecked checkbox.
            'timeopen'          => (int) ($e->timeopen        ?? 0),
            'timeclose'         => (int) ($e->timeclose       ?? 0),
            'multiple_submit'   => (int) ($e->multiple_submit ?? 0),
            // P1 #19 — pre-fill admin-notify checkbox.
            'notify_admin_on_response' => (int) ($e->notify_admin_on_response ?? 0),
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_evaluation/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_evaluation:manage', $this->get_context_for_dynamic_submission());
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
