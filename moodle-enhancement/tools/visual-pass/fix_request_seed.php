<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY seed for the ui-fix-request evidence (2026-10-19): ONE pending enrolment request from the test learner
 * vp_learner1 (tenant /1, line manager vp_manager1) for course 11 (A0006, manual enrolment only), submitted through
 * local_sentientia_request\request_manager::submit() exactly as the catalogue dialog does, so it is routed to the line
 * manager. Idempotent: an existing pending request for the same learner and course is reported, not duplicated.
 *
 * Refuses unless wwwroot is localhost AND $CFG->noemailever is set (submit() notifies the approver).
 *
 *   cd C:/xampp/htdocs/moodle5/public && php <this file>
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');

global $CFG, $DB;
$host = parse_url($CFG->wwwroot, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1'], true) || empty($CFG->noemailever)) {
    fwrite(STDERR, "refused: run only on the local site with noemailever set\n");
    exit(1);
}

$learner = $DB->get_record('user', ['username' => 'vp_learner1', 'deleted' => 0], '*', MUST_EXIST);
$courseid = 11;
$DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);

$table = 'local_sentientia_request';
$dbman = $DB->get_manager();
if ($dbman->table_exists($table)) {
    $existing = $DB->get_records_select($table, 'userid = :u AND courseid = :c AND status = :s',
        ['u' => $learner->id, 'c' => $courseid, 's' => 'pending'], 'id DESC', 'id', 0, 1);
    if ($existing) {
        echo 'pending request already there: id ' . (int) reset($existing)->id . "\n";
        exit(0);
    }
}

\core\session\manager::set_user($learner);
$request = \local_sentientia_request\request_manager::submit((int) $learner->id, $courseid,
    'Local visual-evidence test request (2026-10-19), routed to the line manager; safe to reject.');
echo 'submitted request id ' . (int) $request->id . ' status ' . ($request->status ?? '?') . "\n";
