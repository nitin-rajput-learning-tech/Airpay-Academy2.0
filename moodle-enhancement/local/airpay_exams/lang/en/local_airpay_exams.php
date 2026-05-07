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

// Errors.
$string['missingrequiredfields'] = 'Please pick a quiz and provide a display name.';
$string['invalidquiz'] = 'The selected quiz no longer exists.';
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
