<?php
/**
 * Fix test_manager role — remove local/courses:manage so they get manager dashboard, not admin.
 * On production, Manager/HRBP roles would have restricted capabilities.
 * This script simulates that by removing admin-level capabilities from test_manager.
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
global $DB;

$manager = $DB->get_record('user', ['username' => 'test_manager'], '*', MUST_EXIST);
echo "User: {$manager->firstname} {$manager->lastname} (id={$manager->id})\n";

// Remove the system-level manager role assignment
$systemcontext = context_system::instance();
$roleassignments = $DB->get_records('role_assignments', [
    'userid' => $manager->id,
    'contextid' => $systemcontext->id,
    'roleid' => 1, // manager
]);

if (!empty($roleassignments)) {
    foreach ($roleassignments as $ra) {
        $DB->delete_records('role_assignments', ['id' => $ra->id]);
    }
    echo "Removed system-level manager role\n";
}

// Assign a more limited role — use 'user' role (id=7) which has basic authenticated capabilities
// This simulates a production HRBP who can view reports but not manage courses
role_assign(7, $manager->id, $systemcontext->id); // 'user' role at system level
echo "Assigned 'user' role at system level\n";

// Grant specific capabilities that a manager/HRBP needs
$caps = [
    'moodle/site:viewreports',
    'local/myteam:approve_myteam_request_record',
];
foreach ($caps as $cap) {
    assign_capability($cap, CAP_ALLOW, 7, $systemcontext->id, true);
    echo "Granted: $cap\n";
}

echo "\nDone. test_manager now has HRBP-level access (viewreports + team approve).\n";
echo "They should see: team dashboard + learner sections\n";
echo "They should NOT see: admin KPIs, charts, quick nav, system health\n";
