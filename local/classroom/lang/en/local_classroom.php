<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage local_classroom
 */

$string['pluginname'] = 'Classrooms';
$string['browse_classrooms'] = 'Manage Classrooms';
$string['my_classrooms'] = 'My Classrooms';
$string['classrooms'] = 'View Classroom';
$string['costcenter'] = 'Organization';
$string['department'] = 'Country';
$string['shortname'] = 'Short Name';
$string['shortname_help'] = 'Please enter the partner short name';
$string['classroom_offline'] = 'Offline Courses';
$string['classroom_online'] = 'Online Courses';
$string['classroom_type'] = 'Classroom Type';
$string['course'] = 'Course';
$string['assign_test'] = 'Assign Test';
$string['course_tests'] = 'Quizs';
$string['trainers'] = 'Trainers';
$string['description'] = 'Description';
$string['traning_help'] = 'Search and select trainers who will be part of the Classroom training.';
$string['description_help'] = 'Enter Classroom description. This will be displayed in the list of Classrooms.';
$string['internal'] = 'Internal';
$string['external'] = 'External';
$string['institute_type'] = 'Location Type';
$string['institutions'] = 'Institutions';
$string['startdate'] = 'Start Date';
$string['enddate'] = 'End Date';
$string['create_classroom'] = 'Create Classroom';
$string['classroom_header'] = 'View Classrooms';
$string['select_course'] = '--Select Course--';
$string['select_quiz'] = '--Select Quiz--';
$string['select_trainers'] = '--Select Trainer--';
$string['select_institutions'] = '--Select Location--';
$string['classroom_name'] = 'Classroom Name';
$string['allow_multi_session'] = 'Allow Multiple Session';
$string['allow_multiple_sessions_help'] = '

* Fixed:If selected, system will create one 8hr session per day basis selected start and end date.

* Custom:If selected, no session will be created. User can create session basis there requirement.';
$string['allow_multiple_sessions'] = "allow multiple session";
$string['fixed'] = 'Fixed';
$string['custom'] = 'Custom';
$string['need_manage_approval'] = 'Need Manager Approval';
$string['need_manage_approval_help'] = "Select 'Yes', if you want to enforce manager approval workflow. All the enrollments into classroom training will be sent as requests to corresponding reporting manager's approval / rejection. If manager approves the request, users will be enrolled, if rejected users will not be enrolled.";
$string['capacity'] = 'Capacity';
$string['capacity_check_help'] = 'Total number of users who can participate in the Classroom';
$string['need_manager_approval'] = 'need manager approval';
$string['manage_classroom'] = 'Manage Classroom';
$string['manage_classrooms'] = 'Manage Classrooms';
$string['assign_course'] = 'Other Details';
$string['session_management'] = 'Session Management';
$string['location_date'] = 'Location & Date';
$string['certification'] = 'Certification';
$string['learningplan'] = 'Learning Plan';
$string['classroom'] = 'Classroom';
$string['capacity_positive'] = 'Capacity must be greater than zero (0).';
$string['capacity_limitexceeded'] = 'Capacity cannot exceed {$a}';
$string['missingclassroom'] = 'Missed classroom data';
$string['courseduration'] = 'Course Duration';
$string['session_name'] = 'Session Name';
$string['session_type'] = 'Session Type';
$string['clrm_location_type'] = 'Classroom location Type';
$string['classroom_locationtype_help'] = 'Select

* Internal- if you want to search and select an Internal locations like Training Room, Conference Room etc that are internal to your organization and where the training is planned to happen.

* External - if you want to search and select an External locations like Ball Rooms of a hotel, Training room of training institute etc that are external to your organization and where the training is planned to happen. ';
$string['classroom_location'] = 'Classroom location';
$string['location_room'] = 'Classroom location Room';
$string['nomination_startdate'] = 'Nomination start date';
$string['nomination_enddate'] = 'Nomination End date';
$string['type'] = 'Type';
$string['select_category'] = '--Select Category--';
$string['deleteconfirm'] = 'Are you sure you want to delete this "<b>{$a}</b>" classroom?';
$string['deleteallconfirm'] = 'Are you sure you want to delete this "<b>{$a}</b>" session?';
$string['deletecourseconfirm'] = 'Are you sure you want to un-assign this "<b>{$a}</b>" course?';
$string['deletefeedbackconfirm'] = 'Are you sure you want to delete this "<b>{$a}</b>" feedback?';
$string['createclassroom'] = '<span class="fa fa-desktop icon">
        </span> Create Classroom <div class="popupstring">Here you will create classrooms based upon the Country </div>';
$string['updateclassroom']= '<span class="fa fa-desktop icon">
        </span> Update Classroom <div class="popupstring">Here you will update classrooms based upon the Country </div>';
$string['save_continue'] = 'Save & Continue';
$string['enddateerror'] = 'End Date should greater than Start Date.';
$string['sessionexisterror']='There are other sessions have in this time .';
$string['nomination_startdateerror'] = 'Nomination Start Date Schedule should be less than Class Room Start Date .';
$string['nomination_enddateerror'] = 'Nomination End Date Schedule should be less than Class Room Start Date .';
$string['nomination_error'] = 'Nomination End Date should greater than Nomination Start Date.';
$string['cs_timestart'] = 'Session Start Date';
$string['showentries'] = 'View response';
$string['cs_timefinish'] = 'Session End Date';
$string['select_room'] = '--Select ROOM--';
$string['select_costcenter'] = '--Select Organization--';
$string['select_department'] = 'All Countries';
$string['classroom_active_action'] = 'Are you sure you want to publish this "<b>{$a}</b>" classroom?';
$string['classroom_release_hold_action'] = 'Are you sure you want to release this "<b>{$a}</b>" classroom?';
$string['classroom_hold_action'] = 'Are you sure you want to hold this "<b>{$a}</b>" classroom?';
$string['classroom_close_action'] = 'Are you sure you want to cancel this "<b>{$a}</b>" classroom?';
$string['classroom_cancel_action'] = 'Are you sure you want to cancel this "<b>{$a}</b>" classroom?';
$string['classroom_complete_action'] = 'Are you sure you want to complete this "<b>{$a}</b>" classroom?';
$string['classroom_activate'] = 'Are you sure you want to publish this "<b>{$a}</b>" classroom?';
$string['classroom'] = 'Classroom';
$string['learningplan'] = 'Learning Plan';
$string['certificate'] = 'Certificate';
$string['completed'] = 'Completed';
$string['pending'] = 'Pending';
$string['attendace'] = 'Mark Attendance';
$string['attended_sessions'] = 'Attended Sessions';
$string['attended_sessions_users'] = 'Attended Users';
$string['attended_hours'] = 'Attended Hours';
$string['supervisor'] = 'Reporting To';
$string['employee'] = 'Employee Name';
$string['room'] = 'Room';
$string['status'] = 'Status';
$string['trainer'] = 'Trainer';
$string['faculty'] = 'Trainer';
$string['addcourse'] = 'Add Courses';
$string['selfenrol'] = 'Self Enrol';
// Capability strings.
$string['classroom:createclassroom'] = 'Create Classroom';
$string['classroom:viewclassroom'] = 'View Classroom';
$string['classroom:editclassroom'] = 'Edit Classroom';
$string['classroom:deleteclassroom'] = 'Delete Classroom';
$string['classroom:manageclassroom'] = 'Manage Classroom';
$string['classroom:createsession'] = 'Create Session';
$string['classroom:viewsession'] = 'View Session';
$string['classroom:editsession'] = 'Edit Session';
$string['classroom:deletesession'] = 'Delete Session';
$string['classroom:managesession'] = 'Manage Session';
$string['classroom:assigntrainer'] = 'Assign Trainer';
$string['classroom:managetrainer'] = 'Manage Trainer';
$string['classroom:addusers'] = 'Add users';
$string['classroom:removeusers'] = 'Remove users';
$string['classroom:manageusers'] = 'Manage users';

$string['classroom:cancel'] = 'Cancel Classroom';
$string['classroom:classroomcompletion'] = 'Classroom Completion Setting';
$string['classroom:complete'] = 'Complete Classroom';
$string['classroom:createcourse'] = 'Assign Classroom Course';
$string['classroom:createfeedback'] = 'Assign Classroom Feedback';
$string['classroom:deletecourse'] = 'Un Assign Classroom Course';
$string['classroom:deletefeedback'] = 'Un Assign Classroom Feedback';
$string['classroom:editcourse'] = 'Edit Classroom Course';
$string['classroom:editfeedback'] = 'Edit Classroom Feedback';
$string['classroom:hold'] = 'Hold Classroom';
$string['classroom:managecourse'] = 'Manage Classroom Course';
$string['classroom:managefeedback'] = 'Manage Classroom Feedback';
$string['classroom:publish'] = 'Publish Classroom';
$string['classroom:release_hold'] = 'Release Hold Classroom';
$string['classroom:takemultisessionattendance'] = 'Classroom Multisession Attendance';
$string['classroom:manage_owndepartments'] = 'Manage Own Country Classrooms.';
$string['classroom:manage_multiorganizations'] = 'Manage Multi Organizations Classrooms.';

$string['classroom:takesessionattendance'] = 'Classroom Session Attendance';
$string['classroom:trainer_viewclassroom'] = 'Trainer View Classroom';
$string['classroom:viewcourse'] = 'View Classroom Course';
$string['classroom:viewfeedback'] = 'View Classroom Feedback';
$string['classroom:viewusers'] = 'View Classroom Users';
$string['classroom:view_activeclassroomtab'] = 'View Active Classrooms Tab';
$string['classroom:view_allclassroomtab'] = 'View All Classrooms Tab';
$string['classroom:view_cancelledclassroomtab'] = 'View Cancelled Classrooms Tab';
$string['classroom:view_completedclassroomtab'] = 'View Completed Classrooms Tab';
$string['classroom:view_holdclassroomtab'] = 'View Hold Classrooms Tab';
$string['classroom:view_newclassroomtab'] = 'View New Classrooms Tab';
// Room Strings.
$string['institute_name'] = 'Location Name';
$string['building'] = 'Building Name';
$string['roomname'] = 'Room Name';
$string['address'] = 'Address';
$string['capacity'] = 'Capacity';
$string['capacity_help'] = 'Capacity help';

// Session Strings.
$string['onlinesession'] = 'Online Session';
$string['onlinesession_help'] = 'If checked this option and submitted online session will be created.';
$string['addasession'] = 'Add one more Session';
$string['addsession'] = '<i class="fa fa-graduation-cap popupstringicon" aria-hidden="true"></i> Create a Session <div class="popupstring"></div>';
$string['session_dates'] = 'Session Dates';
$string['attendance_status'] = 'Attendance Status';
$string['sessiondatetime'] = 'Session Date and Time';
$string['session_details'] = 'Session Details';
$string['cs_capacity_number'] = 'Capacity must be numeric and positive';
$string['select_cr_room'] = 'Select a room';
// Empty Message Strings.
$string['noclassrooms'] = 'Classrooms not available';
$string['nosessions'] = 'Sessions not available';
$string['noclassroomusers'] = 'Classroom users not available';
$string['noclassroomcourses'] = 'Courses not assigned';
// Classroom Users.
$string['select_all'] = 'Select All';
$string['remove_all'] = 'Un Select All';
$string['not_enrolled_users'] = '<b>Not Enrolled Users ({$a})</b>';
$string['enrolled_users'] = '<b> Enrolled Users ({$a})</b>';
$string['remove_selected_users'] = '<b> Un Enroll Users </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['remove_all_users'] = '<b> Un Enroll All Users </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['add_selected_users'] = '<i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i><b> Enroll Users</b>';
$string['add_all_users'] = ' <i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i> <b> Enroll All Users </b>';


$string['addusers'] = 'Add Users';
$string['addusers_help'] = 'Add users Help';
$string['potusers'] = 'Potential users';
$string['potusersmatching'] = 'Potential users matching \'{$a}\'';
$string['extusers'] = 'Existing users';
$string['extusersmatching'] = 'Existing users matching \'{$a}\'';



// Classroom Evaluations.
$string['noclassroomevaluations'] = 'Classroom feedbacks not available!';
$string['training_feeddback'] = 'Training feedback';
$string['trainer_feedback'] = 'Trainer feedback';

// Classroom Status Tabs.
$string['allclasses'] = 'All';
$string['newclasses'] = 'New';
$string['activeclasses'] = 'Active';
$string['holdclasses'] = 'Hold';
$string['completedclasses'] = 'Completed';
$string['cancelledclasses'] = 'Cancelled';
$string['sessions'] = 'Sessions';
$string['courses'] = 'Courses';
$string['users'] = 'Users';

$string['activate'] = 'Activate';
$string['classroomstatusmsg'] = 'Are you sure you want to activate the classroom?';
$string['viewclassroom_assign_users']='Assign Users';
$string['assignusers']="Assign Users";
$string['continue']='Continue';
$string['assignusers']="Assign Users";
$string['assignusers_heading']='Enroll users to classroom <b>\'{$a}\'</b>';
$string['session_attendance_heading']='Attendance for classroom <b>\'{$a}\'</b>';

$string['online_session_type']='Online session type';
$string['online_session_type_desc']="online session type for online sessions on Classroom.";
$string['online_session_plugin_info']='Online session type plugins not found.';
$string['select_session_type']='Select session type';
$string['join']='Join';
$string['view_classroom'] = 'view classroom';

$string['addcourses'] = '<i class="fa fa-graduation-cap popupstringicon" aria-hidden="true"></i> Assign course <div class="popupstring"></div>';
$string['completion_status'] = 'Completion Status';
$string['completion_status_per'] = 'Completion Status (%)';
$string['type'] = 'Type';
$string['trainer'] = 'Trainer';
$string['submitted'] = 'Submitted';
$string['classroom_self_enrolment'] = '<div class="pl-15 pr-15 pb-15">Are you sure you want to enrol this "<b>{$a}</b>" classroom?</div>';
$string['classroom_enrolrequest_enrolment'] = '<div class="pl-15 pr-15 pb-15">Are you sure you want to enrolment request this "<b>{$a}</b>" classroom?</div>';
$string['alert_capacity_check'] = "<div class='alert alert-danger text-center'>
                                All seats are filled.
                            </div>";
$string['updatesession'] = '<i class="fa fa-graduation-cap popupstringicon" aria-hidden="true"></i> Update Session <div class="popupstring"></div>';

$string['addnewsession'] = 'Add a new session';
$string['createinstitute'] = 'Create Location';
$string['employeeid'] = 'Employee ID';
$string['classroom_info'] = 'Classroom Info';
$string['classroom_info'] = 'Classroom Info';
$string['sessionstartdateerror1'] = 'Session start date should greater than classroom start date.';
$string['sessionstartdateerror2'] = 'Session start date should less than Classroom end date.';
$string['sessionenddateerror1'] = 'Session end date should greater than Classroom start date.';
$string['sessionenddateerror2'] = 'Session end date should less than Classroom end date.';
$string['confirmation'] = 'Confirmation';
$string['unassign'] = 'Un-assign';
$string['roomid'] = 'List out the rooms from classroom.If you find rooms as empty, Please assign location for the classroom.';
$string['roomid_help'] = 'List out the rooms from classroom.';
$string['classroomcompletion'] = 'Classroom Completion Criteria';
$string['classroom_anysessioncompletion'] = 'Classroom is complete when ANY of the below sessions are complete';
$string['classroom_allsessionscompletion'] = 'Classroom is complete when ALL sessions are complete';
$string['classroom_anycoursecompletion'] = 'Classroom is complete when ANY of the below courses are complete';
$string['classroom_allcoursescompletion'] = 'Classroom is complete when ALL courses are complete';
$string['classroom_completion_settings'] = 'Classroom completion settings';
$string['sessiontracking'] = 'Sessions requirements';
$string['session_completion'] = 'Select Session';
$string['coursetracking'] = 'Courses requirements';
$string['course_completion'] = 'Select Course completions';
$string['classroom_donotsessioncompletion'] = 'Do not indicate sessions classroom completion';
$string['classroom_donotcoursecompletion'] = 'Do not indicate courses classroom completion';
$string['select_courses']='Select courses';
$string['select_sessions']='Select sessions';
$string['eventclassroomcreated'] = 'Local classroom created';
$string['eventclassroomupdated'] = 'Local classroom updated';
$string['eventclassroomcancel'] = 'Local classroom cancelled';
$string['eventclassroomcompleted'] = 'Local classroom completed';
$string['eventclassroomcompletions_settings_created'] = 'Local classroom completion settings added.';
$string['eventclassroomcompletions_settings_updated'] = 'Local classroom completion settings updated';
$string['eventclassroomcourses_created'] = 'Local classroom course added';
$string['eventclassroomcourses_deleted'] = 'Local classroom course removed';
$string['eventclassroomcourses_deleted'] = 'Local classroom course removed';
$string['eventclassroomdeleted'] = 'Local classroom deleted';
$string['eventclassroomhold'] = 'Local classroom holded';
$string['eventclassroompublish'] = 'Local classroom published';
$string['eventclassroomsessions_created'] = 'Local classroom sessions created';
$string['eventclassroomsessions_deleted'] = 'Local classroom sessions deleted';
$string['eventclassroomsessions_updated'] = 'Local classroom sessions updated';
$string['eventclassroomusers_created'] = 'Local classroom users enrolled';
$string['eventclassroomusers_deleted'] = 'Local classroom users un enrolled';
$string['eventclassroomusers_updated'] = 'Local classroom users updated';
$string['eventclassroomfeedbacks_created'] = 'Local classroom feedbacks created';
$string['eventclassroomfeedbacks_updated'] = 'Local classroom feedbacks updated';
$string['eventclassroomfeedbacks_deleted'] = 'Local classroom feedbacks deleted';
$string['eventclassroomattendance_created_updated'] = 'Local classroom sessions attendance present/absent';
$string['publish'] = 'Publish';
$string['release_hold'] = 'Release Hold';
$string['cancel'] = 'Cancel';
$string['hold'] = 'Hold';
$string['mark_complete'] = 'Mark Complete';
$string['enroll'] = 'Enroll';
$string['valnamerequired'] = 'Missing Classroom name';
$string['sessionvalnamerequired'] = 'Missing Session name';
$string['numeric'] = 'Only numeric values';
$string['positive_numeric'] = 'Only positive numeric values';
$string['capacity_enroll_check'] = 'Capacity must be greater than allocated seats.';
$string['vallocationrequired'] = 'Please select location in the selected location type.';
$string['vallocation'] = 'Please select only the location in the selected location type.';

$string['new_classroom'] = 'New';
$string['active_classroom'] = 'Active';
$string['cancel_classroom'] = 'Cancelled';
$string['hold_classroom'] = 'Hold';
$string['completed_classroom'] = 'Completed';
$string['completed_user_classroom'] = 'You have not completed this classroom';
$string['classroomlogo'] = 'Banner Image';
$string['bannerimage_help'] = 'Search and select a banner image for the Classroom training';
$string['completion_settings_tab'] = 'Completion Criteria';
$string['target_audience_tab'] = 'Target Audience';
$string['requested_users_tab'] = 'Requested Users';
$string['waitinglist_users_tab'] = 'Waiting List Users';


$string['classroom_completion_tab_info'] = 'No classroom criteria found.';

$string['classroom_completion_tab_info_allsessions'] = 'This classroom will completed when the below listed <b> all sessions </b> should be completed.';

$string['classroom_completion_tab_info_anysessions'] = 'This classroom will completed when the below listed <b> any sessions </b> should be completed.';

$string['classroom_completion_tab_info_allsessions_allcourses'] =  'This classroom will completed when the below listed <b>all courses </b> and <b> all sessions </b> should be completed.';

$string['classroom_completion_tab_info_allsessions_anycourses'] =  'This classroom will completed when the below listed <b>any courses </b> and <b> all sessions </b> should be completed.';

$string['classroom_completion_tab_info_anysessions_allcourses'] =  'This classroom will completed when the below listed <b>all courses </b> and <b> any sessions </b> should be completed.';

$string['classroom_completion_tab_info_anysessions_anycourses'] =  'This classroom will completed when the below listed <b>any courses </b> and <b> any sessions </b> should be completed.';

$string['classroom_completion_tab_info_allcourses'] = 'This classroom will completed when the below listed <b> all courses </b> should be completed.';

$string['classroom_completion_tab_info_anycourses'] = 'This classroom will completed when the below listed <b> any courses </b> should be completed.';

$string['audience_department'] = '<p>This classroom will eligible below-listed target audience.</p>
<p> <b>Countries :</b> {$a}</p>';
$string['audience_group'] = '<p> <b>Groups :</b> {$a}</p>';
$string['audience_hrmsrole'] = '<p> <b>Hrms Role :</b> {$a}</p>';
$string['audience_designation'] = '<p> <b>Designations :</b> {$a}</p>';
$string['audience_location'] = '<p> <b>Locations :</b> {$a}</p>';
$string['no_trainer_assigned'] = 'No trainers assigned';
$string['requestforenroll'] = 'Request';
$string['requestavail'] = 'Requested users not available';
$string['nocoursedesc'] = 'No description provided';
$string['enrolluserssuccess'] = '<b>{$a->changecount}</b> Employee(s) successfully enrolled to this <b>"{$a->classroom}"</b> classroom .';
$string['unenrolluserssuccess'] = '<b>{$a->changecount}</b> Employee(s) successfully un enrolled from this <b>"{$a->classroom}"</b> classroom .';

$string['enrollusers'] = 'Classroom <b>"{$a}"</b> enrollment is in process...';

$string['un_enrollusers'] = 'Classroom <b>"{$a}"</b> un enrollment is in process...';
$string['click_continue'] = 'Click on continue';
$string['manage_br_classrooms'] = 'Manage <br/> classrooms';
$string['noclassroomsavailiable'] = 'No Classrooms Availiable';
$string['employeerolestring'] = 'Employee';
$string['trainerrolestring'] = 'Trainer';
$string['taskclassroomreminder'] = 'Classroom Reminder';
$string['unenrollclassroom'] = 'Are you sure you want to un enroll this "<b>{$a}</b>" classroom?';
$string['unenroll'] = 'Un Enroll';
$string['eventclassroomusers_waitingcreated'] = 'Local classroom users waiting list added';
$string['sortorder'] = 'Waiting Order';
$string['enroltype'] = 'Type';
$string['waitingtime'] = 'Date And Time';
$string['allow_waitinglistusers'] = 'Allow Users Waiting List';
$string['allowuserswaitinglist_help'] ='Allow users to join waiting list post the set the capacity for the Classroom training is full.';
$string['classroom:viewwaitinglist_userstab'] = 'Allow Users Waiting List';
$string['classroomwaitlistinfo'] = '<div class="p-2 text-center"><b>This "{$a->classroom}" classroom is presently reserved</b>. <br/><br/>Thank you for your application request. You are placed on waiting list with order "{$a->classroomwaitinglistno}" and will be reported via email in case enrols to classroom when availiable.</div>';
$string['otherclassroomwaitlistinfo'] = '<div class="p-2 text-center"><b>This "{$a->classroom}" classroom is presently reserved</b>. <br/><br/>Thank you for your application request.<b>"{$a->username}"</b> is placed on waiting list with order "{$a->classroomwaitinglistno}" and will be reported via email in case user enrols to classroom when availiable.</div>';
$string['capacity_waiting_check'] = 'Capacity is required to allow a users waiting list.';
$string['submit'] = 'Submit';
$string['capacity_check'] ='capacity check';
$string['allowuserswaitinglist'] = 'allow users waiting list';
$string['traning'] = 'traning';
$string['classroom_locationtype'] = 'classroom_locationtype';
$string['bannerimage'] = 'bannerimage';

$string['messageprovider:classroomenrolment'] = 'Classroom Enrolment';
$string['classroomenrolmentsub'] = 'Classroom Enrolment';
$string['classroomenrolment'] = '<p>You have been enrolled to the classroom "{$a->name}"!</p>
<p>You can view more information on "{$a->classroomurl}" page.</p>';
$string['tagarea_classroom'] = 'Classroom';
$string['enrolled'] = 'Enrolled';
$string['deleted_classroom'] = 'Deleted Classroom';
$string['points'] = 'Points';
$string['open_pointsclassroom'] = 'points';
$string['open_pointsclassroom_help'] = 'Points for the Classroom default(0)';
$string['enrolusers'] = 'Enroll Users';
$string['enableplugin'] = 'Currently Classroom enrolment method is disabled.<a href="{$a}" target="_blank"> <u>Click here</u></a> to enable the Enrolment method';
$string['manageplugincapability'] = 'Currently Classroom enrolment method is disabled. Please contact the Site administrator.';
$string['attendance'] = 'Attendance';
$string['add_certificate'] = 'Add Certificate';
$string['add_certificate_help'] = 'If you want to issue a certificate when user completes this classroom, please enable here and select the template in next field (Certificate template)';
$string['select_certificate'] = 'Select Certificate';
$string['certificate_template'] = 'Certificate template';
$string['certificate_template_help'] = 'Select Certificate template for this classroom';
$string['err_certificate'] = 'Missing Certificate template';
$string['eventclassroomusercompleted'] = 'Classroom completed by user';
$string['downloadcertificate'] = 'Certificate';
$string['download_certificate'] = 'Download Certificate';
$string['unableto_download_msg'] = "Still you didn't complete this classroom so you cannot download the certificate";
$string['pluginname'] = 'Classrooms';
$string['messageprovider:classroom_cancel'] = 'Classroom_cancel';
$string['messageprovider:classroom_complete'] = 'Classroom_complete';
$string['messageprovider:classroom_enrol'] = 'Classroom_enrol';
$string['messageprovider:classroom_enrolwaiting'] = 'Classroom_enrolwaiting';
$string['messageprovider:classroom_hold'] = 'Classroom_hold';
$string['messageprovider:classroom_invitation'] = 'Classroom_invitation';
$string['messageprovider:classroom_reminder'] = 'Classroom_reminder';
$string['messageprovider:classroom_unenroll'] = 'Classroom_unenroll';
$string['notassigned'] = 'N/A';
$string['inprogress_classroom'] = 'My Classroom';
$string['completed_classroom'] = 'My Classroom';
$string['classroomname'] = 'Classroom Name';

//23-09-2020//
$string['savecontinue'] = 'Save & Continue';
$string['assign'] = 'Assign';
$string['save'] = 'Save';
$string['previous'] = 'Previous';
$string['skip'] = 'Skip';
$string['cancel'] = 'Cancel';
$string['requestprocessing'] = 'Request Processing...';
$string['information'] = 'Information';

/* Strings added by Pallavi Veerla */

$string['remove_all'] = 'Remove All'; 
$string['remove_selected_users'] = 'Remove Selected Users'; 
$string['add_selected_users'] = 'Add Selected Users'; 
$string['scheduled_date'] = 'Scheduled date';
$string['code'] = 'Code';
$string['enrolledusers'] = 'Enrolled Users'; 
$string['seats_allocation'] = 'Seats Allocation';
$string['edit_course'] = 'Edit Course'; 
$string['user_enrollments'] = 'User enrollments';
$string['classroom_completion'] = 'Classroom completion'; 
$string['users_completions'] = 'Users Completions'; 
$string['user_waiting_list'] = 'Users Waiting List';
$string['scheduled'] = 'Scheduled'; 
$string['classroom_code'] = 'Classroom Code'; 
$string['employee_location'] = 'Employee Location';
$string['total_seats'] = 'Total Seats'; 
$string['whats_next'] = 'What\'s next?';
$string['do_you_want_create_session'] = 'Do you want to <b>Create Session</b>'; 
$string['do_you_want_add_course'] = 'Do you want to <b>Add Course</b>'; 
$string['departments'] = 'Countries'; 
$string['sub_departments'] = 'LOB'; 
$string['designations'] = 'Designations';
$string['groups'] = 'Groups';
$string['hrms_roles'] = 'Hrms Role';
$string['locations'] = 'Locations';
$string['name'] = 'Name'; 
$string['session_timings'] = 'Session Timings'; 
$string['duration'] = 'Duration';
$string['trainersoccupiedrequired'] = 'Trainer(s) {$a} already added to another classroom in this duration';
$string['search'] = 'Search';
$string['create_session'] = 'Create a Session';
$string['location'] = 'Location';
$string['classroom_reports'] = 'Classroom Reports';
$string['pleaseselectorganization'] = 'Please Select Organization';

$string['viewmore'] = 'View More';
$string['need_self_enrol'] = 'Need Self Enrol';
$string['enrolled_classroom'] = 'My Classroom';
$string['listtype'] = 'LIST';
$string['cardtype'] = 'CARD';
$string['actions'] = 'Actions';
$string['listicon'] ='icon fa fa-bars fa-fw';
$string['cardicon'] ='icon fa fa-fw fa-th';
$string['completed'] = 'Completed';
$string['selfenrolclassroom'] = 'need Self enrol ';
$string['no_courses_assigned'] = 'No Courses assigned';
$string['session_classropm'] = 'Select Classroom';
$string['bussinessunit'] = "Bussiness Units";
$string['commercialunit'] = "Commercial Units";
$string['states'] = "States";
$string['district'] = "Districts";
$string['subdistrict'] = "Sub Districts";
$string['village'] = "Villages";
$string['territory'] = "Territory";
$string['open_costcenteridlocal_classroom_help'] = 'Organization of the classroom';
$string['open_departmentlocal_classroom_help'] = 'Country of the classroom';
$string['open_subdepartmentlocal_classroom_help'] = 'Commercial Unit of the classroom';
$string['open_level4departmentlocal_classroom_help'] = 'Commercial Area of the classroom';
$string['open_level5departmentlocal_classroom_help'] = 'Territory of the classroom';
$string['open_states_help'] = 'Search and select an available or existing state as target audience';
$string['open_district_help'] = 'Search and select an available or existing district as target audience';
$string['open_subdistrict_help'] = 'Search and select an available or existing subdistrict as target audience';
$string['open_village_help'] = 'Search and select an available or existing village as target audience';
