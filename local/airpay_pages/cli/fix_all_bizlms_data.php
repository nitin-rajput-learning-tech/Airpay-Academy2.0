<?php
/**
 * airpay academy — Fix ALL BizLMS data mappings in one pass.
 *
 * Fixes:
 *   1. Course open_path — maps all courses to Airpay org
 *   2. Course selfenrol — enables self-enrolment for catalog visibility
 *   3. Course open_identifiedas — sets course type (required for JOIN)
 *   4. Supervisor mapping — all emp_* under mgr_nitin
 *   5. Verify all user open_costcenterid values
 *
 * Run: php local/airpay_pages/cli/fix_all_bizlms_data.php
 */
define('CLI_SCRIPT', true);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

global $DB, $CFG;

echo "=== airpay academy — BizLMS Data Fix (All-in-One) ===\n\n";

// ── Pre-check: verify costcenters exist ─────────────────
$airpay = $DB->get_record('local_costcenter', ['id' => 1]);
if (!$airpay) {
    echo "FATAL: Airpay costcenter (id=1) not found. Run setup_costcenters.php first.\n";
    exit(1);
}
echo "Airpay org: id={$airpay->id}, path={$airpay->path}, category={$airpay->category}\n";

// Get departments.
$depts = $DB->get_records('local_costcenter', ['parentid' => $airpay->id], 'id ASC');
echo "Departments: " . count($depts) . "\n";
foreach ($depts as $d) {
    echo "  id={$d->id} | {$d->fullname} | path={$d->path}\n";
}

// ═══════════════════════════════════════════════════════════
// FIX 1: Course open_path — map courses to Airpay org
// ═══════════════════════════════════════════════════════════
echo "\n--- FIX 1: Course open_path ---\n";

// Check if open_path column exists on course table.
$coursecols = $DB->get_columns('course');
if (!isset($coursecols['open_path'])) {
    echo "ERROR: open_path column not found on mdl_course. BizLMS schema incomplete.\n";
    echo "Adding open_path column...\n";
    $dbman = $DB->get_manager();
    $table = new xmldb_table('course');
    $field = new xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    try {
        $dbman->add_field($table, $field);
        echo "  ✓ Added open_path to mdl_course\n";
    } catch (\Throwable $e) {
        echo "  ✗ Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Check current state.
$null_path = $DB->count_records_select('course', "id > 1 AND (open_path IS NULL OR open_path = '')");
$has_path = $DB->count_records_select('course', "id > 1 AND open_path IS NOT NULL AND open_path != ''");
echo "Courses with open_path: $has_path | without: $null_path\n";

// Assign all courses to Airpay root org.
$airpay_path = $airpay->path; // Should be '/1'
if (empty($airpay_path)) {
    $airpay_path = '/' . $airpay->id;
}

$DB->execute(
    "UPDATE {course} SET open_path = ? WHERE id > 1 AND (open_path IS NULL OR open_path = '')",
    [$airpay_path]
);
$updated = $DB->count_records_select('course', "id > 1 AND open_path = ?", [$airpay_path]);
echo "✓ Set open_path='$airpay_path' on $updated courses\n";

// ═══════════════════════════════════════════════════════════
// FIX 2: Course selfenrol — enable for catalog visibility
// ═══════════════════════════════════════════════════════════
echo "\n--- FIX 2: Course selfenrol ---\n";

if (!isset($coursecols['selfenrol'])) {
    echo "Adding selfenrol column...\n";
    $dbman = $DB->get_manager();
    $table = new xmldb_table('course');
    $field = new xmldb_field('selfenrol', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
    try {
        $dbman->add_field($table, $field);
        echo "  ✓ Added selfenrol to mdl_course\n";
    } catch (\Throwable $e) {
        echo "  Column may already exist: " . $e->getMessage() . "\n";
    }
}

$no_selfenrol = $DB->count_records_select('course', "id > 1 AND (selfenrol IS NULL OR selfenrol = 0)");
echo "Courses without selfenrol: $no_selfenrol\n";
$DB->execute("UPDATE {course} SET selfenrol = 1 WHERE id > 1");
echo "✓ Set selfenrol=1 on all courses\n";

// ═══════════════════════════════════════════════════════════
// FIX 3: Course open_identifiedas — set course type
// ═══════════════════════════════════════════════════════════
echo "\n--- FIX 3: Course type (open_identifiedas) ---\n";

// Check if local_course_types table exists and has data.
$tables = $DB->get_tables();
if (in_array('local_course_types', $tables)) {
    $types = $DB->get_records('local_course_types', null, 'id ASC');
    echo "Course types available:\n";
    foreach ($types as $t) {
        echo "  id={$t->id} | " . ($t->name ?? $t->shortname ?? 'unnamed') . "\n";
    }
    if (!empty($types)) {
        $default_type = reset($types)->id;
    } else {
        // Create a default type.
        $default_type = $DB->insert_record('local_course_types', (object)[
            'name' => 'E-Learning',
            'shortname' => 'elearning',
        ]);
        echo "  ✓ Created default course type: E-Learning (id=$default_type)\n";
    }
} else {
    echo "local_course_types table not found — skipping\n";
    $default_type = null;
}

if ($default_type && isset($coursecols['open_identifiedas'])) {
    $DB->execute(
        "UPDATE {course} SET open_identifiedas = ? WHERE id > 1 AND (open_identifiedas IS NULL OR open_identifiedas = 0)",
        [$default_type]
    );
    echo "✓ Set open_identifiedas=$default_type on courses missing type\n";
} elseif (!isset($coursecols['open_identifiedas'])) {
    echo "open_identifiedas column not found on mdl_course — skipping\n";
}

// ═══════════════════════════════════════════════════════════
// FIX 4: Supervisor mapping — emp_* under mgr_nitin
// ═══════════════════════════════════════════════════════════
echo "\n--- FIX 4: Supervisor mapping ---\n";

$mgr = $DB->get_record('user', ['username' => 'mgr_nitin']);
if ($mgr) {
    echo "Manager: mgr_nitin (id={$mgr->id})\n";

    // Get all emp_* users.
    $employees = $DB->get_records_select('user',
        "username LIKE 'emp_%' AND deleted = 0", null, 'id ASC');

    foreach ($employees as $emp) {
        $DB->execute("UPDATE {user} SET open_supervisorid = ? WHERE id = ?",
            [$mgr->id, $emp->id]);
        echo "  ✓ {$emp->username} → supervisor: mgr_nitin\n";
    }
    echo "✓ " . count($employees) . " employees assigned to mgr_nitin\n";
} else {
    echo "⚠ mgr_nitin not found — skipping supervisor setup\n";
}

// ═══════════════════════════════════════════════════════════
// FIX 5: Verify user open_costcenterid values
// ═══════════════════════════════════════════════════════════
echo "\n--- FIX 5: Verify user costcenter assignments ---\n";

$usercols = $DB->get_columns('user');
if (isset($usercols['open_costcenterid'])) {
    $unassigned = $DB->count_records_select('user',
        "deleted = 0 AND id > 1 AND (open_costcenterid IS NULL OR open_costcenterid = 0)");
    if ($unassigned > 0) {
        $DB->execute(
            "UPDATE {user} SET open_costcenterid = ? WHERE deleted = 0 AND id > 1 AND (open_costcenterid IS NULL OR open_costcenterid = 0)",
            [$airpay->id]
        );
        echo "✓ Fixed $unassigned users with missing costcenterid\n";
    } else {
        echo "✓ All users have costcenterid assigned\n";
    }

    // Verify superadmin.
    $admin = $DB->get_record('user', ['username' => 'superadmin']);
    echo "superadmin: costcenterid={$admin->open_costcenterid}, path={$admin->open_path}\n";
} else {
    echo "⚠ open_costcenterid column not on user table\n";
}

// ═══════════════════════════════════════════════════════════
// FIX 6: Ensure open_coursetype exists and is set
// ═══════════════════════════════════════════════════════════
echo "\n--- FIX 6: Course type (open_coursetype) ---\n";
if (isset($coursecols['open_coursetype'])) {
    $DB->execute("UPDATE {course} SET open_coursetype = 0 WHERE id > 1 AND open_coursetype IS NULL");
    echo "✓ Set open_coursetype=0 (e-learning) for courses with NULL type\n";
} else {
    echo "open_coursetype column not found — skipping\n";
}

// ═══════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════
echo "\n=== SUMMARY ===\n";

$total_courses = $DB->count_records_select('course', 'id > 1');
$mapped_courses = $DB->count_records_select('course', "id > 1 AND open_path IS NOT NULL AND open_path != ''");
$selfenrol_courses = $DB->count_records_select('course', "id > 1 AND selfenrol = 1");
$total_users = $DB->count_records_select('user', 'deleted = 0 AND id > 1');

echo "Courses: $total_courses total, $mapped_courses mapped to org, $selfenrol_courses with selfenrol\n";
echo "Users: $total_users total\n";

if (isset($usercols['open_costcenterid'])) {
    $assigned_users = $DB->count_records_select('user', "deleted = 0 AND id > 1 AND open_costcenterid > 0");
    echo "Users with org: $assigned_users\n";
}

if ($mgr) {
    $team_count = $DB->count_records('user', ['open_supervisorid' => $mgr->id, 'deleted' => 0]);
    echo "mgr_nitin team size: $team_count\n";
}

// Test the critical JOIN query.
echo "\n--- Testing BizLMS course query ---\n";
try {
    $test = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.open_path
           FROM {course} c
           JOIN {local_costcenter} co ON co.path = c.open_path
          WHERE c.id > 1
          LIMIT 5"
    );
    echo "JOIN query returned: " . count($test) . " courses\n";
    foreach ($test as $t) {
        echo "  id={$t->id} | {$t->fullname} | path={$t->open_path}\n";
    }
} catch (\Throwable $e) {
    echo "JOIN query FAILED: " . $e->getMessage() . "\n";
}

echo "\n✓ All fixes applied. Purge caches and test.\n";
