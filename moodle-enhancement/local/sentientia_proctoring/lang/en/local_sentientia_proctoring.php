<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Proctoring';

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
