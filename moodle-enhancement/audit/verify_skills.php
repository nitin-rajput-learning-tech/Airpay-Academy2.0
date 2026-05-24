<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

echo "=== Skills CRUD test ===\n";

// Web services
$funcs = $DB->get_records_select('external_functions',
    "name LIKE 'local_airpay_skills_%'", [], 'name ASC', 'name');
foreach ($funcs as $f) echo "  service: {$f->name}\n";

// Create category
$catid = \local_airpay_skills\skills_manager::create_category((object) [
    'name' => 'Claude Test Cat',
    'description' => 'Auto-test category',
    'icon' => 'fa-shield',
    'color' => '#ff6600',
    'sort_order' => 99,
]);
echo "  Created category id=$catid\n";

$c = $DB->get_record('local_airpay_skill_cats', ['id' => $catid]);
echo "  name={$c->name} icon={$c->icon} color={$c->color}\n";

// Create skill in that category
$skillid = \local_airpay_skills\skills_manager::create_skill((object) [
    'name' => 'Claude Test Skill',
    'description' => 'Auto-test skill',
    'categoryid' => $catid,
    'max_level' => 4,
    'sort_order' => 1,
]);
echo "  Created skill id=$skillid\n";

$s = $DB->get_record('local_airpay_skills', ['id' => $skillid]);
echo "  name={$s->name} categoryid={$s->categoryid} max_level={$s->max_level}\n";

// Test category-in-use protection
try {
    \local_airpay_skills\skills_manager::delete_category($catid);
    echo "  ERROR: deleted category even though it has a skill (BUG)\n";
} catch (\Throwable $e) {
    echo "  Category-in-use protection works: " . substr($e->getMessage(), 0, 60) . "\n";
}

// Update skill
\local_airpay_skills\skills_manager::update_skill($skillid, (object) [
    'name' => 'Claude Test Skill (renamed)',
    'max_level' => 5,
]);
$s = $DB->get_record('local_airpay_skills', ['id' => $skillid]);
echo "  After update: name={$s->name} max_level={$s->max_level}\n";

// Delete skill
\local_airpay_skills\skills_manager::delete_skill($skillid);
$s = $DB->get_record('local_airpay_skills', ['id' => $skillid]);
echo "  After skill delete: " . ($s ? 'STILL EXISTS' : 'gone') . "\n";

// Now category can be deleted
\local_airpay_skills\skills_manager::delete_category($catid);
$c = $DB->get_record('local_airpay_skill_cats', ['id' => $catid]);
echo "  After cat delete: " . ($c ? 'STILL EXISTS' : 'gone') . "\n";

// Validation
echo "\n=== Validation ===\n";
try {
    \local_airpay_skills\skills_manager::create_skill((object) [
        'name' => 'Bad', 'categoryid' => 999999,
    ]);
    echo "  invalid category: NOT REJECTED (BUG)\n";
} catch (\Throwable $e) {
    echo "  invalid category rejected: ok\n";
}
