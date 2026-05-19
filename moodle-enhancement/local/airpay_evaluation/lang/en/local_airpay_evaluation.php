<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Evaluations';

// Capabilities.
$string['airpay_evaluation:manage'] = 'Manage evaluation forms';
$string['airpay_evaluation:respond'] = 'Respond to evaluation forms';

// W1-5 (2026-05-15) — observer + trigger queue.
$string['task_process_triggers'] = 'Process queued evaluation triggers';
$string['messageprovider:evaluation_invite'] = 'Evaluation invitation';
$string['invaliditemid']  = 'Invalid item ID for the trigger event';
$string['invalidratearea'] = 'Invalid rating area';

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
