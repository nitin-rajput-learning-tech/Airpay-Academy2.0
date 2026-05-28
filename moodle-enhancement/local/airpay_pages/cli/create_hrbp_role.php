<?php
/**
 * Create a restricted "HRBP" role for manager-level users.
 * HRBP can: view reports, approve requests, view team, manage classrooms.
 * HRBP cannot: manage courses, manage users, manage exams, admin settings.
 *
 * Run: php local/airpay_pages/cli/create_hrbp_role.php
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
global $DB;

echo "=== Creating HRBP Role ===\n\n";

// Check if already exists.
$existing = $DB->get_record('role', ['shortname' => 'hrbp']);
if ($existing) {
    echo "HRBP role already exists (id={$existing->id}). Skipping creation.\n";
    exit(0);
}

// Create the role.
$roleid = create_role('HRBP / Manager', 'hrbp', 'HR Business Partner — team oversight, approvals, compliance monitoring. Can view reports and team data but cannot manage courses or users.', 'manager');
echo "Created role: HRBP (id=$roleid)\n";

// Assign capabilities.
$systemcontext = context_system::instance();
$capabilities = [
    // View capabilities
    'moodle/site:viewreports' => CAP_ALLOW,
    'local/costcenter:view' => CAP_ALLOW,
    'local/courses:view' => CAP_ALLOW,
    'local/courses:report_view' => CAP_ALLOW,
    'local/search:viewcatalog' => CAP_ALLOW,

    // Team management
    'local/myteam:approve_myteam_request_record' => CAP_ALLOW,

    // Requests
    'local/request:viewrecord' => CAP_ALLOW,
    'local/request:approverecord' => CAP_ALLOW,
    'local/request:addcomment' => CAP_ALLOW,

    // Classrooms (view + attendance)
    'local/classroom:viewusers' => CAP_ALLOW,
    'local/classroom:takesessionattendance' => CAP_ALLOW,

    // Notifications
    'local/notifications:view' => CAP_ALLOW,

    // Ratings
    'local/ratings:canrate' => CAP_ALLOW,

    // Skills (view only)
    'local/skillrepository:view_skill' => CAP_ALLOW,
    'local/skillrepository:view_level' => CAP_ALLOW,

    // Tags (view only)
    'local/tags:view' => CAP_ALLOW,

    // Cart
    'local/biz_cart:canbuy' => CAP_ALLOW,
    'local/biz_cart:history' => CAP_ALLOW,

    // DENY admin capabilities explicitly
    'local/courses:manage' => CAP_PREVENT,
    'local/courses:create' => CAP_PREVENT,
    'local/courses:delete' => CAP_PREVENT,
    'local/users:manage' => CAP_PREVENT,
    'local/users:create' => CAP_PREVENT,
    'local/users:delete' => CAP_PREVENT,
    'local/costcenter:manage' => CAP_PREVENT,
    'local/onlineexams:manage' => CAP_PREVENT,
];

foreach ($capabilities as $cap => $permission) {
    try {
        assign_capability($cap, $permission, $roleid, $systemcontext->id, true);
        $permstr = $permission == CAP_ALLOW ? 'ALLOW' : 'PREVENT';
        echo "  $permstr: $cap\n";
    } catch (\Exception $e) {
        echo "  SKIP: $cap (not found)\n";
    }
}

echo "\n=== HRBP ROLE CREATED ===\n";
echo "Role ID: $roleid\n";
echo "Shortname: hrbp\n";
echo "\nTo assign: Site Admin → Users → Define roles → Assign system roles\n";
echo "Or via CLI: role_assign($roleid, \$userid, \$systemcontext->id);\n";
