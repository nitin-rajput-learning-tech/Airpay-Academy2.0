<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_roles:view', $context);

$PAGE->set_url('/local/sentientia_roles/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_index', 'local_sentientia_roles'));
$PAGE->set_heading(get_string('heading_index', 'local_sentientia_roles'));

// Build archetype options for the filter <select>.
$archetypes = [];
foreach (['manager', 'coursecreator', 'editingteacher', 'teacher', 'student',
          'guest', 'user', 'frontpage'] as $arch) {
    $archetypes[] = ['key' => $arch, 'label' => $arch];
}
$archetypes[] = ['key' => 'custom',
    'label' => get_string('ov_archetype_custom', 'local_sentientia_roles')];

// Datatable column config (datatable text-escapes by default; HTML cells
// must declare 'format' => 'html').
$columns = [
    ['key' => 'name',         'label' => get_string('col_name',        'local_sentientia_roles'), 'sortable' => true,  'format' => 'html'],
    ['key' => 'shortname',    'label' => get_string('col_shortname',   'local_sentientia_roles'), 'sortable' => true],
    ['key' => 'archetype_label', 'label' => get_string('col_archetype', 'local_sentientia_roles'), 'sortable' => true, 'format' => 'html'],
    ['key' => 'capcount',     'label' => get_string('col_caps',        'local_sentientia_roles'), 'sortable' => true],
    ['key' => 'assigncount',  'label' => get_string('col_assignments', 'local_sentientia_roles'), 'sortable' => true],
    ['key' => 'sortorder',    'label' => get_string('col_sortorder',   'local_sentientia_roles'), 'sortable' => true],
    ['key' => 'actions',      'label' => get_string('col_actions',     'local_sentientia_roles'), 'sortable' => false, 'format' => 'html'],
];

$can_audit  = has_capability('local/sentientia_roles:audit',  $context);
$can_export = has_capability('local/sentientia_roles:export', $context);

$data = [
    'archetypes'      => $archetypes,
    'columns_json'    => json_encode($columns),
    'extra_args_json' => json_encode(['search' => '', 'archetype' => 'all']),
    'auditurl'        => (new moodle_url('/local/sentientia_roles/audit.php'))->out(false),
    'exporturl'       => (new moodle_url('/local/sentientia_roles/exportcsv.php',
                            ['sesskey' => sesskey()]))->out(false),
    'can_audit'       => $can_audit,
    'can_export'      => $can_export,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_roles/index', $data);
$PAGE->requires->js_call_amd('local_sentientia_roles/role_actions', 'init',
    [['page' => 'index']]);
$PAGE->requires->js_call_amd('theme_airpayux/datatable', 'init', []);
echo $OUTPUT->footer();
