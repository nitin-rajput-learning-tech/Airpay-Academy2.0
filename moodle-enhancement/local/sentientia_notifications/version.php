<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_notifications';
// P1 #48 (2026-05-20) — Hindi top-up: 53 strings covering capabilities,
// CRUD form, errors, success, and privacy metadata.
// ADR-020 W3.4 (2026-06-02) — manager digest crons (rule_monthly_summary,
// rule_manager_nudge) route the manager->reports grouping through the
// local_sentientia_core\org seam (reports_by_manager): identical under
// org_legacy ON (prod-verified: 117 managers exact match); model at cutover.
$plugin->version   = 2026060200;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.4.2'; // +ADR-020 W3.4 org-seam migration of manager digests
