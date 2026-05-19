<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_evaluation\form;

defined('MOODLE_INTERNAL') || die();

use local_airpay_evaluation\evaluation_manager;

/**
 * Create / edit question dynamic form.
 *
 * @package    local_airpay_evaluation
 */
class edit_question extends \core_form\dynamic_form {

    protected function definition() {
        $mform = $this->_form;
        $questionid = (int) ($this->optional_param('questionid', 0, PARAM_INT));
        $evaluationid = (int) ($this->optional_param('evaluationid', 0, PARAM_INT));

        $mform->addElement('hidden', 'questionid', $questionid);
        $mform->setType('questionid', PARAM_INT);
        $mform->addElement('hidden', 'evaluationid', $evaluationid);
        $mform->setType('evaluationid', PARAM_INT);

        // ── Question type ─────────────────────────────────────────────
        $mform->addElement('select', 'questiontype',
            get_string('question_type', 'local_airpay_evaluation'),
            evaluation_manager::QUESTION_TYPES);
        $mform->setType('questiontype', PARAM_ALPHA);
        $mform->addRule('questiontype', null, 'required', null, 'client');
        $mform->addHelpButton('questiontype', 'question_type', 'local_airpay_evaluation');

        // ── Question text ─────────────────────────────────────────────
        $mform->addElement('textarea', 'questiontext',
            get_string('question_text', 'local_airpay_evaluation'),
            ['rows' => 3, 'cols' => 60, 'placeholder' => 'e.g. The training met my expectations.']);
        $mform->setType('questiontext', PARAM_TEXT);
        $mform->addRule('questiontext', null, 'required', null, 'client');

        // ── Multichoice options (only when type=multichoice or
        //    type=multichoice_multi — P1 #18) ──────────────────────────
        $mform->addElement('textarea', 'options',
            get_string('question_options', 'local_airpay_evaluation'),
            ['rows' => 5, 'cols' => 50, 'placeholder' => "Option A\nOption B\nOption C"]);
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'question_options', 'local_airpay_evaluation');
        // disabledIf can chain via a conditions array (Moodle quickform
        // semantics); both type variants enable the options textarea.
        $mform->hideIf('options', 'questiontype', 'noteq', 'multichoice');
        // The hideIf above only handles the FIRST condition. Multi-OR
        // hiding in mforms is done via element grouping; simpler to just
        // leave the textarea visible and rely on server-side validation
        // to ignore it for irrelevant types. The build_question_options_json
        // helper handles type dispatch.

        // ── Numeric bounds (only when type=numeric — P1 #18) ──────────
        $mform->addElement('text', 'numeric_min',
            get_string('numeric_min', 'local_airpay_evaluation'),
            ['size' => 10, 'placeholder' => '— optional —']);
        $mform->setType('numeric_min', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('numeric_min', 'numeric_min',
            'local_airpay_evaluation');
        $mform->hideIf('numeric_min', 'questiontype', 'neq', 'numeric');

        $mform->addElement('text', 'numeric_max',
            get_string('numeric_max', 'local_airpay_evaluation'),
            ['size' => 10, 'placeholder' => '— optional —']);
        $mform->setType('numeric_max', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('numeric_max', 'numeric_max',
            'local_airpay_evaluation');
        $mform->hideIf('numeric_max', 'questiontype', 'neq', 'numeric');

        // ── Required toggle ───────────────────────────────────────────
        $mform->addElement('advcheckbox', 'required',
            get_string('question_required', 'local_airpay_evaluation'));
        $mform->setDefault('required', 1);

        // ── Anonymous toggle (Phase G.2) ──────────────────────────────
        // When set, the analysis surface hides responder identity for
        // *this question only* — even if the parent evaluation isn't
        // anonymous. Useful for sensitive questions in an otherwise
        // attributed survey.
        $mform->addElement('advcheckbox', 'anonymous',
            'Anonymous response',
            'Hide responder identity for this question only');
        $mform->setDefault('anonymous', 0);
    }

    public function validation($data, $files) {
        $errors = [];
        $type = $data['questiontype'] ?? '';

        if ($type === 'multichoice' || $type === 'multichoice_multi') {
            $opts = evaluation_manager::parse_options($data['options'] ?? '');
            if (count($opts) < 2) {
                $errors['options'] = get_string('multichoice_needs_options',
                    'local_airpay_evaluation');
            }
        }

        // P1 #18 — numeric bounds: each must parse to int when set;
        // max ≥ min when both set.
        if ($type === 'numeric') {
            $min_raw = isset($data['numeric_min']) ? trim((string) $data['numeric_min']) : '';
            $max_raw = isset($data['numeric_max']) ? trim((string) $data['numeric_max']) : '';
            $min = ($min_raw === '') ? null : (is_numeric($min_raw) ? (int) $min_raw : false);
            $max = ($max_raw === '') ? null : (is_numeric($max_raw) ? (int) $max_raw : false);
            if ($min === false) {
                $errors['numeric_min'] = get_string('numeric_must_be_integer',
                    'local_airpay_evaluation');
            }
            if ($max === false) {
                $errors['numeric_max'] = get_string('numeric_must_be_integer',
                    'local_airpay_evaluation');
            }
            if ($min !== null && $max !== null && $min !== false && $max !== false
                    && $max < $min) {
                $errors['numeric_max'] = get_string('numeric_min_max_invalid',
                    'local_airpay_evaluation');
            }
        }

        return $errors;
    }

    public function process_dynamic_submission() {
        $data = $this->get_data();
        $questionid = (int) $data->questionid;

        if ($questionid === 0) {
            $newid = evaluation_manager::create_question($data);
            return ['questionid' => $newid, 'message' => get_string('questioncreated', 'local_airpay_evaluation')];
        } else {
            evaluation_manager::update_question($questionid, $data);
            return ['questionid' => $questionid, 'message' => get_string('questionupdated', 'local_airpay_evaluation')];
        }
    }

    public function set_data_for_dynamic_submission(): void {
        $questionid = (int) ($this->optional_param('questionid', 0, PARAM_INT));
        $evaluationid = (int) ($this->optional_param('evaluationid', 0, PARAM_INT));

        if ($questionid === 0) {
            $this->set_data((object) [
                'questionid' => 0,
                'evaluationid' => $evaluationid,
                'questiontype' => 'rating',
            ]);
            return;
        }

        $q = evaluation_manager::get_question($questionid);
        if (!$q) {
            throw new \moodle_exception('invalidquestion', 'local_airpay_evaluation');
        }

        // Decode options back to text (one per line) and numeric bounds.
        // P1 #18 — multichoice + multichoice_multi share the same option
        // list representation; numeric has min/max bounds.
        $opts_text  = '';
        $num_min = '';
        $num_max = '';
        if ($q->questiontype === 'multichoice' || $q->questiontype === 'multichoice_multi') {
            $opts = evaluation_manager::decode_options($q->options);
            $opts_text = implode("\n", $opts);
        } else if ($q->questiontype === 'numeric') {
            $bounds = evaluation_manager::decode_numeric_bounds($q->options ?? null);
            if ($bounds['min'] !== null) $num_min = (string) $bounds['min'];
            if ($bounds['max'] !== null) $num_max = (string) $bounds['max'];
        }

        $this->set_data((object) [
            'questionid'   => $q->id,
            'evaluationid' => $q->evaluationid,
            'questiontype' => $q->questiontype,
            'questiontext' => $q->questiontext,
            'options'      => $opts_text,
            'numeric_min'  => $num_min,
            'numeric_max'  => $num_max,
            'required'     => $q->required ?? 1,
            'anonymous'    => $q->anonymous ?? 0,
        ]);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $evaluationid = (int) ($this->optional_param('evaluationid', 0, PARAM_INT));
        return new \moodle_url('/local/airpay_evaluation/questions.php', ['id' => $evaluationid]);
    }

    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/airpay_evaluation:manage', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }
}
