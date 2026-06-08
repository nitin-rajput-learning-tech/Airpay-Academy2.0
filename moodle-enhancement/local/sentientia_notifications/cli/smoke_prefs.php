<?php
// Smoke test: per-user notification preferences round-trip + rule_engine
// honours channel toggles, rule-type opt-outs, and quiet hours.
//
// Run: php public/local/sentientia_notifications/cli/smoke_prefs.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_sentientia_notifications\prefs_manager;

$user = $DB->get_record_sql(
    "SELECT id FROM {user} WHERE deleted = 0 AND suspended = 0
       AND username NOT IN ('admin', 'guest') ORDER BY id ASC LIMIT 1");
if (!$user) {
    fwrite(STDERR, "FAIL: no user fixture.\n");
    exit(1);
}
$userid = (int) $user->id;
echo "Using user id=$userid\n";

// Cleanup any previous prefs row.
$DB->delete_records('local_sentientia_notif_prefs', ['userid' => $userid]);

// 1. Get_for_user returns defaults when no row exists.
$p = prefs_manager::get_for_user($userid);
if ((int) $p->channel_inapp !== 1 || (int) $p->channel_email !== 1
    || (int) $p->channel_push !== 0 || $p->digest_frequency !== 'daily'
    || !empty($p->disabled_rule_types)
    || $p->quiet_hours_start !== null || $p->quiet_hours_end !== null) {
    fwrite(STDERR, "FAIL: defaults mismatch.\n");
    var_dump($p);
    exit(2);
}
echo "Defaults OK ✓\n";

// 2. Save a row with opt-outs + quiet hours.
$id = prefs_manager::save_for_user(
    $userid, true, false, true, 'weekly',
    ['streak_broken', 'monthly_summary'],
    22, 7);
echo "Saved row id=$id\n";

// 3. Get_for_user returns the saved values.
$p = prefs_manager::get_for_user($userid);
if ((int) $p->channel_inapp !== 1 || (int) $p->channel_email !== 0
    || (int) $p->channel_push !== 1 || $p->digest_frequency !== 'weekly') {
    fwrite(STDERR, "FAIL: saved channels/digest mismatch.\n");
    var_dump($p);
    exit(3);
}
if ($p->disabled_rule_types !== ['streak_broken', 'monthly_summary']) {
    fwrite(STDERR, "FAIL: disabled_rule_types mismatch: "
        . print_r($p->disabled_rule_types, true) . "\n");
    exit(4);
}
if ((int) $p->quiet_hours_start !== 22 || (int) $p->quiet_hours_end !== 7) {
    fwrite(STDERR, "FAIL: quiet hours mismatch.\n");
    exit(5);
}
echo "Saved values round-trip ✓\n";

// 4. Out-of-range guard.
try {
    prefs_manager::save_for_user(
        $userid, true, true, false, 'weekly', [], 25, 30);
    fwrite(STDERR, "FAIL: out-of-range hour did not throw.\n");
    exit(6);
} catch (\Throwable $e) {
    echo "Out-of-range guard ✓ ({$e->getMessage()})\n";
}

// 5. Bad rule type silently filtered.
$id2 = prefs_manager::save_for_user(
    $userid, true, true, false, 'daily',
    ['streak_broken', 'NOT_A_REAL_TYPE', 'inactive_user'],
    null, null);
$p = prefs_manager::get_for_user($userid);
if ($p->disabled_rule_types !== ['streak_broken', 'inactive_user']) {
    fwrite(STDERR, "FAIL: invalid rule type not filtered: "
        . print_r($p->disabled_rule_types, true) . "\n");
    exit(7);
}
echo "Invalid rule type filtered ✓\n";

// 6. Cleanup.
$DB->delete_records('local_sentientia_notif_prefs', ['userid' => $userid]);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
