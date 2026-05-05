<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB, $USER;
$USER = $DB->get_record('user', ['id' => 2]);

$call = function (array $args) {
    return \local_airpay_courses\external\list_courses::execute(...$args);
};

echo "=== list_courses smoke test ===\n\n";
echo "[1] All visible:\n";
$r = $call(['', 'fullname', 'asc', 0, 5, json_encode(['visibility' => 'visible'])]);
echo "    total={$r['total']}\n";
foreach ($r['rows'] as $row) {
    echo "    -> {$row['shortname']}: enrolled={$row['enrolled']}\n";
}

echo "\n[2] Search 'POSH':\n";
$r = $call(['POSH', 'fullname', 'asc', 0, 5, json_encode([])]);
echo "    total={$r['total']}\n";
foreach ($r['rows'] as $row) {
    echo "    -> {$row['shortname']}\n";
}

echo "\n[3] Sort by enrolled (timecreated desc as proxy — newest first):\n";
$r = $call(['', 'timecreated', 'desc', 0, 5, json_encode([])]);
echo "    total={$r['total']}\n";
foreach ($r['rows'] as $row) {
    echo "    -> {$row['shortname']}: created={$row['created']}\n";
}

echo "\nDone.\n";
