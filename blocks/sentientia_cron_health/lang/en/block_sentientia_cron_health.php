<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname']        = 'Airpay Cron Health';
$string['sentientia_cron_health:addinstance'] = 'Add an Airpay Cron Health block';
$string['sentientia_cron_health:myaddinstance'] = 'Add an Airpay Cron Health block to the My Moodle page';

$string['kpi_stuck_airpay']  = 'Airpay tasks stuck';
$string['kpi_stuck_other']   = 'Other tasks stuck';
$string['kpi_in_backoff']    = 'In failure backoff';

$string['stuck_airpay_heading'] = 'Stuck Airpay tasks';
$string['in_backoff_heading']   = 'Tasks in failure backoff';

$string['view_task_logs']    = 'View full task logs →';

// Severity badges — announced by screen readers in addition to the
// colour-coded KPI value. WCAG 1.4.1 (use of colour) requires the
// information conveyed by colour to also be available in another way.
$string['severity_ok']        = 'OK';
$string['severity_warning']   = 'Warning';
$string['severity_critical']  = 'Critical';

// ARIA labels for the KPI numbers — screen readers announce the full
// "1, Airpay tasks stuck, Critical" rather than just the number.
$string['kpi_aria_label']     = '{$a->value} {$a->label}, severity {$a->severity}';
$string['region_label']       = 'Airpay cron health summary';
$string['overdue_label']      = 'overdue by {$a}';
