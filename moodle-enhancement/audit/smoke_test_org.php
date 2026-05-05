<?php
// Smoke-test the org_manager CRUD methods.
// Run from CLI: php smoke_test_org.php

define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');

global $DB, $USER;

echo "=== Airpay Org CRUD Smoke Test ===\n\n";

use \local_airpay_org\org_manager;

// 1. Read tenants
echo "[1] Reading tenants (depth=1):\n";
$tenants = org_manager::get_tenants();
foreach ($tenants as $t) {
    echo "    #{$t->id} {$t->fullname} (path={$t->path}, visible={$t->visible})\n";
}
echo "    -> " . count($tenants) . " tenants\n\n";

// 2. Create — sub-org under Airpay tenant (id=1)
echo "[2] Creating test sub-org under Airpay tenant (id=1)...\n";
$test_data = (object) [
    'fullname'    => 'CRUD-TEST-DEPT-' . time(),
    'shortname'   => 'CRUDTEST_' . time(),
    'description' => 'Created by smoke_test_org.php — safe to delete.',
    'parentid'    => 1,
    'visible'     => 1,
    'sortorder'   => 999,
    'brand_color' => '#abcdef',
];
$newid = org_manager::create($test_data);
echo "    -> created org #{$newid}\n";
$record = org_manager::get($newid);
echo "    -> path={$record->path}, depth={$record->depth}, brand={$record->brand_color}\n\n";

// 3. Update
echo "[3] Updating org #{$newid}...\n";
$update_data = (object) [
    'fullname' => $test_data->fullname . ' (UPDATED)',
    'brand_color' => '#fedcba',
];
org_manager::update($newid, $update_data);
$updated = org_manager::get($newid);
echo "    -> fullname={$updated->fullname}\n";
echo "    -> brand_color={$updated->brand_color}\n\n";

// 4. Toggle visibility
echo "[4] Toggling visibility...\n";
$state1 = org_manager::toggle_visibility($newid);
$state2 = org_manager::toggle_visibility($newid);
echo "    -> after first toggle: visible=" . ($state1 ? 'true' : 'false') . "\n";
echo "    -> after second toggle: visible=" . ($state2 ? 'true' : 'false') . "\n\n";

// 5. Count helpers
echo "[5] Count helpers on tenant id=1 (Airpay):\n";
$desc = org_manager::count_descendants(1);
$users = org_manager::count_users(1);
echo "    -> descendants: {$desc}\n";
echo "    -> users:       {$users}\n\n";

// 6. Try to delete a tenant (should fail)
echo "[6] Try delete tenant id=1 (should refuse)...\n";
try {
    org_manager::delete(1);
    echo "    !!! UNEXPECTED: delete succeeded — this is a bug.\n";
} catch (\moodle_exception $e) {
    echo "    -> correctly refused: {$e->getMessage()}\n";
}
echo "\n";

// 7. Delete the test org we just created
echo "[7] Cleaning up — delete test org #{$newid}...\n";
try {
    org_manager::delete($newid);
    echo "    -> deleted successfully\n";
    $check = org_manager::get($newid);
    echo "    -> verify get(): " . ($check === false ? 'returns false (gone)' : 'still exists!') . "\n";
} catch (\moodle_exception $e) {
    echo "    !!! delete blocked: {$e->getMessage()}\n";
}

echo "\nDone.\n";
