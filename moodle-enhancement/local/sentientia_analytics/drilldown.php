<?php
/**
 * Analytics Drill-Down — view users in a department or learners in a course.
 *
 * Usage:
 *   /local/sentientia_analytics/drilldown.php?type=department&path=/1/15
 *   /local/sentientia_analytics/drilldown.php?type=course&courseid=42
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();

// Capability layer added 2026-09-22 -- see classes/permission.php. The old
// gate named local/courses:manage, undefined since ADR-025 renamed it, so
// this page was reachable only by site admins and only by accident.
//
// Each refusal below says which of three different things was missing (N5,
// 2026-09-24). All three used to throw moodle_exception('nopermission'), a
// key core does not have, so a missing capability, a missing org and an
// out-of-scope department all rendered as the same bare "error/nopermission".
if (!\local_sentientia_analytics\permission::can_view()) {
    throw new \required_capability_exception($context,
        \local_sentientia_analytics\permission::VIEW_CAPABILITY, 'nopermissions', '');
}

// The org subtree this viewer may see. '' means unrestricted, which only a
// holder of :viewallorgs gets; null means no tenant could be established.
$viewerorgpath = \local_sentientia_analytics\permission::visible_org_path();
if ($viewerorgpath === null) {
    throw new moodle_exception('error_noorgscope', 'local_sentientia_analytics');
}

$type = required_param('type', PARAM_ALPHA); // 'department' or 'course'

$PAGE->set_context($context);
$PAGE->set_url('/local/sentientia_analytics/drilldown.php', ['type' => $type]);
$PAGE->set_pagelayout('standard');

global $DB, $OUTPUT;

if ($type === 'department') {
    $path = required_param('path', PARAM_TEXT);
    // Security: validate path format. The trailing slash is dropped here, once.
    // clamp_org_path() hands an unrestricted viewer's path back untrimmed, so
    // comparing it with a trimmed copy refused '/1/15/' as "outside your
    // access"; and get_department_users() matches open_path exactly, so an
    // untrimmed path listed nobody.
    $path = rtrim(preg_replace('/[^0-9\/]/', '', $path), '/');

    // Clamp to the viewer's own subtree. This listing releases every matched
    // user's name, email and last-login time, so an out-of-scope path is
    // refused outright rather than silently redirected to another department
    // -- a viewer must never be shown numbers labelled as one org that came
    // from another.
    if (\local_sentientia_analytics\permission::clamp_org_path($path) !== $path) {
        throw new moodle_exception('error_outofscope', 'local_sentientia_analytics');
    }

    $deptname = '';
    $parts = explode('/', trim($path, '/'));
    $deptid = (int)end($parts);
    if ($deptid) {
        // Name the org only if it is the one AT this path. Org ids are global,
        // so '/1/<an org of another tenant>' passes the clamp above (it is under
        // '/1') and used to print that other tenant's department name in the
        // heading, although the user list was empty.
        $dept = $DB->get_record('local_sentientia_org', ['id' => $deptid], 'fullname, path');
        $deptname = ($dept && rtrim((string) $dept->path, '/') === $path)
            ? format_string($dept->fullname) : 'Department #' . $deptid;
    }

    $PAGE->set_title('Department Analytics: ' . $deptname);
    $PAGE->set_heading('Department Analytics: ' . $deptname);

    $users = \local_sentientia_analytics\analytics_manager::get_department_users($path);

    echo $OUTPUT->header();
    echo '<div style="max-width:1200px; margin:0 auto;">';
    echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">';
    echo '<h2 style="margin:0;"><i class="fa fa-building" style="color:var(--ap-primary);"></i> ' . s($deptname) . '</h2>';
    echo '<a href="' . (new moodle_url('/local/sentientia_analytics/index.php'))->out() . '" style="font-size:13px; color:var(--ap-primary);">';
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

    // Pass the viewer's org scope: get_course_learners() would otherwise list
    // every enrolled learner's name and email across all tenants.
    $learners = \local_sentientia_analytics\analytics_manager::get_course_learners(
        $courseid, 50, $viewerorgpath);

    echo $OUTPUT->header();
    echo '<div style="max-width:1200px; margin:0 auto;">';
    echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">';
    echo '<h2 style="margin:0;"><i class="fa fa-book" style="color:var(--ap-primary);"></i> ' . format_string($course->fullname) . '</h2>';
    echo '<a href="' . (new moodle_url('/local/sentientia_analytics/index.php'))->out() . '" style="font-size:13px; color:var(--ap-primary);">';
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
    // Only a hand-edited URL reaches here: the dashboard links type=department
    // and type=course only. ('invalidparam' is not a core error key and
    // rendered as the bare identifier "error/invalidparam".)
    throw new moodle_exception('invalidaccess');
}
