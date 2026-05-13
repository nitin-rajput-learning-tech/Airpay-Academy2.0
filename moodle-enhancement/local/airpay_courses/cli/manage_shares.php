<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI ops tool for cross-tenant course sharing.
 *
 * Paired with the Sprint C / D web UI (share.php, browse_airpay.php,
 * manage_requests.php) — gives IT a terminal-friendly way to:
 *
 *   - LIST every active share across the platform
 *   - SHARE a course to one or more tenants (push)
 *   - UNSHARE a course from a tenant (withdraw)
 *   - APPROVE / REJECT a pending request
 *   - LIST pending requests
 *
 * The web UI is the right surface for day-to-day decisions; the CLI
 * is for: (a) bulk operations during initial tenant rollout, (b)
 * incident response when the UI is unreachable, (c) scripting from
 * an ansible / ops runbook.
 *
 * USAGE
 *   php local/airpay_courses/cli/manage_shares.php --list
 *     -- list every active share with course + tenant names.
 *
 *   php local/airpay_courses/cli/manage_shares.php --list-pending
 *     -- list pending requests in admin-inbox shape.
 *
 *   php local/airpay_courses/cli/manage_shares.php --course=42 --add=77
 *     -- share course id 42 to tenant root 77.
 *
 *   php local/airpay_courses/cli/manage_shares.php --course=42 --add=77,177
 *     -- share to multiple tenants at once.
 *
 *   php local/airpay_courses/cli/manage_shares.php --course=42 --remove=77
 *     -- withdraw the share of course 42 from tenant 77.
 *
 *   php local/airpay_courses/cli/manage_shares.php --approve=<request_id>
 *     -- approve a pending request (cascades to share + cache purge).
 *
 *   php local/airpay_courses/cli/manage_shares.php --reject=<request_id> --reason="text"
 *     -- reject a pending request with optional rationale.
 *
 *   php local/airpay_courses/cli/manage_shares.php --course=42 --history
 *     -- show full history of shares for one course (active + withdrawn).
 *
 *   php local/airpay_courses/cli/manage_shares.php --json [--list|--list-pending|--history]
 *     -- machine-readable JSON for scripting.
 *
 * EXIT CODES
 *   0  operation succeeded (or list rendered)
 *   1  operation failed (e.g. unknown tenant, missing course)
 *   2  invalid CLI arguments
 *
 * @package local_airpay_courses
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'         => false,
    'list'         => false,
    'list-pending' => false,
    'course'       => false,
    'add'          => false,
    'remove'       => false,
    'approve'      => false,
    'reject'       => false,
    'reason'       => false,
    'history'      => false,
    'json'         => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error('Unknown argument(s): ' . implode(', ', $unrecognized), 2);
}
if ($options['help']) {
    cli_writeln(file_get_contents(__FILE__));
    exit(0);
}

global $DB;

// ── Helper: pretty-print a share row ────────────────────────────────
function _fmt_share($row, array $tenant_names, $course_label): string {
    $tname = $tenant_names[(int) $row->tenant_id] ?? ('Tenant ' . $row->tenant_id);
    return sprintf('  course=%-30s → tenant=%-12s status=%s  (shared by uid=%d on %s)',
        substr($course_label, 0, 30),
        $tname,
        $row->status,
        (int) $row->shared_by,
        $row->timeshared ? date('Y-m-d', (int) $row->timeshared) : '?');
}

// Lookup helper.
function _tenant_name_map(): array {
    $rows = \local_airpay_courses\sharing_manager::known_tenants();
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r->id] = $r->name;
    }
    return $out;
}

// ── --list ──────────────────────────────────────────────────────────
if ($options['list']) {
    $shares = $DB->get_records_sql(
        "SELECT s.id, s.courseid, s.tenant_id, s.shared_by, s.status,
                s.timeshared, c.fullname AS coursename, c.shortname
           FROM {local_airpay_courses_tenant_share} s
           JOIN {course} c ON c.id = s.courseid
          WHERE s.status = :status
       ORDER BY s.tenant_id ASC, s.timeshared DESC",
        ['status' => 'active']);
    $tenant_names = _tenant_name_map();

    if ($options['json']) {
        $payload = [];
        foreach ($shares as $s) {
            $payload[] = [
                'id'           => (int) $s->id,
                'courseid'     => (int) $s->courseid,
                'course_name'  => $s->coursename,
                'course_short' => $s->shortname,
                'tenant_id'    => (int) $s->tenant_id,
                'tenant_name'  => $tenant_names[(int) $s->tenant_id]
                    ?? 'Tenant ' . $s->tenant_id,
                'shared_by'    => (int) $s->shared_by,
                'timeshared'   => (int) $s->timeshared,
            ];
        }
        echo json_encode(['count' => count($payload), 'shares' => $payload],
            JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    cli_writeln('');
    cli_writeln('  Active cross-tenant course shares: ' . count($shares));
    cli_writeln('  ' . str_repeat('-', 90));
    foreach ($shares as $s) {
        cli_writeln(_fmt_share($s, $tenant_names,
            $s->coursename . ' (' . $s->shortname . ')'));
    }
    cli_writeln('');
    exit(0);
}

// ── --list-pending ───────────────────────────────────────────────────
if ($options['list-pending']) {
    $pending = \local_airpay_courses\request_manager::list_pending_requests();
    $tenant_names = _tenant_name_map();

    if ($options['json']) {
        $payload = [];
        foreach ($pending as $r) {
            $payload[] = [
                'id'              => (int) $r->id,
                'courseid'        => (int) $r->courseid,
                'course_name'     => $r->coursename,
                'requesting_tenant' => (int) $r->requesting_tenant,
                'tenant_name'     => $tenant_names[(int) $r->requesting_tenant]
                    ?? 'Tenant ' . $r->requesting_tenant,
                'requester_userid' => (int) $r->requester_userid,
                'requester_email' => $r->email,
                'requested_at'    => (int) $r->timecreated,
            ];
        }
        echo json_encode(['count' => count($payload), 'pending' => $payload],
            JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    cli_writeln('');
    cli_writeln('  Pending course-share requests: ' . count($pending));
    cli_writeln('  ' . str_repeat('-', 90));
    foreach ($pending as $r) {
        $tname = $tenant_names[(int) $r->requesting_tenant]
            ?? ('Tenant ' . $r->requesting_tenant);
        cli_writeln(sprintf('  [request_id=%-5d] course=%-30s from %s by %s (%s) %s',
            (int) $r->id,
            substr($r->coursename, 0, 30),
            $tname,
            $r->firstname . ' ' . $r->lastname,
            $r->email,
            date('Y-m-d H:i', (int) $r->timecreated)));
        cli_writeln(sprintf('  %sapprove: php %s --approve=%d',
            str_repeat(' ', 22), basename(__FILE__), (int) $r->id));
        cli_writeln(sprintf('  %sreject : php %s --reject=%d --reason="..."',
            str_repeat(' ', 22), basename(__FILE__), (int) $r->id));
        cli_writeln('');
    }
    exit(0);
}

// ── --course=N --history ─────────────────────────────────────────────
if ($options['course'] !== false && $options['history']) {
    $courseid = (int) $options['course'];
    $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname');
    if (!$course) {
        cli_error("No course with id $courseid", 1);
    }
    $shares = \local_airpay_courses\sharing_manager::list_course_shares($courseid);
    $tenant_names = _tenant_name_map();

    if ($options['json']) {
        $payload = [];
        foreach ($shares as $tid => $row) {
            $payload[] = [
                'tenant_id'    => (int) $tid,
                'tenant_name'  => $tenant_names[$tid] ?? "Tenant $tid",
                'status'       => $row->status,
                'shared_by'    => (int) $row->shared_by,
                'timeshared'   => (int) $row->timeshared,
                'timemodified' => (int) $row->timemodified,
            ];
        }
        echo json_encode([
            'courseid'     => $courseid,
            'course_name'  => $course->fullname,
            'shares'       => $payload,
        ], JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    cli_writeln('');
    cli_writeln("  Share history for course $courseid ({$course->fullname}):");
    cli_writeln('  ' . str_repeat('-', 80));
    if (empty($shares)) {
        cli_writeln('  (no share history)');
    }
    foreach ($shares as $tid => $row) {
        $tname = $tenant_names[$tid] ?? "Tenant $tid";
        cli_writeln(sprintf('  tenant=%-12s status=%-10s shared %s modified %s by uid=%d',
            $tname,
            $row->status,
            $row->timeshared ? date('Y-m-d', (int) $row->timeshared) : '?',
            $row->timemodified ? date('Y-m-d', (int) $row->timemodified) : '?',
            (int) $row->shared_by));
    }
    cli_writeln('');
    exit(0);
}

// ── --course=N --add=77 (or comma-separated tenants) ────────────────
if ($options['course'] !== false && $options['add'] !== false) {
    $courseid = (int) $options['course'];
    $tenants = array_filter(array_map('intval',
        explode(',', (string) $options['add'])), fn($x) => $x > 0);
    if (empty($tenants)) {
        cli_error('--add value invalid; expected comma-separated tenant ids', 2);
    }
    // CLI runs as the user with mtrace context (uid=2 by Moodle convention
    // unless --as=N — we keep it simple here and use the configured admin).
    $admin = get_admin();
    $out = \local_airpay_courses\sharing_manager::share_course(
        $courseid, $tenants, (int) $admin->id);

    cli_writeln('');
    cli_writeln("  course=$courseid:");
    if (!empty($out['shared'])) {
        cli_writeln('    + newly shared to: ' . implode(',', $out['shared']));
    }
    if (!empty($out['reactivated'])) {
        cli_writeln('    + reactivated for: ' . implode(',', $out['reactivated']));
    }
    if (!empty($out['unchanged'])) {
        cli_writeln('    = already shared to: ' . implode(',', $out['unchanged']));
    }
    foreach ($out['errors'] as $tid => $err) {
        cli_writeln("    ! error on tenant $tid: $err");
    }
    cli_writeln('');
    exit(empty($out['errors']) ? 0 : 1);
}

// ── --course=N --remove=77 ──────────────────────────────────────────
if ($options['course'] !== false && $options['remove'] !== false) {
    $courseid = (int) $options['course'];
    $tenantid = (int) $options['remove'];
    if ($tenantid <= 0) {
        cli_error('--remove value invalid; expected tenant id', 2);
    }
    $admin = get_admin();
    $changed = \local_airpay_courses\sharing_manager::unshare_course(
        $courseid, $tenantid, (int) $admin->id);
    cli_writeln($changed
        ? "  course=$courseid unshared from tenant=$tenantid"
        : "  course=$courseid no-op (not active for tenant=$tenantid)");
    exit(0);
}

// ── --approve=<request_id> ──────────────────────────────────────────
if ($options['approve'] !== false) {
    $rid = (int) $options['approve'];
    if ($rid <= 0) {
        cli_error('--approve value invalid; expected request id', 2);
    }
    $admin = get_admin();
    $changed = \local_airpay_courses\request_manager::approve_request(
        $rid, (int) $admin->id);
    cli_writeln($changed
        ? "  request_id=$rid approved (share row inserted + catalog caches purged)"
        : "  request_id=$rid no-op (already approved or non-existent)");
    exit(0);
}

// ── --reject=<request_id> --reason=text ─────────────────────────────
if ($options['reject'] !== false) {
    $rid = (int) $options['reject'];
    $reason = (string) ($options['reason'] ?? '');
    if ($rid <= 0) {
        cli_error('--reject value invalid; expected request id', 2);
    }
    $admin = get_admin();
    $changed = \local_airpay_courses\request_manager::reject_request(
        $rid, $reason, (int) $admin->id);
    cli_writeln($changed
        ? "  request_id=$rid rejected"
            . ($reason !== '' ? " (reason: $reason)" : '')
        : "  request_id=$rid no-op (already rejected or non-existent)");
    exit(0);
}

// No recognised flag combo — print help.
cli_writeln('');
cli_writeln('  manage_shares.php — Airpay cross-tenant share/request CLI ops tool');
cli_writeln('  ' . str_repeat('-', 60));
cli_writeln('  Use --help for full usage. Quick examples:');
cli_writeln('    --list                              show every active share');
cli_writeln('    --list-pending                      show pending requests');
cli_writeln('    --course=N --history                show full share history for course N');
cli_writeln('    --course=N --add=77                 share course N to tenant 77');
cli_writeln('    --course=N --add=77,177             share to multiple tenants');
cli_writeln('    --course=N --remove=77              withdraw share from tenant 77');
cli_writeln('    --approve=<request_id>              approve a pending request');
cli_writeln('    --reject=<request_id> --reason="…"  reject a pending request');
cli_writeln('');
exit(2);
