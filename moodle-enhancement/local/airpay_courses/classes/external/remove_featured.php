<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_courses\featured_manager;

class remove_featured extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Featured row ID'),
        ]);
    }

    public static function execute(int $id): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['id' => $id]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_courses:manage', $context);
        require_sesskey();

        featured_manager::remove((int) $params['id']);
        return ['ok' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Always true'),
        ]);
    }
}
