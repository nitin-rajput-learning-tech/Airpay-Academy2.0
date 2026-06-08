<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Single-board view page.
 *
 * @package local_sentientia_leaderboard
 */

require_once(__DIR__ . '/../../config.php');

require_login();
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$boardid = required_param('id', PARAM_INT);

$context = context_system::instance();
require_capability('local/sentientia_leaderboard:view', $context);

$board = \local_sentientia_leaderboard\board_manager::get($boardid);
if (!$board) {
    throw new moodle_exception('error_noboard', 'local_sentientia_leaderboard');
}

// Master flag gate.
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled(
            'sentientia.leaderboards.enabled')) {
        throw new moodle_exception('feature_disabled',
            'local_sentientia_leaderboard');
    }
}

// Tenant gate.
$can_view_all = has_capability('local/sentientia_leaderboard:viewall', $context);
if (!$can_view_all && (int) $board->tenantid > 0) {
    $viewer_root = \local_sentientia_platform\tenant::root_for_current_user();
    if ($viewer_root !== (int) $board->tenantid) {
        throw new moodle_exception('error_outoftenant',
            'local_sentientia_leaderboard');
    }
}

$PAGE->set_url('/local/sentientia_leaderboard/view.php', ['id' => $boardid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($board->name));
$PAGE->set_heading(format_string($board->name));

$result = \local_sentientia_leaderboard\ranking_engine::read_top(
    $boardid, 25, $can_view_all);
$my_rank = \local_sentientia_leaderboard\ranking_engine::read_my_rank(
    $boardid, (int) $USER->id);

$data = [
    'boardid'         => $boardid,
    'name'            => format_string($board->name),
    'type'            => $board->type,
    'type_label'      => get_string('type_' . $board->type,
        'local_sentientia_leaderboard'),
    'last_recomputed_str' => (int) $board->last_recomputed > 0
        ? userdate((int) $board->last_recomputed) : '-',
    'rows'            => $result['rows'],
    'has_rows'        => !empty($result['rows']),
    'total'           => $result['total'],
    'optout_total'    => $result['optout_total'],
    'my_rank'         => $my_rank ? (int) $my_rank['rank'] : 0,
    'my_points'       => $my_rank ? (int) $my_rank['points'] : 0,
    'my_optout'       => \local_sentientia_leaderboard\optout_manager::is_opted_out((int) $USER->id) ? 1 : 0,
    'show_my_rank'    => $my_rank !== null,
    'preferences_url' => (new moodle_url('/local/sentientia_leaderboard/preferences.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_leaderboard/board_view', $data);

// Enable real-time updates via AMD client when the realtime flag is on.
$realtime_on = !class_exists('\\local_sentientia_platform\\feature_flags')
    || \local_sentientia_platform\feature_flags::is_enabled(
        'sentientia.leaderboards.realtime.enabled');

$PAGE->requires->js_call_amd(
    'local_sentientia_leaderboard/leaderboard_client',
    'init',
    [[
        'boardid'   => $boardid,
        'realtime'  => $realtime_on,
    ]]
);

echo $OUTPUT->footer();
