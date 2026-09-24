<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * W2-02 - on-box verification that right-to-erasure actually erases.
 *
 * WHAT IT PROVES
 * --------------
 * bc6178610 fixed privacy_manager::process_deletion(): the ME tree deleted from
 * `local_airpay_user_skills`, a table retired by ADR-025, so table_exists() was
 * false, the delete was skipped, and the request was still marked `completed`.
 * `local_sentientia_user_skill_hist` was missed by both trees. The plan's pass
 * criterion is: after erasure, user_skills AND user_skill_hist hold 0 rows for
 * the user AND the request reads `completed` (or `partial` with the missing
 * tables named - never a silent `completed`).
 *
 * WHY IT CREATES ITS OWN ACCOUNT
 * ------------------------------
 * Erasure anonymises, suspends and soft-deletes the user. Running it against a
 * demo persona (Priya, Meera, ...) would destroy the executive demo. So this
 * script creates a fresh throwaway account, seeds rows for it, erases it, and
 * refuses to touch any account it did not create in this run.
 *
 * USAGE (on the UAT box, after the deploy):
 *   sudo -u www-data php w202_erasure_probe.php --i-am-uat
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle5.2/public/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

[$options] = cli_get_params(['i-am-uat' => false], []);
if (empty($options['i-am-uat'])) {
    cli_error('Refusing to run without --i-am-uat.');
}
if (strpos($CFG->wwwroot, 'academy2.airpay.ninja') === false) {
    cli_error("Refusing: wwwroot is {$CFG->wwwroot}, not the UAT instance.");
}

global $DB;
$dbman = $DB->get_manager();

// ── 1. A brand-new throwaway account ─────────────────────────────────────
$username = 'w202probe' . date('YmdHis');
if ($DB->record_exists('user', ['username' => $username])) {
    cli_error("Refusing: {$username} already exists; this script only erases accounts it creates.");
}
$user = (object) [
    'username'   => $username,
    'password'   => bin2hex(random_bytes(16)) . 'Aa1!',   // never used; nobody logs in as this
    'firstname'  => 'W202',
    'lastname'   => 'ErasureProbe',
    'email'      => $username . '@example.invalid',
    'auth'       => 'manual',
    'confirmed'  => 1,
    'mnethostid' => $CFG->mnet_localhost_id,
];
$user->id = user_create_user($user, true, false);
if ($dbman->field_exists('user', 'open_path')) {
    $DB->set_field('user', 'open_path', '/1/2', ['id' => $user->id]);
}
cli_writeln("created throwaway user {$username} (id {$user->id})");

// ── 2. Seed a row in every table the erasure claims to clear ─────────────
$targets = [
    'local_sentientia_user_skills', 'local_sentientia_user_skill_hist',
    'local_sentientia_points_log', 'local_sentientia_streaks',
    'local_sentientia_chat_log', 'local_sentientia_notif_prefs',
];

/**
 * Insert one minimal row for $userid, filling NOT NULL columns that have no default.
 */
$seed = function (string $table, int $userid) use ($DB): string {
    $row = new stdClass();
    foreach ($DB->get_columns($table) as $name => $col) {
        if ($name === 'id') {
            continue;
        }
        if ($name === 'userid') {
            $row->userid = $userid;
            continue;
        }
        if (!$col->not_null || $col->has_default) {
            continue;
        }
        $row->$name = in_array($col->meta_type, ['I', 'N', 'F', 'R'], true) ? 1 : 'w202';
    }
    if (property_exists($row, 'timecreated') || isset($DB->get_columns($table)['timecreated'])) {
        $row->timecreated = time();
    }
    try {
        $DB->insert_record($table, $row);
        return 'seeded';
    } catch (\Throwable $e) {
        return 'seed failed: ' . substr($e->getMessage(), 0, 80);
    }
};

$before = [];
foreach ($targets as $t) {
    if (!$dbman->table_exists($t)) {
        $before[$t] = 'table absent';
        continue;
    }
    $note = $seed($t, (int) $user->id);
    $before[$t] = $DB->count_records($t, ['userid' => $user->id]) . " row(s) ({$note})";
}

// ── 3. Request and process the erasure through the real code path ────────
$adminid = (int) (get_admin()->id);
$reqid = \local_sentientia_privacy\privacy_manager::request_account_deletion(
    (int) $user->id, 'W2-02 on-box erasure verification (throwaway probe account)');
$ok = \local_sentientia_privacy\privacy_manager::process_deletion($reqid, $adminid,
    'W2-02 probe');

// ── 4. Report ────────────────────────────────────────────────────────────
$req = $DB->get_record('local_privacy_requests', ['id' => $reqid]);
$after = $DB->get_record('user', ['id' => $user->id], 'id, username, deleted, suspended, email');

cli_writeln('');
cli_writeln(sprintf('%-36s %-34s %s', 'table', 'before', 'after'));
$pass = true;
foreach ($targets as $t) {
    $a = $dbman->table_exists($t) ? $DB->count_records($t, ['userid' => $user->id]) : 'absent';
    cli_writeln(sprintf('%-36s %-34s %s', $t, $before[$t], $a));
    if (in_array($t, ['local_sentientia_user_skills', 'local_sentientia_user_skill_hist'], true)
            && $a !== 0) {
        $pass = false;
    }
}
cli_writeln('');
cli_writeln("process_deletion returned: " . var_export($ok, true));
cli_writeln("request {$reqid}: status={$req->status}");
cli_writeln("admin_notes: " . trim((string) ($req->admin_notes ?? '')));
cli_writeln("user after: username={$after->username} deleted={$after->deleted} "
    . "suspended={$after->suspended} email={$after->email}");

$statusok = ($req->status === 'completed')
    || ($req->status === 'partial' && trim((string) $req->admin_notes) !== '');
cli_writeln('');
cli_writeln(($pass && $statusok)
    ? 'W2-02 PASS: both skills tables emptied for the user, and the request status is honest.'
    : 'W2-02 FAIL: see the table and status above.');
exit(($pass && $statusok) ? 0 : 1);
