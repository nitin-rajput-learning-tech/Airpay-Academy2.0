<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * BizLMS import registry (ADR-032): the importers this plugin owns, discovered by
 * local_sentientia_platform\bizlms\registry the way db/feature_flags.php files are.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    'course_tags' => \local_sentientia_courses\bizlms\course_tags_importer::class,
];
