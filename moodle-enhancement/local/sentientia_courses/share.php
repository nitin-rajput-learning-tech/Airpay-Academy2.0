<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Cross-tenant course sharing — admin UI.
 *
 * Sprint C (2026-05-13) deliverable. Site admins navigate here from
 * the course management page; ticking checkboxes shares the course
 * to those tenants. Submitting calls the share_course / unshare_course
 * web services.
 *
 * URL pattern:
 *   /local/sentientia_courses/share.php?id=<courseid>
 *
 * Access: requires `local/sentientia_courses:share_to_tenant` (siteadmin
 * only by default; admin can grant to other roles via Site Admin →
 * Users → Permissions → Define roles).
 *
 * @package local_sentientia_courses
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/sentientia_courses:share_to_tenant', $context);

// Phase A0 (2026-05-14): Switchboard gate. When the flag is off,
// even capability holders are blocked — graceful 403 with explanation
// rather than a half-broken page. Site admins can re-enable via
// /local/sentientia_platform/admin/switchboard.php.
if (!\local_sentientia_platform\feature_flags::is_enabled('commerce.crossTenantShare.enabled')) {
    throw new \moodle_exception('featuredisabled', 'local_sentientia_platform', '',
        'commerce.crossTenantShare.enabled');
}

global $DB, $OUTPUT, $USER;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Handle POST submission (the modal form posts back here).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $submitted = optional_param_array('tenants', [], PARAM_INT);
    // Normalise: array of int tenant ids the admin ticked.
    $wanted_tenants = array_filter(array_map('intval', $submitted), fn($i) => $i > 0);

    // Current active shares for this course.
    $current = \local_sentientia_courses\sharing_manager::list_course_shares($courseid);
    $current_active_ids = [];
    foreach ($current as $tenant_id => $row) {
        if ($row->status === \local_sentientia_courses\sharing_manager::STATUS_ACTIVE) {
            $current_active_ids[] = (int) $tenant_id;
        }
    }

    // Diff:
    //  - to_add    = wanted but not currently active → call share_course
    //  - to_remove = currently active but not wanted → call unshare_course
    $to_add    = array_diff($wanted_tenants,    $current_active_ids);
    $to_remove = array_diff($current_active_ids, $wanted_tenants);

    if (!empty($to_add)) {
        \local_sentientia_courses\sharing_manager::share_course($courseid,
            array_values($to_add));
    }
    foreach ($to_remove as $tid) {
        \local_sentientia_courses\sharing_manager::unshare_course($courseid,
            (int) $tid);
    }

    // Bust catalog caches so the shared course appears immediately for
    // its new tenant audience (and stops appearing for unshared ones).
    \cache_helper::purge_by_definition('local_sentientia_catalog', 'trending');
    \cache_helper::purge_by_definition('local_sentientia_catalog', 'new_courses');
    \cache_helper::purge_by_definition('local_sentientia_catalog', 'categories');

    redirect(
        new moodle_url('/local/sentientia_courses/share.php', ['id' => $courseid]),
        get_string('share_saved', 'local_sentientia_courses'),
        2,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Page chrome.
$PAGE->set_url(new moodle_url('/local/sentientia_courses/share.php', ['id' => $courseid]));
$PAGE->set_title('Share course: ' . format_string($course->fullname));
$PAGE->set_heading('Share course: ' . format_string($course->fullname));
$PAGE->set_pagelayout('admin');

// Build form data for the template.
$known_tenants = \local_sentientia_courses\sharing_manager::known_tenants();
$shares = \local_sentientia_courses\sharing_manager::list_course_shares($courseid);

// Identify the owning tenant from the course's open_path so we render
// "(owned by this tenant)" instead of a check-box.
$owner_path  = (string) ($course->open_path ?? '');
$parts       = explode('/', trim($owner_path, '/'));
$owner_root  = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;

$tenant_rows = [];
foreach ($known_tenants as $t) {
    $tid = (int) $t->id;
    $share_row = $shares[$tid] ?? null;
    $is_active = $share_row
        && $share_row->status === \local_sentientia_courses\sharing_manager::STATUS_ACTIVE;
    $tenant_rows[] = [
        'id'         => $tid,
        'name'       => $t->name,
        'is_owner'   => ($tid === $owner_root),
        'is_active'  => $is_active,
        'has_history' => (bool) $share_row,
        'history_label' => $share_row
            ? ($is_active
                ? 'Shared since ' . userdate($share_row->timeshared, '%d %b %Y')
                : 'Withdrawn on '  . userdate($share_row->timemodified, '%d %b %Y'))
            : '',
    ];
}

$data = [
    'courseid'    => $courseid,
    'coursename'  => format_string($course->fullname),
    'courseshort' => format_string($course->shortname),
    'tenants'     => $tenant_rows,
    'sesskey'     => sesskey(),
    'back_url'    => (new moodle_url('/local/sentientia_courses/index.php'))->out(false),
    'save_url'    => (new moodle_url('/local/sentientia_courses/share.php',
        ['id' => $courseid]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_courses/share_page', $data);
echo $OUTPUT->footer();
