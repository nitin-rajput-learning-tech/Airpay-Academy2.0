<?php
/**
 * Airpay Academy — Create test users for multi-role dashboard testing.
 *
 * Creates: Employee, Manager, Admin, External learner
 * Enrols employee in test courses with progress
 *
 * Usage: php local/sentientia_pages/cli/seed_users.php
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

global $DB;

// Check if already seeded.
$existing = $DB->record_exists('user', ['username' => 'test_employee']);
if ($existing) {
    echo "Test users already exist. Skipping.\n";
    echo "Users: test_employee, test_manager, test_admin, test_external\n";
    echo "Password for all: Airpay@2026\n";
    exit(0);
}

$users = [
    [
        'username'  => 'test_employee',
        'firstname' => 'Priya',
        'lastname'  => 'Singh',
        'email'     => 'priya.singh@airpay.co.in',
        'role'      => 'employee',   // BizLMS employee = Moodle student
        'roleid'    => 5,            // student
        'desc'      => 'Office employee — sees learner dashboard',
    ],
    [
        'username'  => 'test_manager',
        'firstname' => 'Vikram',
        'lastname'  => 'Sharma',
        'email'     => 'vikram.sharma@airpay.co.in',
        'role'      => 'manager',
        'roleid'    => 1,            // manager
        'desc'      => 'HRBP/Manager — sees team + own learning',
    ],
    [
        'username'  => 'test_admin',
        'firstname' => 'Amit',
        'lastname'  => 'Patel',
        'email'     => 'amit.patel@airpay.co.in',
        'role'      => 'admin',
        'roleid'    => 1,            // manager (+ admin capabilities)
        'desc'      => 'L&D Admin — sees admin dashboard with KPIs',
    ],
    [
        'username'  => 'test_external',
        'firstname' => 'Deepa',
        'lastname'  => 'Menon',
        'email'     => 'deepa.menon@gmail.com',
        'role'      => 'external',
        'roleid'    => 5,            // student
        'desc'      => 'External learner — public marketplace tenant',
    ],
];

$enrolplugin = enrol_get_plugin('manual');
$now = time();

foreach ($users as $udata) {
    // Create user.
    $user = new stdClass();
    $user->username     = $udata['username'];
    $user->firstname    = $udata['firstname'];
    $user->lastname     = $udata['lastname'];
    $user->email        = $udata['email'];
    $user->auth         = 'manual';
    $user->confirmed    = 1;
    $user->mnethostid   = $CFG->mnet_localhost_id;
    $user->password     = hash_internal_user_password('Airpay@2026');
    $user->timecreated  = $now;
    $user->timemodified  = $now;

    $userid = user_create_user($user, false, false);
    echo "Created user: {$udata['username']} (id=$userid) — {$udata['desc']}\n";

    // Assign system-level role for admin/manager.
    $systemcontext = context_system::instance();
    if ($udata['role'] === 'admin') {
        role_assign(1, $userid, $systemcontext->id); // manager at system level
        // Also assign local/courses:manage capability
        assign_capability('local/courses:manage', CAP_ALLOW, 1, $systemcontext->id, true);
        assign_capability('local/users:manage', CAP_ALLOW, 1, $systemcontext->id, true);
        echo "  Assigned admin capabilities (local/courses:manage, local/users:manage)\n";
    } else if ($udata['role'] === 'manager') {
        role_assign(1, $userid, $systemcontext->id); // manager at system level
        echo "  Assigned manager role at system level\n";
    }

    // Enrol employee and external in test courses.
    if ($udata['role'] === 'employee' || $udata['role'] === 'external') {
        $testcourses = $DB->get_records_select('course', "shortname LIKE 'APTEST%'", [], '', 'id,shortname');
        $enrollcount = 0;
        foreach ($testcourses as $course) {
            $enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MISSING);
            if ($enrolinstance) {
                $enrolplugin->enrol_user($enrolinstance, $userid, 5, $now - (7 * 86400));
                $enrollcount++;
            }
        }
        echo "  Enrolled in $enrollcount test courses\n";

        // Set some completion for employee.
        if ($udata['role'] === 'employee') {
            $completedcourses = $DB->get_records_select('course', "shortname IN ('APTEST-PROD', 'APTEST-DPE', 'APTEST-ITSEC')", [], '', 'id');
            foreach ($completedcourses as $cc) {
                $completion = new stdClass();
                $completion->userid = $userid;
                $completion->course = $cc->id;
                $completion->timecompleted = $now - rand(86400, 20 * 86400);
                $completion->timeenrolled = $now - (14 * 86400);
                $completion->timestarted = $now - (10 * 86400);
                $completion->reaggregate = 0;
                if (!$DB->record_exists('course_completions', ['userid' => $userid, 'course' => $cc->id])) {
                    $DB->insert_record('course_completions', $completion);
                }
            }
            echo "  Set 3 courses as completed\n";

            // Create certificates for employee.
            foreach ($completedcourses as $cc) {
                $coursename = $DB->get_field('course', 'shortname', ['id' => $cc->id]);
                $certrecord = new stdClass();
                $certrecord->userid = $userid;
                $certrecord->templateid = 1;
                $certrecord->code = 'APAC-' . date('Y') . '-' . strtoupper(substr($coursename, 7, 4)) . '-' . str_pad(rand(1, 999), 5, '0', STR_PAD_LEFT);
                $certrecord->emailed = 0;
                $certrecord->timecreated = $now - rand(86400, 15 * 86400);
                $certrecord->component = 'mod_coursecertificate';
                $certrecord->courseid = $cc->id;
                $certrecord->archived = 0;
                $DB->insert_record('tool_certificate_issues', $certrecord);
            }
            echo "  Created 3 certificates\n";
        }
    }
}

echo "\n=== USER SEED COMPLETE ===\n";
echo "Password for ALL test users: Airpay@2026\n";
echo "\nTest accounts:\n";
echo "  test_employee  — Priya Singh   — Learner dashboard\n";
echo "  test_manager   — Vikram Sharma — Manager dashboard\n";
echo "  test_admin     — Amit Patel    — Admin dashboard\n";
echo "  test_external  — Deepa Menon   — External learner\n";
echo "\nLogin as each to test role-specific dashboard views.\n";
