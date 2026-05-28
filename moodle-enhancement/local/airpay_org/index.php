<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin entry-point router.
 *
 * B14 / F-079 stabilization fix (2026-05-28). Plugin's actual UI lives
 * at `admin.php` (admin-facing only — no learner-facing view). This
 * stub provides a predictable `index.php` so external links,
 * sidebar-nav code that auto-discovers plugin entry points, and the
 * Phase 1 audit's URL-routing probes all resolve to the right place
 * without 404-ing.
 *
 * 302-redirect to the canonical admin URL — preserves any query
 * string (useful for deep links from the audit doc / Switchboard).
 *
 * @package    local_airpay_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$target = new moodle_url('/local/airpay_org/admin.php', $_GET);
redirect($target);
