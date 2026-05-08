<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/airpay_skills:manage', $context);

$designation = optional_param('designation', '', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/local/airpay_skills/designation_matrix.php',
    $designation !== '' ? ['designation' => $designation] : []));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Designation-Skill Matrix');
$PAGE->set_heading('Designation-Skill Matrix');
$PAGE->navbar->add('Skills', new moodle_url('/local/airpay_skills/admin.php'));
$PAGE->navbar->add('Designation Matrix');

$designations = \local_airpay_skills\skills_manager::list_designations();
$designation_options = [];
foreach ($designations as $d) {
    $designation_options[] = [
        'value'    => s($d),
        'label'    => format_string($d),
        'selected' => $d === $designation,
    ];
}

$rows = $designation !== ''
    ? \local_airpay_skills\skills_manager::get_designation_skills($designation)
    : [];

$data = [
    'designation_options' => $designation_options,
    'current_designation' => $designation !== '' ? s($designation) : '',
    'rows'                => $rows,
    'has_rows'            => !empty($rows),
    'admin_url'           => (new moodle_url('/local/airpay_skills/admin.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_skills/designation_matrix', $data);
$PAGE->requires->js_call_amd('local_airpay_skills/skill_actions', 'init',
    [['page' => 'designation_matrix']]);
echo $OUTPUT->footer();
