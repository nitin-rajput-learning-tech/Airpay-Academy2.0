<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * End-to-end smoke for airpay_proctoring.
 *
 * Walks: start → consent → submit_identity (mock) → record events →
 *        register chunks → finalize → analyzer scores → flagged → review.
 *
 * Uses the MOCK identity verifier (set provider=mock in settings before
 * running this script).
 *
 * @package local_airpay_proctoring
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

set_config('provider', 'mock', 'local_airpay_proctoring');
set_config('default_reviewer', 2, 'local_airpay_proctoring');

echo "=== airpay_proctoring smoke test ===\n\n";

$test = 0; $pass = 0;
$check = function(string $name, bool $ok, string $detail = '') use (&$test, &$pass) {
    $test++; if ($ok) $pass++;
    printf("  %s [%2d] %s%s\n", $ok ? '✓' : '✗', $test, $name, $detail ? " — $detail" : '');
};

$user = $DB->get_record_sql(
    "SELECT * FROM {user}
      WHERE username = 'public.uat@airpay.test' AND deleted = 0 LIMIT 1");
if (!$user) { echo "FAIL: test user not found\n"; exit(1); }

// Switch the $USER global to the test user so the new B3 session-owner
// checks in session_manager pass (we're calling as the candidate, not
// as the admin who launched the CLI). `cron_setup_user()` was the v4
// helper but is deprecated in v5; `\core\session\manager::set_user()`
// is the modern equivalent and works identically for CLI scripts.
\core\session\manager::set_user($user);

// Pick any quiz on this install.
$quiz = $DB->get_record_sql("SELECT id, name FROM {quiz} LIMIT 1");
if (!$quiz) { echo "FAIL: no quiz to test with\n"; exit(1); }

echo "User: $user->username (id=$user->id)\n";
echo "Quiz: $quiz->name (id=$quiz->id)\n\n";

// === 1. Start session ===
echo "=== 1. Start session ===\n";
$session = \local_airpay_proctoring\session_manager::start_session(
    (int) $user->id, (int) $quiz->id);
$check('Session created', !empty($session->id), "id={$session->id}");
$check('Status = new', $session->status === 'new');

// === 2. Consent ===
echo "\n=== 2. Consent ===\n";
$session = \local_airpay_proctoring\session_manager::record_consent(
    (int) $session->id, (int) $user->id);
$check('Status = consenting', $session->status === 'consenting');
$check('consent_given_at stamped', !empty($session->consent_given_at));

// === 3. Identity verification (mock — always passes) ===
echo "\n=== 3. Identity verification ===\n";
$id_row = \local_airpay_proctoring\session_manager::submit_identity(
    (int) $session->id, (int) $user->id,
    'FAKE-ID-PHOTO-BYTES', 'FAKE-SELFIE-BYTES');
$check('Identity row created', !empty($id_row->id));
$check('Provider = mock', $id_row->provider === 'mock');
$check('Identity passed', $id_row->passed == 1, "score={$id_row->match_score}");
$session = $DB->get_record('local_airpay_proctor_sessions', ['id' => $session->id]);
$check('Session linked to identity', $session->identity_id == $id_row->id);
$check('Status moved to recording', $session->status === 'recording');

// 3b. Identity failure path
$session2 = \local_airpay_proctoring\session_manager::start_session(
    (int) $user->id, (int) $quiz->id);
$id_row2 = \local_airpay_proctoring\session_manager::submit_identity(
    (int) $session2->id, (int) $user->id,
    'ID-BYTES', 'FAIL');  // mock returns failed when selfie === 'FAIL'
$check('Identity failure returns passed=0', $id_row2->passed == 0);

// === 4. Record events ===
echo "\n=== 4. Record events ===\n";
\local_airpay_proctoring\session_manager::record_event(
    (int) $session->id, 'tab_switch', 'warn', ['count' => 1]);
\local_airpay_proctoring\session_manager::record_event(
    (int) $session->id, 'face_lost', 'warn', []);
\local_airpay_proctoring\session_manager::record_event(
    (int) $session->id, 'multiple_faces', 'critical', []);
\local_airpay_proctoring\session_manager::record_event(
    (int) $session->id, 'clipboard_paste', 'critical', []);
$ev_count = $DB->count_records('local_airpay_proctor_events',
    ['sessionid' => $session->id]);
$check('Events recorded', $ev_count >= 4, "$ev_count events");

// === 5. Register chunks ===
echo "\n=== 5. Register recording chunks ===\n";
\local_airpay_proctoring\session_manager::register_chunk(
    (int) $session->id, 'webcam', 0, "s3://bucket/sess{$session->id}/cam_0.webm",
    1024 * 512, 30000);
\local_airpay_proctoring\session_manager::register_chunk(
    (int) $session->id, 'screen', 0, "s3://bucket/sess{$session->id}/scr_0.webm",
    1024 * 1024, 30000);
$chunks = $DB->count_records('local_airpay_proctor_recordings',
    ['sessionid' => $session->id]);
$check('Chunks registered', $chunks === 2);
$retain = $DB->get_field('local_airpay_proctor_recordings', 'retain_until',
    ['sessionid' => $session->id], IGNORE_MULTIPLE);
$check('Retention date set in future', $retain > time(),
    'retain ' . userdate($retain ?: 0));

// === 6. Finalize + risk analyzer ===
echo "\n=== 6. Finalize ===\n";
$session = \local_airpay_proctoring\session_manager::finalize((int) $session->id);
$check('Status flagged (events score > 30)',
    in_array($session->status, ['flagged', 'finished']),
    "status=$session->status risk=$session->risk_score auto=$session->auto_decision");
$check('Risk score computed', $session->risk_score > 0);
$check('Auto decision set', !empty($session->auto_decision));

// Expected score: 5(face_lost) + 25(multiple_faces) + 8(tab_switch) + 10(clipboard) = 48 → warn
$check('Expected score range (40-60)',
    $session->risk_score >= 40 && $session->risk_score <= 60,
    "score={$session->risk_score}");
$check('Auto decision = warn (risk 40-60)', $session->auto_decision === 'warn');

// === 7. Reviewer submits decision ===
echo "\n=== 7. Reviewer decision ===\n";
$reviewer = $DB->get_record('user', ['username' => 'academy@airpay.co.in']);
$session = \local_airpay_proctoring\session_manager::submit_review(
    (int) $session->id, (int) $reviewer->id, 'clean',
    'Smoke test — minor flags are benign.');
$check('Status = reviewed', $session->status === 'reviewed');
$check('Human decision = clean', $session->human_decision === 'clean');
$review_count = $DB->count_records('local_airpay_proctor_reviews',
    ['sessionid' => $session->id]);
$check('Review row inserted', $review_count === 1);

// === 8. Analyzer math accuracy ===
echo "\n=== 8. Analyzer ===\n";
// Add 3 more multiple_faces events → push into FAIL territory
$session3 = \local_airpay_proctoring\session_manager::start_session(
    (int) $user->id, (int) $quiz->id);
\local_airpay_proctoring\session_manager::submit_identity(
    (int) $session3->id, (int) $user->id, 'ID', 'SELFIE');
for ($i = 0; $i < 3; $i++) {
    \local_airpay_proctoring\session_manager::record_event(
        (int) $session3->id, 'multiple_faces', 'critical', []);
}
$session3 = \local_airpay_proctoring\session_manager::finalize((int) $session3->id);
// 3 × 25 = 75 → fail
$check('High-risk session scores fail', $session3->auto_decision === 'fail',
    "score={$session3->risk_score}");

// Cleanup
$DB->delete_records('local_airpay_proctor_events',
    "sessionid IN (SELECT id FROM {local_airpay_proctor_sessions} WHERE userid = $user->id)");
$DB->delete_records('local_airpay_proctor_recordings',
    "sessionid IN (SELECT id FROM {local_airpay_proctor_sessions} WHERE userid = $user->id)");
$DB->delete_records('local_airpay_proctor_identity', ['userid' => $user->id]);
$DB->delete_records('local_airpay_proctor_reviews',
    "sessionid IN (SELECT id FROM {local_airpay_proctor_sessions} WHERE userid = $user->id)");
$DB->delete_records('local_airpay_proctor_sessions', ['userid' => $user->id]);

echo "\n" . str_repeat('=', 50) . "\n";
echo sprintf("Smoke result: %d/%d cases pass\n", $pass, $test);
echo str_repeat('=', 50) . "\n";
exit($pass === $test ? 0 : 1);
