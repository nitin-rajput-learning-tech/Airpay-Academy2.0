<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * BizLMS import registry of local_sentientia_users (ADR-032).
 *
 * Read by local_sentientia_platform\bizlms\registry::discover(). One line per feature this plugin owns the
 * target tables of; the class lives under classes/bizlms/ so the static scan reads it.
 *
 * @package local_sentientia_users
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    // HRMS sync history, transcript history, login days, positions and domains (mapping doc, section 10).
    'users' => \local_sentientia_users\bizlms\users_importer::class,
];
