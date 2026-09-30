<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Recompletion';

// Navigation.
$string['rules']           = 'Recompletion rules';
$string['history']         = 'Reset history';
$string['bulkreset']       = 'Bulk reset';

// Capabilities.
$string['sentientia_recompletion:view']     = 'View recompletion rules and history';
$string['sentientia_recompletion:manage']   = 'Manage recompletion rules';
$string['sentientia_recompletion:reset']    = 'Manually reset user completions';

// Rule status.
$string['enabled']         = 'Enabled';
$string['disabled']        = 'Disabled';
$string['running']         = 'Running';

// Settings.
$string['settings_pre_notify_days']      = 'Pre-notification window (days)';
$string['settings_pre_notify_days_desc'] = 'Notify users this many days BEFORE their compliance is due to expire. Default 30.';
$string['settings_max_batch']            = 'Max users to reset per cron run';
$string['settings_max_batch_desc']       = 'Safety cap so a misconfigured rule cannot reset thousands of users in one cron pass. Default 500.';
$string['settings_dry_run_default']      = 'Dry-run mode (default OFF)';
$string['settings_dry_run_default_desc'] = 'When ON, the daily cron logs what WOULD be reset but does not actually reset anything. Useful for testing new rules.';

// Rule form.
$string['rule_name']            = 'Rule name';
$string['rule_courseid']        = 'Course (leave 0 for all courses with completion enabled)';
$string['rule_period_days']     = 'Reset period (days)';
$string['rule_period_days_help'] = 'Reset completion every N days. 365 = annual, 90 = quarterly. Set to 0 to disable.';
$string['rule_trigger']         = 'Trigger';
$string['rule_trigger_completion'] = 'N days after completion';
$string['rule_trigger_enrolment']  = 'N days after enrolment';
$string['rule_trigger_fixed']      = 'On a fixed calendar date';
$string['rule_fixed_date']      = 'Fixed date (if trigger = fixed)';
$string['rule_reset_grades']    = 'Also reset grades?';
$string['rule_reset_attempts']  = 'Also reset quiz attempts?';
$string['rule_enabled']         = 'Enabled';

// Messages.
$string['messageprovider:recompletion_due_soon'] = 'Recompletion due soon';
$string['messageprovider:recompletion_reset']    = 'Recompletion reset (completed)';
$string['msg_reset_subject'] = 'Recompletion: \'{$a->course}\' has been reset';
$string['msg_reset_body']    = 'Your previous completion of \'{$a->course}\' was on {$a->previous}. Per the {$a->days}-day recompletion rule, you\'ll need to complete it again to maintain compliance.';
$string['msg_due_subject']   = 'Recompletion due in {$a->days} days: \'{$a->course}\'';
$string['msg_due_body']      = 'Heads up — your completion of \'{$a->course}\' will expire in {$a->days} day(s). Plan to redo it before then to maintain compliance.';

// P1 #20 (2026-05-16) — event class names (shown on the Event monitor +
// reports filters). Closes audit item #19.
$string['event_completion_reset'] = 'Course completion reset';

// UI.
$string['nrules']              = '{$a} rules';
$string['rules_empty']         = 'No recompletion rules configured yet.';
$string['history_empty']       = 'No resets performed yet.';
$string['no_courses_resetable'] = 'No courses with completion tracking enabled — recompletion needs course completion configured.';

// Reset history page (ADR-032).
$string['history_title']        = 'Recompletion history';
$string['history_back']         = 'Back to rules';
$string['history_prev']         = 'Prev';
$string['history_next']         = 'Next';
$string['history_filtered']     = 'Showing one course or learner only.';
$string['history_clear_filter'] = 'Show every reset';
$string['hcol_when']            = 'When';
$string['hcol_user']            = 'User';
$string['hcol_course']          = 'Course';
$string['hcol_reason']          = 'Reason';
$string['hcol_previous']        = 'Previous completion';
$string['hcol_grades']          = 'Grades reset?';
$string['hcol_attempts']        = 'Attempts reset?';
$string['badge_dryrun']         = 'dry-run';
$string['badge_legacy']         = 'Legacy';
$string['badge_self']           = 'self';
$string['badge_deleted_user']   = 'Deleted user';
$string['badge_inferred']       = 'estimated';
$string['badge_inferred_title'] = 'The log row of this reset was not found, so its time is worked out from the completion it ended.';
$string['link_evidence']        = 'Evidence';

// Imported rules (ADR-032).
$string['badge_legacy_rule']    = 'Imported from BizLMS';
$string['legacy_settings']      = 'BizLMS settings of this course';
$string['legacy_dead_scorm']    = 'This course only had the old SCORM setting name (deletescormdata), which the BizLMS plugin never read: it did nothing for SCORM.';
$string['legacy_days']          = '{$a} days';
$string['legacy_choice_0']      = 'nothing';
$string['legacy_choice_1']      = 'delete';
$string['legacy_choice_2']      = 'allow an extra attempt';
$string['legacy_enable']                  = 'Recompletion was on';
$string['legacy_recompletionduration']    = 'Duration';
$string['legacy_deletegradedata']         = 'Delete grades';
$string['legacy_archivecompletiondata']   = 'Archive completion data';
$string['legacy_quiz']                    = 'Quiz attempts';
$string['legacy_archivequiz']             = 'Archive quiz attempts';
$string['legacy_scorm']                   = 'SCORM';
$string['legacy_archivescorm']            = 'Archive SCORM data';
$string['legacy_assign']                  = 'Assignments';
$string['legacy_lti']                     = 'LTI grades';
$string['legacy_archivelti']              = 'Archive LTI grades';
$string['legacy_questionnaire']           = 'Questionnaire responses';
$string['legacy_archivequestionnaire']    = 'Archive questionnaire responses';
$string['legacy_pulse']                   = 'Pulse notifications';
$string['legacy_recompletionemailenable'] = 'E-mail the learner on reset';

// Evidence view (ADR-032).
$string['evidence_title']          = 'Reset evidence';
$string['evidence_back']           = 'Back to history';
$string['evidence_learner']        = 'Learner';
$string['evidence_course']         = 'Course';
$string['evidence_reset_at']       = 'Reset at';
$string['evidence_reason']         = 'Reason';
$string['evidence_reset_by']       = 'Reset by';
$string['evidence_previous']       = 'Completed before the reset';
$string['evidence_grades_reset']   = 'Grades reset';
$string['evidence_attempts_reset'] = 'Quiz attempts reset';
$string['evidence_inferred']       = 'estimated';
$string['evidence_inferred_note']  = 'The time of this reset is an estimate. The log row of the reset was not found, so the time is the earlier of the completion plus the rule period and the first evidence of the next cycle.';
$string['evidence_unattached_note'] = 'This evidence could not be tied to a reset: the log row of the reset was purged and no archived completion was left to work one out from.';
$string['evidence_none']           = 'No evidence was archived for this reset.';
$string['evidence_more']           = '{$a} more rows are not shown.';
$string['evidence_redacted']       = '(redacted)';
$string['evidence_course_gone']    = '(deleted course)';
$string['evidence_scheduled']      = 'the scheduled task';
$string['evidence_self']           = 'the learner';
$string['evidence_admin']          = 'an administrator';
$string['evidence_view_off']       = 'The reset evidence view is not switched on.';
$string['evidence_not_found']      = 'There is no such reset, or it belongs to another tenant.';
$string['evidence_type_course_completion']      = 'Course completion';
$string['evidence_type_criteria_completion']    = 'Completion criteria';
$string['evidence_type_activity_completion']    = 'Activity completions';
$string['evidence_type_quiz_attempt']           = 'Quiz attempts';
$string['evidence_type_quiz_grade']             = 'Quiz grades';
$string['evidence_type_scorm_track']            = 'SCORM tracking';
$string['evidence_type_lti_grade']              = 'LTI grades';
$string['evidence_type_questionnaire_response'] = 'Questionnaire responses';
$string['evidence_type_questionnaire_answer']   = 'Questionnaire answers';
$string['evidence_type_gradebook_grade']        = 'Grades';
$string['col_state']         = 'State';
$string['col_enrolled']      = 'Enrolled';
$string['col_started']       = 'Started';
$string['col_completed']     = 'Completed';
$string['col_criteria']      = 'Criteria';
$string['col_grade']         = 'Grade';
$string['col_when']          = 'When';
$string['col_activity']      = 'Activity';
$string['col_quiz']          = 'Quiz';
$string['col_attempt']       = 'Attempt';
$string['col_marks']         = 'Marks';
$string['col_scorm']         = 'SCORM';
$string['col_element']       = 'Element';
$string['col_value']         = 'Value';
$string['col_tool']          = 'LTI tool';
$string['col_questionnaire'] = 'Questionnaire';
$string['col_question']      = 'Question';
$string['col_answer']        = 'Answer';
$string['col_item']          = 'Grade item';

// Privacy.
$string['privacy:metadata:local_sentientia_recompletion_rules'] = 'Recompletion rule definitions';
$string['privacy:metadata:local_sentientia_recompletion_history'] = 'Per-user reset audit log';
$string['privacy:metadata:local_sentientia_recompletion_history:userid'] = 'The user whose completion was reset';
$string['privacy:metadata:local_sentientia_recompletion_history:courseid'] = 'The course that was reset';
$string['privacy:metadata:local_sentientia_recompletion_history:reason'] = 'Why the reset fired';
$string['privacy:metadata:local_sentientia_recompletion_history:reset_by_userid'] = 'The administrator who reset another person\'s completion (empty when the scheduled task did it)';
$string['privacy:metadata:local_sentientia_recompletion_history:previous_timecompleted'] = 'When the user last completed the course before it was reset';
$string['privacy:metadata:local_sentientia_recompletion_history:timecreated'] = 'When the reset happened';
$string['privacy:metadata:local_sentientia_recompletion_history:source'] = 'Whether the reset was made by Sentientia or imported from the BizLMS plugin';
$string['privacy:metadata:local_sentientia_recompletion_archive'] = 'The evidence a reset deleted: the completion, criteria, activity completions, quiz attempts and grades, SCORM tracking, LTI grades and questionnaire answers of the cycle it ended';
$string['privacy:metadata:local_sentientia_recompletion_archive:userid'] = 'The user whose evidence it is';
$string['privacy:metadata:local_sentientia_recompletion_archive:courseid'] = 'The course the evidence belongs to';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemtype'] = 'What kind of evidence the row is';
$string['privacy:metadata:local_sentientia_recompletion_archive:cmid'] = 'The activity the evidence belongs to';
$string['privacy:metadata:local_sentientia_recompletion_archive:instanceid'] = 'The quiz, SCORM package, LTI tool, questionnaire, criterion or grade item the evidence belongs to';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemkey'] = 'The SCORM element or the questionnaire question';
$string['privacy:metadata:local_sentientia_recompletion_archive:state'] = 'The completion state, attempt state or answer';
$string['privacy:metadata:local_sentientia_recompletion_archive:grade'] = 'The grade, marks or score';
$string['privacy:metadata:local_sentientia_recompletion_archive:timeevent'] = 'When the archived thing happened';
$string['privacy:metadata:local_sentientia_recompletion_archive:payload'] = 'The archived row exactly as it was, including the administrator who overrode an activity completion and what the learner typed into a questionnaire';
$string['privacy:metadata:local_sentientia_recompletion_archive:timecreated'] = 'When the evidence was archived';
$string['privacy:export:resets_performed'] = 'Resets I performed';
$string['privacy:export:evidence'] = 'Evidence archived by resets';
