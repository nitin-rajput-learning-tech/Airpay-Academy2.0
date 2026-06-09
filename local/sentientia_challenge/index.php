<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_challenge:view', $context);

$PAGE->set_url('/local/sentientia_challenge/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_index', 'local_sentientia_challenge'));
$PAGE->set_heading(get_string('heading_index', 'local_sentientia_challenge'));

// Datatable column config (datatable text-escapes by default; HTML cells
// must declare 'format' => 'html').
$columns = [
    ['key' => 'name',           'label' => get_string('col_name',         'local_sentientia_challenge'), 'sortable' => true,  'format' => 'html'],
    ['key' => 'target_label',   'label' => get_string('col_target',       'local_sentientia_challenge'), 'sortable' => false],
    ['key' => 'pointsreward',   'label' => get_string('col_points',       'local_sentientia_challenge'), 'sortable' => true],
    ['key' => 'statuslabel',    'label' => get_string('col_status',       'local_sentientia_challenge'), 'sortable' => true],
    ['key' => 'participants',   'label' => get_string('col_participants', 'local_sentientia_challenge'), 'sortable' => true],
    ['key' => 'mystatus_label', 'label' => get_string('col_progress',     'local_sentientia_challenge'), 'sortable' => false],
    ['key' => 'actions',        'label' => get_string('col_actions',      'local_sentientia_challenge'), 'sortable' => false, 'format' => 'html'],
];

$can_manage = has_capability('local/sentientia_challenge:manage', $context);

$data = [
    'columns_json'    => json_encode($columns),
    'extra_args_json' => json_encode(['search' => '', 'status' => 'active']),
    'leaderboardurl' => (new moodle_url('/local/sentientia_challenge/leaderboard.php'))->out(false),
    'can_manage'     => $can_manage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_challenge/index', $data);
$PAGE->requires->js_call_amd('local_sentientia_challenge/challenge_actions', 'init',
    [['page' => 'index']]);
$PAGE->requires->js_call_amd('theme_sentientia/datatable', 'init', []);
echo $OUTPUT->footer();
