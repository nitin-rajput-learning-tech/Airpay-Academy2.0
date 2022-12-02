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
 * Strings for component 'evaluation', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package local_evaluation
 * @copyright  2017 Sreenivas
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['add_item'] = 'Add question';
$string['add_pagebreak'] = 'Add a page break';
$string['adjustment'] = 'Adjustment';
$string['after_submit'] = 'After submission';
$string['allowfullanonymous'] = 'Allow full anonymous';
$string['analysis'] = 'Analysis';
$string['anonymous'] = 'Anonymous';
$string['anonymous_edit'] = 'Record user names';
$string['anonymous_entries'] = 'Anonymous entries ({$a})';
$string['anonymous_user'] = 'Anonymous user';
$string['answerquestions'] = 'Answer the questions';
$string['append_new_items'] = 'Append new items';
$string['autonumbering'] = 'Auto number questions';
$string['autonumbering_help'] = 'Enables or disables automated numbers for each question';
$string['average'] = 'Average';
$string['bold'] = 'Bold';
$string['calendarend'] = 'Feedback {$a}';
$string['calendarstart'] = 'Feedback {$a}';
$string['cannotaccess'] = "You don't have access to this page";
$string['cannotsavetempl'] = 'saving templates is not allowed';
$string['captcha'] = 'Captcha';
$string['captchanotset'] = 'Captcha hasn\'t been set.';
$string['closebeforeopen'] = '"End date" should not be less than "Start date".';
$string['completed_evaluations'] = 'Submitted answers';
$string['complete_the_form'] = 'Answer the questions...';
$string['completed'] = 'Completed';
$string['completedon'] = 'Completed on {$a}';
$string['completionsubmit'] = 'View as completed if the feedback is submitted';
$string['configallowfullanonymous'] = 'If set to \'yes\', users can complete a feedback activity on the front page without being required to log in.';
$string['confirmdeleteentry'] = 'Are you sure you want to delete this entry?';
$string['confirmdeleteitem'] = 'Are you sure you want to delete this element?';
$string['confirmdeletetemplate'] = 'Are you sure you want to delete this template?';
$string['confirmusetemplate'] = 'Are you sure you want to use this template?';
$string['continue_the_form'] = 'Continue answering the questions...';
$string['count_of_nums'] = 'Count of numbers';
$string['courseid'] = 'courseid';
$string['creating_templates'] = 'Save these questions as a new template';
$string['delete_entry'] = 'Delete entry';
$string['delete_item'] = 'Delete question';
$string['delete_old_items'] = 'Delete old items';
$string['delete_pagebreak'] = 'Delete page break';
$string['delete_template'] = 'Delete template';
$string['delete_templates'] = 'Delete template...';
$string['depending'] = 'Dependencies';
$string['depending_help'] = 'It is possible to show an item depending on the value of another item.<br />
<strong>Here is an example.</strong><br />
<ul>
<li>First, create an item on which another item will depend on.</li>
<li>Next, add a pagebreak.</li>
<li>Then add the items dependant on the value of the item created before. Choose the item from the list labelled "Dependence item" and write the required value in the textbox labelled "Dependence value".</li>
</ul>
<strong>The item structure should look like this.</strong>
<ol>
<li>Item Q: Do you have a car? A: yes/no</li>
<li>Pagebreak</li>
<li>Item Q: What colour is your car?<br />
(this item depends on item 1 with value = yes)</li>
<li>Item Q: Why don\'t you have a car?<br />
(this item depends on item 1 with value = no)</li>
<li> ... other items</li>
</ol>';
$string['dependitem'] = 'Dependence item';
$string['dependvalue'] = 'Dependence value';
$string['description'] = 'Description';
$string['do_not_analyse_empty_submits'] = 'Do not analyse empty submits';
$string['dropdown'] = 'Multiple choice - single answer allowed (dropdownlist)';
$string['dropdownlist'] = 'Multiple choice - single answer (dropdown)';
$string['dropdownrated'] = 'Dropdownlist (rated)';
$string['dropdown_values'] = 'Answers';
$string['drop_evaluation'] = 'Remove from this course';
$string['edit_item'] = 'Edit question';
$string['edit_items'] = 'Questions';
$string['email_notification'] = 'Enable notification of submissions';
$string['email_notification_help'] = 'If enabled, teachers will receive notification of feedback submissions.';
$string['emailteachermail'] = '{$a->username} has completed feedback activity : \'{$a->evaluation}\'

You can view it here:

{$a->url}';
$string['emailteachermailhtml'] = '<p>{$a->username} has completed feedback activity : <i>\'{$a->evaluation}\'</i>.</p>
<p>It is <a href="{$a->url}">available on the site</a>.</p>';
$string['entries_saved'] = 'Your answers have been saved. Thank you.';
$string['export_questions'] = 'Export questions';
$string['export_to_excel'] = 'Export to Excel';
$string['eventresponsedeleted'] = 'Response deleted';
$string['eventresponsesubmitted'] = 'Response submitted';
$string['evaluationcompleted'] = '{$a->username} completed {$a->evaluationname}';
$string['evaluation:addinstance'] = 'Add a new feedback';
$string['evaluationclose'] = 'Allow answers to';
$string['evaluation:complete'] = 'Complete a feedback';
$string['evaluation:createprivatetemplate'] = 'Create private template';
$string['evaluation:createpublictemplate'] = 'Create public template';
$string['evaluation:deletesubmissions'] = 'Delete completed submissions';
$string['evaluation:deletetemplate'] = 'Delete template';
$string['evaluation:edititems'] = 'Edit items';
$string['evaluation_is_not_for_anonymous'] = 'feedback is not for anonymous';
$string['evaluation_is_not_open'] = 'The feedback is not open';
$string['evaluation:mapcourse'] = 'Map courses to global feedbacks';
$string['evaluationopen'] = 'Allow answers from';
$string['evaluation:receivemail'] = 'Receive email notification';
$string['evaluation:view'] = 'View a feedback';
$string['evaluation:viewanalysepage'] = 'View the analysis page after submit';
$string['evaluation:viewreports'] = 'View reports';
$string['evaluation:allevalauations'] = 'View all feedbacks';
$string['evaluation:alltempaltes'] = 'View all tempaltes';
$string['evaluation:delete'] = 'Delete feedback';
$string['evaluation:enroll_users'] = 'Enroll users to feedback';
$string['evaluation:ownevalauations'] = 'View own feedbacks';
$string['file'] = 'File';
$string['filter_by_course'] = 'Filter by course';
$string['handling_error'] = 'Error occurred in feedback module action handling';
$string['hide_no_select_option'] = 'Hide the "Not selected" option';
$string['horizontal'] = 'horizontal';
$string['check'] = 'Multiple choice - multiple answers';
$string['checkbox'] = 'Multiple choice - multiple answers allowed (check boxes)';
$string['check_values'] = 'Possible responses';
$string['choosefile'] = 'Choose a file';
$string['chosen_evaluation_response'] = 'chosen feedback response';
$string['downloadresponseas'] = 'Download all responses as:';
$string['importfromthisfile'] = 'Import from this file';
$string['import_questions'] = 'Import questions';
$string['import_successfully'] = 'Import successfully';
$string['info'] = 'Information';
$string['infotype'] = 'Information type';
$string['insufficient_responses_for_this_group'] = 'There are insufficient responses for this group';
$string['insufficient_responses'] = 'insufficient responses';
$string['insufficient_responses_help'] = 'For the feedback to be anonymous, there must be at least 2 responses.';
$string['item_label'] = 'Label';
$string['item_name'] = 'Question';
$string['label'] = 'Label';
$string['labelcontents'] = 'Contents';
$string['mapcourseinfo'] = 'This is a site-wide feedback that is available to all courses using the feedback block. You can however limit the courses to which it will appear by mapping them. Search the course and map it to this feedback.';
$string['mapcoursenone'] = 'No courses mapped. feedback available to all courses';
$string['mapcourse'] = 'Map feedback to courses';
$string['mapcourse_help'] = 'By default, feedback forms created on your homepage are available site-wide
and will appear in all courses using the feedback block. You can force the feedback form to appear by making it a sticky block or limit the courses in which a feedback form will appear by mapping it to specific courses.';
$string['mapcourses'] = 'Map feedback to courses';
$string['mappedcourses'] = 'Mapped courses';
$string['mappingchanged'] = 'Course mapping has been changed';
$string['minimal'] = 'minimum';
$string['maximal'] = 'maximum';
$string['messageprovider:message'] = 'Feedback Reminder Notification';
$string['messageprovider:submission'] = 'Feedback Notifications';
$string['mode'] = 'Mode';
$string['modulename'] = 'feedback';
$string['modulename_help'] = 'The feedback activity module enables a teacher to create a custom survey for collecting feedback from participants using a variety of question types including multiple choice, yes/no or text input.

feedback responses may be anonymous if desired, and results may be shown to all participants or restricted to teachers only. Any feedback activities on the site front page may also be completed by non-logged-in users.

feedback activities may be used

* For course feedback, helping improve the content for later participants
* To enable participants to sign up for course modules, events etc.
* For guest surveys of course choices, school policies etc.
* For anti-bullying surveys in which students can report incidents anonymously';
$string['modulename_link'] = 'mod/evaluation/view';
$string['modulenameplural'] = 'feedback';
$string['move_item'] = 'Move this question';
$string['multichoice'] = 'Multiple choice';
$string['multichoicerated'] = 'Multiple choice (rated)';
$string['multichoicetype'] = 'Multiple choice type';
$string['multichoice_values'] = 'Multiple choice values';
$string['multiplesubmit'] = 'Allow multiple submissions';
$string['multiplesubmit_help'] = 'If enabled for anonymous surveys, users can submit feedback an unlimited number of times.';
$string['name'] = 'Name';
$string['name_required'] = 'Name required';
$string['next_page'] = 'Next page';
$string['no_handler'] = 'No action handler exists for';
$string['no_itemlabel'] = 'No label';
$string['no_itemname'] = 'No itemname';
$string['no_items_available_yet'] = 'No questions have been set up yet';
$string['non_anonymous'] = 'User\'s name will be logged and shown with answers';
$string['non_anonymous_entries'] = 'Non anonymous entries ({$a})';
$string['non_respondents_students'] = 'Non respondents students ({$a})';
$string['not_completed_yet'] = 'Not completed yet';
$string['not_started'] = 'Not started';
$string['no_templates_available_yet'] = 'No templates available yet';
$string['not_selected'] = 'Not selected';
$string['numberoutofrange'] = 'Number out of range';
$string['numeric'] = 'Numeric answer';
$string['numeric_range_from'] = 'Range from';
$string['numeric_range_to'] = 'Range to';
$string['of'] = 'of';
$string['oldvaluespreserved'] = 'All old questions and the assigned values will be preserved';
$string['oldvalueswillbedeleted'] = 'Current questions and all responses will be deleted.';
$string['only_one_captcha_allowed'] = 'Only one captcha is allowed in a feedback';
$string['overview'] = 'Overview';
$string['page'] = 'Page';
$string['page-mod-evaluation-x'] = 'Any feedback module page';
$string['page_after_submit'] = 'Completion message';
$string['pagebreak'] = 'Page break';
$string['pluginadministration'] = 'feedback administration';
$string['pluginname'] = 'Feedbacks';
$string['evaluation'] = 'Feedbacks';
$string['position'] = 'Position';
$string['previous_page'] = 'Previous page';
$string['public'] = 'Public';
$string['question'] = 'Question';
$string['questionandsubmission'] = 'Question and submission settings';
$string['questions'] = 'Questions';
$string['questionslimited'] = 'Showing only {$a} first questions, view individual answers or download table data to view all.';
$string['radio'] = 'Multiple choice - single answer';
$string['radio_values'] = 'Responses';
$string['ready_evaluations'] = 'Ready feedbacks';
$string['required'] = 'Required';
$string['resetting_data'] = 'Reset feedback responses';
$string['resetting_evaluations'] = 'Resetting feedbacks';
$string['response_nr'] = 'Response number';
$string['responses'] = 'Responses';
$string['responsetime'] = 'Responsestime';
$string['save_as_new_item'] = 'Save as new question';
$string['save_as_new_template'] = 'Save as new template';
$string['save_entries'] = 'Submit your answers';
$string['save_item'] = 'Save question';
$string['saving_failed'] = 'Saving failed';
$string['search:activity'] = 'feedback - activity information';
$string['search_course'] = 'Search course';
$string['searchcourses'] = 'Search courses';
$string['searchcourses_help'] = 'Search for the code or name of the course(s) that you wish to associate with this feedback.';
$string['selected_dump'] = 'Selected indexes of $SESSION variable are dumped below:';
$string['send'] = 'send';
$string['send_message'] = 'send message';
$string['show_all'] = 'Show all';
$string['show_analysepage_after_submit'] = 'Show analysis page';
$string['show_entries'] = 'Show responses';
$string['show_entry'] = 'Show response';
$string['show_nonrespondents'] = 'Show non-respondents';
$string['site_after_submit'] = 'Site after submit';
$string['sort_by_course'] = 'Sort by course';
$string['started'] = 'Started';
$string['startedon'] = 'Started on {$a}';
$string['subject'] = 'Subject';
$string['switch_item_to_not_required'] = 'Set as not required';
$string['switch_item_to_required'] = 'Set as required';
$string['template'] = 'Template';
$string['templates'] = 'Templates';
$string['template_deleted'] = 'Template deleted';
$string['template_saved'] = 'Template saved';
$string['textarea'] = 'Longer text answer';
$string['textarea_height'] = 'Number of lines';
$string['textarea_width'] = 'Width';
$string['textfield'] = 'Short text answer';
$string['textfield_maxlength'] = 'Maximum characters accepted';
$string['textfield_size'] = 'Textfield width';
$string['there_are_no_settings_for_recaptcha'] = 'There are no settings for captcha';
$string['this_evaluation_is_already_submitted'] = 'You\'ve already completed this activity.';
$string['typemissing'] = 'missing value "type"';
$string['update_item'] = 'Save changes to question';
$string['url_for_continue'] = 'Link to next activity';
$string['url_for_continue_help'] = 'After submitting the feedback, a continue button is displayed, which links to the course page. Alternatively, it may link to the next activity if the URL of the activity is entered here.';
$string['use_one_line_for_each_value'] = 'Use one line for each answer!';
$string['use_this_template'] = 'Use this template';
$string['using_templates'] = 'Use a template';
$string['vertical'] = 'vertical';
// Deprecated since Moodle 3.1.
$string['cannotmapevaluation'] = 'Database problem, unable to map feedback to course';
$string['line_values'] = 'Rating';
$string['mapcourses_help'] = 'Once you have selected the relevant course(s) from your search,
you can associate them with this feedback using map course(s). Multiple courses may be selected by holding down the Apple or Ctrl key whilst clicking on the course names. A course may be disassociated from a feedback at any time.';
$string['max_args_exceeded'] = 'Max 6 arguments can be handled, too many arguments for';
$string['cancel_moving'] = 'Cancel moving';
$string['movedown_item'] = 'Move this question down';
$string['move_here'] = 'Move here';
$string['moveup_item'] = 'Move this question up';
$string['notavailable'] = 'this feedback is not available';
$string['saving_failed_because_missing_or_false_values'] = 'Saving failed because missing or false values';
$string['cannotunmap'] = 'Database problem, unable to unmap';
$string['viewcompleted'] = 'completed feedbacks';
$string['viewcompleted_help'] = 'You may view completed feedback forms, searchable by course and/or by question.
feedback responses may be exported to Excel.';
$string['parameters_missing'] = 'Parameters missing from';
$string['picture'] = 'Picture';
$string['picture_file_list'] = 'List of pictures';
$string['picture_values'] = 'Choose one or more<br />picture files from the list:';
$string['preview'] = 'Preview';
$string['preview_help'] = 'In the preview you can change the order of questions.';
$string['switch_group'] = 'Switch group';
$string['separator_decimal'] = '.';
$string['separator_thousand'] = ',';
$string['relateditemsdeleted'] = 'All responses for this question will also be deleted.';
$string['radiorated'] = 'Radiobutton (rated)';
$string['radiobutton'] = 'Multiple choice - single answer allowed (radio buttons)';
$string['radiobutton_rated'] = 'Radiobutton (rated)';
// Deprecated since Moodle 3.2.
$string['start'] = 'Start';
$string['stop'] = 'End';
$string['users'] = 'Users';
$string['add_remove_users'] = 'Enroll / Unenroll users';
$string['add_users'] = 'Enroll users';
$string['remove_users'] = 'Unenroll users';
$string['manageevaluation'] = 'Feedbacks';
$string['department'] = 'Department';

$string['download'] = 'Download';
$string['csvdelimiter'] = 'CSV delimiter';
$string['encoding'] = 'Encoding';
$string['errors'] = 'Errors';
$string['nochanges'] = 'No changes';
$string['uploadusers'] = 'Upload Users';
$string['rowpreviewnum'] = 'Preview rows';
$string['uploaduser'] = 'Upload Users';
$string['back_upload'] = 'Back to Upload Users';
$string['uploaduser_help'] = ' The format of the file should be as follows:

* Each line of the file contains one record
* Each record is a series of data separated by commas (or other delimiters)
* The first record contains a list of fieldnames defining the format of the rest of the file';
$string['uploaduserspreview'] = 'Upload Users Preview';
$string['userscreated'] = 'Users Created';
$string['usersskipped'] = 'Users Skipped';
$string['usersupdated'] = 'Users Updated';
$string['uuupdatetype'] = 'Existing users details';
$string['uuoptype'] = 'Upload type';
$string['uuoptype_addnew'] = 'Add new only, skip existing users';
$string['uuoptype_addupdate'] = 'Add new and update existing users';
$string['uuoptype_update'] = 'Update existing users only';
$string['uuupdateall'] = 'Override with file and defaults';
$string['uuupdatefromfile'] = 'Override with file';
$string['uuupdatemissing'] = 'Fill in missing from file and defaults';
$string['uploadusersresult'] = 'Uploaded Users Result';
$string['helpmanual'] = 'Download sample Excel sheet and fill the field values in the format specified below.';
$string['manual'] = 'Help Manual';
$string['info'] = 'Help';
$string['feedback'] = 'Feedback';
$string['survey'] = 'Survey';
$string['type'] = 'Type';
$string['skip'] = 'Skip';
$string['filters'] = 'Filters';
$string['availablelist'] = '<b>Available users ({$a})</b>';
$string['selectedlist'] = 'Selected users';
$string['status'] = 'Status';
$string['select_all'] = 'Select All';
$string['remove_all'] = 'Un Select All';
$string['not_enrolled_users'] = '<b>Not Enrolled Users ({$a})</b>';
$string['enrolled_users'] = '<b> Enrolled Users ({$a})</b>';
$string['remove_selected_users'] = '<b> Un Enroll Users </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['remove_all_users'] = '<b> Un Enroll All Users </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['add_selected_users'] = '<i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i><b> Enroll Users</b>';
$string['add_all_users'] = ' <i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i> <b> Enroll All Users </b>';

$string['createevaluation'] = 'Create feedback';
$string['create_evaluation'] = '<i class="fa fa-clipboard popupstringicon" aria-hidden="true"></i> Create Feedback <div class= "popupstring">Here you will create feedbacks</div>';
$string['update_evaluation'] = '<i class="fa fa-clipboard popupstringicon" aria-hidden="true"></i> Update Feedback <div class= "popupstring">Here you will update feedback</div>';
$string['manageevaluation'] = 'Feedbacks';
$string['evaluationtype'] = 'Feedback type';
$string['evoltype1'] = 'Select feedback Type';
$string['evoltype2'] = 'Training feedback';
$string['evoltype3'] = 'Post - training feedback';
$string['evoltype4'] = 'Trainer feedback';
$string['evalupdated'] = 'Feedback updated successfully';
$string['evalcreated'] = 'Feedback created successfully';
$string['deleteevalaution'] = 'Delete Feedback';
$string['assignusers'] = 'Assign users';
$string['username'] = 'Employee Name';
$string['submitdate'] = 'Submitted date';
$string['enrolledon'] = 'Enrolled date';
$string['confirmdelete'] = 'Do you really want to delete this feedback?';
$string['template_eval'] = 'You can select the template from the below dropdown.';
$string['analysis_eval'] = 'This page allows the registrar to view list of answers given to an feedback.';
$string['entries_eval'] = 'Here trainer can view responses and can also delete entries.';
$string['evalinst'] = 'Evaluating instructor';
$string['evaltdinst']= 'Evaluated instructor';
$string['evaluatedinstructor_help']='By using control key you can select multiple instructors';
$string['missingevaltdinst'] = 'Please select atleast one evaluated instructor';
$string['missingevalinst'] = 'Please select the evaluating instructor';
$string['missingevaluationtype'] = 'Please select the feedback type';
$string['view_feedbacks'] = 'view feedbacks';
$string['warning_enrol'] = '<div><b style="color:red; float:right;">This feedback is currently closed. Though you assign users, they will not get access to this feedback. </b></div>';

$string['createdsuccessfully'] = "Feedback successfully created";
$string['doaddquestions'] = 'Do you want to add Questions';
$string['doenrollusers'] = 'Do you want to enroll users';
$string['doaddtemplates'] = 'Do you want to add Templates';
$string['eventevaluationcreated'] = 'Feedback created';
$string['eventevaluationupdated'] = 'Feedback updated';
$string['eventevaluationdeleted'] = 'Feedback deleted';
$string['eventevaluationenrolled'] = 'Feedback enrollment';
$string['eventevaluationunenrolled'] = 'Feedback unenrollment';
$string['schedule'] = 'Schedule';
$string['open'] = 'Open';
$string['enrolled'] = 'Enrolled';
$string['enrolledon'] = 'Enrolled on';
$string['completedon'] = 'Completed on';
$string['qsintemplate'] = 'Questions in the selected template';
$string['nodata'] = 'No questions in the selected feedback';
$string['response_view'] = 'View in-detail';
$string['nofeedbacks'] = 'No feedbacks available';
$string['enrolledlist'] = 'Enrolled users';
$string['completedlist'] = 'Completed users';
$string['select_department'] = 'Select Department';
$string['select_organization'] = 'Select organization';
$string['incompleted'] = 'Incomplete';
$string['pending'] = 'Pending';
$string['enrolluserssuccess'] = '<b>{$a->changecount}</b> Employee(s) successfully enrolled to this <b>"{$a->feedback}"</b> feedback .';
$string['unenrolluserssuccess'] = '<b>{$a->changecount}</b> Employee(s) successfully un enrolled from this <b>"{$a->feedback}"</b> feedback .';

$string['enrollusers'] = 'Feedback <b>"{$a}"</b> enrollment is in process...';

$string['un_enrollusers'] = 'Feedback <b>"{$a}"</b> un enrollment is in process...';
$string['click_continue'] = 'Click on continue';
$string['left_menu_evaluations'] = 'Manage Feedbacks';
$string['left_menu_myevaluations'] = 'My Feedbacks';
$string['manage_br_evaluation'] = 'Manage <br/> feedbacks';
$string['openevaluation'] = 'Open Evaluation';
//$string['taskevaluationdue'] = 'Evaluation Due Notification';
$string['taskevaluationdue'] = 'Feedback Reminder';
$string['browse_feedback'] = 'Manage Feedback';
$string['my_feedbacks'] = 'My Feedbacks';
$string['self_evaluation'] = 'Self';
$string['supervsior_evaluation'] = 'Supervisor';
$string['submissiontype'] = 'Evaluation Type';
$string['evaluationmode_help'] = 'Set the type of evaluation feedback:

* Self-Employee should submit the feedback

* Supervsior-Supervisor should submit the feedback';
$string['evaluateduser'] = 'Evaluated By';
$string['le_employeename'] = 'Employee Name';
$string['le_myteam'] = 'My Team';
$string['le_createnewquestion'] = 'Create new question';
$string['le_updatequestion'] = 'Update existing question';
$string['le_create'] = 'Create';
$string['le_update'] = 'Update';
$string['confirm'] = 'Confirm';
$string['hideevaluation'] = 'Are you sure,you want to inactivate <b>\'{$a}\'</b> survey';
$string['showevaluation'] = 'Are you sure,you want to activate <b>\'{$a}\'</b> survey';
$string['general_evaluationhdr'] = 'General Settings';
$string['submit'] = 'Submit';
$string['viewresponse'] = 'View Response';
$string['le_inactive'] = 'Make Inactive';
$string['le_active'] = 'Make Active';
$string['evaluationmode'] = 'Evaluation Type';
$string['supervisorfeedbacks'] = 'Supervisor Feedback';
$string['tagarea_evaluation'] = 'Feedbacks';
$string['browse_template'] = 'Manage Template';
$string['questions'] ='Add Questions';
$string['evaluation:evaluationmode'] ='Evaluation mode';
$string['evaluation:manage_multiorganizations'] ='Manage multiple organizations';
$string['evaluation:manage_owndepartments'] ='Manage owndepartments';
$string['evaluation:manage_ownorganization'] ='Manage ownorganization';
$string['evaluation:create_update_question'] ='Create update questions';
$string['enrolusers'] ='Enroll Users';
$string['feedbackname'] ='Missing Feedback name';
$string['messageprovider:feedback_due'] = 'Feedback Due Notification';
$string['messageprovider:feedback_enrollment'] = 'Feedback Enrollment Notification';
$string['messageprovider:feedback_unenrollment'] = 'Feedback Unenrollment Notification';
$string['inprogress_evaluation'] = 'My Feedback';
$string['completed_evaluation'] = 'My Feedback';


/* Strings added by Pallavi Veerla*/

$string['analysis_header'] = 'Analysis of Responses';
$string['feedback_not_found'] = 'Feedback not found'; 
$string['dont_have_permission'] = 'You dont have permission to view this page.';
$string['evaluation_is_not_open_available_from'] = 'The feedback is not open. Available from';
$string['evaluation_is_not_open_closed_on'] = 'The feedback is not open. Closed On';
$string['already_submitted_feedback'] = 'You have already submitted the feedback for the user';
$string['no_permission_to_view_this_page'] = 'You dont have permission to view this page';
$string['no_permissions'] = 'You donot have permissions';
$string['classroom_not_found'] = 'classroom not found!';
$string['program_not_found'] = 'program not found!';
$string['certification_not_found'] = 'certification not found!';
$string['user_created_feedback'] = 'The user with id "{$a->userid}" has created the feedback with id "{$a->objectid}"';
$string['user_deleted_feedback'] = 'The user with id "{$a->userid}" has deleted the feedback with id "{$a->objectid}"';
$string['user_enrolled_feedback'] = 'The user with id "{$a->userid}" enrolled the user with id "{$a->userid}" to the feedback of id "{$a->objectid}"';
$string['user_updated_evaluation'] = 'The user with id "{$a->userid}" has updated the evaluation with id "{$a->objectid}"';
$string['user_deleted_feedback_activity'] = 'The user with id "{$a->userid}" deleted the feedback for the user with id "{$a->relateduserid}" for the feedback activity with id "{$this->objectid}"';
$string['record_id'] = 'The record id.';
$string['feedback_instance_records_belongs_to'] = 'The feedback instance id this records belongs to.';
$string['user_who_completed_feedback'] = 'The user who completed the feedback (0 for anonymous).';
$string['last_time_feedback_completed'] = 'The last time the feedback was completed.';
$string['response_number'] = 'The response number (used when shuffling anonymous responses).';
$string['anonymous_response'] = 'Whether is an anonymous response.';
$string['course_id_feedback_completed'] = 'The course id where the feedback was completed.';
$string['guests_session_key'] = 'For guests, this is the session key';
$string['if_template_template_id'] = 'If it belogns to a template, the template id';
$string['item_name'] = 'The item name.';
$string['item_label'] = 'The item label.';
$string['text_describing_item_or_answer'] = 'The text describing the item or the available possible answers.';
$string['item_type'] = 'The type of the item';
$string['has_value_or_not'] = 'Whether it has a value or not';
$string['position_in_the_list_questons'] = 'The position in the list of questions';
$string['item_required_or_not'] = 'Whether is a item (question) required or not';
$string['item_id_depend_on'] = 'The item id this item depend on';
$string['depend_value'] = 'The depend value';
$string['additional_settings_for_item'] = 'Different additional settings for the item (question)';
$string['item_position_number'] = 'The item position number';
$string['additional_data_required_by_external_functions'] = 'Additional data that may be required by external functions';
$string['record_primary_key'] = 'The primary key of the record';
$string['course_feedback_part'] = 'Course id this feedback is part of.';
$string['only_feedback_name'] = 'Feedback name';
$string['feedback_introduction_text'] = 'Feedback introduction text';
$string['feedback_introduction_text_format'] = 'Feedback intro text format';
$string['whether_feedback_is_anonymous'] = 'Whether the feedback is anonymous.';
$string['whether_email_notification_sent_to_teachers'] = 'Whether email notifications will be sent to teachers.';
$string['whether_multiple_submission_allowed'] = 'Whether multiple submissions are allowed.';
$string['whether_questions_auto_numbered'] = 'Whether questions should be auto-numbered.';
$string['next_page_after_submission'] = 'Link to next page after submission.';
$string['text_display_after_submission'] = 'Text to display after submission.';
$string['text_display_after_submission_format'] = 'Text to display after submission format.';
$string['whether_stats_published'] = 'Whether stats should be published.';
$string['allow_answers_from_this_time'] = 'Allow answers from this time.';
$string['allow_answers_until_this_time'] = 'Allow answers until this time.';
$string['record_modified_time'] = 'The time this record was modified.';
$string['conditional_automatic_mark_submission'] = 'If this field is set to 1, then the activity will be automatically marked as complete on submission.';
$string['course_id_record_belongs_to'] = 'The course id this record belongs to.';
$string['responded_item_id'] = 'The item id that was responded.';
$string['evaluation_completed_table_reference'] = 'Reference to the evaluation_completed table';
$string['old_file_not_used_anymore'] = 'Old field - not used anymore.';
$string['response_value'] = 'The response value.';
$string['reference_to_evaluation_table'] = 'Reference to the evaluation_completedtmp table';
$string['not_yet_started'] = 'Not Yet Started';
$string['closed'] = 'Closed';
$string['costcenteridcourse'] = 'Organization';
$string['departmentidcourse'] = 'Department';
$string['costcenteridcourse_help'] = 'Organization for the feedback';
$string['departmentidcourse_help'] = 'Department for the feedback';
$string['selecttype'] = 'Select type';
$string['search'] = 'Search';
$string['feedback_reports'] = 'Feedback Reports';

$string['enrolled_evaluation'] = 'My Feedback';
$string['listtype'] = 'LIST';
$string['cardtype'] = 'CARD';
$string['evaluationname'] = 'EvaluationName';
$string['actions'] = 'Actions';
$string['listicon'] ='icon fa fa-bars fa-fw';
$string['cardicon'] ='icon fa fa-fw fa-th';
