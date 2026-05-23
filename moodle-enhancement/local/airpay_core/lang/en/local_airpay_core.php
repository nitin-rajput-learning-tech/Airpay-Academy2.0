<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Core (shared infrastructure)';
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
