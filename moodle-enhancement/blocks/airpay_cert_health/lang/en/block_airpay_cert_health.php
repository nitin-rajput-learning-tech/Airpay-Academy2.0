<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Certificate Health';

$string['airpay_cert_health:addinstance']   = 'Add an Airpay Cert Health block';
$string['airpay_cert_health:myaddinstance'] = 'Add an Airpay Cert Health block to the My Moodle page';

// KPI card labels — each one a 7-day rolling count from local_airpay_email_log.
$string['kpi_sent']       = 'Certificates emailed (7d)';
$string['kpi_failed']     = 'Failed sends (7d)';
$string['kpi_suppressed'] = 'Suppressed sends (7d)';

// Severity badge text — same three values used on the cron-health
// block (matches WCAG 1.4.1: severity is also conveyed by text, not
// just colour).
$string['severity_ok']       = 'OK';
$string['severity_warning']  = 'Warning';
$string['severity_critical'] = 'Critical';

// Combined ARIA label so screen readers announce each card as
// "1 Failed sends (7d), severity Critical" rather than three
// disconnected fragments.
$string['kpi_aria_label'] = '{$a->value} {$a->label}, severity {$a->severity}';
$string['region_label']   = 'Airpay certificate-email health summary';

$string['view_full_log'] = 'View full email delivery log →';
