<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge\form;

defined('MOODLE_INTERNAL') || die();

use core_form\dynamic_form;
use moodle_url;
use context;
use context_system;
use local_airpay_challenge\challenge_engine;

/**
 * Modal form for create + edit of a challenge.
 *
 * Persistence runs through {@see challenge_engine::create_challenge()}
 * / {@see challenge_engine::update_challenge()} so type validation,
 * shortname uniqueness, and tenant tagging happen in one place.
 */
class edit_challenge_dynamic_form extends dynamic_form {

    protected function definition(): void {
        $mform = $this->_form;
        $challengeid = $this->optional_param('challengeid', 0, PARAM_INT);

        $mform->addElement('hidden', 'challengeid', $challengeid);
        $mform->setType('challengeid', PARAM_INT);

        // Name + shortname.
        $mform->addElement('text', 'name', get_string('form_name', 'local_airpay_challenge'),
            ['size' => 60, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('form_shortname', 'local_airpay_challenge'),
            ['size' => 30, 'maxlength' => 100]);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('shortname', 'form_shortname', 'local_airpay_challenge');

        // Description.
        $mform->addElement('textarea', 'description',
            get_string('form_description', 'local_airpay_challenge'),
            ['rows' => 4, 'cols' => 60]);
        $mform->setType('description', PARAM_RAW);

        // Type.
        $types = [
            challenge_engine::TYPE_COURSE_COMPLETION => get_string('type_course_completion', 'local_airpay_challenge'),
            challenge_engine::TYPE_CUSTOM            => get_string('type_custom', 'local_airpay_challenge'),
        ];
        $mform->addElement('select', 'type', get_string('form_type', 'local_airpay_challenge'), $types);
        $mform->setDefault('type', challenge_engine::TYPE_COURSE_COMPLETION);

        // Target count.
        $mform->addElement('text', 'targetcount',
            get_string('form_targetcount', 'local_airpay_challenge'), ['size' => 5]);
        $mform->setType('targetcount', PARAM_INT);
        $mform->setDefault('targetcount', 1);
        $mform->addHelpButton('targetcount', 'form_targetcount', 'local_airpay_challenge');

        // Points reward.
        $mform->addElement('text', 'pointsreward',
            get_string('form_pointsreward', 'local_airpay_challenge'), ['size' => 5]);
        $mform->setType('pointsreward', PARAM_INT);
        $mform->setDefault('pointsreward', 100);

        // Status.
        $statuses = [
            challenge_engine::STATUS_DRAFT    => get_string('status_draft',    'local_airpay_challenge'),
            challenge_engine::STATUS_ACTIVE   => get_string('status_active',   'local_airpay_challenge'),
            challenge_engine::STATUS_ARCHIVED => get_string('status_archived', 'local_airpay_challenge'),
        ];
        $mform->addElement('select', 'status',
            get_string('form_status', 'local_airpay_challenge'), $statuses);
        $mform->setDefault('status', challenge_engine::STATUS_DRAFT);

        // Date window (optional).
        $mform->addElement('date_time_selector', 'startdate',
            get_string('form_startdate', 'local_airpay_challenge'),
            ['optional' => true]);
        $mform->addElement('date_time_selector', 'enddate',
            get_string('form_enddate', 'local_airpay_challenge'),
            ['optional' => true]);
    }

    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_challenge:manage',
            $this->get_context_for_dynamic_submission());
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $payload = [
            'name'         => (string) $data->name,
            'shortname'    => (string) ($data->shortname ?? ''),
            'description'  => (string) ($data->description ?? ''),
            'type'         => (string) ($data->type ?? challenge_engine::TYPE_COURSE_COMPLETION),
            'targetcount'  => max(1, (int) ($data->targetcount ?? 1)),
            'pointsreward' => max(0, (int) ($data->pointsreward ?? 100)),
            'status'       => (int) ($data->status ?? challenge_engine::STATUS_DRAFT),
            'startdate'    => !empty($data->startdate) ? (int) $data->startdate : null,
            'enddate'      => !empty($data->enddate)   ? (int) $data->enddate   : null,
        ];

        if (!empty($data->challengeid)) {
            challenge_engine::update_challenge((int) $data->challengeid, $payload);
            return ['id' => (int) $data->challengeid];
        }
        $id = challenge_engine::create_challenge($payload);
        return ['id' => $id];
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $challengeid = $this->optional_param('challengeid', 0, PARAM_INT);
        if ($challengeid > 0) {
            $row = $DB->get_record('local_airpay_challenge_challenges',
                ['id' => $challengeid], '*', MUST_EXIST);
            $this->set_data((object) [
                'challengeid'  => $challengeid,
                'name'         => $row->name,
                'shortname'    => $row->shortname,
                'description'  => $row->description,
                'type'         => $row->type,
                'targetcount'  => (int) $row->targetcount,
                'pointsreward' => (int) $row->pointsreward,
                'status'       => (int) $row->status,
                'startdate'    => $row->startdate ? (int) $row->startdate : 0,
                'enddate'      => $row->enddate   ? (int) $row->enddate   : 0,
            ]);
        }
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/airpay_challenge/index.php');
    }
}
