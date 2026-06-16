<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent\tool;

use local_sentientia_assistant\agent\tool;
use local_sentientia_assistant\agent\tool_result;
use local_sentientia_assistant\agent\invalid_tool_args;

defined('MOODLE_INTERNAL') || die();

/**
 * Tool: surface gap-closing content for the CURRENT learner (READ-ONLY).
 *
 * This is the "RAG over the learner's catalog/skills/progress" action: it
 * returns a short list of visible courses in the learner's tenant they are
 * NOT yet enrolled in, optionally filtered by a keyword the LLM extracted
 * from the conversation (e.g. a skill the learner wants to close a gap in).
 *
 * It changes no state, so OUTCOME_EXECUTED here means "recommendation
 * produced" with statechanged=false. It is tenant-neutral at the resource
 * level (resource_tenant returns 0), so the base scopes it to the acting
 * user's own tenant and every candidate course is filtered through
 * tenant::path_filter — recommendations never leak across tenants.
 *
 * @package local_sentientia_assistant
 */
class recommend_content extends tool {

    /** Hard cap on recommendations returned. */
    private const MAX_RESULTS = 5;
    /** Hard cap on the keyword length we accept from the LLM. */
    private const MAX_KEYWORD = 80;

    public function name(): string {
        return 'recommend_content';
    }

    public function capability(): string {
        return 'local/sentientia_assistant:recommend';
    }

    public function label(): string {
        return get_string('tool_recommend', 'local_sentientia_assistant');
    }

    public function schema(): array {
        return [
            'name'        => $this->name(),
            'description' => 'Suggest up to 5 courses in the learner\'s tenant they are not enrolled in, '
                           . 'optionally matching a skill/topic keyword. Read-only.',
            'args'        => [
                'keyword' => 'string (optional) — a skill or topic to match course names against',
            ],
        ];
    }

    protected function validate_args(array $rawargs, int $userid): array {
        // Keyword is optional and free-text — clean + bound it. We never
        // build SQL from it directly; it goes through sql_like_escape.
        $keyword = '';
        if (isset($rawargs['keyword']) && is_string($rawargs['keyword'])) {
            $keyword = clean_param($rawargs['keyword'], PARAM_TEXT);
            $keyword = \core_text::substr(trim($keyword), 0, self::MAX_KEYWORD);
        }
        return ['keyword' => $keyword];
    }

    protected function resource_tenant(array $args, int $userid): int {
        // Tenant-neutral: the base scopes us to the acting user's tenant.
        return 0;
    }

    protected function is_noop(array $args, int $userid): bool {
        // Read-only — never a no-op; always produce a fresh recommendation.
        return false;
    }

    protected function execute(array $args, int $userid): tool_result {
        global $DB;

        // Tenant-scope every candidate course via the platform helper.
        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_filter('c', 'open_path', true);

        $params = $tnargs + [
            'userid'  => $userid,
            'userid2' => $userid,
        ];

        $likesql = '';
        if ($args['keyword'] !== '') {
            $escaped = $DB->sql_like_escape($args['keyword']);
            $likesql = ' AND ' . $DB->sql_like('c.fullname', ':kw', false);
            $params['kw'] = '%' . $escaped . '%';
        }

        // Visible courses (excluding the site course) in this tenant that
        // the learner is NOT already enrolled in.
        $sql = "SELECT c.id, c.fullname, c.shortname
                  FROM {course} c
                 WHERE c.visible = 1
                   AND c.id > 1
                   AND {$tnsql}
                   {$likesql}
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {user_enrolments} ue
                         JOIN {enrol} e ON e.id = ue.enrolid
                        WHERE e.courseid = c.id
                          AND ue.userid = :userid
                   )
              ORDER BY c.fullname ASC";

        $rows = $DB->get_records_sql($sql, $params, 0, self::MAX_RESULTS);

        if (empty($rows)) {
            return new tool_result(
                tool_result::OUTCOME_EXECUTED,
                get_string('tool_recommend_none', 'local_sentientia_assistant'),
                false
            );
        }

        // Build a localised, escaped list. format_string sanitises names.
        $names = [];
        foreach ($rows as $r) {
            $names[] = '• ' . format_string($r->fullname);
        }
        $message = get_string('tool_recommend_intro', 'local_sentientia_assistant')
            . "\n" . implode("\n", $names);

        return new tool_result(
            tool_result::OUTCOME_EXECUTED,
            $message,
            false
        );
    }
}
