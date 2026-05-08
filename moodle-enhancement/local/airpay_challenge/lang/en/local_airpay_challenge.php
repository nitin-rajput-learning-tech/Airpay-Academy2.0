<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Gamification Challenges';

// Capabilities.
$string['airpay_challenge:view']        = 'View challenges and leaderboards';
$string['airpay_challenge:participate'] = 'Join and leave challenges';
$string['airpay_challenge:manage']      = 'Create, edit, and delete challenges';
$string['airpay_challenge:viewall']     = 'View leaderboards across all tenants';

// Page titles.
$string['heading_index']       = 'Challenges';
$string['heading_view']        = 'Challenge: {$a}';
$string['heading_leaderboard'] = 'Leaderboard';

// Navigation.
$string['nav_my_challenges']   = 'My challenges';
$string['nav_browse']          = 'Browse';
$string['nav_leaderboard']     = 'Leaderboard';
$string['nav_admin']           = 'Manage challenges';

// Index/admin filters.
$string['filter_status']           = 'Status';
$string['filter_status_all']       = 'All';
$string['filter_status_draft']     = 'Draft';
$string['filter_status_active']    = 'Active';
$string['filter_status_archived']  = 'Archived';
$string['filter_search']           = 'Search';
$string['filter_search_placeholder'] = 'Challenge name';
$string['filter_my']               = 'My participation';
$string['filter_my_all']           = 'All challenges';
$string['filter_my_joined']        = 'Joined';
$string['filter_my_completed']     = 'Completed';
$string['filter_my_available']     = 'Available to join';

// Status labels.
$string['status_draft']    = 'Draft';
$string['status_active']   = 'Active';
$string['status_archived'] = 'Archived';

// Attempt status labels.
$string['attempt_enrolled']    = 'Enrolled';
$string['attempt_in_progress'] = 'In progress';
$string['attempt_completed']   = 'Completed';
$string['attempt_failed']      = 'Failed';
$string['attempt_expired']     = 'Expired';

// Type labels.
$string['type_course_completion'] = 'Course completion';
$string['type_streak']            = 'Login streak (Phase 2)';
$string['type_quiz_score']        = 'Quiz score (Phase 2)';
$string['type_custom']            = 'Custom';

// Index/table columns.
$string['col_name']         = 'Name';
$string['col_type']         = 'Type';
$string['col_target']       = 'Target';
$string['col_points']       = 'Points';
$string['col_status']       = 'Status';
$string['col_participants'] = 'Participants';
$string['col_dates']        = 'Dates';
$string['col_actions']      = 'Actions';
$string['col_progress']     = 'My progress';

// Form labels.
$string['form_name']         = 'Challenge name';
$string['form_shortname']    = 'Short name (slug)';
$string['form_shortname_help'] = 'Internal identifier. Used in URLs. Letters, numbers, hyphens only.';
$string['form_description']  = 'Description';
$string['form_type']         = 'Challenge type';
$string['form_targetcount']  = 'Target count';
$string['form_targetcount_help'] = 'How many qualifying course completions are needed to win this challenge.';
$string['form_courseids']    = 'Qualifying courses';
$string['form_courseids_help'] = 'Leave empty to count any course. Otherwise, only completions of these courses count.';
$string['form_pointsreward'] = 'Points reward';
$string['form_status']       = 'Status';
$string['form_startdate']    = 'Starts';
$string['form_enddate']      = 'Ends';

// Buttons.
$string['btn_create']        = 'New challenge';
$string['btn_edit']          = 'Edit';
$string['btn_delete']        = 'Delete';
$string['btn_view']          = 'View';
$string['btn_join']          = 'Join challenge';
$string['btn_leave']         = 'Leave challenge';
$string['btn_publish']       = 'Publish (Active)';
$string['btn_archive']       = 'Archive';
$string['btn_leaderboard']   = 'Leaderboard';

// Tabs.
$string['tab_overview']     = 'Overview';
$string['tab_participants'] = 'Participants';
$string['tab_leaderboard']  = 'Leaderboard';

// Overview metrics.
$string['ov_participants']    = 'Participants';
$string['ov_completed']       = 'Completed';
$string['ov_completion_pct']  = 'Completion rate';
$string['ov_avg_progress']    = 'Avg. progress';
$string['ov_my_progress']     = 'My progress';
$string['ov_my_status']       = 'My status';
$string['ov_my_points']       = 'My points';
$string['ov_top_participant'] = 'Top participant';

// Leaderboard.
$string['lb_col_rank']      = 'Rank';
$string['lb_col_user']      = 'User';
$string['lb_col_points']    = 'Points';
$string['lb_col_completed'] = 'Completed';
$string['lb_no_entries']    = 'No leaderboard entries yet. Join a challenge to start earning points.';
$string['lb_filter_challenge']           = 'Challenge';
$string['lb_filter_challenge_aggregate'] = 'All challenges (aggregate)';
$string['lb_filter_tenant']      = 'Tenant';
$string['lb_filter_tenant_mine'] = 'My tenant only';
$string['lb_filter_tenant_all']  = 'All tenants (cross-tenant)';

// Notifications.
$string['challenge_created']   = 'Challenge "{$a}" created.';
$string['challenge_updated']   = 'Challenge "{$a}" updated.';
$string['challenge_deleted']   = 'Challenge "{$a}" deleted.';
$string['joined_challenge']    = 'You joined this challenge.';
$string['left_challenge']      = 'You left this challenge.';
$string['challenge_completed'] = 'You completed this challenge!';

// Errors.
$string['err_challenge_not_found']  = 'Challenge not found.';
$string['err_challenge_not_active'] = 'This challenge is not currently active.';
$string['err_already_joined']       = 'You already joined this challenge.';
$string['err_not_joined']           = 'You are not joined to this challenge.';
$string['err_already_completed']    = 'You already completed this challenge.';
$string['err_invalid_type']         = 'Invalid challenge type.';
$string['err_invalid_status']       = 'Invalid status.';
$string['err_targetcount_min']      = 'Target count must be at least 1.';
$string['err_pointsreward_min']     = 'Points reward must be 0 or higher.';
$string['err_filterstoolong']       = 'Filter blob exceeds limit.';
$string['err_shortname_taken']      = 'Short name "{$a}" is already in use.';
$string['err_outside_cohort']       = 'This challenge is restricted to a cohort you do not belong to.';

// Empty states.
$string['empty_no_challenges'] = 'No challenges yet. Click "New challenge" to create one.';
$string['empty_no_attempts']   = 'No participants yet.';

// Misc.
$string['target_x_completions']  = '{$a} course completions';
$string['rank_position']         = '#{$a}';
$string['points_x']              = '{$a} pts';
$string['attempts_x_completed']  = '{$a} completed';

// Scheduled task.
$string['task_recompute_leaderboard'] = 'Recompute Airpay challenge leaderboards';

// Privacy provider strings.
$string['privacy:metadata:challenges']             = 'Challenge definitions created by admin users (gamification).';
$string['privacy:metadata:challenges:createdby']   = 'User ID of the admin who created the challenge.';
$string['privacy:metadata:challenges:name']        = 'Display name of the challenge.';
$string['privacy:metadata:challenges:open_path']   = 'BizLMS tenant path of the creator at challenge-creation time.';
$string['privacy:metadata:challenges:timecreated'] = 'When the challenge was created.';

$string['privacy:metadata:attempts']             = 'Per-user enrolment + progress on a challenge.';
$string['privacy:metadata:attempts:challengeid'] = 'The challenge the user joined.';
$string['privacy:metadata:attempts:userid']      = 'The participant user ID.';
$string['privacy:metadata:attempts:status']      = 'Current state (enrolled, in_progress, completed, failed, expired).';
$string['privacy:metadata:attempts:progress']    = 'Number of qualifying actions completed toward the target.';
$string['privacy:metadata:attempts:pointsawarded'] = 'Points awarded if completed.';
$string['privacy:metadata:attempts:timecreated'] = 'When the user joined the challenge.';

$string['privacy:metadata:leaderboard']             = 'Pre-computed leaderboard rankings.';
$string['privacy:metadata:leaderboard:challengeid'] = 'The challenge being ranked (0 = aggregate).';
$string['privacy:metadata:leaderboard:userid']      = 'The user ranked.';
$string['privacy:metadata:leaderboard:points']      = 'Points score driving the ranking.';
$string['privacy:metadata:leaderboard:userrank']    = '1-based rank position.';
