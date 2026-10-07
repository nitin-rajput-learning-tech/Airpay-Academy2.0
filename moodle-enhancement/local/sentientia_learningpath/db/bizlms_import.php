<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-032: the BizLMS data importers this plugin owns. Discovered by
 * local_sentientia_platform\bizlms\registry the way db/feature_flags.php is.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    'learningplan' => \local_sentientia_learningpath\bizlms\importer::class,
];
