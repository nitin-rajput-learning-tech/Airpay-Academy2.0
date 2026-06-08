<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * "My requests" outbox — the receiving-tenant manager's view of every
 * course-share request they (or anyone in their tenant) has filed.
 *
 * Pairs with browse_airpay.php (the catalog view that LETS them file
 * requests) — this page TRACKS the status of every request after
 * filing. The pill on browse_airpay only shows the state per course
 * row; for managers who've filed many requests this consolidated
 * outbox is faster than scrolling.
 *
 * Access: requires local/sentientia_courses:request_course (granted to
 * the `manager` archetype by default).
 *
 * URL pattern: /local/sentientia_courses/my_requests.php
 *
 * @package local_sentientia_courses
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/sentientia_courses:request_course', $context);

global $DB, $OUTPUT, $USER;

// Derive viewer tenant from open_path.
$parts = explode('/', trim($USER->open_path ?? '', '/'));
$viewer_tenant = isset($parts[0]) && ctype_digit($parts[0])
    ? (int) $parts[0] : 0;
if ($viewer_tenant === 0) {
    throw new \moodle_exception('invalidtenant', 'local_sentientia_courses');
}
// Airpay-tenant users own the catalogue — no requests to track.
if ($viewer_tenant === 1) {
    throw new \moodle_exception('cannotrequestowncourse',
        'local_sentientia_courses');
}

$rows_raw = \local_sentientia_courses\request_manager::list_tenant_requests(
    $viewer_tenant);

// Build the display rows. We surface rejection reason when present
// so the manager can see why a request didn't go through.
$rows = [];
$status_meta = [
    'pending'  => ['label' => 'Pending review',   'class' => 'bg-warning text-dark'],
    'approved' => ['label' => 'Approved',         'class' => 'bg-success'],
    'rejected' => ['label' => 'Rejected',         'class' => 'bg-danger'],
];
foreach ($rows_raw as $r) {
    $meta = $status_meta[$r->status] ?? ['label' => ucfirst($r->status),
                                          'class' => 'bg-secondary'];
    $rows[] = [
        'id'           => (int) $r->id,
        'courseid'     => (int) $r->courseid,
        'coursename'   => format_string($r->coursename),
        'courseshort'  => format_string($r->courseshort),
        'status'       => $r->status,
        'status_label' => $meta['label'],
        'status_class' => $meta['class'],
        'has_reason'   => !empty($r->decision_reason),
        'reason'       => $r->decision_reason ? format_string($r->decision_reason) : '',
        'requested_at' => userdate((int) $r->timecreated, '%d %b %Y %H:%M'),
        'decided_at'   => $r->timedecided
            ? userdate((int) $r->timedecided, '%d %b %Y %H:%M')
            : '',
        'is_decided'   => (bool) $r->timedecided,
    ];
}

$known = \local_sentientia_courses\sharing_manager::known_tenants();
$viewer_tenant_name = 'Tenant ' . $viewer_tenant;
foreach ($known as $t) {
    if ((int) $t->id === $viewer_tenant) {
        $viewer_tenant_name = $t->name;
        break;
    }
}

// Per-status counts for the summary header.
$count_total    = count($rows);
$count_pending  = 0;
$count_approved = 0;
$count_rejected = 0;
foreach ($rows as $r) {
    if      ($r['status'] === 'pending')  { $count_pending++; }
    else if ($r['status'] === 'approved') { $count_approved++; }
    else if ($r['status'] === 'rejected') { $count_rejected++; }
}

$PAGE->set_url(new moodle_url('/local/sentientia_courses/my_requests.php'));
$PAGE->set_title('My course-share requests');
$PAGE->set_heading('My course-share requests');
$PAGE->set_pagelayout('admin');

$data = [
    'rows'              => $rows,
    'has_rows'          => !empty($rows),
    'viewer_tenant_name' => $viewer_tenant_name,
    'count_total'       => $count_total,
    'count_pending'     => $count_pending,
    'count_approved'    => $count_approved,
    'count_rejected'    => $count_rejected,
    'browse_url'        => (new moodle_url('/local/sentientia_courses/browse_airpay.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_courses/my_requests', $data);
echo $OUTPUT->footer();
