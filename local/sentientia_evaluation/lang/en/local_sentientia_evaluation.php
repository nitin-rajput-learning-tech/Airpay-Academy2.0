<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Evaluations';

// Capabilities.
$string['sentientia_evaluation:manage'] = 'Manage evaluation forms';
$string['sentientia_evaluation:respond'] = 'Respond to evaluation forms';

// W1-5 (2026-05-15) — observer + trigger queue.
$string['task_process_triggers'] = 'Process queued evaluation triggers';
$string['messageprovider:evaluation_invite'] = 'Evaluation invitation';
$string['invaliditemid']  = 'Invalid item ID for the trigger event';
$string['invalidratearea'] = 'Invalid rating area';

// P1 #19 (2026-05-16) — email-on-response admin notification.
$string['messageprovider:evaluation_response'] = 'Evaluation response — admin notification';

// CRUD strings.
$string['addevaluation'] = 'Create Evaluation Form';
$string['editevaluation'] = 'Edit Evaluation Form';
$string['deleteevaluation'] = 'Delete Evaluation';
$string['publishevaluation'] = 'Publish Evaluation';
$string['archiveevaluation'] = 'Archive Evaluation';
$string['draftevaluation'] = 'Move to Draft';

// Form section headings.
$string['heading_basic'] = 'Form Identity';
$string['heading_kirkpatrick'] = 'Evaluation Framework';
$string['heading_trigger'] = 'When to Send';
$string['heading_privacy'] = 'Privacy';
$string['heading_window'] = 'Availability window (optional)';

// Form labels.
$string['eval_name'] = 'Form name';
$string['description'] = 'Description';
$string['kirkpatrick_level'] = 'Kirkpatrick level';
$string['kirkpatrick_level_help'] = 'Donald Kirkpatrick\'s 4-level training evaluation model. Level 1 (reaction) is sent immediately; Level 3-4 (behaviour/results) typically need 30-90 days delay to measure on-the-job application.';
$string['trigger_event'] = 'Trigger event';
$string['days_after'] = 'Days after trigger';
$string['days_after_help'] = 'How many days to wait after the trigger event before sending the evaluation. 0 = send immediately. Common patterns: 0 (Level 1), 7 (Level 2), 30-60 (Level 3), 90-180 (Level 4).';
$string['organisation'] = 'Organisation (tenant)';
$string['anonymous'] = 'Collect responses anonymously';
$string['anonymous_help'] = 'When checked, responses are not linked to a specific user. Reduces social desirability bias for sensitive topics like POSH or culture surveys, but prevents follow-up with individual respondents.';
$string['status'] = 'Status';
$string['status_draft'] = 'Draft';
$string['status_active'] = 'Active';
$string['status_archived'] = 'Archived';

// Errors.
$string['missingrequiredfields'] = 'Please fill in all required fields.';
$string['invalidkirkpatricklevel'] = 'Invalid Kirkpatrick level (must be 1-4).';
$string['invalidtrigger'] = 'Invalid trigger event.';
$string['invalidstatus'] = 'Invalid status value.';
$string['days_after_invalid'] = 'Days after must be 0 or more.';
$string['confirmdelete'] = 'Delete evaluation "{$a}"? This will permanently remove the form, all its questions, and all submitted responses. This cannot be undone.';
$string['confirmpublish'] = 'Publish "{$a}"? It will start collecting responses based on the trigger event.';
$string['confirmarchive'] = 'Archive "{$a}"? It will stop collecting new responses but past data is preserved.';
$string['confirmdraft'] = 'Move "{$a}" back to draft? It will pause response collection.';

// Success.
$string['evaluationcreated'] = 'Evaluation form created.';
$string['evaluationupdated'] = 'Evaluation form updated.';
$string['evaluationdeleted'] = 'Evaluation deleted.';
$string['evaluationstatuschanged'] = 'Evaluation status updated.';

// Question builder.
$string['managequestions'] = 'Manage Questions';
$string['addquestion'] = 'Add Question';
$string['editquestion'] = 'Edit Question';
$string['deletequestion'] = 'Delete Question';
$string['question_type'] = 'Question type';
$string['question_type_help'] = 'Choose how learners will respond. Rating is best for L1/L2 evaluations; NPS for advocacy; multichoice for forced-choice scenarios; text for qualitative feedback.';
$string['question_text'] = 'Question text';
$string['question_options'] = 'Answer options (one per line)';
$string['question_options_help'] = 'For multiple choice questions only. Enter each option on a new line. Minimum 2 options required.';
$string['question_required'] = 'Required (learner must answer)';

// Question errors.
$string['invalidquestiontype'] = 'Invalid question type.';
$string['invalidevaluation'] = 'Evaluation form not found.';
$string['invalidquestion'] = 'Question not found.';
$string['multichoice_needs_options'] = 'Multiple choice questions need at least 2 options.';
$string['confirmdeletequestion'] = 'Delete this question? Any responses to it will also be removed from past submissions.';

// Question success.
$string['questioncreated'] = 'Question added.';
$string['questionupdated'] = 'Question updated.';
$string['questiondeleted'] = 'Question deleted.';
$string['orderupdated'] = 'Question order saved.';

// G-05: Analysis dashboard + filtered responses + CSV export.
$string['analysis_title']         = 'Evaluation Analysis';
$string['analysis_subtitle']      = 'Cross-evaluation Kirkpatrick aggregation';
$string['filter_from']            = 'Submitted from';
$string['filter_to']              = 'Submitted to';
$string['filter_courseid']        = 'Course ID';
$string['filter_programid']       = 'Program ID';
$string['filter_classroomid']     = 'Classroom ID';
$string['filter_apply']           = 'Apply filters';
$string['filter_clear']           = 'Clear';
$string['filter_subset_hint']     = 'Stats below reflect the filtered subset';
$string['export_csv']             = 'Export CSV';
$string['no_responses_filtered']  = 'No responses match the current filter';
$string['no_responses_filtered_help'] = 'Adjust the date range or context filters above, or wait for learners to submit evaluations.';
$string['avg_rating']             = 'Avg rating';
$string['nps_score']              = 'NPS score';
$string['stat_evaluations']       = 'Evaluations';
$string['stat_responses']         = 'Responses';

// Response submission (learner-facing) and admin viewer.
$string['viewresponses'] = 'View Responses';
$string['evaluationnotactive'] = 'This evaluation is not currently accepting responses.';
$string['alreadyresponded'] = 'You have already submitted this evaluation.';
$string['evaluationhasnoquestions'] = 'This evaluation has no questions yet.';

// P1 #17 (2026-05-16) — availability window + multiple-submit (pulse mode).
$string['timeopen']            = 'Open from';
$string['timeopen_help']       = 'Optional. Earliest date learners can submit a response. Leave the checkbox unticked to open the evaluation immediately. Use this for "compliance survey runs from 1 June to 30 June" workflows.';
$string['timeclose']           = 'Close at';
$string['timeclose_help']      = 'Optional. Date the evaluation stops accepting new responses (existing responses are preserved). Leave unticked to keep the evaluation open indefinitely. Must be on or after the open date.';
$string['multiple_submit']     = 'Allow the same user to submit multiple responses';
$string['multiple_submit_help'] = 'Tick to allow pulse-style surveys where the same learner re-submits over time (weekly engagement check, monthly compliance tick, etc.). When unticked (default), each user gets exactly one submission. Anonymous evaluations always allow re-submission regardless of this setting.';
$string['eval_window_inverted'] = 'Close date must be on or after the open date.';
$string['evaluationnotyetopen'] = 'This evaluation opens on {$a} — please come back then.';
$string['evaluationclosed']     = 'This evaluation closed on {$a}.';
$string['eval_notyetopen_heading'] = 'Not yet open';
$string['eval_notyetopen_body']    = 'This evaluation opens on {$a}. Please come back then to submit your response.';
$string['eval_closed_heading']     = 'Evaluation closed';
$string['eval_closed_body']        = 'This evaluation closed on {$a}. New responses are no longer accepted.';
$string['eval_pulse_hint']         = 'This is a recurring (pulse) evaluation — you can submit a fresh response each time you visit.';

// P1 #18 (2026-05-16) — numeric + multi-select multichoice question types.
$string['numeric_min']                 = 'Minimum allowed value';
$string['numeric_min_help']            = 'Optional. Leave empty for no lower bound. The learner sees this as the <code>min</code> attribute on the input — values below it are rejected at submit time. Examples: <code>0</code> for "non-negative integers", <code>18</code> for age.';
$string['numeric_max']                 = 'Maximum allowed value';
$string['numeric_max_help']            = 'Optional. Leave empty for no upper bound. Must be greater than or equal to the minimum if both are set. Examples: <code>100</code> for percentages, <code>120</code> for age.';
$string['numeric_must_be_integer']     = 'Must be a whole number (e.g. 0, 5, 100).';
$string['numeric_min_max_invalid']     = 'Maximum must be greater than or equal to the minimum.';
$string['invalid_numeric']             = 'Answer must be a whole number for: {$a}';
$string['invalid_numeric_below_min']   = 'Answer to "{$a->q}" must be at least {$a->min}.';
$string['invalid_numeric_above_max']   = 'Answer to "{$a->q}" must be at most {$a->max}.';
$string['invalid_multichoice_multi']   = 'One or more selected options are not valid for: {$a}';
$string['multichoice_multi_hint']      = 'Check all the options that apply.';

// P1 #30 (2026-05-20) — conditional question display. Closes audit
// item #10 from parity-audit-2026-05-15/sentientia_evaluation.md.
$string['heading_dependency']      = 'Conditional display (optional)';
$string['dep_none']                = '— Always shown —';
$string['dep_parent']              = 'Show this question only if…';
$string['dep_parent_help']         = 'Pick a parent question. This question will only appear on the respond page when the parent has been answered AND its answer matches the value below. Cycles are blocked at save time. Leave as <em>Always shown</em> for unconditional questions.';
$string['dep_value']               = '… the parent\'s answer matches';
$string['dep_value_help']          = 'For Yes/No parents, enter <code>yes</code> or <code>no</code>. For multiple-choice parents, enter exactly one of the option labels (case-sensitive). For rating / NPS / numeric parents, enter the numeric value. Leave empty to mean <em>any non-empty answer triggers showing this question</em> — useful for "if the user answers Q3 at all, ask Q4".';
$string['dep_invalid_parent']      = 'The selected parent question does not exist.';
$string['dep_self_reference']      = 'A question cannot depend on itself.';
$string['dep_parent_other_evaluation'] = 'The parent question is not part of this evaluation. Pick a sibling.';
$string['dep_cycle']               = 'This dependency would create a cycle (parent eventually depends back on this question).';

// P1 #38 (2026-05-20) — show-non-respondents admin page. Closes audit
// item #20 from parity-audit-2026-05-15/sentientia_evaluation.md.
$string['non_respondents_title']    = '{$a} — pending / completed';
$string['non_respondents_heading']  = 'Who has responded to "{$a}"?';
$string['non_respondents_subtitle'] = 'Assigned learners and their response status. Auto-assignments come from the W1-5 trigger queue when a learner completes the qualifying course / program / classroom; manual assignments can be added via the admin UI (future).';
$string['back_to_evaluations']      = 'Back to evaluations';
$string['non_respondents_tab_pending']    = 'Pending';
$string['non_respondents_tab_responded']  = 'Responded';
$string['non_respondents_col_name']       = 'Name';
$string['non_respondents_col_email']      = 'Email';
$string['non_respondents_col_trigger']    = 'Assigned via';
$string['non_respondents_col_assigned']   = 'Assigned';
$string['non_respondents_col_due']        = 'Due by';
$string['non_respondents_col_responded']  = 'Responded';
$string['non_respondents_trigger_manual']    = 'Manual';
$string['non_respondents_trigger_course']    = 'Course';
$string['non_respondents_trigger_program']   = 'Program';
$string['non_respondents_trigger_classroom'] = 'Classroom';
$string['non_respondents_empty_pending_heading']   = 'Everyone has responded.';
$string['non_respondents_empty_pending_body']      = 'Either every assigned learner has filled in this evaluation, or no assignments have been recorded yet. Trigger-based evaluations auto-assign learners as they complete the qualifying activity.';
$string['non_respondents_empty_responded_heading'] = 'No responses yet.';
$string['non_respondents_empty_responded_body']    = 'Once assigned learners submit their responses, they will appear here.';

// P1 #39 (2026-05-20) — bulk-assign by audience. Pairs with P1 #37
// (assignments table) + P1 #38 (non-respondents view).
$string['filterstoolong']             = 'Filter payload too long.';
$string['bulk_assign_modal_title']    = 'Bulk-assign by audience';
$string['bulk_assign_form_intro']     = 'Pick one or more filter criteria to target a group of users. The preview below updates as you change filters. Click "Assign matching users" to commit — already-assigned learners are silently deduped.';
$string['bulk_assign_button']         = 'Assign matching users';
$string['bulk_assign_pick_at_least_one'] = 'Pick at least one filter criterion.';
$string['bulk_assign_result']         = '{$a->assigned} new assignment(s); {$a->matched} user(s) matched the audience ({$a->existing} already assigned).';
$string['bulk_assign_capped']         = 'Audience size hit the cap of {$a}. Refine your filter to assign more users (or run the bulk-assign twice with tighter criteria).';

// P1 #40 (2026-05-20) — modal form labels (parallel to classroom/programs).
$string['audience_any']           = 'Any';
$string['audience_any_cohort']    = 'Any cohort';
$string['audience_users_matched'] = 'users match this audience';
$string['audience_designation']   = 'Designation';
$string['audience_region']        = 'Region';
$string['audience_location']      = 'Location';
$string['audience_employmenttype'] = 'Employment type';
$string['audience_cohort']        = 'Cohort';

// P1 #41 (2026-05-20) — DB-backed template library. Closes audit
// item #11 from parity-audit-2026-05-15/sentientia_evaluation.md.
$string['template_name_required']  = 'Template name is required.';
$string['template_payload_corrupt'] = 'This template\'s payload could not be decoded. The row may have been edited outside Sentientia LMS. Delete and re-save.';
$string['template_saved']          = 'Template saved.';
$string['template_deleted']        = 'Template deleted.';
$string['template_save_modal_title']     = 'Save as template';
$string['template_picker_modal_title']   = 'Create from template';
$string['template_name_label']     = 'Template name';
$string['template_desc_label']     = 'Short description (optional)';
$string['template_ispublic']       = 'Make this template available to other tenants';
$string['template_ispublic_help']  = 'When checked, admins in OTHER tenants can pick this template when creating an evaluation. Useful for HQ-curated forms (POSH, AML, anti-bribery). Leave unchecked to keep the template scoped to your tenant.';

// P1 #42 (2026-05-20) — auto-expire overdue assignments.
$string['task_expire_assignments'] = 'Auto-expire overdue evaluation assignments';

// P1 #19 (2026-05-16) — admin notification on every response.
$string['heading_notifications']         = 'Notifications';
$string['notify_admin_on_response']      = 'Email site admins on every response';
$string['notify_admin_on_response_help'] = 'When ticked, every successful submission fires a Sentientia LMS notification to all site admins. They can opt out per channel (email / popup / mobile push) in their own notification preferences. Useful for low-volume strategic surveys (C-suite pulse, post-incident debrief). Leave OFF for high-volume L1 reaction forms — otherwise you will drown admins in mail.';
$string['eval_response_subject']         = 'New evaluation response: {$a}';
$string['eval_response_small']           = 'New response: {$a}';
$string['eval_response_body_plain']      = 'A new response was submitted for evaluation "{$a->evalname}".

Responder: {$a->responder}

View all responses: {$a->url}';
$string['eval_response_body_html']       = '<p>A new response was submitted for evaluation <strong>{$a->evalname}</strong>.</p><p>Responder: {$a->responder}</p><p><a href="{$a->url}">View all responses for this evaluation</a></p>';
$string['eval_response_responder_anonymous'] = '(anonymous)';
$string['eval_response_responder_unknown']   = '(unknown — user account removed)';
$string['answer_required'] = 'Required answer missing: {$a}';
$string['invalid_rating'] = 'Rating must be 1-5: {$a}';
$string['invalid_nps'] = 'NPS score must be 0-10: {$a}';
$string['invalid_yesno'] = 'Answer must be Yes or No: {$a}';
$string['invalid_multichoice'] = 'Selected option is not valid for: {$a}';
$string['please_answer_required'] = 'Please answer all required questions before submitting.';
$string['response_submitted'] = 'Thank you — your response has been recorded.';

// Privacy strings (Phase Z.1).
$string['privacy:metadata:responses'] = 'User-submitted evaluation responses (one row per submission).';
$string['privacy:metadata:responses:evaluationid'] = 'The ID of the evaluation form being responded to.';
$string['privacy:metadata:responses:userid'] = 'The ID of the user who submitted the response (0 if anonymous).';
$string['privacy:metadata:responses:response_data'] = 'JSON-encoded answers (questionid → answer).';
$string['privacy:metadata:responses:timesubmitted'] = 'Submission timestamp.';
