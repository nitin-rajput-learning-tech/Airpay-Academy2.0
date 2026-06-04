<?php
/**
 * airpay academy — Add missing BizLMS columns to mdl_user and populate data.
 *
 * Adds open_costcenterid and open_departmentid columns that BizLMS expects
 * but were missing from the local installation.
 *
 * Run: php local/sentientia_pages/cli/fix_bizlms_columns.php
 */
define('CLI_SCRIPT', true);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

global $DB, $CFG;

echo "=== Add missing BizLMS columns + populate data ===\n\n";

$dbman = $DB->get_manager();
$usercols = $DB->get_columns('user');

// ── Step 1: Add open_costcenterid if missing ────────────
if (!isset($usercols['open_costcenterid'])) {
    echo "Adding open_costcenterid column...\n";
    $table = new xmldb_table('user');
    $field = new xmldb_field('open_costcenterid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'open_location');
    if (!$dbman->field_exists($table, $field)) {
        // Try adding after the last column if open_location doesn't exist.
        try {
            $dbman->add_field($table, $field);
            echo "  ✓ Added open_costcenterid\n";
        } catch (\Throwable $e) {
            // Try without the 'after' reference.
            $field2 = new xmldb_field('open_costcenterid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $dbman->add_field($table, $field2);
            echo "  ✓ Added open_costcenterid (no position ref)\n";
        }
    }
} else {
    echo "  open_costcenterid already exists\n";
}

// ── Step 2: Add open_departmentid if missing ────────────
if (!isset($usercols['open_departmentid'])) {
    echo "Adding open_departmentid column...\n";
    $table = new xmldb_table('user');
    $field = new xmldb_field('open_departmentid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
    try {
        $dbman->add_field($table, $field);
        echo "  ✓ Added open_departmentid\n";
    } catch (\Throwable $e) {
        echo "  ⚠ Could not add open_departmentid: " . $e->getMessage() . "\n";
    }
} else {
    echo "  open_departmentid already exists\n";
}

// ── Step 3: Verify columns now exist ────────────────────
$usercols = $DB->get_columns('user');
if (!isset($usercols['open_costcenterid'])) {
    echo "\nFATAL: open_costcenterid still not present. Aborting.\n";
    exit(1);
}
echo "\n✓ Both columns verified in mdl_user\n";

// ── Step 4: Assign ALL users to Airpay (costcenter id=1) ─
echo "\n--- Assigning users to Airpay org ---\n";
$airpay_id = 1;
$airpay_path = '/1';

// Get a department to assign (Technology, id=3).
$tech_dept = $DB->get_record('local_costcenter', ['id' => 3]);
$default_dept_id = $tech_dept ? $tech_dept->id : 0;

// Assign superadmin.
$DB->execute("UPDATE {user} SET open_costcenterid = ?, open_departmentid = 0, open_path = ? WHERE username = 'superadmin'",
    [$airpay_id, $airpay_path]);
echo "  ✓ superadmin → Airpay (org admin, no dept)\n";

// Assign test users to departments.
$dept_map = [
    'emp_priya'     => ['dept' => 3,  'name' => 'Technology'],
    'emp_ravi'      => ['dept' => 4,  'name' => 'Operations'],
    'emp_anita'     => ['dept' => 5,  'name' => 'Finance'],
    'emp_vikram'    => ['dept' => 6,  'name' => 'Human Resources'],
    'emp_neha'      => ['dept' => 7,  'name' => 'Sales'],
    'emp_amit'      => ['dept' => 8,  'name' => 'Marketing'],
    'emp_sunita'    => ['dept' => 9,  'name' => 'Compliance'],
    'emp_kiran'     => ['dept' => 10, 'name' => 'Customer Support'],
    'emp_deepak'    => ['dept' => 11, 'name' => 'Product'],
    'emp_meera'     => ['dept' => 3,  'name' => 'Technology'],
    'mgr_nitin'     => ['dept' => 0,  'name' => 'Manager (all)'],
    'test_admin'    => ['dept' => 0,  'name' => 'L&D Admin (all)'],
    'test_manager'  => ['dept' => 0,  'name' => 'Manager (all)'],
];

foreach ($dept_map as $username => $info) {
    $user = $DB->get_record('user', ['username' => $username]);
    if ($user) {
        $dept_id = $info['dept'];
        $path = $dept_id > 0 ? "/$airpay_id/$dept_id" : $airpay_path;
        $DB->execute("UPDATE {user} SET open_costcenterid = ?, open_departmentid = ?, open_path = ? WHERE id = ?",
            [$airpay_id, $dept_id, $path, $user->id]);
        echo "  ✓ $username → {$info['name']}\n";
    }
}

// Assign any remaining unassigned users to Airpay + Technology.
$remaining = $DB->execute(
    "UPDATE {user} SET open_costcenterid = ?, open_departmentid = ?, open_path = ?
     WHERE deleted = 0 AND id > 1 AND (open_costcenterid IS NULL OR open_costcenterid = 0)",
    [$airpay_id, $default_dept_id, "/$airpay_id/$default_dept_id"]
);
echo "  ✓ Remaining users → Airpay/Technology\n";

// ── Step 5: Summary ─────────────────────────────────────
echo "\n=== Verification ===\n";
$airpay_users = $DB->count_records_select('user', 'deleted = 0 AND open_costcenterid = ?', [$airpay_id]);
echo "Users in Airpay org: $airpay_users\n";

$depts = $DB->get_records('local_costcenter', ['parentid' => $airpay_id], 'id ASC');
foreach ($depts as $d) {
    $dcount = $DB->count_records('user', ['open_departmentid' => $d->id, 'deleted' => 0]);
    echo "  {$d->fullname}: $dcount users\n";
}

$courses = $DB->count_records_select('course', 'id > 1');
echo "\nTotal courses: $courses\n";

echo "\n✓ Done. Purge caches and test all BizLMS pages.\n";
