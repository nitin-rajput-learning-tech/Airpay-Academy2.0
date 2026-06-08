<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_programs';
// W1-9 + P1 #9/10/14/15 (2026-05-16) — program_completed event + window +
// rich-text + audience enroller + cohort filter + bulk-enrol modal UI + Hindi.
// P1 #45 (2026-05-20) — Hindi top-up: 65 additional strings.
$plugin->version   = 2026052001;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.8.1';  // +P1 #45 Hindi top-up
$plugin->dependencies = ['local_sentientia_org' => 2026041600];
