<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

require_once($CFG->libdir . '/externallib.php');

/**
 * WS: list active boards the caller can view.
 *
 * Tenant-scoped unless the caller has :viewall. Always filters out
 * status != 'active' to keep the response small for block render.
 *
 * @package local_sentientia_leaderboard
 */
class list_boards extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHA,
                'Filter by type (quiz|completion|skill) — empty = all',
                VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $type = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['type' => $type]);
        $type = (string) $params['type'];

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_leaderboard:view', $context);

        if (class_exists('\\local_sentientia_platform\\feature_flags')) {
            if (!\local_sentientia_platform\feature_flags::is_enabled(
                    'sentientia.leaderboards.enabled')) {
                return ['boards' => []];
            }
        }

        $can_view_all = has_capability(
            'local/sentientia_leaderboard:viewall', $context);
        $viewer_root = \local_sentientia_platform\tenant::root_for_current_user();

        $filters = [];
        if ($type !== '' && in_array($type,
                \local_sentientia_leaderboard\board_manager::VALID_TYPES, true)) {
            $filters['type'] = $type;
        }

        $rows = \local_sentientia_leaderboard\board_manager::list_visible(
            $viewer_root, $can_view_all, $filters);

        // Per-type ship gates: if a type's flag is OFF, hide boards of
        // that type from the listing.
        $type_flags = [
            'quiz'       => 'sentientia.leaderboards.type.quiz',
            'completion' => 'sentientia.leaderboards.type.completion',
            'skill'      => 'sentientia.leaderboards.type.skill',
        ];
        $flags_class = class_exists('\\local_sentientia_platform\\feature_flags')
            ? '\\local_sentientia_platform\\feature_flags' : null;

        $out = [];
        foreach ($rows as $r) {
            $flag = $type_flags[$r->type] ?? null;
            if ($flag && $flags_class && !$flags_class::is_enabled($flag)) {
                continue;
            }
            $out[] = [
                'id'              => (int) $r->id,
                'name'            => (string) $r->name,
                'type'            => (string) $r->type,
                'scope'           => (string) $r->scope,
                'last_recomputed' => (int) $r->last_recomputed,
            ];
        }
        return ['boards' => $out];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'boards' => new external_multiple_structure(
                new external_single_structure([
                    'id'              => new external_value(PARAM_INT, 'Board ID'),
                    'name'            => new external_value(PARAM_TEXT, 'Board name'),
                    'type'            => new external_value(PARAM_ALPHA, 'Type'),
                    'scope'           => new external_value(PARAM_ALPHA, 'Scope'),
                    'last_recomputed' => new external_value(PARAM_INT, 'Unix ts'),
                ])
            ),
        ]);
    }
}
