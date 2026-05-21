<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Live engagement';

// ── Phase E.0 — scaffold strings (no UI yet) ─────────────────────────

// Privacy metadata.
$string['privacy:metadata:sessions'] = 'A live session — one row per Mentimeter-style poll/quiz event a trainer runs. Records the owner (trainer userid), session code, start/end timestamps, and tenant/customer context.';
$string['privacy:metadata:sessions:ownerid']    = 'The trainer who created and runs the session.';
$string['privacy:metadata:sessions:code']       = 'Numeric join code (6 digits) audience members use to join.';
$string['privacy:metadata:sessions:tenantid']   = 'BizLMS tenant the session belongs to.';
$string['privacy:metadata:sessions:customerid'] = 'Sentientia customer scope.';
$string['privacy:metadata:sessions:timecreated']  = 'When the session was created.';
$string['privacy:metadata:sessions:timestarted']  = 'When the session went live (or 0 if never started).';
$string['privacy:metadata:sessions:timeended']    = 'When the session ended (or 0 if still running).';

$string['privacy:metadata:slides'] = 'Slides (questions) within a live session. One row per slide.';
$string['privacy:metadata:slides:title']       = 'Slide title shown to audience.';
$string['privacy:metadata:slides:type']        = 'Slide type — multichoice / wordcloud / openended / rating / quiz / ranking.';

$string['privacy:metadata:responses'] = 'Individual audience responses to a slide. Anonymous if userid is null.';
$string['privacy:metadata:responses:userid']      = 'Responder user ID — nullable for anonymous sessions.';
$string['privacy:metadata:responses:value_text']  = 'Free-text response (for wordcloud / openended slides).';
$string['privacy:metadata:responses:value_int']   = 'Numeric response (for multichoice / rating / quiz slides).';
$string['privacy:metadata:responses:timecreated'] = 'When the response was submitted.';

$string['privacy:metadata:participants'] = 'Audience participants in a live session. Tracks presence (last_seen) and display name.';
$string['privacy:metadata:participants:userid']        = 'Participant user ID — nullable for anonymous joins.';
$string['privacy:metadata:participants:display_name']  = 'Display name shown in the audience list / leaderboard.';
$string['privacy:metadata:participants:timejoined']    = 'When the user joined the session.';
$string['privacy:metadata:participants:timelastseen']  = 'Last SSE heartbeat from this participant.';

$string['privacy:metadata:events'] = 'Internal event journal — slide changes, response counts, session lifecycle. Polled by the SSE stream endpoint. Purged 24h after session ends.';
$string['privacy:metadata:events:payload']       = 'JSON payload describing the event (slide_id, response_count, etc.).';
$string['privacy:metadata:events:timecreated']   = 'When the event was generated.';

// Capability descriptions.
$string['sentientia_live:create']  = 'Create a new live session as the trainer';
$string['sentientia_live:run']     = 'Run (start/advance/end) a live session you created';
$string['sentientia_live:join']    = 'Join an existing live session by code';
$string['sentientia_live:respond'] = 'Submit a response to a live slide';
$string['sentientia_live:manage_all'] = 'Admin: view and manage every live session across tenants';

// Errors.
$string['errorfeatureoff'] = 'Sentientia LMS Live engagement is currently disabled. Ask your administrator to enable the live.enabled feature flag.';
$string['invalidsession']            = 'Live session does not exist.';
$string['invalidslidetype']          = 'Invalid slide type: {$a}';
$string['invalidtitle']              = 'Title is required and must be 200 characters or fewer.';
$string['displayname_required']      = 'A display name is required to join the session.';
$string['code_generation_failed']    = 'Unable to allocate a unique join code. Try again in a moment.';
$string['invalid_event_type']        = 'Unknown event type: {$a}';
$string['mc_options_count']          = 'Multiple-choice / quiz slides need 2-20 options (you provided {$a}).';
$string['mc_option_type']            = 'Each option must be a string.';
$string['mc_option_length']          = 'Each option must be 1-200 characters.';
$string['quiz_correct_out_of_range'] = 'The correct-answer index is outside the options list.';
$string['rating_scale_invalid']      = 'Rating scale must have 0 ≤ min < max ≤ 10.';
$string['ranking_items_count']       = 'Ranking slides need 2-20 items (you provided {$a}).';
$string['ranking_item_type']         = 'Each ranking item must be a string.';
$string['ranking_item_length']       = 'Each ranking item must be 1-200 characters.';

// ── Phase E.1.f — trainer dashboard strings ──
$string['trainer_dashboard_pagetitle']  = 'Live sessions — trainer dashboard';
$string['trainer_dashboard_heading']    = 'Your live sessions';
$string['trainer_dashboard_subhead']    = 'Create, manage and run real-time polls, quizzes and word clouds with your audience.';
$string['trainer_create_button']        = 'Create new session';
$string['trainer_no_sessions_heading']  = 'No live sessions yet';
$string['trainer_no_sessions_body']     = 'Create your first session to gather real-time feedback from your audience.';
$string['state_draft']                  = 'Draft';
$string['state_live']                   = 'Live';
$string['state_ended']                  = 'Ended';
$string['live_label']                   = 'live';
$string['col_title']                    = 'Title';
$string['col_state']                    = 'State';
$string['col_code']                     = 'Join code';
$string['col_slides']                   = 'Slides';
$string['col_audience']                 = 'Audience';
$string['col_created']                  = 'Created';
$string['col_actions']                  = 'Actions';
$string['action_edit']                  = 'Edit';
$string['action_run']                   = 'Run';
$string['action_end']                   = 'End';
$string['action_view']                  = 'View';
$string['confirm_end_session']          = 'End this live session? Audience disconnects and results are frozen.';
$string['confirm_delete_session']       = 'Delete this session permanently? Slides, audience records and responses will all be removed.';
$string['dashboard_session_count']      = '{$a} sessions total.';

// ── Phase E.1.g — create-session strings ──
$string['create_session_pagetitle']   = 'Create live session';
$string['create_session_heading']     = 'New live session';
$string['create_session_intro']       = 'Give your session a title and adjust the audience settings. You will be able to add slides next.';
$string['session_created_notice']     = 'Session created. Add your first slide below.';

// ── Phase E.1.g — session_form labels + help ──
$string['form_title_label']           = 'Session title';
$string['form_title_required']        = 'Please provide a session title.';
$string['form_title_too_long']        = 'Session title must be 200 characters or fewer.';
$string['form_title']                 = 'Session title';
$string['form_title_help']            = 'A short name for this session — shown on the trainer dashboard and in audience-facing screens. Examples: "Q3 KYC refresher", "All-hands kickoff Sep 2026".';

$string['form_settings_heading']      = 'Audience settings';

$string['form_allow_anonymous_label'] = 'Allow anonymous audience';
$string['form_allow_anonymous_desc']  = 'When ticked, audience members can join without logging in by entering a display name.';
$string['form_allow_anonymous']       = 'Allow anonymous audience';
$string['form_allow_anonymous_help']  = 'Default OFF for enterprise deployments — most organisations want responses correlated with learner IDs. Tick this only if you are running a workshop where attendee anonymity is the point.';

$string['form_show_results_label']    = 'Show results to audience';
$string['form_show_results_desc']     = 'When ticked, the audience sees the running tally (bar chart / word cloud / leaderboard) update in real time after they respond.';

$string['form_allow_late_join_label'] = 'Allow late join';
$string['form_allow_late_join_desc']  = 'When ticked, audience members can join the session after slides have already been answered. They see the current slide; past slides are skipped for them.';

$string['form_max_concurrent_label']  = 'Maximum concurrent audience';
$string['form_max_concurrent']        = 'Maximum concurrent audience';
$string['form_max_concurrent_help']   = 'Hard cap on how many audience members can be connected simultaneously. Default 500 — chosen to protect server resources. Sessions above 500 attendees need infrastructure review (see ADR-004).';
$string['form_max_concurrent_range']  = 'Maximum concurrent audience must be between 1 and 500.';

$string['form_create_submit']         = 'Create session';

// ── Phase E.1.i — edit / end / delete handler strings ──
$string['edit_session_pagetitle']     = 'Edit live session';
$string['cannot_edit_session']        = 'You do not have permission to edit this session.';
$string['cannot_edit_live_session']   = 'A live session cannot be edited. End it first to make changes.';
$string['cannot_run_session']         = 'You do not have permission to run this session.';
$string['cannot_delete_session']      = 'You do not have permission to delete this session.';
$string['session_updated_notice']     = 'Session updated.';
$string['session_ended_notice']       = 'Session ended. Audience disconnected; results frozen.';
$string['session_not_live_error']     = 'Cannot end this session — it is not currently live.';
$string['session_deleted_notice']     = 'Session deleted permanently.';
$string['delete_session_pagetitle']   = 'Delete live session';
$string['delete_session_heading']     = 'Delete live session?';
$string['delete_session_confirm_html'] = 'You are about to permanently delete <strong>{$a->title}</strong>. This will remove <strong>{$a->slide_count}</strong> slide(s) and <strong>{$a->participant_count}</strong> audience record(s) with all their responses.<br><br>This cannot be undone.';
$string['state_label']                = 'State';
$string['code_label']                 = 'Join code';
$string['action_start_session']       = 'Start session';
$string['add_slide_to_start']         = 'Add at least one slide before starting the session.';
$string['slides_heading']             = 'Slides';
$string['no_slides_yet']              = 'No slides added yet.';
$string['slide_editor_pending_title']  = 'Slide editor — coming in Phase E.1.j';
$string['slide_editor_pending_body']   = 'Adding and editing slides is being built next. Until then, the session frame is in place: you can rename it, adjust audience settings, and (once slides exist) start the session.';
$string['settings_heading_inline']    = 'Audience settings';

// ── Phase E.1.i — start/run page strings ──
$string['session_started_notice']     = 'Session is now live. Audience can join using the code below.';
$string['session_not_startable_error']= 'Could not start the session — it may not be in draft state.';
$string['session_not_live_for_run']   = 'This session is not live. Start it first.';
$string['run_session_pagetitle']      = 'Run live session';
$string['audience_join_at']           = 'Audience joins at';
$string['audience_join_url_hint']     = 'Send your audience to {$a} and they enter the code above.';
$string['audience_count_label']       = 'Audience';
$string['audience_online']            = 'online now';
$string['total_slides_label']         = '{$a} slides in deck';
$string['current_slide_heading']      = 'Current slide';
$string['slide_position_of']          = 'Slide {$a->pos} of {$a->total}';
$string['no_current_slide']           = 'No slide selected yet. Use the slide editor to pick which slide to show first.';
$string['live_runner_pending_title']  = 'Live runner — real-time projector coming in Phase E.3';
$string['live_runner_pending_body']   = 'The current placeholder shows session info and basic state. The Phase E.3 ship adds the SSE-driven projector view (auto-updating audience count, live response chart, advance/back buttons, full-screen mode).';
$string['action_end_session']         = 'End this session';
