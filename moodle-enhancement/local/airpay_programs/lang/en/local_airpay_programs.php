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
