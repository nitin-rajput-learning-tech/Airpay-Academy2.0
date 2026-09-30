<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-032: the BizLMS importer this plugin owns, discovered by
 * local_sentientia_platform\bizlms\registry (CLI only).
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    'recompletion' => \local_sentientia_recompletion\bizlms\importer::class,
];
