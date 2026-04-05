<?php
/**
 * Seed production-realistic data for UI testing.
 * Creates 20 courses across 5 categories, 10 employees with varied progress,
 * certificates, deadlines, activity logs, and manager relationships.
 *
 * Run: php local/airpay_pages/cli/seed_production_data.php
 * Safe to run multiple times — checks for existing data.
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');

global $DB, $CFG;

echo "=== AIRPAY ACADEMY — PRODUCTION DATA SEED ===\n\n";

// Check if already seeded with production data.
$existing = $DB->count_records_select('course', "shortname LIKE 'AP-%'");
if ($existing >= 15) {
    echo "Production data already exists ($existing courses with AP- prefix). Skipping.\n";
    echo "To re-seed, delete courses with shortname starting with 'AP-'.\n";
    exit(0);
}

$now = time();
$enrolplugin = enrol_get_plugin('manual');

// ═══ STEP 1: Create Categories ═══
echo "1. Creating categories...\n";
$categories = [
    'Compliance & Regulatory' => null,
    'Technology & IT' => null,
    'Business Skills' => null,
    'Financial Education' => null,
    'Soft Skills & Leadership' => null,
];
foreach ($categories as $name => &$catid) {
    $existing = $DB->get_record('course_categories', ['name' => $name], 'id');
    if ($existing) {
        $catid = $existing->id;
    } else {
        $cat = core_course_category::create((object)[
            'name' => $name, 'parent' => 0, 'visible' => 1, 'sortorder' => 999
        ]);
        $catid = $cat->id;
    }
    echo "   Category: $name (id=$catid)\n";
}
unset($catid);

// ═══ STEP 2: Create 20 Realistic Courses ═══
echo "\n2. Creating courses...\n";
$courses = [
    // Compliance (mandatory, with deadlines)
    ['short' => 'AP-POSH', 'full' => 'POSH Training — Prevention of Sexual Harassment', 'cat' => 'Compliance & Regulatory', 'days' => 10, 'summary' => 'Mandatory compliance training covering the POSH Act 2013, types of harassment, reporting mechanisms, ICC formation, and redressal procedures.'],
    ['short' => 'AP-AML', 'full' => 'Anti Money Laundering & KYC Compliance', 'cat' => 'Compliance & Regulatory', 'days' => 15, 'summary' => 'AML/CFT framework, KYC norms, suspicious transaction reporting, PMLA Act provisions, customer due diligence, and RBI guidelines.'],
    ['short' => 'AP-DPDP', 'full' => 'Data Protection & DPDP Act 2023', 'cat' => 'Compliance & Regulatory', 'days' => 20, 'summary' => 'Digital Personal Data Protection Act 2023, consent management, data principal rights, data fiduciary obligations, and cross-border data transfer.'],
    ['short' => 'AP-FRAUD', 'full' => 'Fraud Prevention & Detection', 'cat' => 'Compliance & Regulatory', 'days' => 25, 'summary' => 'Types of payment fraud, detection techniques, transaction monitoring, chargeback management, and merchant risk assessment.'],

    // Technology
    ['short' => 'AP-ITSEC', 'full' => 'IT and Information Security Awareness', 'cat' => 'Technology & IT', 'days' => 12, 'summary' => 'Information security best practices, phishing awareness, password hygiene, data classification, incident reporting, and safe browsing.'],
    ['short' => 'AP-CYBER', 'full' => 'Cybersecurity Fundamentals', 'cat' => 'Technology & IT', 'days' => 0, 'summary' => 'Network security, encryption, firewall basics, VPN usage, social engineering attacks, and enterprise security architecture.'],
    ['short' => 'AP-CLOUD', 'full' => 'Cloud Computing Essentials', 'cat' => 'Technology & IT', 'days' => 0, 'summary' => 'AWS, Azure, GCP overview, cloud deployment models, SaaS/PaaS/IaaS, cloud security, and migration strategies.'],
    ['short' => 'AP-API', 'full' => 'API Integration & Payment Gateway Architecture', 'cat' => 'Technology & IT', 'days' => 0, 'summary' => 'REST APIs, webhook integration, payment gateway architecture, settlement flows, and Airpay API documentation.'],

    // Business Skills
    ['short' => 'AP-PROD', 'full' => 'Airpay Product Training', 'cat' => 'Business Skills', 'days' => 0, 'summary' => 'Understanding Airpay payment gateway, merchant onboarding, QR code payments, settlement process, and customer support workflows.'],
    ['short' => 'AP-DPE', 'full' => 'Digital Payments Ecosystem', 'cat' => 'Business Skills', 'days' => 0, 'summary' => 'UPI, wallets, AEPS, BBPS, QR codes, contactless payments, RBI regulations, NPCI infrastructure, and emerging fintech trends.'],
    ['short' => 'AP-BC', 'full' => 'Business Correspondent Training', 'cat' => 'Business Skills', 'days' => 30, 'summary' => 'Financial inclusion, BC model, AEPS operations, micro-ATM usage, KYC process, and rural banking services.'],
    ['short' => 'AP-SALES', 'full' => 'Sales Fundamentals', 'cat' => 'Business Skills', 'days' => 0, 'summary' => 'Consultative selling, objection handling, pipeline management, customer engagement, closing techniques, and CRM usage.'],

    // Financial Education
    ['short' => 'AP-FIN', 'full' => 'Personal Finance & Wellness', 'cat' => 'Financial Education', 'days' => 0, 'summary' => 'Budgeting, savings strategies, debt management, tax planning, insurance basics, and retirement planning for employees.'],
    ['short' => 'AP-INVEST', 'full' => 'Investment Fundamentals', 'cat' => 'Financial Education', 'days' => 0, 'summary' => 'Stock market basics, mutual funds, SIP, risk-return analysis, portfolio diversification, and regulatory environment.'],
    ['short' => 'AP-BANK', 'full' => 'Introduction to Banking & Financial Services', 'cat' => 'Financial Education', 'days' => 0, 'summary' => 'Account types, lending products, insurance, wealth management, and digital banking trends.'],
    ['short' => 'AP-TAX', 'full' => 'Tax Planning for Employees', 'cat' => 'Financial Education', 'days' => 0, 'summary' => 'Income tax slabs, Section 80C deductions, HRA, NPS, ELSS, and tax-saving investment strategies.'],

    // Soft Skills
    ['short' => 'AP-COMM', 'full' => 'Communication & Personality Development', 'cat' => 'Soft Skills & Leadership', 'days' => 0, 'summary' => 'Business communication, email etiquette, presentation skills, active listening, and professional development.'],
    ['short' => 'AP-LEAD', 'full' => 'Leadership Essentials', 'cat' => 'Soft Skills & Leadership', 'days' => 0, 'summary' => 'First-time manager toolkit: delegation, feedback, coaching, team dynamics, conflict resolution, and performance conversations.'],
    ['short' => 'AP-TIME', 'full' => 'Time Management & Productivity', 'cat' => 'Soft Skills & Leadership', 'days' => 0, 'summary' => 'Prioritization strategies, Eisenhower matrix, Pomodoro technique, calendar management, and avoiding procrastination.'],
    ['short' => 'AP-STRESS', 'full' => 'Stress Management & Resilience', 'cat' => 'Soft Skills & Leadership', 'days' => 0, 'summary' => 'Identifying stressors, coping mechanisms, mindfulness, work-life balance, and building resilience in high-pressure environments.'],
];

$courseids = [];
foreach ($courses as $cdata) {
    $existingcourse = $DB->get_record('course', ['shortname' => $cdata['short']], 'id');
    if ($existingcourse) {
        $courseids[$cdata['short']] = $existingcourse->id;
        echo "   EXISTS: {$cdata['short']} (id={$existingcourse->id})\n";
        continue;
    }

    $catid = $categories[$cdata['cat']];
    $courseobj = new stdClass();
    $courseobj->category = $catid;
    $courseobj->shortname = $cdata['short'];
    $courseobj->fullname = $cdata['full'];
    $courseobj->summary = $cdata['summary'];
    $courseobj->summaryformat = FORMAT_HTML;
    $courseobj->format = 'topics';
    $courseobj->numsections = 5;
    $courseobj->visible = 1;
    $courseobj->enablecompletion = 1;
    $courseobj->startdate = $now - (60 * 86400);
    $courseobj->enddate = $cdata['days'] > 0 ? ($now + ($cdata['days'] * 86400)) : 0;

    $course = create_course($courseobj);
    $courseids[$cdata['short']] = $course->id;
    echo "   CREATED: {$cdata['short']} (id={$course->id})" . ($cdata['days'] > 0 ? " [deadline: {$cdata['days']}d]" : '') . "\n";
}

// ═══ STEP 3: Create 10 Employees ═══
echo "\n3. Creating employees...\n";
$employees = [
    ['user' => 'emp_priya', 'first' => 'Priya', 'last' => 'Singh', 'email' => 'priya.singh@airpay.co.in', 'dept' => 'Operations', 'desig' => 'Associate', 'empid' => 'AP-2024-0847'],
    ['user' => 'emp_amit', 'first' => 'Amit', 'last' => 'Patel', 'email' => 'amit.patel@airpay.co.in', 'dept' => 'Technology', 'desig' => 'Senior Developer', 'empid' => 'AP-2023-0512'],
    ['user' => 'emp_rajesh', 'first' => 'Rajesh', 'last' => 'Kumar', 'email' => 'rajesh.kumar@airpay.co.in', 'dept' => 'Sales', 'desig' => 'Area Sales Manager', 'empid' => 'AP-2024-1023'],
    ['user' => 'emp_anita', 'first' => 'Anita', 'last' => 'Joshi', 'email' => 'anita.joshi@airpay.co.in', 'dept' => 'Finance', 'desig' => 'Accounts Executive', 'empid' => 'AP-2023-0789'],
    ['user' => 'emp_deepa', 'first' => 'Deepa', 'last' => 'Menon', 'email' => 'deepa.menon@airpay.co.in', 'dept' => 'HR', 'desig' => 'HR Coordinator', 'empid' => 'AP-2024-0956'],
    ['user' => 'emp_sanjay', 'first' => 'Sanjay', 'last' => 'Gupta', 'email' => 'sanjay.gupta@airpay.co.in', 'dept' => 'Operations', 'desig' => 'Process Lead', 'empid' => 'AP-2022-0345'],
    ['user' => 'emp_neha', 'first' => 'Neha', 'last' => 'Sharma', 'email' => 'neha.sharma@airpay.co.in', 'dept' => 'Technology', 'desig' => 'QA Engineer', 'empid' => 'AP-2024-1156'],
    ['user' => 'emp_vikram', 'first' => 'Vikram', 'last' => 'Singh', 'email' => 'vikram.singh@airpay.co.in', 'dept' => 'Business Development', 'desig' => 'BD Executive', 'empid' => 'AP-2023-0678'],
    ['user' => 'emp_kavita', 'first' => 'Kavita', 'last' => 'Reddy', 'email' => 'kavita.reddy@airpay.co.in', 'dept' => 'Compliance', 'desig' => 'Compliance Analyst', 'empid' => 'AP-2024-0234'],
    ['user' => 'emp_rohit', 'first' => 'Rohit', 'last' => 'Verma', 'email' => 'rohit.verma@airpay.co.in', 'dept' => 'Sales', 'desig' => 'Sales Executive', 'empid' => 'AP-2024-1345'],
];

// Create a manager user.
$mgruser = $DB->get_record('user', ['username' => 'mgr_nitin']);
if (!$mgruser) {
    $mgr = new stdClass();
    $mgr->username = 'mgr_nitin';
    $mgr->firstname = 'Nitin';
    $mgr->lastname = 'Rajput';
    $mgr->email = 'nitin.rajput@airpay.co.in';
    $mgr->auth = 'manual';
    $mgr->confirmed = 1;
    $mgr->mnethostid = $CFG->mnet_localhost_id;
    $mgr->password = hash_internal_user_password('Airpay@2026');
    $mgr->open_employeeid = 'AP-2020-0001';
    $mgr->open_designation = 'Head of L&D';
    $mgr->timecreated = $now - (365 * 86400);
    $mgr->timemodified = $now;
    $mgrid = user_create_user($mgr, false, false);
    echo "   Created manager: mgr_nitin (id=$mgrid)\n";

    // Assign HRBP-level role.
    $systemcontext = context_system::instance();
    $hrbprole = $DB->get_record('role', ['shortname' => 'hrbp']);
    if ($hrbprole) {
        role_assign($hrbprole->id, $mgrid, $systemcontext->id);
    } else {
        // Use user role + viewreports if HRBP doesn't exist yet.
        role_assign(7, $mgrid, $systemcontext->id);
        assign_capability('moodle/site:viewreports', CAP_ALLOW, 7, $systemcontext->id, true);
    }
} else {
    $mgrid = $mgruser->id;
    echo "   Manager exists: mgr_nitin (id=$mgrid)\n";
}

$userids = [];
foreach ($employees as $empdata) {
    $existinguser = $DB->get_record('user', ['username' => $empdata['user']], 'id');
    if ($existinguser) {
        $userids[$empdata['user']] = $existinguser->id;
        echo "   EXISTS: {$empdata['user']} (id={$existinguser->id})\n";
        continue;
    }

    $user = new stdClass();
    $user->username = $empdata['user'];
    $user->firstname = $empdata['first'];
    $user->lastname = $empdata['last'];
    $user->email = $empdata['email'];
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->password = hash_internal_user_password('Airpay@2026');
    $user->open_employeeid = $empdata['empid'];
    $user->open_designation = $empdata['desig'];
    $user->open_supervisorid = $mgrid; // All report to Nitin.
    $user->timecreated = $now - rand(30, 365) * 86400;
    $user->timemodified = $now;
    $user->lastaccess = $now - rand(0, 7) * 86400;
    $user->lastlogin = $now - rand(0, 3) * 86400;

    $userid = user_create_user($user, false, false);
    $userids[$empdata['user']] = $userid;
    echo "   CREATED: {$empdata['user']} — {$empdata['first']} {$empdata['last']} (id=$userid)\n";
}

// ═══ STEP 4: Enrol Users in Courses with Varied Progress ═══
echo "\n4. Enrolling users and setting progress...\n";

// Each employee gets enrolled in 8-15 courses with different completion states.
$studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
$enrolcount = 0;
$completecount = 0;
$certcount = 0;

foreach ($userids as $username => $userid) {
    // Pick random 8-15 courses.
    $allshorts = array_keys($courseids);
    shuffle($allshorts);
    $mycourses = array_slice($allshorts, 0, rand(8, 15));

    foreach ($mycourses as $i => $short) {
        $cid = $courseids[$short];

        // Enrol.
        $enrolinstance = $DB->get_record('enrol', ['courseid' => $cid, 'enrol' => 'manual'], '*', IGNORE_MISSING);
        if (!$enrolinstance) {
            $course = get_course($cid);
            $enrolplugin->add_instance($course);
            $enrolinstance = $DB->get_record('enrol', ['courseid' => $cid, 'enrol' => 'manual']);
        }

        if (!is_enrolled(context_course::instance($cid), $userid)) {
            $enrolplugin->enrol_user($enrolinstance, $userid, $studentroleid, $now - rand(7, 60) * 86400);
            $enrolcount++;
        }

        // Set completion: first 40% completed, next 30% in-progress, rest not started.
        $progress = ($i < count($mycourses) * 0.4) ? 'completed' :
                    (($i < count($mycourses) * 0.7) ? 'inprogress' : 'notstarted');

        if ($progress === 'completed') {
            $cc = $DB->get_record('course_completions', ['userid' => $userid, 'course' => $cid]);
            if (!$cc) {
                $completion = new stdClass();
                $completion->userid = $userid;
                $completion->course = $cid;
                $completion->timecompleted = $now - rand(1, 45) * 86400;
                $completion->timeenrolled = $now - rand(46, 90) * 86400;
                $completion->timestarted = $now - rand(30, 60) * 86400;
                $completion->reaggregate = 0;
                $DB->insert_record('course_completions', $completion);
                $completecount++;

                // Create certificate for completed compliance courses.
                if (strpos($short, 'AP-POSH') !== false || strpos($short, 'AP-AML') !== false ||
                    strpos($short, 'AP-DPDP') !== false || strpos($short, 'AP-ITSEC') !== false ||
                    strpos($short, 'AP-PROD') !== false || strpos($short, 'AP-DPE') !== false) {
                    $certexists = $DB->record_exists('tool_certificate_issues', ['userid' => $userid, 'courseid' => $cid]);
                    if (!$certexists) {
                        $cert = new stdClass();
                        $cert->userid = $userid;
                        $cert->templateid = 1;
                        $cert->code = 'APAC-' . date('Y') . '-' . strtoupper(substr($short, 3)) . '-' . str_pad($userid, 5, '0', STR_PAD_LEFT);
                        $cert->emailed = 0;
                        $cert->timecreated = $now - rand(1, 30) * 86400;
                        $cert->component = 'mod_coursecertificate';
                        $cert->courseid = $cid;
                        $cert->archived = 0;
                        $DB->insert_record('tool_certificate_issues', $cert);
                        $certcount++;
                    }
                }
            }
        }
    }
    echo "   {$username}: enrolled in " . count($mycourses) . " courses\n";
}

// ═══ STEP 5: Add Activity Logs ═══
echo "\n5. Adding activity logs...\n";
$logcount = 0;
foreach ($userids as $username => $userid) {
    $mycourseids = $DB->get_fieldset_sql(
        "SELECT DISTINCT e.courseid FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = :uid",
        ['uid' => $userid]);

    foreach ($mycourseids as $cid) {
        if ($cid <= 1) continue;
        $ctx = context_course::instance($cid, IGNORE_MISSING);
        if (!$ctx) continue;

        // Enrolment log.
        $log = new stdClass();
        $log->eventname = '\\core\\event\\user_enrolment_created';
        $log->component = 'core';
        $log->action = 'created';
        $log->target = 'user_enrolment';
        $log->objecttable = 'user_enrolments';
        $log->objectid = 0;
        $log->crud = 'c';
        $log->edulevel = 0;
        $log->contextid = $ctx->id;
        $log->contextlevel = CONTEXT_COURSE;
        $log->contextinstanceid = $cid;
        $log->userid = $userid;
        $log->courseid = $cid;
        $log->relateduserid = $userid;
        $log->timecreated = $now - rand(7, 60) * 86400;
        $log->origin = 'cli';
        $log->ip = '127.0.0.1';
        $DB->insert_record('logstore_standard_log', $log);
        $logcount++;

        // Completion log for completed courses.
        $cc = $DB->get_record('course_completions', ['userid' => $userid, 'course' => $cid]);
        if ($cc && $cc->timecompleted) {
            $clog = clone $log;
            $clog->eventname = '\\core\\event\\course_completed';
            $clog->action = 'completed';
            $clog->target = 'course';
            $clog->timecreated = $cc->timecompleted;
            $DB->insert_record('logstore_standard_log', $clog);
            $logcount++;
        }
    }
}
echo "   Added $logcount log entries\n";

// ═══ STEP 6: Auto-accept policies for all new users ═══
echo "\n6. Accepting policies for new users...\n";
$policies = $DB->get_records('tool_policy', [], '', 'id,currentversionid');
$policycount = 0;
foreach ($userids as $username => $userid) {
    foreach ($policies as $policy) {
        if (empty($policy->currentversionid)) continue;
        if (!$DB->record_exists('tool_policy_acceptances', ['policyversionid' => $policy->currentversionid, 'userid' => $userid])) {
            $acc = new stdClass();
            $acc->policyversionid = $policy->currentversionid;
            $acc->userid = $userid;
            $acc->status = 1;
            $acc->lang = 'en';
            $acc->usermodified = $userid;
            $acc->timecreated = $now;
            $acc->timemodified = $now;
            $DB->insert_record('tool_policy_acceptances', $acc);
            $policycount++;
        }
    }
}
// Also for manager.
foreach ($policies as $policy) {
    if (empty($policy->currentversionid)) continue;
    if (!$DB->record_exists('tool_policy_acceptances', ['policyversionid' => $policy->currentversionid, 'userid' => $mgrid])) {
        $acc = new stdClass();
        $acc->policyversionid = $policy->currentversionid;
        $acc->userid = $mgrid;
        $acc->status = 1;
        $acc->lang = 'en';
        $acc->usermodified = $mgrid;
        $acc->timecreated = $now;
        $acc->timemodified = $now;
        $DB->insert_record('tool_policy_acceptances', $acc);
    }
}
echo "   Accepted $policycount policy consents\n";

echo "\n=== SEED COMPLETE ===\n";
echo "Courses: " . count($courseids) . " (5 categories)\n";
echo "Employees: " . count($userids) . " + 1 manager\n";
echo "Enrolments: $enrolcount\n";
echo "Completions: $completecount\n";
echo "Certificates: $certcount\n";
echo "Log entries: $logcount\n";
echo "\nAll employee passwords: Airpay@2026\n";
echo "Manager: mgr_nitin / Airpay@2026\n";
echo "\nLogin as emp_priya to see a populated learner dashboard.\n";
echo "Login as mgr_nitin to see manager team view with 10 team members.\n";
