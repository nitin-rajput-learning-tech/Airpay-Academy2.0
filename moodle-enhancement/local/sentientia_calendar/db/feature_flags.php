<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_calendar.
 *
 * Master flag plus per-event-type sub-flags. Admins can selectively
 * enable / disable which Moodle event sources appear in the ICS feed —
 * e.g. a customer that doesn't use classroom (ILT) can switch off the
 * classroom feed without affecting course / exam reminders.
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — Calendar Sync (Tier 2.6) ──────────────
    'sentientia.calendar_sync.enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS calendar sync (outbound ICS feed).
                          Master switch. When OFF: the user-facing
                          subscription page returns a 403, ics.php returns
                          a 404 even with a valid token, the "My Calendar"
                          nav node is hidden, and token regeneration is
                          rejected. When ON: each authenticated user gets
                          a personal subscription URL they can paste into
                          Outlook / Google Calendar / Apple Calendar.
                          Default OFF until per-customer rollout per ADR-013.',
    ],

    'sentientia.calendar_sync.events.courses' => [
        'default'     => true,
        'description' => 'Include course completion deadlines in the ICS feed.
                          Each enrolled course with open_coursecompletiondays
                          > 0 generates one VEVENT at
                          enrolment.timestart + days * 86400.
                          When OFF, course-deadline VEVENTs are omitted
                          from the feed.',
    ],

    'sentientia.calendar_sync.events.classroom' => [
        'default'     => true,
        'description' => 'Include classroom (instructor-led training)
                          sessions in the ICS feed. Each
                          local_airpay_classroom_sessions row the user is
                          enrolled in generates one VEVENT with
                          starttime → endtime.
                          When OFF, classroom-session VEVENTs are omitted
                          from the feed. Useful for customers that don\'t
                          use ILT.',
    ],

    'sentientia.calendar_sync.events.exams' => [
        'default'     => true,
        'description' => 'Include exam close-dates in the ICS feed. Each
                          quiz.timeclose > now in a course the user is
                          enrolled in generates one VEVENT.
                          When OFF, exam-close VEVENTs are omitted from
                          the feed.',
    ],

    // ─── Tier 2.6 Phase 2 — OAuth scaffolding (default OFF) ──────────
    'sentientia.calendar_sync.oauth.enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS Calendar Sync — OAuth 2.0
                          bi-directional sync (Microsoft 365 + Google
                          Calendar). MASTER SWITCH for Phase 2. When OFF
                          (the default for this chip): the connect /
                          callback / refresh surfaces all 404, no live
                          HTTP traffic reaches Microsoft or Google, and
                          no rows accumulate in {local_sentientia_calendar_oauth}.
                          When ON: the user-facing "Connect Outlook" /
                          "Connect Google" buttons appear, the
                          Authorization Code with PKCE flow runs, and
                          access + refresh tokens are stored
                          encrypted-at-rest via \\core\\encryption.
                          Phase 2 ships SCAFFOLDING only — even when this
                          flag is ON, the live HTTP exchange throws
                          oauth_not_live until Phase 2.1 wires it up.
                          Per ADR-013, default OFF stays until per-customer
                          rollout decisions are made.',
    ],

];
