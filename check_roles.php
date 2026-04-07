<?php
define('CLI_SCRIPT', true);
require_once('C:\xampp\htdocs\moodle\config.php');
global $DB;

echo "=== ROLES ===\n";
$roles = $DB->get_records('role', [], 'id', 'id,shortname,archetype');
foreach ($roles as $r) {
    echo "{$r->id} | {$r->shortname} | {$r->archetype}\n";
}

echo "\n=== USERS (first 10 non-guest) ===\n";
$users = $DB->get_records_sql("SELECT id, username, firstname, lastname, email FROM {user} WHERE deleted = 0 AND id > 1 ORDER BY id LIMIT 10");
foreach ($users as $u) {
    echo "{$u->id} | {$u->username} | {$u->firstname} {$u->lastname} | {$u->email}\n";
}

echo "\n=== COSTCENTER FIELD ===\n";
$field = $DB->get_record('user_info_field', ['shortname' => 'costcenterid'], '*', IGNORE_MISSING);
if ($field) {
    echo "Field exists: id={$field->id}, name={$field->name}\n";
} else {
    echo "No costcenterid profile field found\n";
    // Check what profile fields exist
    $fields = $DB->get_records('user_info_field', [], 'id', 'id,shortname,name');
    foreach ($fields as $f) {
        echo "  Profile field: {$f->id} | {$f->shortname} | {$f->name}\n";
    }
}

echo "\n=== COSTCENTERS ===\n";
$ccs = $DB->get_records_sql("SELECT id, fullname, shortname FROM {local_costcenter} ORDER BY id LIMIT 10");
foreach ($ccs as $cc) {
    echo "{$cc->id} | {$cc->fullname} | {$cc->shortname}\n";
}

echo "\n=== CAPABILITIES for role detection ===\n";
$caps = ['local/courses:manage', 'local/users:manage', 'moodle/site:config', 'local/costcenter:manage'];
foreach ($caps as $cap) {
    $exists = $DB->record_exists('capabilities', ['name' => $cap]);
    echo "$cap: " . ($exists ? 'EXISTS' : 'NOT FOUND') . "\n";
}
