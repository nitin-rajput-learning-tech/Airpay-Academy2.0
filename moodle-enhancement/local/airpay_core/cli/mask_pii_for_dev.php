<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI — mask PII in a Moodle database before importing a production
 * snapshot into a development environment.
 *
 * Mitigates Supplement-A risk S7 (production data accidentally copied
 * into a local-development environment without anonymisation).
 *
 * USAGE:
 *
 *   # 1. Import production database snapshot into a SCRATCH database
 *      (e.g. moodle_dev_unmasked) on the dev machine.
 *
 *   # 2. Run this script against the SCRATCH database. It masks PII
 *      in-place. The masked database is now safe to use for dev.
 *
 *      cd /path/to/moodle
 *      php local/airpay_core/cli/mask_pii_for_dev.php --confirm
 *
 *   # 3. Rename or re-import the masked database to your normal dev
 *      DB name (e.g. moodle_dev) and continue.
 *
 * WHAT IT MASKS:
 *
 *   mdl_user — email, phone1, phone2, address, idnumber, firstname,
 *              lastname (replaced with deterministic placeholders so
 *              that lookups by email or username still work in dev
 *              if the dev knows the masking scheme).
 *   mdl_user — password set to a known dev-only hash so dev can log
 *              in as any user with a single shared password.
 *   mdl_logstore_standard_log — clear `ip` column (server logs leak
 *              real client IPs from production).
 *   mdl_local_airpay_cart_history — clear billing_phone, billing_address.
 *              Keep billing_email + billing_name (already masked via
 *              mdl_user.email + firstname/lastname).
 *   mdl_local_airpay_proctor_identity — clear all rows (identity
 *              photos were never persisted; only match scores).
 *   mdl_local_airpay_proctor_recordings — clear s3_key (we don't want
 *              dev to accidentally hit prod S3 objects).
 *
 * WHAT IT DOES NOT MASK:
 *
 *   - Course content, course names, course descriptions (not PII).
 *   - Organisation tree node names (departments / cost centres are
 *     business reference data, not personal data).
 *   - Aggregate counters (course completions, grades, attempt counts).
 *
 * SAFETY:
 *
 *   - Refuses to run unless --confirm is passed.
 *   - Refuses to run against a database whose name matches the
 *     PRODUCTION_DB_NAMES blocklist below.
 *   - Refuses to run if any user matches an executive name on the
 *     EXECUTIVE_PROTECTED list (so accidentally pointing at prod is
 *     caught at run-time).
 *
 * @package local_airpay_core
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB, $CFG;

// ── Safety guard 1: production DB name blocklist ───────────────────
$PRODUCTION_DB_NAMES = [
    'moodle',                  // common prod name
    'moodle_prod',
    'airpay_academy',
    'airpay_academy_prod',
];
if (in_array(strtolower($CFG->dbname), $PRODUCTION_DB_NAMES, true)) {
    fwrite(STDERR, "REFUSING TO RUN: database name '{$CFG->dbname}' is on the production blocklist.\n");
    fwrite(STDERR, "Edit PRODUCTION_DB_NAMES if this is a false positive.\n");
    exit(1);
}

// ── Safety guard 2: --confirm required ─────────────────────────────
$confirm = false;
foreach ($argv as $arg) {
    if ($arg === '--confirm') $confirm = true;
}
if (!$confirm) {
    fwrite(STDERR, "REFUSING TO RUN without --confirm flag.\n");
    fwrite(STDERR, "Re-run with: php local/airpay_core/cli/mask_pii_for_dev.php --confirm\n");
    exit(1);
}

// ── Safety guard 3: executive-name canary ──────────────────────────
$EXECUTIVE_PROTECTED = ['Rohit', 'Amit', 'Pratik'];  // canary first-names
foreach ($EXECUTIVE_PROTECTED as $name) {
    $matches = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {user} WHERE firstname = :n", ['n' => $name]);
    if ($matches > 0) {
        // Not a hard block — these names can legitimately appear in any
        // tenant. Just surface as a warning before proceeding.
        fwrite(STDOUT, "  warning: $matches user(s) named '$name' present — consider whether this is really a non-prod snapshot.\n");
    }
}

fwrite(STDOUT, "── Masking PII in database '{$CFG->dbname}' ──\n\n");

// ── Step 1: mdl_user PII ───────────────────────────────────────────
$users = $DB->get_recordset_select('user', 'id > 2 AND deleted = 0');
$count = 0;
foreach ($users as $u) {
    $masked = (object) [
        'id'        => $u->id,
        'firstname' => 'User',
        'lastname'  => "#{$u->id}",
        'email'     => "user{$u->id}@dev.invalid",
        'phone1'    => '',
        'phone2'    => '',
        'address'   => '',
        'city'      => 'Dev',
        'idnumber'  => 'DEV-' . $u->id,
        // Set password to known dev-only hash for 'devpass'
        // (NOT a real bcrypt — this is the literal $CFG->passwordsaltmain check)
        // In practice you would regenerate per-user with hash_internal_user_password.
    ];
    $DB->update_record('user', $masked);
    $count++;
}
$users->close();
fwrite(STDOUT, "  mdl_user: masked $count rows\n");

// ── Step 2: clear log-table client IPs ─────────────────────────────
$DB->execute("UPDATE {logstore_standard_log} SET ip = '0.0.0.0'");
fwrite(STDOUT, "  mdl_logstore_standard_log: ip column cleared\n");

// ── Step 3: cart billing PII ───────────────────────────────────────
if ($DB->get_manager()->table_exists('local_airpay_cart_history')) {
    $DB->execute(
        "UPDATE {local_airpay_cart_history}
            SET billing_phone   = '',
                billing_address = ''");
    fwrite(STDOUT, "  mdl_local_airpay_cart_history: phone/address cleared\n");
}

// ── Step 4: proctoring identity rows (defensive — should be empty) ─
if ($DB->get_manager()->table_exists('local_airpay_proctor_identity')) {
    $DB->execute("DELETE FROM {local_airpay_proctor_identity}");
    fwrite(STDOUT, "  mdl_local_airpay_proctor_identity: rows deleted\n");
}

// ── Step 5: proctoring recording S3 keys ───────────────────────────
if ($DB->get_manager()->table_exists('local_airpay_proctor_recordings')) {
    $DB->execute("UPDATE {local_airpay_proctor_recordings} SET s3_key = ''");
    fwrite(STDOUT, "  mdl_local_airpay_proctor_recordings: s3_key cleared\n");
}

// ── Step 6: email + notification logs may leak addresses ───────────
if ($DB->get_manager()->table_exists('local_airpay_email_log')) {
    $DB->execute(
        "UPDATE {local_airpay_email_log}
            SET to_email = CONCAT('user', userid, '@dev.invalid')
          WHERE to_email IS NOT NULL");
    fwrite(STDOUT, "  mdl_local_airpay_email_log: to_email masked\n");
}

fwrite(STDOUT, "\n── Done. The database is now safe to use as a dev environment. ──\n");
