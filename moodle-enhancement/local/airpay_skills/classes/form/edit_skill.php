<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_skills\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit skill dynamic form.
 *
 * @package    local_airpay_skills
 */
class edit_skill extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $skillid = (int) ($this->optional_param('skillid', 0, PARAM_INT));

        $mform->addElement('hidden', 'skillid', $skillid);
        $mform->setType('skillid', PARAM_INT);

        // ── Identity ──────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_skill', 'local_airpay_skills'));

        $mform->addElement('text', 'name', get_string('skill_name', 'local_airpay_skills'),
            ['size' => 50, 'maxlength' => 100, 'placeholder' => 'e.g. Anti-Money Laundering, Java Programming']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description', 'local_airpay_skills'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        // ── Category ──────────────────────────────────────────────────
        $cats = \local_airpay_skills\skills_manager::get_categories_options();
        $mform->addElement('select', 'categoryid', get_string('category', 'local_airpay_skills'), $cats);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', null, 'required', null, 'client');

        // ── Levels ────────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_levels', get_string('heading_levels', 'local_airpay_skills'));

        $level_options = [
            1 => '1 — Awareness only',
            2 => '2 — Basic competency',
            3 => '3 — Intermediate (most roles)',
            4 => '4 — Advanced',
            5 => '5 — Expert (specialist roles)',
        ];
        $mform->addElement('select', 'max_level', get_string('max_level', 'local_airpay_skills'),
            $level_options);
        $mform->setType('max_level', PARAM_INT);
        $mform->setDefault('max_level', 5);
        $mform->addHelpButton('max_level', 'max_level', 'local_airpay_skills');

        $mform->addElement('text', 'sort_order', get_string('sort_order', 'local_airpay_skills'),
            ['size' => 5]);
        $mform->setType('sort_order', PARAM_INT);
        $mform->setDefault('sort_order', 0);
    }

    public function validation($data, $files) {
        return [];
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $skillid = (int) $data->skillid;

        if ($skillid === 0) {
            $newid = \local_airpay_skills\skills_manager::create_skill($data);
            return ['skillid' => $newid, 'message' => get_string('skillcreated', 'local_airpay_skills')];
        } else {
            \local_airpay_skills\skills_manager::update_skill($skillid, $data);
            return ['skillid' => $skillid, 'message' => get_string('skillupdated', 'local_airpay_skills')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $skillid = (int) ($this->optional_param('skillid', 0, PARAM_INT));

        if ($skillid === 0) {
            $this->set_data((object) ['skillid' => 0]);
            return;
        }

        $s = $DB->get_record('local_airpay_skills', ['id' => $skillid], '*', MUST_EXIST);
        $this->set_data((object) [
            'skillid'     => $s->id,
            'name'        => $s->name,
            'description' => $s->description ?? '',
            'categoryid'  => $s->categoryid,
            'max_level'   => $s->max_level ?? 5,
            'sort_order'  => $s->sort_order ?? 0,
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/airpay_skills/admin.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_skills:manage', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }
}
