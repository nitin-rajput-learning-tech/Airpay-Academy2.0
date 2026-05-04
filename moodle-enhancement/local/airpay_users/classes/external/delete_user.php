<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_airpay_users\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: delete a user (soft delete via Moodle's delete_user).
 *
 * @package    local_airpay_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_user extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID to delete'),
        ]);
    }

    public static function execute(int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['userid' => $userid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_users:delete', $context);

        $success = \local_airpay_users\user_manager::delete($params['userid']);

        return [
            'userid'  => $params['userid'],
            'success' => $success,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid'  => new external_value(PARAM_INT, 'User ID'),
            'success' => new external_value(PARAM_BOOL, 'Whether delete succeeded'),
        ]);
    }
}
