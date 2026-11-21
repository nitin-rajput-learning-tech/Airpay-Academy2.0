<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Proctoring';

// Navigation.
$string['adminhome']      = 'Proctoring admin';
$string['reviewqueue']    = 'Review queue';
$string['attempts']       = 'Proctored attempts';
$string['mysessions']     = 'My proctored sessions';
$string['settings_h']     = 'Proctoring settings';

// Capabilities.
$string['sentientia_proctoring:attempt']      = 'Attempt a proctored quiz';
$string['sentientia_proctoring:viewattempts']  = 'View proctoring attempt details';
$string['sentientia_proctoring:review']        = 'Review flagged proctoring sessions';
$string['sentientia_proctoring:manage']        = 'Manage proctoring settings + reviewer assignments';
$string['sentientia_proctoring:bypass']        = 'Bypass proctoring (emergency, audit-logged)';

// Consent flow.
$string['consent_title']     = 'Recorded exam — consent required';
$string['consent_intro']     = 'This exam is proctored. Before you begin, please review and consent.';
$string['consent_l1']        = '<strong>Identity verification</strong> — you will be asked to upload a photo of a government ID and a selfie. The match score is computed and only the score is retained.';
$string['consent_l2']        = '<strong>Webcam and screen monitoring</strong> — your camera and screen are recorded for the duration of the exam. Audio is captured for noise-flagging only.';
$string['consent_l3']        = '<strong>Automated review</strong> — recordings are scanned by AI for suspicious behaviour (multiple faces, frame exits, prolonged background noise). Flagged sessions are reviewed by a human proctor.';
$string['consent_retention'] = 'Recordings are retained for {$a} days then deleted. Identity photos are deleted immediately after match.';
$string['consent_accept']    = 'I have read and consent to these recording terms';
$string['consent_decline']   = 'Cancel and exit';
$string['consent_proceed']   = 'Continue to exam';

// Identity step.
$string['identity_title']    = 'Step 1 — Identity verification';
$string['identity_id_label'] = 'Government ID (clear photo, all 4 corners visible)';
$string['identity_selfie_label'] = 'Selfie (face centred, well-lit)';
$string['identity_submit']   = 'Verify identity';
$string['identity_processing'] = 'Verifying — this takes 10-30 seconds...';
$string['identity_passed']   = 'Identity verified (match score: {$a})';
$string['identity_failed']   = 'Identity verification failed. Please retry or contact support.';
$string['identity_lowmatch']  = 'Match score too low ({$a}). Please retake the selfie in better light.';

// Monitoring step.
$string['monitor_title']     = 'Step 2 — Live monitoring active';
$string['monitor_camera']    = 'Camera on';
$string['monitor_mic']       = 'Microphone on';
$string['monitor_screen']    = 'Screen recording on';
$string['monitor_lockwarn']  = 'Do not leave this browser tab. Tab switches are logged.';

// Events.
$string['event_face_lost']        = 'Face left frame';
$string['event_multiple_faces']   = 'Multiple faces detected';
$string['event_tab_switch']       = 'Tab switched away';
$string['event_window_blur']      = 'Window lost focus';
$string['event_mic_noise']        = 'Background noise spike';
$string['event_clipboard_paste']  = 'Paste detected';
$string['event_fullscreen_exit']  = 'Fullscreen exited';
$string['event_session_start']    = 'Session started';
$string['event_session_end']      = 'Session ended';

// Review queue.
$string['review_pending']        = 'Pending review';
$string['review_in_progress']    = 'In review';
$string['review_completed']      = 'Reviewed';
$string['review_decision']       = 'Decision';
$string['review_decision_clean'] = 'Clean — no issues';
$string['review_decision_warn']  = 'Warning — minor flags';
$string['review_decision_fail']  = 'Cheating detected — fail attempt';
$string['review_note']           = 'Reviewer note';
$string['review_assign']         = 'Assign reviewer';

// Status.
$string['status_new']         = 'Not started';
$string['status_consenting']  = 'Awaiting consent';
$string['status_verifying']   = 'Verifying identity';
$string['status_recording']   = 'Recording';
$string['status_finished']    = 'Finished';
$string['status_flagged']     = 'Flagged for review';
$string['status_reviewed']    = 'Reviewed';

// Settings.
$string['settings_provider']   = 'Identity verification provider';
$string['settings_provider_desc'] = 'aws = AWS Rekognition (production), mock = local mock (testing/dev). Set per-environment.';
$string['settings_aws_region']  = 'AWS region';
$string['settings_aws_key']     = 'AWS access key ID';
$string['settings_aws_secret']  = 'AWS secret access key';
$string['settings_aws_s3_bucket'] = 'S3 bucket for recordings';
$string['settings_match_threshold'] = 'Identity match threshold (%)';
$string['settings_match_threshold_desc'] = 'Minimum face-match score to pass identity step. Default 85.';
$string['settings_retention_days']  = 'Recording retention (days)';
$string['settings_retention_days_desc'] = 'How many days recordings are kept before deletion. Default 90.';
$string['settings_recording_chunk_secs'] = 'Recording chunk size (seconds)';
$string['settings_recording_chunk_secs_desc'] = 'How often video chunks are uploaded. Default 30s.';
$string['settings_default_reviewer'] = 'Default reviewer userid';
$string['settings_default_reviewer_desc'] = 'User who receives flagged sessions if no specific reviewer is assigned.';

// Notifications.
$string['messageprovider:session_flagged']   = 'Proctored session flagged for review';
$string['messageprovider:session_reviewed']  = 'Your proctored session was reviewed';
$string['messageprovider:identity_failed']   = 'Identity verification failed';

// Errors.
$string['error_consent_required']  = 'Consent is required before starting a proctored exam.';
$string['error_identity_required'] = 'Identity verification must complete before starting.';
$string['error_no_provider']       = 'No identity provider configured.';
$string['error_session_not_found'] = 'Proctoring session not found.';
$string['error_session_state']     = 'Invalid session state for this action.';
$string['error_review_not_allowed'] = 'You are not authorised to review this session.';

// Privacy.
$string['privacy:metadata:local_sentientia_proctor_sessions'] = 'Proctored exam sessions';
$string['privacy:metadata:local_sentientia_proctor_identity'] = 'Identity verification scores (photos deleted after match)';
$string['privacy:metadata:local_sentientia_proctor_events']   = 'Per-attempt behavioural events';
$string['privacy:metadata:local_sentientia_proctor_recordings'] = 'S3 keys for webcam/screen recordings';
$string['privacy:metadata:local_sentientia_proctor_reviews']  = 'Human reviewer notes and decisions';
$string['privacy:metadata:aws_rekognition'] = 'AWS Rekognition (identity face matching)';
$string['privacy:metadata:aws_rekognition:photo'] = 'ID photo and selfie (deleted after match, only score retained)';
$string['privacy:metadata:aws_s3'] = 'AWS S3 (recording storage)';
$string['privacy:metadata:aws_s3:video'] = 'Video chunks of the exam attempt';
$string['privacy:metadata:logstore'] ='A flagged session and a submitted review are recorded in the standard log: the session, the quiz, the candidate, the reviewer and the decision.';

// Decision A5 (2026-11-12): the two audit events the platform's audit log lists.
$string['eventsessionflagged']  = 'Proctored session flagged for review';
$string['eventreviewsubmitted'] = 'Proctoring review submitted';

// B1 "Fix the foundation" (ADR-038 D2, 2026-11-20).
// The banner the monitor shows on every page of an open proctored attempt.
$string['monitor_notice'] = 'This attempt is proctored: switching to another tab or window, leaving fullscreen and pasting text are recorded.';
$string['error_event_type'] = 'This event cannot be reported from the browser.';
// B1 review (2026-11-20): upload_chunk refuses while sentientia.proctoring.recording.enabled is OFF.
$string['error_recording_disabled'] = 'Recording is not switched on for your organisation.';
// Server-recorded and monitor events, as reviewers see them.
$string['event_page_shown']         = 'Quiz page opened';
$string['event_window_focus']       = 'Window regained focus';
$string['event_consent_given']      = 'Learner agreed to monitoring';
$string['event_identity_confirmed'] = 'Learner confirmed they are taking the attempt themselves';
$string['event_attempt_linked']     = 'Linked to the quiz attempt';
$string['event_attempt_submitted']  = 'Attempt submitted';
$string['event_attempt_abandoned']  = 'Attempt abandoned';
$string['event_attempt_overdue']    = 'Attempt overdue';
// Scheduled task.
$string['task_purge_old_recordings'] = 'Proctoring: delete recordings past their retention date';
// Settings headings.
$string['settings_h_store']      = 'Recording storage (AWS S3)';
$string['settings_h_store_desc'] = 'Used by the daily task that deletes recordings past their retention date. Identity photos are no longer sent to any provider (decision D10: no automated ID-to-face matching).';
$string['settings_h_rec']        = 'Recording';
$string['settings_h_rev']        = 'Review queue';
// Notifications (sent in the recipient's language).
$string['msg_flagged_subject'] = 'Proctored session flagged: {$a->decision} (risk {$a->score})';
$string['msg_flagged_body'] = 'A proctored quiz session has been flagged for review.

Session ID: {$a->sessionid}
User ID: {$a->userid}
Quiz ID: {$a->quizid}
Risk score: {$a->score}/100
Automatic verdict: {$a->decision}

Open the proctoring review queue to look at the session and record a decision.';
$string['msg_reviewed_subject'] = 'Your proctored quiz attempt was reviewed';
$string['msg_reviewed_body'] = 'A reviewer has looked at your proctored quiz attempt.

Decision: {$a->decision}

Thank you for completing the proctored quiz.';
$string['msg_reviewed_body_fail'] = 'A reviewer has looked at your proctored quiz attempt.

Decision: {$a->decision}

The reviewer found activity that breaks the rules of this quiz, so the result of this attempt may not count. If you think this is a mistake, please contact your L&D team.';
$string['msg_identityfailed_subject'] = 'Identity verification failed';
$string['msg_identityfailed_body'] = 'Your identity verification did not pass for this proctored quiz.

Match score: {$a->score}%

Please try again with your face centred and well lit. If it keeps failing, contact your L&D team.';
// Privacy fields.
$string['privacy:metadata:sessions:userid']           = 'The learner whose attempt is proctored';
$string['privacy:metadata:sessions:quizid']           = 'The proctored quiz';
$string['privacy:metadata:sessions:attemptid']        = 'The quiz attempt the session belongs to';
$string['privacy:metadata:sessions:status']           = 'Where the session is in its life cycle';
$string['privacy:metadata:sessions:consent_given_at'] = 'When the learner agreed to monitoring in the quiz\'s pre-start check';
$string['privacy:metadata:sessions:risk_score']       = 'The score computed from the recorded events';
$string['privacy:metadata:sessions:auto_decision']    = 'The automatic verdict from that score';
$string['privacy:metadata:sessions:human_decision']   = 'The reviewer\'s verdict';
$string['privacy:metadata:sessions:timestarted']      = 'When monitoring started';
$string['privacy:metadata:sessions:timefinished']     = 'When the server closed the session';
$string['privacy:metadata:events:event_type']         = 'What happened (for example a tab switch, a paste, consent given, attempt submitted)';
$string['privacy:metadata:events:severity']           = 'How much the event counts in the score';
$string['privacy:metadata:events:payload_json']       = 'Details of the event, such as the quiz page number and, in secure mode, the network (IP) address each quiz page was requested from';
$string['privacy:metadata:events:timecreated']        = 'When the server recorded the event';

// B2 "Secure assessment mode" (ADR-038 D3 and D6, 2026-11-20).
// On the attempt pages (local_sentientia_proctoring/secure_mode), in the learner's language.
$string['secure_banner'] = 'Secure mode: copying, pasting and printing are blocked, and leaving fullscreen or this page is recorded.';
$string['secure_banner_count'] = 'Violations recorded: {$a}.';
$string['secure_banner_limit'] = 'Violations recorded: {$a->count} of {$a->limit}. At {$a->limit} your attempt is submitted automatically.';
$string['secure_gate_title'] = 'Fullscreen required';
$string['secure_gate_body'] = 'The questions are shown only in fullscreen. Select the button to continue.';
$string['secure_gate_returned'] = 'You left fullscreen, so the questions are hidden. This was recorded.';
$string['secure_gate_denied'] = 'Your browser did not switch to fullscreen. Try again. If it keeps failing, use an up-to-date desktop browser or contact your L&D team.';
$string['secure_gate_button'] = 'Continue in fullscreen';
$string['secure_warning_flag'] = 'Violation {$a} recorded: you left fullscreen or this page. Your attempt will be flagged for a reviewer.';
$string['secure_warning_limit'] = 'Violation {$a->count} of {$a->limit} recorded: you left fullscreen or this page. At {$a->limit} your attempt is submitted automatically.';
$string['secure_autosubmitted_title'] = 'Your attempt was submitted';
$string['secure_autosubmitted_body'] = 'Your attempt reached {$a} violations, so it was submitted automatically with the answers saved up to that moment. A reviewer will look at it.';
$string['secure_continue'] = 'Continue';
$string['secure_blocked'] = 'This is not allowed during this assessment.';
$string['secure_watermark'] = '{$a->name} · attempt {$a->attempt}';
// Secure-mode events, as reviewers see them.
$string['event_page_served']             = 'Quiz page sent by the server';
$string['event_heartbeat_gap']           = 'The page stopped reporting';
$string['event_monitor_missing']         = 'Pages that never reported';
$string['event_rate_limited']            = 'Too many reports: some were not stored';
$string['event_integrity_autosubmitted'] = 'Attempt submitted automatically (violation limit)';
$string['event_secure_rules_accepted']   = 'Learner accepted the secure-mode rules';
$string['event_fullscreen_enter']        = 'Entered fullscreen';
$string['event_fullscreen_unavailable']  = 'Browser has no fullscreen (questions not hidden)';
$string['event_fullscreen_denied']       = 'Browser refused fullscreen';
$string['event_file_dialog']             = 'File chooser opened (not counted)';
$string['event_copy_blocked']            = 'Copy blocked';
$string['event_cut_blocked']             = 'Cut blocked';
$string['event_paste_blocked']           = 'Paste blocked';
$string['event_contextmenu_blocked']     = 'Right-click blocked';
$string['event_print_blocked']           = 'Print blocked';
$string['event_shortcut_blocked']        = 'Keyboard shortcut blocked';
$string['event_drop_blocked']            = 'Drag and drop blocked';
$string['event_screenshot_key']          = 'Print Screen key pressed (cannot be blocked)';
$string['event_extended_display']        = 'A second display is connected (as the browser reports it)';
$string['event_ip_changed']              = 'Network address changed between two pages';
// The integrity report on the quiz attempt review page (teachers).
$string['report_title'] = 'Integrity report';
$string['report_level'] = 'Security level';
$string['report_policy'] = 'On violations';
$string['report_policy_warn'] = 'Warn and flag for review';
$string['report_policy_autosubmit'] = 'Submit automatically at {$a} violations';
$string['report_violations'] = 'Violations counted';
$string['report_violations_of'] = '{$a->count} of {$a->limit}';
$string['report_autosubmitted'] = 'Submitted automatically';
$string['report_status'] = 'Review status';
$string['report_verdict_auto'] = 'Automatic verdict';
$string['report_verdict_human'] = 'Reviewer verdict';
$string['report_gaps'] = 'Heartbeat gaps';
$string['report_unmonitored'] = 'Pages that never reported';
$string['report_counts'] = 'Events';
$string['report_timeline'] = 'Timeline (server time)';
$string['report_timeline_truncated'] = 'Showing the newest {$a->shown} of {$a->total} events.';
$string['report_col_time'] = 'Time';
$string['report_col_event'] = 'Event';
$string['report_col_page'] = 'Page';
$string['report_col_note'] = 'Details';
$string['report_counted'] = 'Counted as a violation';
$string['report_counted_grace_exceeded'] = 'Counted as a violation: the window lost the focus within {$a} seconds of the page opening and did not get it back within those seconds';
$string['report_notcounted_duplicate'] = 'Not counted: within {$a} seconds of the last violation';
$string['report_notcounted_grace'] = 'Not counted: within {$a} seconds of the page opening';
$string['report_note_gap'] = 'No report for {$a} seconds';
$string['report_note_missing'] = '{$a->served} pages sent, {$a->shown} reported';
$string['report_note_autosubmitted'] = 'At {$a->violations} of {$a->limit} violations';
$string['report_note_ip'] = 'From {$a->from} to {$a->to}';
$string['report_summarypage'] = 'Summary';
$string['report_none'] = 'No events recorded.';
$string['report_open'] = 'Open in the proctoring pages';
$string['report_limits'] = 'These records come from the learner\'s browser; every time is our server\'s. Leaving fullscreen or the page is counted; blocked copy, paste and print attempts are listed but not counted. The page cannot see a second device, another person or a screenshot taken with the computer\'s own tools, and a modified browser can stop it: a heartbeat gap, or a page that never reported, is then the trace. A reviewer decides; the automatic verdict only orders the review queue.';
// Privacy fields (B2).
$string['privacy:metadata:sessions:securitylevel']     = 'The security level of the quiz when the learner accepted its rules';
$string['privacy:metadata:sessions:violationlimit']    = 'The number of violations at which the attempt is submitted automatically (hiring assessments)';
$string['privacy:metadata:sessions:violations']        = 'How many violations the server counted';
$string['privacy:metadata:sessions:lastviolation']     = 'When the last violation was counted';
$string['privacy:metadata:sessions:lastheartbeat']     = 'When the attempt page last reported to the server';
$string['privacy:metadata:sessions:timeautosubmitted'] = 'When the server submitted the attempt because it reached the limit';

// Privacy metadata: fields of the identity, recordings and reviews tables (2026-11-21).
$string['privacy:metadata:identity:userid']        = 'The learner whose identity was checked';
$string['privacy:metadata:identity:sessionid']     = 'The proctored session the check belongs to';
$string['privacy:metadata:identity:provider']      = 'The service that compared the photos';
$string['privacy:metadata:identity:match_score']   = 'How closely the selfie matched the ID photo';
$string['privacy:metadata:identity:passed']        = 'Whether the identity check passed';
$string['privacy:metadata:identity:error_code']    = 'The error code if the check could not be completed';
$string['privacy:metadata:identity:error_msg']     = 'The error message if the check could not be completed';
$string['privacy:metadata:identity:timecreated']   = 'When the check was made';
$string['privacy:metadata:recordings:sessionid']   = 'The proctored session the recording belongs to';
$string['privacy:metadata:recordings:kind']        = 'Whether the recording is of the webcam or the screen';
$string['privacy:metadata:recordings:s3_key']      = 'Where the recording is stored';
$string['privacy:metadata:recordings:duration_ms'] = 'How long the recording is';
$string['privacy:metadata:recordings:retain_until'] = 'When the recording is due to be deleted';
$string['privacy:metadata:recordings:deleted_at']  = 'When the recording was deleted';
$string['privacy:metadata:recordings:timecreated'] = 'When the recording was stored';
$string['privacy:metadata:reviews:sessionid']      = 'The proctored session that was reviewed';
$string['privacy:metadata:reviews:reviewer_userid'] = 'The reviewer who made the decision';
$string['privacy:metadata:reviews:decision']       = 'The reviewer\'s decision';
$string['privacy:metadata:reviews:note']           = 'The reviewer\'s note';
$string['privacy:metadata:reviews:timecreated']    = 'When the review was submitted';
