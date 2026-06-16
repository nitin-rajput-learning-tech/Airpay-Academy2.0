<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence + review lifecycle for course drafts (cards + questions).
 *
 * All writes go through this class so customerid + costcenterid are always
 * populated from the actor, timecreated + timemodified are always set, and
 * status transitions follow the defined lifecycle.
 *
 * DRAFT lifecycle:
 *   pending → generated → approved → published
 *                      ↘ rejected
 *                      ↘ failed (on API error)
 *
 * CARD / QUESTION lifecycle:
 *   generated → approved | edited | rejected
 *
 * The mandatory human-review gate: a draft can only reach `published` after a
 * reviewer has finalised it (≥1 approved/edited card). Nothing generated is
 * ever auto-published.
 *
 * @package local_sentientia_authoring
 */
class draft_manager {

    public const DRAFT_TABLE     = 'local_sentientia_auth_draft';
    public const CARD_TABLE      = 'local_sentientia_auth_card';
    public const QUESTION_TABLE  = 'local_sentientia_auth_question';
    public const VOICEOVER_TABLE = 'local_sentientia_auth_voiceover';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_FAILED    = 'failed';

    public const ITEM_GENERATED = 'generated';
    public const ITEM_APPROVED  = 'approved';
    public const ITEM_EDITED    = 'edited';
    public const ITEM_REJECTED  = 'rejected';

    /**
     * Resolve the BizLMS tenant root from a user's open_path.
     *
     * @param \stdClass|null $user A user with open_path. NULL = global $USER.
     * @return int
     */
    public static function tenant_root_for(?\stdClass $user = null): int {
        global $USER;
        $u = $user ?? $USER;
        $parts = explode('/', trim((string) ($u->open_path ?? ''), '/'));
        return (int) ($parts[0] ?? 0);
    }

    /**
     * Create a pending draft row before the AI call goes out, so a crash
     * mid-call leaves an audit trail.
     *
     * @param int      $ownerid
     * @param string   $title
     * @param string   $sourcetext
     * @param string   $sourcetype   prompt | doc | pdf
     * @param string   $targetlang
     * @param string   $model
     * @param int      $masteryscore
     * @param int|null $templateid
     * @param string   $promptversion
     * @return int Draft id.
     */
    public static function create_pending(int $ownerid, string $title, string $sourcetext,
            string $sourcetype, string $targetlang, string $model, int $masteryscore,
            ?int $templateid = null, string $promptversion = prompt_builder::VERSION_V1): int {
        global $DB;

        $owner = $DB->get_record('user', ['id' => $ownerid], 'id, open_path', MUST_EXIST);
        $now = time();

        $version = trim($promptversion);
        if ($version === '') {
            $version = prompt_builder::VERSION_V1;
        }
        if (strlen($version) > 32) {
            $version = substr($version, 0, 32);
        }
        if (!in_array($sourcetype, ['prompt', 'doc', 'pdf'], true)) {
            $sourcetype = 'prompt';
        }
        if ($masteryscore < 0 || $masteryscore > 100) {
            $masteryscore = 70;
        }

        $record = new \stdClass();
        $record->ownerid            = $ownerid;
        $record->customerid         = 1; // Phase 1: hardcoded Airpay.
        $record->costcenterid       = self::tenant_root_for($owner);
        $record->templateid         = $templateid !== null && $templateid > 0 ? $templateid : null;
        $record->title              = $title === '' ? 'Untitled module' : $title;
        $record->sourcetype         = $sourcetype;
        $record->sourcetext         = $sourcetext;
        $record->targetlang         = $targetlang === '' ? 'en' : $targetlang;
        $record->model              = $model;
        $record->prompt_version     = $version;
        $record->mastery_score      = $masteryscore;
        $record->num_cards          = 0;
        $record->num_questions      = 0;
        $record->tokens_in          = 0;
        $record->tokens_out         = 0;
        $record->status             = self::STATUS_PENDING;
        $record->error_detail       = null;
        $record->reviewed_by        = null;
        $record->reviewed_at        = null;
        $record->published_courseid = null;
        $record->generated_at       = 0;
        $record->timecreated        = $now;
        $record->timemodified       = $now;

        return $DB->insert_record(self::DRAFT_TABLE, $record);
    }

    /**
     * Persist generation results (cards + questions) into a draft in one txn.
     *
     * On success: status → generated, counts set, tokens recorded, cards +
     * questions inserted with sortorder 1..N. On zero output (parser returned
     * nothing usable): status → failed.
     *
     * @param int          $draftid
     * @param \stdClass[]  $cards     response_parser ->cards
     * @param \stdClass[]  $questions response_parser ->questions
     * @param int          $tokensin
     * @param int          $tokensout
     * @param string       $mode      mock | live
     * @return void
     */
    public static function persist_generation(int $draftid, array $cards, array $questions,
            int $tokensin, int $tokensout, string $mode): void {
        global $DB;

        $txn = $DB->start_delegated_transaction();
        try {
            $draft = $DB->get_record(self::DRAFT_TABLE, ['id' => $draftid], '*', MUST_EXIST);
            $now = time();

            $draft->tokens_in     = $tokensin;
            $draft->tokens_out    = $tokensout;
            $draft->generated_at  = $now;
            $draft->timemodified  = $now;
            $draft->num_cards     = count($cards);
            $draft->num_questions = count($questions);

            if (count($cards) === 0 && count($questions) === 0) {
                $draft->status = self::STATUS_FAILED;
                $draft->error_detail = "parser_no_output (mode={$mode})";
                $DB->update_record(self::DRAFT_TABLE, $draft);
                $txn->allow_commit();
                return;
            }

            $draft->status = self::STATUS_GENERATED;
            $draft->error_detail = null;
            $DB->update_record(self::DRAFT_TABLE, $draft);

            $sort = 1;
            foreach ($cards as $c) {
                $row = new \stdClass();
                $row->draftid       = $draftid;
                $row->cardtype      = isset($c->cardtype) ? (string) $c->cardtype : 'concept';
                $row->heading       = isset($c->heading) ? (string) $c->heading : '';
                $row->body          = isset($c->body) ? (string) $c->body : '';
                $row->flip_back     = isset($c->flip_back) ? $c->flip_back : null;
                $row->narration     = isset($c->narration) ? $c->narration : null;
                $row->sortorder     = $sort++;
                $row->status        = self::ITEM_GENERATED;
                $row->reviewer_note = null;
                $row->timecreated   = $now;
                $row->timemodified  = $now;
                $DB->insert_record(self::CARD_TABLE, $row);
            }

            $sort = 1;
            foreach ($questions as $q) {
                $row = new \stdClass();
                $row->draftid             = $draftid;
                $row->qtype               = isset($q->qtype) ? (string) $q->qtype : 'multichoice';
                $row->qtext               = isset($q->qtext) ? (string) $q->qtext : '';
                $row->qoptions_json       = isset($q->qoptions_json) ? (string) $q->qoptions_json : null;
                $row->qanswer             = isset($q->qanswer) ? (string) $q->qanswer : '';
                $row->qfeedback_correct   = $q->qfeedback_correct ?? null;
                $row->qfeedback_incorrect = $q->qfeedback_incorrect ?? null;
                $row->qexplanation        = $q->qexplanation ?? null;
                $row->points              = 1;
                $row->sortorder           = $sort++;
                $row->status              = self::ITEM_GENERATED;
                $row->reviewer_note       = null;
                $row->timecreated         = $now;
                $row->timemodified        = $now;
                $DB->insert_record(self::QUESTION_TABLE, $row);
            }

            $txn->allow_commit();
        } catch (\Throwable $e) {
            $txn->rollback($e);
        }
    }

    /**
     * Mark a draft failed (no cards/questions persisted).
     *
     * @param int    $draftid
     * @param string $errordetail Short tag — NEVER include API key text.
     * @return void
     */
    public static function mark_failed(int $draftid, string $errordetail): void {
        global $DB;
        $now = time();
        $update = new \stdClass();
        $update->id           = $draftid;
        $update->status       = self::STATUS_FAILED;
        $update->error_detail = substr($errordetail, 0, 1000);
        $update->generated_at = $now;
        $update->timemodified = $now;
        $DB->update_record(self::DRAFT_TABLE, $update);
    }

    /**
     * Update a single card's review status (+ optional content edits).
     *
     * @param int    $cardid
     * @param string $newstatus One of ITEM_*.
     * @param array  $updates   Optional: heading, body, flip_back, narration, reviewer_note.
     * @return void
     */
    public static function review_card(int $cardid, string $newstatus, array $updates = []): void {
        global $DB;
        self::assert_item_status($newstatus);
        $now = time();
        $update = new \stdClass();
        $update->id           = $cardid;
        $update->status       = $newstatus;
        $update->timemodified = $now;
        foreach (['heading', 'body', 'flip_back', 'narration', 'reviewer_note'] as $f) {
            if (array_key_exists($f, $updates)) {
                $update->{$f} = $updates[$f];
            }
        }
        $DB->update_record(self::CARD_TABLE, $update);
    }

    /**
     * Update a single question's review status (+ optional content edits).
     * When the reviewer edits structural fields, the result is re-validated
     * through question_type::normalise() so a manual edit can't persist an
     * invalid MRQ/match.
     *
     * @param int    $questionid
     * @param string $newstatus One of ITEM_*.
     * @param array  $updates   Optional: qtext, qoptions_json, qanswer,
     *                          qfeedback_correct, qfeedback_incorrect,
     *                          qexplanation, points, reviewer_note.
     * @return void
     */
    public static function review_question(int $questionid, string $newstatus, array $updates = []): void {
        global $DB;
        self::assert_item_status($newstatus);
        $now = time();
        $update = new \stdClass();
        $update->id           = $questionid;
        $update->status       = $newstatus;
        $update->timemodified = $now;
        foreach (['qtext', 'qoptions_json', 'qanswer', 'qfeedback_correct',
                  'qfeedback_incorrect', 'qexplanation', 'points', 'reviewer_note'] as $f) {
            if (array_key_exists($f, $updates)) {
                $update->{$f} = $updates[$f];
            }
        }
        $DB->update_record(self::QUESTION_TABLE, $update);
    }

    /**
     * @param string $status
     * @throws \coding_exception
     */
    private static function assert_item_status(string $status): void {
        $allowed = [self::ITEM_APPROVED, self::ITEM_EDITED, self::ITEM_REJECTED, self::ITEM_GENERATED];
        if (!in_array($status, $allowed, true)) {
            throw new \coding_exception("Invalid item status: {$status}");
        }
    }

    /**
     * Finalise the review: draft → approved if ≥1 card is approved/edited,
     * else rejected. Records reviewer + timestamp. This is the gate before
     * any publish step.
     *
     * @param int $draftid
     * @param int $reviewerid
     * @return string New draft status.
     */
    public static function finalise_review(int $draftid, int $reviewerid): string {
        global $DB;
        $now = time();

        $usablecards = $DB->count_records_select(self::CARD_TABLE,
            'draftid = :did AND status IN (:s1, :s2)',
            ['did' => $draftid, 's1' => self::ITEM_APPROVED, 's2' => self::ITEM_EDITED]);

        $newstatus = $usablecards > 0 ? self::STATUS_APPROVED : self::STATUS_REJECTED;

        $update = new \stdClass();
        $update->id           = $draftid;
        $update->status       = $newstatus;
        $update->reviewed_by  = $reviewerid;
        $update->reviewed_at  = $now;
        $update->timemodified = $now;
        $DB->update_record(self::DRAFT_TABLE, $update);

        return $newstatus;
    }

    /**
     * Mark a reviewed draft as published once a real course has been built.
     * Only an approved draft can be published — enforces the human-review gate.
     *
     * @param int $draftid
     * @param int $courseid
     * @return void
     * @throws \moodle_exception when the draft is not in `approved` status.
     */
    public static function mark_published(int $draftid, int $courseid): void {
        global $DB;
        $draft = $DB->get_record(self::DRAFT_TABLE, ['id' => $draftid], '*', MUST_EXIST);
        if ($draft->status !== self::STATUS_APPROVED) {
            throw new \moodle_exception('err_publish_not_approved', 'local_sentientia_authoring');
        }
        $now = time();
        $update = new \stdClass();
        $update->id                 = $draftid;
        $update->status             = self::STATUS_PUBLISHED;
        $update->published_courseid = $courseid;
        $update->timemodified       = $now;
        $DB->update_record(self::DRAFT_TABLE, $update);
    }

    /**
     * Record a voiceover job for a card.
     *
     * @param int    $draftid
     * @param int    $cardid
     * @param array  $result  tts_client::synthesize() output.
     * @param string $lang
     * @return int Voiceover row id.
     */
    public static function record_voiceover(int $draftid, int $cardid, array $result, string $lang): int {
        global $DB;
        $draft = $DB->get_record(self::DRAFT_TABLE, ['id' => $draftid], '*', MUST_EXIST);
        $now = time();

        $row = new \stdClass();
        $row->draftid      = $draftid;
        $row->cardid       = $cardid;
        $row->customerid   = (int) $draft->customerid;
        $row->costcenterid = (int) $draft->costcenterid;
        $row->voice_id     = (string) ($result['voice_id'] ?? 'mock');
        $row->lang         = $lang === '' ? 'en' : $lang;
        $row->charcount    = (int) ($result['charcount'] ?? 0);
        $row->audio_ref    = $result['audio_ref'] ?? null;
        $row->mode         = ($result['mode'] ?? 'mock') === 'live' ? 'live' : 'mock';
        $row->status       = ($result['mode'] ?? 'mock') === 'failed' ? 'failed' : 'ready';
        $row->error_detail = isset($result['error']) ? substr((string) $result['error'], 0, 1000) : null;
        $row->timecreated  = $now;
        $row->timemodified = $now;

        return $DB->insert_record(self::VOICEOVER_TABLE, $row);
    }

    /**
     * Load a draft with its cards + questions + voiceovers, tenant-scoped.
     *
     * @param int       $draftid
     * @param \stdClass $actor
     * @param bool      $manageall
     * @return \stdClass|null Null when missing OR out of the actor's scope.
     */
    public static function load_for_actor(int $draftid, \stdClass $actor, bool $manageall): ?\stdClass {
        global $DB;
        $draft = $DB->get_record(self::DRAFT_TABLE, ['id' => $draftid]);
        if (!$draft) {
            return null;
        }
        $actorroot = self::tenant_root_for($actor);
        if (!$manageall) {
            if ((int) $draft->ownerid !== (int) $actor->id
                    && (int) $draft->costcenterid !== $actorroot) {
                return null;
            }
        }
        $out = new \stdClass();
        $out->draft      = $draft;
        $out->cards      = array_values($DB->get_records(self::CARD_TABLE, ['draftid' => $draftid], 'sortorder ASC'));
        $out->questions  = array_values($DB->get_records(self::QUESTION_TABLE, ['draftid' => $draftid], 'sortorder ASC'));
        $out->voiceovers = array_values($DB->get_records(self::VOICEOVER_TABLE, ['draftid' => $draftid], 'timecreated ASC'));
        return $out;
    }

    /**
     * List recent drafts owned by an actor (or all if manage_all).
     *
     * @param \stdClass $actor
     * @param bool      $manageall
     * @param int       $limit
     * @return \stdClass[]
     */
    public static function list_for_actor(\stdClass $actor, bool $manageall, int $limit = 50): array {
        global $DB;
        if ($manageall) {
            return array_values($DB->get_records(self::DRAFT_TABLE, [], 'timecreated DESC', '*', 0, $limit));
        }
        $tenant = self::tenant_root_for($actor);
        return array_values($DB->get_records_sql(
            "SELECT * FROM {" . self::DRAFT_TABLE . "}
              WHERE ownerid = :uid OR costcenterid = :cid
           ORDER BY timecreated DESC",
            ['uid' => (int) $actor->id, 'cid' => $tenant], 0, $limit));
    }

    /**
     * Sum tokens used by an actor today — drives the per-user soft cap.
     *
     * @param int $userid
     * @return int
     */
    public static function tokens_used_today(int $userid): int {
        global $DB;
        $today = strtotime('today');
        return (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(tokens_in + tokens_out), 0)
               FROM {" . self::DRAFT_TABLE . "}
              WHERE ownerid = :uid AND timecreated >= :since",
            ['uid' => $userid, 'since' => $today]);
    }
}
