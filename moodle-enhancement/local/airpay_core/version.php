<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Airpay Core (shared infrastructure).
 *
 * Tenant-scoping helpers, cross-plugin shared traits.
 * Created Phase 8.1 (2026-05-12) to systematize the tenant-equality
 * check that 10/11 blocking security findings traced back to.
 *
 * @package    local_airpay_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_core';
$plugin->version   = 2026051300;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0'; // +cron-health publisher + audit_log + structured_logger
