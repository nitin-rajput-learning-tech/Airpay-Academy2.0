<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Skills Matrix';
$string['privacy:metadata'] = 'The skills plugin stores skill level data linked to user IDs.';

// Capabilities.
$string['airpay_skills:view'] = 'View skill matrix and gap analysis';
$string['airpay_skills:manage'] = 'Manage skill categories and definitions';

// CRUD strings.
$string['addskill'] = 'Add Skill';
$string['editskill'] = 'Edit Skill';
$string['deleteskill'] = 'Delete Skill';
$string['addcategory'] = 'Add Skill Category';
$string['editcategory'] = 'Edit Category';
$string['deletecategory'] = 'Delete Category';

// Form section headings.
$string['heading_skill'] = 'Skill Definition';
$string['heading_levels'] = 'Proficiency Levels';
$string['heading_category'] = 'Category Identity';
$string['heading_visual'] = 'Visual Style';

// Form labels.
$string['skill_name'] = 'Skill name';
$string['category_name'] = 'Category name';
$string['description'] = 'Description';
$string['category'] = 'Category';
$string['max_level'] = 'Max proficiency level';
$string['max_level_help'] = 'The highest proficiency level a learner can reach for this skill. Use 5 for skills where mastery matters; use 3 for binary/awareness-only skills.';
$string['icon'] = 'Icon';
$string['color'] = 'Brand colour';
$string['color_help'] = 'Hex colour code (e.g. #0066A7). Used for the category badge in skill listings and gap analysis charts.';
$string['sort_order'] = 'Display order';

// Errors.
$string['missingrequiredfields'] = 'Please fill in all required fields.';
$string['invalidcategory'] = 'Selected category does not exist.';
$string['categoryinuse'] = 'Cannot delete category — it still has skills assigned. Move or delete those skills first.';
$string['color_invalid'] = 'Colour must be a hex code starting with # (e.g. #0066A7).';
$string['confirmdeleteskill'] = 'Delete skill "{$a}"? This will also remove all role mappings, course mappings, and learner records for this skill. This cannot be undone.';
$string['confirmdeletecategory'] = 'Delete category "{$a}"? Only allowed if no skills are assigned to it.';

// Success.
$string['skillcreated'] = 'Skill created.';
$string['skillupdated'] = 'Skill updated.';
$string['skilldeleted'] = 'Skill deleted.';
$string['categorycreated'] = 'Category created.';
$string['categoryupdated'] = 'Category updated.';
$string['categorydeleted'] = 'Category deleted.';

$string['skills'] = 'Skills';
$string['skillmatrix'] = 'Skill Matrix';
$string['gapanalysis'] = 'Gap Analysis';
$string['yourskills'] = 'Your Skills';
$string['requiredskills'] = 'Required for your role';
$string['currentlevel'] = 'Current Level';
$string['requiredlevel'] = 'Required Level';
$string['gap'] = 'Gap';
$string['met'] = 'Met';
$string['partial'] = 'In Progress';
$string['missing'] = 'Not Started';
$string['skillsgap'] = '{$a->gaps} skill gaps out of {$a->total}';
$string['skillsmet'] = '{$a->met}/{$a->total} skills at required level ({$a->percentage}%)';
$string['recommendedcourses'] = 'Recommended to fill gaps';
$string['nodesignation'] = 'No role/designation set. Contact your manager to update your profile.';
$string['noskillsmapped'] = 'No skills mapped for your role yet. Check back soon.';
$string['teamheatmap'] = 'Team Skills Heat Map';
$string['careerpath'] = 'Career Path';

// Privacy provider strings.
$string['privacy:metadata:user_skills']                = 'Earned skill levels per user.';
$string['privacy:metadata:user_skills:userid']         = 'The user whose skill level is recorded.';
$string['privacy:metadata:user_skills:skillid']        = 'The skill being recorded.';
$string['privacy:metadata:user_skills:current_level']  = 'The level the user has been credited at (1..max_level).';
$string['privacy:metadata:user_skills:source']         = 'Whether this was derived from a course completion, assessment, or manual entry.';
$string['privacy:metadata:user_skills:source_id']      = 'Course or assessment ID that granted the level.';
$string['privacy:metadata:user_skills:timecreated']    = 'When the level was first recorded.';

// P1 #22 (2026-05-16) — skill-level audit log privacy metadata.
$string['privacy:metadata:user_skill_hist']                = 'Append-only audit log of every change to a user\'s skill level. Lets HR answer "when did this user reach this level?" and supports compliance reporting.';
$string['privacy:metadata:user_skill_hist:userid']         = 'The user whose skill level changed.';
$string['privacy:metadata:user_skill_hist:skillid']        = 'The skill that changed.';
$string['privacy:metadata:user_skill_hist:previous_level'] = 'The level the user held before this change (0 if they had no level).';
$string['privacy:metadata:user_skill_hist:new_level']      = 'The level the user holds after this change.';
$string['privacy:metadata:user_skill_hist:source']         = 'What triggered the change (course completion, assessment, manual entry, import).';
$string['privacy:metadata:user_skill_hist:source_id']      = 'Course or assessment ID that triggered the change.';
$string['privacy:metadata:user_skill_hist:changed_by_userid'] = 'The acting user id (manager / admin). Null when the change was triggered automatically (e.g. by a course-completion observer).';
$string['privacy:metadata:user_skill_hist:timecreated']    = 'When the change was recorded.';
