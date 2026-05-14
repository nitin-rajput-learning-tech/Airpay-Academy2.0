<?php
/**
 * Airpay Manager Dashboard — team learning visibility for supervisors.
 *
 * @package    local_airpay_manager
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $CFG, $OUTPUT, $PAGE;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/airpay_manager/index.php');
$PAGE->set_title('My Team — Learning Dashboard');
$PAGE->set_heading('My Team — Learning Dashboard');
$PAGE->set_pagelayout('standard');

$isadmin = is_siteadmin() || has_capability('local/courses:manage', $context);

// Admin can pick which manager's team to view.
$viewuserid = optional_param('manager', $USER->id, PARAM_INT);
if ($viewuserid !== $USER->id && !$isadmin) {
    $viewuserid = $USER->id;
}

// Fetch team + summary in two batched queries (no N+1).
$team = \local_airpay_manager\team_manager::get_team($viewuserid);
$summary = \local_airpay_manager\team_manager::summarize_team($team);

if (empty($summary) && !$isadmin) {
    throw new moodle_exception('nopermission', 'error', '',
        null, 'You have no direct reports. This dashboard is for managers with team members.');
}

$team_data = [];
$total_enrolled = 0;
$total_completed = 0;
$total_overdue = 0;
$at_risk = 0;

foreach ($summary as $row) {
    $total_enrolled  += $row['enrolled'];
    $total_completed += $row['completed'];
    $total_overdue   += $row['overdue'];
    if ($row['rate'] < 50 && $row['enrolled'] > 0) { $at_risk++; }

    $row['profile_url']  = (new moodle_url('/local/airpay_users/profile.php',     ['id' => $row['id']]))->out(false);
    $row['drilldown_url'] = (new moodle_url('/local/airpay_manager/member.php',    ['id' => $row['id']]))->out(false);
    $row['nudge_url']    = (new moodle_url('/local/airpay_notifications/nudge.php',['userid' => $row['id'], 'type' => 'general']))->out(false);
    $row['skills_url']   = (new moodle_url('/local/airpay_skills/index.php',       ['userid' => $row['id']]))->out(false);
    $team_data[] = $row;
}

$team_size = count($team_data);
$avg_rate = $team_size > 0 ? round($total_completed / max($total_enrolled, 1) * 100) : 0;

// Phase B0+ — stat_card-compatible KPI array. The legacy
// {team_size, avg_rate, ...} flat fields are kept on the context so
// other templates that read them keep working.
$kpi_tiles = [
    [
        'label' => 'Team Members',
        'value' => number_format($team_size),
        'icon'  => 'users',
        'color' => 'primary',
    ],
    [
        'label' => 'Avg Completion',
        'value' => $avg_rate . '%',
        'icon'  => 'check-circle',
        // Healthy when >= 80, warning when 50-79, danger below 50.
        'color' => $avg_rate >= 80 ? 'success' : ($avg_rate >= 50 ? 'warning' : 'danger'),
    ],
    [
        'label' => 'Overdue Items',
        'value' => number_format($total_overdue),
        'icon'  => 'exclamation-triangle',
        // Danger when there ARE overdue; muted primary when zero.
        'color' => $total_overdue > 0 ? 'danger' : 'primary',
    ],
    [
        'label' => 'At Risk (<50%)',
        'value' => number_format($at_risk),
        'icon'  => 'warning',
        'color' => $at_risk > 0 ? 'warning' : 'primary',
    ],
];

$data = [
    'team'           => $team_data,
    'has_team'       => !empty($team_data),
    'team_size'      => $team_size,
    'total_enrolled' => $total_enrolled,
    'total_completed' => $total_completed,
    'total_overdue'  => $total_overdue,
    'at_risk'        => $at_risk,
    'avg_rate'       => $avg_rate,
    'kpi_tiles'      => $kpi_tiles,
    'has_kpi_tiles'  => !empty($kpi_tiles),
    'manager_name'   => format_string($USER->firstname),
    'sesskey'        => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_manager/dashboard', $data);
echo $OUTPUT->footer();
