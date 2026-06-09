<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_sentientia_courses\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit course dynamic form.
 *
 * Single form handles both flows — courseid=0 means "create new",
 * courseid>0 means "edit existing".
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_course extends \core_form\dynamic_form {

    /**
     * Build the form fields.
     */
    protected function definition() {
        $mform = $this->_form;
        $courseid = (int) ($this->optional_param('courseid', 0, PARAM_INT));
        $iscreate = ($courseid === 0);

        // Hidden courseid.
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // ── Basic info ────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_sentientia_courses'));

        $mform->addElement('text', 'fullname', get_string('fullname', 'local_sentientia_courses'),
            ['size' => 50, 'maxlength' => 254]);
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_sentientia_courses'),
            ['size' => 30, 'maxlength' => 100]);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addHelpButton('shortname', 'shortname', 'local_sentientia_courses');

        $mform->addElement('text', 'idnumber', get_string('idnumber', 'local_sentientia_courses'),
            ['size' => 30, 'maxlength' => 100]);
        $mform->setType('idnumber', PARAM_TEXT);

        // ── Category & Organisation ───────────────────────────────────
        $mform->addElement('header', 'hdr_category', get_string('heading_category', 'local_sentientia_courses'));

        $cats = $this->get_category_options();
        $mform->addElement('select', 'category', get_string('category', 'local_sentientia_courses'), $cats);
        $mform->setType('category', PARAM_INT);
        $mform->addRule('category', null, 'required', null, 'client');

        $orgs = $this->get_org_options();
        $mform->addElement('select', 'open_costcenterid', get_string('organisation', 'local_sentientia_courses'), $orgs);
        $mform->setType('open_costcenterid', PARAM_INT);

        // ── Description ───────────────────────────────────────────────
        $mform->addElement('header', 'hdr_summary', get_string('heading_summary', 'local_sentientia_courses'));

        $mform->addElement('editor', 'summary_editor', get_string('summary', 'local_sentientia_courses'),
            ['rows' => 5], ['noclean' => true]);
        $mform->setType('summary_editor', PARAM_RAW);

        // ── Format & Visibility ───────────────────────────────────────
        $mform->addElement('header', 'hdr_format', get_string('heading_format', 'local_sentientia_courses'));

        $formats = [
            'topics'   => get_string('format_topics', 'local_sentientia_courses'),
            'weeks'    => get_string('format_weeks', 'local_sentientia_courses'),
            'singleactivity' => get_string('format_single', 'local_sentientia_courses'),
            'social'   => get_string('format_social', 'local_sentientia_courses'),
        ];
        $mform->addElement('select', 'format', get_string('courseformat', 'local_sentientia_courses'), $formats);
        $mform->setDefault('format', 'topics');

        $mform->addElement('text', 'numsections', get_string('numsections', 'local_sentientia_courses'),
            ['size' => 5]);
        $mform->setType('numsections', PARAM_INT);
        $mform->setDefault('numsections', 5);
        $mform->disabledIf('numsections', 'format', 'eq', 'singleactivity');
        $mform->disabledIf('numsections', 'format', 'eq', 'social');

        $mform->addElement('selectyesno', 'visible', get_string('visibility', 'local_sentientia_courses'));
        $mform->setDefault('visible', 1);

        // ── Dates ─────────────────────────────────────────────────────
        $mform->addElement('date_time_selector', 'startdate', get_string('startdate', 'local_sentientia_courses'));
        $mform->setDefault('startdate', time());

        $mform->addElement('date_time_selector', 'enddate', get_string('enddate', 'local_sentientia_courses'),
            ['optional' => true]);

        // ── Completion deadline (P1 #21 — 2026-05-16) ────────────────
        // Closes audit item #28 from
        // parity-audit-2026-05-15/sentientia_courses.md. Column already
        // exists in the `course` table (open_coursecompletiondays) but
        // wasn't exposed on the form, so course-creators couldn't set
        // it. `course_manager::get_completion_deadline()` already reads
        // the value; restoring the form field unblocks that flow.
        $mform->addElement('text', 'open_coursecompletiondays',
            get_string('coursecompletiondays', 'local_sentientia_courses'),
            ['size' => 6, 'placeholder' => '0']);
        $mform->setType('open_coursecompletiondays', PARAM_INT);
        $mform->setDefault('open_coursecompletiondays', 0);
        $mform->addHelpButton('open_coursecompletiondays',
            'coursecompletiondays', 'local_sentientia_courses');
    }

    /**
     * Custom validation.
     */
    public function validation($data, $files) {
        global $DB;
        $errors = [];

        // P1 #21 — completion deadline must be non-negative.
        if (isset($data['open_coursecompletiondays'])
                && $data['open_coursecompletiondays'] !== ''
                && (int) $data['open_coursecompletiondays'] < 0) {
            $errors['open_coursecompletiondays']
                = get_string('coursecompletiondays_invalid',
                    'local_sentientia_courses');
        }

        $courseid = (int) ($data['courseid'] ?? 0);
        $iscreate = ($courseid === 0);

        // Shortname uniqueness.
        if (!empty($data['shortname'])) {
            $sql = "shortname = :sn";
            $params = ['sn' => $data['shortname']];
            if (!$iscreate) {
                $sql .= " AND id != :id";
                $params['id'] = $courseid;
            }
            if ($DB->record_exists_select('course', $sql, $params)) {
                $errors['shortname'] = get_string('shortnametaken', 'local_sentientia_courses');
            }
        }

        // End date after start date.
        if (!empty($data['enddate']) && !empty($data['startdate']) && $data['enddate'] <= $data['startdate']) {
            $errors['enddate'] = get_string('enddatebeforestart', 'local_sentientia_courses');
        }

        return $errors;
    }

    /**
     * Process form submission.
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $courseid = (int) $data->courseid;

        // Convert summary_editor to summary + summaryformat.
        if (isset($data->summary_editor) && is_array($data->summary_editor)) {
            $data->summary = $data->summary_editor['text'] ?? '';
            $data->summaryformat = $data->summary_editor['format'] ?? FORMAT_HTML;
        }

        if ($courseid === 0) {
            $newid = \local_sentientia_courses\course_manager::create($data);
            return ['courseid' => $newid, 'message' => get_string('coursecreated', 'local_sentientia_courses')];
        } else {
            \local_sentientia_courses\course_manager::update($courseid, $data);
            return ['courseid' => $courseid, 'message' => get_string('courseupdated', 'local_sentientia_courses')];
        }
    }

    /**
     * Pre-fill form with existing course data.
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $courseid = (int) ($this->optional_param('courseid', 0, PARAM_INT));

        if ($courseid === 0) {
            $this->set_data((object) ['courseid' => 0]);
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Resolve the org id from open_path (open_costcenterid column does
        // not exist on production — open_path is canonical).
        $orgid = 0;
        if (!empty($course->open_path)) {
            $org = $DB->get_record('local_sentientia_org', ['path' => $course->open_path], 'id');
            if ($org) {
                $orgid = (int) $org->id;
            }
        }

        $data = (object) [
            'courseid'  => $course->id,
            'fullname'  => $course->fullname,
            'shortname' => $course->shortname,
            'idnumber'  => $course->idnumber,
            'category'  => $course->category,
            'open_costcenterid' => $orgid,
            'format'    => $course->format,
            'visible'   => $course->visible,
            'startdate' => $course->startdate,
            'enddate'   => $course->enddate,
            // P1 #21 — pre-fill completion deadline. Column may not be
            // present on installs that pre-date the BizLMS column add,
            // so defensive null-coalesce.
            'open_coursecompletiondays' => (int) ($course->open_coursecompletiondays ?? 0),
            'summary_editor' => [
                'text'   => $course->summary ?? '',
                'format' => $course->summaryformat ?? FORMAT_HTML,
            ],
        ];

        $this->set_data($data);
    }

    /**
     * Page URL fallback.
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_courses/index.php');
    }

    /**
     * Capability check.
     */
    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        $courseid = (int) ($this->optional_param('courseid', 0, PARAM_INT));

        if ($courseid === 0) {
            require_capability('local/sentientia_courses:create', $context);
        } else {
            require_capability('local/sentientia_courses:update', $context);
        }
    }

    /**
     * Context.
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function get_category_options(): array {
        global $DB;
        $cats = $DB->get_records('course_categories', ['visible' => 1], 'sortorder ASC',
            'id, name, depth, path');

        $options = [];
        foreach ($cats as $c) {
            $indent = str_repeat('— ', max(0, $c->depth - 1));
            $options[$c->id] = $indent . format_string($c->name);
        }
        return $options;
    }

    private function get_org_options(): array {
        global $DB;
        $orgs = $DB->get_records('local_sentientia_org', ['visible' => 1],
            'depth ASC, fullname ASC', 'id, fullname, depth');

        $options = [0 => '— No specific organisation —'];
        foreach ($orgs as $o) {
            $indent = str_repeat('— ', max(0, $o->depth - 1));
            $options[$o->id] = $indent . format_string($o->fullname);
        }
        return $options;
    }
}
