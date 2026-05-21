<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Session analytics CSV export — Phase E.7 (2026-05-21).
 *
 * Streams a CSV report of every response in a session — one row per
 * (slide, participant, response) tuple — with computed columns:
 *
 *   slide_position, slide_title, slide_type, participant_name,
 *   response_value, time_to_answer_seconds, response_timestamp
 *
 * Streamed via fputcsv to PHP output so even a 50K-row session won't
 * blow up the worker's memory limit. UTF-8 BOM prefixed so Excel
 * opens the file with correct accents.
 *
 * Auth:
 *   - require_login()
 *   - require_capability('local/sentientia_live:run', $context)
 *   - the requesting user must own the session OR have :manage
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_live:run', $context);

// Master flag gate — if the feature is off, nobody exports anything.
if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$id     = required_param('id', PARAM_INT);
$format = optional_param('format', 'csv', PARAM_ALPHA);

$sess = \local_sentientia_live\session_manager::get($id);
if (!$sess) {
    throw new \moodle_exception('invalidsession', 'local_sentientia_live');
}

// Ownership gate — only the session owner OR a user with :manage can
// download the responses. Mirrors session_manager::can_user_run().
$is_owner = ((int) $sess->ownerid === (int) $USER->id);
$can_manage = has_capability('local/sentientia_live:manage', $context);
if (!$is_owner && !$can_manage) {
    throw new \moodle_exception('nopermissions', 'error', '',
        get_string('export_session_label', 'local_sentientia_live'));
}

if ($format !== 'csv') {
    // Future-proof: json / xlsx could be added here. For now refuse
    // anything else explicitly rather than silently 200ing with wrong
    // content-type.
    throw new \moodle_exception('export_format_unsupported',
        'local_sentientia_live', '', $format);
}

// ── Stream CSV ──
$filename = clean_filename('sentientia-live-session-' . $sess->id . '-'
    . preg_replace('/[^a-z0-9]+/', '-', strtolower($sess->title))
    . '-' . date('Ymd-Hi') . '.csv');

// Defensive: nuke any output buffer Moodle layered on. Otherwise the
// CSV is wrapped in HTML page chrome on some hosts.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
if (!$out) {
    throw new \moodle_exception('export_open_failed', 'local_sentientia_live');
}

// UTF-8 BOM so Excel auto-detects encoding.
fwrite($out, "\xEF\xBB\xBF");

// Header rows — session metadata block, then a blank, then the per-
// response data header. Excel happily parses this; downstream tools
// can `tail -n +N` past the metadata block.
fputcsv($out, ['Sentientia LMS Live — session export']);
fputcsv($out, ['Session ID', (int) $sess->id]);
fputcsv($out, ['Title',      $sess->title]);
fputcsv($out, ['Join code',  substr($sess->code, 0, 3) . ' ' . substr($sess->code, 3)]);
fputcsv($out, ['State',      $sess->state]);
fputcsv($out, ['Created',    $sess->timecreated
    ? userdate((int) $sess->timecreated, '%Y-%m-%d %H:%M:%S')
    : '']);
fputcsv($out, ['Started',    $sess->timestarted
    ? userdate((int) $sess->timestarted, '%Y-%m-%d %H:%M:%S')
    : '']);
fputcsv($out, ['Ended',      $sess->timeended
    ? userdate((int) $sess->timeended, '%Y-%m-%d %H:%M:%S')
    : '']);
$slide_count    = \local_sentientia_live\slide_manager::count_for_session((int) $sess->id);
$total_resp     = $DB->count_records_sql(
    "SELECT COUNT(r.id)
       FROM {local_sentientia_live_responses} r
       JOIN {local_sentientia_live_slides} s ON s.id = r.slideid
      WHERE s.sessionid = :sid",
    ['sid' => $sess->id]);
$total_partic   = \local_sentientia_live\participant_manager::total_count_for_session(
    (int) $sess->id);
fputcsv($out, ['Total slides',       $slide_count]);
fputcsv($out, ['Total participants', $total_partic]);
fputcsv($out, ['Total responses',    $total_resp]);
fputcsv($out, ['Exported by',        fullname($USER)
    . ' <' . $USER->email . '>']);
fputcsv($out, ['Exported at',        userdate(time(), '%Y-%m-%d %H:%M:%S')]);
fputcsv($out, []);  // blank row separator

// Per-response data header.
fputcsv($out, [
    'slide_position',
    'slide_title',
    'slide_type',
    'is_quiz_correct',
    'participant_name',
    'response_value',
    'time_to_answer_seconds',
    'response_timestamp',
]);

// Build a slide-id → start-time map so we can compute time-to-answer
// per response without N queries to the events table. One scan of
// slide_changed events suffices.
$slide_start_times = [];
$slide_changed_events = $DB->get_records(
    'local_sentientia_live_events',
    ['sessionid' => (int) $sess->id, 'type' => 'slide_changed'],
    'timecreated ASC, id ASC',
    'id, payload_json, timecreated'
);
foreach ($slide_changed_events as $e) {
    $payload = json_decode($e->payload_json ?? '{}', true);
    if (is_array($payload) && isset($payload['slide_id'])) {
        // Last one wins (most recent slide-show event for this slide).
        $slide_start_times[(int) $payload['slide_id']] = (int) $e->timecreated;
    }
}
// Fallback for any slide never shown: session start time.
$session_start_fallback = (int) ($sess->timestarted ?? $sess->timecreated ?? 0);

// Stream responses. Order by slide position, then by submission time.
$rs = $DB->get_recordset_sql(
    "SELECT r.id          AS response_id,
            r.value_int,
            r.value_text,
            r.timecreated AS response_t,
            s.id          AS slide_id,
            s.position    AS slide_pos,
            s.title       AS slide_title,
            s.type        AS slide_type,
            s.settings_json,
            p.display_name
       FROM {local_sentientia_live_responses} r
       JOIN {local_sentientia_live_slides}    s ON s.id = r.slideid
  LEFT JOIN {local_sentientia_live_participants} p ON p.id = r.participantid
      WHERE s.sessionid = :sid
   ORDER BY s.position ASC, r.timecreated ASC, r.id ASC",
    ['sid' => $sess->id]
);

foreach ($rs as $row) {
    // Decode slide settings once per response — cheap (one json_decode).
    $settings = [];
    if (!empty($row->settings_json)) {
        $decoded = json_decode($row->settings_json, true);
        if (is_array($decoded)) {
            $settings = $decoded;
        }
    }

    // Resolve the response value into a human-readable string.
    $value_str = '';
    $is_correct = '';
    switch ($row->slide_type) {
        case 'multichoice':
        case 'quiz':
            $opts = $settings['options'] ?? [];
            $idx  = (int) $row->value_int;
            $value_str = $opts[$idx] ?? ('[idx ' . $idx . ']');
            if ($row->slide_type === 'quiz') {
                $correct_idx = (int) ($settings['correct_index'] ?? -1);
                $is_correct = ($correct_idx >= 0 && $idx === $correct_idx)
                    ? 'yes' : 'no';
            }
            break;
        case 'rating':
            $value_str = (string) (int) $row->value_int;
            break;
        case 'wordcloud':
        case 'openended':
            $value_str = (string) ($row->value_text ?? '');
            break;
        case 'ranking':
            // value_text is JSON [item_idx, ...] — render as
            // "1>2>3" with the item labels for readability.
            $value_str = (string) ($row->value_text ?? '');
            $order = json_decode($value_str, true);
            $items = $settings['items'] ?? [];
            if (is_array($order)) {
                $parts = [];
                foreach ($order as $rank => $iidx) {
                    $iidx = (int) $iidx;
                    $parts[] = ($rank + 1) . '. ' . ($items[$iidx] ?? ('item ' . $iidx));
                }
                $value_str = implode(' > ', $parts);
            }
            break;
        default:
            $value_str = (string) ($row->value_int ?? $row->value_text ?? '');
    }

    // Time-to-answer = response.t - slide_start_t (clamped to ≥ 0).
    $slide_start = $slide_start_times[(int) $row->slide_id]
        ?? $session_start_fallback;
    $time_to_answer = max(0, (int) $row->response_t - $slide_start);

    fputcsv($out, [
        (int) $row->slide_pos,
        $row->slide_title,
        $row->slide_type,
        $is_correct,
        $row->display_name ?? '(anonymous)',
        $value_str,
        $time_to_answer,
        userdate((int) $row->response_t, '%Y-%m-%d %H:%M:%S'),
    ]);
}
$rs->close();

fclose($out);
exit;
