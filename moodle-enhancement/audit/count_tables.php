<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

$tables = [
    'local_onlinetests', 'local_classroom', 'local_classroom_sessions',
    'local_learningplan', 'local_program', 'local_program_levels',
    'local_evaluation', 'local_airpay_org',
];
$dbman = $DB->get_manager();
foreach ($tables as $t) {
    if ($dbman->table_exists($t)) {
        echo "$t: " . $DB->count_records($t) . "\n";
    } else {
        echo "$t: TABLE NOT FOUND\n";
    }
}
