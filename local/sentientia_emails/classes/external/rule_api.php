<?php
/**
 * AJAX web service API for rule operations.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class rule_api extends external_api {

    public static function toggle_rule_parameters() {
        return new external_function_parameters([
            'ruleid'  => new external_value(PARAM_INT, 'Rule ID'),
            'enabled' => new external_value(PARAM_INT, '1=enabled, 0=disabled'),
        ]);
    }

    public static function toggle_rule(int $ruleid, int $enabled): array {
        $params = self::validate_parameters(self::toggle_rule_parameters(), [
            'ruleid'  => $ruleid,
            'enabled' => $enabled,
        ]);

        $context = \context_system::instance();
        if (!is_siteadmin()) {
            self::validate_context($context);
            require_capability('local/sentientia_emails:manage_rules', $context);
        }

        \local_sentientia_emails\rule_manager::toggle_rule($params['ruleid'], (bool)$params['enabled']);

        return ['success' => true];
    }

    public static function toggle_rule_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
        ]);
    }
}
