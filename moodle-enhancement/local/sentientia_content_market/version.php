<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_content_market';
$plugin->version   = 2026061601;  // +1: idx_provider_ext now per-tenant (provider, external_id, costcenterid)
$plugin->requires  = 2024100700;  // Moodle 4.5+
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.0-beta';
