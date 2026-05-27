<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

use local_sentientia_live\response_recorder;
use local_sentientia_live\slide_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Open-ended question type — Phase E.6 implementation (D4, 2026-05-24).
 *
 * Audience submits FREE-FORM text up to a configurable cap. D4 chip
 * raised the absolute ceiling 280 → 500 chars (the P3-R stub still
 * documented 280 — superseded). Trainer can flip a moderation toggle to
 * hide individual responses without deleting them. The result panel
 * paginates at PAGE_SIZE per page so a 300-attendee session stays
 * legible on the projector.
 *
 * Settings shape:
 *   {max_chars:    int (10-500, default 500),
 *    moderation:   bool (default false, trainer-side hide/show toggle)}
 *
 * Tally shape (no aggregation — display-all):
 *   [
 *     ['id' => int, 'text' => string, 'participantid' => int,
 *      'timecreated' => int],
 *     ...
 *   ]
 *
 * Response payload (decoded POST body — keys this class plucks):
 *   ['slide_id' => int, 'text' => string]
 *
 * Backwards-compatibility: 'value_text' / 'slideid' aliases accepted so
 * play.php's existing POST shape keeps working without a refactor.
 *
 * Anti-abuse:
 *   - HTML stripped at persist time via clean_param(PARAM_TEXT).
 *   - 500-char hard ceiling enforced both in validate_config (settings
 *     editor blocked) AND in persist_response (untrusted POST blocked
 *     even if a settings-blob's max_chars is somehow stale).
 *   - response_recorder's uk_slide_participant unique key already
 *     enforces "one response per participant per slide" (re-submit
 *     overwrites).
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class open_ended extends abstract_question_type {

    public const SLUG = 'openended';
    public const FEATURE_FLAG = 'live.questiontype.openended';
    public const NAME_STRING_KEY = 'qtype_openended_name';
    public const DESCRIPTION_STRING_KEY = 'qtype_openended_desc';

    /** Hard cap on response length — D4 raised from 280 → 500. */
    public const MAX_CHARS_CEILING = 500;

    /** Floor on max_chars setting (anything shorter is hostile to learners). */
    public const MAX_CHARS_FLOOR = 10;

    /** Default page size for the trainer's paginated response list. */
    public const PAGE_SIZE = 10;

    /**
     * @inheritDoc
     */
    public function render(array $context): string {
        global $OUTPUT;

        $slide = $context['slide']
            ?? throw new \coding_exception('render: $context[slide] required');
        $settings = $context['settings']
            ?? slide_manager::parse_settings($slide);
        $aria_id_prefix = $context['aria_id_prefix']
            ?? ('qt-openended-' . (int) $slide->id);

        $max_chars = $this->clamp_max_chars(
            (int) ($settings['max_chars'] ?? self::MAX_CHARS_CEILING));

        return $OUTPUT->render_from_template(
            'local_sentientia_live/qt_open_ended_audience',
            [
                'slideid'        => (int) $slide->id,
                'sessionid'      => (int) $slide->sessionid,
                'max_chars'      => $max_chars,
                'aria_id_prefix' => $aria_id_prefix,
                'placeholder'    => get_string('openended_response_placeholder',
                    'local_sentientia_live'),
                'submit_label'   => get_string('audience_submit_response',
                    'local_sentientia_live'),
                'sesskey'        => sesskey(),
                'label_text'     => get_string('qt_openended_audience_label',
                    'local_sentientia_live'),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function persist_response(int $userid, array $payload): int {
        $slideid = $this->payload_slide_id($payload);
        $text    = (string) ($payload['text']
            ?? $payload['value_text']
            ?? '');

        // Strip every HTML tag; trim; cap.
        $text = trim(clean_param($text, PARAM_TEXT));
        if ($text === '') {
            throw new \moodle_exception('response_text_required',
                'local_sentientia_live');
        }
        if (mb_strlen($text) > self::MAX_CHARS_CEILING) {
            throw new \moodle_exception('response_text_too_long',
                'local_sentientia_live', '', self::MAX_CHARS_CEILING);
        }

        return response_recorder::submit($slideid, $userid, null, $text);
    }

    /**
     * @inheritDoc
     *
     * No aggregation. Returns flat list, newest first, with participant
     * display name resolved so the trainer's paginated view can render
     * "<name>: <text>" without an N+1 lookup.
     */
    public function tally(int $sessionid, int $slideid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT r.id, r.participantid, p.display_name,
                    r.value_text, r.timecreated
               FROM {local_sentientia_live_responses} r
          LEFT JOIN {local_sentientia_live_participants} p
                    ON p.id = r.participantid
              WHERE r.slideid = :sid
           ORDER BY r.timecreated DESC, r.id DESC",
            ['sid' => $slideid]
        );
        $out = [];
        foreach ($rows as $r) {
            $t = trim((string) $r->value_text);
            if ($t === '') {
                continue;
            }
            $out[] = [
                'id'            => (int) $r->id,
                'text'          => $t,
                'display_name'  => $r->display_name ?? '?',
                'timecreated'   => (int) $r->timecreated,
                'participantid' => (int) $r->participantid,
            ];
        }
        return $out;
    }

    /**
     * @inheritDoc
     */
    public function validate_config(array $config): array {
        $errors = [];

        // max_chars (optional — default applied).
        if (array_key_exists('max_chars', $config)) {
            $max_chars = $config['max_chars'];
            if (!is_int($max_chars) && !ctype_digit((string) $max_chars)) {
                $errors['max_chars'] = get_string(
                    'openended_max_chars_int_required',
                    'local_sentientia_live');
            } else {
                $n = (int) $max_chars;
                if ($n < self::MAX_CHARS_FLOOR
                    || $n > self::MAX_CHARS_CEILING) {
                    $errors['max_chars'] = get_string(
                        'openended_max_chars_out_of_range',
                        'local_sentientia_live', (object) [
                            'min' => self::MAX_CHARS_FLOOR,
                            'max' => self::MAX_CHARS_CEILING,
                        ]);
                }
            }
        }

        // moderation (optional — bool/int).
        if (array_key_exists('moderation', $config)) {
            $mod = $config['moderation'];
            if (!is_bool($mod)
                && !in_array($mod, [0, 1, '0', '1', true, false], true)) {
                $errors['moderation'] = get_string('openended_moderation_bool',
                    'local_sentientia_live');
            }
        }

        return $errors;
    }

    /**
     * @inheritDoc
     */
    public function get_aria_announcements(): array {
        return [
            'response_recorded' => get_string('a11y_response_recorded',
                'local_sentientia_live'),
            'new_response'      => get_string('qt_openended_a11y_new_response',
                'local_sentientia_live'),
            'response_hidden'   => get_string('qt_openended_a11y_response_hidden',
                'local_sentientia_live'),
            'response_shown'    => get_string('qt_openended_a11y_response_shown',
                'local_sentientia_live'),
        ];
    }

    /**
     * Paginate the tally for trainer-facing display. Pure data — no DB
     * hit; takes the tally + page number, slices into PAGE_SIZE chunks.
     *
     * @param array $tally    Result of {@see tally()}.
     * @param int   $page     1-based page index (clamped to valid range).
     * @param int   $pagesize Items per page (clamped to PAGE_SIZE if invalid).
     * @return array {
     *   'rows'        => array slice for this page,
     *   'page'        => current page,
     *   'total_pages' => total page count,
     *   'page_size'   => effective page size,
     *   'total'       => total response count,
     *   'has_prev'    => bool,
     *   'has_next'    => bool,
     * }
     */
    public static function paginate(array $tally, int $page = 1,
                                      int $pagesize = self::PAGE_SIZE): array {
        $pagesize = max(1, $pagesize > 0 ? $pagesize : self::PAGE_SIZE);
        $total = count($tally);
        $total_pages = max(1, (int) ceil($total / $pagesize));
        $page = max(1, min($page, $total_pages));
        $offset = ($page - 1) * $pagesize;
        $slice = array_slice($tally, $offset, $pagesize);

        return [
            'rows'        => $slice,
            'page'        => $page,
            'total_pages' => $total_pages,
            'page_size'   => $pagesize,
            'total'       => $total,
            'has_prev'    => $page > 1,
            'has_next'    => $page < $total_pages,
        ];
    }

    /**
     * Clamp a max_chars value into the [FLOOR, CEILING] range. Used by
     * render() so a stale settings blob from before D4 doesn't render
     * a 280-char textarea against a 500-char persist ceiling.
     */
    private function clamp_max_chars(int $value): int {
        return max(self::MAX_CHARS_FLOOR,
            min(self::MAX_CHARS_CEILING, $value));
    }

    /**
     * Resolve the slide ID from a payload. Accepts 'slide_id' (canonical
     * per abstract_question_type docstring) or 'slideid' (legacy from
     * audience/play.php's hidden field).
     */
    private function payload_slide_id(array $payload): int {
        $raw = $payload['slide_id'] ?? $payload['slideid'] ?? 0;
        $id = (int) $raw;
        if ($id <= 0) {
            throw new \moodle_exception('invalidslide',
                'local_sentientia_live');
        }
        return $id;
    }
}
