<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * v1: GET /openapi — return the OpenAPI 3.0 spec describing the v1 surface.
 *
 * The canonical spec ships as docs/openapi-v1.yaml. This endpoint reads it
 * from disk and returns it (JSON-encoded) so a client can self-document and
 * generate SDKs. Falls back to a minimal inline document if the file is
 * unreadable.
 *
 * @package local_sentientia_api
 */
class openapi extends base {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * @return array
     */
    public static function execute(): array {
        global $CFG;

        self::validate_parameters(self::execute_parameters(), []);
        self::open_v1('local_sentientia_api_v1_openapi', 'local/sentientia_api:read');

        $path = $CFG->dirroot . '/local/sentientia_api/docs/openapi-v1.yaml';
        $spec = '';
        if (is_readable($path)) {
            $spec = (string) file_get_contents($path);
        }
        if ($spec === '') {
            $spec = "openapi: 3.0.3\ninfo:\n  title: Sentientia Public API\n  version: '1.0.0'\n";
        }

        return [
            'version' => 'v1',
            'format'  => 'yaml',
            'spec'    => $spec,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'version' => new external_value(PARAM_ALPHANUMEXT, 'API version'),
            'format'  => new external_value(PARAM_ALPHA, 'Document format (yaml)'),
            'spec'    => new external_value(PARAM_RAW, 'The OpenAPI document'),
        ]);
    }
}
