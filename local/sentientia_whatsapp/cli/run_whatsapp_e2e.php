<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * End-to-end WhatsApp pipeline verification — Stream C / Phase C.1 helper.
 *
 * Orchestrates the cron-to-WhatsApp pathway:
 *   1. Generate test user's prefs: opt-in to WhatsApp + give a mobile
 *   2. Toggle engagement.whatsapp.enabled + engagement.whatsapp.reminders ON
 *   3. Approve a deadline_3d DLT template (so the client doesn't refuse)
 *   4. Call notification_bridge::also_send (the same call sentientia_courses
 *      cron makes)
 *   5. Verify a row landed in local_sentientia_send_log with status='mocked'
 *      (or 'sent' if engagement.whatsapp.live_mode were ON — but for
 *      this test we stay in mock mode)
 *   6. Restore every flag + preference + template state
 *
 * Mock-mode is the entire point — until Karix/MSG91 credentials land
 * AND DLT regulator approves the templates in the public DLT portal,
 * the whatsapp_client deliberately doesn't make external network calls.
 * This test proves the LOCAL pipeline works: when DLT + credentials land,
 * flipping live_mode=ON will Just Work without further code changes.
 *
 * Usage:
 *   php local/sentientia_whatsapp/cli/run_whatsapp_e2e.php --userid=N
 *
 * Exit codes: 0 = pass, 1 = at least one failure, 2 = setup failure.
 *
 * @package local_sentientia_whatsapp
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'   => false,
    'userid' => 0,
], [
    'h' => 'help',
    'u' => 'userid',
]);

if ($options['help'] || empty($options['userid'])) {
    cli_writeln('Usage: php run_whatsapp_e2e.php --userid=N');
    exit(2);
}

$userid = (int) $options['userid'];

global $DB;
$user = $DB->get_record('user', ['id' => $userid],
    'id, firstname, lastname, deleted, suspended');
if (!$user || $user->deleted || $user->suspended) {
    cli_writeln('User #' . $userid . ' not found / deleted / suspended.');
    exit(2);
}

cli_writeln('');
cli_writeln('=== Sentientia WhatsApp end-to-end verification ===');
cli_writeln('User: id=' . $userid . ' name=' . $user->firstname . ' ' . $user->lastname);
cli_writeln('Mode: MOCK (engagement.whatsapp.live_mode stays OFF — no external POST)');
cli_writeln('');

$failures = 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$failures) {
    if ($cond) {
        cli_writeln('  [PASS] ' . $name);
    } else {
        cli_writeln('  [FAIL] ' . $name . ($detail !== '' ? ' — ' . $detail : ''));
        $failures++;
    }
};

// ── Backup state we're about to mutate ──
cli_writeln('Backing up current state...');

$prior_prefs = \local_sentientia_whatsapp\preference_manager::get($userid);
$prior_flag_master    = get_config(null, 'local_sentientia_platform_flag_engagement_whatsapp_enabled');
$prior_flag_reminders = get_config(null, 'local_sentientia_platform_flag_engagement_whatsapp_reminders');

// The seeded deadline_3d whatsapp template — capture its prior status.
$tpl = $DB->get_record('local_sentientia_dlt_templates', [
    'template_key' => 'deadline_3d',
    'channel'      => 'whatsapp',
    'language'     => 'en',
]);
if (!$tpl) {
    cli_writeln('!! Seeded deadline_3d WhatsApp template missing. '
        . 'Has the plugin been installed? Aborting.');
    exit(2);
}
$prior_tpl_status = $tpl->status;
cli_writeln('  deadline_3d whatsapp template current status: ' . $prior_tpl_status);
cli_writeln('');

// ── Step 1: Set the user's prefs ──
cli_writeln('Step 1: Set user prefs (opt-in + mobile)');
try {
    // DLT compliance: setting whatsapp_optin = 1 requires an
    // accompanying consent timestamp + text. set() validates both.
    \local_sentientia_whatsapp\preference_manager::set($userid, [
        'mobile_number'    => '+919876543210',
        'whatsapp_optin'   => 1,
        'prefer_channel'   => 'whatsapp',
        'dlt_consent_at'   => time(),
        'dlt_consent_text' => 'E2E test consent — '
            . 'I agree to receive WhatsApp messages from Airpay Academy.',
    ], null, 'whatsapp e2e test');
    $assert('prefs.set returned without error', true);
} catch (\Throwable $e) {
    $assert('prefs.set returned without error', false, $e->getMessage());
}

$refreshed = \local_sentientia_whatsapp\preference_manager::get($userid);
$assert('whatsapp_optin = 1 after set', (int) $refreshed->whatsapp_optin === 1);
$assert('mobile_number set', !empty($refreshed->mobile_number));
cli_writeln('');

// ── Step 2: Toggle feature flags ON ──
cli_writeln('Step 2: Toggle engagement.whatsapp.enabled + .reminders ON');
\local_sentientia_platform\feature_flags::set('engagement.whatsapp.enabled', 0, true);
\local_sentientia_platform\feature_flags::set('engagement.whatsapp.reminders', 0, true);
$assert('master flag enabled',
    \local_sentientia_platform\feature_flags::is_enabled('engagement.whatsapp.enabled'));
$assert('reminders sub-flag enabled',
    \local_sentientia_platform\feature_flags::is_enabled('engagement.whatsapp.reminders'));
cli_writeln('');

// ── Step 3: Approve the deadline_3d template ──
cli_writeln('Step 3: Approve deadline_3d WhatsApp template');
\local_sentientia_whatsapp\dlt_template_registry::transition_status(
    (int) $tpl->id, 'approved', null,
    'e2e test temporary approval');
$approved = \local_sentientia_whatsapp\dlt_template_registry::get_approved(
    'deadline_3d', 'whatsapp', 'en');
$assert('template is now approved + retrievable',
    $approved !== null && $approved->status === 'approved');
cli_writeln('');

// ── Step 4: Capture send_log count before, fire the bridge, count after ──
cli_writeln('Step 4: Fire notification_bridge::also_send (same call cron makes)');
$before_count = $DB->count_records('local_sentientia_send_log',
    ['userid' => $userid, 'channel' => 'whatsapp']);
cli_writeln('  send_log rows BEFORE: ' . $before_count);

$user_obj = (object) ['id' => $userid, 'firstname' => $user->firstname];
$result = \local_sentientia_whatsapp\notification_bridge::also_send(
    $user_obj,
    'engagement.whatsapp.reminders',
    'deadline_3d',
    [
        'firstname'  => $user->firstname,
        'coursename' => 'E2E Test Course',
        'duedate'    => '01 Jun 2026',
        'course_url' => $CFG->wwwroot . '/course/view.php?id=1',
    ]
);

cli_writeln('  also_send returned: ' . var_export($result, true));
$assert('also_send returned a status', is_string($result));

$after_count = $DB->count_records('local_sentientia_send_log',
    ['userid' => $userid, 'channel' => 'whatsapp']);
cli_writeln('  send_log rows AFTER:  ' . $after_count);

$assert('send_log row was inserted', $after_count > $before_count);
cli_writeln('');

// ── Step 5: Inspect the new row ──
cli_writeln('Step 5: Verify the send_log entry');
$row = $DB->get_record_sql(
    "SELECT * FROM {local_sentientia_send_log}
      WHERE userid = :uid AND channel = 'whatsapp'
   ORDER BY id DESC LIMIT 1",
    ['uid' => $userid]
);
if ($row) {
    cli_writeln('  template_key: ' . $row->template_key);
    cli_writeln('  status:       ' . $row->status);
    cli_writeln('  channel:      ' . $row->channel);
    $assert('template_key = deadline_3d', $row->template_key === 'deadline_3d');
    $assert('channel = whatsapp', $row->channel === 'whatsapp');
    $assert('status in [mocked, sent]',
        in_array($row->status, ['mocked', 'sent'], true),
        'got: ' . $row->status);
} else {
    $assert('found the new send_log row', false);
}
cli_writeln('');

// ── Step 6: Restore everything ──
cli_writeln('Step 6: Restore prior state');

// Restore template status.
\local_sentientia_whatsapp\dlt_template_registry::transition_status(
    (int) $tpl->id, $prior_tpl_status, null,
    'e2e test rollback');

// Restore feature flags.
\local_sentientia_platform\feature_flags::set('engagement.whatsapp.enabled', 0,
    $prior_flag_master === '1' ? true : null);
\local_sentientia_platform\feature_flags::set('engagement.whatsapp.reminders', 0,
    $prior_flag_reminders === '1' ? true : null);

// Restore user prefs (if they had a row before, restore; else delete).
if ($prior_prefs->id !== null) {
    \local_sentientia_whatsapp\preference_manager::set($userid, [
        'mobile_number'  => (string) ($prior_prefs->mobile_number ?? ''),
        'whatsapp_optin' => (int) ($prior_prefs->whatsapp_optin ?? 0),
        'sms_optin'      => (int) ($prior_prefs->sms_optin ?? 0),
        'prefer_channel' => (string) ($prior_prefs->prefer_channel ?? 'email'),
    ], null, 'e2e test rollback');
} else {
    // No prior row — delete what we created.
    $DB->delete_records('local_sentientia_user_channel_prefs',
        ['userid' => $userid]);
}
cli_writeln('  Template status restored to: ' . $prior_tpl_status);
cli_writeln('  Flags restored');
cli_writeln('  Prefs restored');

// Clean up the send_log row we created.
if ($row) {
    $DB->delete_records('local_sentientia_send_log', ['id' => $row->id]);
    cli_writeln('  Test send_log row removed');
}
cli_writeln('');

// ── Summary ──
cli_writeln('===========');
if ($failures === 0) {
    cli_writeln('Result: ALL PASS — WhatsApp pipeline ready.');
    cli_writeln('When DLT-approved templates + Karix/MSG91 credentials land,');
    cli_writeln('flip engagement.whatsapp.live_mode = ON to enable real sends.');
    exit(0);
}
cli_writeln('Result: ' . $failures . ' failure(s).');
exit(1);
