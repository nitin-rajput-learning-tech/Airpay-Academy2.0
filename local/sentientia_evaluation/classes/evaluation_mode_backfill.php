<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * The EV-17 back-fill: mark the supervisor evaluations a site already imported before the evaluationmode column existed.
 *
 * Upgrade step 2026100701 added local_sentientia_evaluation.evaluationmode with the default 'SE' and nothing else, which
 * is the whole back-fill for a site that has imported nothing. A site that already ran the BizLMS evaluation import
 * (a rehearsal or a UAT copy) holds imported supervisor forms that read 'SE' after that step, and the plugin no longer
 * guesses from the responses (learner_history, shows_subject), so on such a site the person evaluated would be listed as
 * having "responded" to a form about themselves and the Subject column would disappear. This class sets those forms to
 * 'SP', from the two places that still know which forms were supervisor evaluations:
 *
 *  1. THE OLD SIGNAL: a form with a response that names a subject (responses.subject_userid is not NULL). Only the import
 *     writes that column, and only on a supervisor form, so it is the signal the plugin used until EV-17. It misses an
 *     anonymous supervisor form (the subject is deliberately not kept) and a form whose completions name no evaluator.
 *  2. THE MAP: an imported or adopted form (local_sentientia_legacymap) whose BizLMS row, which the legacy tables keep,
 *     says SP. This catches the two the old signal misses. It runs only when the map and local_evaluations (with its
 *     evaluationmode column) are on this site, and it reads the value the way importer::mode_of() does (case and
 *     surrounding spaces ignored).
 *
 * It only ever changes SE to SP: never SP to SE, never a form the signals do not name, never any other column, row or
 * table. Running it again changes nothing, and it holds no personal data. A native form is untouched: nothing but the
 * import writes a subject or a map row for it.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evaluation_mode_backfill {

    /** The form table. */
    private const FORMS = 'local_sentientia_evaluation';

    /** The response table, whose subject_userid is the old signal. */
    private const RESPONSES = 'local_sentientia_evaluation_responses';

    /** The BizLMS form table the import reads (kept after the import). */
    private const LEGACY_FORMS = 'local_evaluations';

    /** Ids per IN() list. */
    private const CHUNK = 1000;

    /**
     * Mark the forms the two signals name as supervisor evaluations.
     *
     * @return array{subject: int, map: int} How many forms each signal switched from SE to SP (a form the first signal
     *         switched is not counted again by the second).
     */
    public static function apply(): array {
        global $DB;
        $dbman = $DB->get_manager();
        $counts = ['subject' => 0, 'map' => 0];
        if (!$dbman->table_exists(self::FORMS) || !$dbman->field_exists(self::FORMS, 'evaluationmode')) {
            return $counts;
        }

        // 1. Forms with a response that names a subject.
        if ($dbman->table_exists(self::RESPONSES) && $dbman->field_exists(self::RESPONSES, 'subject_userid')) {
            $ids = $DB->get_fieldset_sql('SELECT DISTINCT r.evaluationid FROM {' . self::RESPONSES . '} r
                                           WHERE r.subject_userid IS NOT NULL');
            $counts['subject'] = self::mark_supervisor($ids);
        }

        // 2. Imported forms whose BizLMS row says SP.
        $map = \local_sentientia_platform\bizlms\legacymap::TABLE;
        if ($dbman->table_exists($map) && $dbman->table_exists(self::LEGACY_FORMS)
                && $dbman->field_exists(self::LEGACY_FORMS, 'evaluationmode')) {
            $ids = $DB->get_fieldset_sql(
                'SELECT e.id
                   FROM {' . $map . '} m
                   JOIN {' . self::FORMS . '} e ON e.id = m.targetid
                   JOIN {' . self::LEGACY_FORMS . '} l ON l.id = m.sourceid
                  WHERE m.feature = :feature AND m.sourcetable = :src AND m.targettable = :tgt AND m.subkey = :sk
                    AND m.outcome IN (\'imported\', \'adopted\')
                    AND UPPER(TRIM(l.evaluationmode)) = :sp
                    AND e.evaluationmode <> :spnow',
                ['feature' => bizlms\importer::FEATURE, 'src' => bizlms\importer::SRC_FORMS,
                    'tgt' => bizlms\importer::T_FORMS, 'sk' => '', 'sp' => evaluation_manager::MODE_SUPERVISOR,
                    'spnow' => evaluation_manager::MODE_SUPERVISOR]);
            $counts['map'] = self::mark_supervisor($ids);
        }
        return $counts;
    }

    /**
     * Set the forms to SP where they are not already. timemodified is left alone: this repairs a marker, it is not an
     * edit of the form.
     *
     * @param array $ids Form ids (any type; non-positive ones are ignored).
     * @return int How many forms changed.
     */
    private static function mark_supervisor(array $ids): int {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        $changed = 0;
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'evid');
            $select = "id {$insql} AND evaluationmode <> :sp";
            $params['sp'] = evaluation_manager::MODE_SUPERVISOR;
            $changed += $DB->count_records_select(self::FORMS, $select, $params);
            $DB->set_field_select(self::FORMS, 'evaluationmode', evaluation_manager::MODE_SUPERVISOR, $select, $params);
        }
        return $changed;
    }
}
