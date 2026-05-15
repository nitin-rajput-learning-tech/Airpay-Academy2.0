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

// G-02: View detail page + tabs.
$string['view_classroom_title']      = 'Classroom: {$a}';
$string['back_to_classrooms']        = 'Back to classrooms';
$string['tab_overview']              = 'Overview';
$string['tab_sessions']              = 'Sessions';
$string['tab_users']                 = 'Users';
$string['tab_attendance']            = 'Attendance';
$string['no_description']            = 'No description set.';
$string['updated']                   = 'Updated';

// Sessions tab.
$string['add_session']               = 'Add Session';
$string['edit_session']              = 'Edit Session';
$string['delete_session']            = 'Delete Session';
$string['session_title']             = 'Session title';
$string['session_date']              = 'Date';
$string['session_time']              = 'Time';
$string['session_starttime']         = 'Start time';
$string['session_endtime']           = 'End time';
$string['session_duration']          = 'Duration';
$string['session_location']          = 'Session location';
$string['session_trainer']           = 'Trainer (overrides classroom default)';
$string['session_notes']             = 'Notes';
$string['session_minutes']           = '{$a} min';
$string['no_sessions']               = 'No sessions scheduled yet.';

// W1-7 (2026-05-15) — virtual meeting + recording URLs on sessions.
$string['session_virtual_header']    = 'Virtual session links';
$string['session_meeting_url']       = 'Live meeting URL';
$string['session_meeting_url_help']  = 'Optional. Paste the Zoom / Teams / Webex / Google Meet join link. Attendees will see a "Join live session" button on the session listing. Leave empty for in-person sessions.';
$string['session_recording_url']     = 'Recording URL';
$string['session_recording_url_help'] = 'Optional. Paste the post-session recording URL. Attendees who missed the live session can watch the replay. Add this after the session ends.';

// W1-9 (2026-05-15) — event names.
$string['event_classroom_completed'] = 'Classroom completed';
$string['session_created']           = 'Session scheduled.';
$string['session_updated']           = 'Session updated.';
$string['sessiondeleted']            = 'Session deleted.';
$string['confirm_delete_session']    = 'Delete the session "{$a}"? This will also remove any attendance recorded for it. This cannot be undone.';
$string['invalidsessiontime']        = 'Session start and end times are required.';
$string['endbeforestart']            = 'Session end time must be after start time.';

// Users / roster tab.
$string['enrol_users']               = 'Enrol Users';
$string['unenrol_user']              = 'Remove from classroom';
$string['no_users_enrolled']         = 'No users enrolled in this classroom yet.';
$string['users_enrolled_count']      = '{$a} users enrolled';
$string['confirm_unenrol_user']      = 'Remove {$a} from this classroom? Their attendance for all sessions of this classroom will also be cleared.';
$string['userunenrolled']            = 'User removed from classroom.';
$string['users_enrolled_success']    = '{$a} user(s) enrolled.';

// Attendance.
$string['attendance_for_session']    = 'Attendance for: {$a}';
$string['mark_all_present']          = 'Mark all present';
$string['save_attendance']           = 'Save attendance';
$string['attendance_status_absent']  = 'Absent';
$string['attendance_status_present'] = 'Present';
$string['attendance_status_late']    = 'Late';
$string['attendance_status_excused'] = 'Excused';
$string['attendancemarked']          = 'Attendance updated.';
$string['attendance_summary']        = 'Present: {$a->present} · Late: {$a->late} · Excused: {$a->excused} · Absent: {$a->absent}';
$string['no_attendance_yet']         = 'Roster is empty — enrol users first to mark attendance.';
$string['invalidattendancestatus']   = 'Invalid attendance status.';
$string['toomanymarks']              = 'Too many attendance records in one request (limit 1000).';

// Bounds — note: do NOT add a `filterstoolong` translation here; existing
// list_classrooms_test::test_json_filter_rejects_oversized_payload greps
// for the [[filterstoolong]] placeholder (Moodle's missing-string fallback).
// Refactor that test to check $e->errorcode before adding the translation.

// Privacy strings (Phase Z.1).
$string['privacy:metadata:roster'] = 'Per-classroom user roster (who is enrolled in which classroom).';
$string['privacy:metadata:roster:classroomid'] = 'Classroom ID.';
$string['privacy:metadata:roster:userid'] = 'Enrolled user ID.';
$string['privacy:metadata:roster:timecreated'] = 'Enrolment timestamp.';
$string['privacy:metadata:attendance'] = 'Per-session attendance records.';
$string['privacy:metadata:attendance:sessionid'] = 'Session ID.';
$string['privacy:metadata:attendance:userid'] = 'Attendee user ID.';
$string['privacy:metadata:attendance:status'] = 'Attendance status (present/absent/late).';
$string['privacy:metadata:attendance:markedat'] = 'When the attendance was marked.';
$string['privacy:metadata:attendance:markedby'] = 'ID of the user who marked attendance.';
