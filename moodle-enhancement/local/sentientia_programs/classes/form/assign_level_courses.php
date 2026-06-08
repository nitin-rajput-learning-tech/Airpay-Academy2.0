<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Modal form: assign one or more courses to a level.
 *
 * Loaded via core_form/modalform from the levelcourses sub-page. Dropdown
 * shows courses NOT already on this level (and skips the site course).
 *
 * Mirrors local_sentientia_learningpath\form\assign_courses_form (plain
 * <select multiple> for headless reliability).
 *
 * @package    local_sentientia_programs
 */
class assign_level_courses extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;

        $levelid = (int) $this->optional_param('levelid', 0, PARAM_INT);

        $mform->addElement('hidden', 'levelid', $levelid);
        $mform->setType('levelid', PARAM_INT);

        global $DB;

        // Already-assigned set — exclude from dropdown.
        $assigned = $DB->get_fieldset_select('local_sentientia_programs_courses',
            'courseid', 'levelid = :l', ['l' => $levelid]);

        $where = ['c.id > 1'];
        $params = [];
        if (!empty($assigned)) {
            [$insql, $inparams] = $DB->get_in_or_equal($assigned, SQL_PARAMS_NAMED, 'aid', false);
            $where[] = "c.id $insql";
            $params = array_merge($params, $inparams);
        }
        $wheresql = implode(' AND ', $where);

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.visible
               FROM {course} c
              WHERE $wheresql
           ORDER BY c.fullname ASC", $params, 0, 5000);

        $options = [];
        foreach ($courses as $c) {
            $label = format_string($c->fullname);
            if (!empty($c->shortname) && $c->shortname !== $c->fullname) {
                $label .= ' (' . format_string($c->shortname) . ')';
            }
            if (!$c->visible) {
                $label .= ' — hidden';
            }
            $options[(int) $c->id] = $label;
        }

        $mform->addElement('select', 'courseids',
            get_string('add_courses', 'local_sentientia_programs'),
            $options,
            ['multiple' => 'multiple', 'size' => min(15, max(5, count($options)))]);
        $mform->setType('courseids', PARAM_INT);
        $mform->addRule('courseids', null, 'required', null, 'client');
        $mform->addElement('static', 'hint', '',
            '<small class="text-muted">Hold Ctrl (or Cmd on Mac) and click to select multiple.</small>');

        if (empty($options)) {
            $mform->addElement('static', 'no_options',
                '',
                '<p class="text-muted fst-italic">All available courses are already on this level.</p>');
        }
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $levelid = (int) $data->levelid;
        $courseids = is_array($data->courseids ?? null) ? array_map('intval', $data->courseids) : [];

        $count = \local_sentientia_programs\program_manager::assign_courses_to_level($levelid, $courseids);

        return [
            'levelid'  => $levelid,
            'inserted' => $count,
            'message'  => get_string('courses_assigned_count', 'local_sentientia_programs', $count),
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        // Nothing to pre-fill — always a "create" form.
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_programs:update', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $levelid = (int) $this->optional_param('levelid', 0, PARAM_INT);
        return new \moodle_url('/local/sentientia_programs/levelcourses.php',
            ['levelid' => $levelid]);
    }
}
