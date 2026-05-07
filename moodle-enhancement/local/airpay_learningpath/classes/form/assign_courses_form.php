<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Modal form: assign one or more courses to a learning path.
 *
 * Loaded via core_form/modalform from the path detail page's Courses tab.
 * The dropdown only shows courses NOT already on the path.
 *
 * @package    local_airpay_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_courses_form extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $pathid = (int) $this->optional_param('pathid', 0, PARAM_INT);

        $mform->addElement('hidden', 'pathid', $pathid);
        $mform->setType('pathid', PARAM_INT);

        global $DB;

        // Build the list of available courses — every course EXCEPT:
        //   - the site course (id=1)
        //   - courses already on this path
        // Limit to 5000 to prevent runaway DOM size; in practice we have ~411.
        $assigned = $DB->get_fieldset_select('local_airpay_learningpath_courses',
            'courseid', 'pathid = :p', ['p' => $pathid]);

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

        // Multi-select. The autocomplete element is nicer UX but loads its
        // options via a separate AJAX call which can race in headless test
        // environments. A plain HTML <select multiple> is bulletproof: the
        // options are in the page on first render, no extra round-trip.
        $mform->addElement('select', 'courseids',
            get_string('add_courses', 'local_airpay_learningpath'),
            $options,
            ['multiple' => 'multiple', 'size' => min(15, max(5, count($options)))]);
        $mform->setType('courseids', PARAM_INT);
        $mform->addRule('courseids', null, 'required', null, 'client');
        $mform->addElement('static', 'hint', '',
            '<small class="text-muted">Hold Ctrl (or Cmd on Mac) and click to select multiple.</small>');

        if (empty($options)) {
            $mform->addElement('static', 'no_options',
                '',
                '<p class="text-muted fst-italic">All available courses are already on this path.</p>');
        }
    }

    /**
     * Process the submitted form. Returns ['inserted' => N] on success.
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $pathid = (int) $data->pathid;
        $courseids = is_array($data->courseids) ? array_map('intval', $data->courseids) : [];

        $count = \local_airpay_learningpath\path_manager::assign_courses($pathid, $courseids);

        return [
            'pathid'   => $pathid,
            'inserted' => $count,
            'message'  => $count . ' course' . ($count === 1 ? '' : 's') . ' assigned.',
        ];
    }

    public function set_data_for_dynamic_submission(): void {
        // Nothing to pre-fill — this is always a "create" form.
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_learningpath:update', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $pathid = (int) $this->optional_param('pathid', 0, PARAM_INT);
        return new \moodle_url('/local/airpay_learningpath/view.php',
            ['id' => $pathid, 'tab' => 'courses']);
    }
}
