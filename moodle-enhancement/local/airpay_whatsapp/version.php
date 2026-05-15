<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Airpay WhatsApp + SMS engagement channel.
 *
 * Phase A1 iter 1 (2026-05-15) — opt-in + preference UI only. No
 * external API integration yet — that's iter 3 with DLT compliance
 * and provider sign-off.
 *
 * @package    local_airpay_whatsapp
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_whatsapp';
$plugin->version   = 2026051500;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;   // No sending yet — opt-in UI only
$plugin->release   = '0.1.0-alpha';
$plugin->dependencies = [
    'local_airpay_core' => 2026051401,  // feature_flags resolver
];
// Release history
// 0.1.0-alpha  Phase A1 iter 1: opt-in + preference UI
//                + local_airpay_user_channel_prefs table
//                + classes/preference_manager
//                + preferences.php user-facing page
//                + classes/privacy/provider (GDPR + DPDP)
//                + PHPUnit tests for preference_manager
//              No external sending yet — that's iter 3.
