<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI report: certificate emails sent.
 *
 * Sprint B (2026-05-13) deliverable. Operations-friendly query tool
 * for the Super Admin to answer: "show me certificate emails sent
 * since DATE, broken down by tenant + status".
 *
 * Backs the audit answer to "did this learner actually receive their
 * certificate" without requiring DB console access. Source of truth:
 * the `attachment_filename` + `certificate_issue_id` columns added
 * to `local_sentientia_email_log` in the Sprint B schema migration.
 *
 * USAGE
 *   php local/sentientia_emails/cli/cert_emails_report.php
 *     -- default: report from start-of-today, all tenants, summary table
 *
 *   php local/sentientia_emails/cli/cert_emails_report.php --since=2026-05-01
 *     -- report since the given ISO date
 *
 *   php local/sentientia_emails/cli/cert_emails_report.php --tenant=77
 *     -- restrict to a specific tenant root (1=Airpay, 77=Public, 177=ZEEA)
 *
 *   php local/sentientia_emails/cli/cert_emails_report.php --status=failed
 *     -- only show failed sends — useful when diagnosing email outage
 *
 *   php local/sentientia_emails/cli/cert_emails_report.php --detail
 *     -- per-row listing instead of aggregate summary
 *
 *   php local/sentientia_emails/cli/cert_emails_report.php --csv
 *     -- emit CSV (for spreadsheet import / audit trail)
 *
 * EXIT CODES
 *   0  report rendered (always 0 unless --since-failure-only-and-fail)
 *   2  invalid arguments
 *
 * @package local_sentientia_emails
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'    => false,
    'since'   => false,
    'tenant'  => false,
    'status'  => false,
    'detail'  => false,
    'csv'     => false,
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

// Resolve the since filter — default to start-of-today server local TZ.
$since_str = $options['since'] ?: date('Y-m-d');
$since_ts  = strtotime($since_str);
if ($since_ts === false || $since_ts <= 0) {
    cli_error("Invalid --since value: '$since_str' — expected YYYY-MM-DD", 2);
}

// Build WHERE clause. We focus on log rows that DID carry a certificate
// (certificate_issue_id IS NOT NULL OR attachment_filename IS NOT NULL).
$where = ['l.timecreated >= :since', '(l.certificate_issue_id IS NOT NULL OR l.attachment_filename IS NOT NULL)'];
$params = ['since' => $since_ts];

if (!empty($options['tenant'])) {
    $where[] = 'l.tenant_id = :tenant';
    $params['tenant'] = (int) $options['tenant'];
}
if (!empty($options['status'])) {
    $where[] = 'l.status = :status';
    $params['status'] = $options['status'];
}

$where_sql = implode(' AND ', $where);

if ($options['detail']) {
    // Per-row listing.
    $rows = $DB->get_records_sql(
        "SELECT l.id, l.timecreated, l.userid, l.courseid, l.tenant_id,
                l.status, l.attachment_filename, l.certificate_issue_id,
                u.firstname, u.lastname, u.email
           FROM {local_sentientia_email_log} l
      LEFT JOIN {user} u ON u.id = l.userid
          WHERE $where_sql
       ORDER BY l.timecreated DESC
          LIMIT 500",
        $params
    );

    if ($options['csv']) {
        echo "log_id,timestamp,user_name,user_email,courseid,tenant,status,filename,issue_id\n";
        foreach ($rows as $r) {
            printf("%d,%s,%s %s,%s,%d,%d,%s,%s,%s\n",
                $r->id,
                date('Y-m-d H:i:s', (int) $r->timecreated),
                $r->firstname, $r->lastname,
                $r->email,
                (int) $r->courseid,
                (int) $r->tenant_id,
                $r->status,
                $r->attachment_filename ?? '',
                $r->certificate_issue_id ?? '');
        }
    } else {
        cli_writeln('');
        cli_writeln(sprintf('  Certificate emails since %s (%d row(s))',
            $since_str, count($rows)));
        cli_writeln('  ' . str_repeat('-', 100));
        cli_writeln(sprintf('  %-20s  %-30s  %-9s  %-10s  %s',
            'When', 'Recipient', 'Tenant', 'Status', 'Attachment'));
        cli_writeln('  ' . str_repeat('-', 100));
        foreach ($rows as $r) {
            $recipient = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')
                . ' <' . ($r->email ?? '') . '>');
            $tenant_label = match ((int) $r->tenant_id) {
                1   => 'Airpay (1)',
                77  => 'Public(77)',
                177 => 'ZEEA(177)',
                default => (string) $r->tenant_id,
            };
            cli_writeln(sprintf('  %-20s  %-30s  %-9s  %-10s  %s',
                date('Y-m-d H:i', (int) $r->timecreated),
                substr($recipient, 0, 30),
                $tenant_label,
                $r->status,
                $r->attachment_filename ?? '(none)'));
        }
    }
} else {
    // Aggregate summary: count per (tenant, status).
    $summary = $DB->get_records_sql(
        "SELECT CONCAT(l.tenant_id, '-', l.status) AS key_,
                l.tenant_id, l.status, COUNT(*) AS cnt
           FROM {local_sentientia_email_log} l
          WHERE $where_sql
       GROUP BY l.tenant_id, l.status
       ORDER BY l.tenant_id, l.status",
        $params
    );

    if ($options['csv']) {
        echo "tenant_id,status,count\n";
        foreach ($summary as $r) {
            printf("%d,%s,%d\n", (int) $r->tenant_id, $r->status, (int) $r->cnt);
        }
    } else {
        cli_writeln('');
        cli_writeln(sprintf('  Certificate emails summary since %s', $since_str));
        cli_writeln('  ' . str_repeat('-', 56));
        cli_writeln(sprintf('  %-15s  %-25s  %s', 'Tenant', 'Status', 'Count'));
        cli_writeln('  ' . str_repeat('-', 56));

        $totals = ['sent' => 0, 'failed' => 0, 'suppressed' => 0, 'suppressed_completion' => 0];
        foreach ($summary as $r) {
            $tenant_label = match ((int) $r->tenant_id) {
                1   => 'Airpay (1)',
                77  => 'Public (77)',
                177 => 'ZEEA (177)',
                default => (string) $r->tenant_id,
            };
            cli_writeln(sprintf('  %-15s  %-25s  %d',
                $tenant_label, $r->status, (int) $r->cnt));
            $totals[$r->status] = ($totals[$r->status] ?? 0) + (int) $r->cnt;
        }
        cli_writeln('  ' . str_repeat('-', 56));
        cli_writeln(sprintf('  Sent: %d  Failed: %d  Suppressed: %d',
            $totals['sent'] ?? 0, $totals['failed'] ?? 0,
            ($totals['suppressed'] ?? 0) + ($totals['suppressed_completion'] ?? 0)));
        cli_writeln('');
        cli_writeln("  Re-run with --detail for the per-row listing.");
    }
}

exit(0);
