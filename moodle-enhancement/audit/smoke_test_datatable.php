<?php
// Smoke-test the list_users web service.
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');

global $DB, $USER;

// Login as siteadmin (id=2 in Moodle defaults).
$USER = $DB->get_record('user', ['id' => 2]);

echo "=== Datatable list_users WS Smoke Test ===\n\n";

$call = function (array $args) {
    return \local_airpay_users\external\list_users::execute(...$args);
};

// 1. Default — active users only
echo "[1] Default (active users):\n";
$r = $call(['', 'lastname', 'asc', 0, 5, json_encode(['status' => 'active'])]);
echo "    total={$r['total']}, returned=" . count($r['rows']) . "\n";
if (!empty($r['rows'])) {
    echo "    first row: " . substr($r['rows'][0]['fullname'], 0, 80) . "...\n";
}
echo "\n";

// 2. Search
echo "[2] Search 'nitin':\n";
$r = $call(['nitin', 'lastname', 'asc', 0, 5, json_encode(['status' => 'all'])]);
echo "    total={$r['total']}\n";
foreach ($r['rows'] as $row) {
    echo "    -> {$row['email']}\n";
}
echo "\n";

// 3. Org filter — Airpay tenant
echo "[3] Filter orgid=1 (Airpay):\n";
$r = $call(['', 'lastname', 'asc', 0, 5, json_encode(['orgid' => 1, 'status' => 'all'])]);
echo "    total={$r['total']}\n\n";

// 4. Suspended only
echo "[4] Suspended status:\n";
$r = $call(['', 'lastname', 'asc', 0, 5, json_encode(['status' => 'suspended'])]);
echo "    total={$r['total']}\n\n";

// 5. Sort by lastaccess desc — most recent first
echo "[5] Sort by lastaccess DESC, page 0, perpage 3:\n";
$r = $call(['', 'lastaccess', 'desc', 0, 3, json_encode(['status' => 'all'])]);
echo "    total={$r['total']}\n";
foreach ($r['rows'] as $row) {
    echo "    -> {$row['email']} (last: {$row['lastaccess']})\n";
}
echo "\n";

// 6. Pagination — page 1
echo "[6] Pagination — page 1, perpage 5:\n";
$r = $call(['', 'lastname', 'asc', 1, 5, json_encode(['status' => 'all'])]);
echo "    total={$r['total']}, page=1 returned " . count($r['rows']) . "\n";

// 7. Edge case — search with no matches
echo "\n[7] Search 'xxxnomatchxxx':\n";
$r = $call(['xxxnomatchxxx', 'lastname', 'asc', 0, 5, json_encode(['status' => 'all'])]);
echo "    total={$r['total']} (expected 0)\n";

echo "\nDone.\n";
