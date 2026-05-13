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
