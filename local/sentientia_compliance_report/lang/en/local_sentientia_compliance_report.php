<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Compliance Report';
$string['sentientia_compliance_report:export'] = 'Export the compliance report';
$string['privacy:metadata'] = 'Stores compliance snapshot data linked to user IDs.';
$string['taskrefresh'] = 'Refresh compliance snapshot';
$string['messageprovider:compliance_alert'] = 'Compliance deadline alerts';

// Settings.
$string['settingsheading'] = 'Compliance Report Settings';
$string['settingsdesc'] = 'Configure auto-enrolment, escalation rules, and email notifications for mandatory compliance courses.';
$string['autoenrol'] = 'Auto-enrol missing employees';
$string['autoenrol_desc'] = 'Automatically enrol active employees in mandatory courses they are not enrolled in.';
$string['reminderdays'] = 'Reminder after (days)';
$string['reminderdays_desc'] = 'Send a reminder if employee has not started after this many days.';
$string['managerescalation'] = 'Manager escalation';
$string['managerescalation_desc'] = 'Send overdue alerts to the employee\'s manager.';
$string['weeklyreport'] = 'Weekly compliance email';
$string['weeklyreport_desc'] = 'Send a weekly compliance summary to L&D admins.';

// Report page.
$string['compliancereport'] = 'Compliance Report';
$string['compliancematrix'] = 'Compliance Matrix';
$string['defaulters'] = 'Defaulters';
$string['departmentscorecard'] = 'Department Scorecard';
$string['managerreport'] = 'Manager Report';
$string['export'] = 'Export to Excel';
$string['exportfilename'] = 'Compliance_Report';

// Status labels.
$string['status_completed'] = 'Completed';
$string['status_in_progress'] = 'In Progress';
$string['status_overdue'] = 'Overdue';
$string['status_not_started'] = 'Not Started';
$string['status_not_enrolled'] = 'Not Enrolled';
$string['status_exempted'] = 'Exempted';

// KPIs.
$string['compliancerate'] = 'Compliance Rate';
$string['totalitems'] = 'Total Items';
$string['overduecount'] = 'Overdue';
$string['notenrolledcount'] = 'Not Enrolled';

// Misc.
$string['managecourses'] = 'Manage Mandatory Courses';
$string['addcourse'] = 'Add Mandatory Course';
$string['exemptuser'] = 'Exempt User';
$string['nodata'] = 'No compliance data available. Run the snapshot task first.';
$string['lastrefreshed'] = 'Last refreshed';

// 2026-09-10 — filter bar defaults (were literal English).
$string['filter_all_business_units'] = 'All Business Units';
$string['filter_all_departments'] = 'All Departments';
$string['filter_all_subdepartments'] = 'All Sub-Departments';
$string['filter_all_entities'] = 'All Entities';

// Report page chrome (1.0.2, 2026-09-16) — every on-screen literal is a string so the
// Hindi pack reaches 100% parity on the report itself, not just the filter defaults.
$string['data_updated'] = 'Data updated: {$a}';
$string['stale_refresh'] = 'Stale — refresh recommended';
$string['filter_label'] = 'Filter:';
$string['clear_filters'] = 'Clear filters';
$string['configure'] = 'Configure';
$string['col_employee'] = 'Employee';
$string['col_email'] = 'Email';
$string['col_department'] = 'Department';
$string['col_course'] = 'Course';
$string['col_days_overdue'] = 'Days Overdue';
$string['col_progress'] = 'Progress';
$string['col_total'] = 'Total';
$string['col_completed'] = 'Completed';
$string['col_overdue'] = 'Overdue';
$string['col_rate'] = 'Rate';
$string['col_status'] = 'Status';
$string['col_manager'] = 'Manager';
$string['col_team_items'] = 'Team Items';
$string['col_entity'] = 'Entity';
$string['col_days'] = 'Days';
$string['col_action'] = 'Action';
$string['col_user'] = 'User';
$string['col_reason'] = 'Reason';
$string['col_date'] = 'Date';
$string['n_days'] = '{$a} days';
$string['on_track'] = 'On Track';
$string['at_risk'] = 'At Risk';
$string['critical'] = 'Critical';
$string['config_courses_heading'] = 'Compliance Courses';
$string['config_courses_desc'] = 'Courses tracked for mandatory compliance. Add or remove courses per entity.';
$string['select_course'] = 'Select course...';
$string['deadline_days'] = 'Deadline days';
$string['add'] = 'Add';
$string['remove'] = 'Remove';
$string['inactive'] = 'Inactive';
$string['confirm_remove_course'] = 'Remove this course from compliance tracking?';
$string['excluded_users_heading'] = 'Excluded Users';
$string['excluded_users_desc'] = 'Users excluded from all compliance tracking (operations, temp accounts, etc.).';
$string['user_id'] = 'User ID';
$string['reason_placeholder'] = 'Reason (e.g., Operations email)';
$string['exclude'] = 'Exclude';
$string['reinclude'] = 'Re-include';
$string['no_excluded_users'] = 'No excluded users.';
$string['unknown_entity'] = 'Unknown';
$string['msg_course_added'] = 'Course added to compliance tracking.';
$string['msg_course_removed'] = 'Course removed from tracking.';
$string['msg_user_excluded'] = 'User excluded from tracking.';
$string['msg_user_included'] = 'User re-included in tracking.';
