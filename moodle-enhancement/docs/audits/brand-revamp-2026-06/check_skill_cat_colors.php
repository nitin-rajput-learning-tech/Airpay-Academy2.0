<?php
// Read-only diagnostic: dump local_sentientia_skill_cats colours.
// Run:  php check_skill_cat_colors.php
// Brand-revamp 2026-06 — verifies the seeded category-colour migration.
define('CLI_SCRIPT', true);
require('C:/xampp/htdocs/moodle5/config.php');

global $DB;
$rows = $DB->get_records('local_sentientia_skill_cats', null, 'sort_order ASC',
    'id, name, color, sort_order');

$offbrand = ['#0f7a73', '#7c3aed', '#ea580c'];
$bad = 0;
echo str_pad('id', 4) . str_pad('color', 12) . 'name' . PHP_EOL;
echo str_repeat('-', 50) . PHP_EOL;
foreach ($rows as $r) {
    $flag = in_array(strtolower($r->color), $offbrand, true) ? '  <-- OFF-BRAND' : '';
    if ($flag) { $bad++; }
    echo str_pad($r->id, 4) . str_pad($r->color, 12) . $r->name . $flag . PHP_EOL;
}
echo str_repeat('-', 50) . PHP_EOL;
echo "rows: " . count($rows) . " | off-brand: " . $bad . PHP_EOL;
