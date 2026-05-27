<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * Orchestration + persistence layer for AI content translation.
 *
 * Responsibilities:
 *   - Build the protected-term list + dispatch the Anthropic call
 *   - Apply the deterministic brand-override post-processing pass
 *   - Persist the translation row through its status lifecycle
 *   - Enforce tenant ownership on load + save
 *
 * Phase T.0 status lifecycle:
 *
 *   pending → translated → saved
 *                       ↘ discarded
 *                       ↘ failed (on API error / parser-zero)
 *
 * All writes set customerid + costcenterid from the actor and stamp
 * timecreated + timemodified.
 *
 * @package local_sentientia_translate
 */
class translate_engine {

    public const TABLE = 'local_sentientia_tr_log';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_TRANSLATED = 'translated';
    public const STATUS_SAVED      = 'saved';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_DISCARDED  = 'discarded';

    /**
     * Resolve the BizLMS tenant root (1, 77, 177, ...) from a user's open_path.
     *
     * @param \stdClass|null $user
     * @return int
     */
    public static function tenant_root_for(?\stdClass $user = null): int {
        global $USER;
        $u = $user ?? $USER;
        $path = isset($u->open_path) ? (string)$u->open_path : '';
        $parts = explode('/', trim($path, '/'));
        return (int)($parts[0] ?? 0);
    }

    /**
     * SHA-1 dedup / translation-memory key for (source, target).
     *
     * @param string $sourcetext
     * @param string $targetlang
     * @return string
     */
    public static function source_hash(string $sourcetext, string $targetlang): string {
        return sha1($targetlang . "\n" . trim($sourcetext));
    }

    /**
     * Create a pending translation row before the API call goes out.
     *
     * @param int    $ownerid
     * @param string $title
     * @param string $sourcetext
     * @param string $targetlang
     * @param string $model
     * @return int Row id
     */
    public static function create_pending(int $ownerid, string $title, string $sourcetext,
                                          string $targetlang, string $model): int {
        global $DB;

        $owner = $DB->get_record('user', ['id' => $ownerid], 'id, open_path', MUST_EXIST);
        $now = time();

        $row = new \stdClass();
        $row->ownerid             = $ownerid;
        $row->customerid          = 1;  // Phase 1: hardcoded Airpay.
        $row->costcenterid        = self::tenant_root_for($owner);
        $row->sourcehash          = self::source_hash($sourcetext, $targetlang);
        $row->sourcelang          = 'en';
        $row->targetlang          = $targetlang;
        $row->title               = $title === '' ? 'Untitled translation' : $title;
        $row->sourcetext          = $sourcetext;
        $row->translatedtext      = null;
        $row->model               = $model;
        $row->prompt_version      = prompt_builder::VERSION;
        $row->brand_terms_applied = 0;
        $row->tokens_in           = 0;
        $row->tokens_out          = 0;
        $row->mode                = 'mock';
        $row->status              = self::STATUS_PENDING;
        $row->error_detail        = null;
        $row->generated_at        = 0;
        $row->timecreated         = $now;
        $row->timemodified        = $now;

        return $DB->insert_record(self::TABLE, $row);
    }

    /**
     * Run a translation end-to-end: dispatch -> parse -> brand post-process
     * -> persist. Returns the resulting result object for the UI to render
     * a diff.
     *
     * @param int    $rowid       The pending row to fill in
     * @param string $sourcetext
     * @param string $targetlang
     * @param int    $customerid
     * @param string $model
     * @return \stdClass {status, translatedtext, brand_terms_applied, mode, tokens_in, tokens_out, error}
     */
    public static function run(int $rowid, string $sourcetext, string $targetlang,
                               int $customerid, string $model): \stdClass {
        $protected = brand_manager::get_protected_terms($customerid);

        $api = anthropic_client::generate($sourcetext, $targetlang, $protected, $model);

        if ($api['mode'] === 'failed') {
            self::mark_failed($rowid, (string)$api['error']);
            return (object)[
                'status'              => self::STATUS_FAILED,
                'translatedtext'      => null,
                'brand_terms_applied' => 0,
                'mode'                => 'failed',
                'tokens_in'           => 0,
                'tokens_out'          => 0,
                'error'               => (string)$api['error'],
            ];
        }

        $parsed = response_parser::parse($api['body']);
        if ($parsed === null) {
            self::mark_failed($rowid, 'parser_no_translation');
            return (object)[
                'status'              => self::STATUS_FAILED,
                'translatedtext'      => null,
                'brand_terms_applied' => 0,
                'mode'                => $api['mode'],
                'tokens_in'           => (int)$api['tokens_in'],
                'tokens_out'          => (int)$api['tokens_out'],
                'error'               => 'parser_no_translation',
            ];
        }

        // Deterministic brand-override post-processing — guarantees brand
        // rendering regardless of what the model returned.
        [$finaltext, $applied] = brand_manager::apply_for(
            $parsed->translated_text, $customerid, $targetlang);

        self::store_translation($rowid, $finaltext, $applied,
            (int)$api['tokens_in'], (int)$api['tokens_out'], $api['mode']);

        return (object)[
            'status'              => self::STATUS_TRANSLATED,
            'translatedtext'      => $finaltext,
            'brand_terms_applied' => $applied,
            'mode'                => $api['mode'],
            'tokens_in'           => (int)$api['tokens_in'],
            'tokens_out'          => (int)$api['tokens_out'],
            'error'               => null,
        ];
    }

    /**
     * Persist a completed translation onto a pending row, flipping status
     * to `translated`.
     *
     * @param int    $rowid
     * @param string $translatedtext
     * @param int    $brandapplied
     * @param int    $tokensin
     * @param int    $tokensout
     * @param string $mode
     * @return void
     */
    public static function store_translation(int $rowid, string $translatedtext, int $brandapplied,
                                             int $tokensin, int $tokensout, string $mode): void {
        global $DB;
        $now = time();
        $upd = new \stdClass();
        $upd->id                  = $rowid;
        $upd->translatedtext      = $translatedtext;
        $upd->brand_terms_applied = $brandapplied;
        $upd->tokens_in           = $tokensin;
        $upd->tokens_out          = $tokensout;
        $upd->mode                = $mode === 'live' ? 'live' : 'mock';
        $upd->status              = self::STATUS_TRANSLATED;
        $upd->error_detail        = null;
        $upd->generated_at        = $now;
        $upd->timemodified        = $now;
        $DB->update_record(self::TABLE, $upd);
    }

    /**
     * Mark a translation row as failed.
     *
     * @param int    $rowid
     * @param string $errordetail Short tag — NEVER include API key text
     * @return void
     */
    public static function mark_failed(int $rowid, string $errordetail): void {
        global $DB;
        $now = time();
        $upd = new \stdClass();
        $upd->id           = $rowid;
        $upd->status       = self::STATUS_FAILED;
        $upd->error_detail = substr($errordetail, 0, 1000);
        $upd->generated_at = $now;
        $upd->timemodified = $now;
        $DB->update_record(self::TABLE, $upd);
    }

    /**
     * Accept a translated row (admin clicked "Save" after reviewing the diff).
     *
     * @param int $rowid
     * @param int $actorid Ownership / capability check happens at the UI.
     * @return bool
     */
    public static function accept(int $rowid, int $actorid): bool {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => $rowid], 'id, status', IGNORE_MISSING);
        if (!$row || $row->status !== self::STATUS_TRANSLATED) {
            return false;
        }
        $upd = new \stdClass();
        $upd->id           = $rowid;
        $upd->status       = self::STATUS_SAVED;
        $upd->timemodified = time();
        $DB->update_record(self::TABLE, $upd);
        return true;
    }

    /**
     * Discard a translated row (admin rejected the diff).
     *
     * @param int $rowid
     * @param int $actorid
     * @return bool
     */
    public static function discard(int $rowid, int $actorid): bool {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => $rowid], 'id, status', IGNORE_MISSING);
        if (!$row) {
            return false;
        }
        $upd = new \stdClass();
        $upd->id           = $rowid;
        $upd->status       = self::STATUS_DISCARDED;
        $upd->timemodified = time();
        $DB->update_record(self::TABLE, $upd);
        return true;
    }

    /**
     * Load a translation row scoped to the actor's tenant.
     *
     * Returns null if the row doesn't exist OR the actor lacks access
     * (different tenant AND no manage_all cap).
     *
     * @param int       $rowid
     * @param \stdClass $actor
     * @param bool      $manageall
     * @return \stdClass|null
     */
    public static function load_for_actor(int $rowid, \stdClass $actor, bool $manageall): ?\stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => $rowid], '*', IGNORE_MISSING);
        if (!$row) {
            return null;
        }
        if (!$manageall) {
            $actorroot = self::tenant_root_for($actor);
            if ((int)$row->ownerid !== (int)$actor->id
                && (int)$row->costcenterid !== $actorroot) {
                return null;
            }
        }
        return $row;
    }

    /**
     * List recent translations for an actor (or all if manage_all).
     *
     * @param \stdClass $actor
     * @param bool      $manageall
     * @param int       $limit
     * @return \stdClass[]
     */
    public static function list_for_actor(\stdClass $actor, bool $manageall, int $limit = 50): array {
        global $DB;
        if ($manageall) {
            return array_values($DB->get_records(self::TABLE, [], 'timecreated DESC', '*', 0, $limit));
        }
        $tenant = self::tenant_root_for($actor);
        return array_values($DB->get_records_sql(
            "SELECT * FROM {" . self::TABLE . "}
              WHERE ownerid = :uid OR costcenterid = :cid
           ORDER BY timecreated DESC",
            ['uid' => (int)$actor->id, 'cid' => $tenant],
            0, $limit
        ));
    }

    /**
     * Sum tokens used by translation for an entire customer, today.
     * Drives the per-customer daily cost cap.
     *
     * @param int $customerid
     * @return int
     */
    public static function tokens_used_today_for_customer(int $customerid): int {
        global $DB;
        $today = strtotime('today');
        return (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(tokens_in + tokens_out), 0)
               FROM {" . self::TABLE . "}
              WHERE customerid = :cid AND timecreated >= :since",
            ['cid' => $customerid, 'since' => $today]
        );
    }
}
