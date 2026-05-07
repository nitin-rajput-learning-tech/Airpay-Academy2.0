<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Certification Programs';

// Capabilities.
$string['airpay_programs:view']   = 'View certification programs';
$string['airpay_programs:manage'] = 'Manage certification programs';
$string['airpay_programs:enrol']  = 'Enrol into programs';
$string['airpay_programs:create'] = 'Create programs';
$string['airpay_programs:update'] = 'Edit programs';
$string['airpay_programs:delete'] = 'Delete programs';

// CRUD strings.
$string['addprogram'] = 'Add Certification Program';
$string['editprogram'] = 'Edit Program';
$string['deleteprogram'] = 'Delete Program';
$string['publishprogram'] = 'Publish Program';
$string['archiveprogram'] = 'Archive Program';
$string['draftprogram'] = 'Move to Draft';

// Form sections.
$string['heading_basic'] = 'Program Details';
$string['heading_org'] = 'Organisation';
$string['heading_completion'] = 'Completion Rules';
$string['heading_status'] = 'Status';

// Field labels.
$string['name'] = 'Program name';
$string['description'] = 'Description';
$string['organisation'] = 'Organisation (tenant)';
$string['completion_rule'] = 'How learners complete this program';
$string['completion_all_levels'] = 'Complete ALL levels (sequential certification)';
$string['completion_any_level'] = 'Complete ANY level (parallel certification)';
$string['status'] = 'Status';
$string['status_draft'] = 'Draft';
$string['status_active'] = 'Active';
$string['status_archived'] = 'Archived';

// Errors.
$string['missingrequiredfields'] = 'Please fill in all required fields.';
$string['invalidstatus'] = 'Invalid status value.';
$string['confirmdelete'] = 'Are you sure you want to delete "{$a}"? This will permanently remove the program, all its levels, course assignments, and learner enrolments. This cannot be undone.';
$string['confirmpublish'] = 'Publish "{$a}" to make it available to learners?';
$string['confirmarchive'] = 'Archive "{$a}"? It will no longer accept new enrolments.';
$string['confirmdraft'] = 'Move "{$a}" back to draft? It will be hidden from learners.';

// Success.
$string['programcreated'] = 'Certification program created.';
$string['programupdated'] = 'Program updated.';
$string['programdeleted'] = 'Program deleted.';
$string['programstatuschanged'] = 'Program status updated.';

// G-03: View detail page + tabs.
$string['view_program_title']    = 'Program: {$a}';
$string['back_to_programs']      = 'Back to programs';
$string['tab_overview']          = 'Overview';
$string['tab_levels']            = 'Levels';
$string['tab_users']             = 'Users';
$string['no_description']        = 'No description set.';
$string['updated']               = 'Updated';
$string['levels_count_label']    = '{$a} levels';
$string['enrolled_count_label']  = '{$a} enrolled';

// Levels tab.
$string['add_level']             = 'Add Level';
$string['edit_level']            = 'Edit Level';
$string['delete_level']          = 'Delete Level';
$string['level_name']            = 'Level name';
$string['level_description']     = 'Description';
$string['level_completion']      = 'Completion';
$string['level_required']        = 'Required to complete the program';
$string['level_optional']        = 'Optional level (skippable)';
$string['level_position']        = 'Position';
$string['level_courses']         = 'Courses';
$string['no_levels']             = 'This program has no levels yet. Add at least one level to begin.';
$string['levelcreated']          = 'Level added.';
$string['levelupdated']          = 'Level updated.';
$string['leveldeleted']          = 'Level deleted.';
$string['confirm_delete_level']  = 'Delete the level "{$a}"? This will also remove its course assignments. This cannot be undone.';
$string['toomanylevels']         = 'Too many levels in one request (limit 200).';

// Courses-per-level sub-page.
$string['manage_level_courses']  = 'Manage courses for: {$a}';
$string['back_to_program']       = 'Back to program';
$string['add_courses']           = 'Add Courses';
$string['no_courses_assigned']   = 'No courses assigned to this level yet.';
$string['confirm_unassign_course'] = 'Remove "{$a}" from this level?';
$string['courseassigned']        = 'Courses assigned to level.';
$string['courseunassigned']      = 'Course removed from level.';
$string['courses_assigned_count'] = '{$a} course(s) assigned.';
$string['toomanycourses']        = 'Too many courses in one request (limit 100).';

// Users / enrolment tab.
$string['enrol_users']           = 'Enrol Users';
$string['unenrol_user']          = 'Remove from program';
$string['no_users_enrolled']     = 'No learners enrolled in this program yet.';
$string['confirm_unenrol_user']  = 'Remove {$a} from this program? Their level progress will also be cleared.';
$string['userunenrolled']        = 'User unenrolled from program.';
$string['users_enrolled_success'] = '{$a} user(s) enrolled.';

// Bounds.
$string['toomanyusers']          = 'Too many users in one request (limit 500).';
