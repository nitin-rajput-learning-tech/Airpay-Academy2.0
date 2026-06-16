<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

// P2.1 (2026-06-16) — Talent mobility / succession / career pathing.
// New plugin built on the skills taxonomy (local_sentientia_skillsai when
// present, falling back to local_sentientia_skills) and role definitions
// from local_sentientia_roles. Default-OFF feature flag sentientia.talent.enabled.
$plugin->component = 'local_sentientia_talent';
$plugin->version   = 2026061600;
$plugin->requires  = 2024100700; // Moodle 4.5+ / 5.1.
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.0-beta';
$plugin->dependencies = [
    // Hard dependency: tenant + feature-flag platform layer.
    'local_sentientia_platform' => ANY_VERSION,
    // Hard dependency: manual skills taxonomy used as graceful-degradation
    // fallback when the AI skills plugin (local_sentientia_skillsai) is absent.
    'local_sentientia_skills'   => ANY_VERSION,
];
