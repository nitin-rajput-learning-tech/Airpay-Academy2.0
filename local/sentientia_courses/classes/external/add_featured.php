<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_courses\featured_manager;

class add_featured extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'     => new external_value(PARAM_INT, 'Course ID'),
            'costcenterid' => new external_value(PARAM_INT, '0=all tenants',
                VALUE_DEFAULT, 0),
            'label'        => new external_value(PARAM_TEXT, 'Optional caption',
                VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $courseid, int $costcenterid = 0,
                                    string $label = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'costcenterid', 'label'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_courses:manage', $context);
        require_sesskey();

        $id = featured_manager::add(
            (int) $params['courseid'],
            (int) $params['costcenterid'],
            0,
            $params['label'] !== '' ? (string) $params['label'] : null);
        return ['id' => $id];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Saved row ID'),
        ]);
    }
}
