<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD for editable instructional-design templates (P0.3 #2).
 *
 * A template captures the structure + tone a course should follow. Trainers
 * create / edit / archive their own and their tenant's; a :manage_all holder
 * who is also cross-tenant sees every tenant's (ADR-031). Every write
 * populates customerid + costcenterid (from the actor's open_path) and
 * timecreated + timemodified, per .claude/rules/database.md.
 *
 * Built-in starter templates (is_builtin=1) are seeded on install and shared
 * by every tenant. They are NOT deletable, so a trainer always has a working
 * starting point, and since ADR-031 (2026-09-25) only a cross-tenant caller
 * may edit one: a rewrite changes the template every tenant generates from.
 *
 * All reads are tenant-scoped: a scoped caller only sees templates they own,
 * that belong to their tenant root, or that are built-in. "Shared" is decided
 * by is_builtin, not costcenterid 0 - a tenantless author's private template
 * is also stamped 0, and used to be published to every tenant that way.
 *
 * @package local_sentientia_authoring
 */
class template_manager {

    public const TABLE = 'local_sentientia_auth_template';

    /** Max field lengths (defensive, mirrors the schema). */
    public const MAX_NAME_LEN = 200;
    public const MAX_BODY_LEN = 20000;

    /**
     * Resolve the BizLMS tenant root from a user's open_path.
     *
     * @param \stdClass|null $user A user with open_path. NULL = global $USER.
     * @return int
     */
    public static function tenant_root_for(?\stdClass $user = null): int {
        global $USER;
        // ADR-031: the platform resolver (numeric root only; '' = 0 = none).
        return \local_sentientia_platform\tenant::root_for_user($user ?? $USER);
    }

    /**
     * Create a template owned by $ownerid.
     *
     * @param int         $ownerid
     * @param string      $name
     * @param string      $body
     * @param string|null $description
     * @param string|null $structurejson Optional JSON outline.
     * @param bool        $isbuiltin     Only the installer passes true.
     * @return int New template id.
     */
    public static function create(int $ownerid, string $name, string $body,
            ?string $description = null, ?string $structurejson = null, bool $isbuiltin = false): int {
        global $DB;

        $name = trim($name);
        if ($name === '') {
            throw new \invalid_parameter_exception('Template name is required');
        }
        if (trim($body) === '') {
            throw new \invalid_parameter_exception('Template body is required');
        }
        if (mb_strlen($name) > self::MAX_NAME_LEN) {
            $name = mb_substr($name, 0, self::MAX_NAME_LEN);
        }

        $owner = $ownerid > 0
            ? $DB->get_record('user', ['id' => $ownerid], 'id, open_path', MUST_EXIST)
            : null;
        $now = time();

        $record = new \stdClass();
        $record->ownerid        = $ownerid;
        $record->customerid     = 1; // Phase 1: hardcoded Airpay.
        $record->costcenterid   = $isbuiltin ? 0 : self::tenant_root_for($owner);
        $record->name           = $name;
        $record->description    = $description !== null && trim($description) !== '' ? $description : null;
        $record->body           = $body;
        $record->structure_json = $structurejson !== null && trim($structurejson) !== '' ? $structurejson : null;
        $record->is_builtin     = $isbuiltin ? 1 : 0;
        $record->archived       = 0;
        $record->timecreated    = $now;
        $record->timemodified   = $now;

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update an existing template. Only the provided fields are changed.
     *
     * @param int   $templateid
     * @param array $updates Keys: name, description, body, structure_json.
     * @return void
     */
    public static function update(int $templateid, array $updates): void {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['id' => $templateid], '*', MUST_EXIST);

        $record = new \stdClass();
        $record->id = $existing->id;

        if (array_key_exists('name', $updates)) {
            $name = trim((string) $updates['name']);
            if ($name === '') {
                throw new \invalid_parameter_exception('Template name cannot be blank');
            }
            $record->name = mb_substr($name, 0, self::MAX_NAME_LEN);
        }
        if (array_key_exists('body', $updates)) {
            $body = (string) $updates['body'];
            if (trim($body) === '') {
                throw new \invalid_parameter_exception('Template body cannot be blank');
            }
            $record->body = $body;
        }
        if (array_key_exists('description', $updates)) {
            $d = trim((string) $updates['description']);
            $record->description = $d === '' ? null : $d;
        }
        if (array_key_exists('structure_json', $updates)) {
            $s = trim((string) $updates['structure_json']);
            $record->structure_json = $s === '' ? null : $s;
        }
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Archive (soft-delete) a template. Built-ins cannot be archived.
     *
     * @param int $templateid
     * @return void
     */
    public static function archive(int $templateid): void {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['id' => $templateid], '*', MUST_EXIST);
        if ((int) $existing->is_builtin === 1) {
            throw new \moodle_exception('err_template_builtin', 'local_sentientia_authoring');
        }
        $record = new \stdClass();
        $record->id           = $existing->id;
        $record->archived     = 1;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Hard-delete a template. Built-ins cannot be deleted. Drafts that
     * referenced it keep their templateid (FK is nullable at the schema level
     * but we leave the recorded value for audit — only the picker hides it).
     *
     * @param int $templateid
     * @return void
     */
    public static function delete(int $templateid): void {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['id' => $templateid], '*', MUST_EXIST);
        if ((int) $existing->is_builtin === 1) {
            throw new \moodle_exception('err_template_builtin', 'local_sentientia_authoring');
        }
        $DB->delete_records(self::TABLE, ['id' => $templateid]);
    }

    /**
     * Load a single template, tenant-scoped to the actor.
     *
     * @param int       $templateid
     * @param \stdClass $actor
     * @param bool      $manageall
     * @return \stdClass|null Null if missing OR out of the actor's scope.
     */
    public static function load_for_actor(int $templateid, \stdClass $actor, bool $manageall): ?\stdClass {
        global $DB;
        $tpl = $DB->get_record(self::TABLE, ['id' => $templateid]);
        if (!$tpl) {
            return null;
        }
        if (draft_manager::is_unscoped($actor, $manageall)) {
            return $tpl;
        }
        $root = self::tenant_root_for($actor);
        // Visible if: owned by actor, OR a shared built-in, OR same tenant.
        // ownerid 0 (built-in, or an author anonymised by a privacy erasure)
        // never makes anyone the owner - not even a caller whose id is 0.
        // ADR-031: tenant 0 is nobody's tenant, so it matches no one.
        $isowner = (int) $actor->id > 0 && (int) $tpl->ownerid === (int) $actor->id;
        if ($isowner
                || (int) $tpl->is_builtin === 1
                || ($root > 0 && (int) $tpl->costcenterid === $root)) {
            return $tpl;
        }
        return null;
    }

    /**
     * ADR-031: may the actor CHANGE (edit / archive) this template?
     *
     * Seeing a template is not licence to rewrite it. A built-in is shared by
     * every tenant, so only a cross-tenant caller may edit it - before
     * 2026-09-25 any trainer in any tenant could rewrite the body every other
     * tenant generates from. A tenant template may be changed by its owner or
     * within its tenant; any tenant's by an unscoped caller (is_unscoped()).
     *
     * @param \stdClass $tpl A template row (from load_for_actor())
     * @param \stdClass $actor
     * @param bool      $manageall Whether the actor holds :manage_all
     * @return bool
     */
    public static function can_edit(\stdClass $tpl, \stdClass $actor, bool $manageall): bool {
        $actorid = (int) ($actor->id ?? 0);
        if ($actorid <= 0) {
            return false;
        }
        if ((int) $tpl->is_builtin === 1) {
            return \local_sentientia_platform\tenant::is_cross_tenant($actorid);
        }
        if (draft_manager::is_unscoped($actor, $manageall)) {
            return true;
        }
        if ((int) $tpl->ownerid === $actorid) {
            return true;
        }
        $root = self::tenant_root_for($actor);
        return $root > 0 && (int) $tpl->costcenterid === $root;
    }

    /**
     * List active (non-archived) templates visible to the actor.
     *
     * @param \stdClass $actor
     * @param bool      $manageall
     * @return \stdClass[]
     */
    public static function list_for_actor(\stdClass $actor, bool $manageall): array {
        global $DB;
        if (draft_manager::is_unscoped($actor, $manageall)) {
            return array_values($DB->get_records(self::TABLE, ['archived' => 0], 'is_builtin DESC, name ASC'));
        }
        $root = self::tenant_root_for($actor);
        // ownerid > 0: see load_for_actor() - ownerid 0 is nobody.
        // ADR-031: built-ins are shared by is_builtin, and tenant 0 matches no
        // one, so a tenantless author's template no longer reaches every tenant.
        $params = ['uid' => (int) $actor->id];
        $tenantsql = '';
        if ($root > 0) {
            $tenantsql = ' OR costcenterid = :cid';
            $params['cid'] = $root;
        }
        return array_values($DB->get_records_sql(
            "SELECT * FROM {" . self::TABLE . "}
              WHERE archived = 0
                AND ((ownerid = :uid AND ownerid > 0) OR is_builtin = 1{$tenantsql})
           ORDER BY is_builtin DESC, name ASC",
            $params
        ));
    }

    /**
     * Seed the built-in starter templates on install. Idempotent — does
     * nothing if any built-in already exists.
     *
     * @return void
     */
    public static function seed_builtins(): void {
        global $DB;
        if ($DB->record_exists(self::TABLE, ['is_builtin' => 1])) {
            return;
        }

        $builtins = [
            [
                'name' => 'Compliance micro-module',
                'description' => 'Hook → 3 concept cards → scenario card → 5-question mixed assessment. For regulatory / policy training.',
                'body' => "Structure: 1 scenario hook card, then 3 concept cards, then 1 scenario application card. "
                    . "Assessment: 5 questions mixing multichoice, mrq, and match. Tone: precise, compliance-grade, no ambiguity. "
                    . "Mastery score: 70. Emphasise what the learner MUST do and the consequence of non-compliance.",
                'structure' => json_encode([
                    'num_cards' => 5,
                    'question_mix' => ['multichoice' => 2, 'mrq' => 2, 'match' => 1],
                    'mastery_score' => 70,
                ]),
            ],
            [
                'name' => 'Product knowledge flip-deck',
                'description' => 'Flip cards for term/definition recall + a short match assessment. For product / feature onboarding.',
                'body' => "Structure: 4 flip cards (front = term/feature, back = plain-language meaning), then 1 concept summary card. "
                    . "Assessment: 3 questions — 1 match-the-following (term to definition), 2 multichoice. Tone: friendly, concrete examples. "
                    . "Mastery score: 70.",
                'structure' => json_encode([
                    'num_cards' => 5,
                    'question_mix' => ['multichoice' => 2, 'mrq' => 0, 'match' => 1],
                    'mastery_score' => 70,
                ]),
            ],
            [
                'name' => 'Skill how-to walkthrough',
                'description' => 'Step-by-step example cards + a select-all-that-apply check. For procedural / tool skills.',
                'body' => "Structure: 1 concept card framing the goal, then 3 example cards walking the steps, then 1 scenario card. "
                    . "Assessment: 4 questions — 2 mrq (select all correct steps), 2 multichoice. Tone: practical, action-oriented. "
                    . "Mastery score: 70.",
                'structure' => json_encode([
                    'num_cards' => 5,
                    'question_mix' => ['multichoice' => 2, 'mrq' => 2, 'match' => 0],
                    'mastery_score' => 70,
                ]),
            ],
        ];

        foreach ($builtins as $b) {
            self::create(0, $b['name'], $b['body'], $b['description'], $b['structure'], true);
        }
    }
}
