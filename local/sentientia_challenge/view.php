<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_challenge:view', $context);

$id  = required_param('id',  PARAM_INT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
if (!in_array($tab, ['overview', 'participants', 'leaderboard'], true)) {
    $tab = 'overview';
}

$challenge = $DB->get_record('local_sentientia_challenge_challenges',
    ['id' => $id], '*', MUST_EXIST);
$challenge->participants = (int) $DB->count_records(
    'local_sentientia_challenge_attempts', ['challengeid' => $id]);
$challenge->completed = (int) $DB->count_records(
    'local_sentientia_challenge_attempts',
    ['challengeid' => $id, 'status' => \local_sentientia_challenge\challenge_engine::ATTEMPT_COMPLETED]);

$myattempt = $DB->get_record('local_sentientia_challenge_attempts',
    ['challengeid' => $id, 'userid' => $USER->id]);
$row = \local_sentientia_challenge\challenge_engine::format_challenge_row($challenge, $myattempt ?: null);

$baseurl = new moodle_url('/local/sentientia_challenge/view.php', ['id' => $id]);
$PAGE->set_url($baseurl);
$PAGE->navbar->add(get_string('heading_index', 'local_sentientia_challenge'),
    new moodle_url('/local/sentientia_challenge/index.php'));
$PAGE->navbar->add($row['name']);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_view', 'local_sentientia_challenge', $row['name']));
$PAGE->set_heading(get_string('heading_view', 'local_sentientia_challenge', $row['name']));

$can_manage = has_capability('local/sentientia_challenge:manage', $context);
$can_join   = has_capability('local/sentientia_challenge:participate', $context);

// Leaderboard tab columns.
$lb_columns = [
    ['key' => 'rank',     'label' => get_string('lb_col_rank',      'local_sentientia_challenge'), 'sortable' => false],
    ['key' => 'fullname', 'label' => get_string('lb_col_user',      'local_sentientia_challenge'), 'sortable' => false],
    ['key' => 'points',   'label' => get_string('lb_col_points',    'local_sentientia_challenge'), 'sortable' => false],
    ['key' => 'attemptscompleted', 'label' => get_string('lb_col_completed', 'local_sentientia_challenge'), 'sortable' => false],
];

$mystatus_label = $row['mystatus'] !== ''
    ? get_string('attempt_' . $row['mystatus'], 'local_sentientia_challenge')
    : '';

$data = [
    'challenge'        => $row,
    'tab'              => $tab,
    'show_overview'    => $tab === 'overview',
    'show_participants' => $tab === 'participants',
    'show_leaderboard' => $tab === 'leaderboard',
    'can_manage'       => $can_manage,
    'can_join'         => $can_join,
    'mystatus_label'   => $mystatus_label,
    'lb_columns_json'  => json_encode($lb_columns),
    'lb_extra_args_json' => json_encode(['challengeid' => $id, 'tenantmode' => 'mine']),
    'index_url'        => (new moodle_url('/local/sentientia_challenge/index.php'))->out(false),
    'overview_url'     => (clone $baseurl)->out(false, ['tab' => 'overview']),
    'participants_url' => (clone $baseurl)->out(false, ['tab' => 'participants']),
    'leaderboard_url'  => (clone $baseurl)->out(false, ['tab' => 'leaderboard']),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_challenge/view', $data);
$PAGE->requires->js_call_amd('local_sentientia_challenge/challenge_actions', 'init',
    [['page' => 'view', 'tab' => $tab]]);
if ($tab === 'participants' || $tab === 'leaderboard') {
    $PAGE->requires->js_call_amd('theme_sentientia/datatable', 'init', []);
}
echo $OUTPUT->footer();
