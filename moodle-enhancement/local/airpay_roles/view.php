<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/airpay_roles:view', $context);

$roleid = required_param('id',  PARAM_INT);
$tab    = optional_param('tab', 'overview', PARAM_ALPHA);
if (!in_array($tab, ['overview', 'capabilities', 'audit'], true)) {
    $tab = 'overview';
}

$role = \local_airpay_roles\role_manager::get_role($roleid);

$baseurl = new moodle_url('/local/airpay_roles/view.php', ['id' => $roleid]);
$PAGE->set_url($baseurl);
$PAGE->navbar->add(get_string('heading_index', 'local_airpay_roles'),
    new moodle_url('/local/airpay_roles/index.php'));
$PAGE->navbar->add($role['name']);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('heading_view', 'local_airpay_roles', $role['name']));
$PAGE->set_heading(get_string('heading_view', 'local_airpay_roles', $role['name']));

$can_manage = has_capability('local/airpay_roles:manage', $context);

// Capabilities tab columns.
$caps_columns = [
    ['key' => 'capability', 'label' => get_string('cap_col_name',      'local_airpay_roles'), 'sortable' => true],
    ['key' => 'component',  'label' => get_string('cap_col_component', 'local_airpay_roles'), 'sortable' => true],
    ['key' => 'risks_html', 'label' => get_string('cap_col_risks',     'local_airpay_roles'), 'sortable' => false, 'format' => 'html'],
    ['key' => 'perm_label', 'label' => get_string('cap_col_perm',      'local_airpay_roles'), 'sortable' => true],
    ['key' => 'actions',    'label' => get_string('cap_col_actions',   'local_airpay_roles'), 'sortable' => false, 'format' => 'html'],
];

// Audit tab columns.
$audit_columns = [
    ['key' => 'when',           'label' => get_string('audit_col_when',   'local_airpay_roles'), 'sortable' => false],
    ['key' => 'changedby_name', 'label' => get_string('audit_col_who',    'local_airpay_roles'), 'sortable' => false],
    ['key' => 'action_label',   'label' => get_string('audit_col_action', 'local_airpay_roles'), 'sortable' => false, 'format' => 'html'],
    ['key' => 'capability',     'label' => get_string('audit_col_cap',    'local_airpay_roles'), 'sortable' => false],
    ['key' => 'change',         'label' => get_string('audit_col_change', 'local_airpay_roles'), 'sortable' => false, 'format' => 'html'],
    ['key' => 'reason',         'label' => get_string('audit_col_reason', 'local_airpay_roles'), 'sortable' => false],
];

$data = [
    'role'             => $role,
    'tab'              => $tab,
    'show_overview'     => $tab === 'overview',
    'show_capabilities' => $tab === 'capabilities',
    'show_audit'        => $tab === 'audit',
    'can_manage'       => $can_manage,
    'caps_columns_json' => json_encode($caps_columns),
    'caps_extra_args_json' => json_encode(['roleid' => $roleid, 'search' => '', 'perm' => 'all']),
    'audit_columns_json' => json_encode($audit_columns),
    'audit_extra_args_json' => json_encode(['roleid' => $roleid, 'action' => '']),
    'index_url'        => (new moodle_url('/local/airpay_roles/index.php'))->out(false),
    'overview_url'     => (clone $baseurl)->out(false, ['tab' => 'overview']),
    'caps_url'         => (clone $baseurl)->out(false, ['tab' => 'capabilities']),
    'audit_url'        => (clone $baseurl)->out(false, ['tab' => 'audit']),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_roles/view', $data);
$PAGE->requires->js_call_amd('local_airpay_roles/role_actions', 'init',
    [['page' => 'view', 'tab' => $tab, 'roleid' => $roleid]]);
if ($tab === 'capabilities' || $tab === 'audit') {
    $PAGE->requires->js_call_amd('theme_airpayux/datatable', 'init', []);
}
echo $OUTPUT->footer();
