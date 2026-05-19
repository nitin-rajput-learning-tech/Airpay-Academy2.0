<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Course Requests';

// Navigation.
$string['myrequests']      = 'My course requests';
$string['pendingapprovals'] = 'Pending approvals';
$string['allrequests']     = 'All requests';

// Capabilities.
$string['airpay_request:request']    = 'Request enrolment in restricted courses';
$string['airpay_request:approve']    = 'Approve / reject course requests';
$string['airpay_request:viewall']    = 'View all requests across tenant';
$string['airpay_request:overrideroute'] = 'Override the approval routing';

// Actions.
$string['requestcourse']   = 'Request access';
$string['requestcourse_long'] = 'Request enrolment';
$string['approve']         = 'Approve';
$string['reject']          = 'Reject';
$string['cancel_request']  = 'Cancel request';
$string['reason']          = 'Reason';
$string['reason_help']     = 'Tell the approver why you need this course (200 chars min).';
$string['decision_note']   = 'Decision note';
$string['decision_note_help'] = 'Visible to the requester. Required for rejections.';

// Status.
$string['status_pending']   = 'Pending';
$string['status_approved']  = 'Approved';
$string['status_rejected']  = 'Rejected';
$string['status_cancelled'] = 'Cancelled';
$string['status_expired']   = 'Auto-expired';

// SLA labels.
$string['sla_due_in']    = 'Due in {$a}';
$string['sla_overdue']   = 'Overdue';
$string['sla_decided']   = 'Decided';
$string['sla_48h']       = '48h SLA';
$string['sla_escalated'] = 'Escalated';

// Approval routing.
$string['route_manager']    = 'Manager approval';
$string['route_courseowner'] = 'Course owner approval';
$string['route_admin']      = 'Site admin approval';

// Notifications.
$string['messageprovider:request_submitted']  = 'Request submitted (requester)';
$string['messageprovider:request_pending']    = 'Request needs your approval (approver)';
$string['messageprovider:request_decided']    = 'Request decision (requester)';
$string['messageprovider:request_escalated']  = 'Request escalated past SLA';

// Errors.
$string['error_reasonshort']     = 'Reason must be at least 20 characters.';
$string['error_alreadyenrolled'] = 'You are already enrolled in this course or learning path.';
$string['error_alreadyrequested'] = 'You already have a pending request for this item.';
$string['error_courseunavailable'] = 'This course is not available for request.';
$string['error_invalidstate']    = 'Invalid request state for this action.';
$string['error_outoftenant']     = 'This action is not allowed across tenants.';

// P1 #6 (2026-05-16) — polymorphic requests (path support).
$string['error_path_inactive']   = 'This learning path is not active and cannot be requested.';

// Settings.
$string['settings_sla_hours']        = 'SLA hours before escalation';
$string['settings_sla_hours_desc']   = 'How long an approver has before the request auto-escalates.';
$string['settings_default_approver'] = 'Default approver when no manager';
$string['settings_default_approver_desc'] = 'User ID who receives requests when the requester has no assigned manager and no course owner.';
$string['settings_auto_expire_days'] = 'Auto-expire after N days';
$string['settings_auto_expire_days_desc'] = 'Requests pending for this many days expire automatically. Set to 0 to disable.';

// UI.
$string['no_requests']      = 'No requests yet.';
$string['no_pending']       = 'No pending approvals — you\'re all caught up!';
$string['request_table_col_course']    = 'Course';
$string['request_table_col_requester'] = 'Requester';
$string['request_table_col_status']    = 'Status';
$string['request_table_col_requested'] = 'Requested';
$string['request_table_col_decided']   = 'Decided';
$string['request_table_col_approver']  = 'Approver';
$string['request_table_col_actions']   = 'Actions';

// Privacy.
$string['privacy:metadata:local_airpay_request']                   = 'Course enrolment requests';
$string['privacy:metadata:local_airpay_request:userid']            = 'The user who placed the request';
$string['privacy:metadata:local_airpay_request:courseid']          = 'The requested course';
$string['privacy:metadata:local_airpay_request:reason']            = 'Free-text reason from the requester';
$string['privacy:metadata:local_airpay_request:decision_note']     = 'Approver\'s decision note';
$string['privacy:metadata:local_airpay_request:approver_userid']   = 'The user who decided the request';
$string['privacy:metadata:local_airpay_request:status']            = 'Pending / approved / rejected / etc.';
$string['privacy:metadata:local_airpay_request:timecreated']       = 'When the request was placed';

// W1-9 (2026-05-15) — event names.
$string['event_request_submitted'] = 'Access request submitted';
$string['event_request_approved']  = 'Access request approved';
$string['event_request_rejected']  = 'Access request rejected';
