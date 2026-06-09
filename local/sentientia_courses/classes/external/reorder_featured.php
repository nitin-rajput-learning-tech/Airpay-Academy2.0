<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_courses\featured_manager;

class reorder_featured extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Featured row ID'),
                'IDs in desired order'),
        ]);
    }

    public static function execute(array $ids): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['ids' => $ids]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_courses:manage', $context);
        require_sesskey();

        $changed = featured_manager::reorder(array_map('intval', $params['ids']));
        return ['changed' => $changed];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'changed' => new external_value(PARAM_INT, 'Rows updated'),
        ]);
    }
}
