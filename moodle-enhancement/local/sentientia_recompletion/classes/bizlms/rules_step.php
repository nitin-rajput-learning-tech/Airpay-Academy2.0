<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * The per-course recompletion settings of BizLMS become one Sentientia rule per course, always DISABLED.
 *
 * local_recompletion_config holds one name/value row per setting per course, so the accounting unit is the
 * course (a derived group, keyed by the course id). Four things matter for what the rule does:
 *
 * - enabled is 0 whatever the legacy 'enable' row said. The Sentientia engine runs every enabled rule with no
 *   other switch, and it deletes completions, grades and quiz attempts. An imported rule starts resetting
 *   nobody until a person turns it on (owner decision recompletion.enable_imported_rules).
 * - costcenterid is 0 (every tenant), as the legacy cron had no tenant filter (owner decision
 *   recompletion.rule_tenant). Only cross-tenant callers see such a rule.
 * - the period is the legacy duration in whole days, rounded up; a course with no usable duration gets the
 *   site default of the legacy plugin, else a year, and the course is reported.
 * - every setting is kept in legacy_config, because the rule cannot express the email templates, the extra
 *   quiz attempt, the archive switches or the assignment, LTI and questionnaire choices.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules_step extends step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.rules';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::RULE_UNIT;
    }

    /**
     * {@inheritdoc}
     */
    public function targettable(): string {
        return sources::RULES;
    }

    /**
     * {@inheritdoc}
     */
    public function group_by(): array {
        return ['course'];
    }

    /**
     * {@inheritdoc}
     */
    public function columns(): array {
        return ['course', 'name', 'value'];
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $rows, context $ctx): array {
        $evidence = evidence::of($ctx);
        // Read so that a value the owner did not accept (or a missing one) stops the feature here too. The
        // values the preflight allows are the only ones this step implements: global, and not enabled.
        $enable = $ctx->decision('recompletion.enable_imported_rules');
        $ctx->decision('recompletion.rule_tenant');

        $course = (int) reset($rows)->course;
        if ($course <= SITEID || !$ctx->lookups->course_exists($course)) {
            // The rule of a deleted course has nothing to run on. The site course never has one.
            return [outcome::skip($course, 'orphan_course', 'course_not_found')];
        }

        // One setting per row; the row with the highest id wins should a name repeat.
        $config = [];
        foreach ($rows as $row) {
            $config[(string) $row->name] = (string) $row->value;
        }

        $warnings = [];
        $days = mapper::period_days($config['recompletionduration'] ?? null);
        if ($days === null) {
            $days = mapper::period_days(evidence::site_duration());
            $warnings[] = 'duration_fallback';
        }

        $shortname = $evidence->course_shortname($course) ?? ('course ' . $course);
        $now = time();
        $rule = (object) [
            'name' => $ctx->text->fit('Legacy recompletion: ' . $shortname, 200, 'name'),
            'courseid' => $course,
            'period_days' => $days,
            'trigger_type' => 'completion',
            'fixed_date' => null,
            'reset_grades' => mapper::switch_on($config['deletegradedata'] ?? null),
            'reset_attempts' => mapper::switch_on($config['quiz'] ?? null),
            'enabled' => $enable === true ? 1 : 0,
            'costcenterid' => 0,
            // The legacy config has no timestamps: the import time is the only one there is.
            'timecreated' => $now,
            'timemodified' => $now,
            'last_run_at' => $evidence->last_reset_of_course($course),
            'last_run_resets' => null,
            'legacy_config' => mapper::payload($config),
        ];
        $outcome = outcome::insert($course, sources::RULES, $rule);
        foreach ($warnings as $warning) {
            $outcome->warn($warning);
        }
        return [$outcome];
    }
}
