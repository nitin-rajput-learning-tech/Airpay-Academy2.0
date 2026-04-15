<?php
/**
 * Airpay Manager Dashboard — team learning visibility for supervisors.
 *
 * Shows: team completion grid, overdue alerts, compliance status, skill gaps.
 * Accessible to: managers (users with direct reports via open_supervisorid),
 * L&D admins, and siteadmins.
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

// Permission: manager (has direct reports) OR admin.
$isadmin = is_siteadmin() || has_capability('local/courses:manage', $context);
$managerid = $USER->id;

// Admin can view any manager's team.
$viewuserid = optional_param('manager', $USER->id, PARAM_INT);
if ($viewuserid !== $USER->id && !$isadmin) {
    $viewuserid = $USER->id;
}

// Get direct reports.
$team = $DB->get_records_sql(
    "SELECT u.id, u.firstname, u.lastname, u.email, u.lastlogin, u.open_path,
            u.open_designation, u.open_department
       FROM {user} u
      WHERE u.open_supervisorid = :mgr AND u.deleted = 0 AND u.suspended = 0
   ORDER BY u.lastname, u.firstname",
    ['mgr' => $viewuserid]
);

if (empty($team) && !$isadmin) {
    throw new moodle_exception('nopermission', 'error', '',
        null, 'You have no direct reports. This dashboard is for managers with team members.');
}

// Build team learning data.
$team_data = [];
$total_enrolled = 0;
$total_completed = 0;
$total_overdue = 0;
$at_risk = 0;

foreach ($team as $member) {
    // Enrollment count.
    $enrolled = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT e.courseid)
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
          WHERE ue.userid = :uid",
        ['uid' => $member->id]);

    // Completion count.
    $completed = $DB->count_records_sql(
        "SELECT COUNT(*)
           FROM {course_completions}
          WHERE userid = :uid AND timecompleted IS NOT NULL",
        ['uid' => $member->id]);

    // Completion rate.
    $rate = $enrolled > 0 ? round(($completed / $enrolled) * 100) : 0;

    // Overdue compliance courses.
    $overdue = 0;
    if ($DB->get_manager()->table_exists('local_airpay_compliance_snapshot')) {
        $overdue = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_compliance_snapshot}
             WHERE userid = :uid AND status = 'overdue'",
            ['uid' => $member->id]);
    }

    // Last activity.
    $lastlogin = $member->lastlogin ? userdate($member->lastlogin, '%d %b %Y') : 'Never';
    $inactive_days = $member->lastlogin ? round((time() - $member->lastlogin) / 86400) : 999;
    $is_inactive = $inactive_days > 14;

    // Gamification data (if available).
    $streak = 0;
    $points = 0;
    if ($DB->get_manager()->table_exists('local_airpay_streaks')) {
        $streakdata = $DB->get_record('local_airpay_streaks', ['userid' => $member->id], 'current_streak, total_points');
        if ($streakdata) {
            $streak = $streakdata->current_streak;
            $points = $streakdata->total_points;
        }
    }

    $total_enrolled += $enrolled;
    $total_completed += $completed;
    $total_overdue += $overdue;
    if ($rate < 50 && $enrolled > 0) { $at_risk++; }

    $team_data[] = [
        'id'          => $member->id,
        'firstname'   => format_string($member->firstname),
        'lastname'    => format_string($member->lastname),
        'fullname'    => format_string($member->firstname . ' ' . $member->lastname),
        'email'       => s($member->email),
        'designation' => format_string($member->open_designation ?? ''),
        'enrolled'    => $enrolled,
        'completed'   => $completed,
        'rate'        => $rate,
        'rate_class'  => $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger'),
        'overdue'     => $overdue,
        'has_overdue' => ($overdue > 0),
        'lastlogin'   => $lastlogin,
        'is_inactive' => $is_inactive,
        'inactive_days' => $inactive_days,
        'streak'      => $streak,
        'points'      => number_format($points),
        'profile_url' => (new moodle_url('/local/users/profile.php', ['id' => $member->id]))->out(false),
        'nudge_url'   => (new moodle_url('/local/airpay_notifications/nudge.php', ['userid' => $member->id, 'type' => 'general']))->out(false),
        'skills_url'  => (new moodle_url('/local/airpay_skills/index.php', ['userid' => $member->id]))->out(false),
    ];
}

$team_size = count($team_data);
$avg_rate = $team_size > 0 ? round($total_completed / max($total_enrolled, 1) * 100) : 0;

$data = [
    'team'           => $team_data,
    'has_team'       => !empty($team_data),
    'team_size'      => $team_size,
    'total_enrolled' => $total_enrolled,
    'total_completed' => $total_completed,
    'total_overdue'  => $total_overdue,
    'at_risk'        => $at_risk,
    'avg_rate'       => $avg_rate,
    'manager_name'   => format_string($USER->firstname),
    'sesskey'        => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_manager/dashboard', $data);
echo $OUTPUT->footer();
