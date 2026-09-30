<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * BizLMS import registry of local_sentientia_core (ADR-032).
 *
 * Read by local_sentientia_platform\bizlms\registry::discover(). One line per feature this plugin owns the
 * target tables of; the class lives under classes/bizlms/ so the static scan reads it.
 *
 * @package local_sentientia_core
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    // local_logs and local_courseerrors -> local_sentientia_admin_log (mapping doc, section 8).
    'legacy_logs' => \local_sentientia_core\bizlms\legacy_logs_importer::class,
];
