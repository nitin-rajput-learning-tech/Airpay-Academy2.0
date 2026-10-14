<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_classroom';
// W1-7 + W1-9 + P1 #4/13/15 (2026-05-16) — meeting URLs + dates +
// audience enroller + Hindi pack.
// P1 #44 (2026-05-20) — Hindi top-up: 74 additional strings covering
// CRUD, sessions, attendance, view tabs, privacy metadata.
// 2026100701: ADR-032 owner decision IDN-04 - the importer implements the platform's copies_files marker (its logo copy is a
// declared side effect, counted in the run report). No schema change. The marker interface ships with
// local_sentientia_platform 2026100701, which this plugin therefore requires. One version for the batch (F-85).
// 2026093002: ADR-032: BizLMS import schema (trainers, linked courses, roster completion, location hierarchy) + importer
// 2026100702: one version above both 2026100701 bumps (IDN-04 copies_files marker; XC-CLS-ENROL services capability), so a site at either re-reads db/services.php.
$plugin->version   = 2026100702;  // ADR-032 IDN-04 + XC-CLS-ENROL (no schema change)
// 2026093001: T-01: teacher archetype can view classrooms + take attendance (back-fill)
// 2026092501: locations table + locationid in install.xml; lat/long NUMBER(10,6)
// 2026092500: ADR-031: every classroomid/sessionid/userid tenant-checked
// 2026092400: waitlist table in install.xml + created where missing
// 2026092200: count_classrooms takes a path, not a LIKE pattern
// 2026052001:
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.11.2';  // ADR-032 IDN-04 copies_files marker + XC-CLS-ENROL services capability; 1.11.0: ADR-032 BizLMS classroom importer + import schema
$plugin->dependencies = [
    'local_sentientia_org'      => 2026041600,
    'local_sentientia_platform' => 2026100701,  // tenant::is_cross_tenant / scope_path; bizlms import framework + provenance; the copies_files marker (IDN-04)
];
