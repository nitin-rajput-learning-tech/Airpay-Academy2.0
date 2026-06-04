<?php
/**
 * Airpay Academy — Seed test data for dashboard testing.
 *
 * Creates: 8 courses, enrols superadmin, sets progress/completion,
 * creates certificate records, and adds deadlines.
 *
 * Usage: php local/sentientia_pages/cli/seed_testdata.php
 *
 * Safe to run multiple times — checks for existing data before inserting.
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->libdir . '/completionlib.php');

global $DB, $USER;

// Use superadmin (id=2 typically, or whatever exists).
$adminuser = $DB->get_record('user', ['username' => 'superadmin'], '*', IGNORE_MISSING);
if (!$adminuser) {
    $adminuser = $DB->get_record_sql("SELECT * FROM {user} WHERE id > 1 AND deleted = 0 ORDER BY id ASC LIMIT 1");
}
if (!$adminuser) {
    cli_error('No admin user found');
}

echo "Using user: {$adminuser->firstname} {$adminuser->lastname} (id={$adminuser->id})\n";

// Check if we already seeded.
$existing = $DB->count_records_select('course', "shortname LIKE 'APTEST%'");
if ($existing > 0) {
    echo "Test data already exists ($existing test courses). Skipping.\n";
    echo "To re-seed, delete courses with shortname starting with 'APTEST' first.\n";
    exit(0);
}

// Get or create a test category.
$category = $DB->get_record('course_categories', ['name' => 'Airpay Test Courses'], '*', IGNORE_MISSING);
if (!$category) {
    $catdata = new stdClass();
    $catdata->name = 'Airpay Test Courses';
    $catdata->description = 'Test courses for dashboard development';
    $catdata->parent = 0;
    $catdata->sortorder = 999;
    $catdata->visible = 1;
    $catdata->timemodified = time();
    $category = core_course_category::create($catdata);
    $categoryid = $category->id;
    echo "Created category: Airpay Test Courses (id=$categoryid)\n";
} else {
    $categoryid = $category->id;
    echo "Using existing category: id=$categoryid\n";
}

// Course definitions — realistic Airpay training courses.
$courses = [
    ['shortname' => 'APTEST-POSH', 'fullname' => 'POSH Training — Prevention of Sexual Harassment',
     'summary' => 'Mandatory compliance training covering the POSH Act 2013, types of harassment, reporting mechanisms, and ICC procedures.',
     'enddate' => time() + (5 * 86400), 'progress' => 65],

    ['shortname' => 'APTEST-AML', 'fullname' => 'Anti Money Laundering Compliance',
     'summary' => 'Covers KYC norms, suspicious transaction reporting, PMLA Act provisions, and customer due diligence procedures.',
     'enddate' => time() + (10 * 86400), 'progress' => 30],

    ['shortname' => 'APTEST-ITSEC', 'fullname' => 'IT and Information Security Awareness',
     'summary' => 'Information security best practices, data protection, phishing awareness, password management, and incident reporting.',
     'enddate' => time() + (3 * 86400), 'progress' => 85],

    ['shortname' => 'APTEST-PROD', 'fullname' => 'Airpay Product Training',
     'summary' => 'Understanding Airpay payment gateway, merchant onboarding, settlement process, and customer support workflows.',
     'enddate' => 0, 'progress' => 100],

    ['shortname' => 'APTEST-DPE', 'fullname' => 'Digital Payments Ecosystem',
     'summary' => 'UPI, wallets, AEPS, BBPS, QR codes, contactless payments, RBI regulations, and emerging fintech trends.',
     'enddate' => 0, 'progress' => 100],

    ['shortname' => 'APTEST-COMM', 'fullname' => 'Communication and Personality Development',
     'summary' => 'Business communication, email etiquette, presentation skills, time management, and professional development.',
     'enddate' => time() + (15 * 86400), 'progress' => 0],

    ['shortname' => 'APTEST-LEAD', 'fullname' => 'Leadership Essentials',
     'summary' => 'First-time manager toolkit: delegation, feedback, coaching, team dynamics, and performance conversations.',
     'enddate' => 0, 'progress' => 0],

    ['shortname' => 'APTEST-SALES', 'fullname' => 'Sales Fundamentals',
     'summary' => 'Consultative selling, objection handling, pipeline management, customer engagement, and closing strategies.',
     'enddate' => time() + (20 * 86400), 'progress' => 0],
];

$enrolplugin = enrol_get_plugin('manual');
$now = time();
$createdcount = 0;

foreach ($courses as $cdata) {
    // Create the course.
    $courseobj = new stdClass();
    $courseobj->category = $categoryid;
    $courseobj->shortname = $cdata['shortname'];
    $courseobj->fullname = $cdata['fullname'];
    $courseobj->summary = $cdata['summary'];
    $courseobj->summaryformat = FORMAT_HTML;
    $courseobj->format = 'topics';
    $courseobj->numsections = 5;
    $courseobj->visible = 1;
    $courseobj->enablecompletion = 1;
    $courseobj->startdate = $now - (30 * 86400);
    $courseobj->enddate = $cdata['enddate'];

    $course = create_course($courseobj);
    echo "Created course: {$course->shortname} (id={$course->id})\n";

    // Enrol superadmin as student.
    $enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MISSING);
    if (!$enrolinstance) {
        $enrolid = $enrolplugin->add_instance($course);
        $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
    }
    $enrolplugin->enrol_user($enrolinstance, $adminuser->id, 5, $now - (14 * 86400)); // role 5 = student
    echo "  Enrolled user as student\n";

    // Set completion status.
    if ($cdata['progress'] >= 100) {
        // Mark as completed.
        $completion = new stdClass();
        $completion->userid = $adminuser->id;
        $completion->course = $course->id;
        $completion->timecompleted = $now - rand(86400, 30 * 86400);
        $completion->timeenrolled = $now - (14 * 86400);
        $completion->timestarted = $now - (10 * 86400);
        $completion->reaggregate = 0;

        $existingcc = $DB->get_record('course_completions', [
            'userid' => $adminuser->id,
            'course' => $course->id,
        ]);
        if (!$existingcc) {
            $DB->insert_record('course_completions', $completion);
        }
        echo "  Marked as completed\n";
    } else if ($cdata['progress'] > 0) {
        // Insert a partial completion record (started but not completed).
        $completion = new stdClass();
        $completion->userid = $adminuser->id;
        $completion->course = $course->id;
        $completion->timeenrolled = $now - (14 * 86400);
        $completion->timestarted = $now - (7 * 86400);
        $completion->reaggregate = 0;

        $existingcc = $DB->get_record('course_completions', [
            'userid' => $adminuser->id,
            'course' => $course->id,
        ]);
        if (!$existingcc) {
            $DB->insert_record('course_completions', $completion);
        }
        echo "  Set as in-progress ({$cdata['progress']}%)\n";
    }

    // Create certificate record for completed courses.
    if ($cdata['progress'] >= 100) {
        $certexists = $DB->record_exists('tool_certificate_issues', [
            'userid' => $adminuser->id,
            'courseid' => $course->id,
        ]);
        if (!$certexists) {
            $certrecord = new stdClass();
            $certrecord->userid = $adminuser->id;
            $certrecord->templateid = 1; // Default template (may not exist, but record is valid)
            $certrecord->code = 'APAC-' . date('Y') . '-' . strtoupper(substr($cdata['shortname'], 7, 4)) . '-' . str_pad(rand(1, 999), 5, '0', STR_PAD_LEFT);
            $certrecord->emailed = 0;
            $certrecord->timecreated = $now - rand(86400, 20 * 86400);
            $certrecord->component = 'mod_coursecertificate';
            $certrecord->courseid = $course->id;
            $certrecord->archived = 0;
            $DB->insert_record('tool_certificate_issues', $certrecord);
            echo "  Created certificate: {$certrecord->code}\n";
        }
    }

    // Add log entries for timeline.
    $logrecord = new stdClass();
    $logrecord->eventname = '\\core\\event\\user_enrolment_created';
    $logrecord->component = 'core';
    $logrecord->action = 'created';
    $logrecord->target = 'user_enrolment';
    $logrecord->objecttable = 'user_enrolments';
    $logrecord->objectid = 0;
    $logrecord->crud = 'c';
    $logrecord->edulevel = 0;
    $logrecord->contextid = context_course::instance($course->id)->id;
    $logrecord->contextlevel = CONTEXT_COURSE;
    $logrecord->contextinstanceid = $course->id;
    $logrecord->userid = $adminuser->id;
    $logrecord->courseid = $course->id;
    $logrecord->relateduserid = $adminuser->id;
    $logrecord->timecreated = $now - rand(86400, 14 * 86400);
    $logrecord->origin = 'cli';
    $logrecord->ip = '127.0.0.1';
    $DB->insert_record('logstore_standard_log', $logrecord);

    if ($cdata['progress'] >= 100) {
        $completelog = clone $logrecord;
        $completelog->eventname = '\\core\\event\\course_completed';
        $completelog->action = 'completed';
        $completelog->target = 'course';
        $completelog->timecreated = $now - rand(86400, 7 * 86400);
        $DB->insert_record('logstore_standard_log', $completelog);
    }

    $createdcount++;
}

echo "\n=== SEED COMPLETE ===\n";
echo "Created: $createdcount courses\n";
echo "Enrolled: user {$adminuser->username} in all courses\n";
echo "Certificates: " . $DB->count_records('tool_certificate_issues', ['userid' => $adminuser->id]) . "\n";
echo "Log entries: " . $DB->count_records('logstore_standard_log', ['userid' => $adminuser->id]) . " (approx)\n";
echo "\nPurge caches and refresh dashboard to see the data.\n";
