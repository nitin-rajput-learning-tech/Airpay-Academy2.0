<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sprint D — receiving-tenant manager's view of Airpay's library.
 *
 * Lets a Public or ZEEA manager browse every Airpay-owned course
 * (open_path prefixed by `/1`) and request specific ones be added to
 * their tenant's catalog. Each row shows the current state:
 *   - not requested → "Request access" button
 *   - pending → "Request pending" badge
 *   - approved / already_shared → "In your catalog" badge
 *   - rejected → "Rejected" badge with optional reason
 *
 * Access: requires local/sentientia_courses:request_course (granted to
 * the `manager` archetype by default).
 *
 * @package local_sentientia_courses
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/sentientia_courses:request_course', $context);

// Phase A0 (2026-05-14): Switchboard gate.
if (!\local_sentientia_platform\feature_flags::is_enabled('commerce.crossTenantRequest.enabled')) {
    throw new \moodle_exception('featuredisabled', 'local_sentientia_platform', '',
        'commerce.crossTenantRequest.enabled');
}

global $DB, $OUTPUT, $USER;

// Derive viewer's tenant root from open_path.
$parts = explode('/', trim($USER->open_path ?? '', '/'));
$viewer_tenant = isset($parts[0]) && ctype_digit($parts[0])
    ? (int) $parts[0] : 0;
if ($viewer_tenant === 0) {
    throw new \moodle_exception('invalidtenant', 'local_sentientia_courses');
}

// Airpay-owned courses = open_path under /1.
// Excludes the viewer's own tenant (a /1 user shouldn't see this page
// at all — they own everything by default — and they can't request
// their own courses).
if ($viewer_tenant === 1) {
    throw new \moodle_exception('cannotrequestowncourse',
        'local_sentientia_courses');
}

// POST handler — manager clicked "Request access" for some courseid.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $courseid = required_param('courseid', PARAM_INT);
    \local_sentientia_courses\request_manager::create_request($courseid);
    redirect(
        new moodle_url('/local/sentientia_courses/browse_airpay.php'),
        get_string('request_filed', 'local_sentientia_courses'),
        2,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// List of Airpay-owned courses with the viewer's request state per row.
// We don't paginate at this scale — Airpay's library is in the
// hundreds, not thousands.
$courses = $DB->get_records_sql(
    "SELECT c.id, c.fullname, c.shortname, c.summary, c.timecreated,
            c.open_path, cc.name AS categoryname
       FROM {course} c
       JOIN {course_categories} cc ON cc.id = c.category
      WHERE c.visible = 1 AND c.id > 1
        AND (c.open_path = :airpayexact OR c.open_path LIKE :airpayprefix)
   ORDER BY c.timecreated DESC",
    ['airpayexact' => '/1', 'airpayprefix' => '/1/%']
);

$rows = [];
foreach ($courses as $c) {
    $state = \local_sentientia_courses\request_manager::request_state(
        (int) $c->id, $viewer_tenant);
    $rows[] = [
        'id'           => (int) $c->id,
        'fullname'     => format_string($c->fullname),
        'shortname'    => format_string($c->shortname),
        'summary'      => shorten_text(strip_tags(format_text($c->summary)), 160),
        'categoryname' => format_string($c->categoryname ?? ''),
        'state'        => $state,
        'state_label'  => self_browse_state_label($state),
        'state_class'  => self_browse_state_class($state),
        'can_request'  => ($state === 'none' || $state === 'rejected'),
    ];
}

/**
 * Map the state string to a human-friendly label for the badge.
 */
function self_browse_state_label(string $state): string {
    switch ($state) {
        case 'none':            return get_string('browse_state_none', 'local_sentientia_courses');
        case 'pending':         return get_string('browse_state_pending', 'local_sentientia_courses');
        case 'approved':
        case 'already_shared':  return get_string('browse_state_in_catalog', 'local_sentientia_courses');
        case 'rejected':        return get_string('browse_state_rejected', 'local_sentientia_courses');
        default:                return ucfirst($state);
    }
}

/**
 * Map state to a Bootstrap badge class.
 */
function self_browse_state_class(string $state): string {
    switch ($state) {
        case 'none':            return 'bg-light text-dark';
        case 'pending':         return 'bg-warning text-dark';
        case 'approved':
        case 'already_shared':  return 'bg-success';
        case 'rejected':        return 'bg-danger';
        default:                return 'bg-secondary';
    }
}

$known_tenants = \local_sentientia_courses\sharing_manager::known_tenants();
$viewer_tenant_name = get_string('browse_tenant_fallback', 'local_sentientia_courses', $viewer_tenant);
foreach ($known_tenants as $t) {
    if ((int) $t->id === $viewer_tenant) {
        $viewer_tenant_name = format_string($t->name);
        break;
    }
}

// Page copy: customer name from the theme (white-label), tenant name format_string()'d above, so every
// get_string() result below is HTML-safe once and the template renders it with triple braces (F-12).
$customername = get_string('customername', 'theme_sentientia');
$copyparams = (object) ['customer' => $customername, 'tenant' => $viewer_tenant_name];
$pagetitle = get_string('browse_title', 'local_sentientia_courses', $customername);

$PAGE->set_url(new moodle_url('/local/sentientia_courses/browse_airpay.php'));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('admin');

$data = [
    'courses'             => $rows,
    'has_courses'         => !empty($rows),
    'title'               => $pagetitle,
    'intro'               => get_string('browse_intro', 'local_sentientia_courses', $copyparams),
    'empty_text'          => get_string('browse_empty', 'local_sentientia_courses', $customername),
    'isolation_note'      => get_string('browse_isolation_note', 'local_sentientia_courses', $copyparams),
    'sesskey'             => sesskey(),
    'post_url'            => (new moodle_url('/local/sentientia_courses/browse_airpay.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_courses/browse_airpay', $data);
echo $OUTPUT->footer();
