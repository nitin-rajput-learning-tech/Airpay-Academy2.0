<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * Evaluation manager — CRUD for evaluation forms (containers).
 *
 * Questions and responses live in companion tables; their CRUD will be
 * added in a follow-up. For now this manages the form-level metadata.
 *
 * @package    local_sentientia_evaluation
 */
class evaluation_manager {

    private const TABLE          = 'local_sentientia_evaluation';
    private const QUESTIONS_TABLE = 'local_sentientia_evaluation_questions';
    private const RESPONSES_TABLE = 'local_sentientia_evaluation_responses';
    // P1 #37 (2026-05-20) — assignments table.
    private const ASSIGN_TABLE   = 'local_sentientia_evaluation_assign';
    // P1 #41 (2026-05-20) — template library.
    private const TEMPLATE_TABLE = 'local_sentientia_evaluation_template';
    // W1-5 — the trigger queue (delete() now clears its rows too).
    private const TRIGGERS_TABLE = 'local_sentientia_evaluation_triggers';

    /**
     * A numeric question whose allowed range spans at most this many steps (max - min) keeps a count per value, so
     * the admin view can draw bars (BizLMS "1 to 5" items). A wider range, or none, shows the average only.
     */
    public const NUMERIC_DISTRIBUTION_SPAN = 10;

    /** local_sentientia_evaluation.evaluationmode: a self evaluation (every native form) or a supervisor evaluation (EV-17). */
    public const MODE_SELF = 'SE';
    public const MODE_SUPERVISOR = 'SP';

    /** The default-OFF flag behind the individual responses pages (EV-06, db/feature_flags.php). */
    public const FLAG_RESPONSE_DRILLDOWN = 'sentientia.evaluation.response_drilldown';

    /** Status values matching install.xml. */
    public const STATUS_DRAFT    = 0;
    public const STATUS_ACTIVE   = 1;
    public const STATUS_ARCHIVED = 2;

    /** Kirkpatrick evaluation levels. */
    public const KIRKPATRICK_LEVELS = [
        1 => 'Level 1 — Reaction (did learners enjoy it?)',
        2 => 'Level 2 — Learning (did they learn the content?)',
        3 => 'Level 3 — Behaviour (did they apply it on the job?)',
        4 => 'Level 4 — Results (did business outcomes change?)',
    ];

    /** Trigger events that fire the evaluation. */
    public const TRIGGER_EVENTS = [
        'manual'              => 'Manual — admin sends to specific users',
        'course_completion'   => 'After course completion',
        'program_completion'  => 'After program completion',
        'classroom_end'       => 'After classroom session ends',
    ];

    public static function get(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ADR-032 (2026-09-30): imported history is read-only
    //
    // A form the BizLMS import brought over (its map row says imported or
    // adopted) is a record of what was asked and answered, not a live form:
    // editing it, re-opening it or deleting it would rewrite or destroy that
    // record (decision evaluation.imported_forms_read_only). The import
    // itself never goes through this class. To run the same questions again,
    // export the form as a template and create a new evaluation from it.
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Did the BizLMS import create this evaluation?
     *
     * @param int $evaluationid
     * @return bool False for a native evaluation, and on a site whose platform has no import framework.
     */
    public static function is_imported(int $evaluationid): bool {
        if ($evaluationid <= 0 || !class_exists(\local_sentientia_platform\bizlms\provenance::class)) {
            return false;
        }
        return \local_sentientia_platform\bizlms\provenance::is_imported(self::TABLE, $evaluationid);
    }

    /**
     * Which of these evaluations did the BizLMS import create? The same test as is_imported(), for a whole page of
     * the admin list in one query.
     *
     * @param int[] $evaluationids
     * @return array<int, true> The ids that are imported or adopted, as keys.
     */
    public static function imported_ids(array $evaluationids): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $evaluationids), static fn(int $i): bool => $i > 0)));
        if (!$ids || !class_exists(\local_sentientia_platform\bizlms\legacymap::class)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'impid');
        $params['imptable'] = self::TABLE;
        $found = $DB->get_fieldset_select(\local_sentientia_platform\bizlms\legacymap::TABLE, 'targetid',
            "targettable = :imptable AND targetid {$insql} AND outcome IN ('imported', 'adopted')", $params);
        $imported = array_fill_keys(array_map('intval', $found), true);
        ksort($imported);
        return $imported;
    }

    /**
     * Refuse to change an evaluation the import created.
     *
     * @param int $evaluationid
     * @throws \moodle_exception error_imported_form_read_only
     */
    public static function assert_not_imported(int $evaluationid): void {
        if (self::is_imported($evaluationid)) {
            throw new \moodle_exception('error_imported_form_read_only', 'local_sentientia_evaluation');
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // ADR-031 tenant scope (2026-09-25)
    //
    // :manage says WHAT a caller may do, never WHERE. Every holder is a
    // tenant admin (a manager-archetype role at system context) unless
    // tenant::is_cross_tenant() says otherwise. Until 2026-09-25 any holder
    // could read any tenant's respondents, answers and aggregates by id,
    // delete or re-scope any tenant's evaluation, and create a "global"
    // (costcenterid 0) evaluation that evaluation_engine sends to every
    // tenant's learners - then export their answers.
    //
    // Deliberately NOT inside create()/update()/delete(): the CLI smoke
    // scripts drive those without a session user. Every web entry point
    // (pages, web services, dynamic forms) calls the gates below.
    // ═══════════════════════════════════════════════════════════════════

    /**
     * ADR-031: may the current user manage (read results of, edit, delete)
     * this evaluation?
     *
     * Cross-tenant callers always may. Anyone else only when the evaluation
     * is bound to an org inside their own tenant. A global evaluation
     * (costcenterid 0 - evaluation_engine matches it to every user in every
     * tenant) or one with no open_path cannot be shown to be in anyone's
     * tenant, so it is left to cross-tenant callers.
     *
     * @param \stdClass $evaluation record carrying costcenterid and open_path
     * @return bool
     */
    public static function can_manage_evaluation(\stdClass $evaluation): bool {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return true;
        }
        $path = rtrim(trim((string) ($evaluation->open_path ?? '')), '/');
        if ((int) ($evaluation->costcenterid ?? 0) === 0 || $path === '') {
            return false;
        }
        return self::path_in_root($path, \local_sentientia_platform\tenant::root_for_current_user());
    }

    /**
     * ADR-031: refuse unless can_manage_evaluation().
     *
     * @param \stdClass $evaluation
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_evaluation_access(\stdClass $evaluation): void {
        if (!self::can_manage_evaluation($evaluation)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
    }

    /**
     * ADR-031: load an evaluation by id and refuse unless can_manage_evaluation().
     *
     * @param int $evaluationid
     * @return \stdClass the evaluation record
     * @throws \moodle_exception invalidevaluation / error_outoftenant
     */
    public static function require_evaluation_access_by_id(int $evaluationid): \stdClass {
        $evaluation = self::get($evaluationid);
        if (!$evaluation) {
            throw new \moodle_exception('invalidevaluation', 'local_sentientia_evaluation');
        }
        self::require_evaluation_access($evaluation);
        return $evaluation;
    }

    /**
     * ADR-031: resolve a question to its evaluation and refuse unless the
     * caller may manage that evaluation.
     *
     * @param int $questionid
     * @return \stdClass the question record
     * @throws \moodle_exception invalidquestion / error_outoftenant
     */
    public static function require_question_access(int $questionid): \stdClass {
        $question = self::get_question($questionid);
        if (!$question) {
            throw new \moodle_exception('invalidquestion', 'local_sentientia_evaluation');
        }
        self::require_evaluation_access_by_id((int) $question->evaluationid);
        return $question;
    }

    /**
     * ADR-031: may this user RESPOND to this evaluation?
     *
     * A global evaluation (costcenterid 0) is sent to everyone, so anyone may
     * answer it. A tenant-bound one only by a user of that tenant: a learner
     * could otherwise post answers into (and trigger admin notifications on)
     * another tenant's evaluation by id. Cross-tenant users always may.
     *
     * @param \stdClass $evaluation
     * @param \stdClass $user carrying id and open_path
     * @return bool
     */
    public static function can_respond(\stdClass $evaluation, \stdClass $user): bool {
        if ((int) ($evaluation->costcenterid ?? 0) === 0
                || \local_sentientia_platform\tenant::is_cross_tenant((int) ($user->id ?? 0))) {
            return true;
        }
        $path = rtrim(trim((string) ($evaluation->open_path ?? '')), '/');
        if ($path === '') {
            // Tenant-bound but pathless: evaluation_engine treats it as '/<costcenterid>'.
            $path = '/' . (int) $evaluation->costcenterid;
        }
        $evaluationroot = (int) (explode('/', trim($path, '/'))[0] ?? 0);
        $userroot = \local_sentientia_platform\tenant::root_for_user($user);
        return $userroot > 0 && $evaluationroot === $userroot;
    }

    /**
     * ADR-031: the costcenterid (org id) a create/update/import may write.
     *
     * Cross-tenant callers keep the old behaviour, including 0 = a global
     * evaluation. A scoped caller may only pick an org inside their own
     * tenant, and 0 gives them their own tenant root org instead of a global
     * evaluation delivered to every tenant. A scoped caller with no tenant,
     * or whose tenant has no org row, writes nothing.
     *
     * @param int $orgid local_sentientia_org.id, or 0
     * @return int
     * @throws \moodle_exception error_outoftenant
     */
    public static function scoped_costcenterid(int $orgid): int {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return $orgid;
        }
        $root = \local_sentientia_platform\tenant::root_for_current_user();
        if ($root <= 0) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        if ($orgid <= 0) {
            $rows = $DB->get_records('local_sentientia_org', ['path' => '/' . $root],
                'depth ASC, id ASC', 'id, path', 0, 1);
            $org = reset($rows);
            if (!$org) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
            return (int) $org->id;
        }
        $org = $DB->get_record('local_sentientia_org', ['id' => $orgid], 'id, path');
        $path = $org ? rtrim(trim((string) $org->path), '/') : '';
        if (!self::path_in_root($path, $root)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return $orgid;
    }

    /**
     * ADR-031: org options for the evaluation form. Cross-tenant callers get
     * every org plus "No specific organisation" (a global evaluation);
     * anyone else only their own tenant's orgs (none without a tenant).
     *
     * @return array<int, string> org id => indented name
     */
    public static function org_options(): array {
        global $DB;
        $options = [];
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            $options[0] = '— No specific organisation —';
            $orgs = $DB->get_records('local_sentientia_org', ['visible' => 1],
                'depth ASC, fullname ASC', 'id, fullname, depth');
        } else {
            [$tsql, $targs] = \local_sentientia_platform\tenant::path_filter('', 'path');
            $orgs = $DB->get_records_select('local_sentientia_org', "visible = 1 AND {$tsql}",
                $targs, 'depth ASC, fullname ASC', 'id, fullname, depth');
        }
        foreach ($orgs as $o) {
            $indent = str_repeat('— ', max(0, (int) $o->depth - 1));
            $options[(int) $o->id] = $indent . format_string($o->fullname);
        }
        return $options;
    }

    /**
     * ADR-031: evaluation count for the index KPI tiles, scoped like
     * list_evaluations (the tiles used to count every tenant's forms).
     *
     * @param int|null $status STATUS_* filter, or null for all
     * @return int
     */
    public static function count_evaluations_scoped(?int $status = null): int {
        global $DB;
        if (!$DB->get_manager()->table_exists(self::TABLE)) {
            return 0;
        }
        [$tsql, $params] = self::scope_sql('e');
        $where = $tsql;
        if ($status !== null) {
            $where .= ' AND e.status = :evstatus';
            $params['evstatus'] = $status;
        }
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {" . self::TABLE . "} e WHERE {$where}", $params);
    }

    /**
     * ADR-031: response count for the index KPI tile, over the evaluations
     * the caller may see.
     *
     * @return int
     */
    public static function count_responses_scoped(): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::RESPONSES_TABLE) || !$dbman->table_exists(self::TABLE)) {
            return 0;
        }
        [$tsql, $params] = self::scope_sql('e');
        // Submitted responses only: the trigger queue's pending shell (timesubmitted 0) is an invitation.
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {" . self::RESPONSES_TABLE . "} r
               JOIN {" . self::TABLE . "} e ON e.id = r.evaluationid
              WHERE r.timesubmitted > 0 AND {$tsql}", $params);
    }

    /**
     * ADR-031: WHERE fragment for the evaluations a caller may see, on
     * evaluation alias $alias. Cross-tenant: 1=1. Anyone else: their own
     * tenant's evaluations (tenant::path_filter, 1=0 without a tenant),
     * never a global one - costcenterid 0 reaches every tenant's learners
     * whatever its open_path says (evaluation_engine::is_user_in_eval_scope).
     *
     * @param string $alias evaluation table alias
     * @return array{0: string, 1: array}
     */
    public static function scope_sql(string $alias): array {
        [$tsql, $params] = \local_sentientia_platform\tenant::path_filter($alias);
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return [$tsql, $params];
        }
        return ["({$tsql} AND {$alias}.costcenterid <> 0)", $params];
    }

    /** Is $path tenant $root itself or inside it ('/N' or '/N/...')? */
    private static function path_in_root(string $path, int $root): bool {
        $path = rtrim(trim($path), '/');
        if ($root <= 0 || $path === '') {
            return false;
        }
        $exact = '/' . $root;
        return $path === $exact || strpos($path, $exact . '/') === 0;
    }

    public static function count_evaluations(?int $status = null): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::TABLE)) return 0;
        if ($status === null) {
            return $DB->count_records(self::TABLE);
        }
        return $DB->count_records(self::TABLE, ['status' => $status]);
    }

    /**
     * How many responses have been SUBMITTED (for one evaluation, or for all).
     *
     * The pending "shell" row evaluation_engine writes when a trigger fires (timesubmitted 0, response_data '{}')
     * is an invitation, not a response, so it is not counted (until 2026-10-01 it was, and every invited user
     * inflated the "Total Responses" tile before answering anything).
     *
     * @param int|null $evaluationid null = every evaluation
     * @return int
     */
    public static function count_responses(?int $evaluationid = null): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::RESPONSES_TABLE)) return 0;
        if ($evaluationid !== null) {
            return $DB->count_records_select(self::RESPONSES_TABLE,
                'evaluationid = :eid AND timesubmitted > 0', ['eid' => $evaluationid]);
        }
        return $DB->count_records_select(self::RESPONSES_TABLE, 'timesubmitted > 0');
    }

    public static function count_questions(int $evaluationid): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::QUESTIONS_TABLE)) return 0;
        return $DB->count_records(self::QUESTIONS_TABLE, ['evaluationid' => $evaluationid]);
    }

    /**
     * Create an evaluation form.
     */
    public static function create(object $data): int {
        global $DB;

        if (empty($data->name)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_evaluation');
        }

        $level = (int) ($data->kirkpatrick_level ?? 1);
        if (!array_key_exists($level, self::KIRKPATRICK_LEVELS)) {
            throw new \moodle_exception('invalidkirkpatricklevel', 'local_sentientia_evaluation');
        }

        $trigger = $data->trigger_event ?? 'manual';
        if (!array_key_exists($trigger, self::TRIGGER_EVENTS)) {
            throw new \moodle_exception('invalidtrigger', 'local_sentientia_evaluation');
        }

        // P1 #17 (2026-05-16) — time window + multiple-submit. Default 0 (no
        // constraint) so existing callers and import paths keep working
        // unchanged.
        $timeopen        = max(0, (int) ($data->timeopen        ?? 0));
        $timeclose       = max(0, (int) ($data->timeclose       ?? 0));
        $multiple_submit = isset($data->multiple_submit) ? (int) $data->multiple_submit : 0;

        // Reject obvious misconfiguration where the window is inverted.
        if ($timeopen > 0 && $timeclose > 0 && $timeclose < $timeopen) {
            throw new \moodle_exception('eval_window_inverted', 'local_sentientia_evaluation');
        }

        // P1 #19 — opt-in admin notification on every response.
        $notify_admin = isset($data->notify_admin_on_response)
            ? (int) $data->notify_admin_on_response : 0;

        $record = (object) [
            'name'                     => trim($data->name),
            'description'              => $data->description ?? '',
            'kirkpatrick_level'        => $level,
            'trigger_event'            => $trigger,
            'days_after'               => max(0, (int) ($data->days_after ?? 0)),
            'costcenterid'             => (int) ($data->costcenterid ?? 0),
            'status'                   => (int) ($data->status ?? self::STATUS_DRAFT),
            'anonymous'                => isset($data->anonymous) ? (int) $data->anonymous : 0,
            'timeopen'                 => $timeopen,
            'timeclose'                => $timeclose,
            'multiple_submit'          => $multiple_submit,
            'notify_admin_on_response' => $notify_admin,
            'timecreated'              => time(),
            'timemodified'             => time(),
        ];

        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
            if ($org) {
                $record->open_path = $org->path;
            }
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        self::assert_not_imported($id);
        $record = (object) ['id' => $id, 'timemodified' => time()];

        if (isset($data->name))         $record->name = trim($data->name);
        if (isset($data->description))  $record->description = $data->description;
        if (isset($data->kirkpatrick_level)) {
            $level = (int) $data->kirkpatrick_level;
            if (!array_key_exists($level, self::KIRKPATRICK_LEVELS)) {
                throw new \moodle_exception('invalidkirkpatricklevel', 'local_sentientia_evaluation');
            }
            $record->kirkpatrick_level = $level;
        }
        if (isset($data->trigger_event)) {
            if (!array_key_exists($data->trigger_event, self::TRIGGER_EVENTS)) {
                throw new \moodle_exception('invalidtrigger', 'local_sentientia_evaluation');
            }
            $record->trigger_event = $data->trigger_event;
        }
        if (isset($data->days_after))   $record->days_after = max(0, (int) $data->days_after);
        if (isset($data->costcenterid)) $record->costcenterid = (int) $data->costcenterid;
        if (isset($data->status))       $record->status = (int) $data->status;
        if (isset($data->anonymous)) {
            // 2026-09-25: anonymity is a promise made to the people who have
            // already answered. Once responses exist it cannot be withdrawn:
            // unticking it used to reopen the Responded tab (names, emails
            // and the minute each person responded) next to their anonymous
            // answers. Every "=== 1" check reads any other value as "not
            // anonymous", so the stored value is normalised to 0/1.
            $anonymous = ((int) $data->anonymous === 1) ? 1 : 0;
            if ($anonymous === 0 && (int) ($existing->anonymous ?? 0) === 1
                    && self::has_submitted_responses($id)) {
                throw new \moodle_exception('error_anonymity_locked', 'local_sentientia_evaluation');
            }
            $record->anonymous = $anonymous;
        }

        // P1 #17 — time-window + multiple-submit fields.
        if (property_exists($data, 'timeopen')) {
            $record->timeopen = max(0, (int) $data->timeopen);
        }
        if (property_exists($data, 'timeclose')) {
            $record->timeclose = max(0, (int) $data->timeclose);
        }
        if (property_exists($data, 'multiple_submit')) {
            $record->multiple_submit = (int) $data->multiple_submit;
        }
        if (property_exists($data, 'notify_admin_on_response')) {
            $record->notify_admin_on_response = (int) $data->notify_admin_on_response;
        }

        // Validate window post-merge (compare against existing for fields
        // the caller didn't touch).
        $effective_open  = $record->timeopen  ?? (int) ($existing->timeopen  ?? 0);
        $effective_close = $record->timeclose ?? (int) ($existing->timeclose ?? 0);
        if ($effective_open > 0 && $effective_close > 0
                && $effective_close < $effective_open) {
            throw new \moodle_exception('eval_window_inverted', 'local_sentientia_evaluation');
        }

        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            $org = $DB->get_record('local_sentientia_org', ['id' => $record->costcenterid]);
            $record->open_path = $org ? $org->path : '';
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * P1 #17 — Is the evaluation currently within its availability window?
     *
     * Returns true if:
     *   - timeopen  == 0 OR now >= timeopen   AND
     *   - timeclose == 0 OR now <  timeclose
     *
     * Pure function — does not check status (the caller still needs to
     * confirm STATUS_ACTIVE). Pass `now` for deterministic tests.
     */
    public static function is_open_now(object $eval, int $now = 0): bool {
        if ($now <= 0) {
            $now = time();
        }
        $open  = (int) ($eval->timeopen  ?? 0);
        $close = (int) ($eval->timeclose ?? 0);
        if ($open  > 0 && $now < $open)  return false;
        if ($close > 0 && $now >= $close) return false;
        return true;
    }

    public static function change_status(int $id, int $status): int {
        global $DB;
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
            throw new \moodle_exception('invalidstatus', 'local_sentientia_evaluation');
        }
        self::assert_not_imported($id);
        $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'status' => $status,
            'timemodified' => time(),
        ]);
        return $status;
    }

    /**
     * Delete an evaluation form. Cascades through questions, responses, assignments and queued triggers
     * (until 2026-09-30 the last two were left behind, pointing at a form that no longer existed).
     */
    public static function delete(int $id): bool {
        global $DB;
        $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        self::assert_not_imported($id);

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(self::QUESTIONS_TABLE, ['evaluationid' => $id]);
            $DB->delete_records(self::RESPONSES_TABLE, ['evaluationid' => $id]);
            $DB->delete_records(self::ASSIGN_TABLE, ['evaluationid' => $id]);
            $DB->delete_records(self::TRIGGERS_TABLE, ['evaluationid' => $id]);
            $DB->delete_records(self::TABLE, ['id' => $id]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Question CRUD (sub-entity of evaluation form)
    // ═══════════════════════════════════════════════════════════════════

    /** Question types and their display labels. */
    public const QUESTION_TYPES = [
        'rating'             => '5-point rating (1=Strongly Disagree → 5=Strongly Agree)',
        'nps'                => 'NPS (0-10 likelihood)',
        'yesno'              => 'Yes / No',
        'multichoice'        => 'Multiple choice (pick one)',
        // P1 #18 (2026-05-16) — closes audit item #3.
        'multichoice_multi'  => 'Multiple choice (check all that apply)',
        // P1 #18 (2026-05-16) — closes audit item #6.
        'numeric'            => 'Number (integer with optional min / max)',
        'text'               => 'Free text response',
    ];

    /**
     * Returns true if the question type stores its option set (or numeric
     * bounds) in the `options` JSON column. Centralised so create/update
     * + the form keep in lockstep when we add more option-bearing types.
     */
    private static function needs_options(string $type): bool {
        return in_array($type, ['multichoice', 'multichoice_multi', 'numeric'], true);
    }

    public static function get_questions(int $evaluationid): array {
        global $DB;
        return $DB->get_records(self::QUESTIONS_TABLE,
            ['evaluationid' => $evaluationid], 'sortorder ASC, id ASC');
    }

    // ─────────────────────────────────────────────────────────────────
    // Phase G.1 (2026-05-08) — evaluation TEMPLATE import / export
    //
    // Lets admins move an evaluation form definition (without responses)
    // between tenants or environments via a JSON file. Export + Import
    // are inverses — the JSON shape is the contract.
    // ─────────────────────────────────────────────────────────────────

    /** JSON shape version — bump when fields change incompatibly. */
    public const TEMPLATE_FORMAT_VERSION = 1;

    /**
     * Build a portable JSON template payload from one evaluation.
     *
     * @return array{
     *   format: int,
     *   exported_at: int,
     *   evaluation: array{name:string, description:string,
     *                     kirkpatrick_level:int, trigger_event:string,
     *                     days_after:int, anonymous:int},
     *   questions: list<array{questiontype:string, questiontext:string,
     *                          options:array, required:int, sortorder:int}>
     * }
     */
    public static function export_template(int $evaluationid): array {
        global $DB;
        $eval = $DB->get_record(self::TABLE, ['id' => $evaluationid],
            '*', MUST_EXIST);
        $questions = self::get_questions($evaluationid);

        $payload = [
            'format'      => self::TEMPLATE_FORMAT_VERSION,
            'exported_at' => time(),
            'evaluation'  => [
                'name'              => (string) $eval->name,
                'description'       => (string) ($eval->description ?? ''),
                'kirkpatrick_level' => (int) $eval->kirkpatrick_level,
                'trigger_event'     => (string) $eval->trigger_event,
                'days_after'        => (int) $eval->days_after,
                'anonymous'         => (int) $eval->anonymous,
            ],
            'questions'   => [],
        ];

        foreach ($questions as $q) {
            $payload['questions'][] = [
                'questiontype' => (string) $q->questiontype,
                'questiontext' => (string) $q->questiontext,
                'options'      => self::decode_options($q->options),
                'required'     => (int) $q->required,
                'anonymous'    => (int) ($q->anonymous ?? 0),
                'sortorder'    => (int) $q->sortorder,
            ];
        }
        return $payload;
    }

    /**
     * Import a template payload (decoded from JSON) into a new evaluation.
     * Returns the new evaluation ID. Does NOT import responses.
     *
     * @param array $payload    Same shape as export_template returns.
     * @param int   $costcenterid  Tenant to assign the new evaluation to.
     * @param int   $status        Initial status (default DRAFT).
     * @return array{id:int, name:string, question_count:int}
     */
    public static function import_template(array $payload,
                                            int $costcenterid = 0,
                                            int $status = self::STATUS_DRAFT): array {
        global $DB;

        if (!isset($payload['format']) || (int) $payload['format'] > self::TEMPLATE_FORMAT_VERSION) {
            throw new \invalid_parameter_exception(
                'Unsupported template format (version '
                . ($payload['format'] ?? 'missing') . ')');
        }
        $eval_data = $payload['evaluation'] ?? null;
        $questions = $payload['questions'] ?? [];
        if (!is_array($eval_data) || !is_array($questions)) {
            throw new \invalid_parameter_exception(
                'Malformed template — missing evaluation or questions');
        }

        $tx = $DB->start_delegated_transaction();
        try {
            $newid = self::create((object) [
                'name'              => trim((string) ($eval_data['name'] ?? 'Imported evaluation')),
                'description'       => (string) ($eval_data['description'] ?? ''),
                'kirkpatrick_level' => (int) ($eval_data['kirkpatrick_level'] ?? 1),
                'trigger_event'     => (string) ($eval_data['trigger_event'] ?? 'manual'),
                'days_after'        => (int) ($eval_data['days_after'] ?? 0),
                'anonymous'         => (int) ($eval_data['anonymous'] ?? 0),
                'costcenterid'      => $costcenterid,
                'status'            => $status,
            ]);

            $sortorder = 0;
            foreach ($questions as $q) {
                if (empty($q['questiontext'])) continue;
                $sortorder++;
                $questiontype = (string) ($q['questiontype'] ?? 'rating');
                // A number question exports its bounds as {min, max} in `options` (export_template() writes the
                // stored JSON through decode_options()); create_question() takes them as numeric_min / numeric_max.
                // Joined into a newline string like a choice list they were lost: both came back unset, so reusing a
                // form (the way to run an imported one again) dropped its 1..5 range.
                $numericbounds = null;
                if ($questiontype === 'numeric' && isset($q['options']) && is_array($q['options'])
                        && !array_is_list($q['options'])) {
                    $numericbounds = $q['options'];
                }
                self::create_question((object) [
                    'evaluationid' => $newid,
                    'questiontype' => $questiontype,
                    'questiontext' => (string) $q['questiontext'],
                    // create_question expects newline-separated text
                    // (parse_options() splits on \n). We stored the
                    // options as an array in the JSON template, so
                    // re-stringify them here.
                    'options'      => $numericbounds === null && isset($q['options']) && is_array($q['options'])
                        ? implode("\n", array_map('strval',
                            array_values($q['options'])))
                        : '',
                    'numeric_min'  => $numericbounds['min'] ?? '',
                    'numeric_max'  => $numericbounds['max'] ?? '',
                    'required'     => isset($q['required']) ? (int) $q['required'] : 1,
                    'anonymous'    => isset($q['anonymous']) ? (int) $q['anonymous'] : 0,
                    'sortorder'    => (int) ($q['sortorder'] ?? $sortorder),
                ]);
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }

        $imported = $DB->get_record(self::TABLE, ['id' => $newid], 'id, name');
        return [
            'id'             => (int) $imported->id,
            'name'           => format_string($imported->name),
            'question_count' => (int) self::count_questions((int) $imported->id),
        ];
    }

    public static function get_question(int $questionid) {
        global $DB;
        return $DB->get_record(self::QUESTIONS_TABLE, ['id' => $questionid]);
    }

    /**
     * Create a question. Auto-assigns sortorder = max + 1 within evaluation.
     */
    public static function create_question(object $data): int {
        global $DB;

        if (empty($data->evaluationid) || empty($data->questiontext) || empty($data->questiontype)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_evaluation');
        }

        if (!array_key_exists($data->questiontype, self::QUESTION_TYPES)) {
            throw new \moodle_exception('invalidquestiontype', 'local_sentientia_evaluation');
        }

        if (!$DB->record_exists(self::TABLE, ['id' => $data->evaluationid])) {
            throw new \moodle_exception('invalidevaluation', 'local_sentientia_evaluation');
        }
        self::assert_not_imported((int) $data->evaluationid);

        // P1 #18 — both multichoice variants need an options list; numeric
        // optionally stores {min, max} in the same column.
        $options_json = self::build_question_options_json($data);

        // Auto-increment sortorder.
        $maxsort = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {" . self::QUESTIONS_TABLE . "} WHERE evaluationid = :eid",
            ['eid' => $data->evaluationid]);

        // P1 #30 (2026-05-20) — conditional dependency. Validate the
        // parent is a sibling AND not self-referential. Cycle detection
        // is unnecessary at create time because the new row has no id
        // yet, so no chain can lead back to it.
        $depends_on_qid   = null;
        $depends_on_value = null;
        if (!empty($data->depends_on_qid)) {
            $depends_on_qid = (int) $data->depends_on_qid;
            self::validate_dep_parent($depends_on_qid,
                (int) $data->evaluationid, null);
            // depends_on_value can be empty string (meaning "any non-empty
            // parent answer triggers showing this question"). Trim
            // whitespace but preserve empty-string-means-anything.
            $depends_on_value = isset($data->depends_on_value)
                ? trim((string) $data->depends_on_value) : '';
            if ($depends_on_value === '') {
                $depends_on_value = null;
            }
        }

        $record = (object) [
            'evaluationid' => (int) $data->evaluationid,
            'questiontype' => $data->questiontype,
            'questiontext' => trim($data->questiontext),
            'options'      => $options_json,
            'required'     => isset($data->required) ? (int) $data->required : 1,
            // Phase G.2 (2026-05-08) — per-question anonymous toggle.
            'anonymous'    => isset($data->anonymous) ? (int) $data->anonymous : 0,
            'depends_on_qid'   => $depends_on_qid,
            'depends_on_value' => $depends_on_value,
            'sortorder'    => isset($data->sortorder) ? (int) $data->sortorder : ((int) $maxsort + 1),
            'timecreated'  => time(),
        ];

        return $DB->insert_record(self::QUESTIONS_TABLE, $record);
    }

    /**
     * P1 #30 — validate that $parent_qid is acceptable as a dependency
     * parent for a question in $evaluationid. The optional $self_qid
     * lets update_question() pass its own id to exclude self-cycles.
     *
     * Rules:
     *   1. parent must exist
     *   2. parent must live in the same evaluation
     *   3. parent must not be the child itself (only meaningful on update)
     *   4. following parent.depends_on_qid recursively must not loop back
     *      to the child (cycle detection)
     *
     * Throws moodle_exception on any rule violation.
     */
    public static function validate_dep_parent(int $parent_qid,
                                                 int $evaluationid,
                                                 ?int $self_qid): void {
        global $DB;
        if ($parent_qid <= 0) {
            throw new \moodle_exception('dep_invalid_parent',
                'local_sentientia_evaluation');
        }
        if ($self_qid !== null && $parent_qid === $self_qid) {
            throw new \moodle_exception('dep_self_reference',
                'local_sentientia_evaluation');
        }
        $parent = $DB->get_record(self::QUESTIONS_TABLE,
            ['id' => $parent_qid], 'id, evaluationid, depends_on_qid');
        if (!$parent) {
            throw new \moodle_exception('dep_invalid_parent',
                'local_sentientia_evaluation');
        }
        if ((int) $parent->evaluationid !== $evaluationid) {
            throw new \moodle_exception('dep_parent_other_evaluation',
                'local_sentientia_evaluation');
        }
        // Walk the parent's chain. If we ever see $self_qid the dep
        // would cycle. Use a visited-set guard in case the existing
        // data has a pre-existing cycle (shouldn't happen because we
        // validate at write time, but defensive).
        if ($self_qid !== null) {
            $visited = [];
            $cursor = (int) ($parent->depends_on_qid ?? 0);
            while ($cursor > 0 && !isset($visited[$cursor])) {
                if ($cursor === $self_qid) {
                    throw new \moodle_exception('dep_cycle',
                        'local_sentientia_evaluation');
                }
                $visited[$cursor] = true;
                $cursor = (int) $DB->get_field(self::QUESTIONS_TABLE,
                    'depends_on_qid', ['id' => $cursor]) ?: 0;
            }
        }
    }

    public static function update_question(int $id, object $data): bool {
        global $DB;
        $existing = $DB->get_record(self::QUESTIONS_TABLE, ['id' => $id], '*', MUST_EXIST);
        // An answered question of an imported form must keep its type and options: its answers are stored
        // against them (ADR-032).
        self::assert_not_imported((int) $existing->evaluationid);

        $record = (object) ['id' => $id];

        if (isset($data->questiontype)) {
            if (!array_key_exists($data->questiontype, self::QUESTION_TYPES)) {
                throw new \moodle_exception('invalidquestiontype', 'local_sentientia_evaluation');
            }
            $record->questiontype = $data->questiontype;
        }
        if (isset($data->questiontext)) $record->questiontext = trim($data->questiontext);
        if (isset($data->required))     $record->required = (int) $data->required;
        if (isset($data->anonymous)) {
            // 2026-09-25: the same promise per question. Unticking an
            // anonymous question after responses are in used to put a name
            // on every one of its answers (response_to_csv_row() hides the
            // respondent only while some question is anonymous).
            $anonymous = ((int) $data->anonymous === 1) ? 1 : 0;
            if ($anonymous === 0 && (int) ($existing->anonymous ?? 0) === 1
                    && self::has_submitted_responses((int) $existing->evaluationid)) {
                throw new \moodle_exception('error_question_anonymity_locked',
                    'local_sentientia_evaluation');
            }
            $record->anonymous = $anonymous;
        }
        if (isset($data->sortorder))    $record->sortorder = (int) $data->sortorder;

        // P1 #18 — Re-derive the options JSON if the type or option
        // metadata changed. For numeric we also accept numeric_min /
        // numeric_max via build_question_options_json().
        $finaltype = $record->questiontype ?? $existing->questiontype;
        $options_was_touched = isset($data->options)
            || isset($data->numeric_min) || isset($data->numeric_max);
        if (self::needs_options($finaltype) && $options_was_touched) {
            // Merge the type into the synthetic payload so the helper
            // dispatches correctly.
            $payload = clone $data;
            $payload->questiontype = $finaltype;
            $record->options = self::build_question_options_json($payload);
        } else if (isset($record->questiontype) && !self::needs_options($finaltype)) {
            // Type changed to one that doesn't store options — wipe the
            // stale JSON so analysis surfaces don't dereference dead data.
            $record->options = null;
        }

        // P1 #30 — conditional dependency on update. property_exists()
        // distinguishes "caller intentionally cleared the dep" (null)
        // from "caller didn't touch this field" (key absent).
        if (property_exists($data, 'depends_on_qid')) {
            $new_parent = $data->depends_on_qid !== null && $data->depends_on_qid !== ''
                ? (int) $data->depends_on_qid : null;
            if ($new_parent === null) {
                $record->depends_on_qid   = null;
                $record->depends_on_value = null;
            } else {
                self::validate_dep_parent($new_parent,
                    (int) $existing->evaluationid, $id);
                $record->depends_on_qid = $new_parent;
                $depends_on_value = isset($data->depends_on_value)
                    ? trim((string) $data->depends_on_value) : '';
                $record->depends_on_value = $depends_on_value === ''
                    ? null : $depends_on_value;
            }
        } else if (property_exists($data, 'depends_on_value')
                && (int) ($existing->depends_on_qid ?? 0) > 0) {
            // Caller updated just the value (keeping the same parent).
            $v = trim((string) $data->depends_on_value);
            $record->depends_on_value = $v === '' ? null : $v;
        }

        $DB->update_record(self::QUESTIONS_TABLE, $record);
        return true;
    }

    /**
     * P1 #18 — Build the `options` JSON column for a question payload.
     * Returns null when the type doesn't carry options.
     *
     *  - multichoice / multichoice_multi → ["Opt A", "Opt B", ...]
     *  - numeric                          → {"min": int|null, "max": int|null}
     *
     * @throws \moodle_exception when options are malformed for the type.
     */
    private static function build_question_options_json(object $data): ?string {
        $type = (string) ($data->questiontype ?? '');

        if ($type === 'multichoice' || $type === 'multichoice_multi') {
            $opts = self::parse_options($data->options ?? '');
            if (count($opts) < 2) {
                throw new \moodle_exception('multichoice_needs_options',
                    'local_sentientia_evaluation');
            }
            return json_encode(array_values($opts));
        }

        if ($type === 'numeric') {
            // Empty string = "no constraint". We store null in JSON so the
            // shape stays stable and the form can distinguish "unset" from
            // "zero is the bound".
            $min = (isset($data->numeric_min) && $data->numeric_min !== '')
                ? (int) $data->numeric_min : null;
            $max = (isset($data->numeric_max) && $data->numeric_max !== '')
                ? (int) $data->numeric_max : null;
            if ($min !== null && $max !== null && $max < $min) {
                throw new \moodle_exception('numeric_min_max_invalid',
                    'local_sentientia_evaluation');
            }
            return json_encode(['min' => $min, 'max' => $max]);
        }

        return null;
    }

    /**
     * P1 #18 — Decode the numeric `{min, max}` from a question's options
     * JSON. Returns ['min' => int|null, 'max' => int|null].
     */
    public static function decode_numeric_bounds(?string $options_json): array {
        if (!$options_json) {
            return ['min' => null, 'max' => null];
        }
        $decoded = json_decode($options_json, true);
        if (!is_array($decoded)) {
            return ['min' => null, 'max' => null];
        }
        return [
            'min' => isset($decoded['min']) && $decoded['min'] !== null
                ? (int) $decoded['min'] : null,
            'max' => isset($decoded['max']) && $decoded['max'] !== null
                ? (int) $decoded['max'] : null,
        ];
    }

    public static function delete_question(int $id): bool {
        global $DB;
        $question = $DB->get_record(self::QUESTIONS_TABLE, ['id' => $id], '*', MUST_EXIST);
        self::assert_not_imported((int) $question->evaluationid);
        // Deleting an answered anonymous question would undo the anonymity
        // lock: identity_protected() keeps a named evaluation's respondents
        // hidden only while such a question exists (2026-09-25).
        if (self::question_anonymity_locked($id)) {
            throw new \moodle_exception('error_question_anonymity_delete_locked',
                'local_sentientia_evaluation');
        }
        $DB->delete_records(self::QUESTIONS_TABLE, ['id' => $id]);
        return true;
    }

    /**
     * Reorder questions — accepts an ordered array of question IDs.
     * Each ID's sortorder is set to its index in the array.
     */
    public static function reorder_questions(int $evaluationid, array $ordered_ids): bool {
        global $DB;
        self::assert_not_imported($evaluationid);

        $transaction = $DB->start_delegated_transaction();
        try {
            $sortorder = 0;
            foreach ($ordered_ids as $qid) {
                $qid = (int) $qid;
                if (!$DB->record_exists(self::QUESTIONS_TABLE,
                    ['id' => $qid, 'evaluationid' => $evaluationid])) {
                    continue;
                }
                $DB->set_field(self::QUESTIONS_TABLE, 'sortorder', $sortorder, ['id' => $qid]);
                $sortorder++;
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return true;
    }

    /** Parse options text (one per line) into clean array. */
    public static function parse_options(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $opts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $opts[] = $line;
            }
        }
        return $opts;
    }

    /** Decode stored options JSON back to array. */
    public static function decode_options(?string $json): array {
        if (empty($json)) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Response submission + retrieval (learner + admin views)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Has the user already submitted this evaluation in a way that
     * should block a fresh submission?
     *
     * Returns false (= "user may submit again") when:
     *   - the evaluation is anonymous (we don't tie responses to users
     *     anyway, so re-submission is a no-op for identity), or
     *   - the evaluation has multiple_submit=1 (P1 #17 — pulse surveys
     *     explicitly allow re-submission).
     *
     * Otherwise checks for a SUBMITTED response row. The pending shell the trigger queue writes for an invited user
     * (timesubmitted 0, see evaluation_engine::process_due_triggers()) is not a response: counting it told every
     * invited user of a named form "you already responded" and made submit_response() throw alreadyresponded
     * before they had answered anything (fixed 2026-10-01).
     */
    public static function has_user_responded(int $evaluationid, int $userid): bool {
        global $DB;
        $eval = self::get($evaluationid);
        if (!$eval || (int) $eval->anonymous === 1) {
            return false;
        }
        // P1 #17 — pulse surveys.
        if ((int) ($eval->multiple_submit ?? 0) === 1) {
            return false;
        }
        return $DB->record_exists_select(self::RESPONSES_TABLE,
            'evaluationid = :eid AND userid = :uid AND timesubmitted > 0',
            ['eid' => $evaluationid, 'uid' => $userid]);
    }

    /**
     * Submit a response. Validates each answer against its question type.
     */
    public static function submit_response(int $evaluationid, int $userid,
                                            array $answers, array $context = []): int {
        global $DB;

        $eval = self::get($evaluationid);
        if (!$eval) {
            throw new \moodle_exception('invalidevaluation', 'local_sentientia_evaluation');
        }
        if ((int) $eval->status !== self::STATUS_ACTIVE) {
            throw new \moodle_exception('evaluationnotactive', 'local_sentientia_evaluation');
        }

        // P1 #17 — gate on the configured availability window. Admins
        // marking an evaluation "active" no longer have to manually
        // archive it when its window closes.
        if (!self::is_open_now($eval)) {
            $now = time();
            $open  = (int) ($eval->timeopen  ?? 0);
            $close = (int) ($eval->timeclose ?? 0);
            if ($open > 0 && $now < $open) {
                throw new \moodle_exception('evaluationnotyetopen',
                    'local_sentientia_evaluation', '', userdate($open));
            }
            if ($close > 0 && $now >= $close) {
                throw new \moodle_exception('evaluationclosed',
                    'local_sentientia_evaluation', '', userdate($close));
            }
        }

        if ((int) $eval->anonymous !== 1 && $userid > 0) {
            if (self::has_user_responded($evaluationid, $userid)) {
                throw new \moodle_exception('alreadyresponded', 'local_sentientia_evaluation');
            }
        }

        $questions = self::get_questions($evaluationid);
        if (empty($questions)) {
            throw new \moodle_exception('evaluationhasnoquestions', 'local_sentientia_evaluation');
        }

        // P1 #30 — determine which questions are VISIBLE given the
        // answers submitted so far. A question with a dependency whose
        // parent answer doesn't match is treated as hidden and its
        // answer is forced to null (not validated, not stored). This
        // is the server-side counterpart to the JS show/hide on the
        // respond page: clients can't bypass dependency-required by
        // crafting a payload that includes the hidden question's answer.
        $visible = self::compute_visibility_map($questions, $answers);

        $cleaned = [];
        foreach ($questions as $q) {
            if (empty($visible[$q->id])) {
                // Hidden by dependency → answer is null regardless of payload.
                $cleaned[$q->id] = null;
                continue;
            }
            $raw = $answers[$q->id] ?? null;
            $clean = self::validate_answer($q, $raw);
            $cleaned[$q->id] = $clean;
        }

        $stored_userid = ((int) $eval->anonymous === 1) ? 0 : $userid;

        $record = (object) [
            'evaluationid'  => $evaluationid,
            'userid'        => $stored_userid,
            'courseid'      => isset($context['courseid'])    ? (int) $context['courseid']    : null,
            'programid'     => isset($context['programid'])   ? (int) $context['programid']   : null,
            'classroomid'   => isset($context['classroomid']) ? (int) $context['classroomid'] : null,
            'response_data' => json_encode($cleaned),
            'timesubmitted' => time(),
        ];

        $responseid = $DB->insert_record(self::RESPONSES_TABLE, $record);

        // P1 #37 (2026-05-20) — mark any matching assignments as
        // 'responded'. Anonymous responses still mark assignments
        // because the assignment uses the ACTUAL userid (the responder
        // who clicked submit), not the stored userid (which is 0 for
        // anonymous). Wrapped in try/catch so a missing assignment row
        // doesn't poison submission.
        if ($userid > 0) {
            try {
                self::mark_assignments_responded((int) $eval->id, (int) $userid);
            } catch (\Throwable $e) {
                debugging('mark_assignments_responded failed: ' . $e->getMessage(),
                    DEBUG_NORMAL);
            }
        }

        // P1 #19 — opt-in admin notification. Wrapped in try/catch so a
        // misconfigured message provider can never break submission.
        if ((int) ($eval->notify_admin_on_response ?? 0) === 1) {
            try {
                self::notify_admins_of_response($eval, $responseid,
                    $stored_userid, (int) $userid);
            } catch (\Throwable $e) {
                debugging('notify_admins_of_response failed: ' . $e->getMessage(),
                    DEBUG_NORMAL);
            }
        }

        return $responseid;
    }

    /**
     * P1 #37 (2026-05-20) — record an assignment.
     *
     * Idempotent: the UNIQUE index on (evaluationid, userid,
     * trigger_event, source_id) catches re-assigns. We do a
     * record_exists pre-check (cheaper than catching the exception);
     * if the row exists we just return its id. If it exists but is
     * 'expired', we re-open it to 'assigned' (an admin re-assigning
     * an expired evaluation is a deliberate action).
     *
     * @param int      $evaluationid
     * @param int      $userid
     * @param string   $trigger_event
     * @param int      $source_id           0 for manual
     * @param int|null $assigned_by_userid  null for auto
     * @param int|null $due_at              optional deadline
     * @return int  Row id (existing or new).
     */
    public static function ensure_assignment(int $evaluationid, int $userid,
                                               string $trigger_event = 'manual',
                                               int $source_id = 0,
                                               ?int $assigned_by_userid = null,
                                               ?int $due_at = null): int {
        global $DB;

        // An assignment of an imported form is history: re-opening an expired one would rewrite it.
        self::assert_not_imported($evaluationid);

        $existing = $DB->get_record(self::ASSIGN_TABLE, [
            'evaluationid' => $evaluationid,
            'userid'       => $userid,
            'trigger_event' => $trigger_event,
            'source_id'    => $source_id,
        ]);
        if ($existing) {
            // Re-open an expired assignment — admin re-assignment is
            // deliberate. Don't touch 'responded' rows; if the user
            // has already responded, the assignment is already closed.
            if ($existing->status === 'expired') {
                $DB->update_record(self::ASSIGN_TABLE, (object) [
                    'id'           => $existing->id,
                    'status'       => 'assigned',
                    'due_at'       => $due_at,
                    'timemodified' => time(),
                ]);
            }
            return (int) $existing->id;
        }

        $now = time();
        return (int) $DB->insert_record(self::ASSIGN_TABLE, (object) [
            'evaluationid'       => $evaluationid,
            'userid'             => $userid,
            'trigger_event'      => $trigger_event,
            'source_id'          => $source_id,
            'status'             => 'assigned',
            'assigned_by_userid' => $assigned_by_userid,
            'due_at'             => $due_at,
            'timecreated'        => $now,
            'timemodified'       => $now,
        ]);
    }

    /**
     * P1 #37 — close every open assignment for (evaluation, user)
     * when the user submits a response. Idempotent — called from
     * submit_response after the response row is inserted.
     *
     * A single user can have multiple open assignments for one
     * evaluation (e.g. course_completion source=X AND
     * program_completion source=Y both auto-assigned them). All
     * matching rows flip to 'responded' on a single submission —
     * one submission satisfies all outstanding assignments.
     */
    public static function mark_assignments_responded(int $evaluationid,
                                                       int $userid): int {
        global $DB;
        $now = time();
        // get_records first so we can return the count; bulk UPDATE
        // would be one query but losing the count costs us testability.
        $rows = $DB->get_records(self::ASSIGN_TABLE, [
            'evaluationid' => $evaluationid,
            'userid'       => $userid,
            'status'       => 'assigned',
        ]);
        foreach ($rows as $row) {
            $DB->update_record(self::ASSIGN_TABLE, (object) [
                'id'           => $row->id,
                'status'       => 'responded',
                'responded_at' => $now,
                'timemodified' => $now,
            ]);
        }
        return count($rows);
    }

    /**
     * P1 #37 — query helper for the show-non-respondents page (future
     * P1 #38). Returns assignment rows joined to user details, filtered
     * by status.
     *
     * @param int    $evaluationid
     * @param string $status  'assigned' (default) | 'responded' | 'expired'
     * @return array<int, \stdClass>
     */
    public static function list_assignments(int $evaluationid,
                                              string $status = 'assigned'): array {
        global $DB;
        return $DB->get_records_sql("
            SELECT a.id, a.userid, a.trigger_event, a.source_id,
                   a.status, a.due_at, a.responded_at, a.timecreated,
                   u.firstname, u.lastname, u.email
              FROM {" . self::ASSIGN_TABLE . "} a
              JOIN {user} u ON u.id = a.userid
             WHERE a.evaluationid = :eid
               AND a.status = :status
               AND u.deleted = 0
          ORDER BY a.timecreated DESC, u.lastname ASC", [
            'eid'    => $evaluationid,
            'status' => $status,
        ]);
    }

    /**
     * Must respondent identity stay hidden for this evaluation? STICKY.
     *
     * True when ANY of these holds:
     *   - the evaluation is anonymous now;
     *   - any stored response was collected anonymously - submit_response()
     *     stores those with userid 0, and nothing else ever does, so the
     *     rows remember the promise even if the flag was later switched off
     *     (update() refuses that since 2026-09-25, but earlier data may
     *     carry it);
     *   - any question is anonymous (response_to_csv_row() already hides the
     *     respondent for the whole row then).
     *
     * Until 2026-09-25 only the evaluation's CURRENT flag was read, so an
     * admin could untick "Collect responses anonymously" after responses
     * were in and read the Responded tab (names, emails, responded_at to the
     * minute) against the anonymous answers.
     *
     * @param object $evaluation record carrying id and anonymous
     * @return bool
     */
    public static function identity_protected(object $evaluation): bool {
        global $DB;
        if ((int) ($evaluation->anonymous ?? 0) === 1) {
            return true;
        }
        $evaluationid = (int) ($evaluation->id ?? 0);
        if ($evaluationid <= 0) {
            return false;
        }
        return $DB->record_exists(self::RESPONSES_TABLE, ['evaluationid' => $evaluationid, 'userid' => 0])
            || $DB->record_exists(self::QUESTIONS_TABLE, ['evaluationid' => $evaluationid, 'anonymous' => 1]);
    }

    /**
     * Do the response list and the CSV export carry a "Subject" column for this evaluation?
     *
     * A supervisor evaluation (BizLMS evaluationmode SP) is answered by one person ABOUT another, and the import
     * keeps that person in responses.subject_userid. The form says it is one: its evaluationmode column is SP (EV-17;
     * every native form is SE). Native forms never set a subject, so for them nothing changes: the column appears
     * only on an SP form where some response names a subject, and never on a protected evaluation (the subject of
     * an anonymous supervisor form could identify the respondent; the import does not keep it there either).
     *
     * @param \stdClass $evaluation record carrying id, anonymous and evaluationmode
     * @param bool|null $identityprotected identity_protected($evaluation), when the caller has it already;
     *                  null = work it out here
     * @return bool
     */
    public static function shows_subject(\stdClass $evaluation, ?bool $identityprotected = null): bool {
        global $DB;
        if (($evaluation->evaluationmode ?? self::MODE_SELF) !== self::MODE_SUPERVISOR) {
            return false;
        }
        if ($identityprotected ?? self::identity_protected($evaluation)) {
            return false;
        }
        return $DB->record_exists_select(self::RESPONSES_TABLE,
            'evaluationid = :eid AND subject_userid IS NOT NULL', ['eid' => (int) $evaluation->id]);
    }

    /**
     * Has anybody actually submitted this evaluation? The trigger queue's
     * pending "shell" rows (timesubmitted 0, see evaluation_engine) are not
     * responses.
     *
     * @param int $evaluationid
     * @return bool
     */
    public static function has_submitted_responses(int $evaluationid): bool {
        global $DB;
        return $DB->record_exists_select(self::RESPONSES_TABLE,
            'evaluationid = :eid AND timesubmitted > 0', ['eid' => $evaluationid]);
    }

    /**
     * Refuse a response row that is not a submission.
     *
     * The trigger queue writes a pending "shell" for an invited user (timesubmitted 0, response_data '{}'): an
     * invitation, not a response. response_detail.php opens any response id, so without this it showed the shell as
     * the invitee's answer: their name, "submitted 1 Jan 1970" and every question unanswered.
     *
     * @param \stdClass $response a responses row
     * @return void
     * @throws \moodle_exception invalidresponse when the row is a shell
     */
    public static function require_submitted_response(\stdClass $response): void {
        if ((int) ($response->timesubmitted ?? 0) <= 0) {
            throw new \moodle_exception('invalidresponse', 'local_sentientia_evaluation');
        }
    }

    /**
     * Are the individual responses pages (response_list.php and response_detail.php) switched on for the current
     * user's customer and tenant?
     *
     * @return bool
     */
    public static function response_drilldown_enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG_RESPONSE_DRILLDOWN);
    }

    /**
     * Who the detail page says answered: name, e-mail and employee id, or nobody.
     *
     * The name goes through fullname() over every name field, as the response list and the CSV do, so the site's name
     * format applies to all three (the page printed trim(firstname . ' ' . lastname)). A protected evaluation
     * ({@see self::identity_protected()}) names nobody and shows the "anonymous" label, whatever the response row
     * holds; so does a response with no user, or whose account is gone, as before.
     *
     * @param \stdClass $response a responses row
     * @param bool $protected identity_protected($evaluation)
     * @return array{user_name: string, user_email: string, employee_id: string}
     */
    public static function response_detail_respondent(\stdClass $response, bool $protected): array {
        global $DB;
        $anonymous = ['user_name' => get_string('eval_response_responder_anonymous', 'local_sentientia_evaluation'),
            'user_email' => '', 'employee_id' => ''];
        $userid = (int) ($response->userid ?? 0);
        if ($protected || $userid <= 0) {
            return $anonymous;
        }
        $user = $DB->get_record('user', ['id' => $userid], self::respondent_fields() . ', open_employeeid');
        if (!$user) {
            return $anonymous;
        }
        return ['user_name' => fullname($user), 'user_email' => (string) $user->email,
            'employee_id' => (string) ($user->open_employeeid ?? '')];
    }

    /**
     * The first two gates of response_list.php and response_detail.php, in the order the pages apply them.
     *
     * (1) local/sentientia_evaluation:manage, which only the manager archetype holds by default: a manager, a tenant
     * administrator and a site administrator. A learner (":respond" only) and a trainer (the teacher archetype) are
     * refused. The pages used to ask for ":view", which no plugin declares, so nobody could open them (EV-06).
     * (2) The flag {@see self::FLAG_RESPONSE_DRILLDOWN}: OFF answers "not available", as if the pages did not exist.
     *
     * The ADR-031 tenant gate ({@see self::require_evaluation_access()}) is the page's third step, because it needs
     * the evaluation; the capability and the flag come first so that nothing is read for a caller who may not be
     * here. Neither this nor that gate decides who sees NAMES: identity_protected() does, per evaluation.
     *
     * @return void
     * @throws \required_capability_exception for a caller without :manage
     * @throws \moodle_exception response_drilldown_unavailable when the flag is OFF
     */
    public static function require_response_drilldown(): void {
        require_capability('local/sentientia_evaluation:manage', \context_system::instance());
        if (!self::response_drilldown_enabled()) {
            throw new \moodle_exception('response_drilldown_unavailable', 'local_sentientia_evaluation');
        }
    }

    /**
     * Text for a template that prints it with {{ }}.
     *
     * Mustache escapes once, in the template, so the text is filtered here (multilang tags and the like) and NOT
     * escaped as well: format_string() with its default would turn "Tom & Jerry" into "Tom &amp; Jerry" and the
     * template into "Tom &amp;amp; Jerry".
     *
     * This is the rule for a Mustache {{ }} only. Do not carry it over to other APIs: $PAGE->set_title() and
     * set_heading() run format_string() on what they are given, so they take the raw text. A breadcrumb does not:
     * navigation_node::get_content() formats a crumb that has a link, but the last crumb has its link removed by the
     * theme and core/navbar prints it raw ({{{text}}}), so $PAGE->navbar->add() is given format_string($text).
     *
     * @param string|null $text
     * @return string
     */
    public static function display_text(?string $text): string {
        return format_string((string) $text, true, ['escape' => false]);
    }

    /**
     * The heading part of the aggregate responses page (responses.php).
     *
     * is_anonymous is identity_protected(), the rule the response list and the CSV apply, so a form that is protected
     * because it once collected anonymous answers (or has an anonymous question) is badged as well, not only one whose
     * flag is set today.
     *
     * @param \stdClass $evaluation
     * @return array{name: string, description: string, is_anonymous: bool, kirkpatrick_label: string}
     */
    public static function responses_page_header(\stdClass $evaluation): array {
        return [
            'name'              => self::display_text($evaluation->name ?? ''),
            'description'       => self::display_text($evaluation->description ?? ''),
            'is_anonymous'      => self::identity_protected($evaluation),
            'kirkpatrick_label' => self::KIRKPATRICK_LEVELS[(int) ($evaluation->kirkpatrick_level ?? 0)] ?? '',
        ];
    }

    /**
     * May this evaluation no longer be made non-anonymous? True when it is
     * anonymous and somebody has already answered it (update() refuses the
     * change; edit_evaluation shows the reason).
     *
     * @param int $evaluationid
     * @return bool
     */
    public static function anonymity_locked(int $evaluationid): bool {
        global $DB;
        return (int) $DB->get_field(self::TABLE, 'anonymous', ['id' => $evaluationid]) === 1
            && self::has_submitted_responses($evaluationid);
    }

    /**
     * The same for one question: anonymous, and its evaluation already has
     * responses (update_question() refuses; edit_question shows the reason).
     *
     * @param int $questionid
     * @return bool
     */
    public static function question_anonymity_locked(int $questionid): bool {
        global $DB;
        $q = $DB->get_record(self::QUESTIONS_TABLE, ['id' => $questionid], 'id, evaluationid, anonymous');
        return $q && (int) $q->anonymous === 1
            && self::has_submitted_responses((int) $q->evaluationid);
    }

    /**
     * Is the list of who has responded withheld for this evaluation?
     *
     * An anonymous evaluation stores its responses with userid 0, but the
     * assignment rows still record WHO responded and WHEN (responded_at,
     * to the minute). Listing them next to the anonymous answers, whose
     * timesubmitted is the same moment, would let the admin put a name to
     * each answer. So the 'responded' list is withheld; the pending list
     * (who still has to be chased) is not. Withheld whenever
     * {@see self::identity_protected()} - not just while the evaluation's
     * anonymous flag happens to be set.
     *
     * @param \stdClass $evaluation record carrying id and anonymous
     * @param string    $status 'assigned' | 'responded' | 'expired'
     * @return bool
     */
    public static function respondents_hidden(\stdClass $evaluation, string $status): bool {
        return $status === 'responded' && self::identity_protected($evaluation);
    }

    /**
     * How a submission time is shown next to a response: to the minute
     * normally, to the DAY for an evaluation whose respondents are
     * protected ({@see self::identity_protected()}) - a minute-exact time
     * is what matches an anonymous answer to a named log line, notification
     * or assignment row.
     *
     * @param int  $timestamp
     * @param bool $identityprotected
     * @param bool $iso true for the CSV's 'Y-m-d[ H:i]' form, false for the
     *                  pages' 'd M Y[ H:i]' form
     * @return string
     */
    public static function submitted_label(int $timestamp, bool $identityprotected, bool $iso = false): string {
        if ($iso) {
            // $fixday = false: userdate() otherwise strips the leading zero
            // from %d ('2026-10-5'), which is not the ISO date the CSV promises.
            return userdate($timestamp, $identityprotected ? '%Y-%m-%d' : '%Y-%m-%d %H:%M',
                99, false);
        }
        return userdate($timestamp, $identityprotected ? '%d %b %Y' : '%d %b %Y %H:%M');
    }

    /**
     * The date_from / date_to filters of responses.php and exportcsv.php,
     * snapped to whole days: date_from to the start of its day, date_to to
     * 23:59:59 of its day (server time, as before).
     *
     * The filters are documented as YYYY-MM-DD, and for that input the
     * result is unchanged. But they were parsed with a bare strtotime(), so
     * '2026-09-25 14:31' filtered to the minute: narrowing the window until
     * one response is left recovers the minute-exact submission time that
     * submitted_label() withholds for a protected evaluation.
     *
     * @param string $datefrom raw date_from ('' = none)
     * @param string $dateto   raw date_to ('' = none)
     * @return array{date_from?: int, date_to?: int}
     */
    public static function response_filter_days(string $datefrom, string $dateto): array {
        $out = [];
        if (trim($datefrom) !== '' && ($ts = strtotime($datefrom)) !== false) {
            $out['date_from'] = (int) strtotime(date('Y-m-d', $ts) . ' 00:00:00');
        }
        if (trim($dateto) !== '' && ($ts = strtotime($dateto)) !== false) {
            $out['date_to'] = (int) strtotime(date('Y-m-d', $ts) . ' 23:59:59');
        }
        return $out;
    }

    /**
     * Assignment rows the non_respondents page may show: list_assignments(),
     * except that an anonymous evaluation's 'responded' list is empty
     * ({@see self::respondents_hidden()}). Callers must already have passed
     * require_evaluation_access().
     *
     * @param \stdClass $evaluation
     * @param string    $status
     * @return array<int, \stdClass>
     */
    public static function list_assignments_for_view(\stdClass $evaluation, string $status): array {
        if (self::respondents_hidden($evaluation, $status)) {
            return [];
        }
        return self::list_assignments((int) $evaluation->id, $status);
    }

    // ═══════════════════════════════════════════════════════════════════
    // P1 #41 (2026-05-20) — DB-backed template library.
    // ═══════════════════════════════════════════════════════════════════
    //
    // The Phase G.1 JSON export/import already produces a self-describing
    // template payload. The template library is a thin DB cache of those
    // payloads: save a row, look it up later, hand it back to
    // `import_template()` for reuse. No new payload schema; the row
    // stores the exact same JSON that export_template() produces.

    /**
     * Save an existing evaluation as a reusable template.
     *
     * @param int    $evaluationid     Source evaluation
     * @param string $template_name    Display name for the template
     * @param string $template_desc    Short description shown in the picker
     * @param int    $createdby_userid Acting admin (recorded for audit)
     * @param int    $costcenterid     Originating tenant (0 = global)
     * @param bool   $ispublic         True = visible across tenants
     * @return int  New template row id
     */
    public static function save_template_from_evaluation(int $evaluationid,
                                                           string $template_name,
                                                           string $template_desc,
                                                           int $createdby_userid,
                                                           int $costcenterid = 0,
                                                           bool $ispublic = false): int {
        global $DB;
        if (trim($template_name) === '') {
            throw new \moodle_exception('template_name_required',
                'local_sentientia_evaluation');
        }
        $payload = self::export_template($evaluationid);
        $now = time();
        return (int) $DB->insert_record(self::TEMPLATE_TABLE, (object) [
            'name'             => trim($template_name),
            'description'      => $template_desc,
            'payload'          => json_encode($payload),
            'createdby_userid' => $createdby_userid,
            'costcenterid'     => $costcenterid,
            'ispublic'         => $ispublic ? 1 : 0,
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
    }

    /**
     * Create a new evaluation from a saved template.
     * Returns the array `import_template()` returns (id, name, question_count).
     *
     * No session check here (the CLI calls it): a page or web service that offers it calls
     * {@see self::require_template_access()} first.
     */
    public static function create_evaluation_from_template(int $templateid,
                                                             int $target_costcenterid = 0,
                                                             int $status = self::STATUS_DRAFT): array {
        global $DB;
        $row = $DB->get_record(self::TEMPLATE_TABLE, ['id' => $templateid],
            '*', MUST_EXIST);
        $payload = json_decode((string) $row->payload, true);
        if (!is_array($payload)) {
            throw new \moodle_exception('template_payload_corrupt',
                'local_sentientia_evaluation');
        }
        return self::import_template($payload, $target_costcenterid, $status);
    }

    /**
     * The templates the CURRENT USER may see (ADR-031): a cross-tenant caller (site admin, or the platform's
     * crosstenant capability) sees every template; anyone else sees the templates whose organisation is inside
     * their own tenant. A caller with no tenant sees none.
     *
     * A template's costcenterid is an organisation id (the importer and the "save as template" call both store one),
     * so the scope is read from that organisation's path, not by comparing the id with a tenant number. A template
     * with costcenterid 0 belongs to no tenant and is for cross-tenant callers only, as a global evaluation is.
     * Another tenant's `ispublic` templates are NOT included: that is the strict ADR-031 reading. Widening it
     * (the help text of "Make this template available to other tenants" promises as much) is an owner decision for
     * when a picker is built; until then nothing in the plugin lists templates to a user.
     *
     * Until 2026-10-01 this took a costcenterid, treated 0 as "everything", compared a non-zero one with the
     * template's organisation id as a bare number, and always added every tenant's public templates.
     *
     * @return array<int, \stdClass> template rows, newest first
     */
    public static function list_templates(): array {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return $DB->get_records(self::TEMPLATE_TABLE, null, 'timemodified DESC, id DESC');
        }
        // path_filter() is '1=0' for a caller with no tenant.
        [$tsql, $tparams] = \local_sentientia_platform\tenant::path_filter('o', 'path');
        return $DB->get_records_sql(
            "SELECT t.*
               FROM {" . self::TEMPLATE_TABLE . "} t
               JOIN {local_sentientia_org} o ON o.id = t.costcenterid
              WHERE {$tsql}
           ORDER BY t.timemodified DESC, t.id DESC", $tparams);
    }

    /**
     * ADR-031: may the current user use (create an evaluation from, delete) this template? The same rule as
     * list_templates().
     *
     * @param \stdClass $template a template row carrying costcenterid
     * @return bool
     */
    public static function can_access_template(\stdClass $template): bool {
        global $DB;
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return true;
        }
        $orgid = (int) ($template->costcenterid ?? 0);
        if ($orgid <= 0) {
            return false;
        }
        $path = (string) $DB->get_field('local_sentientia_org', 'path', ['id' => $orgid]);
        return self::path_in_root($path, \local_sentientia_platform\tenant::root_for_current_user());
    }

    /**
     * ADR-031: load a template by id and refuse unless can_access_template(). A page or web service that offers
     * create_evaluation_from_template() or delete_template() calls this first, at the entry point, as the
     * evaluation pages call require_evaluation_access(); those two methods take no session user (the CLI drives
     * them), so the gate is not inside them.
     *
     * @param int $templateid
     * @return \stdClass the template row
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_template_access(int $templateid): \stdClass {
        global $DB;
        $template = $DB->get_record(self::TEMPLATE_TABLE, ['id' => $templateid], '*', MUST_EXIST);
        if (!self::can_access_template($template)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return $template;
    }

    /**
     * Did the BizLMS import create this template?
     *
     * @param int $templateid
     * @return bool False for a template a person saved, and on a site whose platform has no import framework.
     */
    public static function is_imported_template(int $templateid): bool {
        if ($templateid <= 0 || !class_exists(\local_sentientia_platform\bizlms\provenance::class)) {
            return false;
        }
        return \local_sentientia_platform\bizlms\provenance::is_imported(self::TEMPLATE_TABLE, $templateid);
    }

    /**
     * Delete a template row.
     *
     * A template the BizLMS import created is part of the imported history and is kept (decision
     * framework.protect_imported_history: delete actions on imported rows are blocked).
     *
     * No session check here (the CLI calls it): a page or web service that offers it calls
     * {@see self::require_template_access()} first.
     *
     * @param int $templateid
     * @return bool
     * @throws \moodle_exception error_imported_template_read_only
     */
    public static function delete_template(int $templateid): bool {
        global $DB;
        $DB->get_record(self::TEMPLATE_TABLE, ['id' => $templateid],
            '*', MUST_EXIST);
        if (self::is_imported_template($templateid)) {
            throw new \moodle_exception('error_imported_template_read_only', 'local_sentientia_evaluation');
        }
        $DB->delete_records(self::TEMPLATE_TABLE, ['id' => $templateid]);
        return true;
    }

    /**
     * P1 #19 — Send a Moodle notification to every siteadmin announcing
     * that a new response has come in.
     *
     * - Recipients: get_admins() (each admin can opt out per-channel via
     *   their own notification preferences — the message provider is
     *   exposed in the user-profile UI).
     * - Anonymous responses: subject/body omit the responder name and
     *   include "(anonymous)" instead.
     * - Body links to the admin's responses view, not the learner's.
     */
    private static function notify_admins_of_response(\stdClass $eval,
                                                       int $responseid,
                                                       int $stored_userid,
                                                       int $actual_userid): void {
        global $DB, $CFG;

        $admins = get_admins();
        if (empty($admins)) {
            return;
        }

        // Respect anonymity at notification time too — even if the
        // RESPONSES_TABLE stored userid=0, an admin who knows when the
        // response landed could still cross-reference logs. Best policy:
        // never expose responder identity in the notification when
        // anonymous=1. 2026-09-25: nor when any question is anonymous, or
        // earlier responses were collected anonymously - a named
        // notification stamped with the submission minute would put a name
        // on that anonymous answer ({@see self::identity_protected()}).
        $is_anonymous = self::identity_protected($eval);
        if ($is_anonymous || $stored_userid === 0) {
            $responder_label = get_string('eval_response_responder_anonymous',
                'local_sentientia_evaluation');
        } else {
            $responder = $DB->get_record('user',
                ['id' => $stored_userid > 0 ? $stored_userid : $actual_userid]);
            $responder_label = $responder
                ? fullname($responder) . ' <' . $responder->email . '>'
                : get_string('eval_response_responder_unknown',
                    'local_sentientia_evaluation');
        }

        $eval_name = format_string($eval->name);
        $url = new \moodle_url('/local/sentientia_evaluation/responses.php',
            ['id' => (int) $eval->id]);
        $url_str = $url->out(false);

        $subject = get_string('eval_response_subject',
            'local_sentientia_evaluation', $eval_name);

        $body_plain = get_string('eval_response_body_plain',
            'local_sentientia_evaluation', (object) [
                'evalname'  => $eval_name,
                'responder' => $responder_label,
                'url'       => $url_str,
            ]);
        $body_html = get_string('eval_response_body_html',
            'local_sentientia_evaluation', (object) [
                'evalname'  => $eval_name,
                'responder' => s($responder_label),
                'url'       => $url_str,
            ]);
        $small = get_string('eval_response_small',
            'local_sentientia_evaluation', $eval_name);

        foreach ($admins as $admin) {
            $msg = new \core\message\message();
            $msg->component         = 'local_sentientia_evaluation';
            $msg->name              = 'evaluation_response';
            $msg->userfrom          = \core_user::get_noreply_user();
            $msg->userto            = $admin;
            $msg->subject           = $subject;
            $msg->fullmessage       = $body_plain;
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml   = $body_html;
            $msg->smallmessage      = $small;
            $msg->notification      = 1;
            $msg->contexturl        = $url_str;
            $msg->contexturlname    = get_string('viewresponses',
                'local_sentientia_evaluation');
            message_send($msg);
        }
    }

    /**
     * P1 #30 — compute which question ids are visible given the
     * answer payload so far. A question is visible iff:
     *   - it has no dependency, OR
     *   - its parent is itself visible AND the parent's answer matches.
     *
     * The matching rule:
     *   - depends_on_value is null → "show when parent has any non-empty answer"
     *   - depends_on_value is set  → "show when parent's answer === this value"
     *     (string-equality after both sides are cast to string and trimmed)
     *
     * Parent visibility is computed before the child's because we walk
     * the questions list in order — and `get_questions()` returns them
     * sorted by sortorder ASC, so admins who put a child before its
     * parent in the sort order get the obvious bug (we don't try to
     * topologically sort here; that's an authoring error).
     *
     * @param array $questions  Indexed by sortorder (from get_questions).
     * @param array $answers    Map of qid → raw submitted answer.
     * @return array<int,bool>  qid → visible?
     */
    public static function compute_visibility_map(array $questions,
                                                    array $answers): array {
        // Index questions by id for O(1) parent lookup.
        $byid = [];
        foreach ($questions as $q) {
            $byid[(int) $q->id] = $q;
        }

        $visible = [];
        foreach ($questions as $q) {
            $qid = (int) $q->id;
            $parent_qid = (int) ($q->depends_on_qid ?? 0);
            if ($parent_qid <= 0) {
                $visible[$qid] = true;
                continue;
            }
            // If the parent isn't in the same evaluation (orphaned
            // foreign key after a delete) treat the child as hidden:
            // we can't evaluate the dependency, so showing it would
            // confuse the learner.
            if (!isset($byid[$parent_qid])) {
                $visible[$qid] = false;
                continue;
            }
            // Parent must itself be visible.
            if (empty($visible[$parent_qid])) {
                $visible[$qid] = false;
                continue;
            }
            $parent_raw = $answers[$parent_qid] ?? null;
            if ($parent_raw === null || $parent_raw === ''
                    || (is_array($parent_raw) && empty($parent_raw))) {
                // Parent unanswered → child stays hidden until parent fills.
                $visible[$qid] = false;
                continue;
            }
            $needed = $q->depends_on_value;
            if ($needed === null || $needed === '') {
                // "Any non-empty answer triggers" mode.
                $visible[$qid] = true;
                continue;
            }
            // String-equality match. Multichoice_multi parents pass an
            // array — show child when ANY selected option matches.
            if (is_array($parent_raw)) {
                $visible[$qid] = in_array((string) $needed, array_map(
                    fn($v) => (string) $v, $parent_raw), true);
            } else {
                $visible[$qid] = (trim((string) $parent_raw)
                    === trim((string) $needed));
            }
        }
        return $visible;
    }

    /**
     * Validate a single answer against its question type.
     * @throws \moodle_exception  On invalid answer for a required question
     */
    private static function validate_answer(object $question, $raw) {
        $required = (int) ($question->required ?? 1) === 1;

        if ($raw === null || $raw === '') {
            if ($required) {
                throw new \moodle_exception('answer_required', 'local_sentientia_evaluation',
                    '', $question->questiontext);
            }
            return null;
        }

        switch ($question->questiontype) {
            case 'rating':
                $v = (int) $raw;
                if ($v < 1 || $v > 5) {
                    throw new \moodle_exception('invalid_rating', 'local_sentientia_evaluation',
                        '', $question->questiontext);
                }
                return $v;
            case 'nps':
                $v = (int) $raw;
                if ($v < 0 || $v > 10) {
                    throw new \moodle_exception('invalid_nps', 'local_sentientia_evaluation',
                        '', $question->questiontext);
                }
                return $v;
            case 'yesno':
                $v = strtolower(trim((string) $raw));
                if (!in_array($v, ['yes', 'no', '1', '0', 'true', 'false'], true)) {
                    throw new \moodle_exception('invalid_yesno', 'local_sentientia_evaluation',
                        '', $question->questiontext);
                }
                return ($v === 'yes' || $v === '1' || $v === 'true') ? 'yes' : 'no';
            case 'multichoice':
                $opts = self::decode_options($question->options);
                $raw = trim((string) $raw);
                if (!in_array($raw, $opts, true)) {
                    throw new \moodle_exception('invalid_multichoice', 'local_sentientia_evaluation',
                        '', $question->questiontext);
                }
                return $raw;
            case 'multichoice_multi':
                // P1 #18 — accepts either a real JSON-decoded array
                // (preferred path from the AJAX submit handler) or a
                // delimited string ("A|B|C") as a fallback. Each value
                // must be in the allowed options list.
                $opts = self::decode_options($question->options);
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $raw = $decoded;
                    } else {
                        $raw = array_filter(array_map('trim', explode('|', $raw)),
                            fn($x) => $x !== '');
                    }
                }
                if (!is_array($raw)) {
                    throw new \moodle_exception('invalid_multichoice_multi',
                        'local_sentientia_evaluation', '', $question->questiontext);
                }
                $clean = [];
                foreach ($raw as $v) {
                    $v = trim((string) $v);
                    if ($v === '') continue;
                    if (!in_array($v, $opts, true)) {
                        throw new \moodle_exception('invalid_multichoice_multi',
                            'local_sentientia_evaluation', '', $question->questiontext);
                    }
                    $clean[] = $v;
                }
                if ($required && empty($clean)) {
                    throw new \moodle_exception('answer_required',
                        'local_sentientia_evaluation', '', $question->questiontext);
                }
                // De-duplicate to keep aggregate stats honest.
                return array_values(array_unique($clean));
            case 'numeric':
                // P1 #18 — must parse cleanly to int + respect optional
                // min/max bounds from the question's options JSON.
                if (!is_numeric($raw)) {
                    throw new \moodle_exception('invalid_numeric',
                        'local_sentientia_evaluation', '', $question->questiontext);
                }
                $v = (int) $raw;
                $bounds = self::decode_numeric_bounds($question->options ?? null);
                if ($bounds['min'] !== null && $v < $bounds['min']) {
                    $a = (object) [
                        'q'   => $question->questiontext,
                        'min' => $bounds['min'],
                    ];
                    throw new \moodle_exception('invalid_numeric_below_min',
                        'local_sentientia_evaluation', '', $a);
                }
                if ($bounds['max'] !== null && $v > $bounds['max']) {
                    $a = (object) [
                        'q'   => $question->questiontext,
                        'max' => $bounds['max'],
                    ];
                    throw new \moodle_exception('invalid_numeric_above_max',
                        'local_sentientia_evaluation', '', $a);
                }
                return $v;
            case 'text':
                return trim((string) $raw);
            default:
                return null;
        }
    }

    /**
     * Get aggregate stats for each question (for admin response viewer).
     */
    public static function get_response_stats(int $evaluationid): array {
        global $DB;

        $questions = self::get_questions($evaluationid);
        // Submitted responses only (a trigger shell has no answers to add up).
        $responses = $DB->get_records_select(self::RESPONSES_TABLE,
            'evaluationid = :eid AND timesubmitted > 0', ['eid' => $evaluationid], 'timesubmitted DESC');

        $stats = [];
        foreach ($questions as $q) {
            $stats[$q->id] = self::init_stats_bucket($q);
        }

        foreach ($responses as $r) {
            $data = json_decode($r->response_data, true);
            if (!is_array($data)) continue;
            foreach ($data as $qid => $answer) {
                $qid = (int) $qid;
                if (!isset($stats[$qid])) continue;
                if ($answer === null || $answer === '') continue;
                self::accumulate_stat($stats[$qid], $questions[$qid] ?? null, $answer);
            }
        }

        foreach ($stats as $qid => &$bucket) {
            $q = $questions[$qid] ?? null;
            if (!$q) continue;
            self::finalise_stats($bucket, $q);
        }
        unset($bucket);

        return $stats;
    }

    private static function init_stats_bucket(object $q): array {
        switch ($q->questiontype) {
            case 'rating':
                return ['type' => 'rating', 'count' => 0, 'sum' => 0,
                        'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]];
            case 'nps':
                return ['type' => 'nps', 'count' => 0, 'detractors' => 0,
                        'passives' => 0, 'promoters' => 0, 'sum' => 0];
            case 'yesno':
                return ['type' => 'yesno', 'count' => 0, 'yes' => 0, 'no' => 0];
            case 'multichoice':
                $opts = self::decode_options($q->options);
                $dist = [];
                foreach ($opts as $o) $dist[$o] = 0;
                return ['type' => 'multichoice', 'count' => 0, 'distribution' => $dist];
            case 'multichoice_multi':
                // P1 #18 — same shape as multichoice (per-option distribution)
                // but each response can populate multiple buckets. Track
                // response count (people answering) AND total picks
                // (selections) separately so the analysis surface can
                // distinguish them.
                $opts = self::decode_options($q->options);
                $dist = [];
                foreach ($opts as $o) $dist[$o] = 0;
                return ['type' => 'multichoice_multi', 'count' => 0,
                        'total_picks' => 0, 'distribution' => $dist];
            case 'numeric':
                // P1 #18 — running min/max/sum so finalise can compute avg.
                $bounds = self::decode_numeric_bounds($q->options ?? null);
                // count is of NUMERIC answers only (the average and the bar shares divide by it); a stored answer
                // that is not a number is tallied in non_numeric instead, see accumulate_stat().
                $bucket = ['type' => 'numeric', 'count' => 0, 'sum' => 0,
                        'min_seen' => null, 'max_seen' => null, 'non_numeric' => 0,
                        'bound_min' => $bounds['min'], 'bound_max' => $bounds['max']];
                // A small bounded range also keeps a count per whole value (value => count). It is only shown
                // while every answer so far is a whole number inside the range ('distribution_exact').
                if ($bounds['min'] !== null && $bounds['max'] !== null && $bounds['max'] >= $bounds['min']
                        && $bounds['max'] - $bounds['min'] <= self::NUMERIC_DISTRIBUTION_SPAN) {
                    $bucket['distribution'] = array_fill_keys(range($bounds['min'], $bounds['max']), 0);
                    $bucket['distribution_exact'] = true;
                }
                return $bucket;
            case 'text':
                return ['type' => 'text', 'count' => 0, 'samples' => []];
            default:
                return ['type' => 'unknown', 'count' => 0];
        }
    }

    private static function accumulate_stat(array &$bucket, ?object $q, $answer): void {
        if (!$q) return;
        if ($q->questiontype === 'numeric' && !is_numeric($answer)) {
            // Imported or edited data can hold an answer that is not a number. It cannot be added up, so it stays out
            // of the count, the average and the bars (counting it would pull the average down and leave the bars short
            // of the count), and the page says how many were left out. The bars no longer cover every stored
            // answer, so they are not shown as exact.
            $bucket['non_numeric'] = (int) ($bucket['non_numeric'] ?? 0) + 1;
            if (isset($bucket['distribution'])) {
                $bucket['distribution_exact'] = false;
            }
            return;
        }
        $bucket['count']++;
        switch ($q->questiontype) {
            case 'rating':
                $v = (int) $answer;
                if ($v >= 1 && $v <= 5) {
                    $bucket['sum'] += $v;
                    $bucket['distribution'][$v]++;
                }
                break;
            case 'nps':
                $v = (int) $answer;
                if ($v >= 0 && $v <= 10) {
                    $bucket['sum'] += $v;
                    if ($v <= 6) $bucket['detractors']++;
                    else if ($v <= 8) $bucket['passives']++;
                    else $bucket['promoters']++;
                }
                break;
            case 'yesno':
                if ($answer === 'yes') $bucket['yes']++;
                else if ($answer === 'no') $bucket['no']++;
                break;
            case 'multichoice':
                $a = (string) $answer;
                if (isset($bucket['distribution'][$a])) {
                    $bucket['distribution'][$a]++;
                }
                break;
            case 'multichoice_multi':
                // P1 #18 — `count` is incremented once already (one person
                // answered); we add each selected option to the
                // distribution and bump total_picks for the per-pick rate.
                $picks = is_array($answer) ? $answer
                    : (is_string($answer) && ($d = json_decode($answer, true)) && is_array($d) ? $d : []);
                foreach ($picks as $a) {
                    $a = (string) $a;
                    if (isset($bucket['distribution'][$a])) {
                        $bucket['distribution'][$a]++;
                        $bucket['total_picks']++;
                    }
                }
                break;
            case 'numeric':
                // Not (int): an imported BizLMS answer may be 7.25 (the item allowed decimals), and truncating
                // it here would understate the sum and the average. A native answer is an int, which stays one.
                $v = $answer + 0;
                $bucket['sum'] += $v;
                if ($bucket['min_seen'] === null || $v < $bucket['min_seen']) {
                    $bucket['min_seen'] = $v;
                }
                if ($bucket['max_seen'] === null || $v > $bucket['max_seen']) {
                    $bucket['max_seen'] = $v;
                }
                if (isset($bucket['distribution'])) {
                    $whole = (int) $v;
                    if ($v == $whole && isset($bucket['distribution'][$whole])) {
                        $bucket['distribution'][$whole]++;
                    } else {
                        // 7.25, or a value outside the allowed range: bars would no longer add up to every answer.
                        $bucket['distribution_exact'] = false;
                    }
                }
                break;
            case 'text':
                if (count($bucket['samples']) < 5) {
                    $bucket['samples'][] = (string) $answer;
                }
                break;
        }
    }

    private static function finalise_stats(array &$bucket, object $q): void {
        switch ($q->questiontype) {
            case 'rating':
                $bucket['avg'] = $bucket['count'] > 0
                    ? round($bucket['sum'] / $bucket['count'], 2) : 0;
                break;
            case 'nps':
                if ($bucket['count'] > 0) {
                    $promoter_pct  = ($bucket['promoters']  / $bucket['count']) * 100;
                    $detractor_pct = ($bucket['detractors'] / $bucket['count']) * 100;
                    $bucket['nps_score'] = round($promoter_pct - $detractor_pct);
                    $bucket['avg'] = round($bucket['sum'] / $bucket['count'], 1);
                } else {
                    $bucket['nps_score'] = 0;
                    $bucket['avg'] = 0;
                }
                break;
            case 'yesno':
                $bucket['yes_pct'] = $bucket['count'] > 0
                    ? round(($bucket['yes'] / $bucket['count']) * 100) : 0;
                break;
            case 'multichoice_multi':
                // P1 #18 — average picks per respondent (1.0 = everyone
                // picked exactly one option, 2.5 = avg of 2-3 picks each).
                $bucket['avg_picks'] = $bucket['count'] > 0
                    ? round($bucket['total_picks'] / $bucket['count'], 2) : 0;
                break;
            case 'numeric':
                // P1 #18 — compute avg only if any responses came in;
                // leave min_seen/max_seen as null when count=0 so the
                // analysis surface can render "—" rather than "0".
                $bucket['avg'] = $bucket['count'] > 0
                    ? round($bucket['sum'] / $bucket['count'], 2) : 0;
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Admin response viewer (responses.php): the statistics, ready for the template
    // ═══════════════════════════════════════════════════════════════════

    /**
     * One display row per question for the aggregate view (responses.php), from the buckets get_response_stats()
     * or get_response_stats_filtered() computed. Kept out of the page so it can be tested.
     *
     * Every row carries the flags is_rating, is_nps, is_yesno, is_multichoice, is_multichoice_multi, is_numeric and
     * is_text; the data for a type is present only when somebody answered that question (count > 0), and the
     * template shows its "no answers" text otherwise:
     *  - rating: avg, avg_pct, distribution (level, count, pct, pct_label);
     *  - nps: nps_score, avg, promoters, passives, detractors with their shares, nps_class;
     *  - yesno: yes, no, yes_pct, no_pct;
     *  - multichoice: distribution (option, count, pct);
     *  - multichoice_multi: distribution (option, count, pct) where pct is the share of RESPONDENTS who ticked the
     *    option, respondents, total_picks, avg_picks, summary and share_note;
     *  - numeric: avg (two decimals), min_seen and max_seen (the lowest and highest answers as stored, every
     *    decimal kept), bound_min, bound_max, lowest_highest, range_text (both bounds set only) and, for a small
     *    bounded range whose answers are all whole numbers inside it, has_distribution with distribution (label,
     *    count, pct). response_count is of numeric answers only: a stored answer that is not a number is left out
     *    of every figure, and ignored_note (set whenever one was) says how many; with no number at all the row
     *    carries no statistics;
     *  - text: has_samples with samples.
     *
     * @param \stdClass[] $questions question records (from get_questions)
     * @param array $stats buckets by question id
     * @return array[] list of rows, in question order
     */
    public static function response_question_rows(array $questions, array $stats): array {
        $component = 'local_sentientia_evaluation';
        // {{ }} escapes once, in the template, so the text is not escaped here as well (format_string() would
        // turn a '&' into '&amp;' and the template into '&amp;amp;').
        $text = static fn(string $s): string => format_string($s, true, ['escape' => false]);
        // An average is rounded to two decimals; the lowest and highest answers are real answers, so they are shown as
        // stored (format_float() with -1 keeps every decimal, and strips trailing zeros).
        $number = static fn($n): string => format_float((float) $n, 2, true, true);
        $stored = static fn($n): string => format_float((float) $n, -1, true, true);

        $rows = [];
        $position = 0;
        foreach ($questions as $q) {
            $position++;
            $bucket = $stats[$q->id] ?? ['type' => $q->questiontype, 'count' => 0];
            $count = (int) ($bucket['count'] ?? 0);
            if ($q->questiontype === 'numeric' && ($bucket['min_seen'] ?? null) === null) {
                // No number was given: there is no average, lowest or highest to show, whatever else the bucket holds.
                $count = 0;
            }

            $row = [
                'id'           => $q->id,
                'position'     => $position,
                'questiontext' => $text((string) $q->questiontext),
                'questiontype' => $q->questiontype,
                'required'     => (bool) $q->required,
                // Phase G.2 (2026-05-08) - per-question anonymous flag.
                // Hidden in analysis only - the response_data still contains
                // the responder's userid for audit purposes; the UI just
                // doesn't surface it for this particular question.
                'is_anonymous_question' => (int) ($q->anonymous ?? 0) === 1,
                'response_count' => $count,
                'is_rating'    => ($q->questiontype === 'rating'),
                'is_nps'       => ($q->questiontype === 'nps'),
                'is_yesno'     => ($q->questiontype === 'yesno'),
                'is_multichoice' => ($q->questiontype === 'multichoice'),
                'is_multichoice_multi' => ($q->questiontype === 'multichoice_multi'),
                'is_numeric'   => ($q->questiontype === 'numeric'),
                'is_text'      => ($q->questiontype === 'text'),
            ];

            if ($q->questiontype === 'rating' && $count > 0) {
                $row['avg'] = $bucket['avg'];
                $row['avg_pct'] = round(($bucket['avg'] / 5) * 100);
                $dist_rows = [];
                foreach ($bucket['distribution'] as $val => $n) {
                    $pct = round(($n / $count) * 100);
                    $dist_rows[] = [
                        'level' => $val,
                        'count' => $n,
                        'pct'   => $pct,
                        'pct_label' => $n . ' (' . $pct . '%)',
                    ];
                }
                $row['distribution'] = $dist_rows;
            }

            if ($q->questiontype === 'nps' && $count > 0) {
                $row['nps_score']  = $bucket['nps_score'];
                $row['avg']        = $bucket['avg'];
                $row['promoters']  = $bucket['promoters'];
                $row['passives']   = $bucket['passives'];
                $row['detractors'] = $bucket['detractors'];
                $total = max(1, $count);
                $row['promoter_pct']  = round(($bucket['promoters']  / $total) * 100);
                $row['passive_pct']   = round(($bucket['passives']   / $total) * 100);
                $row['detractor_pct'] = round(($bucket['detractors'] / $total) * 100);
                $row['nps_class'] = $bucket['nps_score'] >= 50 ? 'text-success'
                                  : ($bucket['nps_score'] >= 0 ? 'text-warning' : 'text-danger');
            }

            if ($q->questiontype === 'yesno' && $count > 0) {
                $row['yes']     = $bucket['yes'];
                $row['no']      = $bucket['no'];
                $row['yes_pct'] = $bucket['yes_pct'];
                $row['no_pct']  = 100 - $bucket['yes_pct'];
            }

            if ($q->questiontype === 'multichoice' && $count > 0) {
                $dist_rows = [];
                foreach ($bucket['distribution'] as $opt => $n) {
                    $dist_rows[] = [
                        'option' => $text((string) $opt),
                        'count'  => $n,
                        'pct'    => round(($n / $count) * 100),
                    ];
                }
                $row['distribution'] = $dist_rows;
            }

            if ($q->questiontype === 'multichoice_multi' && $count > 0) {
                // A person can tick several options, so each share is of RESPONDENTS and the shares can add up to
                // more than 100%.
                $dist_rows = [];
                foreach ($bucket['distribution'] as $opt => $n) {
                    $dist_rows[] = [
                        'option' => $text((string) $opt),
                        'count'  => $n,
                        'pct'    => (int) round(($n / $count) * 100),
                    ];
                }
                $row['distribution'] = $dist_rows;
                $row['respondents'] = $count;
                $row['total_picks'] = (int) $bucket['total_picks'];
                $row['avg_picks'] = $number($bucket['avg_picks']);
                $row['summary'] = get_string('responses_multi_summary', $component, (object) [
                    'picks'       => $row['total_picks'],
                    'respondents' => $count,
                    'avg'         => $row['avg_picks'],
                ]);
                $row['share_note'] = get_string('responses_multi_share_note', $component);
            }

            if ($q->questiontype === 'numeric' && !empty($bucket['non_numeric'])) {
                // Shown whether or not any number was given: answers that were left out are worth knowing about.
                $row['ignored_note'] = get_string('responses_numeric_ignored', $component, (int) $bucket['non_numeric']);
            }

            if ($q->questiontype === 'numeric' && $count > 0) {
                $row['avg'] = $number($bucket['avg']);
                $row['min_seen'] = $stored($bucket['min_seen']);
                $row['max_seen'] = $stored($bucket['max_seen']);
                $row['bound_min'] = $bucket['bound_min'];
                $row['bound_max'] = $bucket['bound_max'];
                $row['lowest_highest'] = get_string('responses_numeric_lowest_highest', $component, (object) [
                    'lowest'  => $row['min_seen'],
                    'highest' => $row['max_seen'],
                ]);
                if ($bucket['bound_min'] !== null && $bucket['bound_max'] !== null) {
                    $row['range_text'] = get_string('responses_numeric_range', $component, (object) [
                        'min' => $bucket['bound_min'],
                        'max' => $bucket['bound_max'],
                    ]);
                }
                if (isset($bucket['distribution']) && !empty($bucket['distribution_exact'])) {
                    $dist_rows = [];
                    foreach ($bucket['distribution'] as $value => $n) {
                        $pct = (int) round(($n / $count) * 100);
                        $dist_rows[] = [
                            'label' => (string) $value,
                            'count' => $n,
                            'pct'   => $pct,
                        ];
                    }
                    $row['has_distribution'] = true;
                    $row['distribution'] = $dist_rows;
                }
            }

            if ($q->questiontype === 'text' && !empty($bucket['samples'])) {
                $row['samples'] = array_map(static function ($sample) use ($text) {
                    return ['text' => $text((string) $sample)];
                }, $bucket['samples']);
                $row['has_samples'] = true;
            }

            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * One row per question for the learner-facing form (respond.php), in form order. Kept out of the page so it can
     * be tested.
     *
     *  - position counts 1..n: get_questions() is keyed by question id, so the loop index the page used printed the
     *    id plus one;
     *  - questiontext and the option labels are filtered but not escaped, because the template prints them with
     *    {{ }}, which escapes once (format_string() with its default escaped them twice: "Tom & Jerry" showed as
     *    "Tom &amp; Jerry");
     *  - only the two choice types have options. A number question keeps {min, max} in the same column; those are
     *    its bounds, and used to come out of decode_options() as two options to tick;
     *  - has_numeric_min and has_numeric_max are the template's section keys: a bound of 0 is the text "0", which
     *    Mustache reads as false, so numeric_min itself cannot be the key (the input lost its min="0").
     *
     * @param \stdClass[] $questions question records (from get_questions)
     * @return array[]
     */
    public static function respond_question_rows(array $questions): array {
        // Rating scale: 1-5 with descriptive labels.
        $rating_scale = [
            ['value' => 1, 'label' => 'Strongly Disagree'],
            ['value' => 2, 'label' => 'Disagree'],
            ['value' => 3, 'label' => 'Neutral'],
            ['value' => 4, 'label' => 'Agree'],
            ['value' => 5, 'label' => 'Strongly Agree'],
        ];
        // NPS scale: 0-10.
        $nps_scale = [];
        for ($n = 0; $n <= 10; $n++) {
            $nps_scale[] = ['value' => $n, 'label' => (string) $n];
        }

        $rows = [];
        $position = 0;
        foreach ($questions as $q) {
            $position++;

            $option_rows = [];
            if (in_array($q->questiontype, ['multichoice', 'multichoice_multi'], true)) {
                foreach (self::decode_options($q->options ?? null) as $idx => $opt) {
                    $option_rows[] = [
                        'value'   => $opt,
                        'label'   => self::display_text((string) $opt),
                        'inputid' => 'q-' . $q->id . '-opt-' . $idx,
                    ];
                }
            }

            // P1 #18 — numeric bounds (decoded only when type=numeric).
            $num_bounds = ($q->questiontype === 'numeric')
                ? self::decode_numeric_bounds($q->options ?? null)
                : ['min' => null, 'max' => null];

            $rows[] = [
                'id'              => $q->id,
                'position'        => $position,
                'questiontext'    => self::display_text((string) $q->questiontext),
                'questiontype'    => $q->questiontype,
                'is_rating'       => ($q->questiontype === 'rating'),
                'is_nps'          => ($q->questiontype === 'nps'),
                'is_yesno'        => ($q->questiontype === 'yesno'),
                'is_multichoice'  => ($q->questiontype === 'multichoice'),
                // P1 #18 — both new types surface as flags + share option rows
                // for multichoice_multi.
                'is_multichoice_multi' => ($q->questiontype === 'multichoice_multi'),
                'is_numeric'      => ($q->questiontype === 'numeric'),
                'is_text'         => ($q->questiontype === 'text'),
                'required'        => (bool) $q->required,
                'options'         => $option_rows,
                'rating_scale'    => $rating_scale,
                'nps_scale'       => $nps_scale,
                // Mustache helpers — leave empty string when unset so the
                // template's `<input min/max>` attrs render as the constraint
                // only when present.
                'has_numeric_min' => $num_bounds['min'] !== null,
                'has_numeric_max' => $num_bounds['max'] !== null,
                'numeric_min'     => $num_bounds['min'] !== null ? (string) $num_bounds['min'] : '',
                'numeric_max'     => $num_bounds['max'] !== null ? (string) $num_bounds['max'] : '',
                'numeric_hint'    => $num_bounds['min'] !== null || $num_bounds['max'] !== null
                    ? sprintf('Range: %s to %s',
                        $num_bounds['min'] !== null ? $num_bounds['min'] : '−∞',
                        $num_bounds['max'] !== null ? $num_bounds['max'] : '+∞')
                    : '',
                // P1 #31 (2026-05-20) — dependency wire-up for client show/hide.
                // We emit raw values for the JS to consume; the server-side
                // visibility check happens again in submit_response so a
                // tampered client payload still can't bypass required-when-hidden.
                'has_dependency'   => (int) ($q->depends_on_qid ?? 0) > 0,
                'depends_on_qid'   => (int) ($q->depends_on_qid ?? 0),
                // depends_on_value is intentionally NOT format_string'd — JS
                // compares string-equality against the parent's raw answer,
                // which is the user's literal input. The template emits this
                // via `s()` so the attribute is HTML-escaped safely.
                'depends_on_value' => (string) ($q->depends_on_value ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * One respondent's answers, question by question, each beside how everybody else answered (response_detail.php).
     *
     * response_data is keyed by the BARE question id (submit_response() and the BizLMS import both write it so), and
     * a choice question's options JSON is a plain list; an answer is read as `$data[(int) $q->id]` and the options
     * through decode_options(). Pending trigger shells (timesubmitted 0) are invitations, not responses, so they
     * are left out of the comparison and of the total.
     *
     * Per row: qid, type, text, required, anonymous, my_answer (a tick-all answer joined with ', '; empty when
     * unanswered), has_my_answer, response_count (how many people answered this question) and, by type:
     *  - rating: max_rating, avg and a histogram of levels (is_level, level, count, pct, is_my_choice);
     *  - multichoice, multichoice_multi: a histogram per option (is_option, label, count, pct, is_my_choice) - for
     *    the tick-all type the count is of respondents who ticked the option, so the shares can exceed 100%
     *    together. The template keys its rows on is_level / is_option, never on level / label (an option whose text
     *    is "0" is falsy to Mustache);
     *  - numeric: avg, has_avg and avg_label.
     *
     * @param \stdClass $evaluation the evaluation record
     * @param \stdClass $response the response being shown
     * @return array{questions: array[], total_responses: int}
     */
    public static function response_detail_rows(\stdClass $evaluation, \stdClass $response): array {
        global $DB;
        $text = static fn(string $s): string => format_string($s, true, ['escape' => false]);

        $mine = json_decode((string) ($response->response_data ?: '{}'), true);
        $mine = is_array($mine) ? $mine : [];

        $questions = self::get_questions((int) $evaluation->id);

        // Everybody's submitted answers, by question id. One pass over the responses.
        $values = [];
        $total = 0;
        $submitted = $DB->get_recordset_select(self::RESPONSES_TABLE,
            'evaluationid = :eid AND timesubmitted > 0', ['eid' => (int) $evaluation->id], 'id ASC', 'id, response_data');
        foreach ($submitted as $r) {
            $total++;
            $data = json_decode((string) ($r->response_data ?: '{}'), true);
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $qid => $value) {
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }
                $values[(int) $qid][] = $value;
            }
        }
        $submitted->close();

        $rows = [];
        foreach ($questions as $q) {
            $qid = (int) $q->id;
            $answer = $mine[$qid] ?? null;
            $answered = !($answer === null || $answer === '' || $answer === []);
            $vals = $values[$qid] ?? [];
            $count = count($vals);

            $row = [
                'qid'           => $qid,
                'type'          => $q->questiontype,
                'text'          => $text((string) $q->questiontext),
                'required'      => (bool) $q->required,
                'anonymous'     => (bool) ($q->anonymous ?? 0),
                'my_answer'     => $answered
                    ? (is_array($answer) ? implode(', ', array_map('strval', $answer)) : (string) $answer) : '',
                'has_my_answer' => $answered,
                'response_count' => $count,
            ];

            if ($q->questiontype === 'rating') {
                $row['max_rating'] = (int) (self::decode_options($q->options)['max'] ?? 5);
                $nums = array_filter($vals, 'is_numeric');
                $row['avg'] = $nums ? round(array_sum($nums) / count($nums), 2) : 0;
                $hist = [];
                for ($i = 1; $i <= $row['max_rating']; $i++) {
                    $n = count(array_filter($vals, static fn($v) => is_numeric($v) && (int) $v === $i));
                    $hist[] = [
                        'is_level' => true,
                        'level' => $i,
                        'count' => $n,
                        'pct'   => $count > 0 ? round(100 * $n / $count, 1) : 0,
                        'is_my_choice' => $answered && is_numeric($answer) && (int) $answer === $i,
                    ];
                }
                $row['histogram'] = $hist;
            } else if ($q->questiontype === 'multichoice' || $q->questiontype === 'multichoice_multi') {
                $mineticked = is_array($answer) ? array_map('strval', $answer) : [];
                $hist = [];
                foreach (self::decode_options($q->options) as $option) {
                    $option = (string) $option;
                    $n = 0;
                    foreach ($vals as $v) {
                        if (is_array($v) ? in_array($option, array_map('strval', $v), true) : (string) $v === $option) {
                            $n++;
                        }
                    }
                    // is_option is the template's section key, not label: Mustache reads the text "0" as false, so a
                    // choice whose text is "0" would be dropped from the histogram with the respondent's pick on it.
                    $hist[] = [
                        'is_option' => true,
                        'label' => $text($option),
                        'count' => $n,
                        'pct'   => $count > 0 ? round(100 * $n / $count, 1) : 0,
                        'is_my_choice' => $answered && (is_array($answer)
                            ? in_array($option, $mineticked, true) : (string) $answer === $option),
                    ];
                }
                $row['histogram'] = $hist;
            } else if ($q->questiontype === 'numeric') {
                $nums = array_filter($vals, 'is_numeric');
                $row['avg'] = $nums ? round(array_sum($nums) / count($nums), 2) : 0;
                $row['has_avg'] = (bool) $nums;
                if ($nums) {
                    $row['avg_label'] = get_string('response_detail_numeric_avg', 'local_sentientia_evaluation',
                        format_float((float) $row['avg'], 2, true, true));
                }
            }

            $rows[] = $row;
        }
        return ['questions' => $rows, 'total_responses' => $total];
    }

    // ═══════════════════════════════════════════════════════════════════
    // FILTERED RESPONSES (G-05)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Build a (where_sql, params) pair for response filtering.
     *
     * Recognised filters:
     *   - evaluationid (int)
     *   - date_from, date_to (unix ts) — bounds on timesubmitted
     *   - courseid, programid, classroomid (int) — context match
     *
     * The caller composes these into their own SELECT.
     *
     * Always restricted to SUBMITTED responses (timesubmitted > 0): the pending shell the trigger queue writes
     * for an invited user is not a response, so it is not counted, listed, exported or added up.
     *
     * @param array $filters
     * @return array [string $where, array $params]
     */
    public static function build_response_filter(array $filters): array {
        global $DB;
        $where  = ['r.timesubmitted > 0'];
        $params = [];

        if (!empty($filters['evaluationid'])) {
            $where[] = 'r.evaluationid = :evid';
            $params['evid'] = (int) $filters['evaluationid'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'r.timesubmitted >= :dfrom';
            $params['dfrom'] = (int) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'r.timesubmitted <= :dto';
            $params['dto'] = (int) $filters['date_to'];
        }
        if (!empty($filters['courseid'])) {
            $where[] = 'r.courseid = :cid';
            $params['cid'] = (int) $filters['courseid'];
        }
        if (!empty($filters['programid'])) {
            $where[] = 'r.programid = :pid';
            $params['pid'] = (int) $filters['programid'];
        }
        if (!empty($filters['classroomid'])) {
            $where[] = 'r.classroomid = :crid';
            $params['crid'] = (int) $filters['classroomid'];
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Count responses matching the filter set.
     */
    public static function count_responses_filtered(array $filters): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::RESPONSES_TABLE)) {
            return 0;
        }
        [$where, $params] = self::build_response_filter($filters);
        return (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {" . self::RESPONSES_TABLE . "} r WHERE $where", $params);
    }

    /**
     * Get filtered responses (raw) for a single evaluation. Caller still
     * passes evaluationid in the filter; this enforces it for safety so
     * cross-evaluation queries go through a different path.
     *
     * @return array  Each row: id, evaluationid, userid, courseid,
     *                programid, classroomid, response_data (JSON), timesubmitted
     */
    public static function get_responses_filtered(array $filters,
                                                  int $offset = 0, int $limit = 0): array {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::RESPONSES_TABLE)) {
            return [];
        }
        [$where, $params] = self::build_response_filter($filters);
        $sql = "SELECT r.* FROM {" . self::RESPONSES_TABLE . "} r
                 WHERE $where
              ORDER BY r.timesubmitted DESC, r.id DESC";
        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Get response stats for an evaluation, restricted by the same filter
     * set used elsewhere (date range, course/program/classroom context).
     *
     * Same shape as get_response_stats() but driven by raw responses query.
     */
    public static function get_response_stats_filtered(int $evaluationid, array $filters): array {
        global $DB;

        // Force evaluationid into the filter.
        $filters['evaluationid'] = $evaluationid;

        $questions = self::get_questions($evaluationid);
        $responses = self::get_responses_filtered($filters);

        $stats = [];
        foreach ($questions as $q) {
            $stats[$q->id] = self::init_stats_bucket($q);
        }
        foreach ($responses as $r) {
            $data = json_decode($r->response_data, true);
            if (!is_array($data)) { continue; }
            foreach ($data as $qid => $answer) {
                $qid = (int) $qid;
                if (!isset($stats[$qid])) { continue; }
                if ($answer === null || $answer === '') { continue; }
                self::accumulate_stat($stats[$qid], $questions[$qid] ?? null, $answer);
            }
        }
        foreach ($stats as $qid => &$bucket) {
            $q = $questions[$qid] ?? null;
            if (!$q) { continue; }
            self::finalise_stats($bucket, $q);
        }
        unset($bucket);

        return [
            'response_count' => count($responses),
            'questions'      => $stats,
        ];
    }

    /**
     * Cross-evaluation Kirkpatrick aggregation. Buckets responses by the
     * parent evaluation's `kirkpatrick_level` and returns:
     *   per_level => [
     *     evaluation_count,
     *     response_count,
     *     avg_rating (across all rating qs),
     *     avg_nps   (across all nps qs),
     *   ]
     *
     * Optional filter: same shape as build_response_filter (date_from,
     * date_to, courseid, programid, classroomid).
     */
    public static function get_kirkpatrick_summary(array $filters = []): array {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::TABLE)) {
            return [];
        }

        $summary = [];
        foreach (array_keys(self::KIRKPATRICK_LEVELS) as $level) {
            $summary[$level] = [
                'level'            => $level,
                'level_label'      => self::KIRKPATRICK_LEVELS[$level],
                'evaluation_count' => 0,
                'response_count'   => 0,
                'rating_sum'       => 0,
                'rating_count'     => 0,
                'avg_rating'       => 0,
                'nps_sum'          => 0,
                'nps_count'        => 0,
                'avg_nps'          => 0,
                'nps_promoters'    => 0,
                'nps_detractors'   => 0,
                'nps_score'        => 0,
            ];
        }

        // ADR-031: both halves of the summary cover only the evaluations the
        // current user may see (cross-tenant: all; tenant admin: their own
        // tenant's; no tenant: none). This used to aggregate every tenant.
        [$scopesql, $scopeparams] = self::scope_sql('e');

        // Count evaluations per level (no response filter — these are top-level).
        $eval_counts = $DB->get_records_sql(
            "SELECT e.kirkpatrick_level AS lvl, COUNT(*) AS c
               FROM {" . self::TABLE . "} e
              WHERE {$scopesql}
              GROUP BY e.kirkpatrick_level", $scopeparams);
        foreach ($eval_counts as $row) {
            $lvl = (int) $row->lvl;
            if (isset($summary[$lvl])) {
                $summary[$lvl]['evaluation_count'] = (int) $row->c;
            }
        }

        // Walk responses, decoding answers and accumulating per-question stats
        // into the right Kirkpatrick bucket.
        if (!$dbman->table_exists(self::RESPONSES_TABLE)) {
            return $summary;
        }

        [$where, $params] = self::build_response_filter($filters);

        $sql = "SELECT r.id, r.response_data, e.kirkpatrick_level
                  FROM {" . self::RESPONSES_TABLE . "} r
                  JOIN {" . self::TABLE . "} e ON e.id = r.evaluationid
                 WHERE $where AND {$scopesql}";
        $rs = $DB->get_recordset_sql($sql, array_merge($params, $scopeparams));

        // Cache question types per evaluation to avoid N+1 lookups.
        $qcache = [];
        foreach ($rs as $row) {
            $lvl = (int) $row->kirkpatrick_level;
            if (!isset($summary[$lvl])) { continue; }
            $summary[$lvl]['response_count']++;

            $answers = json_decode($row->response_data, true);
            if (!is_array($answers)) { continue; }

            foreach ($answers as $qid => $answer) {
                $qid = (int) $qid;
                if (!isset($qcache[$qid])) {
                    $qcache[$qid] = $DB->get_field(self::QUESTIONS_TABLE,
                        'questiontype', ['id' => $qid]);
                }
                $qtype = $qcache[$qid] ?? null;
                if (!$qtype) { continue; }

                if ($qtype === 'rating') {
                    $v = (int) $answer;
                    if ($v >= 1 && $v <= 5) {
                        $summary[$lvl]['rating_sum'] += $v;
                        $summary[$lvl]['rating_count']++;
                    }
                } else if ($qtype === 'nps') {
                    $v = (int) $answer;
                    if ($v >= 0 && $v <= 10) {
                        $summary[$lvl]['nps_sum'] += $v;
                        $summary[$lvl]['nps_count']++;
                        if ($v <= 6) {
                            $summary[$lvl]['nps_detractors']++;
                        } else if ($v >= 9) {
                            $summary[$lvl]['nps_promoters']++;
                        }
                    }
                }
            }
        }
        $rs->close();

        // Finalise averages + NPS scores per level.
        foreach ($summary as $lvl => &$row) {
            if ($row['rating_count'] > 0) {
                $row['avg_rating'] = round($row['rating_sum'] / $row['rating_count'], 2);
            }
            if ($row['nps_count'] > 0) {
                $row['avg_nps'] = round($row['nps_sum'] / $row['nps_count'], 1);
                $promoter_pct  = ($row['nps_promoters']  / $row['nps_count']) * 100;
                $detractor_pct = ($row['nps_detractors'] / $row['nps_count']) * 100;
                $row['nps_score'] = round($promoter_pct - $detractor_pct);
            }
        }
        unset($row);

        return $summary;
    }

    /**
     * Build a CSV-friendly row representation of a single response.
     *
     * The row has one column per question (in sortorder) plus context
     * columns (Date, User, Email, Course, Program, Classroom).
     * Anonymous evaluations leave User/Email blank.
     *
     * 2026-09-25: for an evaluation whose respondents are protected
     * ({@see self::identity_protected()} - anonymous now, answered
     * anonymously before, or with an anonymous question) every row hides
     * the respondent, and the Submitted column is the DAY, not the minute:
     * a minute-exact time is what matches an anonymous answer to a name.
     *
     * @param object    $response  raw response row from DB
     * @param array     $questions ordered question records (from get_questions)
     * @param object    $eval      parent evaluation record
     * @param bool|null $identityprotected identity_protected($eval), when the
     *                  caller has it already (exportcsv.php, once per export);
     *                  null = work it out here
     * @param bool      $withsubject true to add a Subject cell after Email: the person a supervisor evaluation is
     *                  about ({@see self::shows_subject()}). It goes with csv_header_row()'s $withsubject, and is
     *                  empty for a response with no subject and on a protected evaluation. Default false, so the
     *                  layout of every native form's export is unchanged.
     * @param array|null $subjectnames {@see self::subject_names()} for every response of the export (exportcsv.php
     *                  reads them in one query); null = look each subject up as the row is built
     * @param array|null $respondents {@see self::respondent_records()} for every response of the export (exportcsv.php
     *                  reads them in one query, and only for a form that is not protected); null = look each
     *                  respondent up as the row is built
     * @return array  row of strings
     */
    public static function response_to_csv_row(object $response, array $questions,
                                                object $eval, ?bool $identityprotected = null,
                                                bool $withsubject = false, ?array $subjectnames = null,
                                                ?array $respondents = null): array {
        global $DB;

        // Phase G.2 (2026-05-08) — when any question in the form is
        // anonymous, hide the responder identity for the whole row to
        // prevent correlation attacks. Otherwise honour the eval-level
        // anonymous flag.
        $any_anonymous_q = false;
        foreach ($questions as $q) {
            if ((int) ($q->anonymous ?? 0) === 1) {
                $any_anonymous_q = true;
                break;
            }
        }
        $protected = $any_anonymous_q
            || ($identityprotected ?? self::identity_protected($eval));

        $row = [];
        $row[] = self::submitted_label((int) $response->timesubmitted, $protected, true);

        if ((int) $eval->anonymous === 1 || (int) $response->userid === 0) {
            $row[] = '(anonymous)';
            $row[] = '';
        } else if ($any_anonymous_q) {
            $row[] = '(question-anonymous)';
            $row[] = '';
        } else if ($protected) {
            // A named row in an evaluation that collected anonymous answers
            // before its flag was switched off: naming it would single out
            // the unnamed rows by elimination.
            $row[] = '(anonymous)';
            $row[] = '';
        } else {
            // One query for the whole export when the caller read the respondents (exportcsv.php); otherwise this
            // row's own lookup. Either way the record has every name field, so fullname() has what it may ask for.
            $u = $respondents !== null
                ? ($respondents[(int) $response->userid] ?? false)
                : \core_user::get_user((int) $response->userid, self::respondent_fields());
            $row[] = $u ? fullname($u) : '(deleted user)';
            $row[] = $u ? $u->email : '';
        }

        // The person a supervisor evaluation is about. Never on a protected evaluation, whatever the row holds.
        if ($withsubject) {
            $row[] = $protected ? '' : self::subject_label($response->subject_userid ?? null, $subjectnames);
        }

        // Context columns.
        $row[] = $response->courseid    ? (string) $response->courseid    : '';
        $row[] = $response->programid   ? (string) $response->programid   : '';
        $row[] = $response->classroomid ? (string) $response->classroomid : '';

        // Per-question answers in the canonical sort order.
        $answers = json_decode((string) $response->response_data, true);
        if (!is_array($answers)) {
            $answers = [];
        }
        foreach ($questions as $q) {
            $a = $answers[$q->id] ?? '';
            if (is_array($a)) {
                $a = implode(' | ', array_map('strval', $a));
            }
            $row[] = (string) $a;
        }

        return $row;
    }

    /**
     * The names of the people some responses are about, for the Subject column of the response list and the CSV.
     *
     * One query for all of them, and fullname() over every name field, so the list and the export print the same
     * name for the same person (the site's name format applies to both). An account that is deleted, or gone, has
     * no entry: the caller says "deleted user" in its own words (a lang string on the page, the plain text the CSV's
     * Respondent column already uses).
     *
     * @param array $subjectids user ids (null, 0 and duplicates are ignored)
     * @return array<int, string> user id => full name
     */
    public static function subject_names(array $subjectids): array {
        global $DB;
        $ids = self::positive_user_ids($subjectids);
        if (!$ids) {
            return [];
        }
        // Every name field, so fullname() has what the site's name format may ask for.
        $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
        $names = [];
        foreach (array_chunk(array_values($ids), 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'subj');
            $users = $DB->get_records_select('user', "deleted = 0 AND id $insql", $params, '', $fields);
            foreach ($users as $user) {
                $names[(int) $user->id] = fullname($user);
            }
        }
        return $names;
    }

    /**
     * The user fields that name a respondent: the id, the email and every name field, so fullname() has what the
     * site's name format may ask for (in developer mode it reports a user object that lacks one).
     *
     * @return string
     */
    private static function respondent_fields(): string {
        return 'id, email, ' . implode(', ', \core_user\fields::get_name_fields());
    }

    /**
     * The respondents of some responses, for the Respondent and Email columns of the CSV, in one query.
     *
     * The records core_user::get_user() gave one row at a time: a deleted account is still here, with its name
     * (response_to_csv_row() says "(deleted user)" only for an account that is gone). Pass the map to
     * response_to_csv_row() as its last argument; the response list reads its respondents in its own query.
     *
     * @param array $userids user ids (null, 0 and duplicates are ignored)
     * @return array<int, \stdClass> user id => record with id, email and every name field
     */
    public static function respondent_records(array $userids): array {
        global $DB;
        $ids = self::positive_user_ids($userids);
        $records = [];
        foreach (array_chunk(array_values($ids), 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'resp');
            $users = $DB->get_records_select('user', "id $insql", $params, '', self::respondent_fields());
            foreach ($users as $user) {
                $records[(int) $user->id] = $user;
            }
        }
        return $records;
    }

    /**
     * The distinct user ids above 0 among some values (a column read off the responses: null, 0 and repeats are
     * dropped).
     *
     * @param array $values
     * @return array<int, int> id => id
     */
    private static function positive_user_ids(array $values): array {
        $ids = [];
        foreach ($values as $id) {
            if ($id !== null && (int) $id > 0) {
                $ids[(int) $id] = (int) $id;
            }
        }
        return $ids;
    }

    /**
     * The name of the person a response is about, for the CSV's Subject column.
     *
     * @param int|string|null $subjectid responses.subject_userid
     * @param array<int, string>|null $names {@see self::subject_names()} for every subject of the export, so an
     *        export makes one user query rather than one per row; null = look this one person up
     * @return string '' when the response has no subject; '(deleted user)' when that account is gone or deleted
     */
    public static function subject_label($subjectid, ?array $names = null): string {
        if ($subjectid === null || (int) $subjectid <= 0) {
            return '';
        }
        $subjectid = (int) $subjectid;
        $names ??= self::subject_names([$subjectid]);
        return $names[$subjectid] ?? '(deleted user)';
    }

    /**
     * The rows of the individual responses list (response_list.php), newest first.
     *
     * Only SUBMITTED responses: an invited user's pending shell (timesubmitted 0) is not a response. On a protected
     * evaluation ($protected, {@see self::identity_protected()}) the respondent is not named and the time is the
     * day; the Subject (the person a supervisor form is about) is named only when $showsubject says the form has
     * one ({@see self::shows_subject()}) and the form is not protected (a protected form names nobody, whatever the
     * caller passes). Respondent and Subject are both named through fullname(), as the CSV does.
     *
     * @param \stdClass $evaluation
     * @param bool $protected identity_protected($evaluation)
     * @param bool $showsubject shows_subject($evaluation, $protected)
     * @return array[] id, subject_name, submitted_at, user_name, user_email, employee_id, context, detail_url
     */
    public static function response_list_rows(\stdClass $evaluation, bool $protected, bool $showsubject): array {
        global $DB;
        // Defence in depth: shows_subject() already says no for a protected form, and so does this.
        $showsubject = $showsubject && !$protected;
        // Every name field, so fullname() can apply the site's name format (the CSV's Respondent column does).
        $namefields = 'u.' . implode(', u.', \core_user\fields::get_name_fields());
        $responses = $DB->get_records_sql(
            "SELECT r.id, r.userid, r.subject_userid, r.courseid, r.programid, r.classroomid, r.timesubmitted,
                    $namefields, u.email, u.open_employeeid
               FROM {" . self::RESPONSES_TABLE . "} r
          LEFT JOIN {user} u ON u.id = r.userid
              WHERE r.evaluationid = :eid
                AND r.timesubmitted > 0
           ORDER BY r.timesubmitted DESC, r.id DESC",
            ['eid' => (int) $evaluation->id]);

        $subjectnames = $showsubject ? self::subject_names(array_column($responses, 'subject_userid')) : [];
        $gone = get_string('responses_subject_deleted', 'local_sentientia_evaluation');
        $anonymous = get_string('eval_response_responder_anonymous', 'local_sentientia_evaluation');

        $rows = [];
        foreach ($responses as $r) {
            // '' when the response has no subject (as in the CSV); the "deleted user" text when that account is gone
            // or deleted.
            $subjectname = '';
            if ($showsubject && (int) ($r->subject_userid ?? 0) > 0) {
                $subjectname = $subjectnames[(int) $r->subject_userid] ?? $gone;
            }
            $rows[] = [
                'id'           => (int) $r->id,
                'subject_name' => $subjectname,
                'submitted_at' => self::submitted_label((int) $r->timesubmitted, $protected),
                // fullname() is '' for a respondent whose account is gone (every name field is null).
                'user_name'    => $protected ? $anonymous : fullname($r),
                'user_email'   => $protected ? '' : (string) ($r->email ?? ''),
                'employee_id'  => $protected ? '' : (string) ($r->open_employeeid ?? ''),
                'context'      => ($r->courseid > 0)    ? "course #$r->courseid"
                               : (($r->programid > 0)   ? "program #$r->programid"
                               : (($r->classroomid > 0) ? "classroom #$r->classroomid" : '—')),
                'detail_url'   => (new \moodle_url('/local/sentientia_evaluation/response_detail.php',
                    ['id' => $r->id]))->out(false),
            ];
        }
        return $rows;
    }

    /**
     * CSV header row matching response_to_csv_row().
     *
     * Every header is plain English, the Subject one too: a CSV is read by spreadsheets and scripts, so its headers
     * do not change with the language of whoever exports it.
     *
     * @param array $questions ordered question records (from get_questions)
     * @param bool $withsubject true to add the Subject column after Email ({@see self::shows_subject()}); it must
     *             be passed to response_to_csv_row() as well
     * @return array
     */
    public static function csv_header_row(array $questions, bool $withsubject = false): array {
        $header = ['Submitted', 'Respondent', 'Email'];
        if ($withsubject) {
            $header[] = 'Subject';
        }
        array_push($header, 'Course ID', 'Program ID', 'Classroom ID');
        $i = 1;
        foreach ($questions as $q) {
            $label = 'Q' . $i . ': ' . trim((string) $q->questiontext);
            // Trim down to keep column header readable.
            if (mb_strlen($label) > 80) {
                $label = mb_substr($label, 0, 77) . '...';
            }
            $header[] = $label;
            $i++;
        }
        return $header;
    }
}
