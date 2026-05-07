<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_programs\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Modal form: create / edit a single program level.
 *
 * @package    local_airpay_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_level extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;

        $programid = (int) $this->optional_param('programid', 0, PARAM_INT);
        $levelid   = (int) $this->optional_param('levelid',   0, PARAM_INT);

        $mform->addElement('hidden', 'programid', $programid);
        $mform->setType('programid', PARAM_INT);
        $mform->addElement('hidden', 'levelid', $levelid);
        $mform->setType('levelid', PARAM_INT);

        $mform->addElement('text', 'name',
            get_string('level_name', 'local_airpay_programs'),
            ['size' => 50, 'maxlength' => 254,
             'placeholder' => 'e.g. Level 1 — Foundations']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description',
            get_string('level_description', 'local_airpay_programs'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('select', 'completion_required',
            get_string('level_completion', 'local_airpay_programs'),
            [
                1 => get_string('level_required', 'local_airpay_programs'),
                0 => get_string('level_optional', 'local_airpay_programs'),
            ]);
        $mform->setType('completion_required', PARAM_INT);
        $mform->setDefault('completion_required', 1);
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $programid = (int) $data->programid;
        $levelid   = (int) $data->levelid;

        if ($levelid === 0) {
            $newid = \local_airpay_programs\program_manager::create_level($programid, $data);
            return [
                'programid' => $programid,
                'levelid'   => $newid,
                'message'   => get_string('levelcreated', 'local_airpay_programs'),
            ];
        }

        \local_airpay_programs\program_manager::update_level($levelid, $data);
        return [
            'programid' => $programid,
            'levelid'   => $levelid,
            'message'   => get_string('levelupdated', 'local_airpay_programs'),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $levelid = (int) $this->optional_param('levelid', 0, PARAM_INT);

        if ($levelid === 0) {
            $this->set_data((object) [
                'programid' => (int) $this->optional_param('programid', 0, PARAM_INT),
                'levelid'   => 0,
                'completion_required' => 1,
            ]);
            return;
        }

        $l = $DB->get_record('local_airpay_programs_levels',
            ['id' => $levelid], '*', MUST_EXIST);

        $this->set_data((object) [
            'programid'           => (int) $l->programid,
            'levelid'             => (int) $l->id,
            'name'                => $l->name,
            'description'         => $l->description ?? '',
            'completion_required' => (int) $l->completion_required,
        ]);
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_programs:update', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $programid = (int) $this->optional_param('programid', 0, PARAM_INT);
        return new \moodle_url('/local/airpay_programs/view.php',
            ['id' => $programid, 'tab' => 'levels']);
    }
}
