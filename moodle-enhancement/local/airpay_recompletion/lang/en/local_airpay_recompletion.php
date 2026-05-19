<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Recompletion';

// Navigation.
$string['rules']           = 'Recompletion rules';
$string['history']         = 'Reset history';
$string['bulkreset']       = 'Bulk reset';

// Capabilities.
$string['airpay_recompletion:view']     = 'View recompletion rules and history';
$string['airpay_recompletion:manage']   = 'Manage recompletion rules';
$string['airpay_recompletion:reset']    = 'Manually reset user completions';

// Rule status.
$string['enabled']         = 'Enabled';
$string['disabled']        = 'Disabled';
$string['running']         = 'Running';

// Settings.
$string['settings_pre_notify_days']      = 'Pre-notification window (days)';
$string['settings_pre_notify_days_desc'] = 'Notify users this many days BEFORE their compliance is due to expire. Default 30.';
$string['settings_max_batch']            = 'Max users to reset per cron run';
$string['settings_max_batch_desc']       = 'Safety cap so a misconfigured rule cannot reset thousands of users in one cron pass. Default 500.';
$string['settings_dry_run_default']      = 'Dry-run mode (default OFF)';
$string['settings_dry_run_default_desc'] = 'When ON, the daily cron logs what WOULD be reset but does not actually reset anything. Useful for testing new rules.';

// Rule form.
$string['rule_name']            = 'Rule name';
$string['rule_courseid']        = 'Course (leave 0 for all courses with completion enabled)';
$string['rule_period_days']     = 'Reset period (days)';
$string['rule_period_days_help'] = 'Reset completion every N days. 365 = annual, 90 = quarterly. Set to 0 to disable.';
$string['rule_trigger']         = 'Trigger';
$string['rule_trigger_completion'] = 'N days after completion';
$string['rule_trigger_enrolment']  = 'N days after enrolment';
$string['rule_trigger_fixed']      = 'On a fixed calendar date';
$string['rule_fixed_date']      = 'Fixed date (if trigger = fixed)';
$string['rule_reset_grades']    = 'Also reset grades?';
$string['rule_reset_attempts']  = 'Also reset quiz attempts?';
$string['rule_enabled']         = 'Enabled';

// Messages.
$string['messageprovider:recompletion_due_soon'] = 'Recompletion due soon';
$string['messageprovider:recompletion_reset']    = 'Recompletion reset (completed)';

// P1 #20 (2026-05-16) — event class names (shown on the Event monitor +
// reports filters). Closes audit item #19.
$string['event_completion_reset'] = 'Course completion reset';

// UI.
$string['nrules']              = '{$a} rules';
$string['rules_empty']         = 'No recompletion rules configured yet.';
$string['history_empty']       = 'No resets performed yet.';
$string['no_courses_resetable'] = 'No courses with completion tracking enabled — recompletion needs course completion configured.';

// Privacy.
$string['privacy:metadata:local_airpay_recompletion_rules'] = 'Recompletion rule definitions';
$string['privacy:metadata:local_airpay_recompletion_history'] = 'Per-user reset audit log';
$string['privacy:metadata:local_airpay_recompletion_history:userid'] = 'The user whose completion was reset';
$string['privacy:metadata:local_airpay_recompletion_history:courseid'] = 'The course that was reset';
$string['privacy:metadata:local_airpay_recompletion_history:reason'] = 'Why the reset fired';
