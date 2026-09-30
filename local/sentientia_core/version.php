<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_core';
$plugin->version   = 2026093001;          // YYYYMMDDNN (2026093001: local_sentientia_admin_log, the ADR-032 legacy_logs import target; before that 2026090301: db/install.php substrate::ensure_all() on FRESH install too - UAT Stage A finding 2026-09-03)
$plugin->requires  = 2024100700;          // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;      // ADR-019 W2 seam + ADR-020 W3.1/3.2/3.2b/3.3/3.4 org + ADR-021 W4 registry (all default-legacy/OFF, dormant) + ADR-032 legacy_logs importer (CLI only) and its report page (flag OFF).
$plugin->release   = '0.8.0-alpha';
// No hard dependency on local_sentientia_platform — tenant_identity guards the
// delegation with class_exists() so the seam degrades gracefully.
