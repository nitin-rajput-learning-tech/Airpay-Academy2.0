<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Reports';

// Capabilities.
$string['sentientia_reports:view']   = 'View saved reports';
$string['sentientia_reports:manage'] = 'Manage saved reports';
$string['sentientia_reports:export'] = 'Export saved reports to CSV';

// CRUD strings.
$string['addreport']      = 'Create Report';
$string['editreport']     = 'Edit Report';
$string['deletereport']   = 'Delete Report';
$string['archivereport']  = 'Archive Report';
$string['activatereport'] = 'Activate Report';

// Form section headings.
$string['heading_basic'] = 'Report Identity';
$string['heading_type']  = 'Report Type';
$string['heading_scope'] = 'Organisation Scope';

// Form labels.
$string['report_name']       = 'Report name';
$string['description']       = 'Description';
$string['report_type']       = 'Report type';
$string['report_type_help']  = 'Each built-in type runs a different query. Course Completion lists user-by-course progress. Compliance Overview shows mandatory training rates. User Activity tracks login engagement. Enrolment Trend shows monthly enrolment volumes.';
$string['organisation']      = 'Organisation (tenant scope)';
$string['organisation_help'] = 'Limit the report to a specific organisation in your hierarchy. Leave on "All organisations" to include every tenant.';
$string['status']            = 'Status';
$string['status_active']     = 'Active';
$string['status_archived']   = 'Archived';

// Errors.
$string['name_required']        = 'Report name is required.';
$string['invalid_report_type']  = 'Invalid report type.';
$string['invalidreport']        = 'Report not found.';
$string['invalidreporttype']    = 'Invalid report type.';
$string['missingrequiredfields'] = 'Please fill in all required fields.';

// Confirmation dialogs.
$string['confirmdelete']   = 'Delete report "{$a}"? This will permanently remove the saved definition. Generated CSV exports are not affected. This cannot be undone.';
$string['confirmarchive']  = 'Archive "{$a}"? It will be hidden from the active list but kept for audit. Reactivate any time.';
$string['confirmactivate'] = 'Activate "{$a}"? It will appear in the main reports list and run on demand.';

// Toast messages.
$string['report_created']      = 'Report created.';
$string['report_updated']      = 'Report updated.';
$string['reportdeleted']       = 'Report deleted.';
$string['reportstatuschanged'] = 'Report status updated.';

// Privacy.
$string['privacy:metadata'] = 'The Airpay Reports plugin stores saved report definitions, but does not export user data directly. Generated reports may aggregate user activity from existing core Sentientia LMS tables.';

// Privacy provider (2026-08-04) — real metadata + export; deletion
// anonymises created_by (report definitions are shared org assets).
$string['privacy:metadata:reports']              = 'Saved report definitions; records which user authored each report';
$string['privacy:metadata:reports:created_by']   = 'The user who created the report';
$string['privacy:metadata:reports:name']         = 'The report name';
$string['privacy:metadata:reports:report_type']  = 'The type of report';
$string['privacy:metadata:reports:timecreated']  = 'When the report was created';
