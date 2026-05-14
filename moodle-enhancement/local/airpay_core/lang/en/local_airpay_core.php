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

$string['unknownflagkey'] = 'Unknown feature flag key: "{$a}". The key must be declared in a plugin\'s db/feature_flags.php registry file before it can be set.';

// Surfaced when a flag-gated feature is disabled but the user tries to
// access its URL directly. Friendlier than a raw 403.
$string['featuredisabled'] = 'The feature "{$a}" is currently disabled by your site administrator. Ask them to re-enable it via the Switchboard.';

// Flag-category display labels (shown as section headers on the Switchboard).
$string['flag_category_ai']         = 'AI &amp; Automation';
$string['flag_category_engagement'] = 'Engagement &amp; Communications';
$string['flag_category_commerce']   = 'Commerce &amp; Marketplace';
$string['flag_category_identity']   = 'Identity &amp; Access';
$string['flag_category_learning']   = 'Learning Delivery';
$string['flag_category_search']     = 'Search';
$string['flag_category_obs']        = 'Observability';
$string['flag_category_ux']         = 'User Experience';
