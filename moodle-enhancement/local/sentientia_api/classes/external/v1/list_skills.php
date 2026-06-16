<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * v1: GET /skills — the skill catalogue.
 *
 * Skills are global definitions (no per-tenant scoping in the skills
 * plugin's schema), so this endpoint surfaces the catalogue read-only.
 * Gracefully degrades to an empty list when local_sentientia_skills is
 * not installed.
 *
 * @package local_sentientia_api
 */
class list_skills extends base {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, 'Free-text filter on skill name', VALUE_DEFAULT, ''),
            'page'    => new external_value(PARAM_INT,  'Zero-based page index', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,  'Page size (max 200)', VALUE_DEFAULT, 50),
        ]);
    }

    /**
     * @param string $search
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function execute(string $search = '', int $page = 0, int $perpage = 50): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'page', 'perpage'));

        self::open_v1('local_sentientia_api_v1_list_skills', 'local/sentientia_api:read');

        $page = max(0, $params['page']);
        $perpage = min(200, max(1, $params['perpage']));

        if (!$DB->get_manager()->table_exists('local_sentientia_skills')) {
            return ['total' => 0, 'skills' => []];
        }

        $where = ['1=1'];
        $args = [];
        if ($params['search'] !== '') {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = $DB->sql_like('s.name', ':s1', false);
            $args['s1'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(s.id) FROM {local_sentientia_skills} s WHERE $wheresql", $args);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT s.id, s.name, s.max_level
                   FROM {local_sentientia_skills} s
                  WHERE $wheresql
               ORDER BY s.name ASC, s.id ASC",
                $args, $page * $perpage, $perpage);
            foreach ($records as $s) {
                $rows[] = [
                    'id'        => (int) $s->id,
                    'name'      => format_string($s->name),
                    'max_level' => (int) $s->max_level,
                ];
            }
        }

        return ['total' => $total, 'skills' => $rows];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'  => new external_value(PARAM_INT, 'Total matching skills'),
            'skills' => new external_multiple_structure(
                new external_single_structure([
                    'id'        => new external_value(PARAM_INT,  'Skill id'),
                    'name'      => new external_value(PARAM_TEXT, 'Skill name'),
                    'max_level' => new external_value(PARAM_INT,  'Maximum proficiency level'),
                ])
            ),
        ]);
    }
}
