<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * BizLMS import registry of local_sentientia_exams (ADR-032).
 *
 * Read by local_sentientia_platform\bizlms\registry::discover(). One line per feature this plugin owns the
 * target tables of; the class lives under classes/bizlms/ so the static scan reads it.
 *
 * @package local_sentientia_exams
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    // The quizzes of the BizLMS online exam courses -> local_sentientia_exams (mapping doc, section 9).
    'exams' => \local_sentientia_exams\bizlms\exams_importer::class,
];
