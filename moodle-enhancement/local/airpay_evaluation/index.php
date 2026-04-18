<?php
// Airpay Training Evaluations — feedback form management.
//
// @package    local_airpay_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_evaluation/index.php'));
$PAGE->set_title('Training Evaluations');
$PAGE->set_heading('Training Evaluations');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// Check for evaluation data — try legacy BizLMS table.
$dbman = $DB->get_manager();
$evals = [];
$total = 0;

if ($dbman->table_exists('local_evaluation') && $DB->count_records('local_evaluation') > 0) {
    $total = $DB->count_records('local_evaluation');
    $records = $DB->get_records('local_evaluation', null, 'id DESC', '*', 0, 25);
    foreach ($records as $r) {
        $evals[] = [
            'id'   => $r->id,
            'name' => format_string($r->name ?? $r->fullname ?? 'Evaluation #' . $r->id),
        ];
    }
}

// Also check Moodle's native feedback activities.
$feedback_count = $DB->count_records('feedback');

$data = [
    'total'          => $total,
    'feedback_count' => $feedback_count,
    'evals'          => $evals,
    'has_evals'      => !empty($evals),
    'has_feedback'   => ($feedback_count > 0),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_evaluation/manage', $data);
echo $OUTPUT->footer();
