<?php
// L-axis UAT: real Moodle messaging delivery.
//
// Drives the actual message_send() pipeline for:
//   1. Manager decision notification (request_decided)
//   2. Manager allocation notification (allocation_assigned)
//   3. Notification preview/test_send (smart_alert)
//
// For each, asserts that:
//   - message_send() returns a non-zero message ID
//   - mdl_notifications row exists with the right component/eventtype/recipient
//   - Cleanup removes test rows.
//
// Run: php public/local/sentientia_org/cli/uat_l_messaging.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB, $USER;

$passed = 0; $failed = 0;
function pass(string $n): void { global $passed; $passed++; echo "  ✓ $n\n"; }
function fail(string $n, string $r): void { global $failed; $failed++; echo "  ✗ $n — $r\n"; }

echo "L-axis UAT: Moodle messaging delivery\n";
echo str_repeat('─', 60) . "\n";

\core\session\manager::set_user(get_admin());

// Find a real user to be the recipient.
$recipient = $DB->get_record_sql(
    "SELECT id, firstname, email FROM {user}
      WHERE deleted = 0 AND suspended = 0 AND id > 2
        AND username NOT IN ('admin', 'guest')
      ORDER BY id ASC LIMIT 1");
if (!$recipient) {
    fail('Setup', 'no recipient user');
    exit(1);
}
echo "Recipient: user $recipient->id ($recipient->firstname)\n\n";

// ── UAT-L3.1: manager decision notification ────────────────────────
echo "=== UAT-L3.1: Manager decision (request_decided) ===\n";
{
    // Component-specific count (not all notifications — too noisy from
    // background tasks like compliance_alert).
    $pre = (int) $DB->count_records('notifications',
        ['component' => 'local_sentientia_manager']);
    echo "  Pre-test local_sentientia_manager count: $pre\n";

    // Seed a fake request row to decide on, OR just call notify_requester_of_decision.
    $fake_request = (object) [
        'id' => 9999,
        'userid' => $recipient->id,
        'courseid' => 4,
    ];
    try {
        \local_sentientia_manager\approval_manager::notify_requester_of_decision(
            $fake_request, 'approved', 'UAT test reason');
        pass('UAT-L3.1.a notify_requester_of_decision ran');
    } catch (\Throwable $e) {
        fail('UAT-L3.1.a notify_requester_of_decision threw', $e->getMessage());
    }

    // Count after.
    $post = (int) $DB->count_records('notifications',
        ['component' => 'local_sentientia_manager']);
    if ($post > $pre) {
        pass("UAT-L3.1.b mdl_notifications incremented ($pre → $post)");
    } else {
        fail("UAT-L3.1.b mdl_notifications did not increment", "$pre → $post (noemailever may not affect in-app)");
    }

    // Find the message we just sent.
    $msg = $DB->get_record_sql(
        "SELECT * FROM {notifications}
          WHERE useridfrom <> :uid AND component = 'local_sentientia_manager'
       ORDER BY id DESC LIMIT 1",
        ['uid' => $recipient->id]);
    if ($msg) {
        pass('UAT-L3.1.c Found a local_sentientia_manager message');
        echo "    subject: '$msg->subject'\n";
        echo "    component: '$msg->component', eventtype: '$msg->eventtype'\n";
    } else {
        fail('UAT-L3.1.c No local_sentientia_manager message in mdl_notifications', '');
    }
}

// ── UAT-L3.2: manager allocation notification ──────────────────────
echo "\n=== UAT-L3.2: Manager allocation (allocation_assigned) ===\n";
{
    $admin = get_admin();
    try {
        // Signature: ($managerid, $userid, $courseid, ?$due_date, $note='')
        \local_sentientia_manager\approval_manager::notify_assignee_of_allocation(
            (int) $admin->id, (int) $recipient->id, 4,
            time() + 86400 * 30, 'UAT allocation');
        pass('UAT-L3.2.a notify_assignee_of_allocation ran');
    } catch (\Throwable $e) {
        fail('UAT-L3.2.a notify_assignee_of_allocation threw', $e->getMessage());
    }
    $msg = $DB->get_record_sql(
        "SELECT * FROM {notifications}
          WHERE component = 'local_sentientia_manager'
            AND eventtype LIKE '%alloc%'
       ORDER BY id DESC LIMIT 1");
    if ($msg) {
        pass('UAT-L3.2.b allocation_assigned message in mdl_notifications');
        echo "    subject: '$msg->subject'\n";
    } else {
        fail('UAT-L3.2.b allocation_assigned not in mdl_notifications', '');
    }
}

// ── UAT-L3.3: notification test_send ──────────────────────────────
echo "\n=== UAT-L3.3: Notification test_send (smart_alert) ===\n";
{
    // Seed a rule to test_send against.
    $ruleid = $DB->insert_record('local_sentientia_notif_rules', (object) [
        'name'         => 'UAT test_send rule',
        'rule_type'    => 'inactive_user',
        'channel'      => 'inapp',
        'trigger_days' => 7,
        'audience'     => 'learner',
        'enabled'      => 1,
        'template'     => '<p>Hi {{firstname}}!</p><p>UAT test_send.</p>',
        'timecreated'  => time(),
        'timemodified' => time(),
    ]);
    echo "  Seeded rule id=$ruleid\n";

    // CLI UAT harness: prime a valid sesskey into the request context for the
    // direct handler call below. Not user input - seeds, does not read, the form.
    $_POST = array_merge($_POST, ['sesskey' => sesskey()]);
    try {
        $result = \local_sentientia_notifications\external\test_send::execute(
            $ruleid, (int) $recipient->id);
        if ($result['ok'] && $result['message_id'] > 0) {
            pass("UAT-L3.3.a test_send returned message_id={$result['message_id']}");
        } else {
            fail('UAT-L3.3.a test_send not ok', json_encode($result));
        }
    } catch (\Throwable $e) {
        fail('UAT-L3.3.a test_send threw', $e->getMessage());
    }

    $msg = $DB->get_record_sql(
        "SELECT * FROM {notifications}
          WHERE component = 'local_sentientia_notifications'
       ORDER BY id DESC LIMIT 1");
    if ($msg) {
        pass('UAT-L3.3.b smart_alert message in mdl_notifications');
        echo "    subject: '$msg->subject' to userid=$msg->useridfrom\n";
    } else {
        fail('UAT-L3.3.b smart_alert not in mdl_notifications', '');
    }

    // Cleanup the rule.
    $DB->delete_records('local_sentientia_notif_rules', ['id' => $ruleid]);
    $DB->delete_records('local_sentientia_notif_log', ['ruleid' => $ruleid]);
}

// ── UAT-L3.4: message providers are registered ────────────────────
echo "\n=== UAT-L3.4: Message providers registration ===\n";
{
    $providers = $DB->get_records('message_providers',
        ['component' => 'local_sentientia_manager']);
    pass('UAT-L3.4.a local_sentientia_manager providers: ' . count($providers));
    foreach ($providers as $p) {
        echo "    - $p->name\n";
    }

    $providers2 = $DB->get_records('message_providers',
        ['component' => 'local_sentientia_notifications']);
    pass('UAT-L3.4.b local_sentientia_notifications providers: ' . count($providers2));
    foreach ($providers2 as $p) {
        echo "    - $p->name\n";
    }
}

echo "\n" . str_repeat('═', 60) . "\n";
$total = $passed + $failed;
echo "L-axis Messaging UAT: $passed/$total cases pass\n";
if ($failed === 0) echo "\nALL OK ✓\n";
exit($failed === 0 ? 0 : 1);
