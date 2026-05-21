<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_sentientia_live\session_manager;

/**
 * Create / edit form for a live session — Phase E.1.g.
 *
 * Used by trainer/create.php and trainer/edit.php. Renders title +
 * the four settings checkboxes (allow_anonymous, show_results_to_audience,
 * allow_late_join) + max_concurrent input.
 *
 * @package local_sentientia_live
 */
class session_form extends \moodleform {

    protected function definition(): void {
        $mform = $this->_form;

        // ── Hidden ID (set in edit mode) ──
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // ── Title ──
        $mform->addElement('text', 'title',
            get_string('form_title_label', 'local_sentientia_live'),
            ['size' => 60, 'maxlength' => 200]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title',
            get_string('form_title_required', 'local_sentientia_live'),
            'required', null, 'client');
        $mform->addRule('title',
            get_string('form_title_too_long', 'local_sentientia_live'),
            'maxlength', 200, 'client');
        $mform->addHelpButton('title', 'form_title', 'local_sentientia_live');

        // ── Settings section ──
        $mform->addElement('header', 'settings_heading',
            get_string('form_settings_heading', 'local_sentientia_live'));

        $mform->addElement('advcheckbox', 'allow_anonymous',
            get_string('form_allow_anonymous_label', 'local_sentientia_live'),
            get_string('form_allow_anonymous_desc', 'local_sentientia_live'));
        $mform->setDefault('allow_anonymous', 0);
        $mform->addHelpButton('allow_anonymous', 'form_allow_anonymous',
            'local_sentientia_live');

        $mform->addElement('advcheckbox', 'show_results_to_audience',
            get_string('form_show_results_label', 'local_sentientia_live'),
            get_string('form_show_results_desc', 'local_sentientia_live'));
        $mform->setDefault('show_results_to_audience', 1);

        $mform->addElement('advcheckbox', 'allow_late_join',
            get_string('form_allow_late_join_label', 'local_sentientia_live'),
            get_string('form_allow_late_join_desc', 'local_sentientia_live'));
        $mform->setDefault('allow_late_join', 1);

        $mform->addElement('text', 'max_concurrent',
            get_string('form_max_concurrent_label', 'local_sentientia_live'),
            ['size' => 6]);
        $mform->setType('max_concurrent', PARAM_INT);
        $mform->setDefault('max_concurrent', 500);
        $mform->addHelpButton('max_concurrent', 'form_max_concurrent',
            'local_sentientia_live');

        // ── Action buttons ──
        $this->add_action_buttons(true,
            get_string('form_create_submit', 'local_sentientia_live'));
    }

    /**
     * Validate the form server-side. Title must be non-empty after trim.
     * max_concurrent must be in [1, 500].
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (empty(trim($data['title'] ?? ''))) {
            $errors['title'] = get_string('form_title_required',
                'local_sentientia_live');
        }

        $max = (int) ($data['max_concurrent'] ?? 500);
        if ($max < 1 || $max > 500) {
            $errors['max_concurrent'] = get_string('form_max_concurrent_range',
                'local_sentientia_live');
        }

        return $errors;
    }
}
