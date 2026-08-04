<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Block plugin version — Sentientia LMS Real-time Leaderboard block.
 *
 * @package block_sentientia_leaderboard
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_sentientia_leaderboard';
$plugin->version   = 2026080400;  // 2026-08-04 privacy null-provider (GDPR registry closure)
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0-alpha';
$plugin->dependencies = [
    'local_sentientia_leaderboard' => 2026052400,
];
