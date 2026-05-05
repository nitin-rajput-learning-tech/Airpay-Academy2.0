<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB, $USER;
$USER = $DB->get_record('user', ['id' => 2]);

echo "=== List WSes — call each, ensure no crash ===\n\n";

$tests = [
    ['local_airpay_classroom\external\list_classrooms', 'classrooms'],
    ['local_airpay_exams\external\list_exams', 'exams'],
    ['local_airpay_evaluation\external\list_evaluations', 'evaluations'],
    ['local_airpay_skills\external\list_skills', 'skills'],
    ['local_airpay_notifications\external\list_rules', 'rules'],
    ['local_airpay_programs\external\list_programs', 'programs'],
    ['local_airpay_learningpath\external\list_paths', 'paths'],
    ['local_airpay_reports\external\list_reports', 'reports'],
];

foreach ($tests as [$class, $label]) {
    try {
        $r = $class::execute('', 'name', 'asc', 0, 5, '{}');
        echo sprintf("    PASS %-12s total=%-5d returned=%d\n",
            $label, $r['total'], count($r['rows']));
    } catch (\Throwable $e) {
        echo sprintf("    FAIL %-12s %s\n", $label, $e->getMessage());
    }
}

echo "\nDone.\n";
