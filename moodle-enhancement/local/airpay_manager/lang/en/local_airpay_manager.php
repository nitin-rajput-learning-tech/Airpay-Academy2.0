<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Airpay Manager Dashboard';
$string['myteam'] = 'My Team';
$string['teamlearning'] = 'Team Learning Dashboard';

// Empty-state — manager dashboard with no direct reports (QA Walk T-03).
$string['emptyteam_title'] = 'You have no team members assigned yet';
$string['emptyteam_message'] = 'Direct reports are assigned via the user supervisor field on the user profile.';
$string['privacy:metadata'] = 'The manager dashboard plugin records enrolment requests + course allocations made by managers for compliance / audit purposes.';

// Capability descriptions.
$string['airpay_manager:view']     = 'View team dashboard + requests + allocations';
$string['airpay_manager:approve']  = 'Approve / reject enrolment requests';
$string['airpay_manager:allocate'] = 'Assign courses to direct reports';

// Message providers (db/messages.php).
$string['messageprovider:request_decided']    = 'Outcome of your enrolment request';
$string['messageprovider:allocation_assigned'] = 'Course assigned by your manager';

// Phase B errors.
$string['duplicaterequest']       = 'You already have a pending request for this course.';
$string['alreadydecided']         = 'This request has already been decided.';
$string['notdirectreport']        = 'The selected user is not your direct report.';
$string['duplicateallocation']    = 'This user already has an allocation for that course.';
$string['manualenrolnotavailable'] = 'The course does not have manual enrolment enabled. Configure it in the course\'s enrolment methods.';
$string['filterstoolong']         = 'Filter blob exceeds 4 KB limit.';

// Privacy provider strings.
$string['privacy:metadata:requests']             = 'Enrolment requests from learners awaiting manager approval.';
$string['privacy:metadata:requests:userid']      = 'The learner who made the request.';
$string['privacy:metadata:requests:courseid']    = 'The course being requested.';
$string['privacy:metadata:requests:managerid']   = 'The manager assigned to decide.';
$string['privacy:metadata:requests:status']      = 'pending | approved | rejected | cancelled.';
$string['privacy:metadata:requests:reason']      = 'Why the learner needs the course (free text).';
$string['privacy:metadata:requests:decision_reason'] = 'Manager note when approving/rejecting.';
$string['privacy:metadata:requests:decided_by']  = 'User ID who clicked approve/reject.';
$string['privacy:metadata:requests:decided_at']  = 'When the decision was made.';
$string['privacy:metadata:requests:timecreated'] = 'When the request was filed.';

$string['privacy:metadata:allocations']            = 'Manager-driven course allocations to direct reports.';
$string['privacy:metadata:allocations:managerid']  = 'Manager who created the allocation.';
$string['privacy:metadata:allocations:userid']     = 'Learner the course is assigned to.';
$string['privacy:metadata:allocations:courseid']   = 'Course allocated.';
$string['privacy:metadata:allocations:due_date']   = 'Optional deadline.';
$string['privacy:metadata:allocations:status']     = 'assigned | in_progress | completed | overdue | cancelled.';
$string['privacy:metadata:allocations:note']       = 'Manager note attached to the allocation.';
$string['privacy:metadata:allocations:timecreated'] = 'When the allocation was created.';
