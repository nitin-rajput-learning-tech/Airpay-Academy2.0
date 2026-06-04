<?php
/**
 * airpay academy — Configure BizLMS synthetic test data.
 *
 * Creates org structure, departments, assigns users and courses
 * to costcenters WITHOUT modifying any DB schema.
 *
 * Uses: local_userdata.costcenterpath, course_categories, local_costcenter
 *
 * Run: php local/sentientia_pages/cli/setup_bizlms_data.php
 */
define('CLI_SCRIPT', true);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

global $DB, $CFG;

echo "=== airpay academy — BizLMS Synthetic Data Setup ===\n\n";

// ── Step 1: Verify costcenters exist ────────────────────
$airpay = $DB->get_record('local_costcenter', ['id' => 1]);
$public = $DB->get_record('local_costcenter', ['id' => 2]);

if (!$airpay || !$public) {
    echo "ERROR: Costcenters not found. Run setup_costcenters.php first.\n";
    exit(1);
}
echo "Costcenters: Airpay(id={$airpay->id}, cat={$airpay->category}), Public(id={$public->id}, cat={$public->category})\n";

// ── Step 2: Create departments (business units) ─────────
echo "\n--- Creating Business Units ---\n";
$columns = $DB->get_columns('local_costcenter');
$now = time();

$airpay_depts = [
    'Technology', 'Operations', 'Finance', 'Human Resources',
    'Sales', 'Marketing', 'Compliance', 'Customer Support', 'Product'
];
$public_depts = ['External Partners'];

foreach ($airpay_depts as $dept) {
    $existing = $DB->get_record('local_costcenter', ['shortname' => strtolower(str_replace(' ', '_', $dept)), 'parentid' => $airpay->id]);
    if ($existing) {
        echo "  Exists: $dept (id={$existing->id})\n";
        continue;
    }
    $rec = new stdClass();
    $rec->fullname = $dept;
    $rec->shortname = strtolower(str_replace(' ', '_', $dept));
    $rec->parentid = $airpay->id;
    $rec->depth = 2;
    $rec->path = '';
    if (isset($columns['visible'])) $rec->visible = 1;
    if (isset($columns['timecreated'])) $rec->timecreated = $now;
    if (isset($columns['timemodified'])) $rec->timemodified = $now;
    if (isset($columns['usermodified'])) $rec->usermodified = 2;
    if (isset($columns['category'])) $rec->category = $airpay->category;
    $deptid = $DB->insert_record('local_costcenter', $rec);
    $DB->set_field('local_costcenter', 'path', "/{$airpay->id}/{$deptid}", ['id' => $deptid]);
    echo "  ✓ Created: $dept (id=$deptid) under Airpay\n";
}

foreach ($public_depts as $dept) {
    $existing = $DB->get_record('local_costcenter', ['shortname' => strtolower(str_replace(' ', '_', $dept)), 'parentid' => $public->id]);
    if ($existing) {
        echo "  Exists: $dept (id={$existing->id})\n";
        continue;
    }
    $rec = new stdClass();
    $rec->fullname = $dept;
    $rec->shortname = strtolower(str_replace(' ', '_', $dept));
    $rec->parentid = $public->id;
    $rec->depth = 2;
    $rec->path = '';
    if (isset($columns['visible'])) $rec->visible = 1;
    if (isset($columns['timecreated'])) $rec->timecreated = $now;
    if (isset($columns['timemodified'])) $rec->timemodified = $now;
    if (isset($columns['usermodified'])) $rec->usermodified = 2;
    if (isset($columns['category'])) $rec->category = $public->category;
    $deptid = $DB->insert_record('local_costcenter', $rec);
    $DB->set_field('local_costcenter', 'path', "/{$public->id}/{$deptid}", ['id' => $deptid]);
    echo "  ✓ Created: $dept (id=$deptid) under Public\n";
}

// ── Step 3: Assign users to Airpay via local_userdata ───
echo "\n--- Assigning users via local_userdata ---\n";
$airpay_path = '/' . $airpay->id;

$allusers = $DB->get_records_select('user', 'deleted = 0 AND id > 1', null, 'id ASC');
$assigned = 0;
foreach ($allusers as $u) {
    $existing = $DB->get_record('local_userdata', ['userid' => $u->id]);
    if ($existing) {
        if (empty($existing->costcenterpath)) {
            $existing->costcenterpath = $airpay_path;
            $existing->timemodified = $now;
            $DB->update_record('local_userdata', $existing);
            $assigned++;
        }
    } else {
        $DB->insert_record('local_userdata', (object)[
            'userid' => $u->id,
            'costcenterpath' => $airpay_path,
            'categorypath' => '',
            'usercreated' => 2,
            'timecreated' => $now,
            'usermodified' => 2,
            'timemodified' => $now,
        ]);
        $assigned++;
    }
}
echo "  ✓ Assigned $assigned users to Airpay (path=$airpay_path)\n";

// ── Step 4: Move courses to Airpay course category ──────
echo "\n--- Assigning courses to Airpay category ---\n";
$airpay_catid = (int)$airpay->category;
if ($airpay_catid > 0) {
    // Get all courses not in site course (id > 1).
    $courses = $DB->get_records_select('course', 'id > 1', null, 'id ASC');
    $moved = 0;
    foreach ($courses as $c) {
        // Check if course is already in an Airpay sub-category.
        $cat = $DB->get_record('course_categories', ['id' => $c->category]);
        if ($cat) {
            // If it's a top-level category, leave it — courses are already categorized.
            // BizLMS may scope by the costcenter's course category tree.
            // We need courses UNDER the Airpay category for them to show up.
            // Move only courses in generic categories.
            if ($c->category == 1) { // "Category 1" (default uncategorized)
                $DB->set_field('course', 'category', $airpay_catid, ['id' => $c->id]);
                $moved++;
            }
        }
    }
    echo "  ✓ Moved $moved courses from default category to Airpay (catid=$airpay_catid)\n";

    // Also make Airpay category the parent of our test course categories.
    $test_cats = $DB->get_records_select('course_categories',
        "id NOT IN (?, ?) AND parent = 0 AND id > 1",
        [$airpay_catid, (int)$public->category]);
    foreach ($test_cats as $tc) {
        $DB->set_field('course_categories', 'parent', $airpay_catid, ['id' => $tc->id]);
        $DB->set_field('course_categories', 'depth', 2, ['id' => $tc->id]);
        $DB->set_field('course_categories', 'path', "/$airpay_catid/{$tc->id}", ['id' => $tc->id]);
        echo "  ✓ Nested category '{$tc->name}' (id={$tc->id}) under Airpay\n";
    }
} else {
    echo "  ⚠ Airpay has no category set — skipping course assignment\n";
}

// ── Step 5: Summary ─────────────────────────────────────
echo "\n=== Summary ===\n";
$cc_count = $DB->count_records('local_costcenter');
$ud_count = $DB->count_records('local_userdata');
$user_count = $DB->count_records_select('user', 'deleted = 0 AND id > 1');
$course_count = $DB->count_records_select('course', 'id > 1');

echo "Costcenters (orgs + depts): $cc_count\n";
echo "Users in local_userdata: $ud_count\n";
echo "Total active users: $user_count\n";
echo "Total courses: $course_count\n";

// Count courses per category under Airpay.
if ($airpay_catid > 0) {
    $subcats = $DB->get_records('course_categories', ['parent' => $airpay_catid]);
    echo "\nAirpay course categories:\n";
    foreach ($subcats as $sc) {
        $ccount = $DB->count_records('course', ['category' => $sc->id]);
        echo "  {$sc->name}: $ccount courses\n";
    }
    $direct = $DB->count_records('course', ['category' => $airpay_catid]);
    echo "  (directly in Airpay root): $direct courses\n";
}

echo "\n✓ Done. Purge caches and test:\n";
echo "  - /local/costcenter/index.php (Manage Company)\n";
echo "  - /local/users/index.php (Manage Users)\n";
echo "  - /local/courses/courses.php (Manage Courses)\n";
echo "  - /local/search/allcourses.php (Catalog)\n";
