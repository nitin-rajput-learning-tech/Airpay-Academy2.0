<?php
/**
 * Analytics Drill-Down — view users in a department or learners in a course.
 *
 * Usage:
 *   /local/airpay_analytics/drilldown.php?type=department&path=/1/15
 *   /local/airpay_analytics/drilldown.php?type=course&courseid=42
 *
 * @package    local_airpay_analytics
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
if (!is_siteadmin() && !has_capability('local/courses:manage', $context)) {
    throw new moodle_exception('nopermission');
}

$type = required_param('type', PARAM_ALPHA); // 'department' or 'course'

$PAGE->set_context($context);
$PAGE->set_url('/local/airpay_analytics/drilldown.php', ['type' => $type]);
$PAGE->set_pagelayout('standard');

global $DB, $OUTPUT;

if ($type === 'department') {
    $path = required_param('path', PARAM_TEXT);
    // Security: validate path format.
    $path = preg_replace('/[^0-9\/]/', '', $path);

    $deptname = '';
    $parts = explode('/', trim($path, '/'));
    $deptid = (int)end($parts);
    if ($deptid) {
        $dept = $DB->get_record('local_airpay_org', ['id' => $deptid], 'fullname');
        $deptname = $dept ? format_string($dept->fullname) : 'Department #' . $deptid;
    }

    $PAGE->set_title('Department Analytics: ' . $deptname);
    $PAGE->set_heading('Department Analytics: ' . $deptname);

    $users = \local_airpay_analytics\analytics_manager::get_department_users($path);

    echo $OUTPUT->header();
    echo '<div style="max-width:1200px; margin:0 auto;">';
    echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">';
    echo '<h2 style="margin:0;"><i class="fa fa-building" style="color:var(--ap-primary);"></i> ' . s($deptname) . '</h2>';
    echo '<a href="' . (new moodle_url('/local/airpay_analytics/index.php'))->out() . '" style="font-size:13px; color:var(--ap-primary);">';
    echo '<i class="fa fa-arrow-left"></i> Back to Analytics</a>';
    echo '</div>';

    if (empty($users)) {
        echo '<div class="ap-empty-state"><i class="fa fa-users ap-empty-state__icon"></i>';
        echo '<h4 class="ap-empty-state__title">No users found in this department</h4></div>';
    } else {
        echo '<div style="background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3); border-radius:12px; overflow:hidden;">';
        echo '<table class="airpay-compliance-rpt__table" style="width:100%;">';
        echo '<thead><tr><th>Name</th><th>Email</th><th>Enrolled</th><th>Completed</th><th>Rate</th><th>Last Login</th></tr></thead>';
        echo '<tbody>';
        foreach ($users as $u) {
            $rate = (int)($u->completion_rate ?? 0);
            $rateclass = $rate >= 80 ? 'color:#16a34a;' : ($rate >= 50 ? 'color:#d97706;' : 'color:#dc2626;');
            $lastlogin = $u->lastlogin ? userdate($u->lastlogin, '%d %b %Y') : 'Never';
            echo '<tr>';
            echo '<td><a href="' . (new moodle_url('/local/users/profile.php', ['id' => $u->id]))->out() . '">' . format_string($u->firstname . ' ' . $u->lastname) . '</a></td>';
            echo '<td>' . s($u->email) . '</td>';
            echo '<td>' . (int)$u->enrolled_courses . '</td>';
            echo '<td>' . (int)$u->completed_courses . '</td>';
            echo '<td style="font-weight:700; ' . $rateclass . '">' . $rate . '%</td>';
            echo '<td>' . $lastlogin . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<p style="font-size:12px; color:var(--ap-text-muted); margin-top:8px;">' . count($users) . ' users in this department</p>';
    }
    echo '</div>';
    echo $OUTPUT->footer();

} elseif ($type === 'course') {
    $courseid = required_param('courseid', PARAM_INT);
    $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);

    $PAGE->set_title('Course Analytics: ' . format_string($course->fullname));
    $PAGE->set_heading('Course Analytics: ' . format_string($course->fullname));

    $learners = \local_airpay_analytics\analytics_manager::get_course_learners($courseid);

    echo $OUTPUT->header();
    echo '<div style="max-width:1200px; margin:0 auto;">';
    echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">';
    echo '<h2 style="margin:0;"><i class="fa fa-book" style="color:var(--ap-primary);"></i> ' . format_string($course->fullname) . '</h2>';
    echo '<a href="' . (new moodle_url('/local/airpay_analytics/index.php'))->out() . '" style="font-size:13px; color:var(--ap-primary);">';
    echo '<i class="fa fa-arrow-left"></i> Back to Analytics</a>';
    echo '</div>';

    $completed = array_filter($learners, fn($l) => $l->status === 'completed');
    $enrolled_only = array_filter($learners, fn($l) => $l->status === 'enrolled');
    echo '<div style="display:flex; gap:12px; margin-bottom:16px;">';
    echo '<div class="ap-badge ap-badge--info">' . count($learners) . ' enrolled</div>';
    echo '<div class="ap-badge ap-badge--success">' . count($completed) . ' completed</div>';
    echo '<div class="ap-badge ap-badge--warning">' . count($enrolled_only) . ' in progress</div>';
    echo '</div>';

    if (empty($learners)) {
        echo '<div class="ap-empty-state"><i class="fa fa-users ap-empty-state__icon"></i>';
        echo '<h4 class="ap-empty-state__title">No learners enrolled in this course</h4></div>';
    } else {
        echo '<div style="background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3); border-radius:12px; overflow:hidden;">';
        echo '<table class="airpay-compliance-rpt__table" style="width:100%;">';
        echo '<thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Enrolled</th><th>Completed</th></tr></thead>';
        echo '<tbody>';
        foreach ($learners as $l) {
            $statusbadge = match($l->status) {
                'completed' => '<span class="badge badge-success">Completed</span>',
                'enrolled' => '<span class="badge badge-warning">In Progress</span>',
                default => '<span class="badge badge-secondary">Not Enrolled</span>',
            };
            $enrolled_date = $l->enrolled_date ? userdate($l->enrolled_date, '%d %b %Y') : '-';
            $completed_date = $l->completed_date ? userdate($l->completed_date, '%d %b %Y') : '-';
            echo '<tr>';
            echo '<td>' . format_string($l->firstname . ' ' . $l->lastname) . '</td>';
            echo '<td>' . s($l->email) . '</td>';
            echo '<td>' . $statusbadge . '</td>';
            echo '<td>' . $enrolled_date . '</td>';
            echo '<td>' . $completed_date . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
    echo $OUTPUT->footer();

} else {
    throw new moodle_exception('invalidparam', 'error');
}
