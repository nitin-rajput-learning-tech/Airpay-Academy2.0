<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;
$dbman = $DB->get_manager();

echo "=== Programs tables ===\n";
foreach (['local_airpay_programs', 'local_airpay_programs_levels',
          'local_airpay_programs_courses', 'local_airpay_programs_users'] as $t) {
    echo "  $t: " . ($dbman->table_exists($t) ? 'EXISTS' : 'MISSING') . "\n";
}

echo "\n=== Web services ===\n";
$funcs = $DB->get_records_select('external_functions',
    "name LIKE 'local_airpay_programs_%' OR name LIKE 'local_airpay_learningpath_%'",
    [], 'name ASC', 'name, classname');
foreach ($funcs as $f) {
    echo "  {$f->name}\n";
}

echo "\n=== Learning Path test (CRUD) ===\n";
$pathid = \local_airpay_learningpath\path_manager::create((object) [
    'name' => 'Claude Test Path',
    'description' => 'Test description',
    'costcenterid' => 0,
]);
echo "  Created path id=$pathid\n";

$row = $DB->get_record('local_airpay_learningpath', ['id' => $pathid]);
echo "  name={$row->name} status={$row->status}\n";

\local_airpay_learningpath\path_manager::toggle_status($pathid, false);
$row = $DB->get_record('local_airpay_learningpath', ['id' => $pathid]);
echo "  After archive: status={$row->status}\n";

\local_airpay_learningpath\path_manager::delete($pathid);
$row = $DB->get_record('local_airpay_learningpath', ['id' => $pathid]);
echo "  After delete: " . ($row ? 'STILL EXISTS' : 'gone') . "\n";

echo "\n=== Program test (CRUD) ===\n";
$progid = \local_airpay_programs\program_manager::create((object) [
    'name' => 'Claude Test Certification',
    'description' => 'Multi-level test program',
    'costcenterid' => 0,
    'completion_required' => 1,
]);
echo "  Created program id=$progid\n";

$row = $DB->get_record('local_airpay_programs', ['id' => $progid]);
echo "  name={$row->name} status={$row->status} completion_required={$row->completion_required}\n";

\local_airpay_programs\program_manager::change_status($progid, 1);
$row = $DB->get_record('local_airpay_programs', ['id' => $progid]);
echo "  After publish (status=1): status={$row->status}\n";

\local_airpay_programs\program_manager::delete($progid);
$row = $DB->get_record('local_airpay_programs', ['id' => $progid]);
echo "  After delete: " . ($row ? 'STILL EXISTS' : 'gone') . "\n";
