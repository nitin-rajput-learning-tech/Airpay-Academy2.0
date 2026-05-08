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
 * Modal form for upserting one designation-skill requirement.
 */
class edit_designation_skill_dynamic_form extends dynamic_form {

    protected function definition(): void {
        global $DB;
        $mform = $this->_form;
        $designation = $this->optional_param('designation', '', PARAM_TEXT);
        $rowid       = $this->optional_param('rowid', 0, PARAM_INT);

        $mform->addElement('hidden', 'designation', $designation);
        $mform->setType('designation', PARAM_TEXT);
        $mform->addElement('hidden', 'rowid', $rowid);
        $mform->setType('rowid', PARAM_INT);

        if ($designation !== '') {
            $mform->addElement('static', 'desig_label', 'Designation',
                '<strong>' . s($designation) . '</strong>');
        }

        // Skill picker — all skills with their max_level.
        $skills = $DB->get_records_sql("
            SELECT s.id, s.name, s.max_level, c.name AS cat
              FROM {local_airpay_skills} s
         LEFT JOIN {local_airpay_skill_cats} c ON c.id = s.categoryid
          ORDER BY c.sort_order ASC, s.name ASC");
        $options = [0 => 'Select a skill...'];
        $maxlevels = [];
        foreach ($skills as $s) {
            $cat = $s->cat ? '[' . format_string($s->cat) . '] ' : '';
            $options[$s->id] = $cat . format_string($s->name) . ' (max ' . (int) $s->max_level . ')';
            $maxlevels[$s->id] = (int) $s->max_level;
        }
        $mform->addElement('select', 'skillid', 'Skill', $options);
        $mform->addRule('skillid', null, 'required', null, 'client');

        // Level — 1..5 covers all skills (UI will validate against max_level).
        $levelopts = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'];
        $mform->addElement('select', 'required_level', 'Required level', $levelopts);
        $mform->setDefault('required_level', 3);
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
        $id = skills_manager::save_designation_skill(
            (string) $data->designation, (int) $data->skillid,
            (int) $data->required_level);
        return ['id' => $id];
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $designation = $this->optional_param('designation', '', PARAM_TEXT);
        $rowid       = $this->optional_param('rowid', 0, PARAM_INT);
        if ($rowid > 0) {
            $existing = $DB->get_record('local_airpay_role_skills', ['id' => $rowid]);
            if ($existing) {
                $this->set_data((object) [
                    'designation'    => $existing->designation,
                    'rowid'          => $rowid,
                    'skillid'        => (int) $existing->skillid,
                    'required_level' => (int) $existing->required_level,
                ]);
                return;
            }
        }
        $this->set_data((object) [
            'designation' => $designation,
            'rowid' => 0,
            'skillid' => 0,
            'required_level' => 3,
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/airpay_skills/designation_matrix.php',
            ['designation' => $this->optional_param('designation', '', PARAM_TEXT)]);
    }
}
