<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_roles:audit', $context);

$PAGE->set_url('/local/sentientia_roles/audit.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_audit', 'local_sentientia_roles'));
$PAGE->set_heading(get_string('heading_audit', 'local_sentientia_roles'));

// Role <select> options.
$roles_options = [];
$allroles = role_fix_names(get_all_roles($context), $context, ROLENAME_ORIGINAL);
foreach ($allroles as $r) {
    $roles_options[] = ['value' => (int) $r->id,
        'label' => format_string($r->localname ?? $r->shortname) . ' (' . s($r->shortname) . ')'];
}

// Action <select> options.
$actions_options = [];
foreach (['capability_set', 'capability_unset', 'role_assigned', 'role_unassigned',
          'role_created', 'role_deleted'] as $a) {
    $actions_options[] = ['value' => $a,
        'label' => get_string('audit_action_' . $a, 'local_sentientia_roles')];
}

$audit_columns = [
    ['key' => 'when',           'label' => get_string('audit_col_when',   'local_sentientia_roles'), 'sortable' => false],
    ['key' => 'changedby_name', 'label' => get_string('audit_col_who',    'local_sentientia_roles'), 'sortable' => false],
    ['key' => 'roleshortname',  'label' => get_string('audit_col_role',   'local_sentientia_roles'), 'sortable' => false],
    ['key' => 'action_label',   'label' => get_string('audit_col_action', 'local_sentientia_roles'), 'sortable' => false, 'format' => 'html'],
    ['key' => 'capability',     'label' => get_string('audit_col_cap',    'local_sentientia_roles'), 'sortable' => false],
    ['key' => 'change',         'label' => get_string('audit_col_change', 'local_sentientia_roles'), 'sortable' => false, 'format' => 'html'],
    ['key' => 'reason',         'label' => get_string('audit_col_reason', 'local_sentientia_roles'), 'sortable' => false],
];

$can_export = has_capability('local/sentientia_roles:export', $context);

$data = [
    'roles_options'         => $roles_options,
    'actions_options'       => $actions_options,
    'audit_columns_json'    => json_encode($audit_columns),
    'audit_extra_args_json' => json_encode(['roleid' => 0, 'action' => '']),
    'index_url'             => (new moodle_url('/local/sentientia_roles/index.php'))->out(false),
    'exporturl'             => (new moodle_url('/local/sentientia_roles/exportcsv.php',
                                ['scope' => 'audit', 'sesskey' => sesskey()]))->out(false),
    'can_export'            => $can_export,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_roles/audit', $data);
$PAGE->requires->js_call_amd('local_sentientia_roles/role_actions', 'init',
    [['page' => 'audit']]);
$PAGE->requires->js_call_amd('theme_sentientia/datatable', 'init', []);
echo $OUTPUT->footer();
