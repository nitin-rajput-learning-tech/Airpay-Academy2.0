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
 * @package BizLMS
 * @subpackage local_courses
 */
$string['pluginname'] = 'Forum';
$string['course'] = 'Course';
$string['organization']='Organization';
$string['mooc'] = 'MOOC';
$string['classroom'] = 'Classroom';
$string['elearning'] = 'E-Learning';
$string['learningplan'] = 'Learning Path';
$string['type'] = 'Type';
$string['category'] = 'Category';
$string['enrolled'] = 'Subscribers';
$string['completed'] = 'Completions';
$string['manual_enrolment'] = 'Manual Enrollment';
$string['add_users']='<< Add Users';
$string['remove_users']='Remove Users >>';
$string['employeesearch']='Filter';
$string['agentsearch']='Agent Search';
$string['empnumber']='Learner ID';
$string['email']='Email';
$string['band'] = 'Band';
$string['departments']='Countries';
$string['sub_departments']='Commercial Unit';
$string['sub-sub-departments']='Sub Commercial Units';
$string['designation'] = 'Designation';
$string['im:already_in'] = 'The user "{$a}" was already enroled to this forum';
$string['im:enrolled_ok'] = 'The user "{$a}" has successfully enroled to this forum ';
$string['im:error_addg'] = 'Error in adding group {$a->groupe}  to forum {$a->courseid} ';
$string['im:error_g_unknown'] = 'Error, unkown group {$a} ';
$string['im:error_add_grp'] = 'Error in adding grouping {$a->groupe} to forum {$a->courseid}';
$string['im:error_add_g_grp'] = 'Error in adding group {$a->groupe} to grouping {$a->groupe}';
$string['im:and_added_g'] = ' and added to Moodle\'s  group  {$a}';
$string['im:error_adding_u_g'] = 'Error in adding to group  {$a}';
$string['im:already_in_g'] = ' already in group {$a}';
$string['im:stats_i'] = '{$a} enroled &nbsp&nbsp';
$string['im:stats_g'] = '{$a->nb} group(s) created : {$a->what} &nbsp&nbsp';
$string['im:stats_grp'] = '{$a->nb} grouping(s) created : {$a->what} &nbsp&nbsp';
$string['im:err_opening_file'] = 'error opening file {$a}';
$string['im:user_notcostcenter'] = '{$a->user} not assigned to {$a->csname} costcenter';
$string['mass_enroll'] = 'Bulk enrolments';
$string['mass_enroll_info'] =
"<p>
With this option you are going to enrol a list of known users from a file with one account per line
</p>
<p>
<b> The firstline </b> the empty lines or unknown accounts will be skipped. </p>
<p>
<b>The first one must contains a unique email of the target user </b>
</p>";
$string['firstcolumn'] = 'First column contains';
$string['creategroups'] = 'Create group(s) if needed';
$string['creategroupings'] = 'Create  grouping(s) if needed';
$string['enroll'] = 'Enrol them to my forum';
$string['im:user_unknown'] = 'The user with an username "{$a}" doesn\'t exists in the System';
$string['points'] = 'Points';
$string['createforum'] = '<i class="icon popupstringicon fa fa-comments-o" aria-hidden="true"></i>Create Forum <div class="popupstring">Here you can create Forum</div>';
$string['editforum'] = '<i class="icon popupstringicon fa fa-comments-o" aria-hidden="true"></i>Update Forum <div class="popupstring">Here you can update Forum</div>';
$string['description']   = 'User with Username "{$a->userid}"  created the forum  "{$a->courseid}"';
$string['desc']   = 'User with Username "{$a->userid}" has updated the forum  "{$a->courseid}"';
$string['descptn']   = 'User with Username "{$a->userid}" has deleted the forum with forumid  "{$a->courseid}"';
$string['usr_description']   = 'User with Username "{$a->userid}" has created the user with Username  "{$a->user}"';
$string['usr_desc']   = 'User with Username "{$a->userid}" has updated the user with Username  "{$a->user}"';
$string['usr_descptn']   = 'User with Username "{$a->userid}" has deleted the user with userid  "{$a->user}"';
$string['ilt_description']   = 'User with Username "{$a->userid}"  created the ilt  "{$a->f2fid}"';
$string['ilt_desc']   = 'User with Username "{$a->userid}" has updated the ilt "{$a->f2fid}"';
$string['ilt_descptn']   = 'User with Username "{$a->userid}" has deleted the ilt "{$a->f2fid}"';
$string['forumcompday'] = 'forum Completion Days';
$string['forumcreator'] = 'forum Creator';
$string['forumcode'] = 'Forum Code';
$string['addcategory'] = '<i class="fa fa-comments-o popupstringicon" aria-hidden="true"></i><i class="fa fa-comments-o secbook popupstringicon cat_pop_icon" aria-hidden="true"></i> Create New Category <div class= "popupstring"></div>';
$string['editcategory'] = '<i class="fa fa-comments-o popupstringicon" aria-hidden="true"></i><i class="fa fa-comments-o secbook popupstringicon cat_pop_icon" aria-hidden="true"></i> Update Category <div class= "popupstring"></div>';
$string['forumcat'] = 'forum Categories';
$string['deletecategory'] = 'Delete Category';
$string['top'] = 'Top';
$string['parent'] = 'Parent';
$string['actions'] = 'Actions';
$string['count'] = 'Number of forum';
$string['categorypopup'] = 'Category {$a}';
$string['missingtype'] = 'Missing Type';
$string['catalog'] = 'Catalog';
$string['noforumdesc'] = 'No description provided';
$string['apply'] = 'Apply';
$string['open_path'] = 'Costcenter';
$string['uploadforumpreview'] = 'Upload forum preview';
$string['uploadforumresult'] = 'Upload forum results';
$string['uploadforum'] = 'Upload forum';
$string['forumfile'] = 'File';
$string['csvdelimiter'] = 'CSV delimiter';
$string['encoding'] = 'Encoding';
$string['rowpreviewnum'] = 'Preview rows';
$string['preview'] = 'Preview';
$string['forumprocess'] = 'forum process';
$string['shortnametemplate'] = 'Template to generate a shortname';
$string['templatefile'] = 'Restore from this file after upload';
$string['reset'] = 'Reset forum after upload';
$string['defaultvalues'] = 'Default forum values';
$string['enrol'] = 'Enrol';
$string['forumexistsanduploadnotallowedwithargs'] = 'forum is already exists with shortname "{$a}", please choose other unique shortname.';
$string['canonlycreateforumincategoryofsameorganisation'] = 'You can only create the forum under your assigned organisation';
$string['canonlycreateforumincategoryofsameorganisationwithargs'] = 'Cannot create a forum under the category \'{$a}\'';
$string['createcategory'] = 'Create New Category';
$string['manage_forum'] = 'Manage Forum';
$string['manage_forum'] = 'Manage forum';
$string['leftmenu_browsecategories'] = 'Manage Categories';
$string['forumother_details'] = 'Other Details';
$string['view_forum'] = 'view forum';
$string['deleteconfirm'] = 'Are you sure, you want to delete "<b>{$a->name}</b>" forum?</br> Once deleted, it can not be reverted.';
$string['department'] = 'Country';
$string['forumcategory'] = 'Category';
$string['fullnameforum'] = 'Fullname';
$string['forumummary'] = 'Summary';
$string['forumoverviewfiles'] = 'Banner image';
$string['startdate'] = 'Start Date';
$string['enddate'] = 'End Date';
$string['program'] = 'Program';
$string['certification'] = 'Certification';
$string['create_forum'] = 'Create Forum';
$string['userenrolments'] = 'User enrollments';
$string['certificate'] = 'Certificate';
$string['points_positive'] = 'Points must be greater than 0';
$string['forumcompletiondays_positive'] ='Completion days must be greater than 0';
$string['enrolusers'] = 'Enrol Users';
$string['grader'] = 'Grader';
$string['activity'] = 'Activity';
$string['forum'] = 'forum';
$string['nocategories'] = 'No categories available';
$string['nosameenddate'] = '"Cut-off date" should not be less than "Due date"';
$string['forummanual'] = 'Download sample Excel sheet and fill the field values in the format specified below.';
$string['help_1'] = '<table border="1">
<tr><td></td><td style="text-align:left;border-left:1px solid white;padding-left:50px;"><b>Mandatory Fields</b></td><tr>
<th>Field</th><th>Restriction</th>
<tr><td>fullname</td><td>Fullname of the forum.</td></tr>
<tr><td>forum-code</td><td>forum-code of the forum.</td></tr>
<tr><td>category_code</td><td>Enter the category code(you can find this code in Manage Categories page).</td></tr>
<tr><td>forumtype</td><td>Type of the forum(Comma seperated)(Ex:classroom,elearning,certification,learningpath,program).</td></tr>
<tr><td>format</td><td>Enter forum format(Ex: singleactivity,social,toggletop,topics,weeks).</td></tr>';

$string['help_2'] = '</td></tr>
<tr><td></td><td style="text-align:left;border-left:1px solid white;padding-left:50px;"><b>Normal Fields</b></td><tr>
<th>Field</th><th>Restriction</th>
<tr><td>Summary</td><td>Summary of the forum.</td></tr>
<tr><td>Cost</td><td>Cost of the forum.</td></tr>
<tr><td>country_code</td><td>Provide Country code. Country must already exist in system as part of organization hierarchy.</td></tr>
<tr><td>commercial_unit_code</td><td>Enter Commercial Unit Code. Bussiness Unit must already exist under specified Country in system as part of organization hierarchy.</td></tr>
<tr><td>commercial_area_code</td><td>Enter Commercial Unit Code. Commercial Unit must already exist under specified Commercial Unit in system as part of organization hierarchy.</td></tr>
<tr><td>territory_code</td><td>Enter Territory Code. Territory must already exist under specified Commercial Unit in system as part of organization hierarchy.</td></tr>
<tr><td>Points</td><td>Points for the forum.</td></tr>
<tr><td>completiondays</td><td>completiondays should be greater than \'0\'. i.e, 1,2,3..etc</td></tr>
</table>';
$string['back_upload'] = 'Back to upload forum';
$string['manual'] = 'Help manual';
$string['enrolledusers'] = 'Enrolled users';
$string['notenrolledusers'] = 'Not enrolled users';
$string['finishbutton'] = 'Finish';
$string['updateforum'] = 'Update forum';
$string['forum_name'] = 'Forum Name';
$string['completed_users'] = 'Completed Users';
$string['forum_filters'] = 'forum Filters';
$string['back'] = 'Back';
$string['sample'] = 'Sample';
$string['selectdept'] = '--Select Country--';
$string['selectsubdept'] = '--Select Commercial Unit--';
$string['selectorg'] = '--Select Organization--';
$string['selectcat'] = '--Select Category--';
$string['select_cat'] = '--Select Categories--';
$string['selectforumtype'] = '--Select forum Type--';
$string['reset'] = 'Reset';
$string['err_category'] = 'Please select Category';
$string['availablelist'] = '<b>Available Users ({$a})</b>';
$string['selectedlist'] = 'Selected users';
$string['status'] = 'Status';
$string['select_all'] = 'Select All';
$string['remove_all'] = 'Un Select All';
$string['not_enrolled_users'] = '<b>Not Enrolled Users ({$a})</b>';
$string['enrolled_users'] = '<b> Enrolled Users ({$a})</b>';
$string['remove_selected_users'] = '<b> Un Enroll Users </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['remove_all_users'] = '<b> Un Enroll All Users </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['add_selected_users'] = '<i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i><b> Enroll Users</b>';
$string['add_all_users'] = ' <i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i> <b> Enroll All Users </b>';$string['course_status_popup'] = 'Activity status for {$a}';
$string['auto_enrol'] = 'Auto Enroll';
$string['need_manage_approval'] = 'Need Manager Approval';
$string['costcannotbenonnumericwithargs'] ='Cost should be in numeric but given "{$a}"';
$string['pointscannotbenonnumericwithargs'] ='Points should be in numeric but given "{$a}"';
$string['need_self_enrol'] = 'Need Self Enroll';
$string['enrolluserssuccess'] = '<b>{$a->changecount}</b> Employee(s) successfully enrolled to this <b>"{$a->course}"</b> course .';
$string['unenrolluserssuccess'] = '<b>{$a->changecount}</b> Employee(s) successfully un enrolled from this <b>"{$a->course}"</b> course .';

$string['enrollusers'] = 'forum <b>"{$a}"</b> enrollment is in process...';

$string['un_enrollusers'] = 'forum <b>"{$a}"</b> un enrollment is in process...';
$string['click_continue'] = 'Click on continue';
$string['bootcamp']= 'XSeeD';
$string['manage_br_forum'] = 'Manage <br/> forum';
$string['noforumavailiable'] = 'No Forum Available';
$string['taskforumnotification'] = 'forum Notification Task';
$string['taskforumreminder'] = 'forum Reminder Task';
$string['pleaseselectorganization'] = 'Please Select Organization';
$string['pleaseselectcategory'] = 'Please Select Category';
$string['enableforum'] = 'Are you sure, want Active forum <b>\'{$a}\'</b>?';
$string['disableforum'] = 'Are you sure, want In-active forum <b>\'{$a}\'</b>?';
$string['forumconfirm'] = 'Confirm';
$string['open_pathforum_help'] = 'Organisation for the forum';
$string['open_departmentidforum_help'] = 'Country for the forum';
$string['open_identifiedasforum_help'] = 'Type of the forum (multi select)';
$string['open_pointsforum_help'] = 'Points for the forum default (0)';
$string['selfenrolforum_help'] = 'Check yes if required self enrollment to the forum';
$string['approvalrequiredforum_help'] = 'Check yes if required to enable request manager for enrolling to the forum';
$string['open_costforum_help'] = 'Cost of the forum';
$string['open_skillforum_help'] = 'Skill achieved on completion of forum';
$string['open_levelforum_help'] = 'Level achieved on completion of forum';
$string['open_pathforum'] = 'Organisation';
$string['open_departmentidforum'] = 'Country';
$string['open_identifiedasforum'] = 'Type';
$string['open_pointsforum'] = 'Points';
$string['selfenrolforum'] = 'self enrollment';
$string['approvalrequiredforum'] = 'request manager for enrolling';
$string['open_costforum'] = 'Cost';
$string['open_skillforum'] = 'Skill ';
$string['open_levelforum'] = 'Level';
$string['notyourorg_msg'] = 'You have tried to view this activity is not belongs to your Organization';
$string['notyourdept_msg'] = 'You have tried to view this activity is not belongs to your Country';
$string['notyourorgforum_msg'] = 'You have tried to view this forum is not belongs to your Organization';
$string['notyourdeptforum_msg'] = 'You have tried to view this forum is not belongs to your Country';
$string['notyourorgforumreport_msg'] = 'You have tried to view this Grader report is not your Organization forum, so you cann\'t access this page';
$string['need_manager_approval '] = 'need_manager_approval';
$string['categorycode'] = 'Category Code';
$string['categorycode_help'] = 'The Category Code of a forum category is only used when matching the category against external systems and is not displayed anywhere on the site. If the category has an official code name it may be entered, otherwise the field can be left blank.';

$string['categories'] = 'Sub Categories :  ';
$string['makeactive'] = 'Make Active';
$string['makeinactive'] = 'Make Inactive';
$string['forum:bulkupload'] = 'Bulk upload';
$string['forum:create'] = 'Create forum';
$string['forum:delete'] = 'Delete  forum';
$string['forum:grade_view'] = 'Grade view';
$string['forum:manage'] = 'Manage forum';
$string['forum:report_view'] = 'Report view';
$string['forum:unenrol'] = 'Unenrol forum';
$string['forum:update'] = 'Update forum';
$string['forum:view'] = 'View forum';
$string['forum:visibility'] = 'forum visibility';
$string['forum:enrol'] = 'forum enrol';

$string['reason_linkedtocostcenter'] = 'As this forum category is linked with the Organization/Country, you can not delete this category';
$string['reason_subcategoriesexists'] = 'As we have sub-categories in this forum category, you can not delete this category';
$string['reason_forumexists'] = 'As we have forum in this forum category, you can not delete this category';
$string['reason'] = 'Reason';
$string['completiondayscannotbeletter'] = 'Cannot create forum with completion days as {$a} ';
$string['completiondayscannotbeempty'] = 'Cannot create forum without completion days.';
$string['tagarea_forum'] = 'forum';
$string['subcategories'] = 'Subcategories';
$string['tag'] = 'Tag';
$string['tag_help'] = 'tag';
$string['open_subdepartmentforum_help'] = 'Commercial Unit of the forum';
$string['open_subdepartmentforum'] = 'Commercial Unit';
$string['suspendconfirm'] = 'Confirmation';
$string['activeconfirm'] = 'Are you sure to make category active ?';
$string['inactiveconfirm'] = 'Are you sure to make category inactive ?';
$string['yes'] = 'Confirm';
$string['no'] = 'Cancel';
$string['add_certificate'] = 'Add Certificate';
$string['add_certificate_help'] = 'If you want to issue a certificate when user completes this forum, please enable here and select the template in next field (Certificate template)';
$string['select_certificate'] = 'Select Certificate';
$string['certificate_template'] = 'Certificate template';
$string['certificate_template_help'] = 'Select Certificate template for this forum';
$string['err_certificate'] = 'Missing Certificate template';
$string['download_certificate'] = 'Download Certificate';
$string['unableto_download_msg'] = "Still this user didn't completed the forum, so you cann't download the certificate";

$string['completionstatus'] = 'Completion Status';
$string['completiondate'] = 'Completion Date';
$string['nousersmsg'] = 'No users Available';
$string['employeename'] = 'Employee Name';
$string['completed'] = 'Completed';
$string['notcompleted'] = 'Not Completed';
$string['messageprovider:forum_complete'] = 'forum Completion Notification';
$string['messageprovider:forum_enrol'] = 'forum Enrollment Notification';
$string['messageprovider:forum_notification'] = 'forum Notification';
$string['messageprovider:forum_reminder'] = 'forum Reminder';
$string['messageprovider:forum_unenroll'] = 'forum Unenrollment Notification';
$string['completed_forum'] = 'My forum';
$string['inprogress_forum'] = 'My forum';
$string['selectforum'] = 'Select forum';
$string['enrolmethod'] = 'Enrolment Method';
$string['deleteuser'] = 'Delete confirmation';
$string['confirmdelete'] = 'Are you sure,do you want to unenroll this user.';
$string['edit'] = 'Edit';
$string['err_points'] = 'Points cannot be empty';
$string['browseevidences'] = 'Browse Evidence';
$string['forumevidencefiles'] = 'forum Evidence';
$string['forumevidencefiles_help'] = 'The forum evidence is displayed in the forum overview on the Dashboard. Additional accepted file types and more than one file may be enabled by a site administrator. If so, these files will be displayed next to the forum summary on the list of forum page.';
$string['browseevidencesname'] = '{$a} Evidences';
$string['selfcompletion'] = 'Self Completion';
$string['selfcompletionname'] = '{$a} Self Completion';
$string['selfcompletionconfirm'] = 'Are you sure,do you want to forum "{$a}" self completion.';

// strings added on 23 sept 2020.
$string['saveandcontinue'] = 'Save & Continue';
$string['forumoverview'] = 'forum Overview';
$string['selectlevel'] = 'Select Level';
$string['errorinrequestprocessing'] = 'Error occured while processing requests';
$string['featuredforum'] = 'Featured forum';
$string['errorinsubmission'] = 'Error in submission';
$string['recentlyenrolledforum'] = 'Recently Enrolled forum';
$string['recentlyaccessedforum'] = 'Recently Accessed forum';
$string['securedforum'] = 'Secured forum';
$string['open_secureforum_forum'] = 'Secured forum';
$string['open_secureforum_forum_help'] = 'Once selected as yes this forum will not be displayed over the mobile app.';
$string['parent_category'] = 'Parent Category';
$string['parent_category_code'] = 'Parent Category Code';
$string['select_skill'] = 'Select Skill';
$string['select_level'] = 'Select Level';
$string['what_next'] = "What's next?";
$string['doyouwantto_addthecontent'] = 'Do you want to <b>add the Questions</b>';
$string['doyouwantto_enrolusers'] = 'Do you want to <b>enrol users</b>';
$string['goto'] = 'Go to';
$string['search'] = 'Search';
$string['no_users_enrolled'] = 'No users enrolled to this forum';
$string['missingfullname'] = 'Please Enter Valid forum Name';
$string['missingshortname'] = 'Please Enter Valid forum Code';
$string['missingtype'] = 'Please Select Type';
$string['forum_reports'] = 'forum Reports';
$string['cannotuploadforumwithlob'] = 'With out Country cannot upload a forum with Commercial Unit';
$string['categorycodeshouldbedepcode'] = 'Category Code should be under the Country i.e \'{$a}\'';
$string['categorycodeshouldbesubdepcode'] = 'Category Code should be short name of Commercial Unit i.e \'{$a}\'';
$string['subdeptshouldunderdepcode'] = 'Commercial Unit should be under the Country i.e \'{$a}\'';
$string['forum_name_help'] = 'Name for the forum';
$string['forumcode_help'] = 'Code for the forum';
$string['enrolled_forum'] = 'My forum';
$string['listtype']	='LIST';
$string['cardtype']	='CARD';
$string['listicon'] ='icon fa fa-bars fa-fw';
$string['cardicon'] ='icon fa fa-fw fa-th';
$string['forumtype'] = 'forum Type';
$string['requestforenroll'] = 'Request';
$string['download_forum'] = 'Download forum';
$string['subcategory'] = 'Sub Categories';
$string['forumname'] = 'forum Name';
$string['lastaccess'] = 'Last Access';
$string['progress'] = 'Progress';
$string['enrollments'] = 'Enrolled';
$string['skill'] = 'Skill';
$string['ratings'] = 'Ratings';
$string['tags'] = 'Tags';
$string['subdepartment'] = 'Commercial Unit';
$string['summary'] = 'Summary';
$string['format'] = 'forum Format';
$string['selfenrol'] = 'Self Enrol';
$string['approvalreqdforum_help'] = 'Select.

* Yes - If you would like to enforce manager or organization head approval while self enrolling to forum
* No - If you would like user to self enroll to forum without an approval from manager or organization head';
$string['forumdescription'] = 'Description';
$string['exportforum'] = 'Export forum to Excel';
$string['make_inactive'] = 'Make Inactive';
$string['make_active'] = 'Make Active';
$string['departmentnotfound'] ='Country not found i.e \'{$a}\'';
$string['categorycode'] = 'Category Code';
// forum types strings
$string['open_forumtypeforum'] = 'forum type';
$string['open_forumtypeforum_help'] = 'Select the forum type';
$string['forum_type'] = 'forum Type';
$string['forum_type_shortname'] = 'Code';
$string['viewforum_type'] = 'Add/View forum type';
$string['add_forumtype'] = 'Add forum type';
$string['edit_forumtype'] = 'Edit forum type';
$string['listtype']	='LIST';
$string['listicon'] ='icon fa fa-bars fa-fw';
$string['name'] = 'Name';
$string['enableforumtype'] = 'Are you sure to activate forum type <b>{$a}</b>';
$string['disableforumtype'] = 'Are you sure to inactivate forum type <b>{$a}</b>';
$string['statusconfirm'] = 'Are you sure you want to {$a->status} "{$a->name}"';
$string['forumtypeexists'] = 'forum type already created ({$a})';
$string['deleteforumtypeconfirm'] = 'Are you sure, you want to delete <b>{$a->name}</b> forum type?</br> Once deleted, it can not be reverted.';
$string['err_forumtype'] = 'Please enter forum type';
$string['err_forumtypeshortname'] = 'Please enter shortname';
$string['add_forum_type'] = 'Add forum Type';
$string['cannotcreateorupdateforum'] = 'This forum Type is not Available for this Category i.e \'{$a}\'';
$string['forumcodeexists'] = 'Forum code already exists ({$a})';
$string['deleteforumtypenotconfirm'] = 'You cannot delete <b>{$a->name}</b> as it is currently mapped to a forum. Please unmap to delete.';
$string['reason'] = 'Reason';
$string['open_costcenteridlocal_forum'] = 'Organisation';
$string['open_departmentlocal_forum'] = 'Bussiness Unit';
$string['open_subdepartmentlocal_forum'] = 'Department';
$string['open_level4departmentlocal_forum'] = 'Sub Department';
$string['open_level5departmentlocal_forum'] = 'Territory';
$string['pleaseselectidentifiedtype'] = 'Please Select Type';

$string['open_costcenteridlocal_forum_help'] = 'Organization of the forum';
$string['open_departmentlocal_forum_help'] = 'Bussiness Unit of the forum';
$string['open_subdepartmentlocal_forum_help'] = 'Department of the forum';
$string['open_level4departmentlocal_forum_help'] = 'Sub Department of the forum';
$string['open_level5departmentlocal_forum_help'] = 'Territory of the forum';

$string['cannotuploadforumwithsubdepartment'] = 'With out Commercial Unit cannot upload a forum with Commercial Area';
$string['categorycodeshouldbesubdepcode'] = 'Category Code should be under the Commercial Unit i.e \'{$a}\'';
$string['categorycodeshouldbesubsubdepcode'] = 'Category Code should be short name of Commercial Area i.e \'{$a}\'';
$string['subdeptshouldundersubdepcode'] = 'Commercial Area should be under the Commercial Unit i.e \'{$a}\'';
$string['subdepartmentnotfound'] ='Commercial Unit not found i.e \'{$a}\'';

$string['cannotuploadforumwithsubsubdepartment'] = 'With out Commercial Area cannot upload a forum with Territory';
$string['categorycodeshouldbesubsubdepcode'] = 'Category Code should be under the Commercial Area i.e \'{$a}\'';
$string['categorycodeshouldbesubsubsubdepcode'] = 'Category Code should be short name of Territory i.e \'{$a}\'';
$string['subdeptshouldundersubsubdepcode'] = 'Territory should be under the Commercial Area i.e \'{$a}\'';
$string['subsubdepartmentnotfound'] ='Commercial Area not found i.e \'{$a}\'';
$string['open_states_help'] = 'Search and select an available or existing state as target audience';
$string['open_district_help'] = 'Search and select an available or existing district as target audience';
$string['open_subdistrict_help'] = 'Search and select an available or existing subdistrict as target audience';
$string['open_village_help'] = 'Search and select an available or existing village as target audience';
$string['username'] = 'Username';
$string['enablereports'] = 'forum reports are currently not configured. <a href="{$a}" target="_blank"> <u>Click here </u></a> to configure reports';
$string['forumcompday_atsearch'] = 'Completion Days';
$string['addnewforum'] = 'Add New forum';
$string['manage_forum'] = 'Manage Forum';
$string['maxgrade'] = 'Max grade';
$string['gradepass'] = 'Pass grade';
$string['forum'] = 'Forum';
$string['view_forum'] = 'View Forum';
$string['manage_br_forum'] = 'Manage Forum';
$string['entergradepass'] = "Please enter Grade pass";
$string['noforumavailable']= 'No Forum Available';
$string['enrolled_forum'] = "My Forum";
$string['inprogress_forum'] = "My Forum";
$string['completed_forum'] = "My Forum";
$string['shouldbeless'] = 'pass grade shoulb be lessthan Max grade {$a}';
$string['numeric'] = 'Only numeric values';
$string['create_newforum'] = 'Create New Forum';
$string['forumoverviewfiles_help'] = 'The forum image is displayed in the forum overview on the Dashboard. Additional accepted file types and more than one file may be enabled by a site administrator. If so, these files will be displayed next to the forum summary on the list of forum page.';
$string['forumummary_help'] = 'The forum summary is displayed in the list of forum. A forum search searches forum summary text in addition to forum names.';
$string['shortnametaken'] = 'Short name is already used for another forum ({$a})';
$string['subscribeusermsg'] = 'Are you sure, you want to Subscribe <b>{$a->name}</b> ?';
$string['subscribeuser'] = 'Subscription confirmation';
$string['typeforum'] ='Forum Event';
$string['enablecourse'] = 'Are you sure, want Active Forum <b>\'{$a}\'</b>?';
$string['disablecourse'] = 'Are you sure, want In-active Forum <b>\'{$a}\'</b>?';
$string['courseconfirm'] = 'Confirm';
$string['nocourseavailiable'] = 'No Forums Available';

