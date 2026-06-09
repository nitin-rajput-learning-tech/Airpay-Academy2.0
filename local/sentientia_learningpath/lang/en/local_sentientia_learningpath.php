<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Learning Paths';

// Capabilities.
$string['sentientia_learningpath:manage'] = 'Manage learning paths';
$string['sentientia_learningpath:view'] = 'View learning paths';
$string['sentientia_learningpath:enrol'] = 'Enrol users into learning paths';
$string['sentientia_learningpath:create'] = 'Create learning paths';
$string['sentientia_learningpath:update'] = 'Edit learning paths';
$string['sentientia_learningpath:delete'] = 'Delete learning paths';

// CRUD.
$string['addpath'] = 'Add Learning Path';
$string['editpath'] = 'Edit Learning Path';
$string['deletepath'] = 'Delete Learning Path';
$string['archivepath'] = 'Archive Path';
$string['activatepath'] = 'Activate Path';

// Form sections.
$string['heading_basic'] = 'Path Details';
$string['heading_org'] = 'Organisation';
$string['heading_window'] = 'Enrolment window (optional)';
$string['heading_status'] = 'Status';

// P1 batch (2026-05-16) — compliance window.
$string['startdate']           = 'Start date';
$string['startdate_help']      = 'Optional. Path becomes enrollable from this date. Leave empty to allow enrolment immediately.';
$string['enddate']             = 'End date';
$string['enddate_help']        = 'Optional. Path stops accepting new enrolments after this date. Existing enrolled learners are unaffected.';
$string['enddate_before_start'] = 'End date must be on or after the start date.';

// P1 #11 (2026-05-16) — bulk-enrol-by-audience modal.
$string['audience_modal_title']      = 'Bulk enrol by target audience';
$string['audience_form_intro']       = 'Pick one or more filter criteria to target a group of users. The preview below updates as you change filters. Click "Enrol matching users" to commit.';
$string['audience_any']              = 'Any';
$string['audience_any_cohort']       = 'Any cohort';
$string['audience_users_matched']    = 'users match';
$string['audience_pick_at_least_one'] = 'Pick at least one filter criterion (use the regular Enrol Users form to enrol all users).';
$string['audience_enrol_button']     = 'Enrol matching users';
$string['audience_enrol_result']     = '%d new enrolment(s); %d user(s) matched the audience.';
$string['designation']               = 'Designation';
$string['region']                    = 'Region';
$string['location']                  = 'Location';
$string['employmenttype']            = 'Employment type';
$string['cohort']                    = 'Cohort';

// Field labels.
$string['name'] = 'Path name';
$string['description'] = 'Description';
$string['organisation'] = 'Organisation (tenant)';
$string['organisation_help'] = 'Choose which tenant this learning path belongs to. Leave as "No specific organisation" to make it available to all tenants.';
$string['status'] = 'Status';
$string['status_active'] = 'Active';
$string['status_archived'] = 'Archived';

// Errors.
$string['missingrequiredfields'] = 'Please fill in all required fields.';
$string['confirmdelete'] = 'Are you sure you want to delete "{$a}"? This will permanently remove the path, all its course assignments, and learner enrolments. This cannot be undone.';
$string['confirmarchive'] = 'Are you sure you want to archive "{$a}"? It will no longer be available to new learners.';
$string['confirmactivate'] = 'Make "{$a}" active? Learners will be able to enrol again.';

// Success.
$string['pathcreated'] = 'Learning path created.';
$string['pathupdated'] = 'Learning path updated.';
$string['pathdeleted'] = 'Learning path deleted.';
$string['pathstatuschanged'] = 'Status updated.';

// G-04 — assignment + enrolment errors / UI strings.
$string['toomanycourses'] = 'Too many courses in one request. Limit is 100 per call.';
$string['toomanyusers'] = 'Too many users in one request. Limit is 500 per call.';
$string['filterstoolong'] = 'Filter payload too long.';
$string['missingrequiredfields'] = 'Required fields are missing.';

// View page (path detail).
$string['view_path_title'] = 'Learning path: {$a}';
$string['tab_overview'] = 'Overview';
$string['tab_courses'] = 'Courses';
$string['tab_users'] = 'Users';
$string['add_courses'] = 'Add Courses';
$string['enrol_users'] = 'Enrol Users';
$string['back_to_paths'] = 'Back to learning paths';

// Confirm prompts.
$string['confirm_unassign_course'] = 'Remove "{$a}" from this learning path? Users keep their course completions.';
$string['confirm_unenrol_user'] = 'Unenrol {$a} from this learning path?';

// Empty states.
$string['no_courses_assigned'] = 'No courses assigned yet. Click "Add Courses" to get started.';
$string['no_users_enrolled'] = 'No users enrolled yet. Click "Enrol Users" to add learners.';

// Privacy strings (Phase Z.1).
$string['privacy:metadata:lp'] = 'Per-learning-path user assignments.';
$string['privacy:metadata:lp:pathid'] = 'Learning path ID.';
$string['privacy:metadata:lp:userid'] = 'Assigned user ID.';
$string['privacy:metadata:lp:status'] = 'Assignment status.';
$string['privacy:metadata:lp:timecreated'] = 'Assignment timestamp.';
$string['privacy:metadata:lp:timemodified'] = 'Last update timestamp.';
