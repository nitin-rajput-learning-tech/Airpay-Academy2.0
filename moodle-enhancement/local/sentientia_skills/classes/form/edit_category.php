<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit skill category dynamic form.
 *
 * @package    local_sentientia_skills
 */
class edit_category extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $categoryid = (int) ($this->optional_param('categoryid', 0, PARAM_INT));

        $mform->addElement('hidden', 'categoryid', $categoryid);
        $mform->setType('categoryid', PARAM_INT);

        $mform->addElement('header', 'hdr_basic', get_string('heading_category', 'local_sentientia_skills'));

        $mform->addElement('text', 'name', get_string('category_name', 'local_sentientia_skills'),
            ['size' => 50, 'maxlength' => 100, 'placeholder' => 'e.g. Compliance, Technical, Leadership']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description', 'local_sentientia_skills'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        // ── Visual ────────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_visual', get_string('heading_visual', 'local_sentientia_skills'));

        $icon_options = [
            'fa-cogs'         => 'Cogs (default)',
            'fa-shield'       => 'Shield (compliance)',
            'fa-code'         => 'Code (technical)',
            'fa-users'        => 'Users (leadership)',
            'fa-line-chart'   => 'Line chart (analytics)',
            'fa-balance-scale' => 'Balance scale (legal)',
            'fa-handshake-o'  => 'Handshake (sales)',
            'fa-graduation-cap' => 'Graduation cap',
        ];
        $mform->addElement('select', 'icon', get_string('icon', 'local_sentientia_skills'), $icon_options);
        $mform->setType('icon', PARAM_TEXT);
        $mform->setDefault('icon', 'fa-cogs');

        $mform->addElement('text', 'color', get_string('color', 'local_sentientia_skills'),
            ['size' => 10, 'maxlength' => 10, 'placeholder' => '#0066A7']);
        $mform->setType('color', PARAM_TEXT);
        $mform->setDefault('color', '#0066A7');
        $mform->addHelpButton('color', 'color', 'local_sentientia_skills');

        $mform->addElement('text', 'sort_order', get_string('sort_order', 'local_sentientia_skills'),
            ['size' => 5]);
        $mform->setType('sort_order', PARAM_INT);
        $mform->setDefault('sort_order', 0);
    }

    public function validation($data, $files) {
        $errors = [];
        // Validate hex color format.
        if (!empty($data['color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
            $errors['color'] = get_string('color_invalid', 'local_sentientia_skills');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $categoryid = (int) $data->categoryid;

        if ($categoryid === 0) {
            $newid = \local_sentientia_skills\skills_manager::create_category($data);
            return ['categoryid' => $newid, 'message' => get_string('categorycreated', 'local_sentientia_skills')];
        } else {
            \local_sentientia_skills\skills_manager::update_category($categoryid, $data);
            return ['categoryid' => $categoryid, 'message' => get_string('categoryupdated', 'local_sentientia_skills')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $categoryid = (int) ($this->optional_param('categoryid', 0, PARAM_INT));

        if ($categoryid === 0) {
            $this->set_data((object) ['categoryid' => 0]);
            return;
        }

        $c = $DB->get_record('local_sentientia_skill_cats', ['id' => $categoryid], '*', MUST_EXIST);
        $this->set_data((object) [
            'categoryid'  => $c->id,
            'name'        => $c->name,
            'description' => $c->description ?? '',
            'icon'        => $c->icon ?? 'fa-cogs',
            'color'       => $c->color ?? '#0066A7',
            'sort_order'  => $c->sort_order ?? 0,
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_skills/admin.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_skills:manage', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }
}
