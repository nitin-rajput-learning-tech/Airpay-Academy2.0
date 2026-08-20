<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Core (shared infrastructure)';
$string['error_outoftenant'] = 'You do not have access to this tenant.';
$string['error_invalidtenant'] = 'Invalid tenant identifier.';

// Scheduled task names.
$string['task_publish_cron_health'] = 'Airpay Core: publish cron-health summary';

// Cache definition descriptions (shown in /admin/cache_settings.php).
$string['cachedef_cron_health_banner'] = 'Dedupe key for cron-health site-notification banners';
$string['cachedef_feature_flags_registry'] = 'Merged registry of every plugin\'s declared feature flags. 60s TTL.';

// Phase A0 (2026-05-14) — Switchboard / feature flags.
$string['switchboard_pagetitle'] = 'The Switchboard — feature flags';
$string['switchboard_no_changes'] = 'No changes to apply.';
$string['switchboard_applied']    = '{$a} flag change(s) applied. The new values take effect within 60 seconds (cache TTL).';

// Phase A0.5 (2026-05-14) — Style Guide.
$string['styleguide_pagetitle'] = 'Airpay Style Guide';

$string['unknownflagkey'] = 'Unknown feature flag key: "{$a}". The key must be declared in a plugin\'s db/feature_flags.php registry file before it can be set.';

// Surfaced when a flag-gated feature is disabled but the user tries to
// access its URL directly. Friendlier than a raw 403.
$string['featuredisabled'] = 'The feature "{$a}" is currently disabled by your site administrator. Ask them to re-enable it via the Switchboard.';

// Flag-category display labels (shown as section headers on the Switchboard).
// Audit fix H2 (2026-05-15): keep literal '&' here — Mustache auto-escapes
// via {{ category_label }} in the template, so pre-encoding to &amp; would
// double-escape and render as "&AMP;" under text-transform: uppercase.
$string['flag_category_ai']         = 'AI & Automation';
$string['flag_category_engagement'] = 'Engagement & Communications';
$string['flag_category_commerce']   = 'Commerce & Marketplace';
$string['flag_category_identity']   = 'Identity & Access';
$string['flag_category_learning']   = 'Learning Delivery';
$string['flag_category_search']     = 'Search';
$string['flag_category_obs']        = 'Observability';
$string['flag_category_ux']         = 'User Experience';
$string['flag_category_sentientia'] = 'Sentientia Platform';

// Session 2 / ADR-002 (2026-05-20) — customer-level feature flag scope.
$string['customer_default_label']   = 'All customers (global default)';
$string['error_invalidcustomer']    = 'Invalid customer identifier: {$a}.';
$string['gateflag_no_customer_scope'] = 'The customer-level scope gate flag has no customer scope itself. Set it via the global or legacy-tenant scope only.';
$string['customer_layer_disabled']  = 'Cannot set customer-scoped override for "{$a}" — the customer-level scope layer is currently disabled. Enable sentientia.customer_level_flags.enabled at the global scope first.';

// Switchboard scope banner copy.
$string['scope_global']                  = 'Global default';
$string['scope_banner_global']           = 'You are editing the <strong>global default</strong> — this applies to every customer and every tenant unless overridden.';
$string['scope_banner_legacy_tenant']    = 'You are editing the <strong>{$a}</strong> tenant (legacy scope — applies across all customers). Toggles here override the global default for {$a} only.';
$string['scope_banner_customer']         = 'You are editing the <strong>{$a}</strong> customer scope. Toggles here apply to every tenant owned by this customer unless overridden at the tenant level.';
$string['scope_banner_customer_tenant']  = 'You are editing the <strong>{$a->customer}</strong> customer / <strong>{$a->tenant}</strong> tenant pair. Toggles here override the customer-wide value for this specific tenant only.';

// P0 borrow #10 (Moodle 5.2, 2026-05-23) — user-status badge on report
// rows, participants list, and gradebook surfaces. Visible to anyone with
// site:viewreports cap; covers the "why is this row at 0%? oh, they left"
// confusion that surfaces in audit prep.
$string['userstatus_suspended']      = 'Suspended';
$string['userstatus_deleted']        = 'Deleted';
$string['userstatus_badge_aria']     = 'Account status: {$a}';
$string['privacy:metadata:userstatus'] = 'The user-status helper reads but does not store user suspension flags.';

// P0 borrow #11 (Moodle 5.2, 2026-05-23) — Backup filename template.
// Site Admin → Plugins → Local plugins → Airpay Core. Default value is
// backwards-compatible with Moodle's built-in filename builder.
$string['settings_pagetitle']                       = 'Airpay Core';
$string['setting_backup_filename_template']         = 'Default backup filename template';
$string['setting_backup_filename_template_desc']    = 'Template used when SENTIENTIA pipeline (and future Sentientia LMS export jobs) generates backup file names. Use the placeholder tokens listed below — they will be substituted at run time. Tokens not in the template are simply ignored. The {extension} is appended automatically.';
$string['setting_backup_filename_tokens']           = 'Available tokens:';

// ADR-017 Phase 2 / C1.2 (2026-05-28) — Polymorphic user_type labels + profile keys.
// 4 user_types: employee | consumer | partner_employee | operator.
// Per Q6 ruling: blocking in all 5 locales before any provider call-site cuts over.
$string['usertype_employee_label']          = 'Employee';
$string['usertype_consumer_label']          = 'Learner';
$string['usertype_partner_employee_label']  = 'Partner staff';
$string['usertype_operator_label']          = 'Operator';
// Profile field labels (rendered by all 4 providers' profile_context())
$string['profile_field_department']         = 'Department';
$string['profile_field_job_title']          = 'Job title';
$string['profile_field_employee_id']        = 'Employee ID';
$string['profile_field_manager_name']       = 'Manager';
$string['profile_field_hire_date']          = 'Joined';
$string['profile_field_interests']          = 'Topics you follow';
$string['profile_field_weekly_goal_hours']  = 'Weekly learning goal (hours)';
$string['profile_field_referral_source']    = 'Heard about us from';
$string['profile_field_courses_enrolled']   = 'Courses enrolled';
$string['profile_field_consent_marketing']  = 'Marketing emails';
$string['profile_field_consent_leaderboard'] = 'Visible on leaderboard';
$string['profile_field_customer_name']      = 'Organisation';
$string['profile_field_partner_employee_id'] = 'Employee ID';
$string['profile_field_partner_department'] = 'Department';
$string['profile_field_partner_job_title']  = 'Role';
$string['profile_field_partner_manager']    = 'Manager';
$string['profile_field_operator_role']      = 'Operator role';
$string['profile_field_contact_phone']      = 'Contact';
$string['profile_field_oncall_for']         = 'On-call for';
// Onboarding step labels
$string['onboarding_step_welcome']          = 'Welcome';
$string['onboarding_step_interests']        = 'Pick your topics';
$string['onboarding_step_weekly_goal']      = 'Set a weekly goal';
$string['onboarding_step_manager_intro']    = 'Meet your manager';
$string['onboarding_step_compliance_walkthrough'] = 'Mandatory training';
$string['onboarding_step_consent_capture']  = 'Privacy choices';
$string['onboarding_step_finish']           = 'You\'re all set';

// Privacy provider (2026-08-04) — real metadata + export; deletion
// anonymises the author columns (flag config + audit rows are retained).
$string['privacy:metadata:feature_flags']               = 'Feature-flag configuration; records which admin last modified each flag';
$string['privacy:metadata:feature_flags:modified_by']   = 'The admin who last changed the flag';
$string['privacy:metadata:feature_flags:flag_key']      = 'The flag that was changed';
$string['privacy:metadata:feature_flags:timemodified']  = 'When the flag was last changed';
$string['privacy:metadata:flag_audit']                  = 'Audit trail of feature-flag changes; records which admin made each change';
$string['privacy:metadata:flag_audit:changed_by']       = 'The admin who made the change';
$string['privacy:metadata:flag_audit:flag_key']         = 'The flag that was changed';
$string['privacy:metadata:flag_audit:old_value']        = 'The flag value before the change';
$string['privacy:metadata:flag_audit:new_value']        = 'The flag value after the change';
$string['privacy:metadata:flag_audit:reason']           = 'The reason recorded for the change';
$string['privacy:metadata:flag_audit:timecreated']      = 'When the change was made';
