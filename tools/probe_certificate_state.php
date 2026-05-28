<?php
/**
 * Read-only probe — Certificate stack DB state.
 *
 * Born from C10 (Stabilization Audit Bucket C, 2026-05-28). Walks the
 * `tool_certificate_*` tables, lists templates + recent issues, checks
 * presence of Sentientia overlay files. Safe to run on production —
 * pure SELECT.
 *
 * Output answers the question "what's actually shipped in the
 * certificate stack?" — driving the
 * `docs/audits/C10-CERTIFICATE-STACK-INVESTIGATION-2026-05-28.md`
 * gap analysis.
 *
 * @package    tools
 * @copyright  2026 Airpay Payment Services
 */
define('CLI_SCRIPT', true);
require('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

echo "── Certificate stack on local ──\n";
foreach ([
    'tool_certificate_templates',
    'tool_certificate_issues',
    'tool_certificate_pages',
    'tool_certificate_elements',
] as $t) {
    if ($DB->get_manager()->table_exists($t)) {
        printf("  %-35s %d rows\n", $t, $DB->count_records($t));
    } else {
        printf("  %-35s MISSING\n", $t);
    }
}

echo "\n── Templates (top 10) ──\n";
$tpls = $DB->get_records('tool_certificate_templates', null,
    'timecreated DESC', 'id, name, shared, contextid', 0, 10);
foreach ($tpls as $t) {
    printf("  id=%-3d  shared=%-1d  ctx=%-4d  '%s'\n",
        $t->id, $t->shared, $t->contextid, $t->name);
}

echo "\n── Recent issues (last 10) ──\n";
$issues = $DB->get_records('tool_certificate_issues', null,
    'timecreated DESC', 'id, templateid, userid, courseid, code', 0, 10);
foreach ($issues as $i) {
    $u = $DB->get_record('user', ['id' => $i->userid],
        'firstname, lastname', IGNORE_MISSING);
    $name = $u ? "{$u->firstname} {$u->lastname}" : "(deleted)";
    printf("  id=%-4d  template=%-3d  user=%-5d (%-20s)  course=%-4d  code=%s\n",
        $i->id, $i->templateid, $i->userid, substr($name, 0, 20),
        $i->courseid ?? 0, $i->code);
}

echo "\n── airpay_pages/certificates.php presence ──\n";
$path = $CFG->dirroot . '/local/airpay_pages/certificates.php';
echo "  " . (file_exists($path) ? "EXISTS  " : "MISSING ") . $path . "\n";

echo "\n── certificate_helper.php presence ──\n";
$path = $CFG->dirroot . '/local/airpay_emails/classes/certificate_helper.php';
echo "  " . (file_exists($path) ? "EXISTS  " : "MISSING ") . $path . "\n";

echo "\n── airpay_emails rules that attach a certificate ──\n";
if ($DB->get_manager()->table_exists('local_airpay_email_rules')) {
    $sql = "SELECT * FROM {local_airpay_email_rules}
             WHERE auto_stop_on_completion = 1 OR action LIKE '%cert%' OR rule_key LIKE '%complet%'
          ORDER BY id";
    $rules = $DB->get_records_sql($sql);
    if (empty($rules)) {
        echo "  (none matching completion+certificate)\n";
    }
    foreach ($rules as $r) {
        printf("  rule id=%-3d  key=%-30s  active=%d\n",
            $r->id, $r->rule_key ?? $r->action ?? '?', $r->is_active ?? 0);
    }
}

echo "\nProbe complete.\n";
