<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Classroom Training';

// Capabilities.
$string['airpay_classroom:manage'] = 'Manage classroom sessions';
$string['airpay_classroom:view'] = 'View classroom sessions';
$string['airpay_classroom:attendance'] = 'Manage attendance';
$string['airpay_classroom:create'] = 'Create classroom sessions';
$string['airpay_classroom:update'] = 'Edit classroom sessions';
$string['airpay_classroom:delete'] = 'Delete classroom sessions';

// CRUD form strings.
$string['addclassroom'] = 'Add Classroom';
$string['editclassroom'] = 'Edit Classroom';
$string['deleteclassroom'] = 'Delete Classroom';
$string['cancelclassroom'] = 'Cancel Classroom';
$string['completeclassroom'] = 'Mark as Completed';
$string['reopenclassroom'] = 'Reopen Classroom';

// Form section headings.
$string['heading_basic'] = 'Basic Information';
$string['heading_logistics'] = 'Logistics';
$string['heading_org'] = 'Organisation';
$string['heading_status'] = 'Status';

// Form field labels.
$string['name'] = 'Classroom name';
$string['description'] = 'Description';
$string['location'] = 'Location';
$string['capacity'] = 'Maximum capacity';
$string['trainer'] = 'Primary trainer';
$string['organisation'] = 'Organisation (tenant)';
$string['status'] = 'Status';
$string['status_active'] = 'Active';
$string['status_completed'] = 'Completed';
$string['status_cancelled'] = 'Cancelled';

// Errors.
$string['missingrequiredfields'] = 'Please fill in all required fields.';
$string['capacityinvalid'] = 'Capacity must be at least 1.';
$string['invalidstatus'] = 'Invalid status value.';
$string['confirmdelete'] = 'Are you sure you want to delete "{$a}"? This will permanently remove the classroom, all its sessions, and attendance records. This cannot be undone.';
$string['confirmcancel'] = 'Are you sure you want to cancel "{$a}"? Enrolled learners will be notified.';
$string['confirmcomplete'] = 'Mark "{$a}" as completed? This indicates all sessions are finished.';
$string['confirmreopen'] = 'Reopen "{$a}" and set status back to active?';

// Success messages.
$string['classroomcreated'] = 'Classroom created.';
$string['classroomupdated'] = 'Classroom updated.';
$string['classroomdeleted'] = 'Classroom deleted.';
$string['classroomstatuschanged'] = 'Classroom status changed.';
