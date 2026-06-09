<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin entry-point router.
 *
 * B14 / F-079 stabilization fix (2026-05-28). Plugin's actual UI lives
 * at `admin.php` (admin-facing only). This stub provides a predictable
 * `index.php` so URL-routing probes and external links resolve to the
 * right place without 404-ing.
 *
 * 302-redirect to canonical admin URL.
 *
 * @package    local_sentientia_proctoring
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$target = new moodle_url('/local/sentientia_proctoring/admin.php', $_GET);
redirect($target);
