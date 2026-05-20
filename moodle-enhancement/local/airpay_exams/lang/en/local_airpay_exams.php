<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Online Exams';
$string['airpay_exams:manage'] = 'Manage online exams';
$string['airpay_exams:view'] = 'View online exams';
$string['airpay_exams:enrol'] = 'Enrol users into exams (via parent quiz course)';

// CRUD strings.
$string['addexam'] = 'Register Exam';
$string['editexam'] = 'Edit Exam';
$string['deleteexam'] = 'Unregister Exam';
$string['activateexam'] = 'Activate Exam';
$string['deactivateexam'] = 'Deactivate Exam';

// Form sections.
$string['heading_basic'] = 'Quiz Selection';
$string['heading_settings'] = 'Exam Settings';
$string['heading_org'] = 'Organisation';

// Form labels.
$string['quiz'] = 'Underlying quiz';
$string['quiz_help'] = 'Pick an existing Moodle quiz activity to register as an enterprise exam. Only quizzes not already registered are shown. Build the quiz first in its course (Add activity > Quiz), then register it here.';
$string['exam_name'] = 'Exam display name';
$string['exam_name_help'] = 'A human-friendly name shown in reports and dashboards. May differ from the underlying quiz name.';
$string['duration'] = 'Time limit (seconds)';
$string['duration_help'] = 'Override the quiz time limit at the exam level. Leave blank to use the quiz default. 1800 = 30 minutes, 3600 = 1 hour.';
$string['passinggrade'] = 'Passing grade (%)';
$string['organisation'] = 'Organisation (tenant)';
$string['exam_active'] = 'Exam is active';

// P1 #23 (2026-05-16) — exam categories. Closes audit item #12 from
// parity-audit-2026-05-15/airpay_exams.md.
$string['exam_category']      = 'Category';
$string['exam_category_help'] = 'Tag this exam for discovery. Reuses the same category taxonomy as courses (set up at Site administration ▶ Courses ▶ Manage course categories) so admins can group exams next to the training that prepares for them. Common groupings: Compliance, Sales, Leadership, Onboarding. Leave as <em>Uncategorised</em> if the exam is one-off.';
$string['uncategorised']      = '— Uncategorised —';

// Errors.
$string['missingrequiredfields'] = 'Please pick a quiz and provide a display name.';
$string['invalidquiz'] = 'The selected quiz no longer exists.';
$string['invalidcategory'] = 'The selected category no longer exists.';
$string['quizalreadyregistered'] = 'This quiz is already registered as an exam. Edit the existing exam instead.';
$string['duration_invalid'] = 'Duration must be 0 or a positive number of seconds.';
$string['passinggrade_invalid'] = 'Passing grade must be between 0 and 100.';
$string['confirmdelete'] = 'Unregister exam "{$a}"? The underlying Moodle quiz will NOT be deleted — it stays in its course. Only the enterprise exam metadata (tenant, display name, status) is removed.';
$string['confirmactivate'] = 'Activate "{$a}"? Learners will see it as an available exam.';
$string['confirmdeactivate'] = 'Deactivate "{$a}"? Learners will not see it but data is retained.';

// Success.
$string['examcreated'] = 'Exam registered.';
$string['examupdated'] = 'Exam updated.';
$string['examdeleted'] = 'Exam unregistered (underlying quiz preserved).';
$string['examstatuschanged'] = 'Exam status updated.';

// Index page.
$string['noexams_subtitle'] = 'Register existing Moodle quizzes as enterprise exams to add tenant scoping, custom passing grades, and dashboard reporting.';

// Privacy.
$string['privacy:metadata'] = 'The Airpay airpay_exams plugin does not store personal data in plugin-owned tables.';

// P1 #33 (2026-05-20) — deadline-reminder cron. Closes audit item #16
// from parity-audit-2026-05-15/airpay_exams.md.
$string['task_exam_reminder']              = 'Exam deadline reminder';
$string['messageprovider:exam_reminder']   = 'Exam deadline reminder';

$string['reminder_settings_heading']       = 'Exam deadline reminders (cron)';
$string['reminder_settings_intro']         = 'Daily scheduled task that nudges learners whose exam deadline is approaching. Deadline source: <code>quiz.timeclose</code> (the calendar timestamp set on the wrapping quiz). The task is disabled by default; opt in via <code>reminder_enabled</code> below, then enable the task at <em>Site administration ▶ Server ▶ Scheduled tasks</em>. Default schedule: 09:15 daily.';
$string['reminder_enabled']                = 'Enable exam reminders';
$string['reminder_enabled_help']           = 'When OFF (default) the task is a no-op. When ON it fires daily, sends notifications to learners approaching exam timeclose, and dedupes via <code>local_airpay_exams_remind_sent</code>.';
$string['reminder_days_before']            = 'Reminder buckets (days before timeclose)';
$string['reminder_days_before_help']       = 'Comma-separated list of days-before-deadline thresholds. <code>7,3,1</code> nudges learners 7 days out, again at 3 days, and finally 1 day before. Bucket assignment is monotonic — a learner at 5 days out hits the "7" bucket once.';
$string['reminder_max_per_run']            = 'Max notifications per run';
$string['reminder_max_per_run_help']       = 'Safety cap so a misconfigured rule cannot mailbomb every learner in one cron tick. Default 500. Untreated learners roll over to the next run; the unique index dedupes.';
$string['reminder_status']                 = 'Last run';
$string['reminder_last_run_value']         = 'Last successful run: <strong>{$a->time}</strong>. Notifications sent in that run: <strong>{$a->count}</strong>.';
$string['reminder_last_run_never']         = 'The exam-deadline-reminder cron has never run. Enable the task and check back after 09:15 server time, or run <code>php admin/cli/scheduled_task.php --execute=\\\\local_airpay_exams\\\\task\\\\exam_reminder</code> from the command line to fire it manually.';

// Reminder message body — rendered by the cron task.
$string['reminder_subject']      = 'Reminder: exam "{$a->examname}" is due in {$a->days_remaining} day(s)';
$string['reminder_small']        = '"{$a->examname}" due in {$a->days_remaining} day(s)';
$string['reminder_body_plain']   = 'Hi,

This is a reminder that the exam "{$a->examname}" (course: {$a->coursename}) closes on {$a->deadline} — {$a->days_remaining} day(s) from now.

Sit the exam here: {$a->exam_url}

— Airpay Academy';
$string['reminder_body_html']    = '<p>Hi,</p><p>This is a reminder that the exam <strong>{$a->examname}</strong> (course: {$a->coursename}) closes on <strong>{$a->deadline}</strong> — {$a->days_remaining} day(s) from now.</p><p><a href="{$a->exam_url}">Sit the exam</a></p><p style="color:#777;">— Airpay Academy</p>';

// P1 #34 (2026-05-20) — overdue manager-escalation cron. Closes audit
// item #17 (the sibling of #16 in parity-audit-2026-05-15/airpay_exams.md).
$string['task_exam_overdue']               = 'Exam overdue — manager escalation';
$string['messageprovider:exam_overdue_supervisor'] = 'Exam overdue — supervisor escalation';

$string['overdue_settings_heading']        = 'Overdue manager escalation (cron)';
$string['overdue_settings_intro']          = 'Sibling of the exam reminder. When a learner misses an exam\'s <code>quiz.timeclose</code>, this task notifies their direct supervisor (<code>user.open_supervisorid</code>). Learners with no supervisor are skipped. Reuses the <code>local_airpay_exams_remind_sent</code> table with NEGATIVE <code>days_before_deadline</code> values to mark post-deadline rows. Default schedule: 09:45 daily.';
$string['overdue_enabled']                 = 'Enable supervisor escalation';
$string['overdue_enabled_help']            = 'When OFF (default) the task is a no-op. When ON it fires daily, escalates overdue learners to their supervisor, and dedupes against the same table the reminder task uses.';
$string['overdue_days_after']              = 'Escalation buckets (days after deadline)';
$string['overdue_days_after_help']         = 'Comma-separated list of days-after-deadline thresholds. <code>1,7,14</code> escalates 1 / 7 / 14 days past quiz.timeclose, then stops.';
$string['overdue_max_per_run']             = 'Max escalations per run';
$string['overdue_max_per_run_help']        = 'Safety cap so a misconfigured rule cannot mailbomb every manager in one cron tick. Default 500.';
$string['overdue_status']                  = 'Last overdue run';
$string['overdue_last_run_value']          = 'Last successful run: <strong>{$a->time}</strong>. Escalations sent: <strong>{$a->count}</strong>.';
$string['overdue_last_run_never']          = 'The exam supervisor-escalation cron has never run. Enable it and check back, or run <code>php admin/cli/scheduled_task.php --execute=\\\\local_airpay_exams\\\\task\\\\exam_overdue</code> manually.';

$string['overdue_subject']      = '{$a->learner_name} is {$a->days_past} day(s) overdue on exam "{$a->exam_name}"';
$string['overdue_small']        = '{$a->learner_name} overdue on {$a->exam_name}';
$string['overdue_body_plain']   = 'Hi,

Your team member {$a->learner_name} missed the deadline for exam "{$a->exam_name}" (course: {$a->coursename}).

Deadline: {$a->deadline} ({$a->days_past} day(s) ago).

View exam:    {$a->exam_url}
View learner: {$a->learner_profile_url}

Please follow up with them.

— Airpay Academy';
$string['overdue_body_html']    = '<p>Hi,</p><p>Your team member <strong>{$a->learner_name}</strong> missed the deadline for exam <strong>{$a->exam_name}</strong> (course: {$a->coursename}).</p><p>Deadline: <strong>{$a->deadline}</strong> ({$a->days_past} day(s) ago).</p><ul><li><a href="{$a->exam_url}">View exam</a></li><li><a href="{$a->learner_profile_url}">View learner profile</a></li></ul><p>Please follow up with them.</p><p style="color:#777;">— Airpay Academy</p>';
