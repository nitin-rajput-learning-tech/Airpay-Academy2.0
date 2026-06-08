<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings — Airpay Course Engine.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Course Engine';

// Capabilities.
$string['sentientia_courses:manage'] = 'Manage courses';
$string['sentientia_courses:enrol'] = 'Enrol users into courses';
$string['sentientia_courses:view'] = 'View course management';
$string['sentientia_courses:create'] = 'Create courses';
$string['sentientia_courses:update'] = 'Edit courses';
$string['sentientia_courses:delete'] = 'Delete courses';
$string['sentientia_courses:visibility'] = 'Show or hide courses';

// CRUD form strings.
$string['addcourse'] = 'Add Course';
$string['editcourse'] = 'Edit Course';
$string['deletecourse'] = 'Delete Course';
$string['hidecourse'] = 'Hide Course';
$string['showcourse'] = 'Show Course';

// Form section headings.
$string['heading_basic'] = 'Basic Information';
$string['heading_category'] = 'Category & Organisation';
$string['heading_summary'] = 'Description';
$string['heading_format'] = 'Format & Visibility';

// Form field labels.
$string['fullname'] = 'Course full name';
$string['shortname'] = 'Course short name';
$string['shortname_help'] = 'Unique short identifier — used in URLs and reports.';
$string['idnumber'] = 'Course ID number';
$string['category'] = 'Course category';
$string['organisation'] = 'Organisation (tenant)';
$string['summary'] = 'Course description';
$string['courseformat'] = 'Course format';
$string['format_topics'] = 'Topics format';
$string['format_weeks'] = 'Weekly format';
$string['format_single'] = 'Single activity';
$string['format_social'] = 'Social format';
$string['numsections'] = 'Number of sections';
$string['visibility'] = 'Visible to learners';
$string['startdate'] = 'Start date';
$string['enddate'] = 'End date';

// P1 #21 (2026-05-16) — completion deadline. Closes audit item #28 from
// parity-audit-2026-05-15/sentientia_courses.md.
$string['coursecompletiondays']      = 'Completion deadline (days from enrolment)';
$string['coursecompletiondays_help'] = 'Number of days from enrolment by which learners must complete this course. Read by <code>course_manager::get_completion_deadline()</code> and used by the reminder workflow to decide when to nudge learners. Set to <code>0</code> for no deadline. Examples: <code>30</code> for monthly compliance modules, <code>180</code> for half-yearly refreshers, <code>365</code> for annual recertification.';
$string['coursecompletiondays_invalid'] = 'Completion deadline must be 0 or a positive number of days.';

// Error messages.
$string['missingrequiredfields'] = 'Please fill in all required fields.';
$string['shortnametaken'] = 'This short name is already in use. Please choose another.';
$string['enddatebeforestart'] = 'End date must be after start date.';
$string['cannotdeletesitecourse'] = 'The site course cannot be deleted.';
$string['confirmdelete'] = 'Are you sure you want to delete "{$a}"? This will permanently remove the course and all enrolments, activities, and grades. This cannot be undone.';
$string['confirmhide'] = 'Are you sure you want to hide "{$a}"? Learners will no longer see this course.';
$string['confirmshow'] = 'Are you sure you want to make "{$a}" visible to learners?';

// Success messages.
$string['coursecreated'] = 'Course created successfully.';
$string['courseupdated'] = 'Course updated successfully.';
$string['coursedeleted'] = 'Course deleted.';
$string['coursehidden'] = 'Course hidden.';
$string['courseshown'] = 'Course visible.';

// Privacy.
$string['privacy:metadata'] = 'The Airpay sentientia_courses plugin does not store personal data in plugin-owned tables; user state lives on core Sentientia LMS tables exported by their respective providers.';

// Sprint C (2026-05-13) — cross-tenant sharing.
$string['sentientia_courses:share_to_tenant'] = 'Share a course to other tenants';
$string['event_course_share_created'] = 'Airpay: course shared to tenant';
$string['event_course_share_withdrawn'] = 'Airpay: course share withdrawn from tenant';
$string['share_saved'] = 'Share settings saved. The catalog will refresh for affected tenants within a few seconds.';
$string['invalidparameter'] = 'One or more parameters is invalid.';

// Sprint D (2026-05-13) — pull/request workflow.
$string['sentientia_courses:request_course'] = 'Request a course be shared to my tenant';
$string['sentientia_courses:approve_request'] = 'Approve / reject share-requests from other tenants';
$string['event_course_share_requested'] = 'Airpay: course-share requested';
$string['event_course_share_request_approved'] = 'Airpay: course-share request approved';
$string['event_course_share_request_rejected'] = 'Airpay: course-share request rejected';
$string['request_filed'] = 'Your request has been filed. An Airpay administrator will review it shortly.';
$string['request_approved'] = 'Request approved. The course is now in the requesting tenant\'s catalogue.';
$string['request_rejected'] = 'Request rejected. The requester will see the decision in their outbox.';
$string['invalidtenant'] = 'Could not determine your tenant — your user account has no organisation path.';
$string['invaliduser']  = 'The requesting user account is no longer active.';
$string['invalidcourse'] = 'No such course.';
$string['cannotrequestowncourse'] = 'You cannot request a course your tenant already owns.';

// P1 #28 (2026-05-20) — learner deadline-reminder cron. Closes audit
// item #14 from parity-audit-2026-05-15/sentientia_courses.md.
$string['task_course_reminder']        = 'Course deadline reminder';
$string['messageprovider:course_reminder'] = 'Course deadline reminder';

$string['reminder_settings_heading']   = 'Course deadline reminders (cron)';
$string['reminder_settings_intro']     = 'Daily scheduled task that nudges learners whose course deadline is approaching. Deadlines are computed from <code>enrolment.timestart + course.open_coursecompletiondays × 86400</code> — the field exposed on the edit-course form. The task is disabled by default; once you opt in, enable it from <em>Site administration ▶ Server ▶ Scheduled tasks</em>. Default schedule: 09:00 daily.';
$string['reminder_enabled']            = 'Enable deadline reminders';
$string['reminder_enabled_help']       = 'When OFF (default) the scheduled task is a no-op. When ON the task fires every day, sends notifications to learners approaching their deadline, and records each send in <code>local_sentientia_courses_remind_sent</code> for de-dupe.';
$string['reminder_days_before']        = 'Reminder buckets (days before deadline)';
$string['reminder_days_before_help']   = 'Comma-separated list of days-before-deadline thresholds. <code>7,3,1</code> nudges learners 7 days out, again at 3 days, and a final ping 1 day before the deadline. Empty list = no reminders. Bucket assignment is monotonic — a learner at 5 days out hits the "7" bucket only once.';
$string['reminder_max_per_run']        = 'Max notifications per run';
$string['reminder_max_per_run_help']   = 'Safety cap so a misconfigured rule cannot mailbomb 50 000 users in one cron tick. Default 500. Untreated learners roll over to the next cron run; the unique index dedupes so nothing is lost.';
$string['reminder_status']             = 'Last run';
$string['reminder_last_run_value']     = 'Last successful run: <strong>{$a->time}</strong>. Notifications sent in that run: <strong>{$a->count}</strong>. (Lifetime audit available via <code>SELECT * FROM mdl_local_sentientia_courses_remind_sent ORDER BY timesent DESC</code>.)';
$string['reminder_last_run_never']     = 'The deadline-reminder cron has never run. Enable the task and check back after 09:00 server time tomorrow, or run <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_courses\\\\task\\\\course_reminder</code> from the command line to fire it manually.';

// Reminder message body — rendered by the cron task itself.
$string['reminder_subject']      = 'Reminder: "{$a->fullname}" is due in {$a->days_remaining} day(s)';
$string['reminder_small']        = '"{$a->fullname}" due in {$a->days_remaining} day(s)';
$string['reminder_body_plain']   = 'Hi,

This is a reminder that your course "{$a->fullname}" is due to be completed by {$a->deadline} ({$a->days_remaining} day(s) from now).

Pick it up here: {$a->course_url}

— Airpay Academy';
$string['reminder_body_html']    = '<p>Hi,</p><p>This is a reminder that your course <strong>{$a->fullname}</strong> is due to be completed by <strong>{$a->deadline}</strong> ({$a->days_remaining} day(s) from now).</p><p><a href="{$a->course_url}">Continue the course</a></p><p style="color:#777;">— Airpay Academy</p>';

// Phase B.3.a (2026-05-21) — push notification version of the reminder.
// Push title ~ <= 50 chars, body <= 120 chars to render cleanly on
// Chrome/Firefox/Edge notification toasts AND on iOS lock-screen.
$string['reminder_push_title']   = 'Course due in {$a->days_remaining} day(s)';
$string['reminder_push_body']    = '"{$a->fullname}" — finish by {$a->deadline}. Tap to continue.';

// P1 #29 (2026-05-20) — overdue manager-escalation cron. Closes audit
// item #15 from parity-audit-2026-05-15/sentientia_courses.md.
$string['task_course_overdue']             = 'Course overdue — manager escalation';
$string['messageprovider:course_overdue_supervisor'] = 'Course overdue — supervisor escalation';

$string['overdue_settings_heading']        = 'Overdue manager escalation (cron)';
$string['overdue_settings_intro']          = 'Sibling of the deadline reminder. When a learner misses their course deadline, this task notifies their direct supervisor (<code>user.open_supervisorid</code>). Learners with no supervisor on file are skipped — there\'s nobody to escalate to. The task is disabled by default; opt in via <code>overdue_enabled</code> and enable the scheduled task from <em>Site administration ▶ Server ▶ Scheduled tasks</em>. Default schedule: 09:30 daily.';
$string['overdue_enabled']                 = 'Enable supervisor escalation';
$string['overdue_enabled_help']            = 'When OFF (default), the task is a no-op. When ON, the task fires daily, escalates overdue learners to their supervisor, and de-dupes against the same <code>local_sentientia_courses_remind_sent</code> table the reminder task uses (negative <code>days_before_deadline</code> values mark post-deadline rows).';
$string['overdue_days_after']              = 'Escalation buckets (days after deadline)';
$string['overdue_days_after_help']         = 'Comma-separated list of days-after-deadline thresholds. <code>1,7,14</code> escalates the supervisor 1 day after the learner missed, again at 7 days, and a final ping at 14 days. After the widest bucket, the cron stops nagging.';
$string['overdue_max_per_run']             = 'Max escalations per run';
$string['overdue_max_per_run_help']        = 'Safety cap so a misconfigured rule cannot mailbomb every manager in one cron tick. Default 500. Untreated learners roll over to the next cron run; the unique index dedupes.';
$string['overdue_status']                  = 'Last overdue run';
$string['overdue_last_run_value']          = 'Last successful run: <strong>{$a->time}</strong>. Escalations sent: <strong>{$a->count}</strong>.';
$string['overdue_last_run_never']          = 'The supervisor-escalation cron has never run. Enable the task and check back after 09:30 server time tomorrow, or run <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_courses\\\\task\\\\course_overdue</code> from the command line to fire it manually.';

// Escalation message body — rendered by the cron task itself.
$string['overdue_subject']      = '{$a->learner_name} is {$a->days_past} day(s) overdue on "{$a->course_name}"';
$string['overdue_small']        = '{$a->learner_name} overdue on {$a->course_name}';
// Phase B.3.b — push notification (short, mobile-friendly) for supervisors.
$string['overdue_push_title']   = '{$a->learner_name} — {$a->days_past}d overdue';
$string['overdue_push_body']    = 'Missed "{$a->course_name}" deadline ({$a->deadline}). Tap to follow up.';
$string['overdue_body_plain']   = 'Hi,

Your team member {$a->learner_name} missed the deadline for the course "{$a->course_name}".

Deadline: {$a->deadline} ({$a->days_past} day(s) ago).

View course:    {$a->course_url}
View learner:   {$a->learner_profile_url}

Please follow up with them so they can complete the course as soon as possible.

— Airpay Academy';
$string['overdue_body_html']    = '<p>Hi,</p><p>Your team member <strong>{$a->learner_name}</strong> missed the deadline for the course <strong>{$a->course_name}</strong>.</p><p>Deadline: <strong>{$a->deadline}</strong> ({$a->days_past} day(s) ago).</p><ul><li><a href="{$a->course_url}">View course</a></li><li><a href="{$a->learner_profile_url}">View learner profile</a></li></ul><p>Please follow up with them so they can complete the course as soon as possible.</p><p style="color:#777;">— Airpay Academy</p>';
