<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Smoke test for airpay_recompletion — rule lifecycle + reset.
 *
 * @package local_airpay_recompletion
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

echo "=== airpay_recompletion smoke ===\n\n";

$test = 0; $pass = 0;
$check = function(string $name, bool $ok, string $detail = '') use (&$test, &$pass) {
    $test++; if ($ok) $pass++;
    printf("  %s [%2d] %s%s\n", $ok ? '✓' : '✗', $test, $name, $detail ? " — $detail" : '');
};

// Setup: pick a course with completion enabled (or enable on first available).
$course = $DB->get_record_sql(
    "SELECT * FROM {course} WHERE enablecompletion = 1 AND id > 1 LIMIT 1");
if (!$course) {
    // Force-enable on the first non-site course.
    $course = $DB->get_record_sql("SELECT * FROM {course} WHERE id > 1 LIMIT 1");
    if (!$course) { echo "FAIL: no test course\n"; exit(1); }
    $course->enablecompletion = 1;
    $DB->update_record('course', $course);
}
$user = $DB->get_record('user', ['username' => 'public.uat@airpay.test']);

echo "User: $user->username (id=$user->id)\n";
echo "Course: $course->fullname (id=$course->id)\n\n";

// === 1. Create rule ===
echo "=== 1. Create rule ===\n";
$rule = (object) [
    'name'           => 'Smoke test annual',
    'courseid'       => (int) $course->id,
    'period_days'    => 365,
    'trigger'        => 'completion',
    'fixed_date'     => null,
    'reset_grades'   => 1,
    'reset_attempts' => 1,
    'enabled'        => 1,
    'costcenterid'   => 0,
    'timecreated'    => time(),
    'timemodified'   => time(),
];
$rule->id = $DB->insert_record('local_airpay_recompletion_rules', $rule);
$check('Rule created', !empty($rule->id), "id=$rule->id");

// === 2. Seed a fake completion that's 366 days old (just past expiry) ===
echo "\n=== 2. Seed expired completion ===\n";
$DB->delete_records('course_completions',
    ['userid' => $user->id, 'course' => $course->id]);
$old_completion = time() - (366 * 86400);
$cc_id = $DB->insert_record('course_completions', [
    'userid'        => $user->id,
    'course'        => $course->id,
    'timecompleted' => $old_completion,
    'timestarted'   => $old_completion - 86400,
    'timeenrolled'  => $old_completion - 86400,
    'reaggregate'   => 0,
]);
$check('Old completion row inserted', $cc_id > 0, "ts=$old_completion (~366d old)");

// === 3. Run rule in dryrun ===
echo "\n=== 3. Run rule (dryrun) ===\n";
// Reload the rule from DB (gets the autoincremented id + clean object).
$rule = $DB->get_record('local_airpay_recompletion_rules', ['id' => $rule->id]);
echo "  (rule reloaded — trigger_type=$rule->trigger_type courseid=$rule->courseid)\n";
$r_dry = \local_airpay_recompletion\recompletion_engine::run_rule($rule, true);
$check('Dryrun finds at least 1 candidate', $r_dry['reset'] >= 1,
    "reset={$r_dry['reset']} notified={$r_dry['notified']} skipped={$r_dry['skipped']}");

// Verify the completion ROW IS STILL THERE in dryrun mode.
$still_exists = $DB->record_exists('course_completions',
    ['userid' => $user->id, 'course' => $course->id]);
$check('Dryrun does NOT actually reset', $still_exists);

$dry_history = $DB->count_records('local_airpay_recompletion_history',
    ['ruleid' => $rule->id, 'dryrun' => 1]);
$check('Dryrun history rows recorded', $dry_history >= 1);

// === 4. Run rule for real ===
echo "\n=== 4. Run rule (real) ===\n";
$r = \local_airpay_recompletion\recompletion_engine::run_rule($rule, false);
$check('Real run resets the completion', $r['reset'] >= 1,
    "reset={$r['reset']}");

$still_exists = $DB->record_exists('course_completions',
    ['userid' => $user->id, 'course' => $course->id]);
$check('Completion row gone', !$still_exists);

$real_history = $DB->count_records('local_airpay_recompletion_history',
    ['ruleid' => $rule->id, 'userid' => $user->id, 'dryrun' => 0]);
$check('Audit history row recorded', $real_history >= 1);

// === 5. Idempotency — second run shouldn't double-reset ===
echo "\n=== 5. Idempotent re-run ===\n";
$r2 = \local_airpay_recompletion\recompletion_engine::run_rule($rule, false);
$check('Re-run resets 0 (already reset)', $r2['reset'] === 0,
    "reset={$r2['reset']}");

// === 6. Bulk reset ===
echo "\n=== 6. Bulk reset (manual) ===\n";
// Reinsert the completion so we can bulk-reset it.
$DB->insert_record('course_completions', [
    'userid'        => $user->id,
    'course'        => $course->id,
    'timecompleted' => time() - 86400,
    'timestarted'   => time() - 86400 * 2,
    'timeenrolled'  => time() - 86400 * 3,
    'reaggregate'   => 0,
]);
$bulk = \local_airpay_recompletion\recompletion_engine::bulk_reset(
    (int) $course->id, [(int) $user->id], 2, 'bulk', true, true);
$check('Bulk reset returns reset=1', $bulk['reset'] === 1);
$check('Audit row marks reason=bulk',
    $DB->record_exists('local_airpay_recompletion_history',
        ['ruleid' => 0, 'userid' => $user->id, 'reason' => 'bulk']));

// === 7. Engine run_all ===
echo "\n=== 7. run_all() with multiple rules ===\n";
$totals = \local_airpay_recompletion\recompletion_engine::run_all(true);
$check('run_all() returns totals', isset($totals['rules_run'])
    && isset($totals['reset']),
    "rules_run={$totals['rules_run']} reset={$totals['reset']}");
$check('rules_run >= 1', $totals['rules_run'] >= 1);

// Cleanup.
$DB->delete_records('local_airpay_recompletion_rules', ['id' => $rule->id]);
$DB->delete_records('local_airpay_recompletion_history', ['ruleid' => $rule->id]);
$DB->delete_records('local_airpay_recompletion_history', ['userid' => $user->id, 'reason' => 'bulk']);
$DB->delete_records('course_completions', ['userid' => $user->id, 'course' => $course->id]);

echo "\n" . str_repeat('=', 50) . "\n";
echo sprintf("Smoke result: %d/%d cases pass\n", $pass, $test);
echo str_repeat('=', 50) . "\n";
exit($pass === $test ? 0 : 1);
