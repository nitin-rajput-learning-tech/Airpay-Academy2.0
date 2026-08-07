<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_integrations';
// 2026080700 — KeKa JML hardening: webhook gated behind
// sentientia.hrms.webhook.enabled + hrms_enable, hash_equals secret
// (header-only), canonical user_create_user/user_update_user upsert with
// open_employeeid-first identity matching, leaver session-kill, validated
// default tenant placement, reportsTo manager sync, keka_reconcile task.
$plugin->version   = 2026080700;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.2.0-beta';
