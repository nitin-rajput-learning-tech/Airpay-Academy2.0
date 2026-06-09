<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

use local_sentientia_live\response_recorder;
use local_sentientia_live\slide_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Multiple-choice question type — Phase E.4 (2026-05-25).
 *
 * Audience picks ONE of N options. Validates 2-6 options at the class
 * layer (slide_manager::validate_settings still accepts 2-20 for
 * backward compatibility with already-stored production rows). Results
 * render as a horizontal bar chart with one bar per option, percentage
 * labels and a running count. The bar chart updates in place via SSE
 * (response_added event) without a page reload.
 *
 * Settings shape (class-layer validation — see validate_config):
 *   {options:       ["a", "b", ...]      // 2-6 strings, 1-200 chars
 *    correct_index: int (optional)       // 0-based; -1 / absent = no correct
 *    render_style:  "radio" | "buttons"  // default "radio"
 *   }
 *
 * Tally shape (rich — different from response_recorder::tally):
 *   [
 *     ['index' => 0, 'label' => 'Option A', 'count' => 12, 'is_correct' => false],
 *     ['index' => 1, 'label' => 'Option B', 'count' =>  3, 'is_correct' => true],
 *     ...
 *   ]
 *
 * Response payload (POST body fields the audience controller forwards):
 *   ['option_index' => int, 'slideid' => int, 'participantid' => int]
 *
 * Render context (expected by render()):
 *   - 'slide'           stdClass slide row
 *   - 'settings'        parsed settings array
 *   - 'aria_id_prefix'  string for accessibility id attrs
 *   - 'session'         stdClass session row
 *   - 'participant'     stdClass|null
 *   - 'action_url'      string (POST target)
 *   - 'sesskey'         string
 *   - 'token'           string|null (anonymous bearer)
 *   - 'show_correct'    bool — reveal correct answer in audience render
 *                       (typically false until trainer's reveal action)
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class multiple_choice extends abstract_question_type {

    public const SLUG = 'multichoice';
    public const FEATURE_FLAG = 'live.questiontype.multichoice';
    public const NAME_STRING_KEY = 'qtype_multichoice_name';
    public const DESCRIPTION_STRING_KEY = 'qtype_multichoice_desc';

    public const MIN_OPTIONS = 2;
    public const MAX_OPTIONS = 6;
    public const MAX_OPTION_LENGTH = 200;
    public const RENDER_STYLE_RADIO = 'radio';
    public const RENDER_STYLE_BUTTONS = 'buttons';
    public const VALID_RENDER_STYLES = [
        self::RENDER_STYLE_RADIO,
        self::RENDER_STYLE_BUTTONS,
    ];

    /**
     * Render audience-facing HTML — radio-group or button-group of N
     * options. Delegates to the qt_multiple_choice_audience Mustache
     * template via the global renderer so the markup matches our
     * design tokens (airpay-btn / airpay-card / aria roles).
     *
     * @param array $context See class doc for required keys.
     * @return string Safe-to-echo HTML.
     */
    public function render(array $context): string {
        global $OUTPUT, $PAGE;

        $slide      = $context['slide']    ?? null;
        $settings   = $context['settings'] ?? [];
        $session    = $context['session']  ?? null;
        $aria_prefix = (string) ($context['aria_id_prefix']
            ?? ('mc_' . (int) ($slide->id ?? 0)));
        $action_url = (string) ($context['action_url'] ?? '');
        $sesskey    = (string) ($context['sesskey'] ?? sesskey());
        $token      = (string) ($context['token'] ?? '');
        $show_correct = !empty($context['show_correct']);

        $options       = $settings['options'] ?? [];
        $correct_index = isset($settings['correct_index'])
            ? (int) $settings['correct_index']
            : -1;
        $render_style = $settings['render_style']
            ?? self::RENDER_STYLE_RADIO;
        if (!in_array($render_style, self::VALID_RENDER_STYLES, true)) {
            $render_style = self::RENDER_STYLE_RADIO;
        }

        // Shape the option rows. The template uses two flag sections
        // (`is_radio` / `is_buttons`) instead of swapping templates so
        // the audience HTML stays self-contained in one Mustache file.
        $option_rows = [];
        foreach ($options as $i => $label) {
            $option_rows[] = [
                'index'      => $i,
                'label'      => $label,
                'input_id'   => $aria_prefix . '_opt_' . $i,
                'is_correct' => $show_correct && $i === $correct_index,
            ];
        }

        $ctx = [
            'slideid'        => (int) ($slide->id ?? 0),
            'sessionid'      => (int) ($session->id ?? ($slide->sessionid ?? 0)),
            'question'       => (string) ($slide->title ?? ''),
            'aria_id_prefix' => $aria_prefix,
            'action_url'     => $action_url,
            'sesskey'        => $sesskey,
            'has_token'      => $token !== '',
            'token'          => $token,
            'options'        => $option_rows,
            'is_radio'       => $render_style === self::RENDER_STYLE_RADIO,
            'is_buttons'     => $render_style === self::RENDER_STYLE_BUTTONS,
            'show_correct'   => $show_correct,
        ];

        // Renderer falls back to $PAGE->get_renderer if $OUTPUT not set
        // (CLI test contexts). Render via the global $OUTPUT renderer
        // when available — that's what the rest of the plugin uses.
        $renderer = $OUTPUT ?? (isset($PAGE) ? $PAGE->get_renderer('core') : null);
        if ($renderer === null) {
            throw new \coding_exception('multiple_choice::render() requires a renderer');
        }
        return $renderer->render_from_template(
            'local_sentientia_live/qt_multiple_choice_audience', $ctx);
    }

    /**
     * Validate the option_index and persist via response_recorder.
     *
     * Returns the response row ID. Throws moodle_exception on invalid
     * payload (out-of-range index, missing slideid/participantid).
     *
     * Note: $userid is informational — the underlying storage is keyed
     * by participantid, not userid (anonymous participants have no
     * userid but always have a participantid). Callers MUST pass the
     * resolved participantid in the payload.
     *
     * @param int   $userid   Moodle user ID (0 for anonymous).
     * @param array $payload  Must contain option_index, slideid, participantid.
     * @return int Response row ID.
     * @throws \moodle_exception
     */
    public function persist_response(int $userid, array $payload): int {
        if (!array_key_exists('option_index', $payload)
            || $payload['option_index'] === null
            || $payload['option_index'] === '') {
            throw new \moodle_exception('mc_option_index_required',
                'local_sentientia_live');
        }
        $option_index = (int) $payload['option_index'];
        $slideid       = (int) ($payload['slideid'] ?? 0);
        $participantid = (int) ($payload['participantid'] ?? 0);

        if ($slideid <= 0) {
            throw new \moodle_exception('invalidslide',
                'local_sentientia_live');
        }
        if ($participantid <= 0) {
            throw new \moodle_exception('invalidparticipant',
                'local_sentientia_live');
        }

        // Bounds check against the slide's stored options. We re-fetch
        // settings here rather than trust the payload — keeps validation
        // server-authoritative even if the audience tampers with hidden
        // form fields.
        $slide = slide_manager::get($slideid);
        if (!$slide || $slide->type !== self::SLUG) {
            throw new \moodle_exception('invalidslide',
                'local_sentientia_live');
        }
        $settings = slide_manager::parse_settings($slide);
        $count = is_array($settings['options'] ?? null)
            ? count($settings['options'])
            : 0;
        if ($option_index < 0 || $option_index >= $count) {
            throw new \moodle_exception('response_out_of_range',
                'local_sentientia_live', '', $option_index);
        }

        // response_recorder::submit handles the idempotent upsert + SSE
        // event emission. We only own the type-level validation.
        return response_recorder::submit($slideid, $participantid,
            $option_index, null);
    }

    /**
     * Tally for chart rendering — rich shape with label + is_correct
     * flags per option (not just the [idx => count] map that
     * response_recorder::tally returns).
     *
     * Returns rows in option order (NOT sorted by count) so the audience
     * sees the same ordering they voted in. Trainer-side bar chart
     * sorts visually via CSS where needed.
     *
     * @return array Rows: [['index', 'label', 'count', 'is_correct'], ...]
     */
    public function tally(int $sessionid, int $slideid): array {
        $slide = slide_manager::get($slideid);
        if (!$slide || (int) $slide->sessionid !== $sessionid) {
            return [];
        }
        if ($slide->type !== self::SLUG) {
            return [];
        }

        $settings = slide_manager::parse_settings($slide);
        $options  = $settings['options'] ?? [];
        $correct_index = isset($settings['correct_index'])
            ? (int) $settings['correct_index']
            : -1;

        // response_recorder::tally for 'multichoice' returns [idx => count]
        // initialised to 0 for every option index. Reuse it so we don't
        // duplicate SQL.
        $counts = response_recorder::tally($slideid);

        $rows = [];
        foreach ($options as $i => $label) {
            $rows[] = [
                'index'      => $i,
                'label'      => $label,
                'count'      => (int) ($counts[$i] ?? 0),
                'is_correct' => $i === $correct_index,
            ];
        }
        return $rows;
    }

    /**
     * Render the trainer-facing live bar chart for an MC slide via the
     * qt_multiple_choice_result template. Computes the same bar-width
     * maths result_panel uses (and that chart_updater.js re-applies on
     * each SSE event) so the initial server render matches the live
     * mutations exactly.
     *
     * Not part of the abstract contract — a type-specific companion to
     * render(). run.php calls it for multichoice current slides; other
     * (still-scaffold) types keep using the generic result_panel until
     * they mature and grow their own result template.
     *
     * @param int  $sessionid
     * @param int  $slideid
     * @param bool $show_correct  Mark the correct option (trainer view).
     *                            Default false — audience never sees the
     *                            correct answer until the trainer reveals.
     * @return string HTML safe to echo.
     */
    public function render_result(int $sessionid, int $slideid,
                                    bool $show_correct = false): string {
        global $OUTPUT, $PAGE;

        $rows  = $this->tally($sessionid, $slideid);
        $total = 0;
        $max   = 0;
        foreach ($rows as $r) {
            $total += $r['count'];
            if ($r['count'] > $max) {
                $max = $r['count'];
            }
        }

        $options = [];
        foreach ($rows as $r) {
            $options[] = [
                'index'              => $r['index'],
                'label'              => $r['label'],
                'count'              => $r['count'],
                'percent'            => $total > 0
                    ? (int) round(($r['count'] / $total) * 100)
                    : 0,
                'bar_percent'        => $max > 0
                    ? (int) round(($r['count'] / $max) * 100)
                    : 0,
                'is_correct'         => $r['is_correct'],
                'show_correct_badge' => $show_correct && $r['is_correct'],
            ];
        }

        $ctx = [
            'slideid'         => $slideid,
            'sessionid'       => $sessionid,
            'total_responses' => $total,
            'has_responses'   => $total > 0,
            'show_correct'    => $show_correct,
            'options'         => $options,
        ];

        $renderer = $OUTPUT ?? (isset($PAGE) ? $PAGE->get_renderer('core') : null);
        if ($renderer === null) {
            throw new \coding_exception(
                'multiple_choice::render_result() requires a renderer');
        }
        return $renderer->render_from_template(
            'local_sentientia_live/qt_multiple_choice_result', $ctx);
    }

    /**
     * Validate creation-time settings for an MC slide.
     *
     * Class-layer constraints (stricter than slide_manager's storage
     * validator, which accepts 2-20 for legacy reasons):
     *   - options: array, 2-6 entries
     *   - each option: non-empty string, ≤200 chars
     *   - correct_index (optional): int, 0 ≤ N < count(options)
     *   - render_style (optional): "radio" | "buttons"
     *
     * Returns a map of field-name → error message. Empty map = valid.
     * Never throws — the form layer relies on the map form to surface
     * field-level errors.
     *
     * @param array $config Type-specific settings blob.
     * @return array Field → error map (empty when valid).
     */
    public function validate_config(array $config): array {
        $errors = [];

        $options = $config['options'] ?? null;
        if (!is_array($options)) {
            $errors['options'] = get_string('mc_options_must_be_array',
                'local_sentientia_live');
            return $errors;  // No point checking the rest.
        }
        $count = count($options);
        if ($count < self::MIN_OPTIONS || $count > self::MAX_OPTIONS) {
            $errors['options'] = get_string('mc_options_count_2_6',
                'local_sentientia_live', $count);
        }

        foreach ($options as $i => $opt) {
            if (!is_string($opt)) {
                $errors['options.' . $i] = get_string('mc_option_type',
                    'local_sentientia_live');
                continue;
            }
            $trimmed = trim($opt);
            if ($trimmed === '' || mb_strlen($trimmed) > self::MAX_OPTION_LENGTH) {
                $errors['options.' . $i] = get_string('mc_option_length',
                    'local_sentientia_live');
            }
        }

        // correct_index is OPTIONAL for multichoice. A negative value
        // (or blank / null) means "no correct answer" and is valid; an
        // explicit index must fall inside [0, count). This matches
        // slide_manager::validate_settings so the class layer and the
        // storage layer agree.
        if (array_key_exists('correct_index', $config)
            && $config['correct_index'] !== null
            && $config['correct_index'] !== '') {
            $ci = (int) $config['correct_index'];
            if ($ci >= $count) {
                $errors['correct_index'] = get_string(
                    'quiz_correct_out_of_range',
                    'local_sentientia_live');
            }
        }

        if (array_key_exists('render_style', $config)
            && $config['render_style'] !== null
            && $config['render_style'] !== ''
            && !in_array($config['render_style'],
                self::VALID_RENDER_STYLES, true)) {
            $errors['render_style'] = get_string('mc_render_style_invalid',
                'local_sentientia_live');
        }

        return $errors;
    }

    /**
     * Screen-reader announcements emitted by chart_updater.js when an
     * MC slide is on screen.
     */
    public function get_aria_announcements(): array {
        return [
            'response_recorded' => get_string('a11y_response_recorded',
                'local_sentientia_live'),
            'tally_updated'     => get_string('a11y_mc_tally_updated',
                'local_sentientia_live'),
            'correct_revealed'  => get_string('a11y_mc_correct_revealed',
                'local_sentientia_live'),
        ];
    }
}
