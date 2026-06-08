<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Create / edit exam wrapper dynamic form.
 *
 * Exams aren't standalone — they wrap an existing Moodle quiz activity.
 * The form picks an existing quiz and adds tenant/duration metadata
 * around it.
 *
 * @package    local_sentientia_exams
 */
class edit_exam extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $examid = (int) ($this->optional_param('examid', 0, PARAM_INT));
        $iscreate = ($examid === 0);

        $mform->addElement('hidden', 'examid', $examid);
        $mform->setType('examid', PARAM_INT);

        // ── Quiz picker ───────────────────────────────────────────────
        $mform->addElement('header', 'hdr_basic', get_string('heading_basic', 'local_sentientia_exams'));

        // For create: exclude already-registered quizzes.
        // For edit: include the current exam's quiz so it remains selectable.
        $exclude = [];
        $current_quizid = 0;
        if (!$iscreate) {
            global $DB;
            $current = $DB->get_record('local_sentientia_exams', ['id' => $examid]);
            if ($current) {
                $current_quizid = (int) $current->quizid;
            }
        }
        $registered = \local_sentientia_exams\exam_manager::get_registered_quiz_ids();
        if ($current_quizid > 0) {
            $exclude = array_diff($registered, [$current_quizid]);
        } else {
            $exclude = $registered;
        }

        $quiz_options = \local_sentientia_exams\exam_manager::get_quiz_options($exclude);
        $mform->addElement('select', 'quizid', get_string('quiz', 'local_sentientia_exams'), $quiz_options);
        $mform->setType('quizid', PARAM_INT);
        $mform->addRule('quizid', null, 'required', null, 'client');
        $mform->addHelpButton('quizid', 'quiz', 'local_sentientia_exams');

        $mform->addElement('text', 'name', get_string('exam_name', 'local_sentientia_exams'),
            ['size' => 50, 'maxlength' => 254, 'placeholder' => 'Display name for this exam']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'exam_name', 'local_sentientia_exams');

        // ── Settings ──────────────────────────────────────────────────
        $mform->addElement('header', 'hdr_settings', get_string('heading_settings', 'local_sentientia_exams'));

        $mform->addElement('text', 'duration', get_string('duration', 'local_sentientia_exams'),
            ['size' => 10, 'placeholder' => '1800 (30 mins)']);
        $mform->setType('duration', PARAM_INT);
        $mform->addHelpButton('duration', 'duration', 'local_sentientia_exams');

        $mform->addElement('text', 'passinggrade', get_string('passinggrade', 'local_sentientia_exams'),
            ['size' => 5, 'placeholder' => '70']);
        $mform->setType('passinggrade', PARAM_FLOAT);

        // P1 #23 (2026-05-16) — exam category. Closes audit item #12 from
        // parity-audit-2026-05-15/sentientia_exams.md. Reuses the core
        // course_categories taxonomy so admins can group exams next to
        // their related training material (BizLMS treated exams as
        // courses, so this is the natural carry-over).
        $cats = $this->get_category_options();
        $mform->addElement('select', 'categoryid',
            get_string('exam_category', 'local_sentientia_exams'), $cats);
        $mform->setType('categoryid', PARAM_INT);
        $mform->setDefault('categoryid', 0);
        $mform->addHelpButton('categoryid', 'exam_category',
            'local_sentientia_exams');

        // ── Organisation ──────────────────────────────────────────────
        $mform->addElement('header', 'hdr_org', get_string('heading_org', 'local_sentientia_exams'));

        $orgs = $this->get_org_options();
        $mform->addElement('select', 'costcenterid', get_string('organisation', 'local_sentientia_exams'), $orgs);
        $mform->setType('costcenterid', PARAM_INT);

        // ── Status ────────────────────────────────────────────────────
        $mform->addElement('advcheckbox', 'status',
            get_string('exam_active', 'local_sentientia_exams'));
        $mform->setDefault('status', 1);
    }

    public function validation($data, $files) {
        $errors = [];
        if (isset($data['passinggrade'])) {
            $g = (float) $data['passinggrade'];
            if ($g < 0 || $g > 100) {
                $errors['passinggrade'] = get_string('passinggrade_invalid', 'local_sentientia_exams');
            }
        }
        if (isset($data['duration']) && $data['duration'] < 0) {
            $errors['duration'] = get_string('duration_invalid', 'local_sentientia_exams');
        }
        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $examid = (int) $data->examid;

        if ($examid === 0) {
            $newid = \local_sentientia_exams\exam_manager::create($data);
            return ['examid' => $newid, 'message' => get_string('examcreated', 'local_sentientia_exams')];
        } else {
            \local_sentientia_exams\exam_manager::update($examid, $data);
            return ['examid' => $examid, 'message' => get_string('examupdated', 'local_sentientia_exams')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $examid = (int) ($this->optional_param('examid', 0, PARAM_INT));

        if ($examid === 0) {
            $this->set_data((object) ['examid' => 0]);
            return;
        }

        $e = $DB->get_record('local_sentientia_exams', ['id' => $examid], '*', MUST_EXIST);
        $this->set_data((object) [
            'examid'       => $e->id,
            'name'         => $e->name,
            'quizid'       => $e->quizid,
            'duration'     => $e->duration ?? '',
            'passinggrade' => $e->passinggrade ?? '',
            'costcenterid' => $e->costcenterid ?? 0,
            // P1 #23 — pre-fill category.
            'categoryid'   => (int) ($e->categoryid ?? 0),
            'status'       => $e->status ?? 1,
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/sentientia_exams/index.php');
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/sentientia_exams:manage', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
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

    /**
     * P1 #23 — render the core course_categories tree as a select.
     * Indented by depth so the hierarchy is visible (Compliance →
     * Compliance/POSH, etc.).
     */
    private function get_category_options(): array {
        global $DB;
        $cats = $DB->get_records('course_categories', ['visible' => 1],
            'sortorder ASC', 'id, name, depth, path');
        $options = [0 => get_string('uncategorised',
            'local_sentientia_exams')];
        foreach ($cats as $c) {
            $indent = str_repeat('— ', max(0, (int) $c->depth - 1));
            $options[$c->id] = $indent . format_string($c->name);
        }
        return $options;
    }
}
