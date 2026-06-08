<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_roles';
// P1 #56 (2026-05-20) — Hindi pack: 94 strings covering capabilities,
// filters, table columns, view tabs, capability edit modal, audit log,
// errors, privacy metadata.
// Goal A audit Bug #10 (2026-05-22) — align list_audit WS with the shared
// theme_airpayux/datatable contract (accept `search`, aliased to the
// existing capability filter).
$plugin->version   = 2026052201;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.1.3-beta'; // +Goal A Bug #10 WS-contract alignment
