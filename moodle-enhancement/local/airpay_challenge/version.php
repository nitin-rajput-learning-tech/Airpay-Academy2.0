<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_challenge';
// P1 #58 (2026-05-20) — Hindi pack: 130 strings — largest pack — covering
// capabilities, filters, attempt status, types, form, buttons, tabs,
// overview, leaderboard, notifications, errors, scheduled task, privacy
// metadata for challenges/attempts/leaderboard tables.
$plugin->version   = 2026052001;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.1.2-beta'; // +P1 #58 Hindi pack
