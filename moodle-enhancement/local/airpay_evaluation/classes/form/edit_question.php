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

        // ── Multichoice options (only when type=multichoice) ──────────
        $mform->addElement('textarea', 'options',
            get_string('question_options', 'local_airpay_evaluation'),
            ['rows' => 5, 'cols' => 50, 'placeholder' => "Option A\nOption B\nOption C"]);
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'question_options', 'local_airpay_evaluation');
        $mform->disabledIf('options', 'questiontype', 'neq', 'multichoice');

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
        if (($data['questiontype'] ?? '') === 'multichoice') {
            $opts = evaluation_manager::parse_options($data['options'] ?? '');
            if (count($opts) < 2) {
                $errors['options'] = get_string('multichoice_needs_options', 'local_airpay_evaluation');
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

        // Decode options back to text (one per line).
        $opts_text = '';
        if ($q->questiontype === 'multichoice') {
            $opts = evaluation_manager::decode_options($q->options);
            $opts_text = implode("\n", $opts);
        }

        $this->set_data((object) [
            'questionid'   => $q->id,
            'evaluationid' => $q->evaluationid,
            'questiontype' => $q->questiontype,
            'questiontext' => $q->questiontext,
            'options'      => $opts_text,
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
