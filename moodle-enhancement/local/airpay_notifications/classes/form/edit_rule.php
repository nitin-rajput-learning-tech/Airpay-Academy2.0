<?php
// This file is part of Moodle - http://moodle.org/
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_notifications\form;

defined('MOODLE_INTERNAL') || die();

use local_airpay_notifications\rule_manager;

/**
 * Create / edit notification rule dynamic form.
 *
 * @package    local_airpay_notifications
 */
class edit_rule extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $ruleid = (int) ($this->optional_param('ruleid', 0, PARAM_INT));

        $mform->addElement('hidden', 'ruleid', $ruleid);
        $mform->setType('ruleid', PARAM_INT);

        // ── Identity ──────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_airpay_notifications'));

        $mform->addElement('text', 'name', get_string('rule_name', 'local_airpay_notifications'),
            ['size' => 50, 'maxlength' => 100, 'placeholder' => 'e.g. Deadline 7 days reminder']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('select', 'rule_type', get_string('rule_type', 'local_airpay_notifications'),
            rule_manager::RULE_TYPES);
        $mform->setType('rule_type', PARAM_ALPHANUMEXT);
        $mform->addRule('rule_type', null, 'required', null, 'client');
        $mform->addHelpButton('rule_type', 'rule_type', 'local_airpay_notifications');

        // ── Trigger conditions ────────────────────────────────────────
        $mform->addElement('header', 'hdr_trigger', get_string('heading_trigger', 'local_airpay_notifications'));

        $mform->addElement('text', 'trigger_days', get_string('trigger_days', 'local_airpay_notifications'),
            ['size' => 5]);
        $mform->setType('trigger_days', PARAM_INT);
        $mform->setDefault('trigger_days', 3);
        $mform->addHelpButton('trigger_days', 'trigger_days', 'local_airpay_notifications');

        $mform->addElement('select', 'audience', get_string('audience', 'local_airpay_notifications'),
            rule_manager::AUDIENCES);
        $mform->setType('audience', PARAM_ALPHA);
        $mform->setDefault('audience', 'learner');

        // ── Delivery ──────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_delivery', get_string('heading_delivery', 'local_airpay_notifications'));

        $mform->addElement('select', 'channel', get_string('channel', 'local_airpay_notifications'),
            rule_manager::CHANNELS);
        $mform->setType('channel', PARAM_ALPHA);
        $mform->setDefault('channel', 'inapp');

        $mform->addElement('textarea', 'template', get_string('template', 'local_airpay_notifications'),
            ['rows' => 5, 'cols' => 50, 'placeholder' => 'Hi {{firstname}}, your course "{{coursename}}" is due in {{days}} days.']);
        $mform->setType('template', PARAM_RAW);
        $mform->addHelpButton('template', 'template', 'local_airpay_notifications');

        // ── Status ────────────────────────────────────────────────────
        $mform->addElement('advcheckbox', 'enabled',
            get_string('enabled', 'local_airpay_notifications'));
        $mform->setDefault('enabled', 1);
    }

    public function validation($data, $files) {
        $errors = [];
        if (isset($data['trigger_days']) && $data['trigger_days'] < 0) {
            $errors['trigger_days'] = get_string('trigger_days_invalid', 'local_airpay_notifications');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $ruleid = (int) $data->ruleid;

        if ($ruleid === 0) {
            $newid = rule_manager::create($data);
            return ['ruleid' => $newid, 'message' => get_string('rulecreated', 'local_airpay_notifications')];
        } else {
            rule_manager::update($ruleid, $data);
            return ['ruleid' => $ruleid, 'message' => get_string('ruleupdated', 'local_airpay_notifications')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $ruleid = (int) ($this->optional_param('ruleid', 0, PARAM_INT));

        if ($ruleid === 0) {
            $this->set_data((object) ['ruleid' => 0]);
            return;
        }

        $r = $DB->get_record('local_airpay_notif_rules', ['id' => $ruleid], '*', MUST_EXIST);
        $this->set_data((object) [
            'ruleid'       => $r->id,
            'name'         => $r->name,
            'rule_type'    => $r->rule_type,
            'channel'      => $r->channel ?? 'inapp',
            'trigger_days' => $r->trigger_days ?? 3,
            'audience'     => $r->audience ?? 'learner',
            'enabled'      => $r->enabled ?? 1,
            'template'     => $r->template ?? '',
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/airpay_notifications/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_notifications:manage', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }
}
