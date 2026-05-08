<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Airpay Manager Dashboard';
$string['myteam'] = 'My Team';
$string['teamlearning'] = 'Team Learning Dashboard';
$string['privacy:metadata'] = 'The manager dashboard plugin records enrolment requests + course allocations made by managers for compliance / audit purposes.';

// Capability descriptions.
$string['airpay_manager:view']     = 'View team dashboard + requests + allocations';
$string['airpay_manager:approve']  = 'Approve / reject enrolment requests';
$string['airpay_manager:allocate'] = 'Assign courses to direct reports';

// Phase B errors.
$string['duplicaterequest']       = 'You already have a pending request for this course.';
$string['alreadydecided']         = 'This request has already been decided.';
$string['notdirectreport']        = 'The selected user is not your direct report.';
$string['duplicateallocation']    = 'This user already has an allocation for that course.';
$string['manualenrolnotavailable'] = 'The course does not have manual enrolment enabled. Configure it in the course\'s enrolment methods.';
$string['filterstoolong']         = 'Filter blob exceeds 4 KB limit.';
