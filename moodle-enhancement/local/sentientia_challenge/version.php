<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_challenge';
// P1 #58 (2026-05-20) — Hindi pack: 130 strings — largest pack — covering
// capabilities, filters, attempt status, types, form, buttons, tabs,
// overview, leaderboard, notifications, errors, scheduled task, privacy
// metadata for challenges/attempts/leaderboard tables.
// Goal A audit Bug #10 (2026-05-22) — align get_leaderboard WS with the
// shared theme_airpayux/datatable contract (accept `search`, currently
// reserved — leaderboard search semantics pending UX decision).
// Stabilization Audit D4 / F-096 (2026-05-28) — downgraded BETA → ALPHA.
// The plugin's `classes/challenge_renderer.php` self-describes as "stub
// replacing BizLMS local_challenge"; all `mdl_local_sentientia_challenge_*`
// tables are empty on local + production. MATURITY_BETA was aspirational.
// Promote back to BETA once the renderer ships its real implementation
// and the challenge tables hold real attempt data.
$plugin->version   = 2026052801;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '1.1.4-alpha'; // +D4 maturity-stamp honesty
