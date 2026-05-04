<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Evaluations';

// Capabilities.
$string['airpay_evaluation:manage'] = 'Manage evaluation forms';
$string['airpay_evaluation:respond'] = 'Respond to evaluation forms';

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
