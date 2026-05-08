<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_skills\form;

defined('MOODLE_INTERNAL') || die();

use core_form\dynamic_form;
use moodle_url;
use context;
use context_system;
use local_airpay_skills\skills_manager;

/**
 * Modal form for editing one skill-level definition.
 */
class edit_skill_level_dynamic_form extends dynamic_form {

    protected function definition(): void {
        global $DB;
        $mform = $this->_form;
        $skillid = $this->optional_param('skillid', 0, PARAM_INT);
        $level   = $this->optional_param('level',   1, PARAM_INT);

        $mform->addElement('hidden', 'skillid', $skillid);
        $mform->setType('skillid', PARAM_INT);
        $mform->addElement('hidden', 'level', $level);
        $mform->setType('level', PARAM_INT);

        if ($skillid > 0) {
            $skill = $DB->get_record('local_airpay_skills', ['id' => $skillid]);
            if ($skill) {
                $mform->addElement('static', 'skill_label', 'Skill',
                    '<strong>' . s($skill->name) . '</strong> '
                    . '<span class="badge bg-light text-dark border ms-2">Level ' . (int) $level . '</span>');
            }
        }

        $mform->addElement('text', 'label', 'Label',
            ['size' => 40, 'maxlength' => 100]);
        $mform->setType('label', PARAM_TEXT);
        $mform->addRule('label', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', 'Description',
            ['rows' => 5, 'cols' => 60]);
        $mform->setType('description', PARAM_RAW);
    }

    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_skills:manage',
            $this->get_context_for_dynamic_submission());
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $id = skills_manager::save_skill_level(
            (int) $data->skillid, (int) $data->level,
            (string) $data->label, (string) ($data->description ?? ''));
        return ['id' => $id];
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $skillid = $this->optional_param('skillid', 0, PARAM_INT);
        $level   = $this->optional_param('level',   1, PARAM_INT);
        if ($skillid > 0) {
            $existing = $DB->get_record('local_airpay_skill_levels',
                ['skillid' => $skillid, 'level' => $level]);
            if ($existing) {
                $this->set_data((object) [
                    'skillid'     => $skillid,
                    'level'       => $level,
                    'label'       => $existing->label,
                    'description' => $existing->description,
                ]);
                return;
            }
        }
        $defaults = [1=>'Awareness',2=>'Basic',3=>'Intermediate',4=>'Advanced',5=>'Expert'];
        $this->set_data((object) [
            'skillid' => $skillid, 'level' => $level,
            'label' => $defaults[$level] ?? '',
            'description' => '',
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/airpay_skills/level_definitions.php',
            ['skillid' => $this->optional_param('skillid', 0, PARAM_INT)]);
    }
}
