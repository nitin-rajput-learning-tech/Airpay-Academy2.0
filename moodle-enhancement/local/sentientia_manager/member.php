<?php
/**
 * Airpay Manager — single team member learning detail (drill-down).
 *
 * Accessible to: the user themselves, their supervisor (any level up to 5),
 * and admins. Shows full course list with progress, certificates earned,
 * activity timeline.
 *
 * @package    local_sentientia_manager
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $OUTPUT, $PAGE;

$userid = required_param('id', PARAM_INT);
$context = context_system::instance();

if (!\local_sentientia_manager\team_manager::can_view_member((int) $USER->id, $userid)) {
    throw new \moodle_exception('nopermission', 'error', '', null,
        'You can only view direct reports or skip-level reports under you.');
}

$detail = \local_sentientia_manager\team_manager::get_member_detail($userid);
$user = $detail['user'];

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_manager/member.php', ['id' => $userid]));
$PAGE->set_title('Team Member — ' . fullname($user));
$PAGE->set_heading('Team Member — ' . fullname($user));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->navbar->add('My Team', new moodle_url('/local/sentientia_manager/index.php'));
$PAGE->navbar->add(fullname($user));

// Resolve org name.
$orgname = '';
$dbman = $DB->get_manager();
if (!empty($user->open_path) && $dbman->table_exists('local_airpay_org')) {
    $org = $DB->get_record('local_airpay_org', ['path' => $user->open_path], 'fullname');
    if ($org) {
        $orgname = format_string($org->fullname);
    } else {
        // Fall back to top-level tenant.
        $parts = explode('/', trim($user->open_path, '/'));
        $top = $parts[0] ?? '';
        if (!empty($top)) {
            $tenant = $DB->get_record('local_airpay_org', ['path' => '/' . $top], 'fullname');
            if ($tenant) $orgname = format_string($tenant->fullname);
        }
    }
}

$completion_rate = $detail['enrolments_total'] > 0
    ? round(($detail['completions_total'] / $detail['enrolments_total']) * 100)
    : 0;

$last_active_label = !empty($user->lastaccess)
    ? userdate($user->lastaccess, '%d %b %Y, %H:%M')
    : 'Never';

$data = [
    'userid'        => (int) $user->id,
    'fullname'      => fullname($user),
    'email'         => s($user->email),
    'employeeid'    => s($user->open_employeeid ?? '—'),
    'designation'   => format_string($user->open_designation ?? '—'),
    'department'    => format_string($user->department ?? '—'),
    'orgname'       => $orgname ?: '—',
    'last_active'   => $last_active_label,
    'profile_url'   => (new moodle_url('/local/sentientia_users/profile.php', ['id' => $user->id]))->out(false),
    'back_url'      => (new moodle_url('/local/sentientia_manager/index.php'))->out(false),

    // Stats.
    'total_enrolled' => $detail['enrolments_total'],
    'total_completed' => $detail['completions_total'],
    'in_progress'   => $detail['in_progress'],
    'not_started'   => $detail['not_started'],
    'completion_rate' => $completion_rate,
    'rate_class'    => $completion_rate >= 80 ? 'success' : ($completion_rate >= 50 ? 'warning' : 'danger'),

    // Tables.
    'courses'           => $detail['courses'],
    'has_courses'       => !empty($detail['courses']),
    'certificates'      => $detail['certificates'],
    'has_certificates'  => !empty($detail['certificates']),
    'cert_count'        => count($detail['certificates']),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_manager/member', $data);
echo $OUTPUT->footer();
