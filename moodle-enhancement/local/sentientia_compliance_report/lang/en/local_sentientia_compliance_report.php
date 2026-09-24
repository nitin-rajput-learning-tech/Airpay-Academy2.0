<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Compliance Report';
$string['sentientia_compliance_report:export'] = 'Export the compliance report';
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

// Privacy metadata (classes/privacy/provider.php, added 2026-09-22).
// Replaced a null_provider that wrongly asserted this plugin held no
// personal data. Every table below is keyed on a user id.
$string['privacy:metadata:compliance_snapshot'] = 'A point-in-time record of one employee\'s status against one mandatory course.';
$string['privacy:metadata:compliance_snapshot:userid'] = 'The ID of the user this record is about.';
$string['privacy:metadata:compliance_snapshot:courseid'] = 'The ID of the course the record refers to.';
$string['privacy:metadata:compliance_snapshot:costcenterid'] = 'The tenant (cost centre) the record belongs to.';
$string['privacy:metadata:compliance_snapshot:department_path'] = 'The organisation path the user sat at when the record was written.';
$string['privacy:metadata:compliance_snapshot:status'] = 'The compliance status recorded for the user.';
$string['privacy:metadata:compliance_snapshot:completion_date'] = 'When the user completed the course.';
$string['privacy:metadata:compliance_snapshot:progress_percent'] = 'How far through the course the user was.';
$string['privacy:metadata:compliance_snapshot:enrol_date'] = 'When the user was enrolled.';
$string['privacy:metadata:compliance_snapshot:deadline_date'] = 'The deadline that applied to the user.';
$string['privacy:metadata:compliance_snapshot:days_overdue'] = 'How many days past the deadline the user was.';
$string['privacy:metadata:compliance_snapshot:matched_by'] = 'How the user was matched to the mandatory course.';
$string['privacy:metadata:compliance_snapshot:snapshot_date'] = 'When this snapshot row was taken.';
$string['privacy:metadata:compliance_exemptions'] = 'An approved exemption releasing one employee from one mandatory course, and who approved it.';
$string['privacy:metadata:compliance_exemptions:userid'] = 'The ID of the user this record is about.';
$string['privacy:metadata:compliance_exemptions:courseid'] = 'The ID of the course the record refers to.';
$string['privacy:metadata:compliance_exemptions:reason'] = 'The reason recorded for the exemption.';
$string['privacy:metadata:compliance_exemptions:approved_by'] = 'The ID of the user who approved the exemption.';
$string['privacy:metadata:compliance_exemptions:expiry_date'] = 'When the exemption expires.';
$string['privacy:metadata:compliance_exemptions:is_active'] = 'Whether the record is currently active.';
$string['privacy:metadata:compliance_exemptions:timecreated'] = 'When the record was created.';
$string['privacy:metadata:compliance_email_log'] = 'A log of the compliance reminder emails sent to an employee, including the address used.';
$string['privacy:metadata:compliance_email_log:userid'] = 'The ID of the user this record is about.';
$string['privacy:metadata:compliance_email_log:courseid'] = 'The ID of the course the record refers to.';
$string['privacy:metadata:compliance_email_log:email_type'] = 'Which reminder email was sent.';
$string['privacy:metadata:compliance_email_log:sent_to'] = 'The email address the message was sent to.';
$string['privacy:metadata:compliance_email_log:timecreated'] = 'When the record was created.';
$string['privacy:metadata:compliance_courses'] = 'Which courses are mandatory. This is shared configuration, not one person\'s data; it records only who created each row.';
$string['privacy:metadata:compliance_courses:createdby'] = 'The ID of the user who created the record.';
