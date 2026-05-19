<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_skills';
// P1 #22 (2026-05-16) — append-only audit log of skill-level changes
// (local_airpay_user_skill_hist). Closes audit item #23 from
// parity-audit-2026-05-15/airpay_skills.md.
$plugin->version   = 2026051901;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.0'; // + P1 #22 skill-level audit log
