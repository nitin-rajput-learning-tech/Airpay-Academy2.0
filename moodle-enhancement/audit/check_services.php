<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

$funcs = $DB->get_records_select('external_functions',
    "name LIKE 'local_airpay_users_%'",
    [],
    'name ASC',
    'name, classname, methodname');

if (empty($funcs)) {
    echo "NO local_airpay_users web services registered\n";
} else {
    echo "Registered web services:\n";
    foreach ($funcs as $f) {
        echo "  - {$f->name} → {$f->classname}\n";
    }
}

// Verify capabilities exist
$caps = $DB->get_records_select('capabilities',
    "name LIKE 'local/airpay_users:%'",
    [],
    'name ASC',
    'name');

echo "\nRegistered capabilities:\n";
foreach ($caps as $c) {
    echo "  - {$c->name}\n";
}
