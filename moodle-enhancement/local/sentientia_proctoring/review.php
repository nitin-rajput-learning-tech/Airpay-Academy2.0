<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_proctoring/review.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('reviewqueue', 'local_sentientia_proctoring'));
$PAGE->set_heading(get_string('reviewqueue', 'local_sentientia_proctoring'));
require_capability('local/sentientia_proctoring:review', $ctx);

$columns = [
    ['key' => 'user_name',     'label' => 'Candidate', 'sortable' => false],
    ['key' => 'quiz_name',     'label' => 'Quiz',      'sortable' => false],
    ['key' => 'risk_badge',    'label' => 'Risk',      'sortable' => true, 'sortkey' => 'risk_score', 'format' => 'badge'],
    ['key' => 'auto_decision', 'label' => 'AI verdict', 'sortable' => false],
    ['key' => 'finished_on',   'label' => 'Finished',  'sortable' => true, 'sortkey' => 'timefinished'],
    ['key' => 'actions',       'label' => '',          'sortable' => false, 'format' => 'html'],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_proctoring/review_queue',
    ['columns_json' => s(json_encode($columns))]);
echo $OUTPUT->footer();
