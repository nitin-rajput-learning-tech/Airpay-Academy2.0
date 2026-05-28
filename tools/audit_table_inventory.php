<?php
/**
 * Audit table inventory — read-only DB-row-count probe.
 *
 * Born from Bucket F closeout (Stabilization Audit 2026-05-28). Drives
 * the "is this plugin actually doing anything?" question that the audit
 * keeps asking. Run on local or production (it's read-only) to get a
 * one-screen snapshot of every Sentientia-product table's row count.
 *
 * Run from anywhere on a host that has the local Moodle config:
 *   php "D:/Claude Local/airpay-ld-os/tools/audit_table_inventory.php"
 *
 * Or, on the production server (read-only, safe):
 *   php /path/to/tools/audit_table_inventory.php
 *
 * The XAMPP-local config.php path is hardcoded — edit the require()
 * line on other hosts. Could be made resolver-based in v2.
 *
 * @package    tools
 * @copyright  2026 Airpay Payment Services
 */

define('CLI_SCRIPT', true);
require('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

$check = function (string $tbl) use ($DB) {
    if (!$DB->get_manager()->table_exists($tbl)) return "⚠ MISSING";
    return $DB->count_records($tbl) . " rows";
};

$groups = [
    'PWA (sentientia_pwa)' => [
        'local_sentientia_push_subs',
        'local_sentientia_push_log',
    ],
    'Leaderboard (sentientia_leaderboard)' => [
        'local_sentientia_lb_boards',
        'local_sentientia_lb_entries',
        'local_sentientia_lb_optouts',
        'local_sentientia_lb_events',
        'local_sentientia_lb_notify_log',
    ],
    'AI Quiz (sentientia_aiquiz)' => [
        'local_sentientia_aiquiz_draft',
        'local_sentientia_aiquiz_question',
    ],
    'Calendar (sentientia_calendar)' => [
        'local_sentientia_calendar_token',
        'local_sentientia_calendar_oauth',
    ],
    'Live (sentientia_live)' => [
        'local_sentientia_live_sessions',
        'local_sentientia_live_slides',
        'local_sentientia_live_participants',
        'local_sentientia_live_responses',
        'local_sentientia_live_events',
    ],
    'Translate (sentientia_translate)' => [
        'local_sentientia_tr_log',
        'local_sentientia_tr_brand',
    ],
    'Recommendations (sentientia_recommendations)' => [
        'local_sentientia_rec_log',
    ],
    'M365 (sentientia_m365)' => [
        'local_sentientia_m365_tokens',
    ],
    'User-type (ADR-017)' => [
        'local_airpay_user_type',
        'local_airpay_employee_profile',
        'local_airpay_consumer_profile',
        'local_airpay_partner_employee_profile',
        'local_airpay_operator_profile',
    ],
    'Challenge (D4 downgrade)' => [
        'local_airpay_challenge_challenges',
        'local_airpay_challenge_attempts',
        'local_airpay_challenge_leaderboard',
    ],
];

foreach ($groups as $label => $tables) {
    echo "── $label ──\n";
    foreach ($tables as $t) {
        echo sprintf("  %-45s %s\n", $t, $check($t));
    }
    echo "\n";
}
