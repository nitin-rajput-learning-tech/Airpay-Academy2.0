<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_leaderboard.
 *
 * Master flag (default OFF — additive shipping per CLAUDE.md) plus a
 * realtime kill-switch (default ON) and per-type ship gates (default OFF
 * so admins can roll out one type at a time after validating).
 *
 * @package local_sentientia_leaderboard
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Master ─────────────────────────────────────────────────
    'sentientia.leaderboards.enabled' => [
        'default'     => false,
        'description' => 'Sentientia LMS real-time leaderboards (Tier 2 #7).
                          Master flag. When OFF, every entry point returns 403,
                          the SSE stream short-circuits, and the block widget
                          renders empty. Default OFF until per-customer rollout
                          is approved.',
    ],

    // ─── Realtime kill-switch ──────────────────────────────────
    'sentientia.leaderboards.realtime.enabled' => [
        'default'     => true,
        'description' => 'Server-Sent Events for leaderboard updates. When ON,
                          the block AMD client opens an EventSource against
                          /local/sentientia_leaderboard/stream.php. When OFF,
                          falls back to 30s short-polling. Toggle OFF if SSE
                          is exhausting Apache workers during a peak.',
    ],

    // ─── Per-type ship gates ───────────────────────────────────
    // Each gates BOTH the board-creation UI (only allowed types are
    // offered) AND the read path (a board of a disabled type returns
    // empty rankings to learners).
    'sentientia.leaderboards.type.quiz' => [
        'default'     => false,
        'description' => 'Quiz leaderboard type — top scorers on a single
                          mod_quiz instance. Tied scores break on shorter
                          time taken.',
    ],

    'sentientia.leaderboards.type.completion' => [
        'default'     => false,
        'description' => 'Course completion leaderboard type — fastest
                          learners to N% completion of a course. Lower
                          seconds-to-complete = higher rank.',
    ],

    'sentientia.leaderboards.type.skill' => [
        'default'     => false,
        'description' => 'Skill points leaderboard type — most skill points
                          earned in a date range (reuses
                          local_airpay_user_skill_hist).',
    ],

    // ─── Privacy: per-user opt-out ──────────────────────────────
    'sentientia.leaderboards.optout.enabled' => [
        'default'     => true,
        'description' => 'Surface the "Hide me from public leaderboards" toggle
                          on /user/preferences.php. ON by default — privacy
                          mandate from Day 0 CLAUDE.md. Turning OFF makes the
                          opt-out preference unreachable (existing rows still
                          honoured at read time).',
    ],

    // ─── Phase L.1: rank-change notifications ──────────────────
    // Task spec uses `sentientia_leaderboard_notifications`; we keep the
    // dotted convention used by every other flag in this plugin so the
    // Switchboard groups it under `sentientia.leaderboards.*`.
    'sentientia.leaderboards.notifications.enabled' => [
        'default'     => false,
        'description' => 'Rank-change notifications via Moodle messaging
                          (Phase L.1). When ON, the recompute path triggers
                          a Moodle message to any learner who either moves
                          5+ positions on a board or enters the top 10.
                          Throttled to 1 message per learner per board per
                          24h. Default OFF — additive shipping per CLAUDE.md.',
    ],

];
