<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
// ADR-031: the designation matrix is shared by every tenant - :manage AND
// cross-tenant (skills_manager::require_catalogue_write()).
\local_sentientia_skills\skills_manager::require_catalogue_write($context);

$designation = optional_param('designation', '', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/local/sentientia_skills/designation_matrix.php',
    $designation !== '' ? ['designation' => $designation] : []));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Designation-Skill Matrix');
$PAGE->set_heading('Designation-Skill Matrix');
$PAGE->navbar->add('Skills', new moodle_url('/local/sentientia_skills/admin.php'));
$PAGE->navbar->add('Designation Matrix');

$designations = \local_sentientia_skills\skills_manager::list_designations();
$designation_options = [];
foreach ($designations as $d) {
    // TEXT for the template: designation_matrix.mustache escapes every {{ }} itself. The value is the designation
    // exactly as stored - s() here made the select send "Risk &amp; Compliance" for "Risk & Compliance", which
    // matches no row, and the Add / Copy buttons carried the same wrong key.
    $designation_options[] = [
        'value'    => $d,
        'label'    => \local_sentientia_skills\skills_manager::plain($d),
        'selected' => $d === $designation,
    ];
}

$rows = $designation !== ''
    ? \local_sentientia_skills\skills_manager::get_designation_skills($designation)
    : [];

$data = [
    'designation_options' => $designation_options,
    'current_designation' => $designation,
    'rows'                => $rows,
    'has_rows'            => !empty($rows),
    'admin_url'           => (new moodle_url('/local/sentientia_skills/admin.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_skills/designation_matrix', $data);
$PAGE->requires->js_call_amd('local_sentientia_skills/skill_actions', 'init',
    [['page' => 'designation_matrix']]);
echo $OUTPUT->footer();
