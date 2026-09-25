<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_challenge:view', $context);

$challengeid = optional_param('challengeid', 0, PARAM_INT);

$PAGE->set_url('/local/sentientia_challenge/leaderboard.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_leaderboard', 'local_sentientia_challenge'));
$PAGE->set_heading(get_string('heading_leaderboard', 'local_sentientia_challenge'));

// Active-challenge dropdown options.
// ADR-031: only challenges the viewer can see - global and their own
// tenant's, or all for a cross-tenant viewer, none for a viewer with no
// tenant. This listed every tenant's active challenge names to anyone.
$crosstenant = \local_sentientia_platform\tenant::is_cross_tenant();
$viewertenant = \local_sentientia_challenge\challenge_engine::tenant_from_path($USER->open_path ?? '');
$active = [];
if ($crosstenant) {
    $active = $DB->get_records('local_sentientia_challenge_challenges',
        ['status' => \local_sentientia_challenge\challenge_engine::STATUS_ACTIVE],
        'name ASC', 'id, name, shortname');
} else if ($viewertenant > 0) {
    $active = $DB->get_records_select('local_sentientia_challenge_challenges',
        'status = :st AND (costcenterid = 0 OR costcenterid = :tn)',
        ['st' => \local_sentientia_challenge\challenge_engine::STATUS_ACTIVE, 'tn' => $viewertenant],
        'name ASC', 'id, name, shortname');
}
$challenges_options = [];
foreach ($active as $c) {
    $challenges_options[] = ['value' => (int) $c->id,
        'label' => format_string($c->name)];
}

$lb_columns = [
    ['key' => 'rank',     'label' => get_string('lb_col_rank',      'local_sentientia_challenge'), 'sortable' => false],
    ['key' => 'fullname', 'label' => get_string('lb_col_user',      'local_sentientia_challenge'), 'sortable' => false],
    ['key' => 'points',   'label' => get_string('lb_col_points',    'local_sentientia_challenge'), 'sortable' => false],
    ['key' => 'attemptscompleted', 'label' => get_string('lb_col_completed', 'local_sentientia_challenge'), 'sortable' => false],
];

// The "all tenants" toggle: get_leaderboard honours it for a cross-tenant
// caller only (ADR-031), so only offer it to one.
$can_view_all = $crosstenant;

$data = [
    'challenges_options' => $challenges_options,
    'lb_columns_json'    => json_encode($lb_columns),
    'lb_extra_args_json' => json_encode(['challengeid' => $challengeid, 'tenantmode' => 'mine']),
    'index_url'          => (new moodle_url('/local/sentientia_challenge/index.php'))->out(false),
    'can_view_all'       => $can_view_all,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_challenge/leaderboard', $data);
$PAGE->requires->js_call_amd('local_sentientia_challenge/challenge_actions', 'init',
    [['page' => 'leaderboard']]);
$PAGE->requires->js_call_amd('theme_sentientia/datatable', 'init', []);
echo $OUTPUT->footer();
