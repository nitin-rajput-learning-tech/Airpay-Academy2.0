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
