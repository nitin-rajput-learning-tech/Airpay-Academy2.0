<?php
// --------------------------------------------------------- 
// block_request is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// block_request is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
// COURSE REQUEST MANAGER BLOCK FOR MOODLE
// by Kyle Goslin & Daniel McSweeney
// Copyright 2012-2014 - Institute of Technology Blanchardstown.
// --------------------------------------------------------- 
/**
 * COURSE REQUEST MANAGER
  *
 * @package    local_request
 * @copyright  2018 Hemalatha c arun
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Request';
$string['plugindesc'] = 'Request for approvals';



//basic Navigation
$string['back'] = 'Back	';
$string['SaveChanges'] = 'Save Changes';
$string['ChangesSaved'] = 'Changes Saved';
$string['SaveAll'] = 'Save All';
$string['SaveEMail'] = 'Add E-Mail';
$string['Continue'] = 'Continue';
$string['Enabled'] = 'Enabled';
$string['Disabled'] = 'Disabled';
$string['clickhere'] = 'Click here';
$string['update'] = 'Update';
$string['request'] = 'Request';

//block_request_config.php
$string['administratorConfig'] = 'Other Settings';
$string['emailConfig'] = 'E-mail Config';
$string['emailConfigContents'] = 'Configure Communication E-Mails';
$string['requestStats'] = 'request Statistics';
$string['emailConfigInfo'] = 'This section contains E-Mail addresses of administators who will be notified whenever any course requests have been recorded.';
$string['emailConfigSectionHeader'] = 'Configure E-Mail';
$string['emailConfigSectionContents'] = 'Configure E-Mail Contents';
$string['statsConfigInfo'] = 'This section contains statistics on the current number of requests which have been made since the Course Request Manager module has been in use on this server.';
$string['totalRequests'] = 'Total number of Requests';
$string['config_addemail'] = 'E-Mail Address';
$string['namingConvetion'] = 'Course Naming Convention';
$string['namingConvetionInstruction'] = 'Course Request Manager will set up your courses using a selected naming convention.';
$string['namingConvetion_option1'] = 'Full Name Only';
$string['namingConvetion_option2'] = 'Short Name - Full Name';
$string['namingConvetion_option3'] = 'Full Name (Short Name)';
$string['namingConvetion_option4'] = 'Short Name - Full Name (Year)';
$string['namingConvetion_option5'] = 'Full Name (Year)';




//module_manager
$string['requestDisplay'] = 'Course Request Manager';
$string['requestDisplaySearchForm'] = 'Configure Request Form - Page 1';
$string['requestWelcome'] = 'Welcome to moodle Course Request Manager. Before requesting a new course, please check your local guidelines.';
$string['requestRequestBtn'] = 'Request a new course setup';
$string['requestExstingTab'] = 'Existing Requests';
$string['requestHistoryTab'] = 'Request History';
$string['requestActions'] = 'Actions';
$string['requestConfirmCancel'] = 'Are you sure you want to cancel this request?';
$string['requestnonePending'] = 'Sorry, nothing pending!';
$string['requestEnrolmentInstruction'] = 'Course Request Manager can generate an automatic enrolment key or you may choose to prompt the user for an enrolment key of their choice.';
$string['requestEnrolmentOption1'] = 'Automatically generated key';
$string['requestEnrolmentOption2'] = 'Prompt user for key';
$string['requestEnrolmentOption3'] = 'Do not ask for key';

$string['deleteAllRequests'] = 'Delete All Current and Archived Requests';
$string['deleteOnlyArch'] = 'Delete Only Archived Requests';
$string['clearHistoryTitle'] = 'Clear History';
$string['allowSelfCategorization'] = 'Allow User to Select Category';
$string['allowSelfCategorization_desc'] = 'When enabled, the user will be prompted to select a location in the Moodle catalogue to place their course';
$string['selfCatOn'] = 'Self Categorization On';
$string['selfCatOff'] = 'Self Categorization Off';

$string['sureDeleteAll'] = 'Are you sure you want to delete ALL history?';
$string['sureOnlyArch'] = 'Are you sure you want to delete only archived records?';
$string['yesDeleteRecords'] = 'Yes Delete';
$string['recordsHaveBeenDeleted'] = 'Records have been deleted';
$string['clickHereToReturn'] = 'Click here to return';

$string['selectedcategory'] = 'Category';


//request details
$string['requestReview_Summary'] = 'Request Summary';
$string['requestReview_intro1'] = 'Please review the following information carefully before submitting your request.';
$string['requestReview_intro2'] = 'Your request will be dealt with as soon as possible.';
$string['requestReview_status'] = 'STATUS';

$string['requestReview_requestType'] = 'Request Type';
$string['requestReview_moduleCode'] = 'Course Code';
$string['requestReview_moduleName'] = 'Course Name';
$string['requestReview_originator'] = 'Originator';



$string['requestReview_SubmitRequest'] = 'Submit Request';
$string['requestReview_AlterRequest'] = 'Alter Request';
$string['requestReview_CancelRequest'] = 'Cancel Request';
$string['requestReview_creationDate'] = 'Creation Date';
$string['requestReview_requestType'] = 'Request Type';

$string['requestReview_OpenDetails'] = 'Open Details';
$string['requestReview_ApproveRequest'] = 'Approve Request';
$string['requestReview_ApproveRequest'] = 'Approve Request';

$string['requestReview_courseName'] = 'Course Name';
$string['requestReview_courseCode'] = 'Course Code';



//comments
$string['comments_date'] = 'Date / Time';
$string['comments_message'] = 'Message';
$string['comments_from'] = 'From';
$string['comments_Header'] = 'Add / View Comments';
$string['comments_Forward'] = 'All comments will automatically be forwarded by email also';
$string['comments_PostComment'] = 'Post Comment';


//deny request
$string['denyrequest_Title'] = 'Course Request Facility - Deny Request';
$string['denyrequest_Instructions'] = 'Outline below why the request has been denied';
$string['denyrequest_Btn'] = 'Deny Request';
$string['denyrequest_reason'] = 'Outline below why the request has been denied (max 280 chars)';

//approve request
$string['approverequest_Title'] = 'Course Request Facility - Approve Request';
$string['approverequest_New'] = 'New course has been created';
$string['approverequest_Process'] = 'Handover process has begun';


//misc
$string['noPending'] = 'Sorry, nothing pending!';
$string['status'] = 'Status';
$string['creationdate'] = 'Creation Date';
$string['requesttype'] = 'Request Type';
$string['originator'] = 'Originator';
$string['comments'] = 'Comments';
$string['bulkactions'] = 'Bulk Actions';
$string['withselectedrequests'] = 'with selected requests';
$string['existingrequests'] = 'Existing Requests';
$string['actions'] = 'Actions';
$string['currentrequests'] = 'Current Requests';
$string['archivedrequests'] = 'Archived Requests';
$string['myarchivedrequests'] = 'My Archived Requests';
$string['allarchivedrequests'] = 'All Archived Requests';


$string['configure'] = 'Configure Course Request Manager';
$string['requestline1'] = 'Please refer to in-house guidelines for naming courses.';
$string['requestadmin'] = 'Course Request Administration';
$string['configureHeader'] = 'Course Request Facility - request Configuration';
$string['approve'] = 'Approve';
$string['deny'] = 'Deny';
$string['edit'] = 'Edit';
$string['cancel'] = 'Cancel';
$string['delete'] = 'Delete';
$string['view'] = 'View';
$string['viewmore'] = 'View More';
$string['addviewcomments'] = 'Add / View Comments';
$string['configurecoursemanagersettings'] = ' Configure Course Request Manager Settings';
$string['configurecourseformfields'] = '  Configure Request Form - Page 1';
$string['informationform'] = ' Configure Request Form - Page 2';
$string['modrequestfacility'] = 'Course Request Facility';
$string['step1text'] = 'Step 1: Course Request Details';
$string['modexists'] = 'It looks like the course you are requesting already exists on the server.';
$string['modcode'] = 'Course Code';
$string['modname'] = 'Course Name';
$string['catlocation'] = 'Catalogue Location';
$string['lecturingstaff'] = 'Lecturing Staff';
$string['actions'] = 'Actions';
$string['noneofthese'] = 'None of these? Continue making a new course';
$string['sendrequestforcontrol'] = 'Send request for control';
$string['sendrequestemail'] = 'Send Request E-Mail';
$string['emailswillbesent'] = 'E-mails will be sent to the owner of the course. Once you send a request, Please wait for a response.';

// View summary.php
$string['viewsummary'] = 'View Summary';

// Comment.php
$string['addviewcomments'] = 'Add / View Comments';

// Approve_course.php
$string['approvecourse'] = 'Approve Course';



// deny_course.php
$string['denycourse'] = 'Deny Course Request';

// Bulk Deny
$string['bulkdeny'] = 'Bulk Deny';

// Bulk Approve
$string['bulkapprove'] = 'Bulk Approve';
$string['approvingcourses'] = 'Approving Courses....';

// block_request_config.php
$string['managersettings'] = 'Manager Settings';


// Form Page 1 & Page2
$string['formpage1'] = 'Form Page 1';
$string['formpage2'] = 'Form Page 2';
$string['formpage2builder'] = 'Form Page 2 Builder';


// Preview form
$string['previewform'] = 'Preview Form';

// course_exists.php
$string['courseexists'] = 'Course Exists';

// Request control.php
$string['requestcontrol'] = 'Request Control';


// History record management
$string['historynav'] = 'History';


// Search Feature
$string['searchAuthor'] = 'Author';
$string['search_side_text'] = 'Search';
$string['searchbuttontext'] = 'Search!';

// Quick approve
$string['quickapprove'] = 'Quick Approve';
$string['quickapprove_desc'] = 'Quick Approve this course?';

// Email and other settings

$string['configureemailsettings'] = 'Configure E-Mail Settings';
$string['configureemailsettings_desc'] = 'This section allows you to configure the e-mail settings for this tool';


$string['configureadminsettings'] = 'Admin Settings';
$string['configureadminsettings_desc'] = 'Addition additional settings for Course Request Manager';

$string['required_field'] = 'Required Field';
$string['optional_field'] = 'Optional Field';

$string['request:myaddinstance'] = 'Add Instance';
$string['request:addinstance'] = 'Add Instance';

// displayLists
$string['displayListWarningTitle'] = 'WARNING';
$string['displayListWarningSideText'] = 'This shortname already exists in the moodle database. Admin attention required. This request is excluded from bulk actions.';

$string['nocatselected'] = 'Sorry no catgory has been selected for this course';

$string['customdeny'] = 'Denial Text Templates';
$string['customdenydesc'] = 'Administrators may deny course requests for a number of reasons. Outlining the reason for a denial in an email can be time consuming. This feature lets you create up to five reasons which can be quickly selected during the denial process. Max 250 chars';
$string['customdenyfiller'] = 'You may enter a denial reason here (max 250 chars)';
$string['denytext1'] = 'Reason 1';
$string['denytext2'] = 'Reason 2';
$string['denytext3'] = 'Reason 3';
$string['denytext4'] = 'Reason 4';
$string['denytext5'] = 'Reason 5';


// Error messages
$string['cannotrequestcourse'] = ' Sorry your account does not have sufficient privelages to request a course. You need to be assigned to a system role with sufficient privileges.';
$string['cannotviewrecords'] = ' Sorry your account does not have sufficient privelages to view records. You need to be assigned to a system role with sufficient privileges.';
$string['cannotapproverecord'] = ' Sorry your account does not have sufficient privelages to approve records. You need to be assigned to a system role with sufficient privileges.';
$string['cannoteditrequest'] = ' Sorry your account does not have sufficient privelages to edit a record. You need to be assigned to a system role with sufficient privileges.';
$string['cannotcomment'] = ' Sorry yyour account does not have sufficient privelages to comment. You need to be assigned to a system role with sufficient privileges.';
$string['cannotdelete'] = ' Sorry your account does not have sufficient privelages to delete a record. You need to be assigned to a system role with sufficient privileges.';
$stirng['cannotdenyrecord'] = ' Sorry your account does not have sufficient privelages to deny a record. You need to be assigned to a system role with sufficient privileges.';
$string['cannotviewconfig'] = ' Sorry your account does not have sufficient privelages to view the config. You need to be assigned to a system role with sufficient privileges.';

$string['request:addcomment'] = 'Add comment';
$string['request:addrecord'] = 'Add Record';
$string['request:approverecord'] = 'Approve Record';
$string['request:deleterecord'] = 'Delete Record';
$string['request:denyrecord'] = 'Deny Record';
$string['request:editrecord'] = 'Edit Record';
$string['request:viewrecord'] = 'View Record';
$string['request:viewconfig'] = 'View Config';

// --- added by hema-----------
$string['requestcourse'] ='Requisition';
$string['request_confirm_message'] ='<div class="pl-15 pr-15">Are you sure do you want to request {$a->component} enrollment?</div>';
$string['viewrequest'] ='List of Requisitions';
$string['requestedby'] ='Requested by';
$string['compname'] = 'Component';
$string['sorting'] = 'Sorting';
$string['requesteddate']='Requested Date';
$string['confirmmsgfor_approve']='<div class="pl-15 pr-15">Are you sure want to enroll {$a->requesteduser} to {$a->component}</div> ';
$string['confirmmsgfor_deny']='<div class="pl-15 pr-15">Are you sure want to reject {$a->requesteduser} request</div>';

$string['success_add'] ='<div class="pl-15 pr-15">Request has been sent, please wait for approval you will get notify shortly</div>';
$string['success_approve']='Successfully enrolled';
$string['success_deny']='Its got rejected, Please contact higher authority for the more information';

$string['confirmmsgfor_delete'] ='<div class="pl-15 pr-15">Are you sure want to delete {$a->requesteduser} request</div>';
$string['success_delete'] ='<div class="pl-15 pr-15">Request been deleted successfully</div>';
$string['confirmmsgfor_add']='<div class="pl-15 pr-15">Are you sure want to request this \'<b>{$a->componentname}</b>\' {$a->component}?</div>';
$string['alreadyrequested']='<div class="pl-15 pr-15">Already you have requested for the same, you will be notified soon about approval</div>';
$string['responder']='Responder';
$string['respondeddate']='Responded Date';
$string['componentname'] ='Component Name';
$string['no_requests']='No requests added yet';

$string['firstrequestedfirst'] = 'First Requested First';

$string['APPROVED'] = 'APPROVED';
$string['REJECTED'] = 'REJECTED';
$string['PENDING'] = 'PENDING';



$string['latestfirst'] = 'Latest First';
$string['classroom'] = 'Classroom';
$string['elearning'] = 'Courses';
$string['learningplan'] = 'Learning path';
$string['program'] = 'Program';
$string['certification'] = 'Certification';
$string['left_menu_requests'] = 'Manage Requests';
$string['course'] = 'Course';
$string['eventrequestcreated'] = 'Local request created';
$string['eventrequestapproved'] = 'Local request approved';
$string['eventrequestdeleted'] = 'Local request deleted';
$string['eventrequestrejected'] = 'Local request rejected';

$string['information'] = 'Information';
$string['capacity_check'] = "<div class='alert alert-danger'>
                                All seats are filled.
                            </div>";
$string['messageprovider:request_add'] = 'Add request notification';
$string['messageprovider:request_approve'] = 'Approve request notification';
$string['messageprovider:request_deny'] = 'Deny request notification';
$string['modidnotset'] = 'New Mod ID Not set';
$string['filters'] = 'Filters';
$string['reject'] = 'Reject';
$string['confirm'] = 'Confirm';
$string['savecontinue'] = 'Save & Continue';
$string['assign'] = 'Assign';
$string['save'] = 'Save';
$string['previous'] = 'Previous';
$string['skip'] = 'Skip';
$string['cancel'] = 'Cancel';
$string['has_requested_for_enrolling_to'] = 'has requested for enrolling to';
$string['error_in_fetching_listofrequests'] = 'Invalid parameters sent';
$string['requestforenroll'] = 'Request';
