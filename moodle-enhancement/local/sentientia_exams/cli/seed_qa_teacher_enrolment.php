<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Seed qa_orgadmin as an editing teacher in the probe course, so the A5
 * Playwright walk can drive the real exam/quiz CREATE form (mod/quiz
 * modedit) instead of only the graceful capability-redirect HTTP half.
 *
 * Idempotent — safe to run before every test run. LOCAL/QA INSTANCES ONLY:
 * refuses to run when the qa_* accounts are absent.
 *
 * @package local_sentientia_exams
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/enrollib.php');

global $DB;

$courseid = (int) ($argv[1] ?? 71); // API001 "Aptitude Test Advanced" by default.

$teacher = $DB->get_record('user', ['username' => 'qa_orgadmin', 'deleted' => 0]);
if (!$teacher) {
    cli_error('qa_orgadmin not found — this seeder is for QA-provisioned instances only.');
}
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);
$role = $DB->get_record('role', ['archetype' => 'editingteacher'], '*', MUST_EXIST);

// 1. Manual enrolment instance on the course (create if the plugin left none).
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if (!$instance) {
    $plugin = enrol_get_plugin('manual');
    $plugin->add_default_instance($course);
    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
    echo "enrol: created manual enrolment instance on course {$course->id}\n";
}

// 2. Enrol + assign editingteacher (enrol_user is idempotent on re-run).
$manual = enrol_get_plugin('manual');
$manual->enrol_user($instance, $teacher->id, $role->id);
echo "enrol: qa_orgadmin ({$teacher->id}) is editingteacher in {$course->shortname} (id={$course->id})\n";

// 3. Verify the capability the create form requires actually resolves now.
$canadd = has_capability('mod/quiz:addinstance', $context, $teacher->id);
echo 'cap: mod/quiz:addinstance for qa_orgadmin = ' . ($canadd ? 'YES' : 'NO') . "\n";
if (!$canadd) {
    cli_error('Enrolment landed but mod/quiz:addinstance still denied — check role caps.');
}
echo "OK: A5 teacher fixture ready — qa_orgadmin can create a quiz in course {$course->id}.\n";
exit(0);
