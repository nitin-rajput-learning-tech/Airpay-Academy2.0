<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Talent Mobility';

// Capabilities.
$string['sentientia_talent:viewopportunities'] = 'View the internal opportunity board';
$string['sentientia_talent:registerinterest'] = 'Register interest in an internal opportunity';
$string['sentientia_talent:viewcareerpath'] = 'View career paths for your role';
$string['sentientia_talent:viewsuccession'] = 'View succession plans (HR / managers)';
$string['sentientia_talent:managesuccession'] = 'Manage succession nominations (HR / managers)';
$string['sentientia_talent:managecareerpaths'] = 'Define and edit career paths';
$string['sentientia_talent:manageopportunities'] = 'Post and manage internal opportunities';
$string['sentientia_talent:audit'] = 'View the talent audit log';

// Navigation.
$string['nav_console'] = 'Talent mobility';
$string['nav_opportunities'] = 'Internal opportunities';

// Page headings.
$string['heading_console'] = 'Talent mobility console';
$string['heading_opportunities'] = 'Internal opportunities';
$string['heading_paths'] = 'Career paths';
$string['heading_succession'] = 'Succession plans';
$string['heading_mypath'] = 'Your career path';

// KPI cards.
$string['kpi_paths'] = 'Career paths';
$string['kpi_successions'] = 'Succession nominations';
$string['kpi_opportunities'] = 'Open opportunities';

// Table columns.
$string['col_path'] = 'Path';
$string['col_from'] = 'From role';
$string['col_to'] = 'To role';
$string['col_status'] = 'Status';
$string['col_role'] = 'Role';
$string['col_candidate'] = 'Candidate';
$string['col_incumbent'] = 'Incumbent';
$string['col_readiness'] = 'Readiness';
$string['col_match'] = 'Skill match';
$string['col_title'] = 'Title';

// Status / readiness labels.
$string['status_active'] = 'Active';
$string['status_archived'] = 'Archived';
$string['readiness_ready_now'] = 'Ready now';
$string['readiness_ready_1y'] = 'Ready in 1 year';
$string['readiness_ready_2y'] = 'Ready in 2 years';
$string['readiness_developing'] = 'Developing';

// Opportunity board.
$string['mypath_intro'] = 'Based on your current role ({$a}), these progressions are open to you:';
$string['match_label'] = 'match';
$string['match_help'] = 'How closely your current skills match the skills required for this role.';
$string['interest_message'] = 'Add a short note (optional)';
$string['btn_register'] = 'Register interest';
$string['btn_withdraw'] = 'Withdraw interest';
$string['interest_registered_badge'] = 'Interest registered';
$string['interest_saved'] = 'Your interest has been updated.';

// Empty states.
$string['empty_paths'] = 'No career paths have been defined for your tenant yet.';
$string['empty_succession'] = 'No succession nominations have been recorded yet.';
$string['empty_opportunities'] = 'There are no open internal opportunities right now.';

// Skills-source indicator.
$string['skillsource_active'] = 'Skill matching is powered by: {$a}';
$string['skillsource_skillsai'] = 'AI skills taxonomy (Sentientia SkillsAI)';
$string['skillsource_manual'] = 'Manual skills matrix (Sentientia Skills)';

// Settings page.
$string['settings_pagetitle'] = 'Sentientia Talent Mobility';
$string['settings_section_general'] = 'Talent mobility';
$string['settings_section_general_desc'] = 'The talent mobility suite is governed by the platform feature-flag Switchboard. This page summarises the current state.';
$string['setting_skillsource'] = 'Active skills taxonomy';
$string['setting_skillsource_desc'] = 'Skill matching for opportunities and succession candidates currently uses: {$a}. When the AI skills plugin is installed and enabled it is used automatically; otherwise the manual skills matrix is used.';
$string['setting_switchboard'] = 'Feature flags';
$string['setting_switchboard_desc'] = 'Enable or disable the talent mobility suite per tenant in the platform Switchboard.';
$string['setting_switchboard_btn'] = 'Open the Switchboard';

// Errors.
$string['error_featuredisabled'] = 'The talent mobility feature is not enabled for your organisation.';
$string['error_missingfields'] = 'Please fill in all required fields.';
$string['error_invalidreadiness'] = 'Invalid readiness value.';
$string['error_invalidstatus'] = 'Invalid opportunity status.';
$string['error_duplicatenomination'] = 'This person is already nominated as a successor for this role.';
$string['error_opportunityclosed'] = 'This opportunity is no longer open for interest.';

// Privacy metadata.
$string['privacy:metadata:succ'] = 'Succession nominations linking candidates to key roles.';
$string['privacy:metadata:succ:designation'] = 'The role being succession-planned.';
$string['privacy:metadata:succ:candidateid'] = 'The user nominated as a successor.';
$string['privacy:metadata:succ:incumbentid'] = 'The user currently holding the role.';
$string['privacy:metadata:succ:readiness'] = 'How ready the candidate is to take the role.';
$string['privacy:metadata:succ:notes'] = 'HR notes about the nomination.';
$string['privacy:metadata:succ:timecreated'] = 'When the nomination was created.';
$string['privacy:metadata:int'] = 'Expressions of interest in internal opportunities.';
$string['privacy:metadata:int:opportunityid'] = 'The opportunity the interest relates to.';
$string['privacy:metadata:int:userid'] = 'The user who expressed interest.';
$string['privacy:metadata:int:message'] = 'An optional note from the applicant.';
$string['privacy:metadata:int:matchpct'] = 'The skill-match percentage at the time of interest.';
$string['privacy:metadata:int:timecreated'] = 'When the interest was registered.';
$string['privacy:metadata:opp'] = 'Internal opportunity postings.';
$string['privacy:metadata:opp:title'] = 'The opportunity title.';
$string['privacy:metadata:opp:postedby'] = 'The user who posted the opportunity.';
$string['privacy:metadata:opp:timecreated'] = 'When the opportunity was posted.';
$string['privacy:metadata:audit'] = 'Audit log of HR-sensitive talent actions.';
$string['privacy:metadata:audit:action'] = 'The action that was performed.';
$string['privacy:metadata:audit:targetuserid'] = 'The subject of the action, if any.';
$string['privacy:metadata:audit:changedby'] = 'The user who performed the action.';
$string['privacy:metadata:audit:timecreated'] = 'When the action occurred.';
