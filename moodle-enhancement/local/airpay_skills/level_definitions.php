<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/airpay_skills:manage', $context);

$skillid = required_param('skillid', PARAM_INT);

$skill = $DB->get_record_sql("
    SELECT s.id, s.name, s.max_level, s.categoryid,
           c.name AS category_name, c.color AS category_color
      FROM {local_airpay_skills} s
 LEFT JOIN {local_airpay_skill_cats} c ON c.id = s.categoryid
     WHERE s.id = :id", ['id' => $skillid], MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/airpay_skills/level_definitions.php',
    ['skillid' => $skillid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Skill levels: ' . format_string($skill->name));
$PAGE->set_heading('Skill levels: ' . format_string($skill->name));
$PAGE->navbar->add('Skills', new moodle_url('/local/airpay_skills/admin.php'));
$PAGE->navbar->add(format_string($skill->name));

$levels = \local_airpay_skills\skills_manager::get_skill_levels($skillid);

$data = [
    'skill' => [
        'id'             => (int) $skill->id,
        'name'           => format_string($skill->name),
        'max_level'      => (int) $skill->max_level,
        'category_name'  => format_string((string) ($skill->category_name ?? 'Uncategorised')),
        'category_color' => s((string) ($skill->category_color ?? '#0066A7')),
    ],
    'levels'    => $levels,
    'admin_url' => (new moodle_url('/local/airpay_skills/admin.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_skills/level_definitions', $data);
$PAGE->requires->js_call_amd('local_airpay_skills/skill_actions', 'init',
    [['page' => 'level_definitions']]);
echo $OUTPUT->footer();
