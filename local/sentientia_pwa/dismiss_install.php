<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Record the user's dismissal of the PWA install CTA — Phase D.1.b
 * (2026-05-22 bug fix). Stores the current timestamp in the
 * `local_sentientia_pwa_install_dismissed_at` user preference so
 * subsequent page renders skip the CTA entirely on the server side.
 *
 * Posted to by amd/install_prompt.js when the user clicks Not now.
 * Fire-and-forget — UI does NOT wait for the response.
 *
 * @package local_sentientia_pwa
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

set_user_preference('local_sentientia_pwa_install_dismissed_at',
    (int) time());

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
