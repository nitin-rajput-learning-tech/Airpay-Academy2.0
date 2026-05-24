<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

echo "=== Notifications CRUD test ===\n";

// Web services
$funcs = $DB->get_records_select('external_functions',
    "name LIKE 'local_airpay_notifications_%'", [], 'name ASC', 'name');
foreach ($funcs as $f) {
    echo "  service: {$f->name}\n";
}

// CRUD
$id = \local_airpay_notifications\rule_manager::create((object) [
    'name' => 'Claude Test Rule',
    'rule_type' => 'deadline_approaching',
    'channel' => 'email',
    'trigger_days' => 5,
    'audience' => 'manager',
    'enabled' => 1,
    'template' => 'Test {{firstname}} - course {{coursename}} due in {{days}}',
]);
echo "  Created rule id=$id\n";

$r = $DB->get_record('local_airpay_notif_rules', ['id' => $id]);
echo "  name={$r->name} type={$r->rule_type} channel={$r->channel} audience={$r->audience} trigger_days={$r->trigger_days} enabled={$r->enabled}\n";

\local_airpay_notifications\rule_manager::update($id, (object) [
    'name' => 'Claude Test Rule (renamed)',
    'channel' => 'inapp',
    'trigger_days' => 7,
]);
$r = $DB->get_record('local_airpay_notif_rules', ['id' => $id]);
echo "  After update: name={$r->name} channel={$r->channel} trigger_days={$r->trigger_days}\n";

\local_airpay_notifications\rule_manager::toggle_enabled($id, false);
$r = $DB->get_record('local_airpay_notif_rules', ['id' => $id]);
echo "  After disable: enabled={$r->enabled}\n";

\local_airpay_notifications\rule_manager::delete($id);
$r = $DB->get_record('local_airpay_notif_rules', ['id' => $id]);
echo "  After delete: " . ($r ? 'STILL EXISTS' : 'gone') . "\n";

// Validation tests
echo "\n=== Validation tests ===\n";
try {
    \local_airpay_notifications\rule_manager::create((object) [
        'name' => 'Bad', 'rule_type' => 'invalid_type',
    ]);
    echo "  invalid type: NOT REJECTED (BUG)\n";
} catch (\Throwable $e) {
    echo "  invalid type rejected: ok\n";
}
try {
    \local_airpay_notifications\rule_manager::create((object) [
        'name' => 'Bad2', 'rule_type' => 'deadline_approaching', 'channel' => 'invalid_channel',
    ]);
    echo "  invalid channel: NOT REJECTED (BUG)\n";
} catch (\Throwable $e) {
    echo "  invalid channel rejected: ok\n";
}
