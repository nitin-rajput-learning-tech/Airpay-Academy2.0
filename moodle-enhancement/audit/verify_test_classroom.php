<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

$c = $DB->get_record_select('local_airpay_classroom',
    "name = :n", ['n' => 'Claude Test Training Room']);

if ($c) {
    echo "CLASSROOM CREATED!\n";
    echo "  id: {$c->id}\n";
    echo "  name: {$c->name}\n";
    echo "  description: {$c->description}\n";
    echo "  location: {$c->location}\n";
    echo "  capacity: {$c->capacity}\n";
    echo "  status: {$c->status}\n";
    echo "  visible: {$c->visible}\n";
    echo "  timecreated: " . date('Y-m-d H:i:s', $c->timecreated) . "\n";

    // Test status change → completed
    \local_airpay_classroom\session_manager::change_status($c->id, 2);
    $c2 = $DB->get_record('local_airpay_classroom', ['id' => $c->id]);
    echo "  [after status change to 2] status: {$c2->status}\n";

    // Test delete
    \local_airpay_classroom\session_manager::delete($c->id);
    $c3 = $DB->get_record('local_airpay_classroom', ['id' => $c->id]);
    echo "  [after delete] record exists: " . ($c3 ? 'YES — DELETE FAILED' : 'NO — delete worked') . "\n";
} else {
    echo "Classroom NOT found — create may have failed\n";
}
