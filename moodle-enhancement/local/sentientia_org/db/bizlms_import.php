<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * BizLMS data-import importers owned by local_sentientia_org (ADR-032).
 *
 * Read by local_sentientia_platform\bizlms\registry::load() the way db/feature_flags.php files are
 * discovered. Every importer class lives under classes/bizlms/.
 *
 * @package    local_sentientia_org
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    'cohort_scope' => \local_sentientia_org\bizlms\cohort_scope_importer::class,
];
