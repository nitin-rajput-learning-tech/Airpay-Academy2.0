<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: seed what the Moodle 5.3 compatibility screenshots (docs/visual-evidence/2026-10-08/moodle53/README.md,
 * items 1 to 19) need on the local XAMPP site, and write .moodle53-evidence.local.json (ids only) for
 * moodle53_checks.mjs. Refuses unless wwwroot is localhost AND $CFG->noemailever is set. Never deletes anything.
 * Idempotent: every object is found again by name.
 *
 *   - "VP53 Dated Assignment" in course 448 (VP Journey Free Course, /1) with an Opened and a Due date, so the learner's
 *     activity page has the Opened / Due line (items 4, 14); the course's Page activity has no dates (the "no empty
 *     gap" half). vp_learner1 and vp_author1 are already enrolled there by the journey seed.
 *   - A pending course request by vp_learner1 for "VP53 Request Course" (/1), routed by the plugin's own rules, for the
 *     Approve / Reject dialogs (item 9). The approver is read back and written to the context file.
 *   - A tenant-/1 override of the first e-mail template (template_manager::save_override), so the live preview renders
 *     an override with the branded wrapper (item 5).
 * Nothing here turns a feature flag on.
 *
 *   php seed_moodle53_evidence.php     (run with cwd = moodle5/public, after provision_local_personas.php and
 *                                       seed_journey_content.php)
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $CFG->wwwroot)) {
    fwrite(STDERR, "Refusing: wwwroot {$CFG->wwwroot} is not a local development host.\n");
    exit(1);
}
if (empty($CFG->noemailever)) {
    fwrite(STDERR, "Refusing: \$CFG->noemailever is not set.\n");
    exit(1);
}

global $DB;
$ctx = ['generated' => date('c')];
$free = $DB->get_record('course', ['shortname' => 'vp_journey_free'], '*', MUST_EXIST);
$ctx['course'] = (int) $free->id;
$learner = $DB->get_record('user', ['username' => 'vp_learner1', 'deleted' => 0], '*', MUST_EXIST);
$admin = $DB->get_record('user', ['username' => 'vp_siteadmin', 'deleted' => 0], '*', MUST_EXIST);
$ctx['users'] = [];
foreach (['vp_learner1', 'vp_author1', 'vp_manager1', 'vp_admin1', 'vp_siteadmin', 'vp_courseauthor1'] as $u) {
    $ctx['users'][$u] = (int) $DB->get_field('user', 'id', ['username' => $u]);
}

// 0. An editing teacher. vp_author1 holds the 'trainer' role (teacher archetype, no course:update), so the edit-mode
// switch never shows for it; the course author persona is enrolled in the course as editingteacher instead (the
// manual enrolment plugin, a local test enrolment).
require_once($CFG->libdir . '/enrollib.php');
$author = $DB->get_record('user', ['username' => 'vp_courseauthor1', 'deleted' => 0], '*', MUST_EXIST);
$manual = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $free->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
if (!$instance) {
    $instance = $DB->get_record('enrol', ['id' => $manual->add_instance($free)], '*', MUST_EXIST);
}
$editingteacher = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
if (!is_enrolled(context_course::instance($free->id), $author->id)) {
    $manual->enrol_user($instance, $author->id, $editingteacher);
    echo "enrolled vp_courseauthor1 as editingteacher in course {$free->id}
";
} else {
    echo "vp_courseauthor1 already enrolled
";
}

// 1. A dated assignment.
$mod = $DB->get_record('modules', ['name' => 'assign'], '*', MUST_EXIST);
$cm = $DB->get_record_sql("SELECT cm.id, cm.instance FROM {course_modules} cm JOIN {assign} a ON a.id = cm.instance
    WHERE cm.course = :c AND cm.module = :m AND a.name = :n AND cm.deletioninprogress = 0",
    ['c' => $free->id, 'm' => $mod->id, 'n' => 'VP53 Dated Assignment'], IGNORE_MULTIPLE);
if (!$cm) {
    $mi = new stdClass();
    $mi->modulename = 'assign';
    $mi->module = $mod->id;
    $mi->course = $free->id;
    $mi->section = 1;
    $mi->visible = 1;
    $mi->visibleoncoursepage = 1;
    $mi->name = 'VP53 Dated Assignment';
    $mi->intro = '<p>Local evidence activity with an Opened and a Due date.</p>';
    $mi->introformat = FORMAT_HTML;
    $mi->alwaysshowdescription = 1;
    $mi->submissiondrafts = 0;
    $mi->requiresubmissionstatement = 0;
    $mi->sendnotifications = 0;
    $mi->sendlatenotifications = 0;
    $mi->sendstudentnotifications = 0;
    $mi->allowsubmissionsfromdate = time() - DAYSECS;
    $mi->duedate = time() + 7 * DAYSECS;
    $mi->cutoffdate = 0;
    $mi->gradingduedate = 0;
    $mi->grade = 100;
    $mi->teamsubmission = 0;
    $mi->requireallteammemberssubmit = 0;
    $mi->blindmarking = 0;
    $mi->hidegrader = 0;
    $mi->markingworkflow = 0;
    $mi->markingallocation = 0;
    $mi->preventsubmissionnotingroup = 0;
    $mi->maxattempts = -1;
    $mi->attemptreopenmethod = 'none';
    $mi->assignsubmission_onlinetext_enabled = 1;
    $mi->assignsubmission_file_enabled = 0;
    $mi->assignfeedback_comments_enabled = 1;
    $mi->cmidnumber = '';
    $mi->groupmode = 0;
    $mi->groupingid = 0;
    $mi->completion = 0;
    $mi = add_moduleinfo($mi, $free);
    $ctx['assign_cmid'] = (int) $mi->coursemodule;
    echo "created assignment cm {$mi->coursemodule}\n";
} else {
    $ctx['assign_cmid'] = (int) $cm->id;
    echo "assignment cm {$cm->id} exists\n";
}
$ctx['page_cmid'] = (int) $DB->get_field_sql(
    "SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
      WHERE cm.course = :c AND m.name = 'page' AND cm.deletioninprogress = 0 ORDER BY cm.id", ['c' => $free->id], IGNORE_MULTIPLE);
$ctx['forum_cmid'] = (int) $DB->get_field_sql(
    "SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
      WHERE cm.course = :c AND m.name = 'forum' AND cm.deletioninprogress = 0 ORDER BY cm.id", ['c' => $free->id], IGNORE_MULTIPLE);

// 2. A pending request.
$reqcourse = $DB->get_record('course', ['shortname' => 'vp53_request']);
if (!$reqcourse) {
    $category = (int) $free->category;
    $reqcourse = create_course((object) ['fullname' => 'VP53 Request Course', 'shortname' => 'vp53_request',
        'category' => $category, 'visible' => 1, 'format' => 'topics', 'numsections' => 1]);
    echo "created course {$reqcourse->id}\n";
}
$DB->set_field('course', 'open_path', '/1', ['id' => $reqcourse->id]);
$ctx['request_course'] = (int) $reqcourse->id;
try {
    $req = $DB->get_record_select('local_sentientia_request', "userid = :u AND courseid = :c AND status = 'pending'",
        ['u' => $learner->id, 'c' => $reqcourse->id], '*', IGNORE_MULTIPLE);
    if (!$req) {
        $req = \local_sentientia_request\request_manager::submit((int) $learner->id, (int) $reqcourse->id,
            'Local evidence request: needed for the Moodle 5.3 dialog screenshots.');
        echo "submitted request {$req->id}\n";
    }
    $ctx['request_id'] = (int) $req->id;
    $ctx['request_approver'] = (int) $req->approver_userid;
    $ctx['request_approver_username'] = (string) $DB->get_field('user', 'username', ['id' => $req->approver_userid]);
    echo "request {$req->id} approver {$ctx['request_approver_username']} ({$req->approver_userid})\n";
} catch (Throwable $e) {
    echo 'request not seeded: ' . $e->getMessage() . "\n";
}

// 3. A tenant-/1 e-mail override.
try {
    $list = \local_sentientia_emails\email_renderer::get_template_list();
    $key = $list[0]['templates'][0]['key'];
    $existing = \local_sentientia_emails\template_manager::get_override($key, 1);
    if (!$existing || empty($existing->body_html)) {
        $path = $CFG->dirroot . '/local/sentientia_emails/templates/' . $key . '.mustache';
        $body = is_file($path) ? file_get_contents($path) : '<p>Hello {{firstname}}, this is a local evidence override.</p>';
        \local_sentientia_emails\template_manager::save_override($key, 1, '', "<!-- VP53 evidence override -->\n" . $body);
        echo "saved tenant-1 override of {$key}\n";
    } else {
        echo "tenant-1 override of {$key} exists\n";
    }
    $ctx['email_template'] = $key;
} catch (Throwable $e) {
    echo 'e-mail override not seeded: ' . $e->getMessage() . "\n";
}

// 3b. A priced course the Public learner may pay for: "VP GST Course E" (seed_gst_evidence.php) with its fee instance pointed
// at the local payment account (id 1, Airpay) and the employee role, so the enrol page shows the payment button (item 6).
$fee = $DB->get_record('course', ['shortname' => 'vp_gst_e']);
if ($fee) {
    $DB->set_field('enrol', 'customint1', (int) ($DB->get_field_sql('SELECT MIN(id) FROM {payment_accounts}') ?: 0), ['courseid' => $fee->id, 'enrol' => 'fee']);
    $DB->set_field('enrol', 'roleid', (int) $DB->get_field('role', 'id', ['shortname' => 'employee']), ['courseid' => $fee->id, 'enrol' => 'fee']);
    $ctx['fee_course'] = (int) $fee->id;
}

// 4. Ids the checks need.
$ctx['evaluation_form'] = (int) $DB->get_field('local_sentientia_evaluation', 'id', ['name' => 'VP Course reaction survey: Compliance Essentials']);
$ctx['skill'] = (int) $DB->get_field_sql('SELECT MIN(id) FROM {local_sentientia_skills}');
$ctx['cert_template'] = (int) $DB->get_field_sql('SELECT MIN(id) FROM {tool_certificate_templates}');
$paidorder = $DB->get_record_select('local_sentientia_cart_history', "costcenterid = 1 AND status IN ('paid','partial_refund') AND orderid IS NOT NULL", [],
    'id, orderid', IGNORE_MULTIPLE);
$ctx['orders_tenant1_orderid'] = $paidorder ? (int) $paidorder->orderid : 0;
file_put_contents(__DIR__ . '/.moodle53-evidence.local.json', json_encode($ctx, JSON_PRETTY_PRINT));
echo json_encode($ctx, JSON_PRETTY_PRINT) . "\n";
