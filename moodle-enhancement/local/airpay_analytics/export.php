<?php
/**
 * Analytics Dashboard Export — CSV download.
 *
 * Usage: /local/airpay_analytics/export.php?range=30d&format=csv
 *
 * @package    local_airpay_analytics
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
if (!is_siteadmin() && !has_capability('local/courses:manage', $context)) {
    throw new moodle_exception('nopermission');
}

$range = optional_param('range', '30d', PARAM_ALPHANUMEXT);
$format = optional_param('format', 'csv', PARAM_ALPHA);

// Tenant scoping.
$orgpath = '';
if (!is_siteadmin()) {
    $orgpath = \local_airpay_org\tenant_manager::get_tenant_path();
}

$data = \local_airpay_analytics\analytics_manager::get_export_data($range, $orgpath);

if ($format === 'csv') {
    $filename = 'Analytics_Report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM.

    // KPIs section.
    fputcsv($output, ['=== ANALYTICS REPORT ===']);
    fputcsv($output, ['Generated', $data['generated']]);
    fputcsv($output, ['Period', $data['range']]);
    fputcsv($output, []);

    fputcsv($output, ['=== KEY PERFORMANCE INDICATORS ===']);
    if (!empty($data['kpis'])) {
        foreach ($data['kpis'] as $kpi) {
            $label = $kpi['label'] ?? $kpi['name'] ?? 'KPI';
            $value = $kpi['value'] ?? $kpi['current'] ?? 0;
            $trend = $kpi['trend']['label'] ?? '';
            fputcsv($output, [$label, $value, $trend]);
        }
    }
    fputcsv($output, []);

    // Funnel section.
    fputcsv($output, ['=== ENGAGEMENT FUNNEL ===']);
    fputcsv($output, ['Stage', 'Count', 'Percentage']);
    if (!empty($data['funnel'])) {
        foreach ($data['funnel'] as $stage) {
            fputcsv($output, [$stage['stage'], $stage['count'], $stage['pct'] . '%']);
        }
    }
    fputcsv($output, []);

    // Heatmap section.
    fputcsv($output, ['=== COMPLIANCE BY DEPARTMENT ===']);
    fputcsv($output, ['Department', 'Users', 'Compliance Rate', 'RAG Status']);
    if (!empty($data['heatmap'])) {
        foreach ($data['heatmap'] as $dept) {
            fputcsv($output, [$dept['department'], $dept['users'], $dept['rate'] . '%', strtoupper($dept['rag'])]);
        }
    }
    fputcsv($output, []);

    // Course effectiveness section.
    fputcsv($output, ['=== COURSE EFFECTIVENESS (Top 20) ===']);
    fputcsv($output, ['Course', 'Enrolled', 'Completed', 'Completion Rate']);
    if (!empty($data['courses'])) {
        foreach ($data['courses'] as $course) {
            fputcsv($output, [
                $course['fullname'] ?? $course->fullname ?? '',
                $course['enrolled'] ?? $course->enrolled ?? 0,
                $course['completed'] ?? $course->completed ?? 0,
                ($course['completion_rate'] ?? $course->completion_rate ?? 0) . '%',
            ]);
        }
    }

    fclose($output);
    die();
}

// Unknown format.
throw new moodle_exception('invalidformat', 'error');
