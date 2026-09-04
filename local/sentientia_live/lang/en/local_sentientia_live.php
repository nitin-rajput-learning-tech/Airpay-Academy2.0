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
$string['trainer_sessions_table_caption'] = 'List of your live sessions with state, join code, slide count, audience size, creation date, and available actions.';

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
$string['slide_editor_pending_title']  = 'Slide editor — coming soon';
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
$string['live_runner_pending_title']  = 'Live runner — real-time projector coming soon';
$string['live_runner_pending_body']   = 'The current placeholder shows session info and basic state. A future update will add the real-time projector view with auto-updating audience count, live response chart, advance/back buttons, and full-screen mode.';
$string['action_end_session']         = 'End this session';
$string['response_count_label']       = 'Responses received';

// ── Phase E.4 — result panel strings ───────────────────────────────
$string['live_results_heading']        = 'Live results';
$string['live_results_total_suffix']   = 'responses';
$string['live_results_empty']          = 'No responses yet — share the code and wait for your audience.';
$string['live_results_correct_label']  = 'Correct';
$string['live_results_avg_label']      = 'Average';
$string['live_results_responses_label']= 'Responses';
$string['live_results_scale_label']    = 'Scale';
$string['live_results_rank_label']     = 'Rank';
$string['live_results_item_label']     = 'Item';
$string['live_results_avg_pos_label']  = 'Avg position';

// ── Phase E.6 — quiz correct-answer summary + leaderboard ─────────
$string['quiz_summary_label']            = 'Quiz result:';
$string['quiz_summary_of']               = 'of';
$string['quiz_summary_got_it_right']     = 'got it right';
$string['quiz_summary_correct_was']      = 'Correct answer:';
$string['quiz_leaderboard_label']        = 'Leaderboard';
$string['quiz_leaderboard_rank_col']     = 'Rank';
$string['quiz_leaderboard_name_col']     = 'Name';
$string['quiz_leaderboard_time_col']     = 'Time';
$string['quiz_leaderboard_seconds_suffix']= 's';
$string['quiz_leaderboard_empty']        = 'No correct answers yet.';

// ── Phase E.7 — Session analytics CSV export ──────────────────────
$string['action_export_csv']             = 'Export';
$string['action_export_csv_title']       = 'Download all responses for this session as CSV';
$string['export_session_label']          = 'Export session';
$string['export_format_unsupported']     = 'Unsupported export format: {$a}';
$string['export_open_failed']            = 'Could not open output stream for export.';

// ── Phase E.1.j — slide editor strings ─────────────────────────────

$string['invalidslide']                = 'Slide does not exist.';
$string['back_to_session']             = 'Back to session';

// Type picker (add_slide.php step 1).
$string['add_slide_pagetitle']          = 'Add a slide';
$string['add_slide_pick_type_heading']  = 'Pick a question type';
$string['add_slide_pick_type_intro']    = 'Choose how your audience will respond. You can add more slides of any type later.';
$string['no_slide_types_enabled']       = 'No question types are enabled on this server. Ask your administrator to enable at least one via the Switchboard.';
$string['use_this_type']                = 'Use this type';

// Per-type display name + short description.
$string['slide_type_multichoice']       = 'Multiple choice';
$string['slide_type_multichoice_desc']  = 'Audience picks one of the options you provide. Results render as a bar chart.';
$string['slide_type_quiz']              = 'Quiz';
$string['slide_type_quiz_desc']         = 'Like multiple choice, but with a correct answer. Audience sees right / wrong instantly and a live leaderboard.';
$string['slide_type_rating']            = 'Rating scale';
$string['slide_type_rating_desc']       = '1-5 (or 0-10 NPS) scale. Results render as an average + distribution histogram.';
$string['slide_type_ranking']           = 'Ranking';
$string['slide_type_ranking_desc']      = 'Audience drags a list of items into their preferred order. Results show aggregate ranking.';
$string['slide_type_wordcloud']         = 'Word cloud';
$string['slide_type_wordcloud_desc']    = 'Audience submits one word. Common answers grow bigger in the cloud.';
$string['slide_type_openended']         = 'Open-ended';
$string['slide_type_openended_desc']    = 'Free-text response. Answers scroll across the screen as audience submits.';

// Add-slide form (step 2) + edit-slide.
$string['add_slide_form_pagetitle']     = 'Add slide';
$string['add_slide_form_heading']       = 'Add slide: {$a}';
$string['edit_slide_pagetitle']         = 'Edit slide';
$string['edit_slide_heading']           = 'Edit slide: {$a}';
$string['slide_added_notice']           = 'Slide added.';
$string['slide_updated_notice']         = 'Slide updated.';
$string['slide_deleted_notice']         = 'Slide deleted.';

// Slide form labels.
$string['slide_title_label']            = 'Question text';
$string['slide_title_required']         = 'Question text is required.';
$string['slide_type_label']             = 'Type';
$string['slide_form_add_submit']        = 'Add slide';
$string['slide_form_update_submit']     = 'Save changes';

// Multiple choice + quiz options repeat.
$string['mc_option']                    = 'Option';
$string['mc_add_more']                  = 'Add more options';
$string['quiz_option']                  = 'Option';
$string['quiz_add_more']                = 'Add more options';
$string['quiz_correct_index_label']     = 'Correct option number';
$string['quiz_correct_index']           = 'Correct option number';
$string['quiz_correct_index_required']  = 'Specify which option (1, 2, ...) is the correct answer.';
$string['quiz_correct_index_help']      = 'The 1-based position of the correct option. So if the correct answer is the second option you typed, enter 2. Validated server-side; out-of-range values are rejected.';

// Rating scale.
$string['rating_scale_min_label']       = 'Scale minimum';
$string['rating_scale_max_label']       = 'Scale maximum';
$string['rating_scale_labels_label']    = 'Scale labels (optional, separated by | )';
$string['rating_scale_labels']          = 'Scale labels';
$string['rating_scale_labels_help']     = 'Pipe-separated labels to show at each step of the scale, in order. Example: "Strongly disagree|Disagree|Neutral|Agree|Strongly agree". Leave blank to show numbers only.';

// Ranking.
$string['ranking_item']                 = 'Item';
$string['ranking_add_more']             = 'Add more items';

// Word cloud.
$string['wc_max_word_length_label']     = 'Max word length';
$string['wc_max_word_length']           = 'Max word length';
$string['wc_max_word_length_help']      = 'Audience submissions longer than this many characters are truncated. Helps keep the cloud readable. Range 3-100.';
$string['wc_dedupe_label']              = 'De-duplicate audience submissions';
$string['wc_dedupe_desc']               = 'When ticked, a learner\'s repeated words are counted once — they can\'t inflate a single word by submitting it again. The maximum-responses cap still limits how many distinct words each learner contributes. When unticked, the same word from one learner is counted each time.';

// Open ended.
$string['openended_max_chars_label']    = 'Max characters per response';
$string['openended_max_chars']          = 'Max characters per response';
$string['openended_max_chars_help']     = 'Hard cap on response length. Default 500. Range 10-500.';

// Slide row actions on edit.php.
$string['action_add_slide']             = 'Add slide';
$string['action_move_up']               = 'Move up';
$string['action_move_down']             = 'Move down';
$string['action_delete_slide']          = 'Delete';
$string['action_show_now']              = 'Show now';
$string['badge_current_slide']          = 'Current';

// Delete-slide confirmation.
$string['delete_slide_pagetitle']       = 'Delete slide';
$string['delete_slide_heading']         = 'Delete slide?';
$string['delete_slide_confirm_html']    = 'Delete the {$a->type} slide <strong>"{$a->title}"</strong>? Any audience responses to this slide will be removed.';

// Set-current notices.
$string['slide_made_current_notice']    = 'Now showing this slide to the audience.';
$string['slide_make_current_failed']    = 'Could not set this slide as current. Make sure the session is live.';

// ── Phase E.2 — audience UI strings ────────────────────────────────

// Join page.
$string['audience_join_pagetitle']      = 'Join live session';
$string['audience_join_heading']        = 'Join a live session';
$string['audience_join_intro']          = 'Enter the 6-digit code your presenter has shared.';
$string['audience_invalid_code']        = 'No live session with that code. Double-check the digits with your presenter.';
$string['audience_code_label']          = 'Session code';
$string['audience_lookup_code']         = 'Find session';
$string['audience_session_found']       = 'Found: <strong>{$a}</strong>';
$string['audience_displayname_label']   = 'Your display name';
$string['audience_displayname_placeholder'] = 'How should we list you?';
$string['audience_join_button']         = 'Join session';
$string['audience_cannot_join']         = 'You do not have permission to join this session.';
$string['audience_anonymous_not_allowed']= 'This session does not accept anonymous joins. Please sign in first.';

// Play page guards.
$string['audience_must_join_first']     = 'Please enter the session code to join first.';
$string['audience_token_invalid']       = 'Your join token is invalid or expired. Rejoin the session.';

// Play page states.
$string['audience_waiting_heading']     = 'Waiting for the next question…';
$string['audience_waiting_body']        = 'Your presenter has not picked the first question yet. Hang on — this page refreshes automatically.';
$string['audience_waiting_next']        = 'Hold tight — the next question will appear automatically.';
$string['audience_current_slide_gone']  = 'The slide your presenter chose is no longer available.';
$string['audience_session_ended_heading'] = 'Session ended';
$string['audience_session_ended_body']    = 'Thanks for participating. Your responses have been recorded.';
$string['audience_response_saved']      = 'Response received — thanks!';
$string['audience_already_responded']   = 'You have already responded to this slide.';
$string['audience_submit_response']     = 'Submit response';
$string['audience_slide_progress']      = 'Question {$a->pos} of {$a->total}';

// Response-side placeholders.
$string['wc_response_placeholder']      = 'Type one word…';
$string['openended_response_placeholder']= 'Your answer…';
$string['ranking_response_intro']       = 'Number each item from 1 (your top choice) downward. Each number must be unique — no ties.';

// ── Phase E.x — Accessibility (P0 #8 — aria-live regions) ─────────
// Strings surfaced inside aria-live / role=region wrappers in the
// result panel, audience play page, and trainer run page. Screen
// readers announce these when the underlying region updates.
$string['a11y_results_region_label']     = 'Live results';
$string['a11y_results_bar_chart_label']  = 'Live results bar chart';
$string['a11y_response_recorded']        = 'Response recorded';
$string['a11y_session_ended_announce']   = 'Session ended. Your responses have been recorded.';
$string['a11y_audience_count_region']    = 'Live audience count';
$string['a11y_response_count_region']    = 'Live response count';
$string['a11y_current_question_region']  = 'Current question';
$string['a11y_waiting_for_question']     = 'Waiting for the next question';
$string['a11y_already_responded']        = 'You have already responded to this question';

// ── Phase E.4-E.9 scaffold — question-type registry strings ───────
// One name + one description per registered abstract_question_type
// subclass. Surfaced by question_type_registry::get_all() through
// get_display_name() / get_description() — used by the slide-type
// picker and any future "available question types" admin listing.
$string['qtype_multichoice_name']        = 'Multiple choice';
$string['qtype_multichoice_desc']        = 'Audience picks one of the options you provide. Results render as a horizontal bar chart with one bar per option, percentage and live count.';
$string['qtype_wordcloud_name']          = 'Word cloud';
$string['qtype_wordcloud_desc']          = 'Audience each submits one short word. Common answers grow larger in the tag cloud as frequency rises.';
$string['qtype_openended_name']          = 'Open-ended';
$string['qtype_openended_desc']          = 'Audience submits free-form text up to a configurable character cap. Answers stream across the projector as they arrive.';
$string['qtype_rating_name']             = 'Rating scale';
$string['qtype_rating_desc']             = '1-5 Likert or 0-10 NPS. Results render as a histogram plus an average + response-count summary.';
$string['qtype_quiz_name']               = 'Quiz';
$string['qtype_quiz_desc']               = 'Like multiple choice, but with a designated correct answer. Audience sees right/wrong instantly; trainer projector shows a live leaderboard ranked by correctness and speed.';
$string['qtype_ranking_name']            = 'Ranking';
$string['qtype_ranking_desc']            = 'Audience drags a list of items into their preferred order. Results show each item\'s aggregate average position — lower is more preferred.';

// ── Phase E.5 — Word cloud full implementation (admin + audience) ──
// Admin settings page strings.
$string['settings_pagetitle']            = 'Sentientia Live engagement';
$string['settings_wc_heading']           = 'Word-cloud defaults';
$string['settings_wc_heading_desc']      = 'Defaults applied when a trainer creates a word-cloud slide. Per-slide settings override these.';
$string['setting_default_min_word_length']      = 'Default minimum word length';
$string['setting_default_min_word_length_desc'] = 'Tokens shorter than this many characters are dropped during tokenisation. Set to 2 by default — drops single-letter noise but keeps short words like "AI" or "UX".';
$string['setting_default_max_responses']        = 'Default maximum responses per learner';
$string['setting_default_max_responses_desc']   = 'Hard cap on how many words a single audience member can contribute to one word-cloud slide. Default 3 — mirrors Mentimeter\'s standard.';

// H4 remediation (UAT-SECURITY-POSTURE-2026-09-03, 2026-09-04) — SSE stream limits.
$string['settings_sse_heading']                 = 'SSE stream limits';
$string['settings_sse_heading_desc']            = 'Bounds on the Server-Sent-Events stream (stream.php) that a volumetric flood of connections could otherwise use to exhaust the Apache worker pool, especially when anonymous audience join is enabled.';
$string['setting_sse_max_seconds']              = 'Maximum SSE stream lifetime (seconds)';
$string['setting_sse_max_seconds_desc']         = 'How long one Server-Sent-Events connection is held open before the server closes it and the browser reconnects automatically. Default 60. Lower this if Apache workers are still under pressure during a large session.';
$string['setting_sse_max_connections']          = 'Maximum concurrent SSE connections';
$string['setting_sse_max_connections_desc']     = 'Hard cap on how many Server-Sent-Events streams may be open at once across the whole site. Once reached, new connection attempts get an immediate HTTP 503 with Retry-After instead of a stream. Default 8 — size this against your Apache MaxRequestWorkers, leaving headroom for ordinary page requests. A fixed cap of 2 concurrent streams per trainer or per audience participant also applies and is not configurable here.';

// B18/F-089 stabilization (2026-05-28) — per-tenant Sentientia Live kill switch admin page.
$string['tenant_switches_title']      = 'Sentientia Live tenant switches';
$string['tenant_switches_intro']      = 'Toggle Sentientia Live features per tenant. Global flags apply to all tenants unless overridden; per-tenant rows shadow the global value for that (customer, tenant) pair. Use this to roll back Live to a single tenant if something goes wrong, or to dark-launch a new question type to one customer.';
$string['tenant_switches_empty']      = 'No Sentientia Live feature flags found yet. Run the cli/set_live_flags.php script to seed defaults, then refresh this page.';
$string['tenant_switches_add_hint']   = 'To create a new per-tenant override, run cli/set_live_flags.php with the appropriate customer_id and tenant_id arguments. A future chip will add an inline "Add override" form here.';
$string['tenant_switches_flipped']    = 'Switch updated. Feature-flag cache invalidated; the change is live for the next request.';
$string['th_flag_key']     = 'Flag key';
$string['th_customer_id']  = 'Customer';
$string['th_tenant_id']    = 'Tenant';
$string['th_enabled']      = 'State';
$string['th_modified']     = 'Last modified';
$string['th_action']       = 'Action';
$string['action_enable']   = 'Enable';
$string['action_disable']  = 'Disable';
$string['invalidflag']     = 'Unknown Sentientia Live flag.';

// Word-cloud slide-editor form fields (per-slide overrides).
$string['wc_min_word_length_label']      = 'Minimum word length';
$string['wc_max_responses_label']        = 'Max words per learner';
$string['wc_max_responses']              = 'Max words per learner';
$string['wc_max_responses_help']         = 'How many words a single audience member can contribute to this slide (1-10). Each submission adds words up to this cap. Defaults to the site-wide setting.';

// Audience response form (word cloud).
$string['wc_audience_input_label']       = 'Type a word to contribute to the cloud';
$string['wc_remaining_hint']             = '{$a->remaining} of {$a->max} words remaining';
$string['wc_max_responses_reached']      = 'You have already contributed the maximum of {$a} words.';
$string['wc_max_responses_invalid']      = 'Max responses per learner must be between {$a->min} and {$a->max}.';
$string['wc_min_word_length_invalid']    = 'Minimum word length must be between 1 and 20.';
$string['wc_min_exceeds_max']            = 'Minimum word length cannot exceed maximum word length.';
$string['wc_a11y_new_word_added']        = 'New word added to the cloud';

// response_recorder errors.
$string['response_slide_mismatch']      = 'That slide is not part of this session.';
$string['response_int_required']        = 'A numeric response is required.';
$string['response_text_required']       = 'A text response is required.';
$string['response_text_too_long']       = 'Response too long. Maximum {$a} characters.';
$string['response_out_of_range']        = 'Response value is out of the allowed range: {$a}';
$string['response_ranking_bad_json']    = 'Ranking response must be a JSON array of item indices.';
$string['response_ranking_incomplete']  = 'Please rank every item before submitting.';
$string['response_ranking_duplicate']   = 'Ranking response contains a duplicate item — each item may appear only once.';
$string['invalidparticipant']           = 'Participant record not found.';
$string['participant_session_mismatch'] = 'Participant does not belong to this session.';

// ── Phase E.4 — multiple_choice question type ─────────────────────
// Class-layer constraints (validate_config) and audience-render
// surfaces. The 2-6 cap is enforced by the class layer only;
// slide_manager::validate_settings still accepts up to 20 options
// for backwards compat with stored production rows.
$string['mc_options_must_be_array']     = 'Options must be provided as a list.';
$string['mc_options_count_2_6']         = 'Multiple choice slides need between 2 and 6 options (you provided {$a}).';
$string['mc_option_index_required']     = 'A selected option is required.';
$string['mc_render_style']              = 'Display style';
$string['mc_render_style_invalid']      = 'Render style must be either "radio" or "buttons".';
$string['mc_render_style_label']        = 'Display style';
$string['mc_render_style_radio']        = 'Radio buttons';
$string['mc_render_style_buttons']      = 'Tap-target buttons';
$string['mc_render_style_help']         = 'Radio buttons fit dense option lists; tap-target buttons are easier on mobile when each option is short.';
$string['mc_correct']                   = 'Correct answer';
$string['mc_correct_label']             = 'Correct answer (optional)';
$string['mc_correct_help']              = 'Optional. Enter the option number (1, 2, …) that is the correct answer, or leave blank for a poll with no right answer. The trainer results view marks the correct option; the audience does not see it until you reveal.';
$string['a11y_mc_tally_updated']        = 'Vote tally updated';
$string['a11y_mc_correct_revealed']     = 'Correct answer revealed';
// ── Phase E.6-E.9 D4 — Question type implementations ───────────────
// One key family per type — audience-side legends, result-panel
// headings, a11y announcements, validation errors. Hindi parity 100%.

// Shared: openended audience + result.
$string['qt_openended_audience_label']     = 'Your answer';
$string['qt_openended_char_hint']          = 'HTML is stripped — plain text only.';
$string['qt_openended_result_heading']     = 'Open-ended responses';
$string['qt_openended_result_a11y_label']  = 'Open-ended response list';
$string['qt_openended_item_aria_prefix']   = 'Response from';
$string['qt_openended_moderation_label']   = 'Show moderation controls (hide / show individual responses)';
$string['qt_openended_moderate_aria']      = 'Toggle visibility of this response';
$string['qt_openended_hide']               = 'Hide';
$string['qt_openended_show']               = 'Show';
$string['qt_openended_pagination_aria']    = 'Response pagination';
$string['qt_openended_page_prev']          = 'Previous page';
$string['qt_openended_page_next']          = 'Next page';
$string['qt_openended_page_of']            = 'Page';
$string['qt_openended_a11y_new_response']  = 'A new response has been received';
$string['qt_openended_a11y_response_hidden'] = 'Response hidden from the projector view';
$string['qt_openended_a11y_response_shown']  = 'Response is now visible on the projector view';

// validate_config errors — openended.
$string['openended_max_chars_int_required'] = 'Maximum character count must be a whole number.';
$string['openended_max_chars_out_of_range'] = 'Maximum character count must be between {$a->min} and {$a->max}.';
$string['openended_moderation_bool']        = 'Moderation toggle must be on or off.';

// Rating-scale audience + result.
$string['qt_rating_audience_legend_stars'] = 'Tap a star to rate (1 = lowest, 5 = highest).';
$string['qt_rating_audience_legend_nps']   = 'Tap a number to score (1 = not at all likely, 10 = extremely likely).';
$string['qt_rating_star_suffix']           = 'star';
$string['qt_rating_nps_low']               = 'Not at all likely';
$string['qt_rating_nps_high']              = 'Extremely likely';
$string['qt_rating_result_heading']        = 'Rating distribution';
$string['qt_rating_result_a11y_label']     = 'Rating-scale results';
$string['qt_rating_histogram_a11y_label']  = 'Histogram of rating responses';
$string['qt_rating_mean_label']            = 'Mean';
$string['qt_rating_median_label']          = 'Median';
$string['qt_rating_a11y_mean_updated']     = 'Mean rating has been updated';
$string['qt_rating_a11y_median_updated']   = 'Median rating has been updated';

// validate_config errors — rating.
$string['rating_scale_type_invalid']         = 'Scale type must be "stars" (1-5) or "nps" (1-10).';
$string['rating_scale_labels_must_array']    = 'Scale labels must be a list.';
$string['rating_scale_labels_must_string']   = 'Each scale label must be a string.';
$string['rating_scale_labels_length_mismatch'] = 'Expected {$a->expected} scale labels but received {$a->got}.';

// Quiz audience + result.
$string['qt_quiz_audience_legend']         = 'Pick the option you think is correct.';
$string['qt_quiz_result_heading']          = 'Quiz results';
$string['qt_quiz_result_a11y_label']       = 'Quiz response distribution and leaderboard';
$string['qt_quiz_result_correct_aria']     = 'Correct answer:';
$string['qt_quiz_leaderboard_heading']     = 'Leaderboard';
$string['qt_quiz_leaderboard_subhead']     = '(top 10 fastest correct)';
$string['qt_quiz_leaderboard_caption']     = 'Top 10 participants who answered correctly, ranked by response time.';
$string['qt_quiz_a11y_correct']            = 'Your answer was correct';
$string['qt_quiz_a11y_incorrect']          = 'Your answer was incorrect';
$string['qt_quiz_a11y_leaderboard']        = 'Quiz leaderboard updated';

// Ranking audience + result.
$string['qt_ranking_audience_sortable_hint'] = 'Or drag-and-drop the items above into your preferred order.';
$string['qt_ranking_position_for']           = 'Position for';
$string['qt_ranking_result_heading']         = 'Ranking results (Borda count)';
$string['qt_ranking_result_a11y_label']      = 'Ranking results table';
$string['qt_ranking_table_caption']          = 'Items ranked by Borda count (higher = more preferred) with average position as a tie-breaker.';
$string['qt_ranking_borda_label']            = 'Borda points';
$string['qt_ranking_item_fallback']          = 'Item {$a}';
$string['qt_ranking_borda_explainer']        = 'Borda points reward higher positions: with N items, position 1 earns N points down to position N earning 1 point. Higher total = more preferred.';
$string['qt_ranking_a11y_item_moved']        = 'Item moved to a new position';
$string['qt_ranking_a11y_ranking_changed']   = 'Ranking results have changed';
