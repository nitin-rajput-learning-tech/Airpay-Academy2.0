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
