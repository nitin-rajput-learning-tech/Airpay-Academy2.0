<?php
/**
 * External API for AI assistant — handles AJAX chat requests.
 *
 * @package    local_sentientia_assistant
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_assistant;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

class external extends external_api {

    /**
     * Ask the AI assistant a question.
     */
    public static function ask_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'User question'),
        ]);
    }

    public static function ask(string $query): array {
        global $USER;

        $params = self::validate_parameters(self::ask_parameters(), ['query' => $query]);
        $query = clean_param($params['query'], PARAM_TEXT);

        if (empty(trim($query))) {
            return ['response' => 'Please ask me something!', 'model' => 'validation', 'cached' => false];
        }

        $result = ai_client::ask($USER->id, $query);

        return [
            'response' => format_text($result['response'], FORMAT_MARKDOWN),
            'model'    => $result['model'],
            'cached'   => $result['cached'],
        ];
    }

    public static function ask_returns(): external_single_structure {
        return new external_single_structure([
            'response' => new external_value(PARAM_RAW, 'AI response (HTML)'),
            'model'    => new external_value(PARAM_TEXT, 'Model used'),
            'cached'   => new external_value(PARAM_BOOL, 'Whether response was cached'),
        ]);
    }

    /**
     * Get chat history.
     */
    public static function get_history_parameters(): external_function_parameters {
        return new external_function_parameters([
            'limit' => new external_value(PARAM_INT, 'Max messages', VALUE_DEFAULT, 20),
        ]);
    }

    public static function get_history(int $limit = 20): array {
        global $USER;
        $messages = ai_client::get_history($USER->id, $limit);
        return ['messages' => array_reverse($messages)];
    }

    public static function get_history_returns(): external_single_structure {
        return new external_single_structure([
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT, 'Message ID'),
                    'role'        => new external_value(PARAM_TEXT, 'user or assistant'),
                    'message'     => new external_value(PARAM_RAW, 'Message text'),
                    'model'       => new external_value(PARAM_TEXT, 'Model used'),
                    'timecreated' => new external_value(PARAM_INT, 'Timestamp'),
                ])
            ),
        ]);
    }
}
