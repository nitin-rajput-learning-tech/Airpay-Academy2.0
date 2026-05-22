<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_challenge';
// P1 #58 (2026-05-20) — Hindi pack: 130 strings — largest pack — covering
// capabilities, filters, attempt status, types, form, buttons, tabs,
// overview, leaderboard, notifications, errors, scheduled task, privacy
// metadata for challenges/attempts/leaderboard tables.
// Goal A audit Bug #10 (2026-05-22) — align get_leaderboard WS with the
// shared theme_airpayux/datatable contract (accept `search`, currently
// reserved — leaderboard search semantics pending UX decision).
$plugin->version   = 2026052201;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.1.3-beta'; // +Goal A Bug #10 WS-contract alignment
