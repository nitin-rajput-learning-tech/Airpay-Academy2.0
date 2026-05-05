<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB, $USER;
$USER = $DB->get_record('user', ['id' => 2]);

echo "=== Bulk Action Smoke Test ===\n\n";

// Pick 3 active test users (deletable / non-special).
$candidates = $DB->get_records_sql(
    "SELECT id, firstname, lastname, suspended FROM {user}
      WHERE deleted = 0 AND id > 100 AND suspended = 0
   ORDER BY id ASC LIMIT 3", []);

if (count($candidates) < 3) {
    echo "Not enough candidates.\n"; exit;
}
$ids = array_keys($candidates);
echo "Test ids: " . implode(', ', $ids) . "\n";
foreach ($candidates as $u) {
    echo "  -> #{$u->id}: {$u->firstname} {$u->lastname} (suspended={$u->suspended})\n";
}

// 1. Bulk suspend
echo "\n[1] Bulk suspend " . count($ids) . " users...\n";
$r = \local_airpay_users\external\bulk_action::execute('suspend', $ids);
echo "    -> action={$r['action']}, count={$r['count']}, skipped={$r['skipped']}\n";

// Verify in DB
$now_susp = $DB->count_records_select('user',
    'id IN (' . implode(',', $ids) . ') AND suspended = 1');
echo "    -> DB confirms {$now_susp} now suspended\n";

// 2. Bulk activate
echo "\n[2] Bulk activate the same set...\n";
$r = \local_airpay_users\external\bulk_action::execute('activate', $ids);
echo "    -> action={$r['action']}, count={$r['count']}, skipped={$r['skipped']}\n";

$now_active = $DB->count_records_select('user',
    'id IN (' . implode(',', $ids) . ') AND suspended = 0');
echo "    -> DB confirms {$now_active} now active\n";

// 3. Try to suspend admin id=2 (should be skipped)
echo "\n[3] Try to suspend admin id=2 + 1 normal id (admin should be skipped)...\n";
$with_admin = array_merge([2], [$ids[0]]);
$r = \local_airpay_users\external\bulk_action::execute('suspend', $with_admin);
echo "    -> count={$r['count']}, skipped={$r['skipped']} (expected: count=1, skipped=1)\n";

// Cleanup: re-activate.
\local_airpay_users\external\bulk_action::execute('activate', $ids);

// 4. Empty list
echo "\n[4] Empty array...\n";
$r = \local_airpay_users\external\bulk_action::execute('suspend', []);
echo "    -> count={$r['count']}, skipped={$r['skipped']} (expected: 0, 0)\n";

echo "\nDone.\n";
