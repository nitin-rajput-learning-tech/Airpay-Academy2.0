<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_proctoring/admin.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('adminhome', 'local_sentientia_proctoring'));
$PAGE->set_heading(get_string('adminhome', 'local_sentientia_proctoring'));
require_capability('local/sentientia_proctoring:viewattempts', $ctx);

$columns = [
    ['key' => 'user_name',     'label' => 'Candidate',     'sortable' => false],
    ['key' => 'quiz_name',     'label' => 'Quiz',          'sortable' => false],
    ['key' => 'started_on',    'label' => 'Started',       'sortable' => true, 'sortkey' => 'timecreated'],
    ['key' => 'status_badge',  'label' => 'Status',        'sortable' => true, 'sortkey' => 'status', 'format' => 'badge'],
    ['key' => 'risk_badge',    'label' => 'Risk',          'sortable' => true, 'sortkey' => 'risk_score', 'format' => 'badge'],
    ['key' => 'auto_decision', 'label' => 'AI verdict',    'sortable' => false],
    ['key' => 'human_decision', 'label' => 'Human verdict', 'sortable' => false],
    ['key' => 'actions',       'label' => '',              'sortable' => false, 'format' => 'html'],
];

$data = [
    'columns_json'    => s(json_encode($columns)),
    'review_queue_url' => has_capability('local/sentientia_proctoring:review', $ctx)
        ? (new moodle_url('/local/sentientia_proctoring/review.php'))->out(false) : '',
    'has_review_cap'  => has_capability('local/sentientia_proctoring:review', $ctx),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_proctoring/admin_home', $data);
echo $OUTPUT->footer();
