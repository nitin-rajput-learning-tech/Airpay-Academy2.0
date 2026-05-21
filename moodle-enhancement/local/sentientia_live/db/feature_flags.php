<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_live.
 *
 * Master flag plus per-question-type sub-flags. Admins can selectively
 * enable / disable question types as they ship per phase E.4 → E.9.
 *
 * Plus a realtime kill-switch — if SSE is overwhelming Apache workers,
 * admin can flip `live.realtime.enabled` OFF and the client falls back
 * to short-polling at 3s intervals.
 *
 * @package local_sentientia_live
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — Live engagement (Stream E) ───────────
    'live.enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS Live engagement (Mentimeter clone).
                          Master flag. When OFF, the entry pages return 403
                          and the SSE stream endpoint short-circuits. Default
                          OFF until Phase E.1 ships the trainer UI.',
    ],

    'live.realtime.enabled' => [
        'default'     => true,
        'description' => 'Phase E.3 — Server-Sent Events realtime push.
                          When ON, audience and trainer screens use
                          EventSource(stream.php) for instant updates.
                          When OFF, fall back to 3s short polling — same
                          behaviour but worse latency and higher request
                          volume. Toggle OFF if SSE is exhausting Apache
                          worker pool during a large session.',
    ],

    // ── Per-question-type ship gates ──
    // Each flag gates BOTH the trainer creation UI AND the audience
    // render — so a partial-rollout customer (Phase E.4 only?) can
    // expose just multichoice without showing word-cloud surfaces.
    'live.questiontype.multichoice' => [
        'default'     => false,
        'description' => 'Phase E.4 — Multiple-choice slide type
                          (bar chart of audience selections).',
    ],
    'live.questiontype.wordcloud' => [
        'default'     => false,
        'description' => 'Phase E.5 — Word cloud slide type (free text
                          aggregated into a tag cloud).',
    ],
    'live.questiontype.openended' => [
        'default'     => false,
        'description' => 'Phase E.6 — Open-ended slide type (scrolling list
                          of raw text responses).',
    ],
    'live.questiontype.rating' => [
        'default'     => false,
        'description' => 'Phase E.7 — Rating-scale slide type (1-5 or 0-10
                          NPS-style).',
    ],
    'live.questiontype.quiz' => [
        'default'     => false,
        'description' => 'Phase E.8 — Quiz slide type (right/wrong answer +
                          live leaderboard).',
    ],
    'live.questiontype.ranking' => [
        'default'     => false,
        'description' => 'Phase E.9 — Ranking slide type (drag-to-order
                          a list of N items).',
    ],

    // ── Audience access modes ──
    'live.allow_anonymous' => [
        'default'     => false,
        'description' => 'Allow audience members to join without logging in.
                          When ON, the trainer can mark per-session
                          allow_anonymous=1 and audience joins with just
                          a display name. When OFF, all participants must
                          authenticate. Default OFF for compliance — most
                          enterprise deployments want logged-in audiences
                          so they can correlate responses with learners.',
    ],

];
