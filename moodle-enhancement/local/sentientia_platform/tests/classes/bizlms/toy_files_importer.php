<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\copies_files;

/**
 * The toy importer with the copies_files marker (owner decision IDN-04).
 *
 * toy_importer is the importer that does NOT implement the marker, so the same class cannot be both: a PHP class
 * implements an interface or it does not. The file areas it declares are the static knob
 * toy_importer::$fileareas, so one reset() puts everything back. Setting toy_importer::$writefile makes finalise()
 * store a file in the system context, inside or outside those areas.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class toy_files_importer extends toy_importer implements copies_files {

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public function allowed_file_areas(): array {
        return self::$fileareas;
    }
}
