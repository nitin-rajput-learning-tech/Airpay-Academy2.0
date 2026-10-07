<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-032: the BizLMS importers this plugin owns.
 *
 * Discovered by local_sentientia_platform\bizlms\registry the way db/feature_flags.php files are. The
 * import has no user-visible surface and no feature flag: it runs only through
 * local/sentientia_platform/cli/import_bizlms.php behind the CLI guard.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    'classroom' => \local_sentientia_classroom\bizlms\importer::class,
];
