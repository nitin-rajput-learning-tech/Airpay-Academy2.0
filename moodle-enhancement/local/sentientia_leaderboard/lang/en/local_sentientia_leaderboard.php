<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Real-time Leaderboards';

// ── Capabilities ──────────────────────────────────────────────
$string['sentientia_leaderboard:view']         = 'View leaderboards inside my tenant';
$string['sentientia_leaderboard:manageboard']  = 'Create, edit, and delete leaderboards inside my tenant';
$string['sentientia_leaderboard:promoteboard'] = 'Promote a leaderboard to customer-wide visibility';
$string['sentientia_leaderboard:viewall']      = 'View leaderboards across tenants (HR analytics)';

// ── Board types ────────────────────────────────────────────────
$string['type_quiz']       = 'Quiz top scorers';
$string['type_completion'] = 'Fastest to complete';
$string['type_skill']      = 'Skill points earned';

$string['type_quiz_desc']       = 'Rank learners by best score on a single quiz. Ties break on time taken.';
$string['type_completion_desc'] = 'Rank learners by how fast they completed a course. Shorter = better.';
$string['type_skill_desc']      = 'Rank learners by skill points earned within a date range.';

// ── Scopes ─────────────────────────────────────────────────────
$string['scope_course']   = 'Course';
$string['scope_tenant']   = 'Tenant';
$string['scope_customer'] = 'Customer-wide';

// ── Statuses ───────────────────────────────────────────────────
$string['status_active']   = 'Active';
$string['status_disabled'] = 'Disabled';
$string['status_archived'] = 'Archived';

// ── Columns ────────────────────────────────────────────────────
$string['col_rank']     = 'Rank';
$string['col_user']     = 'Learner';
$string['col_points']   = 'Points';
$string['col_score']    = 'Score';
$string['col_time']     = 'Time taken';
$string['col_progress'] = 'Progress';
$string['col_skills']   = 'Skills levelled';

// ── Headings + labels ──────────────────────────────────────────
$string['heading_index']         = 'Leaderboards';
$string['heading_create']        = 'Create leaderboard';
$string['heading_edit']          = 'Edit leaderboard';
$string['heading_view']          = 'Leaderboard';
$string['label_name']            = 'Board name';
$string['label_type']            = 'Board type';
$string['label_scope']           = 'Scope';
$string['label_course']          = 'Course';
$string['label_quiz']            = 'Quiz';
$string['label_skills']          = 'Skills (comma-separated IDs; leave blank for all)';
$string['label_window_start']    = 'Scoring window start';
$string['label_window_end']      = 'Scoring window end';
$string['label_recompute']       = 'Recompute interval (seconds)';
$string['label_top_n']           = 'Show top N';
$string['label_show_my_rank']    = 'Show viewer their own rank';
$string['label_optout']          = 'Hide me from public leaderboards';
$string['label_optout_desc']     = 'When checked, your name is hidden from every public leaderboard. You still earn points, but other learners cannot see your ranking.';

// ── Actions ────────────────────────────────────────────────────
$string['action_create']     = 'Create board';
$string['action_save']       = 'Save changes';
$string['action_delete']     = 'Delete';
$string['action_view']       = 'View';
$string['action_recompute']  = 'Recompute now';

// ── Misc + UI ─────────────────────────────────────────────────
$string['anonymous']         = 'Anonymous learner';
$string['you']               = 'You';
$string['your_rank']         = 'Your rank: {$a}';
$string['no_rank_optout']    = 'You are opted out of public leaderboards.';
$string['no_rank_no_entry']  = 'You do not yet have a ranking on this board.';
$string['no_entries']        = 'No rankings yet — check back after the next recompute.';
$string['last_recomputed_at'] = 'Last updated: {$a}';
$string['live_indicator']    = 'Live — updating in real time';
$string['polling_fallback']  = 'Updates every 30s';
$string['feature_disabled']  = 'Leaderboards are not enabled. Ask an admin to switch on sentientia.leaderboards.enabled.';
$string['type_disabled']     = 'This board type is not enabled.';

// ── Block ──────────────────────────────────────────────────────
$string['block_title']       = 'Leaderboard';
$string['block_choose']      = 'Choose a leaderboard';
$string['block_none']        = 'No leaderboards available in your tenant.';

// ── Errors ─────────────────────────────────────────────────────
$string['error_invalidtype']     = 'Invalid board type. Must be one of: quiz, completion, skill.';
$string['error_invalidscope']    = 'Invalid scope. Must be one of: course, tenant, customer.';
$string['error_invalidwindow']   = 'Scoring window end must be after window start.';
$string['error_invalidrecompute'] = 'Recompute interval must be at least 30 seconds.';
$string['error_typenotenabled']  = 'This board type is not enabled by the admin.';
$string['error_quiznotscoped']   = 'Quiz boards must be scoped to a specific quiz (quizid > 0).';
$string['error_completionnotscoped'] = 'Completion boards must be scoped to a specific course (courseid > 0).';
$string['error_noboard']         = 'Leaderboard not found.';
$string['error_outoftenant']     = 'You cannot view a leaderboard from another tenant.';
$string['error_cantpromote']     = 'You do not have permission to make a board customer-wide.';
$string['error_invalidpayload']  = 'Invalid payload data for event journal.';
$string['invalid_event_type']    = 'Unknown event type: {$a}';

// ── Tasks ──────────────────────────────────────────────────────
$string['task_recompute_due_boards'] = 'Recompute due leaderboards (Sentientia)';
$string['task_purge_old_events']     = 'Purge old leaderboard SSE events (Sentientia)';

// ── Phase L.1: events + messages ───────────────────────────────
$string['event_rankings_updated']     = 'Leaderboard rankings updated';
$string['messageprovider:rank_change'] = 'Leaderboard rank change';

// Top-10 entry — celebration. {$a->boardname}, {$a->new_rank}.
$string['msg_top10_subject'] = 'You cracked the top {$a->new_rank} on {$a->boardname}!';
$string['msg_top10_body']    = 'Great work — you just entered the top 10 on "{$a->boardname}" at rank #{$a->new_rank}. Keep going to climb even higher.';

// Moved up — {$a->boardname}, {$a->old_rank}, {$a->new_rank}, {$a->delta}.
$string['msg_moveup_subject'] = 'You moved up {$a->delta} places on {$a->boardname}';
$string['msg_moveup_body']    = 'You climbed from rank #{$a->old_rank} to #{$a->new_rank} on "{$a->boardname}" — a jump of {$a->delta} positions. Nice run.';

// Moved down — same placeholders.
$string['msg_movedown_subject'] = 'You dropped {$a->delta} places on {$a->boardname}';
$string['msg_movedown_body']    = 'Your rank on "{$a->boardname}" slipped from #{$a->old_rank} to #{$a->new_rank} (down {$a->delta} positions). Want to claw it back? Open the board and keep learning.';

// ── User preference (opt-out) ─────────────────────────────────
$string['preference_optout'] = 'Hide me from public leaderboards';

// ── Privacy ────────────────────────────────────────────────────
$string['privacy:metadata:lb_entries']                = 'Cached leaderboard rankings for a user';
$string['privacy:metadata:lb_entries:userid']         = 'The user being ranked';
$string['privacy:metadata:lb_entries:boardid']        = 'The leaderboard the ranking belongs to';
$string['privacy:metadata:lb_entries:points']         = 'Points earned in the board';
$string['privacy:metadata:lb_entries:userrank']       = 'The user\'s rank';
$string['privacy:metadata:lb_entries:last_recomputed'] = 'When this row was last computed';

$string['privacy:metadata:lb_optouts']                = 'Per-user opt-out from being publicly listed';
$string['privacy:metadata:lb_optouts:userid']         = 'The user who opted out';
$string['privacy:metadata:lb_optouts:customerid']     = 'The customer scope of the opt-out';
$string['privacy:metadata:lb_optouts:timeoptedout']   = 'When the user opted out';
