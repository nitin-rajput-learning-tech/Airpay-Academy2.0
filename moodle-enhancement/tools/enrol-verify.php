<?php
// Verifies the local_airpay_catalog one-click free self-enrol fix (QA-walk E-01,
// 2026-05-29) end-to-end. WRITES: enables the feature flag for a tenant and
// enrols a user via enrolment::enrol_now() (bypassing any self-enrol key).
//
// ⚠ LOCAL-DEV-ONLY — refuses to run unless wwwroot is localhost/127.0.0.1
//   (set ALLOW_NONLOCAL=1 to override on a disposable staging box; NEVER prod).
//
// Run:  php moodle-enhancement/tools/enrol-verify.php [courseid] [userid] [tenant]
//   e.g. php moodle-enhancement/tools/enrol-verify.php 71 3421 1
// Moodle public/ dir defaults to C:/xampp/htdocs/moodle5/public; override via
// MOODLE_PUBLIC. (The ambient MOODLE_ROOT var on the dev box points elsewhere.)

define('CLI_SCRIPT', true);
$moodlepublic = getenv('MOODLE_PUBLIC') ?: 'C:/xampp/htdocs/moodle5/public';
if (!is_file($moodlepublic . '/config.php')) {
    fwrite(STDERR, "Moodle config.php not found under '$moodlepublic'. "
        . "Set MOODLE_PUBLIC to your Moodle public/ directory.\n");
    exit(2);
}
require($moodlepublic . '/config.php');

global $CFG, $DB;

// ── Production / non-local guard ─────────────────────────────────────────
$islocal = (strpos($CFG->wwwroot, '//localhost') !== false)
        || (strpos($CFG->wwwroot, '//127.0.0.1') !== false);
if (!$islocal && getenv('ALLOW_NONLOCAL') !== '1') {
    fwrite(STDERR, "REFUSING: this tool WRITES (enables a flag + enrols a user).\n"
        . "  wwwroot = {$CFG->wwwroot} is not localhost. Set ALLOW_NONLOCAL=1 to override.\n");
    exit(2);
}

use local_airpay_catalog\enrolment;
use local_airpay_core\feature_flags;

$courseid = (int) ($argv[1] ?? 71);
$userid   = (int) ($argv[2] ?? 3421);
$tenant   = (int) ($argv[3] ?? 1);

$pass = true;
function check($label, $cond) {
    global $pass;
    $pass = $pass && $cond;
    printf("  [%s] %s\n", $cond ? 'PASS' : 'FAIL', $label);
}

echo "=== Enabling flag for tenant /$tenant ===\n";
feature_flags::invalidate_caches();
feature_flags::set(enrolment::FLAG, $tenant, true, null, 'enrol-verify');
feature_flags::invalidate_caches();
echo "  '" . enrolment::FLAG . "' for /$tenant: "
   . (feature_flags::is_enabled_for_tenant(enrolment::FLAG, $tenant) ? 'YES' : 'NO') . "\n";

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$free = ['is_free' => true, 'price' => 0, 'display' => 'Free'];
$paid = ['is_free' => false, 'price' => 499, 'display' => '₹499'];

echo "\n=== POLICY: should_offer_oneclick() ===\n";
check("internal /$tenant + free + flag ON => one-click",
    enrolment::should_offer_oneclick($user, $free) === true);
$pub = clone $user; $pub->open_path = '/' . enrolment::public_tenant_id();
check('Public tenant => cart (no one-click)',
    enrolment::should_offer_oneclick($pub, $free) === false);
check('paid course => cart (no one-click)',
    enrolment::should_offer_oneclick($user, $paid) === false);

echo "\n=== MECHANISM: enrol_now() on course $courseid ===\n";
$ctx = context_course::instance($courseid);
echo "  before: is_enrolled = " . (is_enrolled($ctx, $user) ? 'YES' : 'no') . "\n";
check('enrol_now() returned true', enrolment::enrol_now($courseid, $userid) === true);
check('user now is_enrolled (key bypassed)', is_enrolled($ctx, $user) === true);
check('idempotent (second call true)', enrolment::enrol_now($courseid, $userid) === true);
$cnt = $DB->count_records_sql(
    "SELECT COUNT(ue.id) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
      WHERE e.courseid = :c AND ue.userid = :u", ['c' => $courseid, 'u' => $userid]);
check("exactly one enrolment row (got $cnt)", $cnt === 1);

echo "\n" . ($pass ? '*** ALL CHECKS PASSED ***' : '!!! SOME CHECKS FAILED !!!') . "\n";
exit($pass ? 0 : 1);
