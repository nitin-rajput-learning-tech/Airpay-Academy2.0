<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_sentientia_live\slide_manager;

/**
 * Polymorphic slide form — Phase E.1.j (2026-05-21).
 *
 * One moodleform handles all 6 question types — the definition()
 * method branches on $this->_customdata['type'] to add type-specific
 * fields. Cuts file count vs. 6 separate forms while keeping the
 * per-type fields cleanly isolated in case-blocks.
 *
 * Type-specific UI:
 *
 *   multichoice — title + 2-20 options (repeat_elements)
 *   quiz        — title + 2-20 options + correct-answer radio
 *   rating      — title + scale_min + scale_max + optional N labels
 *   ranking     — title + 2-20 items (repeat_elements)
 *   wordcloud   — title + max_word_length + min_word_length +
 *                 max_responses_per_user + dedupe checkbox
 *   openended   — title + max_chars
 *
 * Customdata expected:
 *   type      string — required, one of slide_manager::VALID_TYPES
 *   sessionid int    — parent session (for the hidden field)
 *   slideid   int    — 0 for new, > 0 when editing
 *
 * On submit, the caller pulls form data, builds the settings array
 * appropriate to the type, and dispatches to slide_manager::add() or
 * slide_manager::update().
 *
 * @package local_sentientia_live
 */
class slide_form extends \moodleform {

    /** Initial number of option/item rows to render. */
    private const DEFAULT_OPTIONS = 4;

    /** Maximum rows that can be added via "Add more" button. */
    private const MAX_OPTIONS = 20;

    protected function definition(): void {
        $mform = $this->_form;

        $type = $this->_customdata['type'] ?? '';
        $sessionid = (int) ($this->_customdata['sessionid'] ?? 0);
        $slideid = (int) ($this->_customdata['slideid'] ?? 0);

        if (!in_array($type, slide_manager::VALID_TYPES, true)) {
            throw new \moodle_exception('invalidslidetype',
                'local_sentientia_live', '', $type);
        }

        // Hidden routing fields.
        $mform->addElement('hidden', 'sessionid', $sessionid);
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('hidden', 'slideid', $slideid);
        $mform->setType('slideid', PARAM_INT);
        $mform->addElement('hidden', 'type', $type);
        $mform->setType('type', PARAM_ALPHA);

        // Common: title (audience-facing question text).
        $mform->addElement('textarea', 'title',
            get_string('slide_title_label', 'local_sentientia_live'),
            ['rows' => 2, 'cols' => 60, 'maxlength' => 5000]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title',
            get_string('slide_title_required', 'local_sentientia_live'),
            'required', null, 'client');

        // Type label (read-only display so trainer remembers what they picked).
        $mform->addElement('static', 'type_display',
            get_string('slide_type_label', 'local_sentientia_live'),
            get_string('slide_type_' . $type, 'local_sentientia_live'));

        // Branch per type.
        switch ($type) {
            case 'multichoice':
                $this->add_options_repeat($mform, 'options',
                    'mc_option', 'mc_add_more');

                // Phase E.4 — render style (radio | buttons).
                $mform->addElement('select', 'render_style',
                    get_string('mc_render_style_label',
                        'local_sentientia_live'),
                    [
                        'radio'   => get_string('mc_render_style_radio',
                            'local_sentientia_live'),
                        'buttons' => get_string('mc_render_style_buttons',
                            'local_sentientia_live'),
                    ]);
                $mform->setType('render_style', PARAM_ALPHA);
                $mform->setDefault('render_style', 'radio');
                $mform->addHelpButton('render_style',
                    'mc_render_style', 'local_sentientia_live');

                // Phase E.4 — OPTIONAL correct-answer marking (1-based;
                // blank = no correct answer). Unlike quiz, multichoice
                // does not require a correct answer.
                $mform->addElement('text', 'mc_correct_index_1based',
                    get_string('mc_correct_label', 'local_sentientia_live'),
                    ['size' => 4]);
                $mform->setType('mc_correct_index_1based', PARAM_TEXT);
                $mform->addHelpButton('mc_correct_index_1based',
                    'mc_correct', 'local_sentientia_live');
                break;

            case 'quiz':
                $this->add_options_repeat($mform, 'options',
                    'quiz_option', 'quiz_add_more');
                // The correct-answer index is selected by clicking the
                // option (radio buttons next to each). We surface a
                // single field here that the user fills with the option
                // number (1-based) — Phase E.1.k will swap for inline
                // radio per option. For E.1.j, simpler integer input.
                $mform->addElement('text', 'correct_index_1based',
                    get_string('quiz_correct_index_label',
                        'local_sentientia_live'),
                    ['size' => 4]);
                $mform->setType('correct_index_1based', PARAM_INT);
                $mform->setDefault('correct_index_1based', 1);
                $mform->addRule('correct_index_1based',
                    get_string('quiz_correct_index_required',
                        'local_sentientia_live'),
                    'required', null, 'client');
                $mform->addHelpButton('correct_index_1based',
                    'quiz_correct_index', 'local_sentientia_live');
                break;

            case 'rating':
                $mform->addElement('text', 'scale_min',
                    get_string('rating_scale_min_label',
                        'local_sentientia_live'),
                    ['size' => 4]);
                $mform->setType('scale_min', PARAM_INT);
                $mform->setDefault('scale_min', 1);

                $mform->addElement('text', 'scale_max',
                    get_string('rating_scale_max_label',
                        'local_sentientia_live'),
                    ['size' => 4]);
                $mform->setType('scale_max', PARAM_INT);
                $mform->setDefault('scale_max', 5);

                $mform->addElement('text', 'scale_labels',
                    get_string('rating_scale_labels_label',
                        'local_sentientia_live'),
                    ['size' => 80]);
                $mform->setType('scale_labels', PARAM_TEXT);
                $mform->addHelpButton('scale_labels',
                    'rating_scale_labels', 'local_sentientia_live');
                break;

            case 'ranking':
                $this->add_options_repeat($mform, 'items',
                    'ranking_item', 'ranking_add_more');
                break;

            case 'wordcloud':
                $mform->addElement('text', 'max_word_length',
                    get_string('wc_max_word_length_label',
                        'local_sentientia_live'),
                    ['size' => 4]);
                $mform->setType('max_word_length', PARAM_INT);
                $mform->setDefault('max_word_length', 50);
                $mform->addHelpButton('max_word_length',
                    'wc_max_word_length', 'local_sentientia_live');

                // Phase E.5 — per-slide min word length + max submissions
                // per learner. Defaults come from the admin Switchboard
                // (default_min_word_length / default_max_responses).
                $mform->addElement('text', 'min_word_length',
                    get_string('wc_min_word_length_label',
                        'local_sentientia_live'),
                    ['size' => 4]);
                $mform->setType('min_word_length', PARAM_INT);
                $mform->setDefault('min_word_length',
                    (int) (get_config('local_sentientia_live',
                        'default_min_word_length') ?: 2));

                $mform->addElement('text', 'max_responses_per_user',
                    get_string('wc_max_responses_label',
                        'local_sentientia_live'),
                    ['size' => 4]);
                $mform->setType('max_responses_per_user', PARAM_INT);
                $mform->setDefault('max_responses_per_user',
                    (int) (get_config('local_sentientia_live',
                        'default_max_responses') ?: 3));
                $mform->addHelpButton('max_responses_per_user',
                    'wc_max_responses', 'local_sentientia_live');

                $mform->addElement('advcheckbox', 'dedupe',
                    get_string('wc_dedupe_label', 'local_sentientia_live'),
                    get_string('wc_dedupe_desc', 'local_sentientia_live'));
                $mform->setDefault('dedupe', 1);
                break;

            case 'openended':
                $mform->addElement('text', 'max_chars',
                    get_string('openended_max_chars_label',
                        'local_sentientia_live'),
                    ['size' => 6]);
                $mform->setType('max_chars', PARAM_INT);
                $mform->setDefault('max_chars', 280);
                $mform->addHelpButton('max_chars',
                    'openended_max_chars', 'local_sentientia_live');
                break;
        }

        $submit_label = $slideid > 0
            ? get_string('slide_form_update_submit', 'local_sentientia_live')
            : get_string('slide_form_add_submit', 'local_sentientia_live');
        $this->add_action_buttons(true, $submit_label);
    }

    /**
     * Repeating element helper — adds N text fields with "Add more"
     * button + per-row delete. Used for multichoice options, quiz
     * options, and ranking items.
     */
    private function add_options_repeat(\MoodleQuickForm $mform,
                                          string $array_name,
                                          string $label_string_key,
                                          string $add_more_string_key): void {
        $repeatel = [
            $mform->createElement('text', "{$array_name}_text",
                get_string($label_string_key, 'local_sentientia_live'),
                ['size' => 60, 'maxlength' => 200]),
        ];

        $repeatno = self::DEFAULT_OPTIONS;
        $repeatoptions = [
            "{$array_name}_text" => [
                'type' => PARAM_TEXT,
                'helpbutton' => null,
            ],
        ];

        $this->repeat_elements(
            $repeatel,
            $repeatno,
            $repeatoptions,
            "{$array_name}_count",
            "{$array_name}_add_more",
            2,   // add 2 at a time
            get_string($add_more_string_key, 'local_sentientia_live'),
            false
        );
    }

    /**
     * Server-side validation. Defers to slide_manager::validate_settings()
     * after assembling the array for the picked type.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $type = $data['type'] ?? '';
        if (empty(trim($data['title'] ?? ''))) {
            $errors['title'] = get_string('slide_title_required',
                'local_sentientia_live');
        }

        // Build the settings array the same way the controller will,
        // then run validate_settings — error messages will surface here.
        try {
            $settings = self::build_settings_from_form_data((array) $data, $type);
            slide_manager::validate_settings($type, $settings);
        } catch (\moodle_exception $e) {
            // Attach to a sensible field by type — title is the catch-all
            // since it's always present.
            $errors['title'] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Convert flat form data into the typed settings array that
     * slide_manager::add() / update() expects.
     *
     * Static helper so the controller (add_slide.php / edit_slide.php)
     * can reuse the same mapping.
     */
    public static function build_settings_from_form_data(array $data,
                                                          string $type): array {
        switch ($type) {
            case 'multichoice':
            case 'quiz':
                // repeat_elements produces "options_text" => [0 => 'A', 1 => 'B', ...]
                $opts_raw = $data['options_text'] ?? [];
                $options = [];
                if (is_array($opts_raw)) {
                    foreach ($opts_raw as $opt) {
                        $trim = trim((string) $opt);
                        if ($trim !== '') {
                            $options[] = $trim;
                        }
                    }
                }
                $settings = ['options' => $options];
                if ($type === 'quiz') {
                    $idx1 = (int) ($data['correct_index_1based'] ?? 1);
                    $settings['correct_index'] = max(0, $idx1 - 1);  // 1-based -> 0-based
                }
                if ($type === 'multichoice') {
                    $rs = $data['render_style'] ?? 'radio';
                    $settings['render_style'] =
                        in_array($rs, ['radio', 'buttons'], true) ? $rs : 'radio';
                    // Optional correct answer — blank / non-numeric = none.
                    $raw = trim((string) ($data['mc_correct_index_1based'] ?? ''));
                    if ($raw !== '' && ctype_digit($raw) && (int) $raw >= 1) {
                        $settings['correct_index'] = (int) $raw - 1;  // 1-based -> 0-based
                    }
                }
                return $settings;

            case 'rating':
                $labels_raw = trim((string) ($data['scale_labels'] ?? ''));
                $labels = $labels_raw === ''
                    ? []
                    : array_map('trim', explode('|', $labels_raw));
                return [
                    'scale_min'    => (int) ($data['scale_min'] ?? 1),
                    'scale_max'    => (int) ($data['scale_max'] ?? 5),
                    'scale_labels' => $labels,
                ];

            case 'ranking':
                $raw = $data['items_text'] ?? [];
                $items = [];
                if (is_array($raw)) {
                    foreach ($raw as $i) {
                        $trim = trim((string) $i);
                        if ($trim !== '') {
                            $items[] = $trim;
                        }
                    }
                }
                return ['items' => $items];

            case 'wordcloud':
                return [
                    'max_word_length'        => (int) ($data['max_word_length'] ?? 50),
                    'min_word_length'        => (int) ($data['min_word_length'] ?? 2),
                    'max_responses_per_user' => (int) ($data['max_responses_per_user'] ?? 3),
                    'dedupe'                 => !empty($data['dedupe']),
                ];

            case 'openended':
                return ['max_chars' => (int) ($data['max_chars'] ?? 280)];

            default:
                return [];
        }
    }
}
