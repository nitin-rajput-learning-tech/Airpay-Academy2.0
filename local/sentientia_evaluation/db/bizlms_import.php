<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * BizLMS data import (ADR-032): the importers this plugin owns.
 *
 * Discovered by local_sentientia_platform\bizlms\registry the way db/feature_flags.php files are. The key is the
 * feature key and must equal the importer's feature(). One file per plugin; add a line per importer.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    'evaluation' => \local_sentientia_evaluation\bizlms\importer::class,
];
