<?php
// Airpay Training Evaluations — admin management.
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

$can_manage = is_siteadmin() || has_capability('local/airpay_evaluation:manage', $context);

$dbman = $DB->get_manager();
$rows = [];
$total = 0;
$active = 0;
$draft = 0;
$total_responses = 0;

if ($dbman->table_exists('local_airpay_evaluation')) {
    $total  = \local_airpay_evaluation\evaluation_manager::count_evaluations();
    $active = \local_airpay_evaluation\evaluation_manager::count_evaluations(
        \local_airpay_evaluation\evaluation_manager::STATUS_ACTIVE);
    $draft  = \local_airpay_evaluation\evaluation_manager::count_evaluations(
        \local_airpay_evaluation\evaluation_manager::STATUS_DRAFT);
    $total_responses = \local_airpay_evaluation\evaluation_manager::count_responses();

    // Load evaluations with question and response counts.
    $records = $DB->get_records_sql(
        "SELECT e.*,
                (SELECT COUNT(*) FROM {local_airpay_evaluation_questions} q WHERE q.evaluationid = e.id) AS qcount,
                (SELECT COUNT(*) FROM {local_airpay_evaluation_responses} r WHERE r.evaluationid = e.id) AS rcount
           FROM {local_airpay_evaluation} e
       ORDER BY e.timemodified DESC, e.id DESC", [], 0, 100);

    $kp_labels = \local_airpay_evaluation\evaluation_manager::KIRKPATRICK_LEVELS;
    $trigger_labels = \local_airpay_evaluation\evaluation_manager::TRIGGER_EVENTS;

    foreach ($records as $e) {
        $status = (int) $e->status;
        $statuslabel = match ($status) {
            0 => 'Draft',
            1 => 'Active',
            2 => 'Archived',
            default => 'Unknown',
        };
        $statuscss = match ($status) {
            0 => 'badge-secondary',
            1 => 'badge-success',
            2 => 'badge-warning',
            default => 'badge-secondary',
        };

        // Short Kirkpatrick label.
        $kp_short = match ((int) $e->kirkpatrick_level) {
            1 => 'L1 Reaction',
            2 => 'L2 Learning',
            3 => 'L3 Behaviour',
            4 => 'L4 Results',
            default => '—',
        };

        // Trigger label.
        $trigger_short = match ($e->trigger_event) {
            'manual'             => 'Manual',
            'course_completion'  => 'Course done',
            'program_completion' => 'Program done',
            'classroom_end'      => 'Classroom end',
            default              => $e->trigger_event,
        };
        if ($e->trigger_event !== 'manual' && $e->days_after > 0) {
            $trigger_short .= ' + ' . $e->days_after . 'd';
        }

        $rows[] = [
            'id'           => $e->id,
            'name'         => format_string($e->name),
            'description'  => format_string($e->description ?? ''),
            'kirkpatrick'  => $kp_short,
            'trigger'      => $trigger_short,
            'qcount'       => (int) $e->qcount,
            'rcount'       => (int) $e->rcount,
            'anonymous'    => (bool) $e->anonymous,
            'status'       => $status,
            'is_draft'     => ($status === 0),
            'is_active'    => ($status === 1),
            'is_archived'  => ($status === 2),
            'statuslabel'  => $statuslabel,
            'statuscss'    => $statuscss,
        ];
    }
}

$data = [
    'total'           => $total,
    'active'          => $active,
    'draft'           => $draft,
    'total_responses' => $total_responses,
    'evaluations'     => $rows,
    'has_evaluations' => !empty($rows),
    'can_manage'      => $can_manage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_evaluation/manage', $data);
echo $OUTPUT->footer();
