<?php
/**
 * airpay academy — Configure BizLMS costcenters (tenants) and assign superadmin.
 *
 * Mirrors production structure:
 *   - Airpay Payment Services Pvt Ltd (root, 9 business units)
 *   - Public (root, 1 business unit)
 *
 * Run: php local/airpay_pages/cli/setup_costcenters.php
 */
define('CLI_SCRIPT', true);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

global $DB, $CFG;

echo "=== airpay academy — Costcenter Setup ===\n\n";

// ── Step 1: Check current state ─────────────────────────
$existing = $DB->count_records('local_costcenter');
echo "Current costcenter records: $existing\n";

if ($existing > 0) {
    echo "Costcenters already exist. Listing:\n";
    $ccs = $DB->get_records('local_costcenter', null, 'id ASC');
    foreach ($ccs as $cc) {
        echo "  id={$cc->id} | parent={$cc->parentid} | {$cc->fullname} ({$cc->shortname})\n";
    }
    echo "\n";
} else {
    echo "No costcenters. Will create from scratch.\n\n";
}

// ── Step 2: Discover local_costcenter table columns ─────
$columns = $DB->get_columns('local_costcenter');
echo "Table columns: " . implode(', ', array_keys($columns)) . "\n\n";

// ── Step 3: Create root orgs if missing ─────────────────
$now = time();

// Check if Airpay root exists.
$airpay = $DB->get_record('local_costcenter', ['shortname' => 'airpay', 'parentid' => 0]);
if (!$airpay) {
    // Try by fullname.
    $airpay = $DB->get_record_select('local_costcenter', "parentid = 0 AND fullname LIKE '%Airpay%'");
}
if (!$airpay) {
    $rec = new stdClass();
    $rec->fullname = 'AIRPAY PAYMENT SERVICES PRIVATE LIMITED';
    $rec->shortname = 'airpay';
    $rec->parentid = 0;
    $rec->depth = 1;
    if (isset($columns['visible'])) $rec->visible = 1;
    if (isset($columns['timecreated'])) $rec->timecreated = $now;
    if (isset($columns['timemodified'])) $rec->timemodified = $now;
    if (isset($columns['usermodified'])) $rec->usermodified = 2;
    if (isset($columns['path'])) $rec->path = '';
    $airpayid = $DB->insert_record('local_costcenter', $rec);
    // Update path.
    if (isset($columns['path'])) {
        $DB->set_field('local_costcenter', 'path', '/' . $airpayid, ['id' => $airpayid]);
    }
    echo "✓ Created: AIRPAY PAYMENT SERVICES PRIVATE LIMITED (id=$airpayid)\n";
} else {
    $airpayid = $airpay->id;
    echo "  Exists: {$airpay->fullname} (id=$airpayid)\n";
}

// Check if Public root exists.
$public = $DB->get_record('local_costcenter', ['shortname' => 'public', 'parentid' => 0]);
if (!$public) {
    $public = $DB->get_record_select('local_costcenter', "parentid = 0 AND fullname LIKE '%Public%'");
}
if (!$public) {
    $rec = new stdClass();
    $rec->fullname = 'Public';
    $rec->shortname = 'public';
    $rec->parentid = 0;
    $rec->depth = 1;
    if (isset($columns['visible'])) $rec->visible = 1;
    if (isset($columns['timecreated'])) $rec->timecreated = $now;
    if (isset($columns['timemodified'])) $rec->timemodified = $now;
    if (isset($columns['usermodified'])) $rec->usermodified = 2;
    if (isset($columns['path'])) $rec->path = '';
    $publicid = $DB->insert_record('local_costcenter', $rec);
    if (isset($columns['path'])) {
        $DB->set_field('local_costcenter', 'path', '/' . $publicid, ['id' => $publicid]);
    }
    echo "✓ Created: Public (id=$publicid)\n";
} else {
    $publicid = $public->id;
    echo "  Exists: {$public->fullname} (id=$publicid)\n";
}

echo "\nRoot orgs: Airpay=$airpayid, Public=$publicid\n";

// ── Step 4: Set organization_shortname config ───────────
set_config('organization_shortname', 'airpay', 'local_users');
echo "✓ Set local_users/organization_shortname = airpay\n\n";

// ── Step 5: Discover how BizLMS stores user-org mapping ─
echo "=== User-Org Assignment Discovery ===\n";

// Check if user table has BizLMS columns.
$usercols = $DB->get_columns('user');
$bizlms_fields = ['open_costcenterid', 'open_departmentid', 'open_path',
                   'open_supervisorid', 'open_group', 'open_hrmsrole',
                   'open_employeeid', 'open_location'];
echo "BizLMS user columns present:\n";
$has_costcenter_col = false;
foreach ($bizlms_fields as $bf) {
    if (isset($usercols[$bf])) {
        echo "  ✓ $bf\n";
        if ($bf === 'open_costcenterid') $has_costcenter_col = true;
    }
}
if (!$has_costcenter_col) {
    echo "  ✗ open_costcenterid NOT in user table\n";
    echo "\nChecking user_info_field for BizLMS custom profile fields:\n";
    $fields = $DB->get_records('user_info_field', null, 'id ASC');
    foreach ($fields as $f) {
        echo "  id={$f->id} | shortname={$f->shortname} | name={$f->name}\n";
    }
}

// ── Step 6: Check local_userdata table ──────────────────
$tables = $DB->get_tables();
if (in_array('local_userdata', $tables)) {
    echo "\nlocal_userdata table exists. Columns:\n";
    $udcols = $DB->get_columns('local_userdata');
    echo "  " . implode(', ', array_keys($udcols)) . "\n";
    $udcount = $DB->count_records('local_userdata');
    echo "  Records: $udcount\n";
}

// ── Step 7: Assign superadmin to Airpay ─────────────────
echo "\n=== Assigning superadmin to Airpay ===\n";
$admin = $DB->get_record('user', ['username' => 'superadmin']);
if (!$admin) {
    echo "superadmin user not found!\n";
    exit(1);
}

if ($has_costcenter_col) {
    $DB->execute("UPDATE {user} SET open_costcenterid = ?, open_path = ? WHERE id = ?",
        [$airpayid, '/' . $airpayid, $admin->id]);
    echo "✓ Set superadmin open_costcenterid=$airpayid via user table column\n";
} else {
    // Try profile field approach.
    $ccfield = $DB->get_record('user_info_field', ['shortname' => 'costcenterid']);
    if ($ccfield) {
        $existing = $DB->get_record('user_info_data', ['userid' => $admin->id, 'fieldid' => $ccfield->id]);
        if ($existing) {
            $existing->data = (string)$airpayid;
            $DB->update_record('user_info_data', $existing);
        } else {
            $DB->insert_record('user_info_data', (object)[
                'userid' => $admin->id, 'fieldid' => $ccfield->id,
                'data' => (string)$airpayid, 'dataformat' => 0
            ]);
        }
        echo "✓ Set costcenterid=$airpayid via user_info_data\n";
    } else {
        echo "⚠ Cannot assign — no open_costcenterid column and no costcenterid profile field.\n";
        echo "  BizLMS may need admin UI assignment at: /local/costcenter/index.php\n";
        echo "  OR the BizLMS plugins need a DB upgrade.\n";
    }
}

// ── Step 8: Also assign all test users ──────────────────
if ($has_costcenter_col) {
    $result = $DB->execute(
        "UPDATE {user} SET open_costcenterid = ?, open_path = ?
         WHERE deleted = 0 AND id > 1
           AND (open_costcenterid IS NULL OR open_costcenterid = 0)",
        [$airpayid, '/' . $airpayid]
    );
    echo "✓ Assigned all unassigned users to Airpay org\n";
}

// ── Step 9: Make superadmin a site admin ────────────────
echo "\n=== Verifying site admin status ===\n";
$admins = explode(',', $CFG->siteadmins);
if (in_array($admin->id, $admins)) {
    echo "✓ superadmin (id={$admin->id}) IS in \$CFG->siteadmins\n";
} else {
    echo "⚠ superadmin (id={$admin->id}) is NOT in \$CFG->siteadmins: {$CFG->siteadmins}\n";
    echo "  Adding...\n";
    $admins[] = $admin->id;
    set_config('siteadmins', implode(',', array_unique($admins)));
    echo "✓ Added to siteadmins\n";
}

// ── Step 10: Summary ────────────────────────────────────
echo "\n=== Summary ===\n";
echo "Costcenters: Airpay(id=$airpayid), Public(id=$publicid)\n";
echo "Superadmin: id={$admin->id}, username={$admin->username}\n";
echo "organization_shortname: " . get_config('local_users', 'organization_shortname') . "\n";
echo "\nNext: Log out, purge caches, log back in as superadmin.\n";
echo "If Manage Users/Courses still empty, assign superadmin to org at:\n";
echo "  http://localhost:8080/moodle/local/costcenter/index.php\n";
echo "\nDone.\n";
