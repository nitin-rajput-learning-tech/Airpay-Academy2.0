<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * The BizLMS importers this plugin owns (ADR-032), discovered by
 * \local_sentientia_platform\bizlms\registry the way db/feature_flags.php files are.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$imports = ['request' => \local_sentientia_request\bizlms\importer::class];
