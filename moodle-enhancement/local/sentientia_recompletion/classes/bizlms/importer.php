<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer as platform_importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * ADR-032: the BizLMS (community local_recompletion 2023012600) recompletion data, imported.
 *
 * What the legacy plugin leaves behind is compliance evidence: when each person completed a mandatory course,
 * when it was reset, and what the reset deleted (completion state, criteria, activity completions, quiz
 * attempts and grades, SCORM tracking, LTI grades, questionnaire responses). A reset deletes the live rows, so
 * for every cycle before the current one these tables are the only proof that it was completed. The import
 * keeps ALL of it:
 *
 * - local_recompletion_config -> one Sentientia rule per course, always disabled (rules_step);
 * - the completion_reset rows of the standard log -> one history row per reset (events_step);
 * - the archived completions -> archive rows, plus an inferred history row for a cycle whose reset is not in
 *   the log (course_completion_step);
 * - the other fourteen archive tables -> archive rows with the source row as a JSON payload;
 * - two recompute steps attach every archive row to the reset that ended its cycle and give an inferred
 *   reset the real time if the log holds it.
 *
 * Nothing acts on its own: no rule is enabled, no reset runs, no message is sent (the static scan of
 * classes/bizlms/ and the runtime tripwire check both). The log is a live core table, not a legacy table, so
 * it is claimed as a source only while the legacy tables exist.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements platform_importer {

    /** Plugin version that carries the schema this importer writes to (archive table, history.source). */
    private const REQUIRES_VERSION = 2026093001;

    /**
     * Values of the enum columns the import depends on. A value outside the list blocks the feature until the
     * decisions file maps it (mapping doc R8).
     *
     * @var array<string, array<string, array<string|int, string>>>
     */
    private const ENUMS = [
        sources::CMC => ['completionstate' => [0 => 'incomplete', 1 => 'complete', 2 => 'complete_pass', 3 => 'complete_fail']],
        sources::QA => ['state' => ['inprogress' => 'in progress', 'overdue' => 'overdue', 'finished' => 'finished',
            'abandoned' => 'abandoned']],
        sources::QR => ['complete' => ['y' => 'complete', 'n' => 'incomplete']],
        sources::QR_BOOL => ['choice_id' => ['y' => 'yes', 'n' => 'no']],
    ];

    /**
     * {@inheritdoc}
     */
    public function feature(): string {
        return sources::FEATURE;
    }

    /**
     * {@inheritdoc}
     */
    public function component(): string {
        return 'local_sentientia_recompletion';
    }

    /**
     * {@inheritdoc}
     */
    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    /**
     * {@inheritdoc}
     */
    public function depends(): array {
        // Rules are global and the history and archive carry no tenant path, so nothing here resolves an
        // organisation and nothing reads another feature's map.
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function sources(): array {
        $sources = [];
        foreach (sources::legacy_tables() as $table) {
            $sources[$table] = new source_spec($table, true, self::ENUMS[$table] ?? []);
        }
        if ($this->log_is_claimed()) {
            $sources[sources::LOG] = new source_spec(sources::LOG, true);
        }
        return $sources;
    }

    /**
     * {@inheritdoc}
     */
    public function declined_tables(): array {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function target_tables(): array {
        return [sources::RULES, sources::HISTORY, sources::ARCHIVE];
    }

    /**
     * {@inheritdoc}
     */
    public function core_writes(): array {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function tenant_columns(): array {
        // A rule's costcenterid is a tenant root number (0 = every tenant), not a path; the history and archive
        // rows are scoped at read time by the learner's current open_path.
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function reasons(): array {
        return [
            // The rule of a course that no longer exists has nothing to run on.
            new reason('orphan_course', false, false),
            // A row about a person the restored database has no user row for. Evidence nobody can attach to a
            // person is reported to the owner, not dropped in silence.
            new reason('orphan_user', false, true),
            // An answer whose questionnaire response was not imported.
            new reason('orphan_response', false, true),
            // A log row of a reset that names no learner or no course.
            new reason('incomplete_event', false, true),
            // A teacher's preview attempt: not learner history (owner decision recompletion.preview_attempts).
            new reason('preview_attempt', false, false),
            // A log row folded into the inferred history row an earlier run made for the same cycle.
            new reason('matched_inferred', false, false),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function decisions(): array {
        return [
            new decision('recompletion.rule_tenant', 'Tenant of the imported rules', true, null, ['global']),
            new decision('recompletion.enable_imported_rules', 'Enable the imported rules at cutover', true, null, [false]),
            new decision('recompletion.preview_attempts', 'Import teacher-preview quiz attempts', true, null,
                ['skip', 'import']),
            new decision('recompletion.archive_shape', 'Shape of the evidence archive', true, null,
                ['generic_json_payload']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function atomic(): bool {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function steps(): array {
        $steps = [new rules_step()];
        if ($this->log_is_claimed()) {
            $steps[] = new events_step();
        }
        array_push($steps, new course_completion_step(), new criteria_step(), new activity_step(),
            new quiz_attempt_step(), new quiz_grade_step(), new scorm_step(), new lti_step(), new response_step());
        foreach (array_keys(answer_step::TABLES) as $kind) {
            $steps[] = new answer_step($kind);
        }
        // After every load step. The upgrade of an inferred reset comes first: it moves a history row's time,
        // which decides the cycle an archive row belongs to.
        $steps[] = new inferred_resets();
        $steps[] = new archive_cycles();
        return $steps;
    }

    /**
     * {@inheritdoc}
     */
    public function preflight(context $ctx): preflight {
        global $DB;
        $pf = new preflight();
        $dbman = $DB->get_manager();

        // The schema this importer writes to (the plugin version says so too; this says which part is missing).
        if (!$dbman->table_exists(sources::ARCHIVE)) {
            $pf->block('missing_target_table:' . sources::ARCHIVE);
        }
        foreach ([[sources::HISTORY, 'source'], [sources::HISTORY, 'time_inferred'], [sources::RULES, 'legacy_config']] as [$table, $column]) {
            if ($dbman->table_exists($table) && !array_key_exists($column, $DB->get_columns($table))) {
                $pf->block('missing_target_column:' . $table . '.' . $column);
            }
        }

        if ($ctx->legacy->exists(sources::CC)) {
            $completions = $ctx->legacy->count(sources::CC);
            $resets = $ctx->legacy->exists(sources::LOG) ? $ctx->legacy->count(sources::LOG, sources::reset_filter()) : 0;
            $pf->count('archived_completions', $completions);
            $pf->count('reset_events', $resets);
            if ($resets < $completions) {
                // At most this many cycles have no reset in the log (a reset event can also be for a learner
                // with no archived completion); each gets an inferred history row.
                $pf->warn('cycles_without_a_logged_reset_at_most:' . ($completions - $resets));
            }
        }
        if ($ctx->legacy->exists(sources::CONFIG)) {
            // value is a TEXT column: compare it the portable way.
            $enabled = $ctx->legacy->count(sources::CONFIG,
                ["t.name = 'enable' AND " . $DB->sql_compare_text('t.value') . " = '1'", []]);
            $pf->count('legacy_rules_enabled', $enabled);
            if ($enabled > 0) {
                $pf->warn('legacy_rules_were_enabled_all_imported_disabled:' . $enabled);
            }
        }
        return $pf;
    }

    /**
     * {@inheritdoc}
     */
    public function verify(context $ctx): array {
        global $DB;
        $failures = [];

        // The two derived accounting units: the framework does not count them, so count them here.
        if ($ctx->legacy->exists(sources::CONFIG)) {
            $courses = (int) $DB->get_field_sql('SELECT COUNT(DISTINCT course) FROM {' . sources::CONFIG . '}');
            $mapped = $DB->count_records(legacymap::TABLE, ['sourcetable' => sources::RULE_UNIT, 'subkey' => '']);
            if ($courses !== $mapped) {
                $failures[] = 'accounting:' . sources::RULE_UNIT . ': source=' . $courses . ' mapped=' . $mapped;
            }
        }
        if ($this->log_is_claimed()) {
            $events = $ctx->legacy->count(sources::LOG, sources::reset_filter());
            $mapped = $DB->count_records(legacymap::TABLE, ['sourcetable' => sources::EVENT_UNIT, 'subkey' => '']);
            if ($events !== $mapped) {
                $failures[] = 'accounting:' . sources::EVENT_UNIT . ': source=' . $events . ' mapped=' . $mapped;
            }
        }

        // An answer points at a response row that is in the archive.
        $dangling = (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . sources::ARCHIVE . '} a
              WHERE a.parentid IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM {' . sources::ARCHIVE . '} p WHERE p.id = a.parentid)');
        if ($dangling > 0) {
            $failures[] = 'archive_answers_without_a_response:' . $dangling;
        }

        // A reset imported from the log is never in the future (an inferred one is capped at the import time),
        // and never a dry-run row.
        $bad = (int) $DB->count_records_select(sources::HISTORY, 'source = :blmsource AND (timecreated > :blmnow OR dryrun <> 0)',
            ['blmsource' => sources::LEGACY, 'blmnow' => time() + 300]);
        if ($bad > 0) {
            $failures[] = 'imported_history_in_the_future_or_dry_run:' . $bad;
        }
        return $failures;
    }

    /**
     * {@inheritdoc}
     */
    public function finalise(context $ctx): void {
        // Nothing to do: every target id is new (MAP), so there is no sequence to reset.
    }

    /**
     * Is the standard log claimed as a source? Only while a legacy recompletion table exists: the log is a
     * live core table present on every site, and claiming it on a site that never had BizLMS recompletion
     * would make the feature "applicable" there.
     *
     * @return bool
     */
    private function log_is_claimed(): bool {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (sources::legacy_tables() as $table) {
            if ($dbman->table_exists($table)) {
                return true;
            }
        }
        return false;
    }
}
