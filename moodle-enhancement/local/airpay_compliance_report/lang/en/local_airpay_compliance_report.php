<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Compliance Report';
$string['airpay_compliance_report:export'] = 'Export the compliance report';
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
