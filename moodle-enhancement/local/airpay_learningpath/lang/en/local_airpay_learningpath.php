<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Learning Paths';

// Capabilities.
$string['airpay_learningpath:manage'] = 'Manage learning paths';
$string['airpay_learningpath:view'] = 'View learning paths';
$string['airpay_learningpath:enrol'] = 'Enrol users into learning paths';
$string['airpay_learningpath:create'] = 'Create learning paths';
$string['airpay_learningpath:update'] = 'Edit learning paths';
$string['airpay_learningpath:delete'] = 'Delete learning paths';

// CRUD.
$string['addpath'] = 'Add Learning Path';
$string['editpath'] = 'Edit Learning Path';
$string['deletepath'] = 'Delete Learning Path';
$string['archivepath'] = 'Archive Path';
$string['activatepath'] = 'Activate Path';

// Form sections.
$string['heading_basic'] = 'Path Details';
$string['heading_org'] = 'Organisation';
$string['heading_status'] = 'Status';

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
