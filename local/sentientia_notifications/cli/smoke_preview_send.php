<?php
// Smoke test: preview_rule + test_send WS endpoints (admin-only).
//
// Run: php public/local/sentientia_notifications/cli/smoke_preview_send.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB, $USER;

// Authenticate as the site admin so preview/test_send caps pass.
\core\session\manager::set_user(get_admin());
// CLI smoke needs to satisfy require_sesskey() (test_send only).
$_POST['sesskey'] = sesskey();

// 1. Seed a rule.
$rule = $DB->insert_record('local_sentientia_notif_rules', (object) [
    'name'         => 'Smoke preview rule',
    'rule_type'    => 'inactive_user',
    'channel'      => 'inapp',
    'trigger_days' => 7,
    'audience'     => 'learner',
    'enabled'      => 1,
    'template'     => "<p>Hello {{firstname}}!</p><p>You haven't logged in for a while. "
        . "Course of interest: {{course_name}}.</p>",
    'timecreated'  => time(),
    'timemodified' => time(),
]);
echo "Seeded rule id=$rule\n";

// 2. preview_rule.
$result = \local_sentientia_notifications\external\preview_rule::execute($rule, 0);
if (empty($result['subject']) || empty($result['message'])) {
    fwrite(STDERR, "FAIL: preview_rule returned empty.\n");
    exit(1);
}
if (strpos($result['message'], '{{firstname}}') !== false) {
    fwrite(STDERR, "FAIL: placeholders not substituted.\n");
    exit(2);
}
if (strpos($result['message'], 'Hello ') === false) {
    fwrite(STDERR, "FAIL: rendered output missing 'Hello'.\n");
    exit(3);
}
echo "preview_rule subject: {$result['subject']}\n";
echo "preview_rule channel: {$result['channel']}\n";
echo "preview_rule message len: " . strlen($result['message']) . " ✓\n";

// 3. test_send (sends via Moodle messaging — caught by noemailever in dev).
$result = \local_sentientia_notifications\external\test_send::execute($rule, 0);
if (!$result['ok']) {
    fwrite(STDERR, "FAIL: test_send did not return ok=true.\n");
    exit(4);
}
if ($result['message_id'] <= 0) {
    fwrite(STDERR, "FAIL: test_send did not return message_id.\n");
    exit(5);
}
echo "test_send → message_id={$result['message_id']} sent_to={$result['sent_to']} ✓\n";

// 4. Verify the log row.
$logged = $DB->record_exists_select('local_sentientia_notif_log',
    'ruleid = :rid AND status = :st',
    ['rid' => $rule, 'st' => 'sent']);
if (!$logged) {
    fwrite(STDERR, "FAIL: no notif_log row written.\n");
    exit(6);
}
echo "notif_log row written ✓\n";

// 5. Cleanup.
$DB->delete_records('local_sentientia_notif_log', ['ruleid' => $rule]);
$DB->delete_records('local_sentientia_notif_rules', ['id' => $rule]);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
